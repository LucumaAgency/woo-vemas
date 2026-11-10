# woo-vemas

Plugin de WooCommerce para la tienda de reportes digitales de VeMás (build.ve-mas.com, luego ve-mas.com).

- Checkout sin dirección cuando el carrito solo tiene productos virtuales.
- Comprobante **boleta o factura**: DNI/CE o RUC (validado: 11 dígitos, empieza en 10, 15, 17 o 20) y razón social obligatoria con factura.
- Sin notas del pedido; botón "Pagar ahora".
- Comprobante, documento y razón social visibles en el pedido (admin) y en los correos.

## Instalación
Con **Deployer for Git**: repo `LucumaAgency/woo-vemas`, rama `main`. El archivo principal (`woo-vemas.php`) está en la raíz del repo.
No usar el "Download ZIP" de GitHub (crea la carpeta `woo-vemas-main` y WordPress lo trata como otro plugin).

Metadatos del pedido: `_billing_comprobante` (boleta|factura), `_billing_documento`.

## Bitácora
- 1.0.0 · 2026-10-30 · Primera versión.
- 1.0.1 · 2026-10-30 · Orden de campos: nombre, apellidos, correo, celular, comprobante, documento, razón social, país.
- 1.0.2 · 2026-10-30 · Carga liviana: sin galería de Woo (zoom, carrusel, visor), sin script de variaciones ni emojis en el front. Mi cuenta: sin direcciones, menú Resumen / Mis reportes / Mis pedidos / Mis datos, resumen propio (templates/dashboard.php).
- 1.1.0 · 2026-10-30 · migracion.php TEMPORAL: rutas REST de administrador para comprobar archivos, proteger PDF y registrar imágenes copiadas de ve-mas.com. Quitar después del lanzamiento.
- 1.1.1 · 2026-11-10 · Fuera migracion.php (rutas temporales vemas/v1): el sitio ya se lanzó en ve-mas.com.
- 1.2.0 · 2026-11-10 · medicion.php: eventos GA4 por gtag (view_item, add_to_cart, begin_checkout, purchase una vez por pedido). Sin GTM: usa la etiqueta GT-5TQ464CS que está en Bricks.
- 1.3.0 · 2026-11-10 · migracion-pedidos.php TEMPORAL: ruta POST vemas/v1/importar-pedido para pasar los 37 pedidos de la web anterior (respaldo del 2-nov) con fecha, estado y montos originales, sin correos. Quitar después de importar.
- 1.3.1 · 2026-11-10 · Importación: la fecha de completado queda la original (antes salía la del día de importación); `corregir` rehace fechas de un pedido ya importado; GET vemas/v1/importados para verificar fechas y permisos de descarga.
- 1.3.2 · 2026-11-10 · Fuera migracion-pedidos.php: los 37 pedidos ya están importados (ids 384 a 420).
- 1.4.0 · 2026-11-10 · proyectos.php: tipo de contenido Proyectos (vm_proyecto) + Tipos (vm_tipo) con URL /inmuebles/<tipo>/<slug>/ como la web anterior (301 si cambia el tipo), campos vm_*, shortcode [vemas_modulos parte="strip|cards"] y datos dinámicos {vm_*} para las plantillas de Bricks.
