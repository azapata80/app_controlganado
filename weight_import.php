<?php
require __DIR__.'/includes/db.php';
require_once __DIR__.'/includes/weight_service.php';
if(session_status()!==PHP_SESSION_ACTIVE)session_start();
if(empty($_SESSION['csrf_token']))$_SESSION['csrf_token']=bin2hex(random_bytes(32));
$errors=[];$message='';$preview=$_SESSION['weight_import_preview']??null;
if(isset($_GET['imported']))$message='El lote CSV se importó correctamente.';

if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!hash_equals($_SESSION['csrf_token'],$_POST['csrf_token']??''))$errors[]='La sesión del formulario venció.';
    else{
        $action=$_POST['action']??'';
        if($action==='preview'){
            unset($_SESSION['weight_import_preview']);$preview=null;
            if(!isset($_FILES['csv'])||$_FILES['csv']['error']!==UPLOAD_ERR_OK)$errors[]='Seleccione un archivo CSV válido.';
            elseif($_FILES['csv']['size']>2*1024*1024)$errors[]='El archivo supera el máximo de 2 MB.';
            else{
                try{
                    $rows=parse_weight_csv($_FILES['csv']['tmp_name']);
                    if(!$rows)throw new RuntimeException('El archivo no contiene datos.');
                    $preview=validate_weight_rows($pdo,$rows);
                    $preview['file_name']=basename($_FILES['csv']['name']);
                    $_SESSION['weight_import_preview']=$preview;
                }catch(Throwable $e){$errors[]=$e->getMessage();}
            }
        }elseif($action==='confirm'){
            if(!$preview||empty($preview['valid']))$errors[]='No existe una vista previa válida para importar.';
            elseif(!empty($preview['invalid']))$errors[]='Corrija todas las filas rechazadas antes de importar.';
            else{
                try{import_weight_rows($pdo,$preview['valid'],'CSV',$preview['file_name']);unset($_SESSION['weight_import_preview']);header('Location: weight_import.php?imported=1');exit;}
                catch(PDOException $e){$errors[]=$e->getCode()==='23000'?'Otro proceso registró uno de estos pesajes. Genere nuevamente la vista previa.':'No fue posible importar el lote.';}
                catch(Throwable $e){$errors[]=$e->getMessage();}
            }
        }elseif($action==='cancel'){unset($_SESSION['weight_import_preview']);$preview=null;}
    }
}
$batches=$pdo->query("SELECT * FROM weight_import_batches ORDER BY id DESC LIMIT 30")->fetchAll();
include __DIR__.'/includes/header.php';
?>
<div class="hero"><div><h1>Importar pesajes</h1></div><a class="btn secondary" href="weights.php">Volver a pesajes</a></div>
<p class="form-help">Revise el lote completo antes de incorporarlo. La importación es atómica: se guardan todas las filas o ninguna.</p>
<?php if($message):?><div class="alert success"><?=htmlspecialchars($message)?></div><?php endif;?>
<?php if($errors):?><div class="alert danger"><?=htmlspecialchars(implode(' ',array_values($errors)))?></div><?php endif;?>
<form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>"><div class="row"><div class="wide"><label>Archivo CSV (máximo 2 MB y 5.000 filas)</label><input type="file" name="csv" accept=".csv,text/csv" required></div></div><p><button class="btn" name="action" value="preview">Generar vista previa</button></p></form>
<div class="card full"><h3>Formato esperado</h3><p>Columnas obligatorias: <code>tag</code>, <code>weight_date</code>, <code>weight_kg</code>. Opcionales: <code>source</code> y <code>external_reference</code>. También se aceptan los encabezados <code>arete</code>, <code>fecha</code>, <code>peso_kg</code>, <code>fuente</code> y <code>referencia</code>.</p><p><code>tag,weight_date,weight_kg,source,external_reference</code><br><code>EV-26001,2026-09-10,252.50,SISTEMA_EXISTENTE,PESA-8491</code></p></div>
<?php if($preview):?>
<div class="card full"><h3>Vista previa: <?=htmlspecialchars($preview['file_name'])?></h3><p><span class="badge"><?=$preview['total']?> filas</span> <span class="badge"><?=count($preview['valid'])?> válidas</span> <span class="badge <?=count($preview['invalid'])?'danger':''?>"><?=count($preview['invalid'])?> rechazadas</span></p><table><tr><th>Línea</th><th>Arete</th><th>Fecha</th><th>Peso</th><th>Fuente</th><th>Resultado</th></tr><?php foreach(array_slice(array_merge($preview['invalid'],$preview['valid']),0,200) as $row):?><tr><td><?=$row['line']?></td><td><?=htmlspecialchars($row['tag'])?></td><td><?=htmlspecialchars($row['weight_date'])?></td><td><?=htmlspecialchars((string)$row['weight_kg'])?></td><td><?=htmlspecialchars(label_es($row['source']))?></td><td><?=isset($row['errors'])?htmlspecialchars(implode(' ',$row['errors'])):'Válida'?></td></tr><?php endforeach;?></table><?php if($preview['total']>200):?><p>Se muestran las primeras 200 filas.</p><?php endif;?><form method="post" class="inline-actions"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>"><button class="btn secondary" name="action" value="cancel">Cancelar</button><?php if(!$preview['invalid']):?><button class="btn" name="action" value="confirm">Confirmar importación</button><?php endif;?></form></div>
<?php endif;?>
<div class="card full"><h3>Últimos lotes</h3><table><tr><th>Fecha</th><th>Canal</th><th>Origen</th><th>Filas</th><th>Importadas</th></tr><?php foreach($batches as $batch):?><tr><td><?=htmlspecialchars($batch['created_at'])?></td><td><?=htmlspecialchars(label_es($batch['channel']))?></td><td><?=htmlspecialchars($batch['source_name'])?></td><td><?=$batch['total_rows']?></td><td><?=$batch['imported_rows']?></td></tr><?php endforeach;?></table></div>
<?php include __DIR__.'/includes/footer.php';?>
