# FORCEX Tracking: qué hace el sistema

Resumen funcional actualizado al 21/10/2026. Reemplaza a `producto-tracking.md`, que describe una versión anterior.

## En una frase

Es la plataforma interna de FORCEX LOGISTICS para llevar cada carga internacional desde que el Comercial registra la Routing Order (RO) hasta que se entrega y se factura. Avisa sola de los plazos en riesgo y mantiene informado al cliente por correo y con un código de seguimiento.

Está pensada como piloto de un producto que después se venderá a varias empresas. Por eso la marca (nombre, logo y colores) se configura en pantalla y no está escrita en el código.

---

## 1. Quién lo usa

| Rol | Qué hace en el sistema |
|---|---|
| **Administrador / Gerente** | Ve todo. Aprueba clientes, puede forzar el crédito de una RO, ajusta el tipo de cambio, gestiona usuarios, conceptos de tarifa y configuración. Ve el panel del mes, el ranking de comerciales y las alertas escaladas. |
| **Comercial** | Registra clientes (quedan pendientes de aprobación) y crea las RO con sus tarifas de venta. Ve sus ventas, su margen y su puesto en el ranking. Solo puede corregir sus RO mientras están en "Creación Routing Order". |
| **Customer Service** | Es el único punto de contacto con el cliente. Asigna el operador, valida los cambios de ETD/ETA antes de que el cliente los vea, valida documentos (aprobar u observar) y escribe notas al cliente. |
| **Operaciones** | Es dueño de los datos técnicos: BL, naviera, fechas, cut-off, contenedores, tarja, almacén, días libres y ubicación. Registra hitos, sube documentos, registra provisiones (costos de proveedores) y facturas. |
| **Cliente** | No entra al panel interno. Consulta su carga en el **tracking público** con su código (FX-XXXXXXXX) y recibe correos automáticos. |

La matriz de permisos completa se ve en **Roles** (solo Admin).

---

## 2. Recorrido de una RO

1. **Cliente nuevo.** El Comercial lo registra con su RUC, contactos y condición de crédito. El Admin lo aprueba o lo rechaza con un motivo. Hasta que esté aprobado no se le puede emitir una RO.
2. **Nueva RO.** El Comercial carga los datos comerciales (cliente, OT, RO, tipo FCL/LCL/Aéreo, Incoterm, puertos, Customer asignado) y las **tarifas de venta**. La RO toma el tipo de cambio del día y lo deja fijo.
   - El sistema genera un **código de seguimiento** y envía el **correo A** al Customer y al cliente.
3. **Coordinación.** El Customer asigna el operador.
4. **Operación.** Operaciones completa los datos técnicos, registra contenedores (con validación del número ISO 6346) y va registrando los **hitos** en orden. Un hito no se puede saltar hacia atrás ni repetir.
   - Creación RO → Booking confirmado → ATD → En tránsito → Transmisión a Aduanas → ETA → Arribo a puerto → Descarga → Ingreso a almacén → Retiro de almacén → Entregado → Finalizado.
5. **Cambios de fecha.** Si Operaciones cambia la ETD o la ETA, el cliente no lo ve de inmediato.
   - El Customer recibe el **correo B1** y lo valida o lo descarta.
   - Si lo valida, el cliente recibe el **correo B2** con la nueva fecha y la nota del Customer.
   - Si el cambio se hizo en la **actualización masiva**, el Customer recibe **un solo B1 resumido** con todas sus RO. En el Inicio ve un solo pendiente ("Cambio de ETA en 5 RO") y puede publicarlas todas juntas con una sola nota. Cada cliente recibe igual su B2 por separado.
6. **Documentos.** Se suben a la pestaña Documentos con una lista de los obligatorios.
   - La Commercial Invoice, el Packing List y el Borrador de BL quedan "Por validar" hasta que el Customer los aprueba u observa.
   - Si un documento queda observado, se sube una versión corregida.
7. **Llegada.** Al registrar la tarja o el ingreso al almacén, el cliente recibe el **correo C**.
8. **Finanzas.** Se registran las provisiones (costos por proveedor) y las facturas al cliente. El sistema calcula la venta, el costo y el margen en USD.
9. **Cierre.** La RO pasa a Finalizado y conserva todo su historial.

Cada cambio de la ficha queda en el **historial interno** del embarque: quién cambió qué y cuándo. El cliente solo ve los hitos y las notas del Customer.

---

## 3. Pantallas (menú)

