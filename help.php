<?php
require __DIR__.'/includes/db.php';
include __DIR__.'/includes/header.php';
?>
<div class="hero"><div><h1>Ayuda al usuario</h1></div><a class="btn" href="manual.php">Abrir manual completo</a></div>

<section class="help-search" aria-label="Buscar en la ayuda">
  <label for="help-filter">¿Qué necesita hacer?</label>
  <input id="help-filter" type="search" placeholder="Ejemplo: registrar un animal, importar pesajes o cerrar un mes" autocomplete="off">
</section>

<section class="help-shortcuts" aria-label="Accesos de ayuda">
  <a href="animals.php#animal-form"><strong>Registrar ganado</strong><span>Nacimientos, compras e información básica.</span></a>
  <a href="weights.php"><strong>Actualizar pesos</strong><span>Registro manual, cobertura y productividad.</span></a>
  <a href="weight_import.php"><strong>Importar un archivo</strong><span>Validación previa de pesajes en formato CSV.</span></a>
  <a href="warehouse.php"><strong>Gestionar bodega</strong><span>Productos, entradas, mínimos, ajustes y kardex.</span></a>
  <a href="reports.php"><strong>Consultar resultados</strong><span>Costos, valoración y resultado por grupo.</span></a>
  <a href="data_reports.php"><strong>Exportar reportes</strong><span>Descarga tablas por período en Excel o PDF.</span></a>
  <?php if(can_roles(['ADMIN','FINANCE'])):?><a href="closings.php"><strong>Cerrar un período</strong><span>Vista previa, conciliación y cierre mensual.</span></a><?php endif;?>
  <?php if(can_roles(['ADMIN'])):?><a href="catalogs.php"><strong>Editar catálogos</strong><span>Ubicaciones y grupos ganaderos.</span></a><?php endif;?>
</section>

<section class="help-layout">
  <div class="card help-topics">
    <div class="card-header"><h3>Preguntas frecuentes</h3></div>
    <details class="help-item" open><summary>¿Cómo registro un animal?</summary><p>Abra <strong>Animales</strong>, indique si es nacimiento o compra, complete los datos obligatorios y seleccione <strong>Registrar animal</strong>. El nacimiento genera automáticamente su evento, peso y valoración inicial.</p></details>
    <details class="help-item"><summary>¿Por qué no puedo cambiar el grupo desde Animales?</summary><p>El cambio de grupo tiene efecto financiero. Debe registrarse desde <strong>Traslados</strong> para crear la salida del grupo de origen y la entrada del grupo de destino por el mismo valor.</p></details>
    <details class="help-item"><summary>¿Cómo corrijo o agrego una ubicación?</summary><p>Un administrador puede hacerlo en <strong>Catálogos</strong>. Las opciones pueden renombrarse, ordenarse o desactivarse. Solo se pueden eliminar cuando no tienen referencias asociadas.</p></details>
    <details class="help-item"><summary>¿Cómo descuento un producto de bodega?</summary><p>Primero registre sus entradas desde <strong>Bodega</strong>. Luego abra <strong>Costos</strong>, seleccione Consumo de bodega, el producto, grupo, actividad y cantidad. El sistema usa el costo promedio, descuenta la existencia y genera el costo operativo en una sola operación.</p></details>
    <details class="help-item"><summary>¿Por qué aparece “Pesaje vencido”?</summary><p>El último pesaje supera la antigüedad máxima definida en las reglas activas. Registre un peso nuevo o importe el archivo correspondiente.</p></details>
    <details class="help-item"><summary>¿Qué ocurre si una importación contiene errores?</summary><p>La importación es atómica: ninguna fila se guarda hasta que todo el lote sea válido. Revise el detalle de filas rechazadas, corrija el archivo y genere nuevamente la vista previa.</p></details>
    <details class="help-item"><summary>¿Por qué no puedo modificar un mes?</summary><p>Los períodos cerrados protegen la información financiera. Un administrador o usuario de Finanzas debe reabrir primero el último cierre, indicando un motivo.</p></details>
    <details class="help-item"><summary>¿Cómo se calcula el valor del ganado?</summary><p>El peso real más reciente se proyecta hasta la fecha consultada con la regla vigente. Ese peso se multiplica por el precio proyectado por kilogramo. Los supuestos pueden versionarse desde <strong>Reglas</strong>.</p></details>
    <details class="help-item"><summary>¿Qué debo hacer antes de cerrar el mes?</summary><p>Revise pesajes pendientes, nacimientos, muertes, costos, mano de obra, traslados y ventas. Después genere la vista previa y confirme que la conciliación sea cero.</p></details>
  </div>

  <aside class="card help-aside">
    <h3>Ruta recomendada</h3>
    <ol>
      <li>Revise las alertas del panel de control.</li>
      <li>Actualice animales, eventos y pesajes.</li>
      <li>Registre costos y mano de obra.</li>
      <li>Complete traslados y ventas.</li>
      <li>Revise resultados y conciliación.</li>
      <li>Cierre el período.</li>
    </ol>
    <a class="btn secondary" href="manual.php#permisos">Consultar permisos</a>
  </aside>
</section>

<div id="help-empty" class="alert danger" hidden>No se encontraron temas con esas palabras. Consulte el manual completo o pruebe una búsqueda más corta.</div>
<script>
(function(){
  const input=document.getElementById('help-filter');
  const items=[...document.querySelectorAll('.help-item')];
  const empty=document.getElementById('help-empty');
  if(!input)return;
  input.addEventListener('input',function(){
    const term=input.value.trim().toLocaleLowerCase('es');let visible=0;
    items.forEach(item=>{const match=!term||item.textContent.toLocaleLowerCase('es').includes(term);item.hidden=!match;if(match)visible++;});
    empty.hidden=visible>0;
  });
})();
</script>
<?php include __DIR__.'/includes/footer.php';?>
