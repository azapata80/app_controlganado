<?php
require __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/validation.php';
require_once __DIR__ . '/includes/animal_service.php';

$errors = [];
$message = '';
if (isset($_GET['saved']) && $_GET['saved']==='death') $message = 'La muerte se registró y el valor del animal fue retirado del activo.';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $optionalEvidence = fn($v) => $v === '' || $v === null || required_text($v,500);
    $errors = validate_fields($_POST, [
        'animal_id' => [fn($v) => positive_integer($v), 'Seleccione un animal válido.'],
        'event_date' => [fn($v) => date_not_future($v), 'La fecha debe ser válida y no futura.'],
        'notes' => [fn($v) => required_text($v,500), 'Indique la causa o justificación (máximo 500 caracteres).'],
        'evidence_reference' => [$optionalEvidence, 'La referencia de evidencia admite hasta 500 caracteres.'],
    ]);
    if (!$errors) {
        try {
            register_animal_death($pdo,(int)$_POST['animal_id'],$_POST['event_date'],trim($_POST['notes']),trim($_POST['evidence_reference']??''));
            header('Location: events.php?saved=death'); exit;
        } catch (Throwable $e) {
            $errors['general'] = $e->getMessage();
        }
    }
}
$animals = $pdo->query("SELECT id,tag FROM animals WHERE status='ACTIVO' ORDER BY tag")->fetchAll();
$rows = $pdo->query("SELECT e.*,a.tag,rs.version rule_version FROM animal_events e JOIN animals a ON a.id=e.animal_id LEFT JOIN animal_history h ON h.animal_id=e.animal_id AND h.action_type=CASE e.event_type WHEN 'NACIMIENTO' THEN 'BIRTH' ELSE 'DEATH' END AND h.action_date=e.event_date LEFT JOIN rule_sets rs ON rs.id=h.rule_set_id ORDER BY e.event_date DESC,e.id DESC LIMIT 200")->fetchAll();
include __DIR__ . '/includes/header.php';
?>
<div class="hero"><div><h1>Nacimientos y muertes</h1></div></div>
<?php if($message):?><div class="alert success"><?=htmlspecialchars($message)?></div><?php endif;?>
<?php if($errors):?><div class="alert danger"><?=htmlspecialchars(implode(' ',array_values($errors)))?></div><?php endif;?>
<form method="post"><h3>Registrar muerte</h3><div class="row"><div><label>Animal activo</label><select name="animal_id" required><?php foreach($animals as $a):?><option value="<?=$a['id']?>"><?=htmlspecialchars($a['tag'])?></option><?php endforeach;?></select></div><div><label>Fecha</label><input type="date" name="event_date" max="<?=date('Y-m-d')?>" value="<?=htmlspecialchars($_POST['event_date']??date('Y-m-d'))?>" required></div><div><label>Causa / justificación</label><input name="notes" maxlength="500" value="<?=htmlspecialchars($_POST['notes']??'')?>" required></div><div><label>Referencia de evidencia</label><input name="evidence_reference" maxlength="500" placeholder="Documento, fotografía o expediente" value="<?=htmlspecialchars($_POST['evidence_reference']??'')?>"></div></div><p><button class="btn">Registrar y valorar muerte</button></p></form>
<div class="card full"><h3>Historial de nacimientos y muertes</h3><table><tr><th>Fecha</th><th>Animal</th><th>Evento</th><th>Peso calculado</th><th>Valor</th><th>Regla</th><th>Justificación</th><th>Evidencia</th></tr><?php foreach($rows as $r):?><tr><td><?=htmlspecialchars($r['event_date'])?></td><td><?=htmlspecialchars($r['tag'])?></td><td><?=htmlspecialchars(label_es($r['event_type']))?></td><td><?=htmlspecialchars($r['weight_kg']??'')?> kg</td><td><?=$r['value_crc']!==null?money_crc((float)$r['value_crc']):''?></td><td><?=htmlspecialchars($r['rule_version']??'')?></td><td><?=htmlspecialchars($r['notes']??'')?></td><td><?=htmlspecialchars($r['evidence_reference']??'')?></td></tr><?php endforeach;?></table></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
