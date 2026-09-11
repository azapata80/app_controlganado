<?php
require __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/validation.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$definitions = [
    'birth_weight_kg' => ['Peso al nacimiento', 'DECIMAL', 'kg', '0.01'],
    'weaning_age_days' => ['Duración primer tramo', 'INTEGER', 'días', '1'],
    'gain_0_6_kg_day' => ['Ganancia primer tramo', 'DECIMAL', 'kg/día', '0.01'],
    'target_weaning_kg' => ['Meta de destete', 'DECIMAL', 'kg', '0.01'],
    'gain_6_plus_kg_day' => ['Ganancia posterior', 'DECIMAL', 'kg/día', '0.01'],
    'target_sale_kg' => ['Meta de venta', 'DECIMAL', 'kg', '0.01'],
    'price_per_kg' => ['Precio proyectado', 'DECIMAL', 'CRC/kg', '0.01'],
    'max_weight_age_days' => ['Antigüedad máxima del pesaje', 'INTEGER', 'días', '1'],
    'minimum_monthly_weight_coverage' => ['Cobertura mínima mensual', 'DECIMAL', '%', '0.01'],
    'financial_rate_annual' => ['Tasa financiera anual', 'DECIMAL', 'proporción', '0.0001'],
];
$descriptions = [
    'birth_weight_kg' => 'Peso estimado al nacimiento', 'weaning_age_days' => 'Duración del primer tramo de crecimiento',
    'gain_0_6_kg_day' => 'Ganancia diaria en el primer tramo', 'target_weaning_kg' => 'Meta gerencial de peso al destete',
    'gain_6_plus_kg_day' => 'Ganancia diaria posterior al destete', 'target_sale_kg' => 'Peso objetivo para venta',
    'price_per_kg' => 'Precio proyectado por kilogramo', 'max_weight_age_days' => 'Antigüedad máxima deseada del pesaje',
    'minimum_monthly_weight_coverage' => 'Cobertura mínima mensual de pesaje', 'financial_rate_annual' => 'Tasa financiera anual',
];

$formRules = $rules;
$errors = [];
$message = '';
$simulation = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $errors['general'] = 'La sesión del formulario venció. Recargue la página.';
    } else {
        $action = $_POST['action'] ?? '';
        if (in_array($action, ['simulate', 'save_draft'], true)) {
            foreach ($definitions as $key => $definition) $formRules[$key] = $_POST[$key] ?? '';
            $formRules['projection_method'] = 'STAGED_GROWTH';
            $formRules['financial_base_method'] = $_POST['financial_base_method'] ?? '';
            $errors = validate_business_rules($formRules);

            if (!$errors && $action === 'simulate') {
                $animal = ['birth_date' => '2026-01-01'];
                $simulation = [
                    'weaning_weight' => projected_weight($animal, $formRules, null, '2026-07-03'),
                    'year_weight' => projected_weight($animal, $formRules, null, '2027-01-01'),
                    'year_value' => livestock_value(projected_weight($animal, $formRules, null, '2027-01-01'), $formRules),
                    'financial_charge' => monthly_financial_charge(1000000, 1200000, $formRules),
                ];
            }

            if (!$errors && $action === 'save_draft') {
                $version = trim($_POST['version'] ?? '');
                $effectiveFrom = $_POST['effective_from'] ?? '';
                $reason = trim($_POST['change_reason'] ?? '');
                if (!preg_match('/^[A-Za-z0-9._-]{1,40}$/', $version)) $errors['version'] = 'Use hasta 40 letras, números, puntos, guiones o guiones bajos.';
                if (!valid_iso_date($effectiveFrom)) $errors['effective_from'] = 'Indique una fecha válida.';
                if (!required_text($reason, 500)) $errors['change_reason'] = 'Indique el motivo del cambio (máximo 500 caracteres).';

                if (!$errors) {
                    try {
                        $pdo->beginTransaction();
                        $stmt = $pdo->prepare("INSERT INTO rule_sets(name,version,status,effective_from,change_reason,created_by) VALUES(?,?,'DRAFT',?,?,?)");
                        $stmt->execute(['Modelo de control de ganado', $version, $effectiveFrom, $reason, 'administración']);
                        $ruleSetId = (int)$pdo->lastInsertId();
                        $parameter = $pdo->prepare('INSERT INTO rule_parameters(rule_set_id,rule_key,rule_value,value_type,unit,description) VALUES(?,?,?,?,?,?)');
                        $parameter->execute([$ruleSetId, 'projection_method', 'STAGED_GROWTH', 'STRING', null, 'Método de proyección de peso']);
                        foreach ($definitions as $key => $definition) {
                            $parameter->execute([$ruleSetId, $key, (string)$formRules[$key], $definition[1], $definition[2], $descriptions[$key]]);
                        }
                        $parameter->execute([$ruleSetId, 'financial_base_method', $formRules['financial_base_method'], 'STRING', null, 'Base para calcular la carga financiera']);
                        $pdo->commit();
                        $message = "La versión {$version} se guardó como borrador.";
                    } catch (PDOException $e) {
                        if ($pdo->inTransaction()) $pdo->rollBack();
                        $errors['general'] = $e->getCode() === '23000' ? 'La versión indicada ya existe.' : 'No fue posible guardar la versión.';
                    }
                }
            }
        } elseif ($action === 'activate') {
            $id = $_POST['rule_set_id'] ?? null;
            if (!positive_integer($id)) {
                $errors['general'] = 'La versión seleccionada no es válida.';
            } else {
                try {
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare("SELECT * FROM rule_sets WHERE id=? AND status IN ('DRAFT','APPROVED') FOR UPDATE");
                    $stmt->execute([$id]);
                    $selected = $stmt->fetch();
                    if (!$selected) throw new RuntimeException('La versión ya no está disponible para activación.');
                    $conflict = $pdo->prepare("SELECT COUNT(*) FROM rule_sets WHERE status='ACTIVE' AND effective_from>=?");
                    $conflict->execute([$selected['effective_from']]);
                    if ((int)$conflict->fetchColumn() > 0) throw new RuntimeException('La vigencia debe ser posterior al inicio de la versión activa.');
                    $retire = $pdo->prepare("UPDATE rule_sets SET status='RETIRED',effective_to=DATE_SUB(?,INTERVAL 1 DAY) WHERE status='ACTIVE'");
                    $retire->execute([$selected['effective_from']]);
                    $activate = $pdo->prepare("UPDATE rule_sets SET status='ACTIVE',approved_by=?,approved_at=CURRENT_TIMESTAMP WHERE id=?");
                    $activate->execute(['administración', $id]);
                    $pdo->commit();
                    $message = 'La nueva versión quedó activa para su fecha de vigencia.';
                    $rules = load_business_rules($pdo);
                    $formRules = $rules;
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    $errors['general'] = $e instanceof RuntimeException ? $e->getMessage() : 'No fue posible activar la versión.';
                }
            }
        }
    }
}

