<?php
require_once __DIR__.'/weight_service.php';
require_once __DIR__.'/animal_service.php';
require_once __DIR__.'/periods.php';

function asset_snapshot(PDO $pdo,string $asOf): array {
    $animals=active_animals_as_of($pdo,$asOf);$rows=[];$byGroup=[];
    foreach($animals as $animal){
        $valuation=animal_valuation_as_of($pdo,$animal,$asOf);$groupId=(int)$animal['report_group_id'];
        $rows[]=['animal'=>$animal,'group_id'=>$groupId]+$valuation;
        $byGroup[$groupId]=($byGroup[$groupId]??0)+(float)$valuation['value_crc'];
    }
    return ['rows'=>$rows,'by_group'=>$byGroup,'total'=>array_sum($byGroup)];
}

function grouped_sums(PDO $pdo,string $sql,array $params): array {
    $stmt=$pdo->prepare($sql);$stmt->execute($params);$result=[];
    foreach($stmt->fetchAll() as $row)$result[(int)$row['group_id']]=(float)$row['amount'];
    return $result;
}

function calculate_monthly_closing(PDO $pdo,string $month): array {
    $bounds=month_bounds($month);if(!$bounds)throw new InvalidArgumentException('El mes no es válido.');[$start,$end]=$bounds;
    $previous=(new DateTimeImmutable($start))->modify('-1 day')->format('Y-m-d');
    $rules=load_business_rules($pdo,$end);$snapshotRules=$rules;ksort($snapshotRules);$rulesJson=json_encode($snapshotRules,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION);
    $closing=asset_snapshot($pdo,$end);
    $previousClose=$pdo->prepare("SELECT id FROM monthly_closings WHERE month_end=? AND status='CLOSED'");$previousClose->execute([$previous]);$previousId=$previousClose->fetchColumn();
    if($previousId){$stmt=$pdo->prepare('SELECT group_id,closing_asset amount FROM monthly_closing_groups WHERE closing_id=?');$stmt->execute([$previousId]);$openingByGroup=[];foreach($stmt->fetchAll() as $r)$openingByGroup[(int)$r['group_id']]=(float)$r['amount'];}
    else $openingByGroup=asset_snapshot($pdo,$previous)['by_group'];

    $purchases=grouped_sums($pdo,"SELECT COALESCE(h.group_id,a.group_id) group_id,SUM(a.purchase_value) amount FROM animals a LEFT JOIN animal_history h ON h.animal_id=a.id AND h.action_type='PURCHASE' WHERE a.origin='COMPRA' AND a.acquisition_date BETWEEN ? AND ? GROUP BY COALESCE(h.group_id,a.group_id)",[$start,$end]);
    $births=grouped_sums($pdo,"SELECT group_id,SUM(value_crc) amount FROM animal_events WHERE event_type='NACIMIENTO' AND event_date BETWEEN ? AND ? GROUP BY group_id",[$start,$end]);
    $deaths=grouped_sums($pdo,"SELECT group_id,SUM(value_crc) amount FROM animal_events WHERE event_type='MUERTE' AND event_date BETWEEN ? AND ? GROUP BY group_id",[$start,$end]);
    $sales=grouped_sums($pdo,"SELECT group_id,SUM(total_real) amount FROM sales WHERE sale_date BETWEEN ? AND ? GROUP BY group_id",[$start,$end]);
    $projectedSales=grouped_sums($pdo,"SELECT group_id,SUM(total_projected) amount FROM sales WHERE sale_date BETWEEN ? AND ? GROUP BY group_id",[$start,$end]);
    $transferIn=grouped_sums($pdo,"SELECT to_group_id group_id,SUM(total_value) amount FROM transfers WHERE transfer_date BETWEEN ? AND ? GROUP BY to_group_id",[$start,$end]);
    $transferOut=grouped_sums($pdo,"SELECT from_group_id group_id,SUM(total_value) amount FROM transfers WHERE transfer_date BETWEEN ? AND ? GROUP BY from_group_id",[$start,$end]);
    $costs=grouped_sums($pdo,"SELECT group_id,SUM(amount) amount FROM costs WHERE cost_date BETWEEN ? AND ? GROUP BY group_id",[$start,$end]);
    $labor=grouped_sums($pdo,"SELECT group_id,SUM(amount) amount FROM labor_entries WHERE work_date BETWEEN ? AND ? GROUP BY group_id",[$start,$end]);

    $groups=$pdo->query('SELECT id,name FROM cattle_groups ORDER BY id')->fetchAll();$groupRows=[];
    foreach($groups as $group){$id=(int)$group['id'];$v=fn($a)=>(float)($a[$id]??0);
        $row=['group_id'=>$id,'group_name'=>$group['name'],'opening_asset'=>$v($openingByGroup),'closing_asset'=>$v($closing['by_group']),'purchases'=>$v($purchases),'births'=>$v($births),'deaths'=>$v($deaths),'external_sales'=>$v($sales),'projected_sales'=>$v($projectedSales),'transfer_in'=>$v($transferIn),'transfer_out'=>$v($transferOut),'supplies_cost'=>$v($costs),'labor_cost'=>$v($labor)];
        $row['sale_variance']=$row['external_sales']-$row['projected_sales'];
        $row['valuation_change']=$row['closing_asset']-$row['opening_asset']-$row['purchases']-$row['births']-$row['transfer_in']+$row['projected_sales']+$row['deaths']+$row['transfer_out'];
        $row['biological_result']=$row['births']+$row['valuation_change']+$row['sale_variance']-$row['deaths'];
        $row['operating_result']=$row['biological_result']-$row['supplies_cost']-$row['labor_cost'];
        $row['financial_charge']=monthly_financial_charge($row['opening_asset'],$row['closing_asset'],$rules);
        $row['adjusted_result']=$row['operating_result']-$row['financial_charge'];
        $expected=$row['opening_asset']+$row['purchases']+$row['births']+$row['transfer_in']+$row['valuation_change']-$row['projected_sales']-$row['deaths']-$row['transfer_out'];
        $row['reconciliation_difference']=$row['closing_asset']-$expected;$groupRows[]=$row;
    }
    $totals=[];foreach(['opening_asset','closing_asset','biological_result','operating_result','financial_charge','adjusted_result'] as $key)$totals[$key]=array_sum(array_column($groupRows,$key));
    return ['month'=>$month,'start'=>$start,'end'=>$end,'rules'=>$rules,'rules_json'=>$rulesJson,'rules_hash'=>hash('sha256',$rulesJson),'valuations'=>$closing['rows'],'groups'=>$groupRows,'totals'=>$totals];
}

