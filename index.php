<?php
require __DIR__.'/includes/db.php';
require __DIR__.'/includes/functions.php';
require_once __DIR__.'/includes/weight_service.php';

$animals=$pdo->query("SELECT a.*,g.name group_name,w.weight_kg,w.weight_date FROM animals a JOIN cattle_groups g ON g.id=a.group_id LEFT JOIN weights w ON w.id=(SELECT w2.id FROM weights w2 WHERE w2.animal_id=a.id AND w2.weight_date<=CURRENT_DATE ORDER BY w2.weight_date DESC,w2.id DESC LIMIT 1) WHERE a.status='ACTIVO'")->fetchAll();
$total=count($animals);$asset=0.0;$ready=0;$groupStats=[];
foreach($animals as $animal){
    $latest=$animal['weight_kg']!==null?['weight_kg'=>$animal['weight_kg'],'weight_date'=>$animal['weight_date']]:null;
    $projected=projected_weight($animal,$rules,$latest);$value=livestock_value($projected,$rules);$isReady=$projected>=$rules['target_sale_kg'];
    $asset+=$value;if($isReady)$ready++;
    $name=$animal['group_name'];
    if(!isset($groupStats[$name]))$groupStats[$name]=['count'=>0,'asset'=>0.0,'ready'=>0];
    $groupStats[$name]['count']++;$groupStats[$name]['asset']+=$value;if($isReady)$groupStats[$name]['ready']++;
}
$month=date('Y-m');
$costStmt=$pdo->prepare("SELECT (SELECT COALESCE(SUM(amount),0) FROM costs WHERE DATE_FORMAT(cost_date,'%Y-%m')=?)+(SELECT COALESCE(SUM(amount),0) FROM labor_entries WHERE DATE_FORMAT(work_date,'%Y-%m')=?)");$costStmt->execute([$month,$month]);$monthCosts=(float)$costStmt->fetchColumn();
$deathStmt=$pdo->prepare("SELECT COUNT(*) FROM animal_events WHERE event_type='MUERTE' AND DATE_FORMAT(event_date,'%Y-%m')=?");$deathStmt->execute([$month]);$deaths=(int)$deathStmt->fetchColumn();
$weightReport=monthly_weight_report($pdo,$month);$coverage=$weightReport['coverage'];$stale=$weightReport['stale'];$underTarget=$weightReport['under_target'];
$minimumCovered=(int)ceil($weightReport['total']*((float)$rules['minimum_monthly_weight_coverage']/100));$weightsPending=max(0,$minimumCovered-$weightReport['covered']);
include __DIR__.'/includes/header.php';
?>
<div class="hero"><div><p class="eyebrow">Resumen de <?=htmlspecialchars(date('m/Y'))?></p><h1>Panel de control ganadero</h1></div><span class="badge <?=$coverage<$rules['minimum_monthly_weight_coverage']?'danger':''?>">Cobertura de pesaje: <?=number_format($coverage,1)?>%</span></div>

<section class="control-center" aria-label="Centro de control">
  <div class="control-panel">
    <h2>Atención operativa del mes</h2>
    <div class="control-alerts">
      <div class="control-alert <?=$weightsPending?'warn':'ok'?>"><span>Cobertura mínima de pesaje (<?=number_format($rules['minimum_monthly_weight_coverage'],0)?>%)</span><strong><?=$weightsPending?$weightsPending.' por pesar':'Meta cumplida'?></strong></div>
      <div class="control-alert <?=$stale?'warn':'ok'?>"><span>Animales sin pesaje reciente</span><strong><?=$stale?></strong></div>
      <div class="control-alert <?=$underTarget?'warn':'ok'?>"><span>Animales bajo la proyección esperada</span><strong><?=$underTarget?></strong></div>
      <div class="control-alert <?=$deaths?'warn':'ok'?>"><span>Muertes registradas durante el mes</span><strong><?=$deaths?></strong></div>
    </div>
  </div>
  <div class="card">
    <div class="card-header"><div><h3>Acciones rápidas</h3></div></div>
    <div class="quick-actions">
      <?php if(can_roles(['ADMIN','OPERATOR'])):?><a class="quick-action" href="animals.php#animal-form"><strong>Registrar animal</strong><small>Nacimiento o compra</small></a><a class="quick-action" href="weights.php"><strong>Registrar pesaje</strong><small>Manual o importado</small></a><a class="quick-action" href="costs.php"><strong>Registrar costo</strong><small>Insumos y materiales</small></a><a class="quick-action" href="transfers.php"><strong>Mover a engorde</strong><small>Transferencia interna</small></a><?php endif;?>
      <a class="quick-action" href="reports.php"><strong>Ver resultados</strong><small>Pérdidas y ganancias</small></a>
      <a class="quick-action" href="weights.php?month=<?=htmlspecialchars($month)?>"><strong>Evaluar conversión</strong><small>Real frente a esperada</small></a>
      <?php if(can_roles(['ADMIN','FINANCE'])):?><a class="quick-action" href="closings.php"><strong>Cerrar período</strong><small>Valoración mensual</small></a><a class="quick-action" href="settings.php"><strong>Revisar reglas</strong><small>Supuestos financieros</small></a><?php endif;?>
    </div>
  </div>
