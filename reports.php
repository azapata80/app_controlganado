<?php
require __DIR__.'/includes/db.php';
require_once __DIR__.'/includes/functions.php';
$month=$_GET['month']??date('Y-m');if(!preg_match('/^\d{4}-\d{2}$/',$month))$month=date('Y-m');
$monthEnd=(new DateTimeImmutable($month.'-01'))->modify('last day of this month')->format('Y-m-d');
try{$closedStmt=$pdo->prepare("SELECT id FROM monthly_closings WHERE month_end=? AND status='CLOSED'");$closedStmt->execute([$monthEnd]);$closedId=$closedStmt->fetchColumn();if($closedId){header('Location: closing_view.php?id='.$closedId);exit;}}catch(PDOException $e){}
$groups=$pdo->query('SELECT * FROM cattle_groups ORDER BY active DESC,sort_order,name')->fetchAll();
$salesStmt=$pdo->prepare("SELECT COALESCE(SUM(total_real),0) FROM sales WHERE group_id=? AND DATE_FORMAT(sale_date,'%Y-%m')=?");
$costStmt=$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM costs WHERE group_id=? AND DATE_FORMAT(cost_date,'%Y-%m')=?");
$laborStmt=$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM labor_entries WHERE group_id=? AND DATE_FORMAT(work_date,'%Y-%m')=?");
$outStmt=$pdo->prepare("SELECT COALESCE(SUM(total_value),0) FROM transfers WHERE from_group_id=? AND DATE_FORMAT(transfer_date,'%Y-%m')=?");
$inStmt=$pdo->prepare("SELECT COALESCE(SUM(total_value),0) FROM transfers WHERE to_group_id=? AND DATE_FORMAT(transfer_date,'%Y-%m')=?");
$results=[];
foreach($groups as $g){
    $values=[];
    foreach([[$salesStmt,'sales'],[$costStmt,'costs'],[$laborStmt,'labor'],[$outStmt,'transfer_out'],[$inStmt,'transfer_in']] as [$stmt,$key]){$stmt->execute([$g['id'],$month]);$values[$key]=(float)$stmt->fetchColumn();}
    $values['operating']=$values['sales']+$values['transfer_out']-$values['transfer_in']-$values['costs']-$values['labor'];
    $financeBase=$values['costs']+$values['labor'];$values['finance']=max(0,$financeBase*((float)$rules['financial_rate_annual']/12));$values['adjusted']=$values['operating']-$values['finance'];
    $results[]=['group'=>$g]+$values;
}
include __DIR__.'/includes/header.php';
?>
<div class="hero"><div><h1>Estado de Resultados</h1></div></div>
<form method="get"><div class="row"><div><label>Mes</label><input type="month" name="month" value="<?=htmlspecialchars($month)?>"></div></div><p><button class="btn">Consultar</button></p></form>
<table><tr><th>Grupo</th><th>Ventas externas</th><th>Transferencias salida</th><th>Transferencias entrada</th><th>Insumos/materiales</th><th>Mano de obra</th><th>Resultado operativo</th><th>Carga financiera*</th><th>Resultado ajustado*</th></tr><?php foreach($results as $r):?><tr><td><?=htmlspecialchars($r['group']['name'])?></td><td><?=money_crc($r['sales'])?></td><td><?=money_crc($r['transfer_out'])?></td><td><?=money_crc($r['transfer_in'])?></td><td><?=money_crc($r['costs'])?></td><td><?=money_crc($r['labor'])?></td><td><?=money_crc($r['operating'])?></td><td><?=money_crc($r['finance'])?></td><td><?=money_crc($r['adjusted'])?></td></tr><?php endforeach;?></table>
<p class="form-help">*Para meses abiertos se muestra una estimación operativa. El cierre mensual definitivo calcula la carga financiera sobre el activo histórico y elimina las transferencias internas del resultado consolidado.</p>
<?php include __DIR__.'/includes/footer.php';?>