| Pantalla | Qué hay |
|---|---|
| **Inicio** | **Alertas** (plazos detectados por el sistema) y **Requiere tu acción** (tareas de cada rol). Admin y Comercial ven además los números del mes. El Admin ve también el ranking de comerciales y el margen de los últimos 6 meses. |
| **Embarques** | Lista con vistas rápidas: Activos, Retrasados, Arriban en 7 días, Fecha por validar, Finalizados y Todos. Búsqueda por OT, RO, BL, nave o código; filtros por cliente, tipo y "Solo míos"; RO destacadas (fijadas); exportación a Excel. |
| **Actualización masiva** | Desde Embarques. Agrupa las RO activas por **nave y viaje**. Permite cambiar la ETD o la ETA (Operaciones y Admin) o registrar un hito (también el Customer) a varias RO a la vez, hasta 50. Cada RO se revisa con sus reglas; las que no cumplen se omiten y se muestra el motivo. El cut-off no se cambia en masa. |
| **Ficha del embarque** | Encabezado con el código de seguimiento resaltado y el bloque "Requiere atención". Pestañas: **Resumen**, **Seguimiento**, **Documentos** y **Finanzas**, cada una con su icono. PDF del estado para enviar al cliente. |
| **Clientes** | Vistas Todos, Por aprobar, Aprobados y Rechazados. Ficha con contactos, crédito y embarques. |
| **Provisiones** | Costos de proveedores: Por pagar, Pagados y Todos. Se crean desde una ventana emergente. Por defecto se agrupan **por proveedor** (para pagar en bloque); también por RO o sin agrupar. Cada grupo muestra cuántas provisiones tiene, cuántas están por pagar y su total por moneda. |
| **Facturación** | Facturas al cliente: Por cobrar, Pagadas y Todas. Se crean desde una ventana emergente. Por defecto se agrupan **por cliente** (para cobrar); también por RO o sin agrupar. |
| **Proveedores** y **Conceptos** | Catálogos de proveedores y de conceptos de tarifa (flete, THC, etc.). |
| **Reportes** | Indicadores con filtros (por defecto, los últimos 6 meses). Excel de operaciones, Excel financiero (el Admin ve todo, el Comercial solo lo suyo) y PDF gerencial. |
| **Usuarios**, **Roles** y **Configuración** | Solo Admin. En Configuración se definen la marca (empresa, programa, sigla, logo, colores), el tipo de cambio de referencia y los umbrales de las alertas. |
| **Perfil** | Datos propios y contraseña. |
| **Tracking público** | `/tracking-publico`, sin contraseña, con el código de la RO. Muestra el estado en lenguaje simple, las fechas publicadas y las notas del Customer. |

La interfaz está en español, tiene menú lateral claro, funciona en celular (con menú desplegable), tiene buscador global y usa la fuente IBM Plex Sans incluida en el proyecto.

---

## 4. Alertas críticas (Inicio)

Las alertas son plazos que el sistema detecta solo. Las tareas son lo que depende de una persona. Por eso van en bloques separados.

| Alerta | Cuándo aparece | Se cierra sola cuando |
|---|---|---|
| Cut-off | Antes del ATD | Se marca "cut-off cumplido" o se registra el ATD |
| ETA vencida | Pasó la ETA y no hay arribo | Se registra el arribo |
| ETA retrasada | La ETA es 2 o más días posterior a la ETA original | La carga arriba o la ETA vuelve al margen |
| Demurrage (FCL) | Desde la descarga; el día de descarga es el día 1 | Todos los contenedores fueron retirados |
| Detention (FCL) | Desde el retiro de cada contenedor | Todos los contenedores fueron devueltos vacíos |
| Almacenaje | Desde el ingreso al almacén; ese día es el día 1 | Se registra el retiro del almacén |
| Pre-alerta de llegada | 72 h antes de la ETA (amarilla); roja a 24 h o con la ETA pasada | El Customer la envía con la ETA vigente, o se registra el arribo |

- **Semáforo:**
  - Verde: de 5 días a 48 h.
  - Amarilla: de 48 h a 24 h.
  - Roja: 24 h o menos, o ya vencida.
  - Con más de 5 días no sale en el Inicio, pero la ficha muestra la cuenta regresiva.
- **Acciones:** *Vista* y *Posponer* (4 h o hasta mañana a las 8:00, nunca más allá del vencimiento). La alerta vuelve a aparecer si sube de nivel o si cambia el plazo.
- **Quién la ve:**
  - El Customer y el Operador de la RO.
  - El Admin solo las rojas que llevan 4 h o más sin atender.
  - El Comercial, solo para consultar, la ETA vencida o retrasada de sus RO.
- **Umbrales:** se editan en Configuración.
- **Rendimiento:** las alertas se calculan al abrir el Inicio con pocas consultas. Solo se guardan las acciones (vista o pospuesta). El Inicio muestra las 15 más urgentes y un enlace para ver todas.

