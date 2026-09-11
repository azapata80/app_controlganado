<?php
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/rules.php';
require_once __DIR__ . '/periods.php';

function month_bounds(string $month): ?array {
    if (!preg_match('/^\d{4}-\d{2}$/',$month)) return null;
    $start = DateTimeImmutable::createFromFormat('!Y-m-d',$month.'-01');
    if (!$start || $start->format('Y-m') !== $month) return null;
    return [$start->format('Y-m-d'),$start->modify('last day of this month')->format('Y-m-d')];
}

function active_animals_as_of(PDO $pdo,string $asOf): array {
    $sql = "SELECT a.*,
                   COALESCE((SELECT t.to_group_id FROM transfers t WHERE t.animal_id=a.id AND t.transfer_date<=? ORDER BY t.transfer_date DESC,t.id DESC LIMIT 1),
                            (SELECT h.group_id FROM animal_history h WHERE h.animal_id=a.id AND h.action_type IN ('BIRTH','PURCHASE','CREATED') ORDER BY h.action_date,h.id LIMIT 1),a.group_id) report_group_id,
                   g.name group_name,l.name location_name
              FROM animals a
              JOIN cattle_groups g ON g.id=COALESCE((SELECT t.to_group_id FROM transfers t WHERE t.animal_id=a.id AND t.transfer_date<=? ORDER BY t.transfer_date DESC,t.id DESC LIMIT 1),
                                                     (SELECT h.group_id FROM animal_history h WHERE h.animal_id=a.id AND h.action_type IN ('BIRTH','PURCHASE','CREATED') ORDER BY h.action_date,h.id LIMIT 1),a.group_id)
              LEFT JOIN locations l ON l.id=a.location_id
             WHERE a.acquisition_date<=?
               AND NOT EXISTS(SELECT 1 FROM animal_events e WHERE e.animal_id=a.id AND e.event_type='MUERTE' AND e.event_date<=?)
               AND NOT EXISTS(SELECT 1 FROM sales s WHERE s.animal_id=a.id AND s.sale_date<=?)
             ORDER BY g.name,a.tag";
    $stmt = $pdo->prepare($sql); $stmt->execute([$asOf,$asOf,$asOf,$asOf,$asOf]);
    return $stmt->fetchAll();
}

function monthly_weight_report(PDO $pdo,string $month): array {
    $bounds = month_bounds($month);
    if (!$bounds) throw new InvalidArgumentException('El mes no es válido.');
    [$start,$end] = $bounds;
    $animals = active_animals_as_of($pdo,$end);
    $rules = load_business_rules($pdo,$end);
    $weightStmt = $pdo->prepare('SELECT weight_date,weight_kg,source FROM weights WHERE animal_id=? AND weight_date<=? ORDER BY weight_date DESC,id DESC LIMIT 2');
    $details = []; $covered = 0; $underTarget = 0; $stale = 0;
    foreach ($animals as $animal) {
        $weightStmt->execute([$animal['id'],$end]); $weights = $weightStmt->fetchAll();
        $latest = $weights[0] ?? null; $previous = $weights[1] ?? null;
        $baseline = $animal['birth_date'] ? null : ['weight_date'=>$animal['acquisition_date'],'weight_kg'=>$animal['initial_weight_kg']];
        $expected = projected_weight($animal,$rules,$baseline,$end);
        $actual = $latest ? (float)$latest['weight_kg'] : null;
        $deviation = $actual === null ? null : $actual-$expected;
        $dailyGain = null;
        if ($latest && $previous) {
            $days = signed_days_between($previous['weight_date'],$latest['weight_date']);
            if ($days>0) $dailyGain = ((float)$latest['weight_kg']-(float)$previous['weight_kg'])/$days;
        }
        $age = $latest ? signed_days_between($latest['weight_date'],$end) : null;
        $isCovered = $latest && $latest['weight_date'] >= $start;
        $isStale = !$latest || $age>(int)$rules['max_weight_age_days'];
        $isUnder = $deviation !== null && $deviation<0;
        if ($isCovered) $covered++;
        if ($isStale) $stale++;
        if ($isUnder) $underTarget++;
        $details[] = compact('animal','latest','previous','expected','actual','deviation','dailyGain','age','isCovered','isStale','isUnder');
    }
    $total = count($animals);
    return ['month'=>$month,'start'=>$start,'end'=>$end,'total'=>$total,'covered'=>$covered,
        'coverage'=>$total?($covered/$total*100):0.0,'under_target'=>$underTarget,'stale'=>$stale,'details'=>$details];
}

function parse_weight_csv(string $path): array {
    $handle = fopen($path,'rb');
    if (!$handle) throw new RuntimeException('No fue posible leer el archivo.');
    $firstLine = fgets($handle);
    if ($firstLine===false) { fclose($handle); return []; }
    $delimiter = substr_count($firstLine,';')>substr_count($firstLine,',')?';':',';
    rewind($handle);
    $header = fgetcsv($handle,0,$delimiter);
    if (!$header) { fclose($handle); return []; }
    $aliases = ['tag'=>'tag','arete'=>'tag','weight_date'=>'weight_date','fecha'=>'weight_date','weight_kg'=>'weight_kg','peso_kg'=>'weight_kg','source'=>'source','fuente'=>'source','external_reference'=>'external_reference','referencia'=>'external_reference'];
    $keys = array_map(function($value)use($aliases){$value=strtolower(trim((string)$value," \t\n\r\0\x0B\xEF\xBB\xBF"));return $aliases[$value]??$value;},$header);
    foreach (['tag','weight_date','weight_kg'] as $required) if (!in_array($required,$keys,true)) throw new RuntimeException("Falta la columna obligatoria {$required}.");
    $rows=[]; $line=1;
    while (($values=fgetcsv($handle,0,$delimiter))!==false) {
        $line++;
        if (count($values)===1 && trim((string)$values[0])==='') continue;
        if (count($rows)>=5000) { fclose($handle); throw new RuntimeException('El máximo permitido es de 5.000 filas por archivo.'); }
        $values=array_pad($values,count($keys),'');
        $row=array_combine($keys,array_slice($values,0,count($keys)));
        $row['_line']=$line; $rows[]=$row;
    }
    fclose($handle); return $rows;
}

