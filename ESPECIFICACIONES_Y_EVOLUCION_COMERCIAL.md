# Especificaciones, funcionalidades y evolución comercial

**Producto:** Sistema de Gestión y Control de Ganado  
**Versión documentada:** 1.1.0  
**Fecha:** 11 de septiembre de 2026  
**Estado:** aplicación web funcional, disponible para Docker y hosting PHP/MySQL

## 1. Propósito del producto

El Sistema de Gestión y Control de Ganado centraliza la operación ganadera y su
seguimiento financiero. Permite controlar animales, pesos, eventos, costos,
mano de obra, inventario de bodega, traslados, ventas, valorizaciones y cierres
mensuales desde una interfaz en español adaptada a computadoras, tabletas y
teléfonos.

El producto está orientado inicialmente a una organización ganadera que opera
una o varias ubicaciones dentro de una misma instalación. Su principal
diferenciador funcional es el uso de reglas productivas y financieras
versionadas: Finanzas puede cambiar parámetros futuros sin reprogramar la
aplicación ni alterar la explicación de cálculos históricos.

## 2. Usuarios objetivo

- Propietarios, gerentes y administradores de explotaciones ganaderas.
- Encargados de finca responsables del inventario, pesaje y movimientos.
- Personal financiero encargado de valorizaciones, resultados y cierres.
- Responsables de bodega, suministros y control de costos.
- Auditores o usuarios de consulta que requieren información sin modificarla.

## 3. Alcance funcional actual

### 3.1 Inicio y panel de control

- Lanzador visual de aplicaciones con módulos agrupados por proceso.
- Accesos visibles de acuerdo con el perfil del usuario.
- Panel con indicadores gerenciales y alertas operativas.
- Acceso rápido al centro de ayuda y al manual.
- Navegación responsive con menú adaptado a pantallas pequeñas.

### 3.2 Animales y ciclo de vida

- Registro y edición de animales identificados por arete único.
- Clasificación por grupo, ubicación, sexo, estado y origen.
- Ingresos por nacimiento, compra o transferencia.
- Captura de fecha de nacimiento o adquisición, peso inicial y valor de compra.
- Creación automática del peso y del evento inicial cuando corresponde.
- Registro de muerte con peso, valorización, notas y referencia de evidencia.
- Registro de venta y cambio de estado del animal.
- Historial de altas, modificaciones, traslados, ventas y bajas.
- Conservación de la regla aplicada en operaciones que requieren valorización.

### 3.3 Pesajes

- Registro manual del peso por animal y fecha.
- Historial cronológico de pesos.
- Asociación del pesaje con el grupo vigente.
- Proyección de peso cuando no existe una medición actual.
- Control de duplicados por animal, fecha y origen.
- Importación CSV con vista previa y validación previa a la escritura.
- Validación de aretes, fechas, animales inactivos y filas duplicadas.
- API de integración para un sistema externo de pesajes, protegida por clave y
  deshabilitada de forma predeterminada.

### 3.4 Eventos ganaderos

- Gestión de nacimientos y muertes.
- Asociación del evento con animal, grupo, fecha, peso y valor.
- Notas y evidencia de respaldo para bajas.
- Actualización coordinada del inventario y el historial del animal.

### 3.5 Costos e insumos

- Registro de costos por grupo, actividad y fecha.
- Clasificación del costo y descripción del consumo.
- Cantidad, unidad, costo unitario y monto total.
- Compra o consumo directo cuando no se utiliza un producto de bodega.
- Consumo de existencias de bodega integrado con el registro del costo.
- Bloqueo de movimientos en períodos financieros cerrados.

### 3.6 Gestión de bodega

- Catálogo de productos con código único, nombre, categoría y unidad.
- Existencia mínima, existencia actual y estado activo.
- Entradas de inventario y ajustes positivos o negativos.
- Salidas destinadas a un grupo y una actividad.
- Costo promedio ponderado y valorización de existencias.
- Validación transaccional para impedir inventario negativo.
- Kardex con fecha, cantidad, costo, saldo, referencia, notas y responsable.
- Alertas de productos por debajo de la existencia mínima.

### 3.7 Personal y mano de obra

