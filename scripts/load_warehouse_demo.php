<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
if(!in_array('--confirm',$argv,true)){fwrite(STDERR,"Use: php scripts/load_warehouse_demo.php --confirm\n");exit(2);}
define('GANADERIA_TESTING',true);require __DIR__.'/../includes/db.php';
if((int)$pdo->query('SELECT COUNT(*) FROM warehouse_products')->fetchColumn()>0){fwrite(STDERR,"La bodega ya contiene productos; no se modificó ningún dato.\n");exit(2);}
$products=[
 ['SUP-CRIA','Suplemento para desarrollo','ALIMENTACION','saco',15,60,18500],['CON-ENG','Concentrado de engorde','ALIMENTACION','saco',18,70,21750],
 ['VIT-DESP','Vitaminas y desparasitación','VETERINARIO','dosis',15,40,4200],['PLAN-SAN','Plan sanitario mensual','VETERINARIO','dosis',10,25,5600],
 ['CERCA-M','Material para reparación de cercas','MATERIAL','metro',50,120,2450],['BEB-KIT','Kit para mantenimiento de bebederos','MATERIAL','unidad',8,12,28500],
];
try{$pdo->beginTransaction();$product=$pdo->prepare('INSERT INTO warehouse_products(product_code,name,cost_type,unit,minimum_stock,current_stock,average_unit_cost) VALUES(?,?,?,?,?,?,?)');$movement=$pdo->prepare("INSERT INTO warehouse_movements(product_id,movement_date,movement_type,quantity,unit,unit_cost,total_value,stock_after,reference,notes,created_by) VALUES(?,?,'ENTRY',?,?,?,?,?,?,?,?)");foreach($products as [$code,$name,$type,$unit,$minimum,$quantity,$cost]){$product->execute([$code,$name,$type,$unit,$minimum,$quantity,$cost]);$movement->execute([(int)$pdo->lastInsertId(),date('Y-m-d'),$quantity,$unit,$cost,$quantity*$cost,$quantity,'INVENTARIO-INICIAL-DEMO','Existencia inicial de bodega','demo']);}$pdo->commit();echo "Bodega demo cargada: 6 productos con sus existencias iniciales.\n";}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();fwrite(STDERR,'Error: '.$e->getMessage()."\n");exit(1);}
