# Producto Tracking FORCEX

Documento funcional para alinear lo que el sistema ya resuelve hoy y hacia donde debe evolucionar como plataforma de seguimiento de embarques para FORCEX LOGISTICS.

## Objetivo del sistema

El sistema debe centralizar el seguimiento operativo y comercial de embarques internacionales en una sola plataforma privada.

Debe permitir:

- registrar embarques y su informacion base;
- mostrar el estado actual de cada carga;
- llevar una linea de tiempo de eventos;
- controlar documentos sensibles por permisos;
- dar visibilidad al cliente sin exponer informacion de otros clientes;
- ayudar a operaciones y comercial a priorizar embarques importantes.

## Tipo de plataforma

No es una web publica abierta.

Es una plataforma privada con acceso autenticado para:

- administracion;
- operaciones;
- comercial;
- clientes.

## Usuarios y necesidades

### Admin

Necesita:

- acceso total al sistema;
- administrar usuarios y roles;
- ver todos los embarques;
- editar o eliminar registros cuando sea necesario;
- validar configuracion general y seguridad.

### Operaciones

Necesita:

- crear y actualizar embarques;
- registrar cambios de estado;
- cargar documentos;
- mantener informacion precisa del tracking;
- consultar rapidamente cargas prioritarias.

### Comercial

Necesita:

- revisar embarques de clientes;
- hacer seguimiento de cargas criticas;
- tener visibilidad rapida del avance;
- usar el sistema como apoyo de comunicacion con cliente.

### Cliente

Necesita:

- entrar a una vista clara y segura de sus propios embarques;
- ver el estado actual de cada carga;
- revisar tracking y documentos autorizados;
- no ver informacion de otros clientes;
- entender rapidamente si una carga esta en curso, retrasada o finalizada.

## Lo que el sistema ya tiene

Actualmente el producto ya cubre estas bases:

- login privado sin registro publico;
- dashboard interno;
- gestion de clientes;
- gestion de usuarios y roles;
- modulo de embarques;
- estados de tracking por embarque;
- eventos historicos por embarque;
- documentos privados con descarga protegida;
- control de acceso por cliente;
- busqueda de embarques;
- preferencia personal para fijar embarques por usuario.

## Modelo actual de embarque

Cada embarque ya maneja, como minimo:

- cliente;
- OT;
- Routing Order;
- BL;
- tipo de carga;
- origen;
- puerto de carga;
- puerto de descarga;
- destino final;
- naviera;
- consignatario;
- peso y volumen;
- fechas operativas;
- estado actual;
- estado de endoso;
- observaciones.

## Flujo funcional esperado

### 1. Creacion

Operaciones o admin crean el embarque con sus datos base.

### 2. Seguimiento

Durante la vida del embarque se actualiza:

- el estado actual;
- la linea de tiempo de eventos;
- fechas relevantes;
- documentos asociados.

### 3. Consulta interna

Admin, operaciones y comercial consultan listados, filtran y priorizan embarques para trabajo diario.

### 4. Consulta cliente

El cliente entra y solo visualiza embarques de su empresa, con documentos y tracking autorizados.

### 5. Cierre

Cuando la carga termina su ciclo, el embarque queda finalizado con historial trazable.

## Estados del tracking

El sistema hoy ya usa una secuencia operativa similar a esta:

- Creacion Routing Order
- Booking Confirmado
- ATD
- En transito
- Transmision Aduanas
- ETA
- Arribo a puerto
- Descarga
- Ingreso a almacen
- Retiro de almacen
- Entregado
- Finalizado

Esto debe entenderse como la columna vertebral del seguimiento visible para usuario interno y cliente.

## Reglas clave de negocio

- Un cliente solo puede ver sus propios embarques.
- Un cliente solo puede descargar documentos de sus propios embarques.
- Los documentos sensibles no deben exponerse por URL publica.
- El tracking debe reflejar el ultimo estado real del embarque.
- El historial de eventos debe permitir trazabilidad.
- Las preferencias personales, como fijar embarques, deben pertenecer al usuario y no compartirse con otros.

## Linea de producto que queremos

La meta no es solo almacenar embarques.

Queremos una experiencia de tracking donde:

- el usuario vea rapido lo importante;
- el dashboard actue como centro de control;
- los embarques criticos aparezcan primero;
- los estados sean faciles de interpretar;
- la informacion sirva tanto para operacion como para comunicacion con cliente.

## Prioridades funcionales recomendadas

### Corto plazo

- llevar embarques fijados tambien al dashboard;
- destacar mejor cargas urgentes o proximas a ETA;
- mejorar visibilidad de filtros y resumenes;
- asegurar que cliente vea una experiencia simple y entendible.

### Mediano plazo

- agregar indicadores por estado;
- mostrar alertas de retraso o pendientes documentarios;
- incluir bloques de resumen por usuario en dashboard;
- diferenciar vistas internas y vistas cliente con mayor claridad.

### Largo plazo

- tracking publico controlado por codigo seguro si el negocio lo necesita;
- notificaciones por hitos del embarque;
- reportes ejecutivos de tiempos y cumplimiento;
- auditoria mas completa de cambios operativos.

## Dashboard ideal

El dashboard deberia convertirse en una entrada de trabajo, no solo en una pantalla de bienvenida.

Deberia mostrar:

- embarques fijados por el usuario;
- embarques por vencer ETA;
- embarques con pendientes de endoso;
- embarques recientes o con cambios recientes;
- resumen por estado;
- accesos rapidos a tracking, documentos y ficha del embarque.

## Diferencia entre sistema actual y vision objetivo

Hoy:

- ya existe la base operativa privada;
- ya existe el modulo principal de embarques;
- ya existe control de acceso por rol y cliente;
- ya existen documentos privados y tracking.

Todavia falta consolidar:

- una experiencia de dashboard centrada en prioridades;
- documentacion funcional mas completa;
- reglas visuales mas fuertes para seguimiento diario;
- mayor foco en experiencia de cliente final.

## Decision de producto importante

Este sistema debe tratarse como una herramienta operativa con capa comercial y capa cliente.

Eso significa que cada mejora nueva deberia responder al menos una de estas preguntas:

- ayuda a operar mejor;
- ayuda a comunicar mejor el estado al cliente;
- ayuda a priorizar cargas importantes;
- reduce riesgo de error o fuga de informacion.

## Siguiente paso sugerido

El siguiente paso mas coherente con la direccion actual es llevar el sistema de embarques fijados al dashboard para que cada usuario vea arriba sus cargas clave apenas entra.
