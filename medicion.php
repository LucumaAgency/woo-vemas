<?php
/**
 * 1.2.0 · Eventos de ecommerce para GA4 con la etiqueta de Google que ya carga el sitio (gtag, sin GTM).
 * view_item (ficha), add_to_cart (en la carga siguiente a agregar), begin_checkout (checkout) y purchase (página de gracias, una vez por pedido).
 * Con Yape/Plin la compra se cuenta al llegar a "gracias", aunque el pago se confirme después a mano.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function vemas_ga4_item( $product, $qty = 1 ) {
	$cats = wp_get_post_terms( $product->get_parent_id() ?: $product->get_id(), 'product_cat', array( 'fields' => 'names' ) );
	$item = array(
		'item_id'   => $product->get_sku() ?: (string) $product->get_id(),
		'item_name' => $product->get_name(),
		'price'     => (float) wc_get_price_to_display( $product ),
		'quantity'  => (int) $qty,
	);
	if ( ! is_wp_error( $cats ) && $cats ) $item['item_category'] = $cats[0];
	return $item;
}

function vemas_ga4_valor( $items ) {
	$v = 0;
	foreach ( $items as $i ) $v += $i['price'] * $i['quantity'];
	return round( $v, 2 );
}

/** Al agregar al carrito (normal o AJAX) se guarda el evento y se envía en la siguiente página. */
add_action( 'woocommerce_add_to_cart', function ( $key, $product_id, $qty, $variation_id ) {
	if ( ! WC()->session ) return;
	$p = wc_get_product( $variation_id ?: $product_id );
	if ( ! $p ) return;
	$pend   = (array) WC()->session->get( 'vemas_ga4_add', array() );
	$pend[] = vemas_ga4_item( $p, $qty );
	WC()->session->set( 'vemas_ga4_add', $pend );
}, 10, 4 );

add_action( 'wp_footer', function () {
	if ( ! function_exists( 'is_product' ) ) return;
	$eventos = array();
	$moneda  = get_woocommerce_currency();

	if ( WC()->session ) {
		$pend = (array) WC()->session->get( 'vemas_ga4_add', array() );
		if ( $pend ) {
			$eventos[] = array( 'add_to_cart', array( 'currency' => $moneda, 'value' => vemas_ga4_valor( $pend ), 'items' => $pend ) );
			WC()->session->set( 'vemas_ga4_add', array() );
		}
	}

	if ( is_product() ) {
		$p = wc_get_product( get_queried_object_id() );
		if ( $p ) {
			$i = vemas_ga4_item( $p );
			$eventos[] = array( 'view_item', array( 'currency' => $moneda, 'value' => $i['price'], 'items' => array( $i ) ) );
		}
	} elseif ( is_checkout() && ! is_order_received_page() && WC()->cart && ! WC()->cart->is_empty() ) {
		$items = array();
		foreach ( WC()->cart->get_cart() as $c ) $items[] = vemas_ga4_item( $c['data'], $c['quantity'] );
		$eventos[] = array( 'begin_checkout', array( 'currency' => $moneda, 'value' => vemas_ga4_valor( $items ), 'items' => $items ) );
	} elseif ( is_order_received_page() ) {
		$id    = absint( get_query_var( 'order-received' ) );
		$order = $id ? wc_get_order( $id ) : null;
		$key   = isset( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : '';
		if ( $order && hash_equals( $order->get_order_key(), $key ) && ! $order->has_status( 'failed' ) && ! $order->get_meta( '_vemas_ga4_purchase' ) ) {
			$items = array();
			foreach ( $order->get_items() as $it ) {
				$p = $it->get_product();
				if ( ! $p ) continue;
				$i          = vemas_ga4_item( $p, $it->get_quantity() );
				$i['price'] = round( ( (float) $it->get_total() + (float) $it->get_total_tax() ) / max( 1, $it->get_quantity() ), 2 );
				$items[]    = $i;
			}
			$datos = array(
				'transaction_id' => (string) $order->get_order_number(),
				'currency'       => $order->get_currency(),
				'value'          => (float) $order->get_total(),
				'tax'            => (float) $order->get_total_tax(),
				'items'          => $items,
			);
			$cupones = $order->get_coupon_codes();
			if ( $cupones ) $datos['coupon'] = implode( ',', $cupones );
			$eventos[] = array( 'purchase', $datos );
			$order->update_meta_data( '_vemas_ga4_purchase', time() );
			$order->save();
		}
	}

	if ( ! $eventos ) return;
	echo "<script>window.dataLayer=window.dataLayer||[];if(typeof gtag!=='function'){window.gtag=function(){dataLayer.push(arguments);};}\n";
	foreach ( $eventos as $e ) echo 'gtag("event",' . wp_json_encode( $e[0] ) . ',' . wp_json_encode( $e[1] ) . ");\n";
	echo "</script>\n";
}, 50 );
