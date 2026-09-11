<?php
require __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/validation.php';
require_once __DIR__ . '/includes/periods.php';
require_once __DIR__ . '/includes/animal_service.php';
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = validate_fields($_POST, [
        'animal_id' => [fn($v) => positive_integer($v), 'Seleccione un animal válido.'],
        'sale_date' => [fn($v) => date_not_future($v), 'La fecha debe ser válida y no futura.'],
        'weight_kg' => [fn($v) => positive_number($v), 'El peso debe ser mayor que cero.'],
        'real_price_per_kg' => [fn($v) => positive_number($v), 'El precio real debe ser mayor que cero.'],
    ]);
    if (!$errors) {
        try { assert_period_open($pdo,$_POST['sale_date']);$saleRules=load_business_rules($pdo,$_POST['sale_date']); } catch (Throwable $e) { $errors['general']=$e->getMessage(); }
    }
    if (!$errors) {
        $real = (float)$_POST['weight_kg'] * (float)$_POST['real_price_per_kg'];
        $proj = (float)$_POST['weight_kg'] * (float)$saleRules['price_per_kg'];
        try {
            $pdo->beginTransaction();
            $active = $pdo->prepare("SELECT id,group_id,location_id,status FROM animals WHERE id=? AND status='ACTIVO' FOR UPDATE");
            $active->execute([$_POST['animal_id']]);
            $activeAnimal=$active->fetch();
            if (!$activeAnimal) throw new RuntimeException('El animal ya no está activo.');
            $s = $pdo->prepare('INSERT INTO sales(animal_id,group_id,sale_date,weight_kg,real_price_per_kg,total_real,projected_price_per_kg,total_projected) VALUES(?,?,?,?,?,?,?,?)');
            $s->execute([$_POST['animal_id'],$activeAnimal['group_id'],$_POST['sale_date'],$_POST['weight_kg'],$_POST['real_price_per_kg'],$real,$saleRules['price_per_kg'],$proj]);
            $u = $pdo->prepare("UPDATE animals SET status='VENDIDO' WHERE id=?");
            $u->execute([$_POST['animal_id']]);
            $activeAnimal['status']='VENDIDO';
            add_animal_history($pdo,(int)$_POST['animal_id'],'STATUS_CHANGE',$_POST['sale_date'],$activeAnimal,(float)$_POST['weight_kg'],$real,$saleRules['_rule_set_id']??null,['reason'=>'SALE','rule_version'=>$saleRules['_rule_version']??null]);
            $pdo->commit();
            header('Location: sales.php'); exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors['general'] = $e instanceof RuntimeException ? $e->getMessage() : 'No fue posible registrar la venta.';
        }
    }
}
$animals = $pdo->query("SELECT id,tag FROM animals WHERE status='ACTIVO' ORDER BY tag")->fetchAll();
$rows = $pdo->query("SELECT s.*,a.tag FROM sales s JOIN animals a ON a.id=s.animal_id ORDER BY sale_date DESC")->fetchAll();
include __DIR__ . '/includes/header.php';
?>
<div class="hero"><div><h1>Ventas</h1></div></div>
<?php if($errors):?><div class="alert danger"><?=htmlspecialchars(implode(' ',array_values($errors)))?></div><?php endif;?>
<form method="post"><div class="row"><div><label>Animal</label><select name="animal_id"><?php foreach($animals as $a):?><option value="<?=$a['id']?>"><?=htmlspecialchars($a['tag'])?></option><?php endforeach;?></select></div><div><label>Fecha</label><input type="date" name="sale_date" max="<?=date('Y-m-d')?>" value="<?=htmlspecialchars($_POST['sale_date']??date('Y-m-d'))?>" required></div><div><label>Peso kg</label><input type="number" min="0.01" step="0.01" name="weight_kg" value="<?=htmlspecialchars($_POST['weight_kg']??'')?>" required></div><div><label>Precio real/kg</label><input type="number" min="0.01" step="0.01" name="real_price_per_kg" value="<?=htmlspecialchars($_POST['real_price_per_kg']??(string)$rules['price_per_kg'])?>" required></div></div><p><button class="btn">Registrar venta</button></p></form>
<table><tr><th>Fecha</th><th>Animal</th><th>Peso</th><th>Total real</th><th>Total proyectado</th><th>Variación</th></tr><?php foreach($rows as $r):$var=$r['total_real']-$r['total_projected'];?><tr><td><?=htmlspecialchars($r['sale_date'])?></td><td><?=htmlspecialchars($r['tag'])?></td><td><?=htmlspecialchars($r['weight_kg'])?></td><td>₡<?=number_format($r['total_real'],0,',','.')?></td><td>₡<?=number_format($r['total_projected'],0,',','.')?></td><td>₡<?=number_format($var,0,',','.')?></td></tr><?php endforeach;?></table>
<?php include __DIR__ . '/includes/footer.php'; ?>
