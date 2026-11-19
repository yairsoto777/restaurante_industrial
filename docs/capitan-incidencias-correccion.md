# Incidencias del Capitán: corrección de zona, mesa y jornada

La causa del 422 era la combinación de dos problemas: el selector de mesas mostraba todas las mesas de la sede sin depender de la zona; y el controlador rechazaba las combinaciones incompatibles con `abort(422)` en lugar de un error de validación recuperable.

## Corrección

- Selector dependiente: solo incluye mesas de la zona seleccionada, envía el `id_mesa` real y borra la selección anterior al cambiar o quitar la zona. Sin zona, el selector de mesa queda deshabilitado. Se permite incidencia general sin mesa o sin servicio vinculado.
- Backend: validación nombrada `incident`, sin quitar controles de rol o sede. Comprueba zona de la sede activa, mesa de esa zona/sede, servicio aprobado de la sede y coincidencia con `fecha_jornada` cuando se envía. Las zonas y mesas son inventario de sede, no una asignación exclusiva al servicio; no se exige que tengan mesero para poder denunciar precisamente una falta de cobertura.
- Si una solicitud directa aporta solo una mesa válida, su zona se obtiene del registro real y se guarda, evitando relaciones ambiguas. IDs malformados, ajenos o incompatibles no se guardan.
- El formulario usa `FormData` y muestra la respuesta de validación dentro del diálogo. No se cierra ni se recarga en un error: conserva texto, servicio, zona, mesa y el archivo seleccionado. Los mensajes se insertan como texto. Un envío ordinario sin JavaScript vuelve al formulario con los campos anteriores y el diálogo reabierto; los navegadores exigen seleccionar de nuevo los archivos en ese caso.
- El almacenamiento permanente de la evidencia reutiliza el mecanismo existente (JPG/JPEG, PNG, WEBP, máximo 4 MB), después de validar todas las relaciones. No se cambian rutas de almacenamiento ni se eliminan evidencias previas.
- Tras guardar se conserva la fecha/servicio consultado, se actualiza el historial y se presenta la confirmación con el verde existente. Solo se añadió el modificador de éxito; no se rediseñó el formulario.
- Comedor en vivo muestra incidencias de la jornada consultada y generales; en el detalle de una mesa incluye las vinculadas a ella y las generales de su zona. Conserva el mapa, sus ilustraciones, estados, selección y actualización parcial existente cada 30 segundos.

## Verificación aislada

`php artisan test tests/Feature/CaptainIncidentFlowTest.php`: 3 pruebas nuevas, 45 aserciones correctas en SQLite `:memory:`. Cubren relaciones incompatibles, regreso con errores y datos capturados, rechazo de datos de otra sede, jornada incorrecta o no aprobada, incidencia general, evidencia persistida, historial, confirmación verde y visibilidad en la jornada correcta del comedor.

`node tests/Browser/captain-incidents.mjs`: comprobación nueva de filtrado por zona, limpieza de mesa, IDs enviados, conservación del archivo/datos ante error, incidencia general y redirección de éxito; sin navegador ni servidor.

No se repitieron las suites anteriores. Sin cambios de otros roles, migraciones, escrituras a la base real, commit ni push.
