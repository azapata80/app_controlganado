<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
define('GANADERIA_TESTING',true);
require __DIR__.'/../includes/db.php';

$checks=['version'=>trim((string)@file_get_contents(__DIR__.'/../VERSION'))];$failed=false;
try{$checks['database']=$pdo->query('SELECT 1')->fetchColumn()==1?'ok':'error';}catch(Throwable $e){$checks['database']='error';$failed=true;}
try{$checks['active_rule']=!empty($rules['_rule_version'])?'ok':'error';if($checks['active_rule']!=='ok')$failed=true;}catch(Throwable $e){$checks['active_rule']='error';$failed=true;}
try{$checks['users']=(int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn()>0?'ok':'setup_required';if($checks['users']!=='ok')$failed=true;}catch(Throwable $e){$checks['users']='error';$failed=true;}
$checks['timestamp']=(new DateTimeImmutable('now'))->format(DateTimeInterface::ATOM);
echo json_encode($checks,JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT).PHP_EOL;
exit($failed?1:0);