- Catálogo de colaboradores con código, nombre, tarifa y estado.
- Catálogo de actividades operativas.
- Registro de horas trabajadas por persona, grupo, actividad y fecha.
- Cálculo del importe usando la tarifa aplicable al registro.
- Conservación de la tarifa histórica aunque cambie la tarifa del colaborador.
- Activación y desactivación administrativa de colaboradores y actividades.

### 3.8 Traslados

- Movimiento individual de animales entre grupos.
- Uso de peso actual o proyectado.
- Valorización del traslado según la regla vigente.
- Registro equivalente de salida y entrada para conciliación.
- Actualización del grupo del animal y conservación de su historial.

### 3.9 Ventas

- Registro de peso y precio real por kilogramo.
- Cálculo del ingreso real.
- Comparación con precio e ingreso proyectados.
- Cálculo de variación entre resultado real y esperado.
- Cambio del animal al estado vendido y registro histórico.

### 3.10 Resultados financieros

- Valorización del inventario ganadero por grupo.
- Resultado biológico y operativo.
- Incorporación de costos de insumos y mano de obra.
- Comparación de ventas reales y proyectadas.
- Aplicación de carga financiera configurable.
- Consulta del resultado ajustado del período.

### 3.11 Cierres mensuales

- Simulación previa del cierre.
- Cálculo consolidado y por grupo.
- Instantánea de reglas y valorizaciones utilizadas.
- Conciliación de apertura, movimientos y cierre.
- Confirmación y bloqueo del período cerrado.
- Reapertura restringida a Administración y Finanzas, con motivo y bitácora.
- Vista detallada, impresión y exportación CSV del cierre.

### 3.12 Reglas configurables

- Versiones de reglas en borrador, aprobadas, activas o retiradas.
- Fechas de vigencia y conservación de versiones anteriores.
- Simulación de una versión antes de activarla.
- Peso estimado al nacimiento.
- Ganancia diaria por tramos de edad.
- Metas de peso y antigüedad máxima aceptada para pesajes.
- Precio proyectado por kilogramo.
- Cobertura mínima mensual de pesaje.
- Tasa financiera anual y método de cálculo de su base.
- Reproducción de cálculos históricos usando la regla vigente en cada fecha.

### 3.13 Catálogos

- Administración de ubicaciones y grupos ganaderos.
- Creación, edición, orden, activación y desactivación.
- Conteo y detalle de referencias asociadas.
- Eliminación permanente únicamente cuando no existen registros relacionados.
- Catálogos demostrativos actuales: Montezuma, La Flor y Otras.

### 3.14 Reportes y exportaciones

El módulo permite consultar todos los registros o filtrar por un rango de
fechas. La vista previa muestra hasta 200 filas, mientras que la exportación
incluye el resultado completo.

Conjuntos disponibles:

1. Animales.
2. Pesajes.
3. Eventos.
4. Existencias de bodega.
5. Kardex de bodega.
6. Costos.
7. Mano de obra.
8. Traslados.
9. Ventas.
10. Cierres mensuales.

Los resultados se descargan en un archivo compatible con Excel o en PDF. Las
columnas, estados y valores se presentan en español y cada exportación queda
registrada en la bitácora de actividad.

### 3.15 Ayuda y documentación

- Centro de ayuda con búsqueda temática y accesos rápidos.
- Manual de usuario integrado en la aplicación.
- Versión del manual apta para imprimir o guardar como PDF.
- Guías separadas para Docker, SiteGround, producción e integración de pesajes.
- Datos demostrativos disponibles mediante consola o importación en phpMyAdmin.

## 4. Perfiles y permisos

| Perfil | Capacidades principales |
|---|---|
| Administrador | Acceso completo, usuarios, catálogos, reglas, cierres y operación |
| Operación | Animales, pesajes, eventos, costos, bodega, mano de obra, traslados y ventas |
| Finanzas | Panel, resultados, reportes, reglas y cierres mensuales |
| Consulta | Panel, resultados, reportes y ayuda, sin operaciones de escritura |

Los permisos se verifican tanto al mostrar los accesos como al procesar las
operaciones en el servidor. Ocultar una opción de menú no es el único control de
autorización.

## 5. Requisitos no funcionales

### Experiencia de usuario

- Interfaz completamente en español.
- Identidad visual propia y paleta azul, verde y lima.
- Diseño responsive validado en anchos de 320, 390, 768 y 1366 píxeles.
- Formularios adaptables y controles adecuados para interacción táctil.
- Tablas con desplazamiento horizontal dentro de su contenedor.
- Compatibilidad prevista con navegadores modernos de Android, iOS y escritorio.