### Pre-alerta de llegada (despacho anticipado)

- **Para qué:** avisar al cliente, a la agencia de aduana y al transportista que la carga llega, para coordinar el despacho anticipado y el retiro.
- **Cuándo:** la alerta aparece 72 h antes de la ETA real de Operaciones. Las horas se cambian en Configuración. La ven el Customer y el Operador de la RO. El Admin la ve si queda roja sin atender.
- **Quién la envía:** solo el Customer de la RO o el Admin, con **"Preparar pre-alerta"** (en la alerta del Inicio o en la pestaña Seguimiento). El Operador la ve, sin el botón.
- **La pantalla de revisión muestra:**
  - el **estado de cobro**: condición de crédito y facturas por cobrar de la RO y del cliente, en USD y PEN;
  - los **destinatarios**: el cliente, más la agencia de aduana y el transportista de las provisiones de la RO (marcados por defecto); los demás proveedores de la RO, opcionales; hasta 3 correos más;
  - los **documentos** a adjuntar: BL, Borrador de BL, Commercial Invoice y Packing List, hasta 10 MB;
  - una **nota** para todos.
- **Enviar:** sale un correo al cliente (sin adjuntos) y uno aparte a cada proveedor o correo extra (con los datos operativos y los adjuntos). Nadie ve los correos de los demás. Las respuestas le llegan al Customer. Queda en el historial y en "Correos enviados".
- **Retener:** pide un motivo, por ejemplo "facturas pendientes de pago". No envía nada. La alerta sigue en el Inicio con el motivo hasta que se envíe.
- **Bloqueos:**
  - si hay un **cambio de ETA por validar**, no deja enviar hasta validarlo, para que todos reciban la misma fecha;
  - si la carga ya arribó o no tiene ETA, tampoco.
- **Si la ETA cambia después de enviarla**, la alerta vuelve a aparecer para reenviar con la nueva fecha.
- **Al registrar "Arribo a puerto"** no sale ningún correo nuevo: solo cambia el tracking, como antes.
- Reemplaza la tarea anterior "Arriba en N días, coordina el retiro".

---

## 5. Correos automáticos

| Evento | Para quién |
|---|---|
| A · RO registrada | Customer asignado y contactos del cliente (con el código de seguimiento) |
| B1 · Cambio de ETD/ETA por validar | Customer asignado |
| B2 · Fecha validada / reprogramación | Contactos del cliente |
| C · Tarja o ingreso al almacén | Contactos del cliente y Customer |
| Pre-alerta de llegada (la envía el Customer) | Contactos del cliente; agencia de aduana, transportista y otros correos que elija el Customer (uno por destinatario) |
| Nota del Customer | Contactos del cliente |
| Cliente por aprobar / cliente revisado | Admin / Comercial que lo registró |
| Resumen diario de alertas (7:00) | Customer, Operador y seguidores de la RO; Admin si hay escaladas |

- **Asunto estándar:** `[Acción] RO | Evento | Cliente | Tipo POL → POD | BL`.
- **Hilo:** los correos de una misma RO quedan en un solo hilo en el programa de correo.
- **Respuestas:** si el cliente responde, el correo le llega a su Customer.
- **Remitente:** el nombre de la empresa.
- **Registro:** cada envío queda en `notification_logs` (en cola, enviado, fallido) y se procesa en la cola de correos.
- **Interruptor general:** `FORCEX_NOTIFICATIONS`.

### WhatsApp al cliente (sin costo)

- En la ficha, pestaña **Seguimiento**, tarjeta **"WhatsApp al cliente"** (o el botón "WhatsApp" del encabezado). Solo para el Customer y el Admin.
- Se elige un contacto del cliente (o "Otro número…") y un mensaje sugerido: Estado de la carga, Fecha estimada de llegada, Carga en almacén o Mensaje libre. El texto se puede editar.
- **"Abrir WhatsApp"** abre WhatsApp (web o app) con el mensaje escrito, usando un enlace `wa.me`. El Customer revisa y presiona enviar desde su propio WhatsApp. No usa la API pagada de Meta.
- Números: un celular peruano de 9 dígitos se completa con 51; los fijos se rechazan; los internacionales se escriben con `+`. El código por defecto se cambia con `FORCEX_WHATSAPP_COUNTRY_CODE`.
- Cada mensaje abierto queda en el historial interno de la RO (quién, a qué número y los primeros 120 caracteres del texto). El sistema no puede confirmar si el mensaje se envió.

---

## 6. Finanzas

