<?php
/**
 * 1.4.0 · Proyectos consultados (casos de estudio de VeMás).
 *
 * - Tipo de contenido vm_proyecto + taxonomía vm_tipo (Residencial, Comercial, Usos mixtos, Horizontal, Industrial).
 * - URL como en la web anterior: /inmuebles/<tipo>/<slug>/ ; archivo /inmuebles/ y /inmuebles/<tipo>/.
 * - Si un caso cambió de tipo, /inmuebles/<tipo-viejo>/<slug>/ redirige 301 a la URL nueva.
 * - Campos (meta, editables por REST): vm_inmobiliaria, vm_distrito, vm_departamento, vm_tipo_proyecto, vm_anio,
 *   vm_estudio (tal como en la web anterior: "URBANO + BENCH + ESTRATEGIAS"), vm_enlace.
 * - Solo datos de la web anterior: sin módulos ni descripciones agregadas (1.4.2).
 * - Datos dinámicos de Bricks: {vm_tipo} {vm_tipo_url} {vm_tipo_plural} {vm_proyecto_min} {vm_archivo_titulo} {vm_tipos_nav}.
 */
defined( 'ABSPATH' ) || exit;

const VM_PT  = 'vm_proyecto';
const VM_TAX = 'vm_tipo';
const VM_RW  = '1.4.1'; // cambiar para volver a generar las reglas de URL

function vm_meta_keys() {
	return array( 'vm_inmobiliaria', 'vm_distrito', 'vm_departamento', 'vm_tipo_proyecto', 'vm_anio', 'vm_estudio', 'vm_enlace' );
}

add_action( 'init', function () {
	register_taxonomy( VM_TAX, VM_PT, array(
		'labels'            => array( 'name' => 'Tipos de proyecto', 'singular_name' => 'Tipo de proyecto', 'menu_name' => 'Tipos' ),
		'public'            => true,
		'hierarchical'      => true,
		'show_in_rest'      => true,
		'show_admin_column' => true,
		'rewrite'           => array( 'slug' => 'inmuebles', 'with_front' => false ),
	) );
	register_post_type( VM_PT, array(
		'labels'       => array( 'name' => 'Proyectos', 'singular_name' => 'Proyecto', 'add_new_item' => 'Añadir proyecto',
			'edit_item' => 'Editar proyecto', 'all_items' => 'Todos los proyectos', 'menu_name' => 'Proyectos' ),
		'public'       => true,
		'show_in_rest' => true,
		'rest_base'    => 'proyectos',
		'menu_icon'    => 'dashicons-building',
		'menu_position'=> 21,
		'supports'     => array( 'title', 'thumbnail', 'excerpt', 'custom-fields', 'revisions' ),
		'has_archive'  => 'inmuebles',
		'rewrite'      => array( 'slug' => 'inmuebles/%' . VM_TAX . '%', 'with_front' => false ),
		'taxonomies'   => array( VM_TAX ),
	) );
	foreach ( vm_meta_keys() as $k ) {
		register_post_meta( VM_PT, $k, array(
			'type' => 'string', 'single' => true, 'show_in_rest' => true,
			'sanitize_callback' => 'vm_enlace' === $k ? 'esc_url_raw' : 'sanitize_text_field',
			'auth_callback' => function () { return current_user_can( 'edit_posts' ); },
		) );
	}
} );

add_action( 'init', function () {
	if ( get_option( 'vm_proyectos_rw' ) !== VM_RW ) {
		flush_rewrite_rules( false );
		update_option( 'vm_proyectos_rw', VM_RW );
	}
}, 99 );

function vm_term( $post = null ) {
	$t = get_the_terms( $post ?: get_the_ID(), VM_TAX );
	return ( $t && ! is_wp_error( $t ) ) ? $t[0] : null;
}

add_filter( 'post_type_link', function ( $url, $post ) {
	if ( VM_PT !== $post->post_type ) return $url;
	$t = vm_term( $post );
	return str_replace( '%' . VM_TAX . '%', $t ? $t->slug : 'proyecto', $url );
}, 10, 2 );

// URL vieja con otro tipo → 301 a la nueva (WordPress la resuelve igual, con o sin 404).
add_action( 'template_redirect', function () {
	if ( is_singular( VM_PT ) ) {
		$pedida = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
		$buena  = trim( (string) wp_parse_url( get_permalink( get_queried_object_id() ), PHP_URL_PATH ), '/' );
		if ( $pedida && $buena && $pedida !== $buena && ! is_preview() ) {
			wp_safe_redirect( get_permalink( get_queried_object_id() ), 301 );
			exit;
		}
		return;
	}
	if ( ! is_404() ) return;
	$path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
	if ( ! preg_match( '#^inmuebles/[^/]+/([^/]+)$#', $path, $m ) ) return;
	$p = get_page_by_path( sanitize_title( $m[1] ), OBJECT, VM_PT );
	if ( $p && 'publish' === $p->post_status ) {
		wp_safe_redirect( get_permalink( $p ), 301 );
		exit;
	}
} );

