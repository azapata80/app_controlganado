<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
if(!in_array('--confirm',$argv,true)){fwrite(STDERR,"Use: php scripts/load_demo.php --confirm\n");exit(2);}

define('GANADERIA_TESTING',true);
require __DIR__.'/../includes/db.php';
require_once __DIR__.'/../includes/animal_service.php';
require_once __DIR__.'/../includes/transfer_service.php';
require_once __DIR__.'/../includes/closing_service.php';
require_once __DIR__.'/../includes/warehouse_service.php';

try{
    $pdo->beginTransaction();
    foreach(['closing_audit','closing_movements','monthly_valuations','monthly_closing_groups','monthly_closings','transfer_ledger_entries','sales','transfers','labor_entries','warehouse_movements','costs','animal_events','weights','weight_import_batches','animal_history','animals','employees','warehouse_products'] as $table)$pdo->exec("DELETE FROM {$table}");
    $pdo->exec('DELETE FROM activities');
    $pdo->exec('DELETE FROM locations');
    $pdo->exec('DELETE FROM cattle_groups');
    $pdo->exec("INSERT INTO cattle_groups(name,active,sort_order) VALUES ('Ganado de Engorde (Montezuma)',1,10),('Ganado de Cría y Desarrollo',1,20)");
    $pdo->exec("INSERT INTO locations(name,active,sort_order) VALUES ('Montezuma',1,10),('La Flor',1,20),('Otras',1,30)");
    $pdo->exec("INSERT INTO activities(activity_code,name,active) VALUES ('ALIMENTACION','Alimentación',1),('MANEJO','Manejo de ganado',1),('SANIDAD','Sanidad y veterinaria',1),('MANTENIMIENTO','Mantenimiento',1),('ADMINISTRACION','Administración',1),('OTRA','Otra actividad',1)");
    $pdo->exec("INSERT INTO employees(employee_code,name,hourly_rate,active) VALUES ('COL-001','Carlos Méndez',2850,1),('COL-002','María Rodríguez',3100,1),('COL-003','José Vargas',2750,1),('COL-004','Ana Solano',3400,1)");
    $pdo->commit();

    $id=fn(string $table,string $column,string $value):int=>(int)$pdo->query("SELECT id FROM {$table} WHERE {$column}=".$pdo->quote($value))->fetchColumn();
    $engorde=$id('cattle_groups','name','Ganado de Engorde (Montezuma)');
    $cria=$id('cattle_groups','name','Ganado de Cría y Desarrollo');
    $montezuma=$id('locations','name','Montezuma');$laFlor=$id('locations','name','La Flor');$otras=$id('locations','name','Otras');

    $animals=[
      ['CR-26001',$cria,$laFlor,'F','2026-01-15','NACIMIENTO',25,null],
      ['CR-26002',$cria,$laFlor,'M','2026-02-10','NACIMIENTO',25,null],
      ['CR-26003',$cria,$otras,'F','2026-03-05','NACIMIENTO',25,null],
      ['CR-26004',$cria,$laFlor,'M','2026-04-12','NACIMIENTO',25,null],
      ['CR-25110',$cria,$otras,'F','2025-05-01','COMPRA',210,315000],
      ['EN-25001',$engorde,$montezuma,'M','2025-04-15','COMPRA',470,705000],
      ['EN-25002',$engorde,$montezuma,'M','2025-05-20','COMPRA',445,667500],
      ['EN-26003',$engorde,$montezuma,'F','2025-08-18','COMPRA',430,650000],
      ['TR-25004',$cria,$laFlor,'M','2025-03-10','NACIMIENTO',25,null],
      ['EN-25005',$engorde,$montezuma,'M','2025-02-14','COMPRA',505,757500],
      ['EN-25006',$engorde,$montezuma,'M','2025-07-22','COMPRA',500,750000],
      ['CR-26006',$cria,$laFlor,'F','2026-06-01','NACIMIENTO',25,null],
    ];
    $animalIds=[];
    foreach($animals as [$tag,$group,$location,$sex,$birth,$origin,$weight,$value]){
        $acquisition=$origin==='COMPRA'?'2026-01-10':$birth;
        if($tag==='EN-25006')$acquisition='2026-06-10';
        $animalIds[$tag]=create_animal($pdo,['tag'=>$tag,'group_id'=>$group,'location_id'=>$location,'sex'=>$sex,'birth_date'=>$birth,'origin'=>$origin,'acquisition_date'=>$acquisition,'initial_weight_kg'=>$weight,'purchase_value'=>$value]);
    }

    $weightRows=[
      ['CR-26001','2026-07-10',238,$cria],['CR-26001','2026-08-10',260,$cria],['CR-26001','2026-09-08',278,$cria],
      ['CR-26002','2026-07-10',205,$cria],['CR-26002','2026-08-10',232,$cria],['CR-26002','2026-09-08',250,$cria],
      ['CR-26003','2026-07-10',171,$cria],['CR-26003','2026-08-10',201,$cria],['CR-26003','2026-09-08',221,$cria],
      ['CR-26004','2026-07-12',153,$cria],['CR-26004','2026-08-20',181,$cria],
      ['CR-25110','2026-07-08',350,$cria],['CR-25110','2026-08-08',368,$cria],['CR-25110','2026-09-08',384,$cria],
      ['EN-25001','2026-07-05',548,$engorde],['EN-25001','2026-08-05',572,$engorde],['EN-25001','2026-09-05',592,$engorde],
      ['EN-25002','2026-07-05',515,$engorde],['EN-25002','2026-08-05',539,$engorde],['EN-25002','2026-09-05',558,$engorde],
      ['EN-26003','2026-07-07',470,$engorde],['EN-26003','2026-08-07',490,$engorde],['EN-26003','2026-09-07',510,$engorde],
      ['TR-25004','2026-06-10',385,$cria],['TR-25004','2026-07-05',410,$cria],['TR-25004','2026-08-05',432,$engorde],['TR-25004','2026-09-05',450,$engorde],
      ['EN-25005','2026-07-06',575,$engorde],['EN-25005','2026-08-06',600,$engorde],['EN-25005','2026-08-28',610,$engorde],
      ['EN-25006','2026-07-09',518,$engorde],['EN-25006','2026-08-09',540,$engorde],['EN-25006','2026-09-09',560,$engorde],
      ['CR-26006','2026-07-11',74,$cria],['CR-26006','2026-08-11',111,$cria],['CR-26006','2026-09-09',145,$cria],
    ];
    $weightInsert=$pdo->prepare("INSERT INTO weights(animal_id,group_id,weight_date,weight_kg,source) VALUES(?,?,?,?,'MANUAL')");
    foreach($weightRows as [$tag,$date,$weight,$group])$weightInsert->execute([$animalIds[$tag],$group,$date,$weight]);

    transfer_animal($pdo,$animalIds['TR-25004'],$engorde,'2026-07-05','ACTUAL','Traslado demo al alcanzar peso de entrada a engorde');
    $pdo->prepare('UPDATE animals SET location_id=? WHERE id=?')->execute([$montezuma,$animalIds['TR-25004']]);
    $pdo->prepare("UPDATE animal_history SET location_id=? WHERE animal_id=? AND action_type='TRANSFER'")->execute([$montezuma,$animalIds['TR-25004']]);
    register_animal_death($pdo,$animalIds['CR-26004'],'2026-08-20','Complicación respiratoria','EXP-DEMO-2026-08-20');

    $saleRules=load_business_rules($pdo,'2026-08-28');$saleWeight=610.0;$realPrice=1600.0;
    $pdo->beginTransaction();
    $pdo->prepare('INSERT INTO sales(animal_id,group_id,sale_date,weight_kg,real_price_per_kg,total_real,projected_price_per_kg,total_projected) VALUES(?,?,?,?,?,?,?,?)')->execute([$animalIds['EN-25005'],$engorde,'2026-08-28',$saleWeight,$realPrice,$saleWeight*$realPrice,$saleRules['price_per_kg'],$saleWeight*$saleRules['price_per_kg']]);
    $pdo->prepare("UPDATE animals SET status='VENDIDO' WHERE id=?")->execute([$animalIds['EN-25005']]);
    add_animal_history($pdo,$animalIds['EN-25005'],'STATUS_CHANGE','2026-08-28',['group_id'=>$engorde,'location_id'=>$montezuma,'status'=>'VENDIDO'],$saleWeight,$saleWeight*$realPrice,$saleRules['_rule_set_id']??null,['reason'=>'SALE','demo'=>true]);
    $pdo->commit();

    $activityIds=[];foreach($pdo->query('SELECT id,activity_code FROM activities') as $row)$activityIds[$row['activity_code']]=(int)$row['id'];
    $warehouseCatalog=[
      ['SUP-CRIA','Suplemento para desarrollo','ALIMENTACION','saco',15,80,18500],['CON-ENG','Concentrado de engorde','ALIMENTACION','saco',18,90,21750],
      ['VIT-DESP','Vitaminas y desparasitación','VETERINARIO','dosis',15,50,4200],['PLAN-SAN','Plan sanitario mensual','VETERINARIO','dosis',10,30,5600],
      ['CERCA-M','Material para reparación de cercas','MATERIAL','metro',50,200,2450],['BEB-KIT','Kit para mantenimiento de bebederos','MATERIAL','unidad',8,10,28500],
    ];$productIds=[];
    $productInsert=$pdo->prepare('INSERT INTO warehouse_products(product_code,name,cost_type,unit,minimum_stock) VALUES(?,?,?,?,?)');
    foreach($warehouseCatalog as [$code,$name,$type,$unit,$minimum,$entryQuantity,$unitCost]){$productInsert->execute([$code,$name,$type,$unit,$minimum]);$productIds[$code]=(int)$pdo->lastInsertId();warehouse_register_movement($pdo,['product_id'=>$productIds[$code],'movement_date'=>'2026-07-01','movement_type'=>'ENTRY','quantity'=>$entryQuantity,'unit_cost'=>$unitCost,'reference'=>'INV-DEMO-2026-07','notes'=>'Existencia inicial demostrativa','created_by'=>'demo']);}
    $costs=[
      [$cria,'ALIMENTACION','2026-07-06','SUP-CRIA',18],[$engorde,'ALIMENTACION','2026-07-06','CON-ENG',24],
      [$cria,'SANIDAD','2026-08-04','VIT-DESP',12],[$engorde,'SANIDAD','2026-08-04','PLAN-SAN',7],
      [$cria,'MANTENIMIENTO','2026-08-15','CERCA-M',80],[$engorde,'ALIMENTACION','2026-08-18','CON-ENG',26],
      [$cria,'ALIMENTACION','2026-09-03','SUP-CRIA',20],[$engorde,'MANTENIMIENTO','2026-09-04','BEB-KIT',3],
    ];
    foreach($costs as [$group,$activity,$date,$code,$quantity])warehouse_issue_to_cost($pdo,['warehouse_product_id'=>$productIds[$code],'group_id'=>$group,'activity_id'=>$activityIds[$activity],'cost_date'=>$date,'quantity'=>$quantity,'notes'=>'Consumo demostrativo','created_by'=>'demo']);

    $employeeIds=[];foreach($pdo->query('SELECT id,employee_code FROM employees') as $row)$employeeIds[$row['employee_code']]=(int)$row['id'];
    $laborInsert=$pdo->prepare('INSERT INTO labor_entries(employee_id,group_id,activity_id,work_date,hours,hourly_rate,amount,notes) VALUES(?,?,?,?,?,?,?,?)');
    $labor=[
      ['COL-001',$cria,'MANEJO','2026-07-08',8],['COL-002',$engorde,'ALIMENTACION','2026-07-09',7.5],['COL-003',$cria,'SANIDAD','2026-08-04',6],['COL-004',$engorde,'MANTENIMIENTO','2026-08-15',8],
      ['COL-001',$engorde,'MANEJO','2026-08-28',6],['COL-002',$cria,'ALIMENTACION','2026-09-03',8],['COL-003',$engorde,'SANIDAD','2026-09-07',5],['COL-004',$cria,'ADMINISTRACION','2026-09-08',4],
    ];
    $rates=['COL-001'=>2850,'COL-002'=>3100,'COL-003'=>2750,'COL-004'=>3400];
    foreach($labor as [$employee,$group,$activity,$date,$hours])$laborInsert->execute([$employeeIds[$employee],$group,$activityIds[$activity],$date,$hours,$rates[$employee],$hours*$rates[$employee],'Registro demostrativo']);

    close_month($pdo,'2026-08','datos demo');
    echo "Datos demo cargados: 12 animales, 48 pesajes, 6 productos de bodega, 1 traslado, 1 muerte, 1 venta y cierre 2026-08.\n";
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();fwrite(STDERR,'Error: '.$e->getMessage()."\n");exit(1);}
