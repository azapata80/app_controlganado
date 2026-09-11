<?php

function data_report_definitions(): array {
    return [
        'animals'=>[
            'title'=>'Animales','description'=>'Inventario y datos de ingreso','date_column'=>'a.acquisition_date','order'=>'a.acquisition_date DESC,a.id DESC',
            'sql'=>'SELECT a.id,a.tag,g.name AS group_name,l.name AS location_name,a.sex,a.birth_date,a.acquisition_date,a.initial_weight_kg,a.purchase_value,a.status,a.origin FROM animals a JOIN cattle_groups g ON g.id=a.group_id LEFT JOIN locations l ON l.id=a.location_id',
            'columns'=>[['id','ID','integer'],['tag','Arete','text'],['group_name','Grupo','text'],['location_name','Ubicación','text'],['sex','Sexo','sex'],['birth_date','Nacimiento','date'],['acquisition_date','Fecha de ingreso','date'],['initial_weight_kg','Peso inicial (kg)','decimal'],['purchase_value','Valor de compra (₡)','money'],['status','Estado','label'],['origin','Origen','label']],
        ],
        'weights'=>[
            'title'=>'Pesajes','description'=>'Historial de peso por animal','date_column'=>'w.weight_date','order'=>'w.weight_date DESC,w.id DESC',
            'sql'=>'SELECT w.id,a.tag,g.name AS group_name,w.weight_date,w.weight_kg,w.source,w.external_reference FROM weights w JOIN animals a ON a.id=w.animal_id JOIN cattle_groups g ON g.id=w.group_id',
            'columns'=>[['id','ID','integer'],['tag','Arete','text'],['group_name','Grupo','text'],['weight_date','Fecha','date'],['weight_kg','Peso (kg)','decimal'],['source','Origen','label'],['external_reference','Referencia externa','text']],
        ],
        'events'=>[
            'title'=>'Eventos','description'=>'Nacimientos y muertes','date_column'=>'e.event_date','order'=>'e.event_date DESC,e.id DESC',
            'sql'=>'SELECT e.id,a.tag,g.name AS group_name,e.event_type,e.event_date,e.weight_kg,e.value_crc,e.notes,e.evidence_reference FROM animal_events e JOIN animals a ON a.id=e.animal_id JOIN cattle_groups g ON g.id=e.group_id',
            'columns'=>[['id','ID','integer'],['tag','Arete','text'],['group_name','Grupo','text'],['event_type','Tipo','label'],['event_date','Fecha','date'],['weight_kg','Peso (kg)','decimal'],['value_crc','Valor (₡)','money'],['notes','Notas','text'],['evidence_reference','Evidencia','text']],
        ],
        'warehouse_stock'=>[
            'title'=>'Existencias de bodega','description'=>'Productos, mínimos y valorización','date_column'=>'DATE(p.created_at)','order'=>'p.name,p.id',
            'sql'=>'SELECT p.id,p.product_code,p.name,p.cost_type,p.unit,p.minimum_stock,p.current_stock,p.average_unit_cost,(p.current_stock*p.average_unit_cost) AS inventory_value,p.active,p.created_at FROM warehouse_products p',
            'columns'=>[['id','ID','integer'],['product_code','Código','text'],['name','Producto','text'],['cost_type','Categoría','label'],['unit','Unidad','text'],['minimum_stock','Existencia mínima','decimal'],['current_stock','Disponible','decimal'],['average_unit_cost','Costo promedio (₡)','money'],['inventory_value','Valor en bodega (₡)','money'],['active','Activo','boolean'],['created_at','Fecha de creación','datetime']],
        ],
        'warehouse_movements'=>[
            'title'=>'Kardex de bodega','description'=>'Entradas, salidas y ajustes','date_column'=>'m.movement_date','order'=>'m.movement_date DESC,m.id DESC',
            'sql'=>'SELECT m.id,p.product_code,p.name AS product_name,m.movement_date,m.movement_type,m.quantity,m.unit,m.unit_cost,m.total_value,m.stock_after,g.name AS group_name,ac.name AS activity_name,m.reference,m.notes,m.created_by FROM warehouse_movements m JOIN warehouse_products p ON p.id=m.product_id LEFT JOIN cattle_groups g ON g.id=m.group_id LEFT JOIN activities ac ON ac.id=m.activity_id',
            'columns'=>[['id','ID','integer'],['product_code','Código','text'],['product_name','Producto','text'],['movement_date','Fecha','date'],['movement_type','Movimiento','label'],['quantity','Cantidad','decimal'],['unit','Unidad','text'],['unit_cost','Costo unitario (₡)','money'],['total_value','Valor (₡)','money'],['stock_after','Existencia resultante','decimal'],['group_name','Grupo destino','text'],['activity_name','Actividad','text'],['reference','Referencia','text'],['notes','Notas','text'],['created_by','Registrado por','text']],
        ],
        'costs'=>[
            'title'=>'Costos','description'=>'Insumos y materiales','date_column'=>'c.cost_date','order'=>'c.cost_date DESC,c.id DESC',
            'sql'=>'SELECT c.id,g.name AS group_name,ac.name AS activity_name,p.product_code,c.cost_date,c.cost_type,c.description,c.unit,c.quantity,c.unit_cost,c.amount FROM costs c JOIN cattle_groups g ON g.id=c.group_id LEFT JOIN activities ac ON ac.id=c.activity_id LEFT JOIN warehouse_products p ON p.id=c.warehouse_product_id',
            'columns'=>[['id','ID','integer'],['group_name','Grupo','text'],['activity_name','Actividad','text'],['product_code','Producto de bodega','text'],['cost_date','Fecha','date'],['cost_type','Tipo','label'],['description','Descripción','text'],['unit','Unidad','text'],['quantity','Cantidad','decimal'],['unit_cost','Costo unitario (₡)','money'],['amount','Monto (₡)','money']],
        ],
        'labor'=>[
            'title'=>'Mano de obra','description'=>'Horas, tarifas y colaboradores','date_column'=>'le.work_date','order'=>'le.work_date DESC,le.id DESC',
            'sql'=>'SELECT le.id,em.employee_code,em.name AS employee_name,g.name AS group_name,ac.name AS activity_name,le.work_date,le.hours,le.hourly_rate,le.amount,le.notes FROM labor_entries le JOIN employees em ON em.id=le.employee_id JOIN cattle_groups g ON g.id=le.group_id JOIN activities ac ON ac.id=le.activity_id',
            'columns'=>[['id','ID','integer'],['employee_code','Código','text'],['employee_name','Colaborador','text'],['group_name','Grupo','text'],['activity_name','Actividad','text'],['work_date','Fecha','date'],['hours','Horas','decimal'],['hourly_rate','Tarifa por hora (₡)','money'],['amount','Monto (₡)','money'],['notes','Notas','text']],
        ],
        'transfers'=>[
            'title'=>'Traslados','description'=>'Movimientos entre grupos','date_column'=>'t.transfer_date','order'=>'t.transfer_date DESC,t.id DESC',
            'sql'=>'SELECT t.id,a.tag,t.transfer_date,gf.name AS from_group,gt.name AS to_group,t.weight_method,t.weight_kg,t.price_per_kg,t.total_value,t.notes FROM transfers t JOIN animals a ON a.id=t.animal_id JOIN cattle_groups gf ON gf.id=t.from_group_id JOIN cattle_groups gt ON gt.id=t.to_group_id',
            'columns'=>[['id','ID','integer'],['tag','Arete','text'],['transfer_date','Fecha','date'],['from_group','Grupo de origen','text'],['to_group','Grupo de destino','text'],['weight_method','Método de peso','label'],['weight_kg','Peso (kg)','decimal'],['price_per_kg','Precio/kg (₡)','money'],['total_value','Valor total (₡)','money'],['notes','Notas','text']],
        ],
        'sales'=>[
            'title'=>'Ventas','description'=>'Valores reales y proyectados','date_column'=>'s.sale_date','order'=>'s.sale_date DESC,s.id DESC',
            'sql'=>'SELECT s.id,a.tag,g.name AS group_name,s.sale_date,s.weight_kg,s.real_price_per_kg,s.total_real,s.projected_price_per_kg,s.total_projected,(s.total_real-s.total_projected) AS variance FROM sales s JOIN animals a ON a.id=s.animal_id JOIN cattle_groups g ON g.id=s.group_id',
            'columns'=>[['id','ID','integer'],['tag','Arete','text'],['group_name','Grupo','text'],['sale_date','Fecha','date'],['weight_kg','Peso (kg)','decimal'],['real_price_per_kg','Precio real/kg (₡)','money'],['total_real','Total real (₡)','money'],['projected_price_per_kg','Precio proyectado/kg (₡)','money'],['total_projected','Total proyectado (₡)','money'],['variance','Variación (₡)','money']],
        ],
        'closings'=>[
            'title'=>'Cierres mensuales','description'=>'Resultados consolidados por período','date_column'=>'mc.month_end','order'=>'mc.month_end DESC,mc.id DESC',
            'sql'=>'SELECT mc.id,mc.month_end,mc.status,mc.opening_asset,mc.closing_asset,mc.biological_result,mc.operating_result,mc.financial_charge,mc.adjusted_result,mc.closed_by,mc.closed_at,mc.reopen_reason FROM monthly_closings mc',
            'columns'=>[['id','ID','integer'],['month_end','Período','date'],['status','Estado','label'],['opening_asset','Activo inicial (₡)','money'],['closing_asset','Activo final (₡)','money'],['biological_result','Resultado biológico (₡)','money'],['operating_result','Resultado operativo (₡)','money'],['financial_charge','Carga financiera (₡)','money'],['adjusted_result','Resultado ajustado (₡)','money'],['closed_by','Cerrado por','text'],['closed_at','Fecha de cierre','datetime'],['reopen_reason','Motivo de reapertura','text']],
        ],
    ];
}

