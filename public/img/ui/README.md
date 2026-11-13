# Imágenes del capitán

Recursos locales derivados de los videos que ya incluía NUTRILIADO:

- `captain-dining.webp`: fotograma en el segundo 2 de `public/videos/comedor-industrial.mp4`, 1280 × 720.
- `captain-kitchen.webp`: fotograma en el segundo 2 de `public/videos/login-cocina.mp4`, 1600 × 900.

Son imágenes reales de cocina y servicio. Se utilizan como decoración, no como fotografías de una sede específica ni como datos de la operación. No requieren conexiones externas.

Extracción reproducible: `node tests/Browser/operations-smoke.mjs --extract-captain-assets`.

## Fotografías de programación

Descargadas como archivos JPEG locales; no requieren conexión externa para mostrarse. Se usan únicamente como decoración del tipo de servicio, no como fotografías del menú real de una sede.

- `service-breakfast.jpg`: Luke Tokaryk, huevos, tostadas y café. Fuente: https://unsplash.com/photos/a-white-plate-topped-with-eggs-and-toast-VANvc2u1eic — Unsplash License.
- `service-lunch.jpg`: fotografía de hamburguesa y acompañamiento, Pexels, foto 70497. Fuente: https://www.pexels.com/photo/food-dinner-lunch-unhealthy-70497/ — Pexels License. Archivo: https://images.pexels.com/photos/70497/pexels-photo-70497.jpeg.
- `service-dinner.jpg`: Brelyn Bashrum, pasta con salsa. Fuente: https://unsplash.com/photos/penne-pasta-with-red-sauce-on-a-white-plate-oX8mTVCOBbg — Unsplash License.

Se conserva la proporción original al encuadrarlas mediante CSS. Las estadísticas pequeñas no utilizan fotografías y las sedes las muestran únicamente en su encabezado.
