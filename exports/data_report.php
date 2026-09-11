<?php
require __DIR__.'/../includes/db.php';
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/data_reports.php';
[$reportKey,$definition,$scope,$from,$to,$errors]=data_report_request($_GET);
$format=(string)($_GET['format']??'');
if($errors||!in_array($format,['excel','pdf'],true)){http_response_code(400);die($errors?htmlspecialchars(implode(' ',$errors)):'Formato no válido.');}
$rows=data_report_statement($pdo,$definition,$scope,$from,$to)->fetchAll();
$period=data_report_period_label($scope,$from,$to);$suffix=$scope==='all'?'completo':$from.'-'.$to;$filename='reporte-'.$reportKey.'-'.$suffix;
audit_log($pdo,'REPORT_EXPORTED',['report'=>$reportKey,'format'=>$format,'scope'=>$scope,'from'=>$scope==='all'?null:$from,'to'=>$scope==='all'?null:$to,'rows'=>count($rows)]);
if($format==='excel'){
    $content=data_report_excel($definition,$rows,$period);
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="'.$filename.'.xls"');
}else{
    $content=data_report_pdf($definition,$rows,$period);
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="'.$filename.'.pdf"');
}
header('Content-Length: '.strlen($content));echo $content;