// Archivo y tipos: todos los proyectos en una página, del estudio más reciente al más antiguo.
add_action( 'pre_get_posts', function ( $q ) {
	if ( is_admin() || ! $q->is_main_query() ) return;
	if ( $q->is_post_type_archive( VM_PT ) || $q->is_tax( VM_TAX ) ) {
		$q->set( 'posts_per_page', -1 );
		$q->set( 'meta_key', 'vm_anio' );
		$q->set( 'orderby', array( 'meta_value_num' => 'DESC', 'title' => 'ASC' ) );
	}
} );

function vm_css_tipos() {
	static $done = false;
	if ( $done ) return '';
	$done = true;
	return '<style>.vm-tipos{display:flex;gap:8px;flex-wrap:wrap}'
		. '.vm-tipos a{font-family:Montserrat,sans-serif;font-size:13px;font-weight:600;padding:8px 14px;border-radius:999px;border:1px solid #E3E9E8;color:#191A1A;text-decoration:none;transition:.2s}'
		. '.vm-tipos a:hover{border-color:#1C8E80;color:#1C8E80}.vm-tipos a.on{background:#1C8E80;border-color:#1C8E80;color:#fff!important}</style>';
}

/* ---------- Datos dinámicos para Bricks ---------- */

function vm_tipo_plural( $t ) {
	$map = array( 'residencial' => 'residenciales', 'comercial' => 'comerciales', 'usos-mixtos' => 'de usos mixtos',
		'horizontal' => 'horizontales', 'industrial' => 'industriales' );
	return $t ? ( $map[ $t->slug ] ?? mb_strtolower( $t->name ) ) : '';
}

function vm_tag_valor( $tag, $post ) {
	$id = $post ? ( is_object( $post ) ? $post->ID : (int) $post ) : get_the_ID();
	switch ( $tag ) {
		case 'vm_tipo':
			$t = vm_term( $id ); return $t ? $t->name : '';
		case 'vm_tipo_url':
			$t = vm_term( $id ); return $t ? get_term_link( $t ) : get_post_type_archive_link( VM_PT );
		case 'vm_tipo_plural':
			return vm_tipo_plural( vm_term( $id ) );
		case 'vm_proyecto_min':
			return mb_strtolower( (string) get_post_meta( $id, 'vm_tipo_proyecto', true ) );
		case 'vm_archivo_titulo':
			if ( is_tax( VM_TAX ) ) return 'Proyectos ' . vm_tipo_plural( get_queried_object() );
			return 'Estudios en Lima Metropolitana y provincias';
		case 'vm_tipos_nav':
			$cur = is_tax( VM_TAX ) ? get_queried_object_id() : 0;
			$h   = '<nav class="vm-tipos"><a href="' . esc_url( get_post_type_archive_link( VM_PT ) ) . '" class="' . ( $cur ? '' : 'on' ) . '">Todos</a>';
			foreach ( get_terms( array( 'taxonomy' => VM_TAX, 'hide_empty' => true ) ) as $t ) {
				$h .= '<a href="' . esc_url( get_term_link( $t ) ) . '" class="' . ( $cur === $t->term_id ? 'on' : '' ) . '">' . esc_html( $t->name ) . '</a>';
			}
			return vm_css_tipos() . $h . '</nav>';
	}
	return null;
}

function vm_tags() {
	return array( 'vm_tipo', 'vm_tipo_url', 'vm_tipo_plural', 'vm_proyecto_min', 'vm_archivo_titulo', 'vm_tipos_nav' );
}

add_filter( 'bricks/dynamic_tags_list', function ( $tags ) {
	foreach ( vm_tags() as $t ) $tags[] = array( 'name' => '{' . $t . '}', 'label' => $t, 'group' => 'VeMás proyectos' );
	return $tags;
} );

add_filter( 'bricks/dynamic_data/render_tag', function ( $tag, $post, $context = 'text' ) {
	if ( ! is_string( $tag ) ) return $tag;
	$clean = trim( $tag, '{}' );
	if ( ! in_array( $clean, vm_tags(), true ) ) return $tag;
	$v = vm_tag_valor( $clean, $post );
	return null === $v ? $tag : $v;
}, 20, 3 );

add_filter( 'bricks/dynamic_data/render_content', function ( $content, $post, $context = 'text' ) {
	if ( ! is_string( $content ) || false === strpos( $content, '{vm_' ) ) return $content;
	foreach ( vm_tags() as $t ) {
		if ( false !== strpos( $content, '{' . $t . '}' ) ) {
			$content = str_replace( '{' . $t . '}', (string) vm_tag_valor( $t, $post ), $content );
		}
	}
	return $content;
}, 20, 3 );

// Loop "Otros proyectos" (elemento con id vmp-rel en la plantilla): mismo tipo, sin el actual, 4 al azar.
add_filter( 'bricks/posts/query_vars', function ( $vars, $settings, $element_id ) {
	if ( 'vmprel' !== $element_id || ! is_singular( VM_PT ) ) return $vars;
	$t = vm_term( get_queried_object_id() );
	$vars['post_type']      = VM_PT;
	$vars['posts_per_page'] = 4;
	$vars['post__not_in']   = array( get_queried_object_id() );
	$vars['orderby']        = 'rand';
	if ( $t ) $vars['tax_query'] = array( array( 'taxonomy' => VM_TAX, 'terms' => array( $t->term_id ) ) );
	return $vars;
}, 10, 3 );
