<?php
require_once __DIR__.'/animal_service.php';
require_once __DIR__.'/validation.php';

function transfer_animal(PDO $pdo,int $animalId,int $toGroupId,string $transferDate,string $weightMethod,string $notes=''): array {
    if(!date_not_future($transferDate))throw new InvalidArgumentException('La fecha debe ser válida y no futura.');
    if(!in_array($weightMethod,['ACTUAL','PROJECTED'],true))throw new InvalidArgumentException('El método de peso no es válido.');
    assert_period_open($pdo,$transferDate);
    $pdo->beginTransaction();
    try{
        $lock=$pdo->prepare('SELECT * FROM animals WHERE id=? FOR UPDATE');$lock->execute([$animalId]);$animal=$lock->fetch();
        if(!$animal)throw new RuntimeException('El animal no existe.');
        if($animal['status']!=='ACTIVO')throw new RuntimeException('El animal no está activo.');
        if((int)$animal['group_id']===$toGroupId)throw new RuntimeException('El grupo de destino debe ser diferente al grupo actual.');
        $fromGroupId=(int)$animal['group_id'];
        if($transferDate<$animal['acquisition_date'])throw new RuntimeException('La transferencia no puede ser anterior al ingreso del animal.');
        $group=$pdo->prepare('SELECT COUNT(*) FROM cattle_groups WHERE id=?');$group->execute([$toGroupId]);if(!(int)$group->fetchColumn())throw new RuntimeException('El grupo de destino no existe.');
        $later=$pdo->prepare('SELECT COUNT(*) FROM transfers WHERE animal_id=? AND transfer_date>=?');$later->execute([$animalId,$transferDate]);if((int)$later->fetchColumn())throw new RuntimeException('Ya existe una transferencia en esa fecha o posteriormente.');

        $valuation=animal_valuation_as_of($pdo,$animal,$transferDate);
        $actual=$valuation['last_weight']&&$valuation['last_weight']['weight_date']===$transferDate?(float)$valuation['last_weight']['weight_kg']:null;
        if($weightMethod==='ACTUAL'&&$actual===null)throw new RuntimeException('Para usar peso real debe existir un pesaje en la fecha de transferencia.');
        $selectedWeight=$weightMethod==='ACTUAL'?$actual:(float)$valuation['weight_kg'];
        $rules=load_business_rules($pdo,$transferDate);$price=(float)$rules['price_per_kg'];$total=round($selectedWeight*$price,2);
        $stmt=$pdo->prepare('INSERT INTO transfers(animal_id,transfer_date,from_group_id,to_group_id,weight_method,actual_weight_kg,projected_weight_kg,weight_kg,price_per_kg,total_value,rule_set_id,notes) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$animalId,$transferDate,$animal['group_id'],$toGroupId,$weightMethod,$actual,$valuation['weight_kg'],$selectedWeight,$price,$total,$rules['_rule_set_id']??null,$notes]);$transferId=(int)$pdo->lastInsertId();
        $ledger=$pdo->prepare('INSERT INTO transfer_ledger_entries(transfer_id,group_id,entry_type,amount) VALUES(?,?,?,?)');
        $ledger->execute([$transferId,$animal['group_id'],'TRANSFER_OUT',$total]);$ledger->execute([$transferId,$toGroupId,'TRANSFER_IN',$total]);
        $update=$pdo->prepare('UPDATE animals SET group_id=? WHERE id=?');$update->execute([$toGroupId,$animalId]);
        $animal['group_id']=$toGroupId;
        add_animal_history($pdo,$animalId,'TRANSFER',$transferDate,$animal,$selectedWeight,$total,$rules['_rule_set_id']??null,['from_group_id'=>$fromGroupId,'to_group_id'=>$toGroupId,'weight_method'=>$weightMethod,'rule_version'=>$rules['_rule_version']??null]);
        $pdo->commit();
        return ['id'=>$transferId,'weight_kg'=>$selectedWeight,'projected_weight_kg'=>$valuation['weight_kg'],'actual_weight_kg'=>$actual,'price_per_kg'=>$price,'total_value'=>$total,'rule_version'=>$rules['_rule_version']??null];
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
