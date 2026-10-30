<?php
/**
 * Plugin Name: VeMás Tienda (woo-vemas)
 * Description: Checkout de reportes digitales: sin dirección, con boleta o factura (DNI/CE o RUC + razón social), sin notas del pedido.
 * Version: 1.0.2
 * Author: Lucuma Agency
 * Plugin URI: https://github.com/LucumaAgency/woo-vemas
 * Requires Plugins: woocommerce
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/** ¿El carrito solo tiene productos virtuales? (siempre, en la tienda de reportes) */
function vemas_carrito_virtual() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) return false;
	foreach ( WC()->cart->get_cart() as $item ) {
		if ( empty( $item['data'] ) || ! $item['data']->is_virtual() ) return false;
	}
	return true;
}

/** Campos de facturación */
add_filter( 'woocommerce_checkout_fields', function ( $f ) {
	if ( vemas_carrito_virtual() ) {
		foreach ( [ 'billing_address_1', 'billing_address_2', 'billing_city', 'billing_postcode', 'billing_state' ] as $k ) unset( $f['billing'][ $k ] );
	}
	$f['billing']['billing_first_name']['priority'] = 10;
	$f['billing']['billing_last_name']['priority']  = 20;
	$f['billing']['billing_email']['priority']      = 30;
	$f['billing']['billing_email']['class']         = [ 'form-row-first' ];
	$f['billing']['billing_phone']['priority']      = 40;
	$f['billing']['billing_phone']['required']      = true;
	$f['billing']['billing_phone']['label']         = 'Celular';
	$f['billing']['billing_phone']['class']         = [ 'form-row-last' ];
	$f['billing']['billing_comprobante'] = [
		'type' => 'select', 'label' => 'Comprobante', 'required' => true, 'priority' => 50, 'class' => [ 'form-row-first' ],
		'options' => [ 'boleta' => 'Boleta', 'factura' => 'Factura' ], 'default' => 'boleta',
	];
	$f['billing']['billing_documento'] = [
		'type' => 'text', 'label' => 'DNI o carné de extranjería', 'required' => true, 'priority' => 60,
		'class' => [ 'form-row-last' ], 'custom_attributes' => [ 'inputmode' => 'numeric', 'maxlength' => '12' ],
	];
	$f['billing']['billing_company']['label']    = 'Razón social';
	$f['billing']['billing_company']['priority'] = 70;
	$f['billing']['billing_company']['required'] = false; // obligatoria solo con factura (validación abajo)
	$f['billing']['billing_company']['class']    = [ 'form-row-wide', 'vemas-solo-factura' ];
	if ( isset( $f['billing']['billing_country'] ) ) $f['billing']['billing_country']['priority'] = 80;
	unset( $f['order']['order_comments'] );
	// Woo ordena por prioridad antes de este filtro: reordenar aquí para que valga el orden de arriba
	uasort( $f['billing'], function ( $x, $y ) { return ( $x['priority'] ?? 999 ) <=> ( $y['priority'] ?? 999 ); } );
	return $f;
}, 100 );

add_filter( 'woocommerce_enable_order_notes_field', '__return_false' );
add_filter( 'woocommerce_order_button_text', function () { return 'Pagar ahora'; } );

/** Validación DNI/CE o RUC */
add_action( 'woocommerce_after_checkout_validation', function ( $data, $errors ) {
	$tipo = sanitize_key( $_POST['billing_comprobante'] ?? 'boleta' );
	$doc  = preg_replace( '/\s+/', '', (string) ( $_POST['billing_documento'] ?? '' ) );
	if ( $tipo === 'factura' ) {
		if ( ! preg_match( '/^(10|15|17|20)\d{9}$/', $doc ) ) $errors->add( 'validation', 'Para factura ingresa un <strong>RUC</strong> válido de 11 dígitos.' );
		if ( trim( (string) ( $_POST['billing_company'] ?? '' ) ) === '' ) $errors->add( 'validation', 'Para factura ingresa la <strong>razón social</strong>.' );
	} elseif ( $doc !== '' && ! preg_match( '/^(\d{8}|[A-Za-z0-9]{9,12})$/', $doc ) ) {
		$errors->add( 'validation', 'Ingresa un <strong>DNI</strong> de 8 dígitos o un carné de extranjería válido.' );
	}
}, 10, 2 );