### Seguridad

- Autenticación mediante usuario y contraseña almacenada como hash seguro con
  `password_hash`.
- Sesiones mediante cookies `HttpOnly`, `SameSite` y `Secure` bajo HTTPS.
- Expiración de sesión por inactividad.
- Protección CSRF en operaciones de escritura.
- Bloqueo temporal después de intentos fallidos de acceso.
- Encabezados CSP, HSTS, protección contra marcos y detección MIME.
- Consultas preparadas mediante PDO.
- Bitácora de accesos, acciones relevantes y exportaciones.
- Protección web de archivos de configuración, SQL, scripts y documentación.

### Integridad y trazabilidad

- Transacciones de base de datos en operaciones compuestas.
- Identificadores únicos para aretes, productos, usuarios y versiones de reglas.
- Relaciones de integridad referencial entre los principales registros.
- Historial del ciclo de vida del animal.
- Instantáneas de reglas en los cierres mensuales.
- Conservación de costos y tarifas históricas.

### Disponibilidad y operación

- Chequeo de salud para conexión, regla activa y usuarios.
- Respaldo y restauración apoyados en la infraestructura del hosting.
- Contenedores Docker con comprobaciones de salud y persistencia de datos.
- Guía de endurecimiento y lista de verificación para producción.

## 6. Especificaciones técnicas

| Componente | Especificación actual |
|---|---|
| Aplicación | PHP 8.0 o posterior; validada con PHP 8.2 |
| Servidor web | Apache con `.htaccess` |
| Base de datos | MySQL 5.7+ o MariaDB 10.3+; Docker usa MariaDB 10.11 |
| Acceso a datos | PDO con consultas preparadas |
| Interfaz | HTML renderizado en servidor, CSS responsive y JavaScript ligero |
| Moneda actual | Colón costarricense (CRC/₡) |
| Idioma actual | Español |
| Exportaciones | XML compatible con Excel, PDF, CSV para cierres |
| Integración disponible | API de recepción de pesajes y carga CSV |
| Despliegue | Hosting PHP/MySQL, SiteGround o Docker Compose |
| Base de datos evolutiva | Esquema completo y nueve migraciones incrementales |

La instalación actual funciona como una aplicación monolítica independiente.
Cada despliegue utiliza una sola base de datos y representa una organización.

## 7. Entidades principales

- Usuarios y bitácora de actividad.
- Grupos ganaderos y ubicaciones.
- Animales, historial, pesajes y eventos.
- Colaboradores, actividades y mano de obra.
- Productos, movimientos de bodega y costos.
- Traslados y partidas de traslado.
- Ventas.
- Versiones y parámetros de reglas.
- Cierres, resultados por grupo, valorizaciones, movimientos y auditoría.

## 8. Validación y calidad actuales

- Pruebas automatizadas de reglas, fechas, proyecciones y cálculos.
- Validación automática de sintaxis PHP.
- Pruebas de navegador en resoluciones móviles, tableta y escritorio.
- Pruebas de permisos, catálogos, exportaciones y ciclo de bodega.
- Escenario demostrativo reproducible para pruebas funcionales.
- Validación de despliegue mediante chequeo de salud.

Antes de una venta o puesta en producción se requiere una prueba de aceptación
con usuarios reales, reglas aprobadas por Finanzas y dispositivos utilizados en
campo.

## 9. Limitaciones conocidas de la versión 1.1.0

Las siguientes capacidades no forman parte del producto actual:

- Operación multiempresa o multiinquilino dentro de una misma instalación.
- Aplicación móvil nativa, modo offline o sincronización diferida.
- Notificaciones por correo, WhatsApp, SMS o notificaciones push.
- Facturación electrónica, cuentas por pagar o integración contable.
- Gestión completa de proveedores, órdenes de compra y recepción documental.
- Agenda sanitaria avanzada, tratamientos, vacunación y períodos de retiro.
- Reproducción avanzada, genealogía, genética, inseminación y partos esperados.
- Geolocalización, potreros, rotación de pasturas o mapas.
- Lectura directa de RFID, básculas Bluetooth o dispositivos de campo.
- Suscripciones, licencias, cobro en línea y autoservicio comercial.
- Autenticación multifactor o inicio de sesión corporativo.
- API pública general y webhooks; la integración actual se limita a pesajes.
- Personalización por cliente de moneda, idioma, logotipo y zona horaria desde la
  interfaz.

