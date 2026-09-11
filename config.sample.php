<?php
/**
 * Copie este archivo como config.php. En producción se recomienda definir las
 * variables GANADERIA_DB_* en el hosting en lugar de escribir secretos aquí.
 */
$env = static function (string $name, string $default = ''): string {
    $value = getenv($name);
    return $value === false ? $default : $value;
};

return [
  'environment' => $env('GANADERIA_ENV', 'production'),
  'db_host' => $env('GANADERIA_DB_HOST', 'localhost'),
  'db_port' => (int)$env('GANADERIA_DB_PORT', '3306'),
  'db_name' => $env('GANADERIA_DB_NAME', 'ganaderia_el_viejo'),
  'db_user' => $env('GANADERIA_DB_USER', 'usuario_mysql'),
  'db_pass' => $env('GANADERIA_DB_PASS', 'cambiar_esta_clave'),
  'base_url' => rtrim($env('GANADERIA_BASE_URL', ''), '/'),
  'app_name' => $env('GANADERIA_APP_NAME', 'Sistema de Gestión y Control de Ganado'),
  // Déjelo vacío para mantener deshabilitada la API de integración.
  'integration_api_key' => $env('GANADERIA_API_KEY', ''),
];
