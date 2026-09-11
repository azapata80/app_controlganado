<?php

function request_is_https(): bool {
    if(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')return true;
    return strtolower(trim(explode(',',$_SERVER['HTTP_X_FORWARDED_PROTO']??'')[0]))==='https';
}

function send_security_headers(): void {
    if(headers_sent())return;
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
    header('Cache-Control: no-store');
    if(request_is_https())header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

function start_secure_session(): void {
    if(session_status()===PHP_SESSION_ACTIVE)return;
    $secure=request_is_https();
    ini_set('session.use_strict_mode','1');
    ini_set('session.use_only_cookies','1');
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
    session_name('ganaderia_session');session_start();
}

function current_user(): ?array {
    return isset($_SESSION['user'])&&is_array($_SESSION['user'])?$_SESSION['user']:null;
}

function csrf_token(): string {
    if(empty($_SESSION['csrf_token']))$_SESSION['csrf_token']=bin2hex(random_bytes(32));
    if(!headers_sent()&&($_COOKIE['ganaderia_csrf']??'')!==$_SESSION['csrf_token']){
        $secure=request_is_https();
        setcookie('ganaderia_csrf',$_SESSION['csrf_token'],['expires'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Strict']);
    }
    return $_SESSION['csrf_token'];
}

function csrf_input(): string {
    return '<input type="hidden" name="csrf_token" value="'.htmlspecialchars(csrf_token(),ENT_QUOTES,'UTF-8').'">';
}

function audit_log(PDO $pdo,string $action,array $details=[]): void {
    $user=current_user();$route=basename(parse_url($_SERVER['REQUEST_URI']??($_SERVER['SCRIPT_NAME']??'cli'),PHP_URL_PATH));
    $stmt=$pdo->prepare('INSERT INTO user_activity_log(user_id,username,action,route,request_method,ip_address,details) VALUES(?,?,?,?,?,?,?)');
    $stmt->execute([$user['id']??null,$user['username']??null,$action,$route,$_SERVER['REQUEST_METHOD']??'CLI',substr($_SERVER['REMOTE_ADDR']??'',0,45),json_encode($details,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
}

function require_auth(PDO $pdo): void {
    start_secure_session();$user=current_user();
    $loginPath=str_contains(str_replace('\\','/',$_SERVER['SCRIPT_NAME']??''),'/exports/')?'../login.php':'login.php';
    if(!$user){$target=basename((string)parse_url($_SERVER['REQUEST_URI']??'index.php',PHP_URL_PATH));header('Location: '.$loginPath.'?next='.rawurlencode($target));exit;}
    if((int)($_SESSION['last_activity']??0)<time()-28800){session_unset();session_destroy();header('Location: '.$loginPath.'?expired=1');exit;}
    $_SESSION['last_activity']=time();
    $stmt=$pdo->prepare('SELECT active,role,display_name FROM users WHERE id=?');$stmt->execute([$user['id']]);$fresh=$stmt->fetch();
    if(!$fresh||!(int)$fresh['active']){session_unset();session_destroy();header('Location: '.$loginPath.'?disabled=1');exit;}
    $_SESSION['user']['role']=$fresh['role'];$_SESSION['user']['display_name']=$fresh['display_name'];
}

function require_roles(array $roles): void {
    $user=current_user();if(!$user||!in_array($user['role'],$roles,true)){http_response_code(403);die('No tiene permisos para realizar esta operación.');}
}

function can_roles(array $roles): bool {
    $user=current_user();return $user&&in_array($user['role'],$roles,true);
}

function authorize_route(): void {
    $route=basename($_SERVER['SCRIPT_NAME']??'');$method=$_SERVER['REQUEST_METHOD']??'GET';
    if(in_array($route,['users.php','catalogs.php'],true))require_roles(['ADMIN']);
    if($method!=='POST')return;
    if(in_array($route,['settings.php','closings.php'],true))require_roles(['ADMIN','FINANCE']);
    elseif($route==='labor.php'&&in_array($_POST['action']??'',['employee','activity','employee_rate','toggle_employee','toggle_activity'],true))require_roles(['ADMIN']);
    elseif(in_array($route,['animals.php','weights.php','weight_import.php','events.php','costs.php','warehouse.php','labor.php','sales.php','transfers.php'],true))require_roles(['ADMIN','OPERATOR']);
}

function enforce_csrf(): void {
    if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST')return;
    $provided=(string)($_POST['csrf_token']??($_COOKIE['ganaderia_csrf']??''));
    if($provided===''||!hash_equals(csrf_token(),$provided)){http_response_code(419);die('La sesión del formulario venció. Regrese, recargue la página e intente nuevamente.');}
}
