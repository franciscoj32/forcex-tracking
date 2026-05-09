# Arquitectura de Datos de Tracking

## Objetivo

La base de datos de `FORCEX Tracking` fue organizada para soportar una operacion de seguimiento logistico con cuatro prioridades:

1. Trazabilidad operativa del embarque.
2. Integridad entre clientes, usuarios, embarques y eventos.
3. Prevencion de duplicidad en identificadores criticos.
4. Escalabilidad razonable para consultas de seguimiento y gestion.

## Entidades principales

### `clients`

Representa al cliente corporativo dueno de la carga.

Responsabilidades:
- almacenar razon social y RUC
- servir como entidad padre de usuarios cliente
- servir como entidad padre de embarques

Reglas:
- `ruc` es unico
- un cliente puede tener muchos embarques
- un cliente puede tener muchos usuarios cliente

### `users`

Representa a los usuarios internos y a los usuarios cliente.

Roles soportados:
- `admin`
- `operaciones`
- `comercial`
- `cliente`

Reglas:
- `email` es unico
- `username` es unico
- los usuarios con rol `cliente` deben pertenecer a un `client_id`
- los usuarios internos no deben quedar amarrados a un cliente
- `estado` controla acceso operativo (`activo` o `inactivo`)

### `shipments`

Es la entidad central del sistema. Cada embarque concentra la operacion, documentos, tracking y datos comerciales.

Campos clave:
- `client_id`
- `nro_orden_trabajo`
- `tracking_code`
- `routing_order`
- `bl`
- `tipo_carga`
- `estado_actual`
- `estado_endoso`

Reglas de integridad:
- `nro_orden_trabajo` es unico globalmente
- `tracking_code` es unico globalmente
- `bl` es unico por cliente
- `routing_order` es unico por cliente
- `peso` y `volumen` no pueden ser negativos

### `shipment_events`

Registra la linea de tiempo operacional del embarque.

Responsabilidades:
- guardar el historial de estados
- registrar fecha, descripcion y responsable de cada movimiento
- preservar evidencia operativa para auditoria

Reglas:
- cada evento pertenece a un embarque
- `user_id` puede quedar en `null` si el usuario fue eliminado
- existe una restriccion unica por `shipment_id + estado + fecha` para evitar duplicados exactos

### `documents`

Repositorio documental privado del embarque.

Categorias:
- `operativo`
- `endoso`
- `aduanas`
- `facturacion`

Reglas:
- cada documento pertenece a un embarque
- `user_id` puede quedar en `null` si el usuario fue eliminado
- los archivos viven fuera del acceso publico y se descargan via controlador

### `invoices`

Representa facturas asociadas a un embarque.

Reglas:
- `numero` es unico
- `monto` no puede ser negativo
- cada factura pertenece a un embarque

### `provisions`

Representa costos o provisiones operativas ligadas a un embarque.

Reglas:
- cada provision pertenece a un embarque
- `monto` no puede ser negativo

### `user_shipment_preferences`

Tabla de preferencias por usuario para fijar embarques destacados.

Reglas:
- un usuario no puede tener mas de una preferencia para el mismo embarque
- restriccion unica: `user_id + shipment_id`

## Relaciones clave

- `clients -> users`: 1 a muchos
- `clients -> shipments`: 1 a muchos
- `shipments -> shipment_events`: 1 a muchos
- `shipments -> documents`: 1 a muchos
- `shipments -> invoices`: 1 a muchos
- `shipments -> provisions`: 1 a muchos
- `users -> shipment_events`: 1 a muchos, conservando historial aunque el usuario sea eliminado
- `users -> documents`: 1 a muchos, conservando historial aunque el usuario sea eliminado

## Politica de borrado

La estrategia de borrado busca evitar perdida de trazabilidad.

- Si se elimina un `shipment`, sus eventos, documentos, facturas y provisiones si se eliminan porque pertenecen totalmente a ese embarque.
- Si se elimina un usuario interno, los eventos y documentos historicos no se borran.
  En esos casos `user_id` pasa a `null` y la interfaz muestra `Usuario eliminado`.
- Si se elimina un cliente, sus embarques tambien se eliminan por integridad referencial.

## Reglas de negocio endurecidas

Las siguientes reglas fueron implementadas para reducir conflictos en produccion:

- no se permite duplicar `BL` dentro del mismo cliente
- no se permite duplicar `Routing Order` dentro del mismo cliente
- no se permiten montos negativos en facturas y provisiones
- no se permiten pesos o volumenes negativos
- no se permiten eventos exactos duplicados con mismo embarque, estado y fecha

## Indices pensados para operacion

Se agregaron indices para las consultas mas frecuentes del sistema:

- busqueda y listado de embarques por cliente
- filtros por `estado_actual`
- ordenamiento por `updated_at`
- consultas de eventos por embarque y fecha
- consultas de documentos por embarque y categoria
- consultas de provisiones e invoices por embarque
- consultas de usuarios por rol y estado

Esto mejora el comportamiento cuando crezca el volumen de embarques y movimientos.

## Coherencia del modelo

El modelo actual responde a una logica de negocio consistente:

- el cliente es la entidad comercial duena del embarque
- el embarque es la entidad central de operacion
- los eventos son la bitacora historica del embarque
- los documentos son evidencia privada asociada al embarque
- la facturacion y provisiones se relacionan al embarque, no al usuario
- los usuarios son actores del proceso, pero no duenos del dato logistico

Esa separacion evita dependencia excesiva de usuarios individuales y ayuda a mantener continuidad operativa.

## Riesgos controlados y siguientes pasos

El esquema ya esta en una base solida para evaluacion y despliegue de prueba. Si el sistema creciera a un entorno de mayor escala, los siguientes pasos recomendados serian:

1. incorporar catalogos maestros para paises, puertos y navieras en lugar de texto libre
2. agregar `soft deletes` para embarques si el negocio requiere archivo historico recuperable
3. separar facturacion en un modulo mas contable si el flujo financiero se vuelve complejo
4. incorporar auditoria adicional de cambios sensibles
5. considerar busqueda optimizada si el volumen de embarques sube de forma fuerte

## Estado actual

Al cierre de esta revision:

- la integridad referencial principal esta cubierta
- no se detectaron duplicados criticos en los datos actuales
- la base paso validaciones automatizadas de aplicacion
- la estructura queda apta para un dominio de prueba y evaluacion funcional