function insert_closing_movements(PDO $pdo,int $closingId,string $start,string $end): void {
    $insert=$pdo->prepare('INSERT INTO closing_movements(closing_id,group_id,animal_id,movement_type,movement_date,source_id,amount,details) VALUES(?,?,?,?,?,?,?,?)');
    $sources=[
      ['PURCHASE',"SELECT a.id source_id,a.id animal_id,COALESCE(h.group_id,a.group_id) group_id,a.acquisition_date movement_date,a.purchase_value amount,a.tag details FROM animals a LEFT JOIN animal_history h ON h.animal_id=a.id AND h.action_type='PURCHASE' WHERE a.origin='COMPRA' AND a.acquisition_date BETWEEN ? AND ?"],
      ['BIRTH',"SELECT e.id source_id,e.animal_id,e.group_id,e.event_date movement_date,e.value_crc amount,a.tag details FROM animal_events e JOIN animals a ON a.id=e.animal_id WHERE e.event_type='NACIMIENTO' AND e.event_date BETWEEN ? AND ?"],
      ['DEATH',"SELECT e.id source_id,e.animal_id,e.group_id,e.event_date movement_date,e.value_crc amount,a.tag details FROM animal_events e JOIN animals a ON a.id=e.animal_id WHERE e.event_type='MUERTE' AND e.event_date BETWEEN ? AND ?"],
      ['SALE',"SELECT s.id source_id,s.animal_id,s.group_id,s.sale_date movement_date,s.total_real amount,a.tag details FROM sales s JOIN animals a ON a.id=s.animal_id WHERE s.sale_date BETWEEN ? AND ?"],
      ['TRANSFER_OUT',"SELECT t.id source_id,t.animal_id,t.from_group_id group_id,t.transfer_date movement_date,t.total_value amount,a.tag details FROM transfers t JOIN animals a ON a.id=t.animal_id WHERE t.transfer_date BETWEEN ? AND ?"],
      ['TRANSFER_IN',"SELECT t.id source_id,t.animal_id,t.to_group_id group_id,t.transfer_date movement_date,t.total_value amount,a.tag details FROM transfers t JOIN animals a ON a.id=t.animal_id WHERE t.transfer_date BETWEEN ? AND ?"],
      ['SUPPLY_COST',"SELECT c.id source_id,NULL animal_id,c.group_id,c.cost_date movement_date,c.amount,c.description details FROM costs c WHERE c.cost_date BETWEEN ? AND ?"],
      ['LABOR_COST',"SELECT l.id source_id,NULL animal_id,l.group_id,l.work_date movement_date,l.amount,CONCAT(e.name,' · ',a.name) details FROM labor_entries l JOIN employees e ON e.id=l.employee_id JOIN activities a ON a.id=l.activity_id WHERE l.work_date BETWEEN ? AND ?"],
    ];
    foreach($sources as [$type,$sql]){$stmt=$pdo->prepare($sql);$stmt->execute([$start,$end]);foreach($stmt->fetchAll() as $r)$insert->execute([$closingId,$r['group_id'],$r['animal_id'],$type,$r['movement_date'],$r['source_id'],$r['amount']??0,substr($r['details']??'',0,500)]);}
}