- **Tarifas de venta por RO:** concepto, monto y moneda (USD o PEN).
- **Tipo de cambio:** de referencia en Configuración. Cada RO lo copia al crearse y lo deja fijo; solo el Admin puede ajustarlo.
- **Provisiones:** costos por proveedor, con estado por pagar o pagado.
- **Facturas al cliente:** con estado y archivo PDF.
- **Venta, costo y margen en USD por RO:** el margen solo lo ven el Admin y el Comercial dueño de la RO.
- **Crédito:** se hereda del cliente (contado o crédito a N días). El Admin puede forzarlo para una RO.

---

## 7. Seguridad

- No hay registro público. Los usuarios los crea el Admin, y los usuarios inactivos no pueden entrar.
- Cada rol solo edita sus campos. Los demás se muestran como "solo lectura" (`ShipmentFieldAccess`).
- Los documentos se guardan en `storage/app/private` y se descargan a través del sistema, nunca por un enlace público.
- Eliminar registros financieros y embarques es solo del Admin.
- Los mensajes de validación están en español.

---

## 8. Tareas programadas (cron)

En el hosting basta **una** tarea cada minuto:

```
* * * * * cd /ruta/de/la/app && php artisan schedule:run >> /dev/null 2>&1
```

Con eso el sistema:
- procesa la cola de correos cada minuto;
- envía el resumen diario de alertas a las 7:00 (`FORCEX_DAILY_ALERTS_AT`);
- limpia las acciones de alertas de más de 90 días a las 3:00.

---

## 9. Datos de prueba

Los carga `php artisan db:seed` (o `migrate:fresh --seed` en una base vacía).

| Usuario | Rol | Contraseña |
|---|---|---|
| admin | Administrador | cambiar123 |
| juan_comercial, ana_comercial | Comercial | Demo123456 |
| maria_customer, diego_customer | Customer Service | Demo123456 |
| carla_operaciones, luis_operaciones | Operaciones | Demo123456 |

Incluyen 15 RO (FX-OT-250001 a 250015), documentos por validar y observados, cambios de fecha pendientes, provisiones, facturas y una alerta de cada tipo y nivel.

---

## 10. Dónde está cada cosa en el código

| Tema | Archivos |
|---|---|
| Pantallas | `resources/views/` (`dashboard`, `shipments/`, `clients/`, `provisions/`, `invoices/`, `reports/`, `settings/`, `alerts/`) |
| Reglas de cada pantalla | `app/Http/Controllers/` |
| Permisos | `app/Policies/`, `app/Providers/AppServiceProvider.php`, `app/Support/ShipmentFieldAccess.php` |
| Alertas críticas | `app/Services/Alerts/` (`AlertDetector`, `AlertBoard`, `AlertLevel`, `FreeTime`) |
| Pre-alerta de llegada | `app/Services/PreAlertService.php`, `app/Http/Controllers/ShipmentPreAlertController.php`, `app/Models/ShipmentPreAlert.php`, `resources/views/shipments/pre-alert.blade.php` |
| Tareas del Inicio | `app/Services/ActionInbox.php`, `app/Support/ShipmentAttention.php` |
| Correos | `app/Services/ShipmentNotifier.php`, `app/Support/MailSubject.php`, `app/Jobs/SendTrackingMail.php`, `resources/views/vendor/mail/` |
| WhatsApp al cliente | `app/Support/WhatsApp.php`, `app/Http/Controllers/ShipmentWhatsAppController.php`, `resources/views/shipments/tabs/tracking.blade.php` |
| Cambios de ETD/ETA | `app/Services/ShipmentDateChangeService.php`, `app/Observers/ShipmentObserver.php` |
| Indicadores y reportes | `app/Services/ManagementMetrics.php`, `app/Support/ReportFilters.php`, `app/Support/Exports/` |
| Marca y colores | `app/Models/AppSetting.php`, `app/Support/BrandPalette.php`, `app/Support/ChartPalette.php` |
| Tareas programadas | `routes/console.php`, `app/Console/Commands/SendDailyAlerts.php` |
| Base de datos | `database/migrations/`; datos de prueba en `database/seeders/DemoShipmentSeeder.php` |
| Textos en español | `lang/es/`, `lang/es.json` |

---

## 11. Pendiente o conocido

- **Subida al hosting:** por Git con `deploy.sh`. Ver [git-deploy-guide.md](git-deploy-guide.md) (primera vez: respaldo, `.env`, cron).
- **Para el producto multiempresa:**
  - El prefijo del código de seguimiento (`FX-`) sigue fijo en el código.
  - Hoy funciona una instalación por empresa.
- **Perfil:** el usuario todavía puede borrar su propia cuenta. Se recomendó quitarlo.
- **Documentos viejos:** `producto-tracking.md` y `arquitectura-datos-tracking.md` describen una versión anterior y no incluyen roles, finanzas, correos ni alertas.
