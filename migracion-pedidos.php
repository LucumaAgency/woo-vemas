<?php
/**
 * TEMPORAL (1.3.0): importa los pedidos de la web anterior de ve-mas.com desde el respaldo del 2-nov-2026.
 * Quitar después de la importación.
 *
 * POST /wp-json/vemas/v1/importar-pedido  (solo administradores de la tienda)
 * Crea el pedido con su fecha, estado, montos y productos originales, sin enviar ningún correo.
 * Idempotente: si ya existe un pedido con el mismo _vemas_pedido_original, lo devuelve sin tocarlo.
 */
defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', function () {
	register_rest_route( 'vemas/v1', '/importar-pedido', array(
		'methods'             => 'POST',
		'permission_callback' => function () { return current_user_can( 'manage_woocommerce' ); },
		'callback'            => 'vemas_importar_pedido',
	) );
} );

function vemas_sin_correos() {
	add_filter( 'pre_wp_mail', '__return_false', 999 );
	foreach ( WC()->mailer()->get_emails() as $email ) {
		add_filter( 'woocommerce_email_enabled_' . $email->id, '__return_false', 999 );
	}
}

function vemas_fecha( $gmt ) {
	return $gmt ? new WC_DateTime( $gmt, new DateTimeZone( 'UTC' ) ) : null;
}

function vemas_importar_pedido( WP_REST_Request $req ) {
	$p      = $req->get_json_params();
	$origen = absint( $p['origen_id'] ?? 0 );
	if ( ! $origen || empty( $p['items'] ) ) {
		return new WP_Error( 'vemas_datos', 'Falta origen_id o items', array( 'status' => 400 ) );
	}

	$ya = wc_get_orders( array(
		'limit'      => 1,
		'return'     => 'ids',
		'meta_query' => array( array( 'key' => '_vemas_pedido_original', 'value' => (string) $origen ) ),
	) );
	if ( $ya ) {
		return array( 'origen' => $origen, 'id' => $ya[0], 'creado' => false );
	}

	vemas_sin_correos();

	// 1) Pedido pendiente con todos sus datos (sin método de pago todavía).
	$order = new WC_Order();
	$order->set_created_via( 'importacion-web-anterior' );
	$order->set_currency( 'PEN' );
	$order->set_prices_include_tax( false );
	$order->set_address( $p['billing'] ?? array(), 'billing' );
	$order->set_address( $p['shipping'] ?? array(), 'shipping' );
	$order->set_customer_ip_address( $p['ip'] ?? '' );
	$order->set_customer_user_agent( $p['ua'] ?? '' );
	$order->set_customer_note( $p['nota_cliente'] ?? '' );
	$order->set_date_created( vemas_fecha( $p['fecha_gmt'] ) );

	$email = $p['billing']['email'] ?? '';
	$user  = $email ? get_user_by( 'email', $email ) : false;
	if ( $user ) {
		$order->set_customer_id( $user->ID );
	}

	foreach ( $p['items'] as $it ) {
		$prod = wc_get_product( absint( $it['product_id'] ) );
		if ( ! $prod ) {
			return new WP_Error( 'vemas_producto', 'No existe el producto ' . $it['product_id'], array( 'status' => 400 ) );
		}
		$order->add_product( $prod, (int) $it['qty'], array(
			'name'     => $it['nombre'],
			'subtotal' => $it['subtotal'],
			'total'    => $it['total'],
		) );
	}
	foreach ( $p['cupones'] ?? array() as $c ) {
		$ci = new WC_Order_Item_Coupon();
		$ci->set_code( $c['code'] );
		$ci->set_discount( $c['discount'] );
		$order->add_item( $ci );
	}
	$order->set_discount_total( $p['descuento'] ?? 0 );
	$order->set_total( $p['total'] );

	$order->update_meta_data( '_vemas_pedido_original', (string) $origen );
	$order->update_meta_data( '_vemas_origen', wp_json_encode( $p['origen'] ?? array() ) );
	$order->update_meta_data( '_vemas_ga4_purchase', time() ); // nunca enviar purchase a GA4
	if ( ! empty( $p['order_key'] ) ) {
		$order->set_order_key( $p['order_key'] );
	}
	$order->save();

	// 2) Estado final con sus fechas (da los permisos de descarga a los completados).
	if ( ! empty( $p['pagado_gmt'] ) ) {
		$order->set_date_paid( vemas_fecha( $p['pagado_gmt'] ) );
	}
	if ( ! empty( $p['completado_gmt'] ) ) {
		$order->set_date_completed( vemas_fecha( $p['completado_gmt'] ) );
	}
	$order->set_status( $p['status'], '', false );
	$order->save();

	// 3) Método de pago al final, para que el plugin de Mercado Pago no actúe sobre el cambio de estado.
	$order->set_payment_method( $p['metodo'] ?? '' );
	$order->set_payment_method_title( $p['metodo_titulo'] ?? '' );
	$order->set_transaction_id( $p['transaccion'] ?? '' );
	$order->set_date_modified( vemas_fecha( $p['modificado_gmt'] ?? $p['fecha_gmt'] ) );
	$order->save();

	$order->add_order_note( $p['nota_privada'] ?? ( 'Pedido #' . $origen . ' importado de la web anterior.' ), 0, false );

	return array( 'origen' => $origen, 'id' => $order->get_id(), 'creado' => true, 'estado' => $order->get_status(), 'cliente' => $order->get_customer_id() );
}