</section>

<section class="grid" aria-label="Indicadores principales">
  <div class="card kpi"><div class="label">Animales activos</div><div class="value"><?=$total?></div><div class="sub">Población de ambos grupos productivos</div></div>
  <div class="card kpi"><div class="label">Valor del activo</div><div class="value"><?=money_crc($asset)?></div><div class="sub">Proyectado a <?=money_crc((float)$rules['price_per_kg'])?> por kg</div></div>
  <div class="card kpi"><div class="label">Listos para venta</div><div class="value"><?=$ready?></div><div class="sub">Meta de venta ≥ <?=number_format($rules['target_sale_kg'],0)?> kg</div></div>
  <div class="card kpi"><div class="label">Gasto del mes</div><div class="value"><?=money_crc($monthCosts)?></div><div class="sub">Mano de obra, insumos y materiales</div></div>

  <div class="card wide"><div class="card-header"><div><h3>Unidades ganaderas</h3></div></div><div class="business-groups"><?php foreach($groupStats as $name=>$stats):?><div class="business-group"><div><strong><?=htmlspecialchars($name)?></strong><span class="badge"><?=$stats['count']?> animales</span></div><small>Activo estimado: <?=money_crc($stats['asset'])?> · <?=$stats['ready']?> listos para venta</small></div><?php endforeach;?><?php if(!$groupStats):?><p class="form-help">Aún no hay animales activos.</p><?php endif;?></div></div>
  <div class="card wide"><div class="card-header"><div><h3>Distribución del inventario</h3></div></div><div class="group-bars"><?php $maxGroup=max(array_column($groupStats,'count')?:[1]);foreach($groupStats as $name=>$stats):?><div class="group-bar"><div><span><?=htmlspecialchars($name)?></span><strong><?=$stats['count']?></strong></div><div class="bar-track"><span style="width:<?=number_format($stats['count']/$maxGroup*100,2,'.','')?>%"></span></div></div><?php endforeach;?></div></div>

  <div class="card full"><div class="card-header"><div><h3>Modelo activo · <?=htmlspecialchars($rules['_rule_version']??'Sin versión')?></h3></div><?php if(can_roles(['ADMIN','FINANCE'])):?><a class="btn small secondary" href="settings.php">Administrar reglas</a><?php endif;?></div><table><tr><th>Precio proyectado</th><th>Peso nacimiento</th><th>Meta al destete</th><th>Ganancia 0–6 meses</th><th>Ganancia posterior</th><th>Meta de venta</th><th>Carga financiera</th></tr><tr><td><?=money_crc((float)$rules['price_per_kg'])?>/kg</td><td><?=$rules['birth_weight_kg']?> kg</td><td><?=$rules['target_weaning_kg']?> kg</td><td><?=$rules['gain_0_6_kg_day']?> kg/día</td><td><?=$rules['gain_6_plus_kg_day']?> kg/día</td><td><?=$rules['target_sale_kg']?> kg</td><td><?=number_format($rules['financial_rate_annual']*100,2)?>% anual</td></tr></table></div>
</section>
<?php include __DIR__.'/includes/footer.php';?>
