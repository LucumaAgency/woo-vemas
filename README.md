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
