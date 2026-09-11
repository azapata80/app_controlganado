<?php
require __DIR__.'/includes/db.php';
require_once __DIR__.'/includes/validation.php';
require_once __DIR__.'/includes/functions.php';
require_once __DIR__.'/includes/periods.php';
$errors=[];$message='';$action=$_POST['action']??'';
if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        if($action==='employee'){
            $errors=validate_fields($_POST,['employee_code'=>[fn($v)=>required_text($v,40),'El código es obligatorio.'],'name'=>[fn($v)=>required_text($v,160),'El nombre es obligatorio.'],'hourly_rate'=>[fn($v)=>positive_number($v),'La tarifa debe ser mayor que cero.']]);
            if(!$errors){$s=$pdo->prepare('INSERT INTO employees(employee_code,name,hourly_rate) VALUES(?,?,?)');$s->execute([trim($_POST['employee_code']),trim($_POST['name']),$_POST['hourly_rate']]);$message='Colaborador registrado.';}
        }elseif($action==='activity'){
            $errors=validate_fields($_POST,['activity_code'=>[fn($v)=>required_text($v,40),'El código es obligatorio.'],'activity_name'=>[fn($v)=>required_text($v,160),'El nombre es obligatorio.']]);
            if(!$errors){$s=$pdo->prepare('INSERT INTO activities(activity_code,name) VALUES(?,?)');$s->execute([strtoupper(trim($_POST['activity_code'])),trim($_POST['activity_name'])]);$message='Actividad registrada.';}
        }elseif($action==='labor'){
            $optionalNotes=fn($v)=>$v===''||$v===null||required_text($v,500);
            $errors=validate_fields($_POST,['employee_id'=>[fn($v)=>positive_integer($v),'Seleccione un colaborador.'],'group_id'=>[fn($v)=>positive_integer($v),'Seleccione un grupo.'],'activity_id'=>[fn($v)=>positive_integer($v),'Seleccione una actividad.'],'work_date'=>[fn($v)=>date_not_future($v),'La fecha debe ser válida y no futura.'],'hours'=>[fn($v)=>positive_number($v)&&$v<=24,'Las horas deben estar entre 0 y 24.'],'notes'=>[$optionalNotes,'Las notas admiten hasta 500 caracteres.']]);
            if(!$errors){
                assert_period_open($pdo,$_POST['work_date']);
                $rate=$pdo->prepare('SELECT hourly_rate FROM employees WHERE id=? AND active=1');$rate->execute([$_POST['employee_id']]);$hourlyRate=$rate->fetchColumn();
                if($hourlyRate===false)throw new RuntimeException('El colaborador no está activo.');
                $amount=(float)$_POST['hours']*(float)$hourlyRate;
                $s=$pdo->prepare('INSERT INTO labor_entries(employee_id,group_id,activity_id,work_date,hours,hourly_rate,amount,notes) VALUES(?,?,?,?,?,?,?,?)');$s->execute([$_POST['employee_id'],$_POST['group_id'],$_POST['activity_id'],$_POST['work_date'],$_POST['hours'],$hourlyRate,$amount,trim($_POST['notes']??'')]);$message='Horas laboradas registradas.';
            }
        }elseif($action==='employee_rate'){
            if(!positive_integer($_POST['employee_id']??null)||!positive_number($_POST['hourly_rate']??null))$errors['employee']='El colaborador o la tarifa no son válidos.';
            if(!$errors){$s=$pdo->prepare('UPDATE employees SET hourly_rate=? WHERE id=?');$s->execute([$_POST['hourly_rate'],$_POST['employee_id']]);$message='Tarifa actualizada para registros futuros.';}
        }elseif($action==='toggle_employee'){
            if(!positive_integer($_POST['employee_id']??null))$errors['employee']='El colaborador no es válido.';
            if(!$errors){$s=$pdo->prepare('UPDATE employees SET active=1-active WHERE id=?');$s->execute([$_POST['employee_id']]);$message='Estado del colaborador actualizado.';}
        }elseif($action==='toggle_activity'){
            if(!positive_integer($_POST['activity_id']??null))$errors['activity']='La actividad no es válida.';
            if(!$errors){$s=$pdo->prepare('UPDATE activities SET active=1-active WHERE id=?');$s->execute([$_POST['activity_id']]);$message='Estado de la actividad actualizado.';}
        }
    }catch(PDOException $e){$errors['general']=$e->getCode()==='23000'?'El código ya existe o la selección no es válida.':'No fue posible guardar el registro.';}
    catch(Throwable $e){$errors['general']=$e->getMessage();}
}
$employees=$pdo->query('SELECT * FROM employees WHERE active=1 ORDER BY name')->fetchAll();
$allEmployees=$pdo->query('SELECT * FROM employees ORDER BY active DESC,name')->fetchAll();
$groups=$pdo->query('SELECT * FROM cattle_groups WHERE active=1 ORDER BY sort_order,name')->fetchAll();
$activities=$pdo->query('SELECT * FROM activities WHERE active=1 ORDER BY name')->fetchAll();
$allActivities=$pdo->query('SELECT * FROM activities ORDER BY active DESC,name')->fetchAll();
$rows=$pdo->query('SELECT l.*,e.employee_code,e.name employee_name,g.name group_name,a.name activity_name FROM labor_entries l JOIN employees e ON e.id=l.employee_id JOIN cattle_groups g ON g.id=l.group_id JOIN activities a ON a.id=l.activity_id ORDER BY l.work_date DESC,l.id DESC LIMIT 200')->fetchAll();
include __DIR__.'/includes/header.php';
?>
<div class="hero"><div><h1>Personal y mano de obra</h1></div><a class="btn secondary" href="costs.php">Ver insumos</a></div>
<?php if($message):?><div class="alert success"><?=htmlspecialchars($message)?></div><?php endif;?><?php if($errors):?><div class="alert danger"><?=htmlspecialchars(implode(' ',array_values($errors)))?></div><?php endif;?>
<section class="grid">
<form method="post" class="wide"><h3>Nuevo colaborador</h3><div class="row"><div><label>Código</label><input name="employee_code" maxlength="40" required></div><div><label>Nombre</label><input name="name" maxlength="160" required></div><div><label>Tarifa por hora</label><input type="number" min="0.01" step="0.01" name="hourly_rate" required></div></div><p><button class="btn" name="action" value="employee">Guardar colaborador</button></p></form>
<form method="post" class="wide"><h3>Nueva actividad</h3><div class="row"><div><label>Código</label><input name="activity_code" maxlength="40" required></div><div><label>Nombre</label><input name="activity_name" maxlength="160" required></div></div><p><button class="btn" name="action" value="activity">Guardar actividad</button></p></form>
</section>
<section class="grid"><div class="card wide"><h3>Catálogo de colaboradores</h3><table><tr><th>Código</th><th>Nombre</th><th>Tarifa</th><th>Estado</th><th>Acciones</th></tr><?php foreach($allEmployees as $e):?><tr><td><?=htmlspecialchars($e['employee_code'])?></td><td><?=htmlspecialchars($e['name'])?></td><td><form method="post" class="inline-form"><input type="hidden" name="employee_id" value="<?=$e['id']?>"><input class="compact-input" type="number" min="0.01" step="0.01" name="hourly_rate" value="<?=htmlspecialchars($e['hourly_rate'])?>"><button class="btn small" name="action" value="employee_rate">Actualizar</button></form></td><td><?=$e['active']?'Activo':'Inactivo'?></td><td><form method="post" class="inline-form"><input type="hidden" name="employee_id" value="<?=$e['id']?>"><button class="btn small secondary" name="action" value="toggle_employee"><?=$e['active']?'Desactivar':'Activar'?></button></form></td></tr><?php endforeach;?></table></div><div class="card wide"><h3>Catálogo de actividades</h3><table><tr><th>Código</th><th>Nombre</th><th>Estado</th><th>Acción</th></tr><?php foreach($allActivities as $a):?><tr><td><?=htmlspecialchars($a['activity_code'])?></td><td><?=htmlspecialchars($a['name'])?></td><td><?=$a['active']?'Activa':'Inactiva'?></td><td><form method="post" class="inline-form"><input type="hidden" name="activity_id" value="<?=$a['id']?>"><button class="btn small secondary" name="action" value="toggle_activity"><?=$a['active']?'Desactivar':'Activar'?></button></form></td></tr><?php endforeach;?></table></div></section>
<form method="post"><h3>Registrar horas</h3><div class="row"><div><label>Colaborador</label><select name="employee_id" required><?php foreach($employees as $e):?><option value="<?=$e['id']?>"><?=htmlspecialchars($e['employee_code'].' · '.$e['name'])?></option><?php endforeach;?></select></div><div><label>Grupo</label><select name="group_id"><?php foreach($groups as $g):?><option value="<?=$g['id']?>"><?=htmlspecialchars($g['name'])?></option><?php endforeach;?></select></div><div><label>Actividad</label><select name="activity_id"><?php foreach($activities as $a):?><option value="<?=$a['id']?>"><?=htmlspecialchars($a['name'])?></option><?php endforeach;?></select></div><div><label>Fecha</label><input type="date" max="<?=date('Y-m-d')?>" name="work_date" value="<?=date('Y-m-d')?>" required></div><div><label>Horas</label><input type="number" min="0.01" max="24" step="0.25" name="hours" required></div><div class="wide"><label>Notas</label><input name="notes" maxlength="500"></div></div><p><button class="btn" name="action" value="labor">Registrar mano de obra</button></p></form>
<div class="card full"><h3>Registros recientes</h3><table><tr><th>Fecha</th><th>Colaborador</th><th>Grupo</th><th>Actividad</th><th>Horas</th><th>Tarifa histórica</th><th>Total</th></tr><?php foreach($rows as $r):?><tr><td><?=htmlspecialchars($r['work_date'])?></td><td><?=htmlspecialchars($r['employee_name'])?></td><td><?=htmlspecialchars($r['group_name'])?></td><td><?=htmlspecialchars($r['activity_name'])?></td><td><?=htmlspecialchars($r['hours'])?></td><td><?=money_crc((float)$r['hourly_rate'])?></td><td><?=money_crc((float)$r['amount'])?></td></tr><?php endforeach;?></table></div>
<?php include __DIR__.'/includes/footer.php';?>