Estas limitaciones deben comunicarse durante demostraciones y propuestas
comerciales para evitar expectativas fuera del alcance entregado.

## 10. Evaluación de preparación comercial

| Capacidad | Estado | Acción recomendada |
|---|---|---|
| Demostración funcional | Lista | Mantener un ambiente demo reiniciable |
| Instalación individual | Lista | Estandarizar alta, respaldo y soporte |
| Seguridad base | Lista | Realizar auditoría externa antes de escalar |
| Documentación de usuario | Lista | Complementar con videos cortos |
| Pruebas con clientes reales | Pendiente | Ejecutar pilotos controlados |
| Migración de datos del cliente | Parcial | Crear plantillas y asistente de importación |
| Soporte y niveles de servicio | Pendiente | Definir horarios, canales y SLA |
| Contratos y privacidad | Pendiente | Preparar términos, DPA y política de retención |
| Cobro recurrente | Pendiente | Definir planes y plataforma de facturación |
| Plataforma multiempresa | Pendiente | Diseñar aislamiento antes de ofrecer SaaS |
| Monitoreo centralizado | Pendiente | Incorporar alertas, métricas y registro de errores |

## 11. Propuesta de mejoras comerciales

### Prioridad 1 — Producto vendible y pilotos

Objetivo: convertir la aplicación actual en una solución que pueda instalarse,
capacitarse y soportarse de manera repetible.

- Validar el flujo completo con dos o tres fincas piloto.
- Definir el proceso de alta: diagnóstico, configuración, importación,
  capacitación, aceptación y salida en vivo.
- Crear plantillas Excel para importar animales, pesajes, empleados, productos y
  existencias iniciales.
- Incorporar un asistente de importación con reporte de errores y reversión.
- Agregar alertas configurables de pesajes vencidos, inventario mínimo y tareas
  pendientes.
- Crear recorridos de bienvenida y videos de menos de tres minutos por módulo.
- Definir respaldo, restauración, soporte, mantenimiento y tiempos de respuesta.
- Ejecutar una revisión de seguridad y recuperación ante desastres.

Esta fase permite comercializar el producto como instalación administrada para
un cliente por ambiente, sin esperar una arquitectura SaaS completa.

### Prioridad 2 — Mayor valor operativo

Objetivo: aumentar el uso diario y reducir controles paralelos en hojas de
cálculo.

- Agenda sanitaria: medicamentos, vacunación, tratamientos, dosis, responsable
  y próxima aplicación.
- Gestión de reproducción: servicio, inseminación, diagnóstico, gestación, parto
  y genealogía.
- Proveedores, solicitudes, órdenes de compra y recepción en bodega.
- Potreros, lotes físicos, movimientos entre ubicaciones y capacidad instalada.
- Paneles configurables por rol y alertas enviadas por correo o WhatsApp.
- Adjuntos para facturas, certificados, fotografías y evidencias.
- PWA instalable con captura offline para zonas de conectividad limitada.
- Firma o aprobación digital para cierres, ajustes y bajas sensibles.

### Prioridad 3 — Escalamiento como servicio

Objetivo: atender múltiples clientes con operación, seguridad y cobro
centralizados.

- Arquitectura multiinquilino con aislamiento estricto de información.
- Organizaciones, fincas, sedes y permisos por unidad operativa.
- Planes, límites de uso, licencias, período de prueba y facturación recurrente.
- Alta y cancelación de clientes mediante autoservicio.
- Autenticación multifactor, recuperación de cuenta y sesiones administrables.
- Monitoreo central, trazas de errores, métricas, alertas y estado del servicio.
- Respaldos por cliente con pruebas automáticas de restauración.
- API versionada, webhooks y portal para integradores.
- Configuración regional de moneda, idioma, zona horaria e identidad visual.

El aislamiento multiinquilino debe diseñarse y probarse antes de alojar varias
empresas en una misma base de datos.

### Prioridad 4 — Diferenciación y crecimiento

Objetivo: convertir los datos acumulados en ventajas productivas difíciles de
replicar con herramientas genéricas.

