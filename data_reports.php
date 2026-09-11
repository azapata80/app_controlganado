<?php
require __DIR__.'/includes/db.php';
require_once __DIR__.'/includes/functions.php';
require_once __DIR__.'/includes/data_reports.php';
[$reportKey,$definition,$scope,$from,$to,$errors]=data_report_request($_GET);
$rows=[];$truncated=false;
if(!$errors){$rows=data_report_statement($pdo,$definition,$scope,$from,$to,201)->fetchAll();$truncated=count($rows)>200;if($truncated)$rows=array_slice($rows,0,200);}
$query=http_build_query(['report'=>$reportKey,'scope'=>$scope,'from'=>$from,'to'=>$to]);
include __DIR__.'/includes/header.php';
?>
<div class="hero"><div><p class="eyebrow">Consulta y descarga</p><h1>Reportes</h1><p>Seleccione los datos y el período que necesita. La descarga incluye todos los registros encontrados.</p></div><span class="badge"><?=count($rows)?><?= $truncated?'+':'' ?> en vista previa</span></div>

<?php if($errors):?><div class="alert danger" role="alert"><?=htmlspecialchars(implode(' ',$errors))?></div><?php endif;?>

<form method="get" class="report-filter">
  <div class="row">
    <div><label for="report">Tabla de datos</label><select id="report" name="report"><?php foreach(data_report_definitions() as $key=>$item):?><option value="<?=htmlspecialchars($key)?>"<?=$key===$reportKey?' selected':''?>><?=htmlspecialchars($item['title'])?> — <?=htmlspecialchars($item['description'])?></option><?php endforeach;?></select></div>
    <div><label for="scope">Período</label><select id="scope" name="scope"><option value="range"<?=$scope==='range'?' selected':''?>>Rango de fechas</option><option value="all"<?=$scope==='all'?' selected':''?>>Todos los registros</option></select></div>
    <div class="report-date"><label for="from">Desde</label><input id="from" type="date" name="from" value="<?=htmlspecialchars($from)?>"></div>
    <div class="report-date"><label for="to">Hasta</label><input id="to" type="date" name="to" value="<?=htmlspecialchars($to)?>"></div>
  </div>
  <div class="report-actions"><button class="btn" type="submit">Consultar</button><?php if(!$errors):?><a class="btn report-excel" href="exports/data_report.php?<?=$query?>&amp;format=excel">Descargar Excel</a><a class="btn secondary" href="exports/data_report.php?<?=$query?>&amp;format=pdf">Descargar PDF</a><?php endif;?></div>
</form>

<section class="card report-preview">
  <div class="card-header"><div><h3><?=htmlspecialchars($definition['title'])?></h3><p><?=htmlspecialchars(data_report_period_label($scope,$from,$to))?></p></div></div>
  <?php if(!$errors&&$rows):?><div class="table-scroll"><table><thead><tr><?php foreach($definition['columns'] as [, $label]):?><th><?=htmlspecialchars($label)?></th><?php endforeach;?></tr></thead><tbody><?php foreach($rows as $row):?><tr><?php foreach($definition['columns'] as [$key,,$type]):?><td><?=htmlspecialchars(data_report_display($row[$key]??null,$type))?></td><?php endforeach;?></tr><?php endforeach;?></tbody></table></div><?php elseif(!$errors):?><p class="empty-state">No hay registros para los filtros seleccionados.</p><?php endif;?>
  <?php if($truncated):?><p class="form-help">La vista muestra los primeros 200 registros. Las descargas incluyen el resultado completo.</p><?php endif;?>
</section>
<script>
(function(){const scope=document.getElementById('scope');const dates=[...document.querySelectorAll('.report-date')];function update(){const disabled=scope.value==='all';dates.forEach(group=>{group.classList.toggle('is-disabled',disabled);group.querySelector('input').disabled=disabled;});}scope.addEventListener('change',update);update();})();
</script>
<?php include __DIR__.'/includes/footer.php';?>
