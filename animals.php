<?php
require __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/validation.php';
require_once __DIR__ . '/includes/animal_service.php';

$errors = [];
$action = $_POST['action'] ?? 'create';
$commonValidation = [
    'tag' => [fn($v) => required_text($v,80), 'El arete es obligatorio y admite hasta 80 caracteres.'],
    'group_id' => [fn($v) => positive_integer($v), 'Seleccione un grupo válido.'],
    'location_id' => [fn($v) => $v === '' || positive_integer($v), 'La ubicación no es válida.'],
    'sex' => [fn($v) => value_in($v,['M','F']), 'El sexo no es válido.'],
    'birth_date' => [fn($v) => $v === '' || date_not_future($v), 'La fecha de nacimiento no es válida o es futura.'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = validate_fields($_POST,$commonValidation);
    if ($action === 'create') {
        if (!value_in($_POST['origin']??null,['NACIMIENTO','COMPRA'])) $errors['origin'] = 'El origen no es válido.';
        if (($_POST['origin']??'') === 'NACIMIENTO' && !date_not_future($_POST['birth_date']??null)) $errors['birth_date'] = 'El nacimiento requiere una fecha válida y no futura.';
        if (($_POST['origin']??'') === 'COMPRA') {
            if (!date_not_future($_POST['acquisition_date']??null)) $errors['acquisition_date'] = 'La compra requiere una fecha válida y no futura.';
            if (!positive_number($_POST['initial_weight_kg']??null)) $errors['initial_weight_kg'] = 'El peso de compra debe ser mayor que cero.';
            if (!positive_number($_POST['purchase_value']??null)) $errors['purchase_value'] = 'El valor de compra debe ser mayor que cero.';
            if (!empty($_POST['birth_date']) && !empty($_POST['acquisition_date']) && $_POST['birth_date'] > $_POST['acquisition_date']) $errors['birth_date'] = 'El nacimiento no puede ser posterior a la compra.';
        }
    } elseif ($action === 'update' && !positive_integer($_POST['animal_id']??null)) {
        $errors['animal_id'] = 'El animal no es válido.';
    }

    if (!$errors) {
        try {
            if ($action === 'update') update_animal($pdo,(int)$_POST['animal_id'],$_POST);
            else create_animal($pdo,$_POST);
            header('Location: animals.php'); exit;
        } catch (PDOException $e) {
            $errors['general'] = $e->getCode()==='23000' ? 'El arete ya existe o la selección no es válida.' : 'No fue posible guardar el animal.';
        } catch (Throwable $e) {
            $errors['general'] = $e->getMessage();
        }
    }
}

$rows = $pdo->query("SELECT a.*,g.name group_name,l.name location_name FROM animals a JOIN cattle_groups g ON g.id=a.group_id LEFT JOIN locations l ON l.id=a.location_id ORDER BY a.id DESC")->fetchAll();
$editAnimal = null;
$editId = $_GET['edit'] ?? (($action === 'update') ? ($_POST['animal_id'] ?? null) : null);
if (positive_integer($editId)) {
    $stmt = $pdo->prepare('SELECT * FROM animals WHERE id=?'); $stmt->execute([$editId]); $editAnimal = $stmt->fetch() ?: null;
}
$groupsStmt=$pdo->prepare('SELECT * FROM cattle_groups WHERE active=1 OR id=? ORDER BY active DESC,sort_order,name');
$groupsStmt->execute([(int)($editAnimal['group_id']??0)]);$groups=$groupsStmt->fetchAll();
$locationsStmt=$pdo->prepare('SELECT * FROM locations WHERE active=1 OR id=? ORDER BY active DESC,sort_order,name');
$locationsStmt->execute([(int)($editAnimal['location_id']??0)]);$locs=$locationsStmt->fetchAll();
$historyRows = [];
if (positive_integer($_GET['history']??null)) {
    $stmt = $pdo->prepare('SELECT h.*,g.name group_name,l.name location_name,rs.version rule_version FROM animal_history h LEFT JOIN cattle_groups g ON g.id=h.group_id LEFT JOIN locations l ON l.id=h.location_id LEFT JOIN rule_sets rs ON rs.id=h.rule_set_id WHERE h.animal_id=? ORDER BY h.action_date DESC,h.id DESC');
    $stmt->execute([$_GET['history']]); $historyRows = $stmt->fetchAll();
}
$form = $_SERVER['REQUEST_METHOD']==='POST' ? $_POST : ($editAnimal ?: []);
include __DIR__ . '/includes/header.php';
?>
<div class="hero"><div><h1>Animales</h1></div></div>
<?php if($errors):?><div class="alert danger"><?=htmlspecialchars(implode(' ',array_values($errors)))?></div><?php endif;?>
<form method="post" id="animal-form"><?=csrf_input()?>
  <input type="hidden" name="action" value="<?=$editAnimal?'update':'create'?>"><?php if($editAnimal):?><input type="hidden" name="animal_id" value="<?=$editAnimal['id']?>"><?php endif;?>
  <h3><?=$editAnimal?'Editar animal activo':'Registrar nacimiento o compra'?></h3>
  <div class="row">
    <div><label>Arete / ID</label><input name="tag" maxlength="80" value="<?=htmlspecialchars($form['tag']??'')?>" required></div>
    <div><label>Grupo</label><?php if($editAnimal):?><input type="hidden" name="group_id" value="<?=$editAnimal['group_id']?>"><?php endif;?><select name="<?=$editAnimal?'group_display':'group_id'?>" <?=$editAnimal?'disabled':''?>><?php foreach($groups as $g):?><option value="<?=$g['id']?>" <?=($form['group_id']??'')==$g['id']?'selected':''?>><?=htmlspecialchars($g['name'])?></option><?php endforeach;?></select></div>
    <div><label>Ubicación</label><select name="location_id"><option value="">—</option><?php foreach($locs as $l):?><option value="<?=$l['id']?>" <?=($form['location_id']??'')==$l['id']?'selected':''?>><?=htmlspecialchars($l['name'])?></option><?php endforeach;?></select></div>
    <div><label>Sexo</label><select name="sex"><option value="M" <?=($form['sex']??'')==='M'?'selected':''?>>Macho</option><option value="F" <?=($form['sex']??'')==='F'?'selected':''?>>Hembra</option></select></div>
    <div><label>Fecha nacimiento</label><input type="date" max="<?=date('Y-m-d')?>" name="birth_date" value="<?=htmlspecialchars($form['birth_date']??'')?>" <?=$editAnimal?'readonly':''?>></div>
    <?php if(!$editAnimal):?>
    <div><label>Origen</label><select name="origin" id="origin"><option value="NACIMIENTO" <?=($form['origin']??'NACIMIENTO')==='NACIMIENTO'?'selected':''?>>Nacimiento</option><option value="COMPRA" <?=($form['origin']??'')==='COMPRA'?'selected':''?>>Compra</option></select></div>
    <div class="purchase-field"><label>Fecha de compra</label><input type="date" max="<?=date('Y-m-d')?>" name="acquisition_date" value="<?=htmlspecialchars($form['acquisition_date']??date('Y-m-d'))?>"></div>
    <div class="purchase-field"><label>Peso de compra (kg)</label><input type="number" min="0.01" step="0.01" name="initial_weight_kg" value="<?=htmlspecialchars($form['initial_weight_kg']??'')?>"></div>
    <div class="purchase-field"><label>Valor de compra (CRC)</label><input type="number" min="0.01" step="0.01" name="purchase_value" value="<?=htmlspecialchars($form['purchase_value']??'')?>"></div>
    <?php endif;?>
  </div>
  <p><button class="btn"><?=$editAnimal?'Guardar cambios':'Registrar animal'?></button><?php if($editAnimal):?> <a class="btn secondary" href="animals.php">Cancelar</a><?php endif;?></p>
</form>

<?php if(isset($_GET['history'])):?><div class="card full"><h3>Historial del animal</h3><?php if($historyRows):?><table><tr><th>Fecha</th><th>Acción</th><th>Grupo</th><th>Ubicación</th><th>Estado</th><th>Peso</th><th>Valor</th><th>Regla</th></tr><?php foreach($historyRows as $h):?><tr><td><?=htmlspecialchars($h['action_date'])?></td><td><?=htmlspecialchars(label_es($h['action_type']))?></td><td><?=htmlspecialchars($h['group_name']??'')?></td><td><?=htmlspecialchars($h['location_name']??'')?></td><td><?=htmlspecialchars(label_es($h['status']))?></td><td><?=htmlspecialchars($h['weight_kg']??'')?></td><td><?=$h['value_crc']!==null?money_crc((float)$h['value_crc']):''?></td><td><?=htmlspecialchars($h['rule_version']??'')?></td></tr><?php endforeach;?></table><?php else:?><p>No hay movimientos históricos para este animal.</p><?php endif;?></div><?php endif;?>

<table><tr><th>ID</th><th>Arete</th><th>Origen</th><th>Grupo</th><th>Ubicación</th><th>Peso inicial</th><th>Valor compra</th><th>Estado</th><th>Acciones</th></tr><?php foreach($rows as $r):?><tr><td><?=$r['id']?></td><td><?=htmlspecialchars($r['tag'])?></td><td><?=htmlspecialchars(label_es($r['origin']))?></td><td><?=htmlspecialchars($r['group_name'])?></td><td><?=htmlspecialchars($r['location_name']??'')?></td><td><?=htmlspecialchars($r['initial_weight_kg'])?> kg</td><td><?=$r['purchase_value']!==null?money_crc((float)$r['purchase_value']):'—'?></td><td><?=htmlspecialchars(label_es($r['status']))?></td><td><a href="?history=<?=$r['id']?>">Historial</a><?php if($r['status']==='ACTIVO'):?> · <a href="?edit=<?=$r['id']?>">Editar</a><?php endif;?></td></tr><?php endforeach;?></table>
<script>(function(){const origin=document.getElementById('origin');if(!origin)return;const fields=document.querySelectorAll('.purchase-field');function toggle(){const purchase=origin.value==='COMPRA';fields.forEach(x=>x.hidden=!purchase);document.querySelector('[name="birth_date"]').required=!purchase;fields.forEach(x=>{const input=x.querySelector('input');if(input)input.required=purchase;});}origin.addEventListener('change',toggle);toggle();})();</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
