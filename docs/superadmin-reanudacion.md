# Estado del rediseño de Superadministrador

## Implementación completada el 17 de noviembre de 2026

Las especificaciones textuales posteriores del usuario sustituyen la necesidad de recuperar imágenes. Se completaron las seis vistas sobre los controladores y rutas recuperados, sin ejecutar migraciones, alterar datos históricos, commit ni push. Los apartados inferiores conservan el diagnóstico inicial de la interrupción.

- **Panel:** banner Oxford/turquesa con ilustración corporativa nativa, cuatro KPI, solicitudes mensuales, clientes acumulados, distribución activo/inactivo, solicitudes recientes, accesos rápidos y respuestas comerciales fechadas.
- **Solicitudes:** cuatro indicadores, filtros, paginación, tabla y detalle 55/45, revisión, rechazo y panel de conversión con datos precargados de empresa y contacto. La sede se captura porque no existe en la solicitud original.
- **Clientes:** indicadores, filtros por empresa/responsable/estado/tipo, directorio, identidad comercial, sedes y usuarios vinculados, alta y edición mediante los endpoints existentes.
- **Sedes:** selector y banner de empresa, indicadores, tabla y formulario lateral con logotipo, capacidad y gerente. La asignación es aditiva y conserva los vínculos anteriores.
- **Usuarios:** filtros y paginación, cuentas únicas y etiquetas de los nueve roles, perfiles y enlaces de activación para accesos generales. Las cuentas exclusivamente operativas siguen siendo gestionadas por su gerente.
- **Configuración:** cinco bloques de acceso comercial, activación, notificaciones, seguridad e identidad institucional. Solo consulta políticas existentes y enlaza herramientas reales; no existe endpoint para guardar políticas y no se agregaron interruptores ficticios.

### Archivos de esta implementación

- `resources/views/superadmin/workspace.blade.php`, `navigation.blade.php`, las seis vistas en `sections/` y sus formularios/auxiliares en `partials/`.
- Componentes `resources/views/components/superadmin/{kpi,empty,drawer}.blade.php`.
- `public/css/superadmin-workspace.css` y `public/js/superadmin-workspace.js`.
- `app/Http/Controllers/Admin/SuperadminWorkspaceController.php`: datos de gráficas, paginación y filtros complementarios; conserva los endpoints y validaciones de escritura.
- `app/Support/SuperadminWorkspace.php`: responsables y vinculación de sedes, identificación de cuentas generales y corrección de los colores de roles que llamaban a un método privado.
- `resources/views/layouts/app.blade.php`: campana conectada al feed del Superadministrador y avisos propios de este rol sin duplicación. Las ramas de los demás roles se conservan.
- `tests/Smoke/superadmin-readonly.php` y `tests/Browser/superadmin-workspace.mjs`.

### Datos y límites funcionales

Las gráficas reutilizan el enfoque SVG/CSS nativo ya utilizado por el proyecto. No se encontró una dependencia de Chart.js/ApexCharts en el código o las dependencias existentes. Funcionan sin CDN ni conexión externa, con cantidades y fechas reales, valores accesibles y estados vacíos.

La base no contiene estado de activación de sedes, suspensión de usuarios ni último acceso. Los indicadores correspondientes muestran **Sin dato**. En sedes se distingue **Vinculada / Pendiente de vinculación**, derivado de empresa y gerente, sin presentarlo como un estado operativo persistido. Usuarios activos significa cuentas sin activación inicial pendiente y se explica en la vista. Clientes activos utiliza el campo real `clientes.activo`.

El correo está configurado en modo **log**: los enlaces quedan en el registro local y no se entregan a bandejas. Las vistas lo advierten. La entrega real requiere revisar el transporte de correo existente. No se cambiaron credenciales ni configuración.

Las acciones de alta, edición, conversión y activación están conectadas a sus endpoints y validaciones existentes. No se ejecutaron escrituras comerciales ni envío real de correo durante la comprobación, para conservar la base de la demostración.

### Comprobación acotada

`C:/xampp/php/php.exe tests/Smoke/superadmin-readonly.php` renderiza las seis vistas con la base configurada `ar_menu_equipo`, comprueba búsqueda, vacíos, filtro de activación, segunda página, controles de accesos generales, feed de notificaciones, errores de validación y autorización. Bloquea cualquier consulta SQL que no sea de lectura antes de ejecutarla. La sesión de prueba es efímera en memoria.

`node tests/Browser/superadmin-workspace.mjs` revisa las páginas exportadas localmente, sin contactar endpoints operativos ni enviar formularios. Comprueba escritorio/móvil, logotipo original, campana, desbordamiento horizontal, paneles laterales y errores de JavaScript. Los HTML, capturas y reporte se guardan en `storage/app/private/superadmin-preview/`, fuera del directorio público.

---

## Diagnóstico previo a la implementación

Revisión: 17 de noviembre de 2026.

Se conservaron los cambios existentes. Esta revisión no ejecutó migraciones, operaciones de escritura en la base de datos, commit ni push.

## Seis vistas

El controlador `SuperadminWorkspaceController::index` contempla las siguientes secciones:

| Vista | Código recuperado | Pendiente |
| --- | --- | --- |
| Panel (`panel`) | Ruta y carga de datos | Plantilla y presentación |
| Solicitudes (`solicitudes`) | Consulta, filtros, selección y conversión comercial | Plantilla y presentación |
| Clientes (`clientes`) | Consulta, filtros y acciones de guardado | Plantilla y presentación |
| Sedes (`sedes`) | Consulta, filtros y acciones de guardado | Plantilla y presentación |
| Usuarios (`usuarios`) | Consulta, filtros, guardado y activación | Plantilla y presentación |
| Configuración (`configuracion`) | Selección de módulo | Plantilla, presentación y alcance funcional por recuperar |

Ninguna de las seis puede considerarse terminada: el controlador devuelve `superadmin.workspace`, que no existe. También faltan `superadmin.navigation`, `public/css/superadmin-workspace.css` y `public/js/superadmin-workspace.js`, ya referenciados por el layout. El acceso del Superadministrador al dashboard depende de esta implementación incompleta.

No se localizaron referencias visuales del Superadministrador en el repositorio ni en los archivos privados de recuperación revisados. Se solicitaron al usuario para conservar los colores, las gráficas y las estructuras originales antes de implementar la presentación pendiente.

## Migración autorizada

`2026_11_16_000003_prepare_superadmin_commercial_links` figura ejecutada en el lote 22.

Se ejecutó únicamente la verificación de lectura `storage/app/private/superadmin-migration-check.php`, sin el argumento `before`. Resultado:

- Respaldo validado mediante SHA-256.
- Datos originales de todas las tablas operativas y comerciales idénticos a la captura anterior; se excluye la tabla `migrations` de esta comparación.
- Columnas comerciales autorizadas presentes en `clientes`, `sedes` y `contactos`.

Se consultó `artisan migrate:status`; otras migraciones figuran pendientes y no se ejecutaron.

## Comprobaciones acotadas

- Sintaxis PHP correcta en el controlador del espacio de Superadministrador y en `SuperadminCommercialFlow`.
- Diez rutas `superadmin.*` registradas.
- No se probaron operaciones comerciales de escritura ni envíos de correo sobre los datos existentes.

## Continuación

Recuperar las referencias visuales y el alcance de Configuración; completar las plantillas y recursos faltantes sobre los controladores existentes. Validar después las seis vistas con una única revisión dirigida. No repetir la migración ni reconstruir los módulos anteriores.
