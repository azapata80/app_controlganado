<?php
define('GANADERIA_TESTING', true);
if (getenv('GANADERIA_TEST_DB') !== '1') {
    fwrite(STDERR,"Defina GANADERIA_TEST_DB=1 y use una base desechable.\n");
    exit(2);
}
require __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/animal_service.php';
require_once __DIR__ . '/../includes/weight_service.php';
require_once __DIR__ . '/../includes/transfer_service.php';
require_once __DIR__ . '/../includes/closing_service.php';

$failures = [];
function integration_check(string $name, bool $condition): void {
    global $failures;
    if (!$condition) $failures[] = $name;
}

$birthId = create_animal($pdo,[
    'tag'=>'TEST-BIRTH','group_id'=>2,'location_id'=>2,'sex'=>'F','birth_date'=>'2026-09-01',
    'acquisition_date'=>'','initial_weight_kg'=>'','purchase_value'=>'','origin'=>'NACIMIENTO',
]);
$stmt = $pdo->prepare('SELECT a.initial_weight_kg,(SELECT COUNT(*) FROM weights w WHERE w.animal_id=a.id) weights_count,(SELECT COUNT(*) FROM animal_events e WHERE e.animal_id=a.id AND e.event_type=\'NACIMIENTO\') births,(SELECT COUNT(*) FROM animal_history h WHERE h.animal_id=a.id AND h.action_type=\'BIRTH\') history_count FROM animals a WHERE a.id=?');
$stmt->execute([$birthId]); $birth = $stmt->fetch();
integration_check('nacimiento usa 25 kg',(float)$birth['initial_weight_kg']===25.0);
integration_check('nacimiento crea pesaje',(int)$birth['weights_count']===1);
integration_check('nacimiento crea evento',(int)$birth['births']===1);
integration_check('nacimiento crea historial',(int)$birth['history_count']===1);

$purchaseId = create_animal($pdo,[
    'tag'=>'TEST-PURCHASE','group_id'=>1,'location_id'=>1,'sex'=>'M','birth_date'=>'',
    'acquisition_date'=>'2026-09-01','initial_weight_kg'=>'400','purchase_value'=>'590000','origin'=>'COMPRA',
]);
update_animal($pdo,$purchaseId,['tag'=>'TEST-PURCHASE-EDITED','group_id'=>1,'location_id'=>1,'sex'=>'M','birth_date'=>'']);
$valuation = register_animal_death($pdo,$purchaseId,'2026-09-10','Prueba de integración','EXP-TEST');
integration_check('compra adulta se proyecta',abs($valuation['weight_kg']-404.95)<0.001);
integration_check('muerte se valoriza',abs($valuation['value_crc']-607425)<0.01);
$stmt = $pdo->prepare("SELECT a.status,(SELECT COUNT(*) FROM animal_history h WHERE h.animal_id=a.id AND h.action_type='UPDATED') edits,(SELECT COUNT(*) FROM animal_history h WHERE h.animal_id=a.id AND h.action_type='DEATH') deaths FROM animals a WHERE a.id=?");
$stmt->execute([$purchaseId]); $purchase = $stmt->fetch();
integration_check('muerte cambia estado',$purchase['status']==='MUERTO');
integration_check('edición queda auditada',(int)$purchase['edits']===1);
integration_check('muerte queda auditada',(int)$purchase['deaths']===1);

$duplicateRejected = false;
try { register_animal_death($pdo,$purchaseId,'2026-09-10','Duplicada',null); }
catch (RuntimeException $e) { $duplicateRejected = true; }
integration_check('muerte duplicada rechazada',$duplicateRejected);

$checked=validate_weight_rows($pdo,[[
    'tag'=>'TEST-BIRTH','weight_date'=>'2026-09-10','weight_kg'=>'36.5',
    'source'=>'SISTEMA_EXISTENTE','external_reference'=>'EXT-1','_line'=>1,
]],'SISTEMA_EXISTENTE');
integration_check('lote de integración válido',count($checked['valid'])===1&&count($checked['invalid'])===0);
$batchId=import_weight_rows($pdo,$checked['valid'],'API','Prueba integrada');
integration_check('lote registrado',$batchId>0);
$transfer=transfer_animal($pdo,$birthId,1,'2026-09-10','ACTUAL','Prueba de traslado');
integration_check('transferencia usa peso real',abs($transfer['weight_kg']-36.5)<0.001);
$stmt=$pdo->prepare("SELECT a.group_id,(SELECT COUNT(*) FROM transfer_ledger_entries le WHERE le.transfer_id=?) ledger,(SELECT group_id FROM weights WHERE animal_id=? AND weight_date='2026-09-10' AND source='SISTEMA_EXISTENTE') historical_group FROM animals a WHERE a.id=?");
$stmt->execute([$transfer['id'],$birthId,$birthId]);$transferState=$stmt->fetch();
integration_check('transferencia cambia grupo',(int)$transferState['group_id']===1);
integration_check('transferencia genera dos partidas',(int)$transferState['ledger']===2);
integration_check('pesaje conserva grupo histórico',(int)$transferState['historical_group']===2);
$balance=$pdo->query("SELECT SUM(CASE entry_type WHEN 'TRANSFER_OUT' THEN amount ELSE -amount END) FROM transfer_ledger_entries WHERE transfer_id=".(int)$transfer['id'])->fetchColumn();
integration_check('partidas internas balanceadas',abs((float)$balance)<0.001);
$beforeTransfer=active_animals_as_of($pdo,'2026-09-05');$historical=array_values(array_filter($beforeTransfer,fn($a)=>(int)$a['id']===$birthId));
integration_check('consulta conserva grupo previo',(int)$historical[0]['report_group_id']===2);
$duplicate=validate_weight_rows($pdo,[[
    'tag'=>'TEST-BIRTH','weight_date'=>'2026-09-10','weight_kg'=>'36.5',
    'source'=>'SISTEMA_EXISTENTE','external_reference'=>'EXT-1','_line'=>1,
]],'SISTEMA_EXISTENTE');
integration_check('duplicado en base rechazado',count($duplicate['invalid'])===1);
$monthly=monthly_weight_report($pdo,'2026-09');
integration_check('cobertura limitada a 100',$monthly['coverage']>=0&&$monthly['coverage']<=100);
integration_check('población histórica excluye muerte',$monthly['total']===5);

