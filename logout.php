<?php
require __DIR__.'/includes/db.php';
if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){http_response_code(405);header('Allow: POST');exit;}
audit_log($pdo,'LOGOUT');
$_SESSION=[];
if(ini_get('session.use_cookies')){$p=session_get_cookie_params();setcookie(session_name(),'',time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']);}
setcookie('ganaderia_csrf','',time()-42000,'/');
session_destroy();header('Location: login.php');exit;
