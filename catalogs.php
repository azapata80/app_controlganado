<?php
require __DIR__.'/includes/db.php';
require_once __DIR__.'/includes/validation.php';

$catalogDefinitions=[
    'locations'=>['table'=>'locations','title'=>'Ubicaciones','singular'=>'ubicación','article'=>'la'],
    'groups'=>['table'=>'cattle_groups','title'=>'Grupos ganaderos','singular'=>'grupo','article'=>'el'],
];
$errors=[];$message='';

function catalog_usage_details(PDO $pdo,string $catalog,int $id): array {
    $references=$catalog==='locations'
        ? [['Animales','animals','location_id'],['Valorizaciones','monthly_valuations','location_id'],['Historial','animal_history','location_id']]
        : [['Animales','animals','group_id'],['Pesajes','weights','group_id'],['Eventos','animal_events','group_id'],['Costos','costs','group_id'],['Mano de obra','labor_entries','group_id'],['Asientos de traslado','transfer_ledger_entries','group_id'],['Ventas','sales','group_id'],['Cierres','monthly_closing_groups','group_id'],['Valorizaciones','monthly_valuations','group_id'],['Movimientos de cierre','closing_movements','group_id'],['Historial','animal_history','group_id']];
    $details=[];
    foreach($references as [$label,$table,$column]){
        $stmt=$pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE {$column}=?");$stmt->execute([$id]);$count=(int)$stmt->fetchColumn();
        if($count)$details[$label]=$count;
    }
    if($catalog==='groups'){
        $stmt=$pdo->prepare('SELECT COUNT(*) FROM transfers WHERE from_group_id=? OR to_group_id=?');$stmt->execute([$id,$id]);$count=(int)$stmt->fetchColumn();
        if($count)$details['Transferencias']=$count;
    }
    return $details;
}

function catalog_usage_count(PDO $pdo,string $catalog,int $id): int {
    return array_sum(catalog_usage_details($pdo,$catalog,$id));
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    $catalog=$_POST['catalog']??'';$action=$_POST['action']??'';
    $definition=$catalogDefinitions[$catalog]??null;
    if(!$definition){$errors['general']='El catálogo seleccionado no es válido.';}
    else{
        $table=$definition['table'];
        try{
            if(in_array($action,['create','update'],true)){
                $name=trim((string)($_POST['name']??''));
                $sortOrder=filter_var($_POST['sort_order']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>9999]]);
                if(!required_text($name,120))$errors['name']='El nombre es obligatorio y admite hasta 120 caracteres.';
                if($sortOrder===false)$errors['sort_order']='El orden debe ser un número entre 0 y 9999.';
                if($action==='update'&&!positive_integer($_POST['id']??null))$errors['id']='La opción seleccionada no es válida.';
                if(!$errors){
                    if($action==='create'){
                        $stmt=$pdo->prepare("INSERT INTO {$table}(name,sort_order) VALUES(?,?)");
                        $stmt->execute([$name,$sortOrder]);
                        $message='Se creó '.$definition['article'].' '.$definition['singular'].'.';
                    }else{
                        $stmt=$pdo->prepare("UPDATE {$table} SET name=?,sort_order=? WHERE id=?");
                        $stmt->execute([$name,$sortOrder,(int)$_POST['id']]);
                        if(!$stmt->rowCount())$message='No fue necesario aplicar cambios.';
                        else $message='Se actualizó '.$definition['article'].' '.$definition['singular'].'.';
                    }
                }
            }elseif(in_array($action,['toggle','delete'],true)){
                $id=$_POST['id']??null;
                if(!positive_integer($id))throw new RuntimeException('La opción seleccionada no es válida.');
                $pdo->beginTransaction();
                $stmt=$pdo->prepare("SELECT active FROM {$table} WHERE id=? FOR UPDATE");$stmt->execute([(int)$id]);$current=$stmt->fetchColumn();
                if($current===false)throw new RuntimeException('La opción ya no existe.');
                if($action==='delete'){
                    if(catalog_usage_count($pdo,$catalog,(int)$id)>0)throw new RuntimeException('No se puede eliminar porque tiene registros asociados. Puede desactivarla.');
                    if($catalog==='groups'&&(int)$current===1){
                        $activeCount=(int)$pdo->query('SELECT COUNT(*) FROM cattle_groups WHERE active=1')->fetchColumn();
                        if($activeCount<=1)throw new RuntimeException('Debe permanecer al menos un grupo ganadero activo.');
                    }
                    $pdo->prepare("DELETE FROM {$table} WHERE id=?")->execute([(int)$id]);
                    $message='Se eliminó '.$definition['article'].' '.$definition['singular'].' permanentemente.';
                }else{
                  if($catalog==='groups'&&(int)$current===1){
                    $activeCount=(int)$pdo->query('SELECT COUNT(*) FROM cattle_groups WHERE active=1')->fetchColumn();
                    if($activeCount<=1)throw new RuntimeException('Debe permanecer al menos un grupo ganadero activo.');
                  }
                  $pdo->prepare("UPDATE {$table} SET active=1-active WHERE id=?")->execute([(int)$id]);
                  $message='Estado actualizado.';
                }
                $pdo->commit();
            }else{$errors['general']='La acción solicitada no es válida.';}
        }catch(PDOException $e){
            if($pdo->inTransaction())$pdo->rollBack();
            $errors['general']=$e->getCode()==='23000'?'Ya existe una opción con ese nombre.':'No fue posible actualizar el catálogo.';
        }catch(Throwable $e){
            if($pdo->inTransaction())$pdo->rollBack();
            $errors['general']=$e->getMessage();
        }
    }
}

