<?php

function default_business_rules(): array {
    return [
        'projection_method' => 'STAGED_GROWTH',
        'birth_weight_kg' => 25.0,
        'weaning_age_days' => 183,
        'gain_0_6_kg_day' => 1.25,
        'target_weaning_kg' => 250.0,
        'gain_6_plus_kg_day' => 0.55,
        'target_sale_kg' => 600.0,
        'price_per_kg' => 1500.0,
        'max_weight_age_days' => 60,
        'minimum_monthly_weight_coverage' => 50.0,
        'financial_rate_annual' => 0.08,
        'financial_base_method' => 'AVERAGE_ASSET',
    ];
}

function cast_rule_value(string $value, string $type): mixed {
    return match ($type) {
        'DECIMAL' => (float)$value,
        'INTEGER' => (int)$value,
        'BOOLEAN' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
        default => $value,
    };
}

function load_business_rules(PDO $pdo, ?string $asOf = null): array {
    $asOf = $asOf ?: date('Y-m-d');
    $rules = default_business_rules();
    $sql = "SELECT rs.id, rs.version, rp.rule_key, rp.rule_value, rp.value_type
              FROM rule_sets rs
              JOIN rule_parameters rp ON rp.rule_set_id = rs.id
             WHERE rs.id = (
                   SELECT selected.id
                     FROM rule_sets selected
                    WHERE selected.status IN ('ACTIVE','RETIRED')
                      AND selected.effective_from <= ?
                      AND (selected.effective_to IS NULL OR selected.effective_to >= ?)
                    ORDER BY selected.effective_from DESC, selected.id DESC
                    LIMIT 1
             )";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$asOf, $asOf]);
        $rows = $stmt->fetchAll();
    } catch (PDOException $e) {
        // Permite ejecutar la aplicación durante la migración de una base antigua.
        $rules['_rule_set_id'] = null;
        $rules['_rule_version'] = 'DEFAULT-FALLBACK';
        return $rules;
    }

    if (!$rows) {
        $rules['_rule_set_id'] = null;
        $rules['_rule_version'] = 'DEFAULT-FALLBACK';
        return $rules;
    }

    $rules['_rule_set_id'] = (int)$rows[0]['id'];
    $rules['_rule_version'] = $rows[0]['version'];
    foreach ($rows as $row) {
        $rules[$row['rule_key']] = cast_rule_value($row['rule_value'], $row['value_type']);
    }
    return $rules;
}

function validate_business_rules(array $rules): array {
    $errors = [];
    $positive = ['birth_weight_kg', 'weaning_age_days', 'gain_0_6_kg_day', 'target_weaning_kg',
        'gain_6_plus_kg_day', 'target_sale_kg', 'price_per_kg', 'max_weight_age_days'];
    foreach ($positive as $key) {
        if (!isset($rules[$key]) || !is_numeric($rules[$key]) || (float)$rules[$key] <= 0) {
            $errors[$key] = 'Debe ser un número mayor que cero.';
        }
    }
    if (!isset($rules['minimum_monthly_weight_coverage']) || (float)$rules['minimum_monthly_weight_coverage'] < 0 || (float)$rules['minimum_monthly_weight_coverage'] > 100) {
        $errors['minimum_monthly_weight_coverage'] = 'Debe estar entre 0 y 100.';
    }
    if (!isset($rules['financial_rate_annual']) || (float)$rules['financial_rate_annual'] < 0 || (float)$rules['financial_rate_annual'] > 1) {
        $errors['financial_rate_annual'] = 'Debe estar entre 0 y 1 (8% = 0.08).';
    }
    if (!in_array($rules['financial_base_method'] ?? '', ['AVERAGE_ASSET', 'OPENING_ASSET', 'CLOSING_ASSET'], true)) {
        $errors['financial_base_method'] = 'Método financiero no soportado.';
    }
    if (($rules['projection_method'] ?? '') !== 'STAGED_GROWTH') {
        $errors['projection_method'] = 'Método de proyección no soportado.';
    }
    return $errors;
}
