<?php
define('GANADERIA_PUBLIC_API',true);
header('Content-Type: application/json; charset=utf-8');

function json_response(int $status,array $payload): never {
    http_response_code($status);
    echo json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}

if($_SERVER['REQUEST_METHOD']!=='POST')json_response(405,['error'=>'Método no permitido.']);
require __DIR__.'/../includes/db.php';
require_once __DIR__.'/../includes/weight_service.php';

$expectedKey=(string)($config['integration_api_key']??'');
if($expectedKey==='')json_response(503,['error'=>'La integración no está habilitada.']);
$providedKey=(string)($_SERVER['HTTP_X_API_KEY']??'');
if($providedKey===''||!hash_equals($expectedKey,$providedKey))json_response(401,['error'=>'Credencial inválida.']);

$contentLength=(int)($_SERVER['CONTENT_LENGTH']??0);
if($contentLength>2*1024*1024)json_response(413,['error'=>'El cuerpo supera el máximo de 2 MB.']);
$payload=json_decode(file_get_contents('php://input'),true);
if(!is_array($payload)||!isset($payload['weights'])||!is_array($payload['weights']))json_response(400,['error'=>'El cuerpo debe incluir un arreglo weights.']);
if(!$payload['weights']||count($payload['weights'])>5000)json_response(400,['error'=>'El lote debe contener entre 1 y 5.000 pesajes.']);

$rows=[];
foreach($payload['weights'] as $index=>$row){
    if(!is_array($row))json_response(400,['error'=>'Cada pesaje debe ser un objeto.','row'=>$index+1]);
    $row['_line']=$index+1;$rows[]=$row;
}
$checked=validate_weight_rows($pdo,$rows,'SISTEMA_EXISTENTE');
if($checked['invalid'])json_response(422,['error'=>'El lote contiene filas inválidas. No se importó ningún registro.','total'=>$checked['total'],'invalid'=>$checked['invalid']]);

try{
    $sourceName=trim((string)($payload['source_name']??'Sistema existente'));
    $batchId=import_weight_rows($pdo,$checked['valid'],'API',$sourceName?:'Sistema existente');
    json_response(201,['status'=>'imported','batch_id'=>$batchId,'imported_rows'=>count($checked['valid'])]);
}catch(PDOException $e){
    if($e->getCode()==='23000')json_response(409,['error'=>'Uno de los pesajes ya fue registrado. Revalide el lote.']);
    json_response(500,['error'=>'No fue posible importar el lote.']);
}catch(Throwable $e){
    json_response(409,['error'=>$e->getMessage()]);
}