function validate_weight_rows(PDO $pdo,array $rows,?string $forcedSource=null): array {
    $animals=$pdo->query('SELECT id,tag,group_id,acquisition_date FROM animals')->fetchAll();
    $byTag=[]; foreach($animals as $animal)$byTag[$animal['tag']]=$animal;
    $duplicateStmt=$pdo->prepare('SELECT COUNT(*) FROM weights WHERE animal_id=? AND weight_date=? AND source=?');
    $statusStmt=$pdo->prepare("SELECT (SELECT COUNT(*) FROM animal_events WHERE animal_id=? AND event_type='MUERTE' AND event_date<=?)+(SELECT COUNT(*) FROM sales WHERE animal_id=? AND sale_date<=?)");
    $groupStmt=$pdo->prepare("SELECT COALESCE((SELECT t.to_group_id FROM transfers t WHERE t.animal_id=? AND t.transfer_date<=? ORDER BY t.transfer_date DESC,t.id DESC LIMIT 1),(SELECT h.group_id FROM animal_history h WHERE h.animal_id=? AND h.action_type IN ('BIRTH','PURCHASE','CREATED') ORDER BY h.action_date,h.id LIMIT 1),?)");
    $valid=[];$invalid=[];$seen=[];
    foreach($rows as $index=>$row){
        $line=$row['_line']??($index+1);$errors=[];$tag=trim((string)($row['tag']??''));$animal=$byTag[$tag]??null;
        $date=trim((string)($row['weight_date']??''));$weight=$row['weight_kg']??null;
        $source=$forcedSource?:strtoupper(trim((string)($row['source']??'IMPORTACION')));
        if(!$animal)$errors[]='Arete inexistente.';
        if(!date_not_future($date))$errors[]='Fecha inválida o futura.';
        if(!positive_number($weight))$errors[]='Peso inválido.';
        if(!value_in($source,['MANUAL','SISTEMA_EXISTENTE','IMPORTACION']))$errors[]='Fuente inválida.';
        if($animal&&valid_iso_date($date)&&$date<$animal['acquisition_date'])$errors[]='Fecha anterior al ingreso del animal.';
        if($animal&&valid_iso_date($date)){$statusStmt->execute([$animal['id'],$date,$animal['id'],$date]);if((int)$statusStmt->fetchColumn()>0)$errors[]='Animal no activo en esa fecha.';}
        $key=$animal?($animal['id'].'|'.$date.'|'.$source):($tag.'|'.$date.'|'.$source);
        if(isset($seen[$key]))$errors[]='Duplicado dentro del lote.';$seen[$key]=true;
        if($animal&&valid_iso_date($date)&&value_in($source,['MANUAL','SISTEMA_EXISTENTE','IMPORTACION'])){$duplicateStmt->execute([$animal['id'],$date,$source]);if((int)$duplicateStmt->fetchColumn()>0)$errors[]='Ya existe en la base de datos.';}
        $groupId=null;if($animal&&valid_iso_date($date)){$groupStmt->execute([$animal['id'],$date,$animal['id'],$animal['group_id']]);$groupId=(int)$groupStmt->fetchColumn();}
        $normalized=['line'=>$line,'animal_id'=>$animal['id']??null,'group_id'=>$groupId,'tag'=>$tag,'weight_date'=>$date,'weight_kg'=>(float)$weight,'source'=>$source,'external_reference'=>trim((string)($row['external_reference']??''))];
        if(strlen($normalized['external_reference'])>120)$errors[]='La referencia excede 120 caracteres.';
        if($errors){$normalized['errors']=$errors;$invalid[]=$normalized;}else $valid[]=$normalized;
    }
    return ['valid'=>$valid,'invalid'=>$invalid,'total'=>count($rows)];
}

function import_weight_rows(PDO $pdo,array $rows,string $channel,string $sourceName): int {
    if(!$rows)throw new InvalidArgumentException('No hay filas para importar.');
    assert_rows_in_open_period($pdo,$rows,'weight_date');
    $pdo->beginTransaction();
    try{
        $batch=$pdo->prepare("INSERT INTO weight_import_batches(channel,source_name,status,total_rows,imported_rows,rejected_rows,created_by) VALUES(?,?,'IMPORTED',?,?,0,'administración')");
        $batch->execute([$channel,substr($sourceName,0,255),count($rows),count($rows)]);$batchId=(int)$pdo->lastInsertId();
        $insert=$pdo->prepare('INSERT INTO weights(animal_id,group_id,weight_date,weight_kg,source,external_reference,import_batch_id) VALUES(?,?,?,?,?,?,?)');
        foreach($rows as $row)$insert->execute([$row['animal_id'],$row['group_id'],$row['weight_date'],$row['weight_kg'],$row['source'],$row['external_reference']?:null,$batchId]);
        $pdo->commit();return $batchId;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
