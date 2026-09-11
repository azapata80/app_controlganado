<?php
require_once __DIR__ . '/../includes/rules.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/weight_service.php';

$tests = 0;
$failures = [];

function check(string $name, bool $condition, string $detail = ''): void {
    global $tests, $failures;
    $tests++;
    if (!$condition) $failures[] = $name . ($detail ? ": {$detail}" : '');
}

function close_to(float $actual, float $expected, float $tolerance = 0.0001): bool {
    return abs($actual - $expected) <= $tolerance;
}

$rules = default_business_rules();
$animal = ['birth_date' => '2026-01-01'];
$birth = new DateTimeImmutable($animal['birth_date']);
$day183 = $birth->modify('+183 days')->format('Y-m-d');
$day184 = $birth->modify('+184 days')->format('Y-m-d');

check('modelo inicial válido', validate_business_rules($rules) === []);
check('peso al nacimiento', close_to(projected_weight($animal, $rules, null, '2026-01-01'), 25.0));
check('primer tramo completo', close_to(projected_weight($animal, $rules, null, $day183), 253.75));
check('cambio al segundo tramo', close_to(projected_weight($animal, $rules, null, $day184), 254.30));

$day180 = $birth->modify('+180 days')->format('Y-m-d');
$day190 = $birth->modify('+190 days')->format('Y-m-d');
$fromRealWeight = projected_weight($animal, $rules, ['weight_date' => $day180, 'weight_kg' => 240], $day190);
check('pesaje que cruza ambos tramos', close_to($fromRealWeight, 247.60), "obtenido {$fromRealWeight}");

$futureWeight = projected_weight($animal, $rules, ['weight_date' => '2027-01-01', 'weight_kg' => 999], '2026-01-10');
check('pesaje futuro ignorado', close_to($futureWeight, 36.25));
check('fecha anterior al nacimiento', close_to(projected_weight($animal, $rules, null, '2025-12-31'), 0.0));
$purchaseProjection = projected_weight(['birth_date'=>null],$rules,['weight_date'=>'2026-01-01','weight_kg'=>400],'2026-01-11');
check('compra sin nacimiento usa tramo adulto', close_to($purchaseProjection,405.5));
check('valorización por peso', close_to(livestock_value(100, $rules), 150000.0));
check('carga sobre activo promedio', close_to(monthly_financial_charge(1000000, 1200000, $rules), 7333.333333, 0.01));

$invalid = $rules;
$invalid['financial_rate_annual'] = 1.5;
$invalid['minimum_monthly_weight_coverage'] = -1;
check('validación de tasa', isset(validate_business_rules($invalid)['financial_rate_annual']));
check('validación de cobertura', isset(validate_business_rules($invalid)['minimum_monthly_weight_coverage']));
check('fecha ISO válida', valid_iso_date('2026-09-10'));
check('fecha imposible rechazada', !valid_iso_date('2026-02-30'));
check('fecha futura rechazada', !date_not_future('2026-09-11', '2026-09-10'));
check('número positivo', positive_number('1.25'));
check('número negativo rechazado', !positive_number('-1'));
check('mes válido', month_bounds('2026-02') === ['2026-02-01','2026-02-28']);
check('mes inválido', month_bounds('2026-13') === null);
check('fin de período', period_month_end('2026-02-10') === '2026-02-28');

if ($failures) {
    fwrite(STDERR, "FALLARON " . count($failures) . " DE {$tests} PRUEBAS\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "OK: {$tests} pruebas superadas.\n";