$pdo->exec("INSERT INTO employees(employee_code,name,hourly_rate) VALUES('EMP-TEST','Persona Prueba',2500)");
$employeeId=(int)$pdo->lastInsertId();
$pdo->prepare('INSERT INTO labor_entries(employee_id,group_id,activity_id,work_date,hours,hourly_rate,amount,notes) VALUES(?,?,?,?,?,?,?,?)')->execute([$employeeId,1,1,'2026-09-10',4,2500,10000,'Prueba']);
$pdo->exec("UPDATE employees SET hourly_rate=3000 WHERE id={$employeeId}");
$laborSnapshot=$pdo->query("SELECT hourly_rate,amount FROM labor_entries WHERE employee_id={$employeeId}")->fetch();
integration_check('mano de obra conserva tarifa histórica',(float)$laborSnapshot['hourly_rate']===2500.0&&(float)$laborSnapshot['amount']===10000.0);

$closingAnimalId=create_animal($pdo,[
    'tag'=>'TEST-CLOSING','group_id'=>2,'location_id'=>2,'sex'=>'F','birth_date'=>'',
    'acquisition_date'=>'2026-08-01','initial_weight_kg'=>'400','purchase_value'=>'590000','origin'=>'COMPRA',
]);
$closingTransfer=transfer_animal($pdo,$closingAnimalId,1,'2026-08-10','PROJECTED','Transferencia para cierre');
$pdo->prepare('INSERT INTO costs(group_id,activity_id,cost_date,cost_type,description,unit,quantity,unit_cost,amount) VALUES(?,?,?,?,?,?,?,?,?)')->execute([1,1,'2026-08-15','ALIMENTACION','Costo cierre','kg',2,5000,10000]);
$pdo->prepare('INSERT INTO labor_entries(employee_id,group_id,activity_id,work_date,hours,hourly_rate,amount,notes) VALUES(?,?,?,?,?,?,?,?)')->execute([$employeeId,1,1,'2026-08-15',2,3000,6000,'Cierre']);
$closingId=close_month($pdo,'2026-08','integration-test');
$closed=$pdo->query('SELECT * FROM monthly_closings WHERE id='.(int)$closingId)->fetch();
integration_check('cierre queda cerrado',$closed['status']==='CLOSED');
integration_check('huella de reglas reproducible',hash('sha256',$closed['rules_snapshot'])===$closed['rules_hash']);
$maxDifference=(float)$pdo->query('SELECT MAX(ABS(reconciliation_difference)) FROM monthly_closing_groups WHERE closing_id='.(int)$closingId)->fetchColumn();
integration_check('activo conciliado',abs($maxDifference)<0.01);
$internal=$pdo->query('SELECT SUM(transfer_in) incoming,SUM(transfer_out) outgoing FROM monthly_closing_groups WHERE closing_id='.(int)$closingId)->fetch();
integration_check('transferencias eliminables en consolidado',abs((float)$internal['incoming']-(float)$internal['outgoing'])<0.01);
$locked=false;try{assert_period_open($pdo,'2026-08-20');}catch(RuntimeException $e){$locked=true;}integration_check('período cerrado bloquea movimientos',$locked);
reopen_monthly_closing($pdo,$closingId,'Corrección de prueba','integration-test');
assert_period_open($pdo,'2026-08-20');
$reclosedId=close_month($pdo,'2026-08','integration-test');
integration_check('reapertura conserva identidad del cierre',$reclosedId===$closingId);
$auditCount=(int)$pdo->query('SELECT COUNT(*) FROM closing_audit WHERE closing_id='.(int)$closingId)->fetchColumn();
integration_check('reapertura y recierre auditados',$auditCount===3);

$securityPassword='Clave-Segura-2026';$securityHash=password_hash($securityPassword,PASSWORD_DEFAULT);
$pdo->prepare("INSERT INTO users(username,display_name,password_hash,role) VALUES(?,?,?,'VIEWER')")->execute(['test.viewer','Persona Consulta',$securityHash]);
$securityUserId=(int)$pdo->lastInsertId();
integration_check('contraseña almacenada con hash',password_verify($securityPassword,$securityHash)&&$securityHash!==$securityPassword);
$_SESSION['user']=['id'=>$securityUserId,'username'=>'test.viewer','display_name'=>'Persona Consulta','role'=>'VIEWER'];
integration_check('perfil consulta sin permiso de escritura',can_roles(['VIEWER'])&&!can_roles(['ADMIN','OPERATOR']));
audit_log($pdo,'SECURITY_TEST',['result'=>'ok']);
$securityAudit=(int)$pdo->query("SELECT COUNT(*) FROM user_activity_log WHERE user_id={$securityUserId} AND action='SECURITY_TEST'")->fetchColumn();
integration_check('actividad de usuario auditada',$securityAudit===1);

if ($failures) {
    fwrite(STDERR,"FALLÓ INTEGRACIÓN\n- ".implode("\n- ",$failures)."\n");
    exit(1);
}
echo "OK: ciclo de vida, pesajes, transferencias, costos, cierre y seguridad integrados.\n";