function close_month(PDO $pdo,string $month,string $actor='administración'): int {
    $pdo->beginTransaction();
    try{
        $data=calculate_monthly_closing($pdo,$month);
        if($data['end']>=date('Y-m-d'))throw new RuntimeException('Solo se pueden cerrar meses completamente finalizados.');
        $later=$pdo->prepare("SELECT COUNT(*) FROM monthly_closings WHERE month_end>? AND status='CLOSED'");$later->execute([$data['end']]);if((int)$later->fetchColumn())throw new RuntimeException('Existe un cierre posterior. Los períodos deben cerrarse en orden.');
        $existing=$pdo->prepare('SELECT * FROM monthly_closings WHERE month_end=? FOR UPDATE');$existing->execute([$data['end']]);$record=$existing->fetch();
        if($record&&$record['status']==='CLOSED')throw new RuntimeException('El período ya está cerrado.');
        if($record){$closingId=(int)$record['id'];foreach(['monthly_valuations','monthly_closing_groups','closing_movements'] as $table)$pdo->prepare("DELETE FROM {$table} WHERE closing_id=?")->execute([$closingId]);$sql='UPDATE monthly_closings SET status=\'CLOSED\',rule_set_id=?,rules_snapshot=?,rules_hash=?,opening_asset=?,closing_asset=?,biological_result=?,operating_result=?,financial_charge=?,adjusted_result=?,closed_by=?,closed_at=CURRENT_TIMESTAMP WHERE id=?';$pdo->prepare($sql)->execute([$data['rules']['_rule_set_id']??null,$data['rules_json'],$data['rules_hash'],$data['totals']['opening_asset'],$data['totals']['closing_asset'],$data['totals']['biological_result'],$data['totals']['operating_result'],$data['totals']['financial_charge'],$data['totals']['adjusted_result'],$actor,$closingId]);}
        else{$sql="INSERT INTO monthly_closings(month_end,status,rule_set_id,rules_snapshot,rules_hash,opening_asset,closing_asset,biological_result,operating_result,financial_charge,adjusted_result,closed_by) VALUES(?,'CLOSED',?,?,?,?,?,?,?,?,?,?)";$pdo->prepare($sql)->execute([$data['end'],$data['rules']['_rule_set_id']??null,$data['rules_json'],$data['rules_hash'],$data['totals']['opening_asset'],$data['totals']['closing_asset'],$data['totals']['biological_result'],$data['totals']['operating_result'],$data['totals']['financial_charge'],$data['totals']['adjusted_result'],$actor]);$closingId=(int)$pdo->lastInsertId();}
        $groupInsert=$pdo->prepare('INSERT INTO monthly_closing_groups(closing_id,group_id,group_name,opening_asset,closing_asset,purchases,births,deaths,external_sales,projected_sales,sale_variance,transfer_in,transfer_out,valuation_change,supplies_cost,labor_cost,biological_result,operating_result,financial_charge,adjusted_result,reconciliation_difference) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        foreach($data['groups'] as $g)$groupInsert->execute([$closingId,$g['group_id'],$g['group_name'],$g['opening_asset'],$g['closing_asset'],$g['purchases'],$g['births'],$g['deaths'],$g['external_sales'],$g['projected_sales'],$g['sale_variance'],$g['transfer_in'],$g['transfer_out'],$g['valuation_change'],$g['supplies_cost'],$g['labor_cost'],$g['biological_result'],$g['operating_result'],$g['financial_charge'],$g['adjusted_result'],$g['reconciliation_difference']]);
        $valuationInsert=$pdo->prepare('INSERT INTO monthly_valuations(closing_id,animal_id,group_id,location_id,animal_tag,group_name,location_name,month_end,actual_weight_kg,actual_weight_date,projected_weight_kg,valuation_method,price_per_kg,value_crc,rule_set_id) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        foreach($data['valuations'] as $v){$last=$v['last_weight'];$method=!$last?'PROJECTED_FROM_BASE':($last['weight_date']===$data['end']?'ACTUAL':'PROJECTED_FROM_WEIGHT');$valuationInsert->execute([$closingId,$v['animal']['id'],$v['group_id'],$v['animal']['location_id']?:null,$v['animal']['tag'],$v['animal']['group_name'],$v['animal']['location_name']??null,$data['end'],$last['weight_kg']??null,$last['weight_date']??null,$v['weight_kg'],$method,$data['rules']['price_per_kg'],$v['value_crc'],$data['rules']['_rule_set_id']??null]);}
        insert_closing_movements($pdo,$closingId,$data['start'],$data['end']);
        $pdo->prepare("INSERT INTO closing_audit(closing_id,action_type,action_by,reason) VALUES(?,'CLOSED',?,'Cierre mensual')")->execute([$closingId,$actor]);
        $pdo->commit();return $closingId;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

function reopen_monthly_closing(PDO $pdo,int $closingId,string $reason,string $actor='administración'): void {
    if(trim($reason)==='')throw new InvalidArgumentException('El motivo de reapertura es obligatorio.');
    $pdo->beginTransaction();
    try{$stmt=$pdo->prepare("SELECT * FROM monthly_closings WHERE id=? AND status='CLOSED' FOR UPDATE");$stmt->execute([$closingId]);$closing=$stmt->fetch();if(!$closing)throw new RuntimeException('El cierre no está disponible para reapertura.');
        $later=$pdo->prepare("SELECT COUNT(*) FROM monthly_closings WHERE month_end>? AND status='CLOSED'");$later->execute([$closing['month_end']]);if((int)$later->fetchColumn())throw new RuntimeException('Debe reabrir primero los cierres posteriores.');
        $pdo->prepare("UPDATE monthly_closings SET status='REOPENED',reopened_by=?,reopened_at=CURRENT_TIMESTAMP,reopen_reason=? WHERE id=?")->execute([$actor,trim($reason),$closingId]);
        $pdo->prepare("INSERT INTO closing_audit(closing_id,action_type,action_by,reason) VALUES(?,'REOPENED',?,?)")->execute([$closingId,$actor,trim($reason)]);$pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