/** Guardar (Woo guarda billing_* como _billing_*; se refuerza por si acaso) */
add_action( 'woocommerce_checkout_create_order', function ( $order, $data ) {
	foreach ( [ 'billing_comprobante', 'billing_documento' ] as $k ) {
		if ( isset( $_POST[ $k ] ) ) $order->update_meta_data( "_$k", sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) );
	}
}, 10, 2 );

function vemas_datos_comprobante( $order ) {
	$t = $order->get_meta( '_billing_comprobante' );
	if ( ! $t ) return [];
	$out = [ 'Comprobante' => ucfirst( $t ), ( $t === 'factura' ? 'RUC' : 'Documento' ) => $order->get_meta( '_billing_documento' ) ];
	if ( $t === 'factura' ) $out['Razón social'] = $order->get_billing_company();
	return $out;
}

/** Mostrar en el pedido (admin) y en los correos */
add_action( 'woocommerce_admin_order_data_after_billing_address', function ( $order ) {
	foreach ( vemas_datos_comprobante( $order ) as $k => $v ) echo '<p><strong>' . esc_html( $k ) . ':</strong> ' . esc_html( $v ) . '</p>';
} );
add_filter( 'woocommerce_email_order_meta_fields', function ( $fields, $sent_to_admin, $order ) {
	foreach ( vemas_datos_comprobante( $order ) as $k => $v ) $fields[ sanitize_title( $k ) ] = [ 'label' => $k, 'value' => $v ];
	return $fields;
}, 10, 3 );

/** Cambia la etiqueta del documento y muestra la razón social según el comprobante */
add_action( 'wp_footer', function () {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) return; ?>
	<script>
	(function(){
		function sync(){
			var s=document.getElementById('billing_comprobante'); if(!s) return;
			var f=s.value==='factura', l=document.querySelector('label[for="billing_documento"]');
			if(l){ var r=l.querySelector('.required'); l.textContent=f?'RUC ':'DNI o carné de extranjería '; if(r) l.appendChild(r); }
			var c=document.getElementById('billing_company_field'); if(c) c.style.display=f?'':'none';
			var i=document.getElementById('billing_documento'); if(i) i.setAttribute('maxlength', f?'11':'12');
		}
		document.addEventListener('change',function(e){ if(e.target && e.target.id==='billing_comprobante') sync(); });
		document.addEventListener('DOMContentLoaded',sync); if(window.jQuery) jQuery(document.body).on('updated_checkout',sync); sync();
	})();
	</script>
<?php } );

/* 1.0.2 · Carga liviana: cada reporte tiene una sola portada, así que la galería de Woo (zoom, carrusel y visor) sobra.
   Tampoco hay productos variables ni emojis. El carrito y el checkout usan shortcodes, así que Woo Blocks ya no carga. */
add_action( 'after_setup_theme', function () {
	remove_theme_support( 'wc-product-gallery-zoom' );
	remove_theme_support( 'wc-product-gallery-lightbox' );
	remove_theme_support( 'wc-product-gallery-slider' );
}, 100 );
add_action( 'wp_enqueue_scripts', function () {
	foreach ( array( 'wc-zoom', 'zoom', 'wc-flexslider', 'flexslider', 'wc-photoswipe', 'photoswipe', 'wc-photoswipe-ui-default',
		'photoswipe-ui-default', 'wc-add-to-cart-variation' ) as $h ) {
		wp_dequeue_script( $h );
	}
	wp_dequeue_style( 'photoswipe' );
	wp_dequeue_style( 'photoswipe-default-skin' );
}, 100 );
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );

/* 1.0.2 · Mi cuenta para reportes digitales: sin direcciones, menú con nombres claros y un resumen propio. */
add_filter( 'woocommerce_account_menu_items', function ( $items ) {
	unset( $items['edit-address'] );
	$nombres = array(
		'dashboard'       => 'Resumen',
		'downloads'       => 'Mis reportes',
		'orders'          => 'Mis pedidos',
		'edit-account'    => 'Mis datos',
		'customer-logout' => 'Cerrar sesión',
	);
	$out = array();
	foreach ( array( 'dashboard', 'downloads', 'orders', 'edit-account', 'customer-logout' ) as $k ) {
		if ( isset( $items[ $k ] ) ) $out[ $k ] = $nombres[ $k ];
	}
	return $out + $items;
} );

add_filter( 'wc_get_template', function ( $located, $template_name ) {
	if ( 'myaccount/dashboard.php' === $template_name ) {
		$propio = __DIR__ . '/templates/dashboard.php';
		if ( file_exists( $propio ) ) return $propio;
	}
	return $located;
}, 10, 2 );
