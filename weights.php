<?php
require __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/validation.php';
require_once __DIR__ . '/includes/weight_service.php';
$errors=[];
if($_SERVER['REQUEST_METHOD']==='POST'){
    $tagStmt=$pdo->prepare('SELECT tag FROM animals WHERE id=?');$tagStmt->execute([$_POST['animal_id']??0]);$tag=$tagStmt->fetchColumn();
    $checked=validate_weight_rows($pdo,[['tag'=>$tag?:'','weight_date'=>$_POST['weight_date']??'','weight_kg'=>$_POST['weight_kg']??'','source'=>'MANUAL','_line'=>1]],'MANUAL');
    if($checked['invalid'])$errors=$checked['invalid'][0]['errors'];
    else{
        try{$row=$checked['valid'][0];assert_period_open($pdo,$row['weight_date']);$s=$pdo->prepare("INSERT INTO weights(animal_id,group_id,weight_date,weight_kg,source) VALUES(?,?,?,?,'MANUAL')");$s->execute([$row['animal_id'],$row['group_id'],$row['weight_date'],$row['weight_kg']]);header('Location: weights.php');exit;}
        catch(PDOException $e){$errors[]=$e->getCode()==='23000'?'Ese pesaje ya existe.':'No fue posible registrar el pesaje.';}
        catch(Throwable $e){$errors[]=$e->getMessage();}
    }
}
$month=$_GET['month']??date('Y-m');
try{$report=monthly_weight_report($pdo,$month);}catch(InvalidArgumentException $e){$month=date('Y-m');$report=monthly_weight_report($pdo,$month);$errors[]=$e->getMessage();}
$animals=$pdo->query("SELECT id,tag FROM animals WHERE status='ACTIVO' ORDER BY tag")->fetchAll();
$rows=$pdo->query("SELECT w.*,a.tag,b.channel,b.source_name FROM weights w JOIN animals a ON a.id=w.animal_id LEFT JOIN weight_import_batches b ON b.id=w.import_batch_id ORDER BY w.weight_date DESC,w.id DESC LIMIT 200")->fetchAll();
include __DIR__.'/includes/header.php';
?>
<div class="hero"><div><h1>Pesajes y productividad</h1></div><a class="btn" href="weight_import.php">Importar CSV</a></div>
<?php if($errors):?><div class="alert danger"><?=htmlspecialchars(implode(' ',array_values($errors)))?></div><?php endif;?>
<form method="post"><h3>Registro manual</h3><div class="row"><div><label>Animal</label><select name="animal_id"><?php foreach($animals as $a):?><option value="<?=$a['id']?>"><?=htmlspecialchars($a['tag'])?></option><?php endforeach;?></select></div><div><label>Fecha</label><input type="date" name="weight_date" max="<?=date('Y-m-d')?>" value="<?=htmlspecialchars($_POST['weight_date']??date('Y-m-d'))?>" required></div><div><label>Peso kg</label><input type="number" min="0.01" step="0.01" name="weight_kg" value="<?=htmlspecialchars($_POST['weight_kg']??'')?>" required></div></div><p><button class="btn">Registrar pesaje</button></p></form>
<form method="get"><div class="row"><div><label>Mes de evaluación</label><input type="month" name="month" value="<?=htmlspecialchars($month)?>" required></div></div><p><button class="btn">Evaluar</button></p></form>
<section class="grid"><div class="card kpi"><div class="label">Población al cierre</div><div class="value"><?=$report['total']?></div></div><div class="card kpi"><div class="label">Pesados durante el mes</div><div class="value"><?=$report['covered']?></div></div><div class="card kpi"><div class="label">Cobertura</div><div class="value"><?=number_format($report['coverage'],1)?>%</div><div class="sub"><?=$report['coverage']>=$rules['minimum_monthly_weight_coverage']?'Cumple la meta':'Por debajo de la meta de '.$rules['minimum_monthly_weight_coverage'].'%'?></div></div><div class="card kpi"><div class="label">Bajo peso esperado</div><div class="value"><?=$report['under_target']?></div><div class="sub"><?=$report['stale']?> sin pesaje reciente</div></div></section>
<div class="card full"><h3>Evaluación por animal al <?=htmlspecialchars($report['end'])?></h3><table><tr><th>Animal</th><th>Grupo</th><th>Último pesaje</th><th>Peso real</th><th>Peso esperado</th><th>Desviación</th><th>Ganancia real/día</th><th>Estado</th></tr><?php foreach($report['details'] as $item):?><tr><td><?=htmlspecialchars($item['animal']['tag'])?></td><td><?=htmlspecialchars($item['animal']['group_name'])?></td><td><?=htmlspecialchars($item['latest']['weight_date']??'Sin pesaje')?></td><td><?=$item['actual']!==null?number_format($item['actual'],2,',','.').' kg':'—'?></td><td><?=number_format($item['expected'],2,',','.')?> kg</td><td><?=$item['deviation']!==null?number_format($item['deviation'],2,',','.').' kg':'—'?></td><td><?=$item['dailyGain']!==null?number_format($item['dailyGain'],3,',','.').' kg':'—'?></td><td><span class="badge <?=$item['isUnder']?'danger':($item['isStale']?'warn':'')?>"><?=$item['isUnder']?'Bajo meta':($item['isStale']?'Pesaje vencido':'En meta')?></span></td></tr><?php endforeach;?></table></div>
<div class="card full"><h3>Últimos registros</h3><table><tr><th>Fecha</th><th>Animal</th><th>Peso</th><th>Fuente</th><th>Lote</th></tr><?php foreach($rows as $r):?><tr><td><?=htmlspecialchars($r['weight_date'])?></td><td><?=htmlspecialchars($r['tag'])?></td><td><?=htmlspecialchars($r['weight_kg'])?> kg</td><td><?=htmlspecialchars(label_es($r['source']))?></td><td><?=htmlspecialchars($r['source_name']??'Manual')?></td></tr><?php endforeach;?></table></div>
<?php include __DIR__.'/includes/footer.php';?>