try {
    $versions = $pdo->query('SELECT id,version,status,effective_from,effective_to,change_reason FROM rule_sets ORDER BY effective_from DESC,id DESC')->fetchAll();
} catch (PDOException $e) {
    $versions = [];
    $errors['general'] = 'Ejecute primero la migración sql/migrations/001_configurable_rules.sql.';
}

include __DIR__ . '/includes/header.php';
?>
<div class="hero"><div><h1>Reglas de negocio</h1></div><span class="badge">Activa: <?= htmlspecialchars($rules['_rule_version'] ?? 'predeterminada') ?></span></div>
<?php if ($message): ?><div class="alert success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert danger"><?php foreach ($errors as $error): ?><div><?= htmlspecialchars($error) ?></div><?php endforeach; ?></div><?php endif; ?>
<form method="post">
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
  <h3>Modelo de crecimiento y valorización</h3>
  <div class="row"><?php foreach ($definitions as $key => $definition): ?><div><label for="<?= $key ?>"><?= htmlspecialchars($definition[0]) ?> (<?= htmlspecialchars($definition[2]) ?>)</label><input id="<?= $key ?>" type="number" name="<?= $key ?>" step="<?= $definition[3] ?>" min="0" value="<?= htmlspecialchars((string)($formRules[$key] ?? '')) ?>" required></div><?php endforeach; ?><div><label for="financial_base_method">Base financiera</label><select id="financial_base_method" name="financial_base_method"><option value="AVERAGE_ASSET" <?=($formRules['financial_base_method']??'')==='AVERAGE_ASSET'?'selected':''?>>Activo promedio</option><option value="OPENING_ASSET" <?=($formRules['financial_base_method']??'')==='OPENING_ASSET'?'selected':''?>>Activo inicial</option><option value="CLOSING_ASSET" <?=($formRules['financial_base_method']??'')==='CLOSING_ASSET'?'selected':''?>>Activo final</option></select></div></div>
  <h3>Datos de la nueva versión</h3>
  <div class="row"><div><label for="version">Versión</label><input id="version" name="version" maxlength="40" placeholder="1.1.0" value="<?=htmlspecialchars($_POST['version']??'')?>"></div><div><label for="effective_from">Vigente desde</label><input id="effective_from" type="date" name="effective_from" value="<?=htmlspecialchars($_POST['effective_from']??date('Y-m-d',strtotime('+1 day')))?>"></div><div class="wide"><label for="change_reason">Motivo del cambio</label><input id="change_reason" name="change_reason" maxlength="500" value="<?=htmlspecialchars($_POST['change_reason']??'')?>"></div></div>
  <p class="button-row"><button class="btn secondary" name="action" value="simulate">Simular</button><button class="btn" name="action" value="save_draft">Guardar borrador</button></p>
</form>
<?php if ($simulation): ?><section class="grid simulation"><div class="card kpi"><div class="label">Peso al final del primer tramo</div><div class="value"><?=number_format($simulation['weaning_weight'],2,',','.')?> kg</div></div><div class="card kpi"><div class="label">Peso aproximado al año</div><div class="value"><?=number_format($simulation['year_weight'],2,',','.')?> kg</div></div><div class="card kpi"><div class="label">Valor aproximado al año</div><div class="value"><?=money_crc($simulation['year_value'])?></div></div><div class="card kpi"><div class="label">Carga mensual sobre ₡1–1,2 MM</div><div class="value"><?=money_crc($simulation['financial_charge'])?></div></div></section><?php endif; ?>
<div class="card full"><h3>Historial de versiones</h3><table><thead><tr><th>Versión</th><th>Estado</th><th>Vigencia</th><th>Motivo</th><th>Acción</th></tr></thead><tbody><?php foreach ($versions as $version): ?><tr><td><?=htmlspecialchars($version['version'])?></td><td><span class="badge"><?=htmlspecialchars(label_es($version['status']))?></span></td><td><?=htmlspecialchars($version['effective_from'])?><?= $version['effective_to']?' – '.htmlspecialchars($version['effective_to']):'' ?></td><td><?=htmlspecialchars($version['change_reason'])?></td><td><?php if (in_array($version['status'],['DRAFT','APPROVED'],true)): ?><form method="post" class="inline-form"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>"><input type="hidden" name="rule_set_id" value="<?=$version['id']?>"><button class="btn small" name="action" value="activate">Activar</button></form><?php else: ?>—<?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
