<?php
define('GANADERIA_PUBLIC_PAGE',true);
require __DIR__.'/includes/db.php';
start_secure_session();
if((int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn()>0){http_response_code(404);die('La configuración inicial ya fue completada.');}
$errors=[];
if($_SERVER['REQUEST_METHOD']==='POST'){
    enforce_csrf();$username=strtolower(trim($_POST['username']??''));$name=trim($_POST['display_name']??'');$password=(string)($_POST['password']??'');$confirm=(string)($_POST['confirm']??'');
    if(!preg_match('/^[a-z0-9._-]{3,80}$/',$username))$errors[]='El usuario debe tener entre 3 y 80 caracteres válidos.';
    if($name===''||mb_strlen($name)>160)$errors[]='El nombre es obligatorio.';
    if(strlen($password)<12)$errors[]='La contraseña debe tener al menos 12 caracteres.';
    if($password!==$confirm)$errors[]='Las contraseñas no coinciden.';
    if(!$errors){
        $stmt=$pdo->prepare("INSERT INTO users(username,display_name,password_hash,role) VALUES(?,?,?,'ADMIN')");$stmt->execute([$username,$name,password_hash($password,PASSWORD_DEFAULT)]);$userId=(int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO user_activity_log(user_id,username,action,route,request_method,ip_address,details) VALUES(?,?,'INITIAL_ADMIN_CREATED','setup.php','POST',?,NULL)")->execute([$userId,$username,substr($_SERVER['REMOTE_ADDR']??'',0,45)]);
        header('Location: login.php?setup=1');exit;
    }
}
?>
<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#073764"><title>Configuración · Sistema de Gestión y Control de Ganado</title><link rel="stylesheet" href="assets/app.css"></head>
<body class="auth-page"><main class="auth-card">
  <img class="auth-logo" src="assets/logo-control-ganado.png" alt="Control de Ganado"><p class="eyebrow">Configuración inicial</p><h1>Crear administrador</h1><p class="form-help">Esta pantalla se deshabilita automáticamente después de crear el primer usuario.</p>
  <?php if($errors):?><div class="alert danger" role="alert" aria-live="polite"><?=htmlspecialchars(implode(' ',$errors))?></div><?php endif;?>
  <form method="post"><?=csrf_input()?>
    <div><label for="username">Usuario</label><input id="username" name="username" minlength="3" maxlength="80" pattern="[a-z0-9._-]+" autocomplete="username" required autofocus></div>
    <div><label for="display-name">Nombre</label><input id="display-name" name="display_name" maxlength="160" autocomplete="name" required></div>
    <div><label for="password">Contraseña</label><input id="password" type="password" name="password" minlength="12" autocomplete="new-password" required></div>
    <div><label for="confirm">Confirmar contraseña</label><input id="confirm" type="password" name="confirm" minlength="12" autocomplete="new-password" required></div>
    <p><button class="btn">Crear administrador</button></p>
  </form>
</main></body></html>