- Integración con RFID, lectores y básculas electrónicas.
- Pronósticos de peso, fecha óptima de venta y consumo de alimento.
- Indicadores de ganancia diaria, conversión, mortalidad y costo por kilogramo.
- Comparativos anónimos por tipo de operación, región o raza, con consentimiento.
- Detección de valores atípicos y alertas tempranas de desempeño o salud.
- Aplicación móvil especializada para recorridos y captura masiva.
- Portal limitado para veterinarios, proveedores o socios comerciales.
- Integraciones contables y de facturación según el país objetivo.

## 12. Modelo comercial sugerido

Para la etapa inicial se recomienda combinar una tarifa de implementación con
una suscripción de soporte y operación:

- **Implementación:** configuración, carga inicial, ajuste de reglas,
  capacitación y acompañamiento de salida en vivo.
- **Suscripción:** alojamiento, respaldos, actualizaciones, monitoreo y soporte.
- **Servicios adicionales:** migración compleja, reportes personalizados,
  integraciones, capacitación adicional y soporte prioritario.

Posible estructura de planes, sujeta a validación con clientes:

| Plan | Enfoque | Capacidades propuestas |
|---|---|---|
| Esencial | Finca pequeña | Animales, pesajes, eventos, alertas y reportes básicos |
| Operación | Finca en crecimiento | Esencial más bodega, costos, personal y traslados |
| Gestión | Operación profesional | Operación más reglas, resultados, cierres e integraciones |

En la versión actual los módulos ya están integrados y no existe un motor de
licenciamiento para separar planes. La tabla representa una opción comercial
futura, no una restricción implementada.

La métrica de precio puede combinar una base por organización con rangos de
animales activos o ubicaciones. El precio final debe definirse después de medir
el costo de soporte y el valor obtenido durante los pilotos.

## 13. Estrategia de salida al mercado

1. Seleccionar un segmento inicial concreto, por ejemplo engorde y cría en
   operaciones pequeñas y medianas de Costa Rica.
2. Ejecutar pilotos con objetivos medibles: menos hojas de cálculo, menor tiempo
   de cierre y mayor cobertura de pesaje.
3. Documentar un caso de éxito con resultados antes y después.
4. Crear una demostración guiada basada en tareas, no solamente en módulos.
5. Ofrecer una puesta en marcha acompañada para reducir el riesgo de adopción.
6. Desarrollar alianzas con veterinarios, consultores, contadores agropecuarios,
   proveedores de básculas y asociaciones ganaderas.
7. Utilizar la retroalimentación de los pilotos para definir precios y ordenar el
   roadmap antes de invertir en una plataforma SaaS completa.

## 14. Indicadores recomendados

### Adopción del producto

- Tiempo desde la creación de la cuenta hasta el primer animal registrado.
- Usuarios activos por semana y por perfil.
- Porcentaje de animales con pesaje vigente.
- Uso mensual de bodega, costos, reportes y cierres.
- Tiempo necesario para completar el cierre mensual.
- Número de operaciones rechazadas por datos incompletos o inconsistentes.

### Resultado para el cliente

- Diferencia de inventario antes y después de implementar el sistema.
- Reducción del tiempo invertido en consolidar información.
- Cobertura y frecuencia de pesajes.
- Costo por kilogramo producido y variación contra presupuesto.
- Pérdidas por mortalidad, vencimientos o faltantes de bodega.

### Desempeño comercial

- Conversión de demostración a piloto y de piloto a cliente pagado.
- Ingreso mensual recurrente y costo de implementación.
- Costo de soporte por cliente.
- Renovación, cancelación y motivo de cancelación.
- Satisfacción del usuario y tiempo de resolución de solicitudes.

## 15. Próxima decisión recomendada

La prioridad inmediata no debería ser ampliar todos los módulos a la vez. Se
recomienda ejecutar un piloto con datos reales y medir tres resultados:

1. Confiabilidad del inventario y de la trazabilidad del animal.
2. Tiempo y calidad del cierre financiero mensual.
3. Facilidad de captura desde teléfonos en condiciones reales de campo.

Con esa evidencia se puede decidir si el siguiente incremento debe enfocarse en
operación offline, sanidad y reproducción, compras y proveedores, o la
conversión a una plataforma multiempresa.
