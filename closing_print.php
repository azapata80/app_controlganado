<?php
require __DIR__.'/includes/db.php';
require_once __DIR__.'/includes/functions.php';
require_once __DIR__.'/includes/validation.php';
$id=$_GET['id']??null;if(!positive_integer($id)){http_response_code(400);die('Cierre no válido.');}
$stmt=$pdo->prepare('SELECT * FROM monthly_closings WHERE id=?');$stmt->execute([$id]);$c=$stmt->fetch();if(!$c){http_response_code(404);die('Cierre no encontrado.');}
$stmt=$pdo->prepare('SELECT * FROM monthly_closing_groups WHERE closing_id=? ORDER BY group_name');$stmt->execute([$id]);$groups=$stmt->fetchAll();
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Cierre <?=htmlspecialchars($c['month_end'])?></title>
<style>body{font-family:Arial,sans-serif;color:#18364a;margin:28px}h1{color:#073764}table{border-collapse:collapse;width:100%;margin:20px 0}th,td{border:1px solid #d7e2e7;padding:8px;text-align:right;font-size:12px}th{background:#f2f7f8}th:first-child,td:first-child{text-align:left}.summary{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}.box{border:1px solid #d7e2e7;border-top:4px solid #76be2b;padding:12px}.box strong{display:block;font-size:18px;margin-top:5px}.actions{margin-bottom:20px}@media print{.actions{display:none}body{margin:10mm}}@media(max-width:700px){.summary{grid-template-columns:1fr 1fr}}</style></head>
<body><div class="actions"><button onclick="window.print()">Imprimir / Guardar PDF</button></div>
<h1>Sistema de Gestión y Control de Ganado</h1><p><strong>Cierre <?=htmlspecialchars(substr($c['month_end'],0,7))?></strong></p><p>Estado: <?=htmlspecialchars(label_es($c['status']))?> · Cerrado: <?=htmlspecialchars($c['closed_at'])?></p>
<div class="summary"><div class="box">Activo inicial<strong><?=money_crc((float)$c['opening_asset'])?></strong></div><div class="box">Activo final<strong><?=money_crc((float)$c['closing_asset'])?></strong></div><div class="box">Resultado operativo<strong><?=money_crc((float)$c['operating_result'])?></strong></div><div class="box">Resultado ajustado<strong><?=money_crc((float)$c['adjusted_result'])?></strong></div></div>
<table><tr><th>Grupo</th><th>Activo inicial</th><th>Activo final</th><th>Resultado biológico</th><th>Insumos</th><th>Mano de obra</th><th>Carga financiera</th><th>Resultado ajustado</th></tr><?php foreach($groups as $g):?><tr><td><?=htmlspecialchars($g['group_name'])?></td><td><?=money_crc((float)$g['opening_asset'])?></td><td><?=money_crc((float)$g['closing_asset'])?></td><td><?=money_crc((float)$g['biological_result'])?></td><td><?=money_crc((float)$g['supplies_cost'])?></td><td><?=money_crc((float)$g['labor_cost'])?></td><td><?=money_crc((float)$g['financial_charge'])?></td><td><?=money_crc((float)$g['adjusted_result'])?></td></tr><?php endforeach;?></table>
<p>Huella SHA-256 de reglas: <?=htmlspecialchars($c['rules_hash'])?></p></body></html>