$locations=$pdo->query("SELECT l.* FROM locations l ORDER BY l.active DESC,l.sort_order,l.name")->fetchAll();
$groups=$pdo->query("SELECT g.* FROM cattle_groups g ORDER BY g.active DESC,g.sort_order,g.name")->fetchAll();
foreach($locations as &$item){$item['usage_details']=catalog_usage_details($pdo,'locations',(int)$item['id']);$item['usage_count']=array_sum($item['usage_details']);}unset($item);
foreach($groups as &$item){$item['usage_details']=catalog_usage_details($pdo,'groups',(int)$item['id']);$item['usage_count']=array_sum($item['usage_details']);}unset($item);
include __DIR__.'/includes/header.php';
?>
<div class="hero"><div><h1>Catálogos</h1></div></div>
<?php if($message):?><div class="alert success" role="status"><?=htmlspecialchars($message)?></div><?php endif;?>
<?php if($errors):?><div class="alert danger" role="alert"><?=htmlspecialchars(implode(' ',array_values($errors)))?></div><?php endif;?>

<section class="grid catalog-grid">
<?php foreach($catalogDefinitions as $key=>$definition):$items=$key==='locations'?$locations:$groups;?>
  <div class="card full">
    <div class="card-header"><h3><?=htmlspecialchars($definition['title'])?></h3><span class="badge"><?=count(array_filter($items,fn($item)=>(int)$item['active']===1))?> activas</span></div>
    <form method="post" class="catalog-create"><?=csrf_input()?><input type="hidden" name="catalog" value="<?=$key?>"><input type="hidden" name="action" value="create">
      <div><label for="<?=$key?>-name">Nueva <?=htmlspecialchars($definition['singular'])?></label><input id="<?=$key?>-name" name="name" maxlength="120" required></div>
      <div><label for="<?=$key?>-order">Orden</label><input id="<?=$key?>-order" type="number" name="sort_order" min="0" max="9999" value="<?=count($items)*10+10?>" required></div>
      <button class="btn" type="submit">Agregar</button>
    </form>
    <div class="table-scroll">
      <table class="catalog-table">
        <thead><tr><th>Nombre</th><th>Orden</th><th>Estado</th><th>Referencias</th><th>Acciones</th></tr></thead>
        <tbody><?php foreach($items as $item):$formId='catalog-'.$key.'-'.$item['id'];?>
          <tr>
            <td><form id="<?=$formId?>" method="post"><?=csrf_input()?><input type="hidden" name="catalog" value="<?=$key?>"><input type="hidden" name="id" value="<?=$item['id']?>"></form><label class="sr-only" for="<?=$key?>-name-<?=$item['id']?>">Nombre</label><input class="catalog-name-input" form="<?=$formId?>" id="<?=$key?>-name-<?=$item['id']?>" name="name" maxlength="120" value="<?=htmlspecialchars($item['name'])?>" required></td>
            <td><label class="sr-only" for="<?=$key?>-order-<?=$item['id']?>">Orden</label><input class="catalog-order-input" form="<?=$formId?>" id="<?=$key?>-order-<?=$item['id']?>" type="number" name="sort_order" min="0" max="9999" value="<?=$item['sort_order']?>" required></td>
            <td><span class="badge <?=$item['active']?'':'warn'?>"><?=$item['active']?'Activa':'Inactiva'?></span></td>
            <td><span class="catalog-reference"><strong><?=$item['usage_count']?> referencias</strong><?php if($item['usage_details']):?><small><?=htmlspecialchars(implode(' · ',array_map(fn($label,$count)=>$label.' '.$count,array_keys($item['usage_details']),$item['usage_details'])))?></small><?php endif;?></span></td>
            <td><span class="catalog-actions"><button class="btn small" form="<?=$formId?>" name="action" value="update">Guardar</button><button class="btn small secondary" form="<?=$formId?>" name="action" value="toggle"><?=$item['active']?'Desactivar':'Activar'?></button><?php if((int)$item['usage_count']===0):?><button class="btn small danger" form="<?=$formId?>" name="action" value="delete" onclick="return confirm('¿Eliminar esta opción permanentemente?')">Eliminar</button><?php endif;?></span></td>
          </tr>
        <?php endforeach;?></tbody>
      </table>
    </div>
  </div>
<?php endforeach;?>
</section>
<p class="form-help">Desactivar oculta la opción en registros nuevos. Eliminar solo está disponible cuando no existe ninguna referencia asociada y es permanente.</p>
<?php include __DIR__.'/includes/footer.php';?>
