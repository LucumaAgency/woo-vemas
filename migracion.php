<?php
/**
 * Migración de archivos desde ve-mas.com (TEMPORAL, se borra después del lanzamiento).
 * Rutas REST solo para administradores, siempre dentro de wp-content/uploads:
 *   GET  vemas/v1/archivo?path=2024/05/x.pdf          → existe, tamaño, md5
 *   POST vemas/v1/proteger  {path}                    → mueve un PDF público a woocommerce_uploads/<mismo año/mes>/ con sufijo al azar
 *   POST vemas/v1/registrar {path,title,alt,caption}  → registra en la biblioteca de medios un archivo ya copiado
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function vemas_mig_ruta( $rel ) {
	$base = wp_get_upload_dir()['basedir'];
	$rel  = ltrim( rawurldecode( (string) $rel ), '/' );
	if ( '' === $rel || false !== strpos( $rel, '..' ) ) return false;
	$abs  = $base . '/' . $rel;
	$real = realpath( $abs );
	if ( ! $real || 0 !== strpos( $real, realpath( $base ) . '/' ) ) return false;
	return $real;
}

add_action( 'rest_api_init', function () {
	$admin = function () { return current_user_can( 'manage_options' ); };

	register_rest_route( 'vemas/v1', '/archivo', array( 'methods' => 'GET', 'permission_callback' => $admin,
		'callback' => function ( $req ) {
			$f = vemas_mig_ruta( $req['path'] );
			if ( ! $f || ! is_file( $f ) ) return array( 'existe' => false );
			return array( 'existe' => true, 'bytes' => filesize( $f ), 'md5' => md5_file( $f ) );
		} ) );

	register_rest_route( 'vemas/v1', '/proteger', array( 'methods' => 'POST', 'permission_callback' => $admin,
		'callback' => function ( $req ) {
			$f = vemas_mig_ruta( $req['path'] );
			if ( ! $f || ! is_file( $f ) ) return new WP_Error( 'no_existe', 'No existe', array( 'status' => 404 ) );
			$base = realpath( wp_get_upload_dir()['basedir'] );
			$rel  = substr( $f, strlen( $base ) + 1 );
			if ( 'pdf' !== strtolower( pathinfo( $f, PATHINFO_EXTENSION ) ) ) return new WP_Error( 'no_pdf', 'Solo PDF', array( 'status' => 400 ) );
			if ( 0 === strpos( $rel, 'woocommerce_uploads/' ) ) return new WP_Error( 'ya', 'Ya está protegido', array( 'status' => 400 ) );
			$sub  = dirname( $rel );
			$sub  = isset( $req['carpeta'] ) ? sanitize_file_name( $req['carpeta'] ) : ( '.' === $sub ? '' : $sub );
			$dir  = $base . '/woocommerce_uploads/' . $sub;
			if ( ! wp_mkdir_p( $dir ) ) return new WP_Error( 'dir', 'No se pudo crear la carpeta', array( 'status' => 500 ) );
			$nom  = sanitize_file_name( pathinfo( $f, PATHINFO_FILENAME ) ) . '-' . strtolower( wp_generate_password( 6, false ) ) . '.pdf';
			if ( ! @rename( $f, $dir . '/' . $nom ) ) return new WP_Error( 'mover', 'No se pudo mover', array( 'status' => 500 ) );
			$nuevo = 'woocommerce_uploads/' . ( $sub ? $sub . '/' : '' ) . $nom;
			return array( 'path' => $nuevo, 'url' => wp_get_upload_dir()['baseurl'] . '/' . $nuevo );
		} ) );

	register_rest_route( 'vemas/v1', '/registrar', array( 'methods' => 'POST', 'permission_callback' => $admin,
		'callback' => function ( $req ) {
			$f = vemas_mig_ruta( $req['path'] );
			if ( ! $f || ! is_file( $f ) ) return new WP_Error( 'no_existe', 'No existe', array( 'status' => 404 ) );
			$base = realpath( wp_get_upload_dir()['basedir'] );
			$rel  = substr( $f, strlen( $base ) + 1 );
			$ya   = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'any', 'fields' => 'ids', 'numberposts' => 1,
				'meta_key' => '_wp_attached_file', 'meta_value' => $rel ) );
			if ( $ya ) return array( 'id' => $ya[0], 'nuevo' => false );
			$tipo = wp_check_filetype( $f );
			if ( ! $tipo['type'] || ! preg_match( '#^(image|video)/#', $tipo['type'] ) ) return new WP_Error( 'tipo', 'Solo imagen o video', array( 'status' => 400 ) );
			$id = wp_insert_attachment( array(
				'post_mime_type' => $tipo['type'],
				'post_title'     => sanitize_text_field( $req['title'] ? $req['title'] : pathinfo( $f, PATHINFO_FILENAME ) ),
				'post_excerpt'   => sanitize_text_field( (string) $req['caption'] ),
				'post_status'    => 'inherit',
				'post_date'      => $req['date'] ? sanitize_text_field( $req['date'] ) : current_time( 'mysql' ),
			), $f, 0, true );
			if ( is_wp_error( $id ) ) return $id;
			if ( $req['alt'] ) update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( $req['alt'] ) );
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $f ) );
			return array( 'id' => $id, 'nuevo' => true );
		} ) );
} );
