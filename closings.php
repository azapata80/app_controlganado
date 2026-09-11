<?php
require __DIR__.'/includes/db.php';
require_once __DIR__.'/includes/closing_service.php';
require_once __DIR__.'/includes/validation.php';
if(session_status()!==PHP_SESSION_ACTIVE)session_start();if(empty($_SESSION['csrf_token']))$_SESSION['csrf_token']=bin2hex(random_bytes(32));
$month=$_POST['month']??$_GET['month']??date('Y-m',strtotime('first day of last month'));$preview=null;$errors=[];$message='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!hash_equals($_SESSION['csrf_token'],$_POST['csrf_token']??''))$errors[]='La sesión del formulario venció.';
    else{
        try{
            $action=$_POST['action']??'';
            if($action==='preview')$preview=calculate_monthly_closing($pdo,$month);
            elseif($action==='close'){$id=close_month($pdo,$month);header('Location: closing_view.php?id='.$id);exit;}
            elseif($action==='reopen'){if(!positive_integer($_POST['closing_id']??null))throw new InvalidArgumentException('El cierre no es válido.');reopen_monthly_closing($pdo,(int)$_POST['closing_id'],trim($_POST['reason']??''));$message='El período quedó reabierto y auditado.';}
        }catch(Throwable $e){$errors[]=$e->getMessage();}
    }
}
$closings=$pdo->query('SELECT c.*,rs.version rule_version FROM monthly_closings c LEFT JOIN rule_sets rs ON rs.id=c.rule_set_id ORDER BY month_end DESC')->fetchAll();
include __DIR__.'/includes/header.php';
?>
<div class="hero"><div><h1>Cierres mensuales</h1></div></div>
<?php if($message):?><div class="alert success"><?=htmlspecialchars($message)?></div><?php endif;?><?php if($errors):?><div class="alert danger"><?=htmlspecialchars(implode(' ',array_values($errors)))?></div><?php endif;?>
<form method="post"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>"><div class="row"><div><label>Mes</label><input type="month" name="month" value="<?=htmlspecialchars($month)?>" required></div></div><p class="button-row"><button class="btn secondary" name="action" value="preview">Vista previa</button><button class="btn" name="action" value="close">Cerrar período</button></p><p class="form-help">La vista previa no guarda información. Solo pueden cerrarse meses completamente finalizados.</p></form>
<?php if($preview):?>
<section class="grid"><div class="card kpi"><div class="label">Activo inicial</div><div class="value"><?=money_crc($preview['totals']['opening_asset'])?></div></div><div class="card kpi"><div class="label">Activo final</div><div class="value"><?=money_crc($preview['totals']['closing_asset'])?></div></div><div class="card kpi"><div class="label">Resultado operativo</div><div class="value"><?=money_crc($preview['totals']['operating_result'])?></div></div><div class="card kpi"><div class="label">Resultado ajustado</div><div class="value"><?=money_crc($preview['totals']['adjusted_result'])?></div></div></section>
<div class="card full"><h3>Vista previa por grupo · regla <?=htmlspecialchars($preview['rules']['_rule_version']??'predeterminada')?></h3><table><tr><th>Grupo</th><th>Activo inicial</th><th>Activo final</th><th>Compras</th><th>Nacimientos</th><th>Muertes</th><th>Variación valorización</th><th>Resultado biológico</th><th>Costos</th><th>Resultado ajustado</th><th>Conciliación</th></tr><?php foreach($preview['groups'] as $g):?><tr><td><?=htmlspecialchars($g['group_name'])?></td><td><?=money_crc($g['opening_asset'])?></td><td><?=money_crc($g['closing_asset'])?></td><td><?=money_crc($g['purchases'])?></td><td><?=money_crc($g['births'])?></td><td><?=money_crc($g['deaths'])?></td><td><?=money_crc($g['valuation_change'])?></td><td><?=money_crc($g['biological_result'])?></td><td><?=money_crc($g['supplies_cost']+$g['labor_cost'])?></td><td><?=money_crc($g['adjusted_result'])?></td><td><?=money_crc($g['reconciliation_difference'])?></td></tr><?php endforeach;?></table></div>
<?php endif;?>
<div class="card full"><h3>Historial de cierres</h3><table><tr><th>Período</th><th>Estado</th><th>Regla</th><th>Activo final</th><th>Resultado ajustado</th><th>Cerrado</th><th>Acciones</th></tr><?php foreach($closings as $c):?><tr><td><?=htmlspecialchars(substr($c['month_end'],0,7))?></td><td><span class="badge <?=$c['status']==='REOPENED'?'warn':''?>"><?=htmlspecialchars(label_es($c['status']))?></span></td><td><?=htmlspecialchars($c['rule_version']??'')?></td><td><?=money_crc((float)$c['closing_asset'])?></td><td><?=money_crc((float)$c['adjusted_result'])?></td><td><?=htmlspecialchars($c['closed_at'])?></td><td><a href="closing_view.php?id=<?=$c['id']?>">Ver</a><?php if($c['status']==='CLOSED'):?><form method="post" class="reopen-form"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>"><input type="hidden" name="closing_id" value="<?=$c['id']?>"><input name="reason" maxlength="500" placeholder="Motivo obligatorio" required><button class="btn small secondary" name="action" value="reopen">Reabrir</button></form><?php endif;?></td></tr><?php endforeach;?></table></div>
<?php include __DIR__.'/includes/footer.php';?>
