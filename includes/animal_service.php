<?php
require_once __DIR__ . '/rules.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/periods.php';

function latest_weight_as_of(PDO $pdo, int $animalId, string $asOf): ?array {
    $stmt = $pdo->prepare('SELECT weight_kg,weight_date FROM weights WHERE animal_id=? AND weight_date<=? ORDER BY weight_date DESC,id DESC LIMIT 1');
    $stmt->execute([$animalId, $asOf]);
    return $stmt->fetch() ?: null;
}

function animal_valuation_as_of(PDO $pdo, array $animal, string $asOf): array {
    $rules = load_business_rules($pdo, $asOf);
    $weight = latest_weight_as_of($pdo, (int)$animal['id'], $asOf);
    $projected = projected_weight($animal, $rules, $weight, $asOf);
    return [
        'weight_kg' => round($projected, 2),
        'value_crc' => round(livestock_value($projected, $rules), 2),
        'last_weight' => $weight,
        'rule_set_id' => $rules['_rule_set_id'] ?? null,
        'rule_version' => $rules['_rule_version'] ?? 'DEFAULT-FALLBACK',
    ];
}

function add_animal_history(PDO $pdo, int $animalId, string $actionType, string $actionDate, array $snapshot, ?float $weight, ?float $value, ?int $ruleSetId, array $details = []): void {
    $stmt = $pdo->prepare('INSERT INTO animal_history(animal_id,action_type,action_date,group_id,location_id,status,weight_kg,value_crc,rule_set_id,details,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?)');
    $stmt->execute([$animalId,$actionType,$actionDate,$snapshot['group_id'],$snapshot['location_id']?:null,$snapshot['status'],$weight,$value,$ruleSetId,json_encode($details,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'administración']);
}

function create_animal(PDO $pdo, array $data): int {
    $origin = $data['origin'];
    $acquisitionDate = $origin === 'NACIMIENTO' ? $data['birth_date'] : $data['acquisition_date'];
    $rules = load_business_rules($pdo, $acquisitionDate);
    $initialWeight = $origin === 'NACIMIENTO' ? (float)$rules['birth_weight_kg'] : (float)$data['initial_weight_kg'];
    $purchaseValue = $origin === 'COMPRA' ? (float)$data['purchase_value'] : null;
    $initialValue = $purchaseValue ?? livestock_value($initialWeight, $rules);
    assert_period_open($pdo,$acquisitionDate);

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO animals(tag,group_id,location_id,sex,birth_date,acquisition_date,initial_weight_kg,purchase_value,status,origin) VALUES(?,?,?,?,?,?,?,?,'ACTIVO',?)");
        $stmt->execute([trim($data['tag']),$data['group_id'],$data['location_id']?:null,$data['sex'],$data['birth_date']?:null,$acquisitionDate,$initialWeight,$purchaseValue,$origin]);
        $animalId = (int)$pdo->lastInsertId();

        $weight = $pdo->prepare("INSERT INTO weights(animal_id,group_id,weight_date,weight_kg,source) VALUES(?,?,?,?, 'MANUAL')");
        $weight->execute([$animalId,$data['group_id'],$acquisitionDate,$initialWeight]);

        if ($origin === 'NACIMIENTO') {
            $event = $pdo->prepare("INSERT INTO animal_events(animal_id,group_id,event_type,event_date,weight_kg,value_crc,notes) VALUES(?,?,'NACIMIENTO',?,?,?,'Alta automática del nacimiento')");
            $event->execute([$animalId,$data['group_id'],$acquisitionDate,$initialWeight,$initialValue]);
        }

        $snapshot = ['group_id'=>$data['group_id'],'location_id'=>$data['location_id']?:null,'status'=>'ACTIVO'];
        add_animal_history($pdo,$animalId,$origin==='NACIMIENTO'?'BIRTH':'PURCHASE',$acquisitionDate,$snapshot,$initialWeight,$initialValue,$rules['_rule_set_id']??null,['origin'=>$origin,'rule_version'=>$rules['_rule_version']??null]);
        $pdo->commit();
        return $animalId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function update_animal(PDO $pdo, int $animalId, array $data): void {
    $pdo->beginTransaction();
    try {
        $lock = $pdo->prepare('SELECT * FROM animals WHERE id=? FOR UPDATE');
        $lock->execute([$animalId]);
        $before = $lock->fetch();
        if (!$before) throw new RuntimeException('El animal no existe.');
        if ($before['status'] !== 'ACTIVO') throw new RuntimeException('Solo se pueden editar animales activos.');
        if ((int)$data['group_id'] !== (int)$before['group_id']) throw new RuntimeException('Use Transferencias para cambiar el grupo del animal.');

        $stmt = $pdo->prepare('UPDATE animals SET tag=?,location_id=?,sex=? WHERE id=?');
        $stmt->execute([trim($data['tag']),$data['location_id']?:null,$data['sex'],$animalId]);
        $after = array_merge($before,['tag'=>trim($data['tag']),'location_id'=>$data['location_id']?:null,'sex'=>$data['sex']]);
        add_animal_history($pdo,$animalId,'UPDATED',date('Y-m-d'),$after,null,null,null,['before'=>$before,'after'=>$after]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function register_animal_death(PDO $pdo, int $animalId, string $eventDate, string $notes, ?string $evidence): array {
    assert_period_open($pdo,$eventDate);
    $pdo->beginTransaction();
    try {
        $lock = $pdo->prepare('SELECT * FROM animals WHERE id=? FOR UPDATE');
        $lock->execute([$animalId]);
        $animal = $lock->fetch();
        if (!$animal) throw new RuntimeException('El animal no existe.');
        if ($animal['status'] !== 'ACTIVO') throw new RuntimeException('El animal ya no está activo.');
        if ($eventDate < $animal['acquisition_date']) throw new RuntimeException('La muerte no puede ser anterior al ingreso del animal.');

        $future = $pdo->prepare('SELECT COUNT(*) FROM weights WHERE animal_id=? AND weight_date>?');
        $future->execute([$animalId,$eventDate]);
        if ((int)$future->fetchColumn() > 0) throw new RuntimeException('Existen pesajes posteriores a la fecha de muerte. Corrija la fecha o los pesajes.');

        $duplicate = $pdo->prepare("SELECT COUNT(*) FROM animal_events WHERE animal_id=? AND event_type='MUERTE'");
        $duplicate->execute([$animalId]);
        if ((int)$duplicate->fetchColumn() > 0) throw new RuntimeException('La muerte de este animal ya fue registrada.');

        $valuation = animal_valuation_as_of($pdo,$animal,$eventDate);
        if ($valuation['weight_kg'] <= 0) throw new RuntimeException('No fue posible determinar el peso del animal a esa fecha.');
        $event = $pdo->prepare("INSERT INTO animal_events(animal_id,group_id,event_type,event_date,weight_kg,value_crc,notes,evidence_reference) VALUES(?,?,'MUERTE',?,?,?,?,?)");
        $event->execute([$animalId,$animal['group_id'],$eventDate,$valuation['weight_kg'],$valuation['value_crc'],$notes,$evidence?:null]);
        $update = $pdo->prepare("UPDATE animals SET status='MUERTO' WHERE id=?");
        $update->execute([$animalId]);
        $animal['status'] = 'MUERTO';
        add_animal_history($pdo,$animalId,'DEATH',$eventDate,$animal,$valuation['weight_kg'],$valuation['value_crc'],$valuation['rule_set_id'],['notes'=>$notes,'evidence_reference'=>$evidence,'rule_version'=>$valuation['rule_version']]);
        $pdo->commit();
        return $valuation;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