function data_report_request(array $input): array {
    $definitions=data_report_definitions();
    $key=(string)($input['report']??'animals');
    if(!isset($definitions[$key]))$key='animals';
    $scope=($input['scope']??'range')==='all'?'all':'range';
    $from=(string)($input['from']??date('Y-m-01'));
    $to=(string)($input['to']??date('Y-m-d'));
    $valid=static function(string $date):bool{$parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date);return $parsed instanceof DateTimeImmutable&&$parsed->format('Y-m-d')===$date;};
    $errors=[];
    if($scope==='range'&&(!$valid($from)||!$valid($to)))$errors[]='Indique un rango de fechas válido.';
    if($scope==='range'&&$valid($from)&&$valid($to)&&$from>$to)$errors[]='La fecha inicial no puede ser posterior a la fecha final.';
    return [$key,$definitions[$key],$scope,$from,$to,$errors];
}

function data_report_statement(PDO $pdo,array $definition,string $scope,string $from,string $to,?int $limit=null): PDOStatement {
    $sql=$definition['sql'];$params=[];
    if($scope!=='all'){$sql.=' WHERE '.$definition['date_column'].' BETWEEN ? AND ?';$params=[$from,$to];}
    $sql.=' ORDER BY '.$definition['order'];
    if($limit!==null)$sql.=' LIMIT '.max(1,(int)$limit);
    $stmt=$pdo->prepare($sql);$stmt->execute($params);return $stmt;
}

