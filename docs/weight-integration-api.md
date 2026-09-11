# API de integración de pesajes

La API permanece deshabilitada mientras `GANADERIA_API_KEY` esté vacía. Use una
clave extensa y aleatoria, transmita siempre por HTTPS y envíela en el encabezado
`X-API-Key`.

## Solicitud

`POST /api/weights.php`

```http
Content-Type: application/json
X-API-Key: clave-configurada
```

```json
{
  "source_name": "Sistema de inventario",
  "weights": [
    {
      "tag": "EV-26001",
      "weight_date": "2026-09-10",
      "weight_kg": 252.5,
      "external_reference": "PESA-8491"
    }
  ]
}
```

La fuente se registra siempre como `SISTEMA_EXISTENTE`. El lote admite hasta
5.000 filas y 2 MB. La operación es atómica: si una fila falla, ninguna se
guarda.

## Respuestas

- `201`: lote importado.
- `400`: formato incorrecto.
- `401`: clave inválida.
- `409`: duplicado detectado durante la escritura.
- `413`: solicitud demasiado grande.
- `422`: filas inválidas; la respuesta incluye el detalle.
- `503`: integración deshabilitada.

Un pesaje se considera duplicado cuando coinciden animal, fecha y fuente.
