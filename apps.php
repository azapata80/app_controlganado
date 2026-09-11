<?php
require __DIR__.'/includes/db.php';

$sections=[
  'Gestión diaria'=>[
    ['Panel de control','Indicadores y alertas','index.php','chart',['ADMIN','OPERATOR','FINANCE','VIEWER'],'blue'],
    ['Animales','Inventario e historial','animals.php','cow',['ADMIN','OPERATOR'],'green'],
    ['Pesajes','Peso y productividad','weights.php','scale',['ADMIN','OPERATOR'],'cyan'],
    ['Eventos','Nacimientos y muertes','events.php','event',['ADMIN','OPERATOR'],'lime'],
  ],
  'Operación y resultados'=>[
    ['Costos','Consumos y compras directas','costs.php','cost',['ADMIN','OPERATOR'],'navy'],
    ['Bodega','Existencias y movimientos','warehouse.php','warehouse',['ADMIN','OPERATOR'],'cyan'],
    ['Personal','Colaboradores y mano de obra','labor.php','people',['ADMIN','OPERATOR'],'green'],
    ['Traslados','Movimientos entre grupos','transfers.php','transfer',['ADMIN','OPERATOR'],'cyan'],
    ['Ventas','Ventas reales y proyectadas','sales.php','sale',['ADMIN','OPERATOR'],'lime'],
    ['Resultados','Estado de resultados','reports.php','report',['ADMIN','OPERATOR','FINANCE','VIEWER'],'blue'],
    ['Reportes','Exportación a Excel y PDF','data_reports.php','download',['ADMIN','OPERATOR','FINANCE','VIEWER'],'green'],
    ['Cierres','Conciliación mensual','closings.php','lock',['ADMIN','FINANCE'],'navy'],
  ],
  'Configuración y soporte'=>[
    ['Reglas','Modelo financiero y productivo','settings.php','settings',['ADMIN','FINANCE'],'green'],
    ['Catálogos','Ubicaciones y grupos','catalogs.php','catalog',['ADMIN'],'cyan'],
    ['Usuarios','Accesos y seguridad','users.php','users',['ADMIN'],'navy'],
    ['Ayuda','Guías y manual de usuario','help.php','help',['ADMIN','OPERATOR','FINANCE','VIEWER'],'lime'],
  ],
];

function app_icon(string $name): string {
  $paths=match($name){
    'chart'=>'<path d="M4 19V9m6 10V5m6 14v-7m4 7H2"/>',
    'cow'=>'<path d="M7 8 4 6v4l3 2m10-4 3-2v4l-3 2M8 8c1-2 7-2 8 0l-1 8c-.3 2-1.8 3-3 3s-2.7-1-3-3L8 8Zm2.5 6h3M11 16h.01m2-.01h.01"/>',
    'scale'=>'<path d="M5 19h14M7 16a7 7 0 1 1 10 0M12 9l3-2m-3 2 2 3"/>',
    'event'=>'<path d="M6 3v3m12-3v3M4 9h16M5 5h14a1 1 0 0 1 1 1v14H4V6a1 1 0 0 1 1-1Zm7 7v6m-3-3h6"/>',
    'cost'=>'<path d="M12 2v20m5-16.5c-1-1-2.5-1.5-5-1.5-3 0-5 1.4-5 3.5S9 11 12 11s5 1.4 5 3.5S15 18 12 18c-2.5 0-4-.5-5-1.5"/>',
    'warehouse'=>'<path d="M3 9 12 3l9 6v11H3V9Zm4 11v-7h10v7M7 9h.01M12 9h.01M17 9h.01"/>',
    'people'=>'<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m7-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm13 10v-2a4 4 0 0 0-3-3.9m-2-11.8a4 4 0 0 1 0 7.4"/>',
    'transfer'=>'<path d="M4 7h14m-4-4 4 4-4 4M20 17H6m4 4-4-4 4-4"/>',
    'sale'=>'<path d="M3 5h2l2.5 10h9l2-7H7m2 11h.01M16 19h.01"/>',
    'report'=>'<path d="M6 2h9l4 4v16H6V2Zm9 0v5h4M9 12h6m-6 4h6"/>',
    'download'=>'<path d="M12 3v12m-4-4 4 4 4-4M5 20h14"/>',
    'lock'=>'<path d="M6 10h12v11H6V10Zm3 0V7a3 3 0 0 1 6 0v3m-3 4v3"/>',
    'settings'=>'<path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm0-13v3m0 13v3m9-9h-3m-12 0H3m15.4-6.4-2.1 2.1M7.7 16.3l-2.1 2.1m12.8 0-2.1-2.1M7.7 8.2 5.6 6.1"/>',
    'catalog'=>'<path d="M4 5h16M4 12h16M4 19h16M7 3v4m5 3v4m5 3v4"/>',
    'users'=>'<path d="M15 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2m7-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm9 2v6m-3-3h6"/>',
    default=>'<path d="M12 18h.01M9.1 9a3 3 0 0 1 5.8 1c0 2-3 2-3 5M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Z"/>',
  };
  return '<svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'.$paths.'</svg>';
}

include __DIR__.'/includes/header.php';
?>
<div class="hero app-launcher-hero"><div><p class="eyebrow">Inicio</p><h1>Aplicaciones</h1></div><span class="badge"><?=htmlspecialchars(current_user()['display_name']??'')?></span></div>

<div class="app-launcher">
<?php foreach($sections as $section=>$modules):$visible=array_values(array_filter($modules,fn($module)=>can_roles($module[4])));if(!$visible)continue;?>
  <section class="app-section">
    <h2><?=htmlspecialchars($section)?></h2>
    <div class="app-grid">
      <?php foreach($visible as [$title,$description,$route,$icon,$roles,$color]):?>
      <a class="app-tile" href="<?=$route?>">
        <span class="app-icon <?=$color?>"><?=app_icon($icon)?></span>
        <span class="app-copy"><strong><?=htmlspecialchars($title)?></strong><small><?=htmlspecialchars($description)?></small></span>
      </a>
      <?php endforeach;?>
    </div>
  </section>
<?php endforeach;?>
</div>
<?php include __DIR__.'/includes/footer.php';?>
