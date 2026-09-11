<?php
function money_crc(float $v): string { return '₡' . number_format($v, 0, ',', '.'); }

function label_es(?string $code): string {
  $labels = [
    'ACTIVO'=>'Activo','VENDIDO'=>'Vendido','MUERTO'=>'Muerto','TRANSFERIDO'=>'Trasladado',
    'NACIMIENTO'=>'Nacimiento','COMPRA'=>'Compra','TRANSFERENCIA'=>'Transferencia',
    'BIRTH'=>'Nacimiento','PURCHASE'=>'Compra','CREATED'=>'Creación','UPDATED'=>'Actualización','DEATH'=>'Muerte','TRANSFER'=>'Traslado','STATUS_CHANGE'=>'Cambio de estado',
    'CLOSED'=>'Cerrado','REOPENED'=>'Reabierto','ACTIVE'=>'Activa','DRAFT'=>'Borrador','APPROVED'=>'Aprobada','RETIRED'=>'Retirada',
    'MANUAL'=>'Manual','SISTEMA_EXISTENTE'=>'Sistema existente','IMPORTACION'=>'Importación',
    'PROJECTED'=>'Proyectado','ACTUAL'=>'Real','PROJECTED_FROM_BASE'=>'Proyección desde base','PROJECTED_FROM_WEIGHT'=>'Proyección desde pesaje',
    'TRANSFER_OUT'=>'Salida por traslado','TRANSFER_IN'=>'Entrada por traslado','SALE'=>'Venta','SUPPLY_COST'=>'Costo de insumos','LABOR_COST'=>'Costo de mano de obra',
    'INSUMO'=>'Insumo','MATERIAL'=>'Material','VETERINARIO'=>'Veterinario','ALIMENTACION'=>'Alimentación','OTRO'=>'Otro',
    'LOGIN_SUCCESS'=>'Inicio de sesión','LOGIN_FAILED'=>'Inicio de sesión fallido','POST_REQUEST'=>'Solicitud de guardado','USER_CREATED'=>'Usuario creado','USER_STATUS_CHANGED'=>'Estado de usuario actualizado','USER_PASSWORD_RESET'=>'Contraseña restablecida','INITIAL_ADMIN_CREATED'=>'Administrador inicial creado','REPORT_EXPORTED'=>'Reporte exportado',
    'CSV'=>'CSV','API'=>'API',
    'ENTRY'=>'Entrada por compra','ISSUE'=>'Salida por consumo','ADJUSTMENT_IN'=>'Ajuste de entrada','ADJUSTMENT_OUT'=>'Ajuste de salida',
  ];
  return $labels[$code??''] ?? (string)$code;
}

function route_label_es(?string $route): string {
  $labels=['login.php'=>'Acceso','logout.php'=>'Salida','setup.php'=>'Configuración inicial','apps.php'=>'Aplicaciones','index.php'=>'Panel','animals.php'=>'Animales','weights.php'=>'Pesajes','weight_import.php'=>'Importación de pesajes','events.php'=>'Eventos','costs.php'=>'Costos','warehouse.php'=>'Bodega','labor.php'=>'Personal','transfers.php'=>'Traslados','sales.php'=>'Ventas','reports.php'=>'Resultados','data_reports.php'=>'Reportes','closings.php'=>'Cierres','closing_view.php'=>'Detalle del cierre','settings.php'=>'Reglas','catalogs.php'=>'Catálogos','users.php'=>'Usuarios','help.php'=>'Ayuda','manual.php'=>'Manual de usuario'];
  return $labels[$route??''] ?? (string)$route;
}

function signed_days_between(string $a, string $b): int {
  $d1 = new DateTimeImmutable($a);
  $d2 = new DateTimeImmutable($b);
  return (int)$d1->diff($d2)->format('%r%a');
}

function days_between(string $a, string $b): int {
  return max(0, signed_days_between($a, $b));
}

function projected_weight(array $animal, array $rules, ?array $lastWeight = null, ?string $asOf = null): float {
  $asOf = $asOf ?: date('Y-m-d');
  $birthDate = $animal['birth_date'] ?? null;
  $birthWeight = (float)$rules['birth_weight_kg'];
  $firstStageDays = (int)$rules['weaning_age_days'];
  $firstStageGain = (float)$rules['gain_0_6_kg_day'];
  $secondStageGain = (float)$rules['gain_6_plus_kg_day'];

  if (!$birthDate) {
    if (!$lastWeight || signed_days_between($lastWeight['weight_date'], $asOf) < 0) return 0.0;
    return (float)$lastWeight['weight_kg']
      + (signed_days_between($lastWeight['weight_date'], $asOf) * $secondStageGain);
  }

  if (signed_days_between($birthDate, $asOf) < 0) {
    return 0.0;
  }

  $baseDate = $birthDate;
  $baseWeight = $birthWeight;
  if ($lastWeight && signed_days_between($lastWeight['weight_date'], $asOf) >= 0) {
    $baseDate = $lastWeight['weight_date'];
    $baseWeight = (float)$lastWeight['weight_kg'];
  }

  $ageAtBase = max(0, signed_days_between($birthDate, $baseDate));
  $daysToProject = max(0, signed_days_between($baseDate, $asOf));
  $daysInFirstStage = min($daysToProject, max(0, $firstStageDays - $ageAtBase));
  $daysInSecondStage = $daysToProject - $daysInFirstStage;

  return $baseWeight
    + ($daysInFirstStage * $firstStageGain)
    + ($daysInSecondStage * $secondStageGain);
}

function livestock_value(float $weightKg, array $rules): float {
  return $weightKg * (float)$rules['price_per_kg'];
}

function monthly_financial_charge(float $openingAsset, float $closingAsset, array $rules): float {
  $rate = (float)$rules['financial_rate_annual'];
  $method = $rules['financial_base_method'] ?? 'AVERAGE_ASSET';
  $base = match ($method) {
    'OPENING_ASSET' => $openingAsset,
    'CLOSING_ASSET' => $closingAsset,
    default => ($openingAsset + $closingAsset) / 2,
  };
  return max(0.0, $base) * ($rate / 12);
}
