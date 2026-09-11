<?php

require_once __DIR__.'/periods.php';

function warehouse_register_movement(PDO $pdo,array $data): int {
    $types=['ENTRY','ADJUSTMENT_IN','ADJUSTMENT_OUT'];$type=(string)($data['movement_type']??'');
    if(!in_array($type,$types,true))throw new InvalidArgumentException('El tipo de movimiento de bodega no es válido.');
    $quantity=(float)($data['quantity']??0);if($quantity<=0)throw new InvalidArgumentException('La cantidad debe ser mayor que cero.');
    $date=(string)($data['movement_date']??'');assert_period_open($pdo,$date);
    $pdo->beginTransaction();
    try{
        $stmt=$pdo->prepare('SELECT * FROM warehouse_products WHERE id=? FOR UPDATE');$stmt->execute([(int)$data['product_id']]);$product=$stmt->fetch();
        if(!$product||(int)$product['active']!==1)throw new RuntimeException('Seleccione un producto activo de bodega.');
        $stock=(float)$product['current_stock'];$average=(float)$product['average_unit_cost'];$incoming=in_array($type,['ENTRY','ADJUSTMENT_IN'],true);
        if(!$incoming&&$quantity>$stock+0.0001)throw new RuntimeException('La existencia disponible no permite realizar la salida.');
        $unitCost=$incoming?(float)($data['unit_cost']??0):$average;if($incoming&&$unitCost<=0)throw new InvalidArgumentException('El costo unitario debe ser mayor que cero.');
        $newStock=$incoming?$stock+$quantity:$stock-$quantity;
        $newAverage=$incoming&&$newStock>0?(($stock*$average)+($quantity*$unitCost))/$newStock:$average;
        $pdo->prepare('UPDATE warehouse_products SET current_stock=?,average_unit_cost=? WHERE id=?')->execute([$newStock,$newAverage,$product['id']]);
        $user=current_user();$createdBy=$user['username']??($data['created_by']??'system');
        $pdo->prepare('INSERT INTO warehouse_movements(product_id,movement_date,movement_type,quantity,unit,unit_cost,total_value,stock_after,reference,notes,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?)')->execute([$product['id'],$date,$type,$quantity,$product['unit'],$unitCost,$quantity*$unitCost,$newStock,trim((string)($data['reference']??''))?:null,trim((string)($data['notes']??''))?:null,$createdBy]);
        $id=(int)$pdo->lastInsertId();$pdo->commit();return $id;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

function warehouse_issue_to_cost(PDO $pdo,array $data): array {
    $quantity=(float)($data['quantity']??0);if($quantity<=0)throw new InvalidArgumentException('La cantidad debe ser mayor que cero.');
    $date=(string)($data['cost_date']??'');assert_period_open($pdo,$date);
    $pdo->beginTransaction();
    try{
        $stmt=$pdo->prepare('SELECT * FROM warehouse_products WHERE id=? FOR UPDATE');$stmt->execute([(int)$data['warehouse_product_id']]);$product=$stmt->fetch();
        if(!$product||(int)$product['active']!==1)throw new RuntimeException('Seleccione un producto activo de bodega.');
        $stock=(float)$product['current_stock'];if($quantity>$stock+0.0001)throw new RuntimeException('Existencia insuficiente. Disponible: '.number_format($stock,2,',','.').' '.$product['unit'].'.');
        $unitCost=(float)$product['average_unit_cost'];if($unitCost<=0)throw new RuntimeException('El producto no tiene un costo promedio disponible. Registre primero una entrada.');
        $amount=$quantity*$unitCost;$newStock=$stock-$quantity;
        $pdo->prepare('INSERT INTO costs(group_id,activity_id,warehouse_product_id,cost_date,cost_type,description,unit,quantity,unit_cost,amount) VALUES(?,?,?,?,?,?,?,?,?,?)')->execute([(int)$data['group_id'],(int)$data['activity_id'],$product['id'],$date,$product['cost_type'],$product['name'],$product['unit'],$quantity,$unitCost,$amount]);
        $costId=(int)$pdo->lastInsertId();$pdo->prepare('UPDATE warehouse_products SET current_stock=? WHERE id=?')->execute([$newStock,$product['id']]);
        $user=current_user();$pdo->prepare("INSERT INTO warehouse_movements(product_id,movement_date,movement_type,quantity,unit,unit_cost,total_value,stock_after,group_id,activity_id,cost_id,notes,created_by) VALUES(?,?,'ISSUE',?,?,?,?,?,?,?,?,?,?)")->execute([$product['id'],$date,$quantity,$product['unit'],$unitCost,$amount,$newStock,(int)$data['group_id'],(int)$data['activity_id'],$costId,trim((string)($data['notes']??''))?:null,$user['username']??($data['created_by']??'system')]);
        $movementId=(int)$pdo->lastInsertId();$pdo->commit();return ['cost_id'=>$costId,'movement_id'=>$movementId,'amount'=>$amount,'stock_after'=>$newStock];
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
