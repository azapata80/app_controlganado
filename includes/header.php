<?php
if (!isset($config)) $config = require __DIR__ . '/../config.php';
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
header('Cache-Control: no-store');
if(function_exists('request_is_https')&&request_is_https())header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
$sessionUser=function_exists('current_user')?current_user():null;
$csrfToken=function_exists('csrf_token')?csrf_token():'';
$currentRoute=basename($_SERVER['SCRIPT_NAME']??'index.php');
$currentRoute=['weight_import.php'=>'weights.php','closing_view.php'=>'closings.php','closing_print.php'=>'closings.php','manual.php'=>'help.php'][$currentRoute]??$currentRoute;
$roleLabels=['ADMIN'=>'Administrador','OPERATOR'=>'Operación','FINANCE'=>'Finanzas','VIEWER'=>'Consulta'];
$navAttrs=static function(string $route)use($currentRoute):string{return $currentRoute===$route?' class="active" aria-current="page"':'';};
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#073764">
<title><?= htmlspecialchars($config['app_name']) ?></title>
<link rel="stylesheet" href="assets/app.css">
</head>
<body>
<header class="topbar">
  <a class="app-brand" href="apps.php" aria-label="Ir a aplicaciones">
    <img src="assets/logo-control-ganado.png" alt="Control de Ganado">
    <span><strong><?=htmlspecialchars($config['app_name'])?></strong></span>
  </a>
  <button class="menu-toggle" type="button" aria-label="Abrir menú" aria-expanded="false" aria-controls="main-nav">☰</button>
  <nav id="main-nav" aria-label="Navegación principal">
    <a href="apps.php"<?=$navAttrs('apps.php')?>>Aplicaciones</a>
    <a href="index.php"<?=$navAttrs('index.php')?>>Panel</a>
    <a href="help.php"<?=$navAttrs('help.php')?>>Ayuda</a>
    <span class="user-identity"><strong><?=htmlspecialchars($sessionUser['display_name']??'')?></strong><small><?=htmlspecialchars($roleLabels[$sessionUser['role']??'']??'')?></small></span>
    <form method="post" action="logout.php" class="logout-form"><?=csrf_input()?><button type="submit">Salir</button></form>
  </nav>
</header>
<main class="container">
<script>
(function(){
  const btn=document.querySelector('.menu-toggle');
  const nav=document.getElementById('main-nav');
  if(!btn||!nav) return;
  btn.addEventListener('click',function(){
    const open=nav.classList.toggle('open');
    btn.setAttribute('aria-expanded',open?'true':'false');
    btn.setAttribute('aria-label',open?'Cerrar menú':'Abrir menú');
    btn.textContent=open?'×':'☰';
  });
  nav.querySelectorAll('a').forEach(a=>a.addEventListener('click',()=>{
    if(window.innerWidth<=760){nav.classList.remove('open');btn.setAttribute('aria-expanded','false');btn.textContent='☰';}
  }));
  document.addEventListener('keydown',e=>{if(e.key==='Escape'){nav.classList.remove('open');btn.setAttribute('aria-expanded','false');btn.setAttribute('aria-label','Abrir menú');btn.textContent='☰';}});
})();
</script>
