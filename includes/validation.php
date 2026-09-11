<?php

function valid_iso_date(mixed $value): bool {
    if (!is_string($value)) return false;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

function date_not_future(mixed $value, ?string $today = null): bool {
    if (!valid_iso_date($value)) return false;
    return $value <= ($today ?: date('Y-m-d'));
}

function value_in(mixed $value, array $allowed): bool {
    return is_string($value) && in_array($value, $allowed, true);
}

function validate_fields(array $data, array $rules): array {
    $errors = [];
    foreach ($rules as $field => $rule) {
        [$validator, $message] = $rule;
        if (!$validator($data[$field] ?? null)) $errors[$field] = $message;
    }
    return $errors;
}

function positive_number(mixed $value, bool $allowZero = false): bool {
    if (!is_numeric($value)) return false;
    return $allowZero ? (float)$value >= 0 : (float)$value > 0;
}

function positive_integer(mixed $value): bool {
    return filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false;
}

function required_text(mixed $value, int $maxLength = 255): bool {
    if (!is_string($value)) return false;
    $value = trim($value);
    $length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    return $value !== '' && $length <= $maxLength;
}
