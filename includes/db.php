<?php
$configFile = __DIR__ . '/../config.php';
if (!file_exists($configFile)) {
    http_response_code(500);
    die('Falta config.php. Copie config.sample.php a config.php y configure MySQL.');
}
$config = require $configFile;
$port = (int)($config['db_port'] ?? 3306);
$dsn = "mysql:host={$config['db_host']};port={$port};dbname={$config['db_name']};charset=utf8mb4";
try {
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    die('No fue posible conectar con la base de datos.');
}

require_once __DIR__ . '/rules.php';
$rules = load_business_rules($pdo);

require_once __DIR__ . '/auth.php';
send_security_headers();
if(!defined('GANADERIA_PUBLIC_PAGE')&&!defined('GANADERIA_PUBLIC_API')&&!defined('GANADERIA_TESTING')){
    require_auth($pdo);
    authorize_route();
    enforce_csrf();
    if(($_SERVER['REQUEST_METHOD']??'GET')==='POST')audit_log($pdo,'POST_REQUEST',['action'=>$_POST['action']??null]);
}
