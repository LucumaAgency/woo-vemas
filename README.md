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
