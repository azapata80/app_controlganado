<?php
define('GANADERIA_PUBLIC_PAGE',true);
require __DIR__.'/includes/db.php';
start_secure_session();
$errors=[];
if(current_user()){header('Location: apps.php');exit;}
$userCount=(int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
if($_SERVER['REQUEST_METHOD']==='POST'){
    enforce_csrf();
    $username=strtolower(trim($_POST['username']??''));
    $password=(string)($_POST['password']??'');
    $stmt=$pdo->prepare('SELECT * FROM users WHERE username=?');$stmt->execute([$username]);$user=$stmt->fetch();
    $locked=$user&&!empty($user['locked_until'])&&strtotime($user['locked_until'])>time();
    $valid=$user&&(int)$user['active']&&!$locked&&password_verify($password,$user['password_hash']);
    if(!$user)password_verify($password,'$2y$10$zL72rXSbQ2hN5Hq9zIXB..SQOOaOe3Bq5uN2s8MrxuS5YAiQLLoqe');
    if(!$valid){
        if($user&&!$locked){$attempts=(int)$user['failed_login_count']+1;$until=$attempts>=5?date('Y-m-d H:i:s',time()+900):null;$pdo->prepare('UPDATE users SET failed_login_count=?,locked_until=? WHERE id=?')->execute([$attempts,$until,$user['id']]);}
        audit_log($pdo,'LOGIN_FAILED',['username'=>$username]);
        $errors[]=$locked?'Cuenta bloqueada temporalmente. Intente más tarde.':'Usuario o contraseña incorrectos.';
    }else{
        $pdo->prepare('UPDATE users SET failed_login_count=0,locked_until=NULL,last_login_at=NOW() WHERE id=?')->execute([$user['id']]);
        session_regenerate_id(true);
        $_SESSION['user']=['id'=>(int)$user['id'],'username'=>$user['username'],'display_name'=>$user['display_name'],'role'=>$user['role']];
        $_SESSION['last_activity']=time();unset($_SESSION['csrf_token']);csrf_token();audit_log($pdo,'LOGIN_SUCCESS');
        $next=basename((string)parse_url($_GET['next']??'apps.php',PHP_URL_PATH));
        $allowed=['apps.php','index.php','animals.php','weights.php','weight_import.php','events.php','costs.php','warehouse.php','labor.php','transfers.php','sales.php','reports.php','data_reports.php','closings.php','settings.php','catalogs.php','users.php','help.php','manual.php'];
        if(!in_array($next,$allowed,true))$next='apps.php';header('Location: '.$next);exit;
    }
}
?>
<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#073764"><title>Ingresar · Sistema de Gestión y Control de Ganado</title><link rel="stylesheet" href="assets/app.css"></head>
<body class="auth-page"><main class="auth-card">
  <img class="auth-logo" src="assets/logo-control-ganado.png" alt="Control de Ganado"><p class="eyebrow">Control de Ganado</p><h1>Sistema de Gestión y Control de Ganado</h1>
  <?php if(!$userCount):?><div class="alert danger" role="alert">No existe un administrador. <a href="setup.php">Complete la configuración inicial</a>.</div><?php endif;?>
  <?php if($errors):?><div class="alert danger" role="alert" aria-live="polite"><?=htmlspecialchars(implode(' ',$errors))?></div><?php endif;?>
  <form method="post"><?=csrf_input()?>
    <div><label for="username">Usuario</label><input id="username" name="username" maxlength="80" autocomplete="username" required autofocus></div>
    <div><label for="password">Contraseña</label><input id="password" type="password" name="password" autocomplete="current-password" required></div>
    <p><button class="btn">Ingresar</button></p>
  </form>
</main></body></html>
