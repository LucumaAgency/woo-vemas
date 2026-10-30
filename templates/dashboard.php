<?php
/** Resumen de Mi cuenta (woo-vemas). Reemplaza myaccount/dashboard.php de Woo. */
if ( ! defined( 'ABSPATH' ) ) exit;
$user = wp_get_current_user();
$n    = count( WC()->customer ? WC()->customer->get_downloadable_products() : array() );
?>
<p>Hola <strong><?php echo esc_html( $user->first_name ? $user->first_name : $user->display_name ); ?></strong>. ¿No eres tú?
	<a href="<?php echo esc_url( wc_logout_url() ); ?>">Cierra sesión</a>.</p>
<div class="vm-acc-cards">
	<a href="<?php echo esc_url( wc_get_account_endpoint_url( 'downloads' ) ); ?>">
		<strong>Mis reportes</strong>
		<span><?php echo $n ? esc_html( sprintf( _n( '%d reporte listo para descargar.', '%d reportes listos para descargar.', $n ), $n ) ) : 'Aún no tienes reportes.'; ?></span>
	</a>
	<a href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>">
		<strong>Mis pedidos</strong>
		<span>Historial de compras y comprobantes.</span>
	</a>
	<a href="<?php echo esc_url( wc_get_account_endpoint_url( 'edit-account' ) ); ?>">
		<strong>Mis datos</strong>
		<span>Nombre, correo y contraseña.</span>
	</a>
</div>
<p>¿Buscas otro reporte? <a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">Ver la tienda de reportes</a>.
	¿Dudas con una compra? Escríbenos a <a href="mailto:contacto@ve-mas.com">contacto@ve-mas.com</a>.</p>
<?php do_action( 'woocommerce_account_dashboard' ); ?>
