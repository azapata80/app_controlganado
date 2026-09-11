<?php
require __DIR__.'/includes/db.php';
require_once __DIR__.'/includes/functions.php';

$errors=[];$message='';$roles=['ADMIN'=>'Administrador','OPERATOR'=>'Operación','FINANCE'=>'Finanzas','VIEWER'=>'Consulta'];
if($_SERVER['REQUEST_METHOD']==='POST'){
    $action=$_POST['action']??'';
    try{
        if($action==='create'){
            $username=strtolower(trim($_POST['username']??''));$name=trim($_POST['display_name']??'');
            $role=$_POST['role']??'';$password=(string)($_POST['password']??'');
            if(!preg_match('/^[a-z0-9._-]{3,80}$/',$username))$errors[]='El usuario debe tener 3 a 80 caracteres: letras minúsculas, números, punto, guion o guion bajo.';
            if($name===''||mb_strlen($name)>160)$errors[]='Digite un nombre válido.';
            if(!isset($roles[$role]))$errors[]='Seleccione un perfil válido.';
            if(strlen($password)<12)$errors[]='La contraseña debe tener al menos 12 caracteres.';
            if(!$errors){
                $stmt=$pdo->prepare('INSERT INTO users(username,display_name,password_hash,role) VALUES(?,?,?,?)');
                $stmt->execute([$username,$name,password_hash($password,PASSWORD_DEFAULT),$role]);
                audit_log($pdo,'USER_CREATED',['target_user_id'=>(int)$pdo->lastInsertId(),'target_username'=>$username,'role'=>$role]);
                $message='Usuario creado correctamente.';
            }
        }elseif($action==='toggle'){
            $id=(int)($_POST['user_id']??0);$active=(int)($_POST['active']??0);
            if($id===(int)current_user()['id']&&!$active)$errors[]='No puede desactivar su propia cuenta.';
            if(!$errors){$stmt=$pdo->prepare('UPDATE users SET active=? WHERE id=?');$stmt->execute([$active,$id]);audit_log($pdo,'USER_STATUS_CHANGED',['target_user_id'=>$id,'active'=>$active]);$message='Estado actualizado.';}
        }elseif($action==='password'){
            $id=(int)($_POST['user_id']??0);$password=(string)($_POST['password']??'');
            if(strlen($password)<12)$errors[]='La nueva contraseña debe tener al menos 12 caracteres.';
            if(!$errors){$stmt=$pdo->prepare('UPDATE users SET password_hash=?,failed_login_count=0,locked_until=NULL WHERE id=?');$stmt->execute([password_hash($password,PASSWORD_DEFAULT),$id]);audit_log($pdo,'USER_PASSWORD_RESET',['target_user_id'=>$id]);$message='Contraseña actualizada.';}
        }
    }catch(PDOException $e){$errors[]=$e->getCode()==='23000'?'Ese nombre de usuario ya existe.':'No fue posible guardar el usuario.';}
}
$users=$pdo->query('SELECT id,username,display_name,role,active,failed_login_count,locked_until,last_login_at,created_at FROM users ORDER BY active DESC,display_name')->fetchAll();
$activity=$pdo->query('SELECT username,action,route,ip_address,created_at FROM user_activity_log ORDER BY id DESC LIMIT 100')->fetchAll();
include __DIR__.'/includes/header.php';
?>
<div class="hero"><div><h1>Usuarios y seguridad</h1></div></div>
<?php if($message):?><div class="alert success"><?=htmlspecialchars($message)?></div><?php endif;?>
<?php if($errors):?><div class="alert danger"><?=htmlspecialchars(implode(' ',$errors))?></div><?php endif;?>
<form method="post"><h3>Crear usuario</h3><div class="row"><div><label>Usuario</label><input name="username" minlength="3" maxlength="80" pattern="[a-z0-9._-]+" autocomplete="off" required></div><div><label>Nombre</label><input name="display_name" maxlength="160" required></div><div><label>Perfil</label><select name="role"><?php foreach($roles as $value=>$label):?><option value="<?=$value?>"><?=htmlspecialchars($label)?></option><?php endforeach;?></select></div><div><label>Contraseña inicial</label><input type="password" name="password" minlength="12" autocomplete="new-password" required></div></div><p><button class="btn" name="action" value="create">Crear usuario</button></p></form>
<div class="card full"><h3>Cuentas</h3><table><thead><tr><th>Usuario</th><th>Nombre</th><th>Perfil</th><th>Estado</th><th>Último acceso</th><th>Acciones</th></tr></thead><tbody><?php foreach($users as $u):?><tr><td><?=htmlspecialchars($u['username'])?></td><td><?=htmlspecialchars($u['display_name'])?></td><td><?=htmlspecialchars($roles[$u['role']]??$u['role'])?></td><td><?=$u['active']?'Activo':'Inactivo'?><?=$u['locked_until']&&$u['locked_until']>date('Y-m-d H:i:s')?' · Bloqueado':''?></td><td><?=htmlspecialchars($u['last_login_at']??'Nunca')?></td><td><form method="post" class="inline-form"><input type="hidden" name="user_id" value="<?=$u['id']?>"><input type="hidden" name="active" value="<?=$u['active']?0:1?>"><button class="btn small secondary" name="action" value="toggle" <?=((int)$u['id']===(int)current_user()['id']&&$u['active'])?'disabled':''?>><?=$u['active']?'Desactivar':'Activar'?></button></form><form method="post" class="inline-form"><input type="hidden" name="user_id" value="<?=$u['id']?>"><label class="sr-only" for="password-<?=$u['id']?>">Nueva contraseña de <?=htmlspecialchars($u['username'])?></label><input id="password-<?=$u['id']?>" class="compact-input" type="password" name="password" minlength="12" autocomplete="new-password" placeholder="Nueva contraseña" required><button class="btn small" name="action" value="password">Cambiar</button></form></td></tr><?php endforeach;?></tbody></table></div>
<div class="card full audit-card"><h3>Actividad reciente</h3><table><thead><tr><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Sección</th><th>IP</th></tr></thead><tbody><?php foreach($activity as $a):?><tr><td><?=htmlspecialchars($a['created_at'])?></td><td><?=htmlspecialchars($a['username']??'Anónimo')?></td><td><?=htmlspecialchars(label_es($a['action']))?></td><td><?=htmlspecialchars(route_label_es($a['route']))?></td><td><?=htmlspecialchars($a['ip_address']??'')?></td></tr><?php endforeach;?></tbody></table></div>
<?php include __DIR__.'/includes/footer.php';?>