function data_report_display(mixed $value,string $type): string {
    if($value===null||$value==='')return '—';
    return match($type){
        'label'=>label_es((string)$value),
        'sex'=>$value==='M'?'Macho':($value==='F'?'Hembra':(string)$value),
        'boolean'=>(int)$value===1?'Sí':'No',
        'money'=>number_format((float)$value,2,',','.'),
        'decimal'=>number_format((float)$value,2,',','.'),
        'datetime'=>(new DateTimeImmutable((string)$value))->format('d/m/Y H:i'),
        'date'=>(new DateTimeImmutable((string)$value))->format('d/m/Y'),
        default=>(string)$value,
    };
}

function data_report_period_label(string $scope,string $from,string $to): string {
    if($scope==='all')return 'Todos los registros';
    $start=DateTimeImmutable::createFromFormat('!Y-m-d',$from);$end=DateTimeImmutable::createFromFormat('!Y-m-d',$to);
    if(!$start||!$end)return 'Rango de fechas no válido';
    return 'Del '.$start->format('d/m/Y').' al '.$end->format('d/m/Y');
}

function data_report_excel(array $definition,array $rows,string $period): string {
    $escape=static fn(mixed $value):string=>htmlspecialchars((string)$value,ENT_XML1|ENT_QUOTES,'UTF-8');
    $xml='<?xml version="1.0" encoding="UTF-8"?>'."\n";
    $xml.='<?mso-application progid="Excel.Sheet"?>'."\n";
    $xml.='<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"><Styles><Style ss:ID="Header"><Font ss:Bold="1"/><Interior ss:Color="#DDEBF0" ss:Pattern="Solid"/></Style></Styles><Worksheet ss:Name="'.$escape($definition['title']).'"><Table>';
    $xml.='<Row><Cell ss:MergeAcross="'.(count($definition['columns'])-1).'"><Data ss:Type="String">'.$escape('Sistema de Gestión y Control de Ganado · '.$definition['title']).'</Data></Cell></Row>';
    $xml.='<Row><Cell ss:MergeAcross="'.(count($definition['columns'])-1).'"><Data ss:Type="String">'.$escape($period).'</Data></Cell></Row><Row/>';
    $xml.='<Row>';foreach($definition['columns'] as [, $label])$xml.='<Cell ss:StyleID="Header"><Data ss:Type="String">'.$escape($label).'</Data></Cell>';$xml.='</Row>';
    foreach($rows as $row){$xml.='<Row>';foreach($definition['columns'] as [$key,,$type]){$value=$row[$key]??null;$numeric=in_array($type,['integer','decimal','money'],true)&&$value!==null&&$value!=='';$xml.='<Cell><Data ss:Type="'.($numeric?'Number':'String').'">'.$escape($numeric?(string)(float)$value:data_report_display($value,$type)).'</Data></Cell>';}$xml.='</Row>';}
    return $xml.'</Table></Worksheet></Workbook>';
}

function data_report_pdf(array $definition,array $rows,string $period): string {
    $pageWidth=842.0;$pageHeight=595.0;$margin=24.0;$usable=$pageWidth-($margin*2);$fontSize=6.5;$lineHeight=8.0;
    $columns=$definition['columns'];$weights=[];
    foreach($columns as [$key,$label]){$max=mb_strlen($label);foreach(array_slice($rows,0,150) as $row)$max=max($max,min(32,mb_strlen((string)($row[$key]??''))));$weights[]=max(6,min(22,$max));}
    $total=array_sum($weights);$widths=array_map(static fn($weight)=>$usable*$weight/$total,$weights);
    $pdfText=static function(string $text):string{$converted=iconv('UTF-8','Windows-1252//TRANSLIT',$text);return str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$converted===false?'':$converted);};
    $wrap=static function(string $text,float $width)use($fontSize):array{$text=preg_replace('/\s+/u',' ',trim($text));if($text==='')return ['—'];$max=max(3,(int)floor(($width-5)/($fontSize*.52)));return array_slice(preg_split('/\R/',wordwrap($text,$max,"\n",true)),0,3);};
    $pages=[];$commands=[];$y=0.0;
    $startPage=function()use(&$commands,&$y,$pageHeight,$margin,$definition,$period,$pdfText){$commands=['0.09 0.21 0.29 rg','BT /F1 15 Tf '.$margin.' '.($pageHeight-$margin-12).' Td ('.$pdfText('Sistema de Gestión y Control de Ganado').') Tj ET','BT /F1 11 Tf '.$margin.' '.($pageHeight-$margin-31).' Td ('.$pdfText($definition['title']).') Tj ET','0.40 0.48 0.52 rg','BT /F1 7 Tf '.$margin.' '.($pageHeight-$margin-45).' Td ('.$pdfText($period.' · Generado '.date('d/m/Y H:i')).') Tj ET'];$y=$pageHeight-$margin-59;};
    $drawRow=function(array $values,bool $header=false)use(&$commands,&$y,$margin,$widths,$wrap,$pdfText,$fontSize,$lineHeight){$wrapped=[];$lines=1;foreach($values as $i=>$value){$cell=$wrap((string)$value,$widths[$i]);$wrapped[]=$cell;$lines=max($lines,count($cell));}$height=max(17,$lines*$lineHeight+5);$x=$margin;if($header)$commands[]='0.88 0.93 0.94 rg '.$x.' '.($y-$height).' '.array_sum($widths).' '.$height.' re f';$commands[]='0.75 0.82 0.84 RG';foreach($wrapped as $i=>$cell){$width=$widths[$i];$commands[]=$x.' '.($y-$height).' '.$width.' '.$height.' re S';foreach($cell as $line=>$text)$commands[]='0.09 0.21 0.29 rg BT /F1 '.($header?'6.5':'6').' Tf '.($x+3).' '.($y-10-($line*$lineHeight)).' Td ('.$pdfText($text).') Tj ET';$x+=$width;}$y-=$height;return $height;};
    $headers=array_map(static fn($column)=>$column[1],$columns);$startPage();$drawRow($headers,true);
    foreach($rows as $row){$values=[];foreach($columns as [$key,,$type])$values[]=data_report_display($row[$key]??null,$type);$testLines=1;foreach($values as $i=>$value)$testLines=max($testLines,count($wrap($value,$widths[$i])));$height=max(17,$testLines*$lineHeight+5);if($y-$height<$margin+12){$pages[]=implode("\n",$commands);$startPage();$drawRow($headers,true);}$drawRow($values);}
    if(!$rows)$commands[]='BT /F1 9 Tf '.$margin.' '.($y-20).' Td ('.$pdfText('No hay registros para los filtros seleccionados.').') Tj ET';$pages[]=implode("\n",$commands);
    $objects=[1=>'<< /Type /Catalog /Pages 2 0 R >>',3=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>'];$kids=[];$next=4;
    foreach($pages as $content){$pageObject=$next++;$contentObject=$next++;$kids[]=$pageObject.' 0 R';$objects[$pageObject]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '.$pageWidth.' '.$pageHeight.'] /Resources << /Font << /F1 3 0 R >> >> /Contents '.$contentObject.' 0 R >>';$objects[$contentObject]='<< /Length '.strlen($content).' >>' . "\nstream\n".$content."\nendstream";}
    $objects[2]='<< /Type /Pages /Kids ['.implode(' ',$kids).'] /Count '.count($kids).' >>';ksort($objects);$pdf="%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";$offsets=[0];foreach($objects as $number=>$object){$offsets[$number]=strlen($pdf);$pdf.=$number." 0 obj\n".$object."\nendobj\n";}$xref=strlen($pdf);$pdf.='xref'."\n0 ".(count($objects)+1)."\n0000000000 65535 f \n";for($i=1;$i<=count($objects);$i++)$pdf.=sprintf('%010d 00000 n ', $offsets[$i])."\n";$pdf.='trailer << /Size '.(count($objects)+1).' /Root 1 0 R >>'."\nstartxref\n".$xref."\n%%EOF";return $pdf;
}
