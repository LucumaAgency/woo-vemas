<?php
/**
 * 1.4.0 · Proyectos consultados (casos de estudio de VeMás).
 *
 * - Tipo de contenido vm_proyecto + taxonomía vm_tipo (Residencial, Comercial, Usos mixtos, Horizontal, Industrial).
 * - URL como en la web anterior: /inmuebles/<tipo>/<slug>/ ; archivo /inmuebles/ y /inmuebles/<tipo>/.
 * - Si un caso cambió de tipo, /inmuebles/<tipo-viejo>/<slug>/ redirige 301 a la URL nueva.
 * - Campos (meta, editables por REST): vm_inmobiliaria, vm_distrito, vm_departamento, vm_tipo_proyecto, vm_anio,
 *   vm_estudio (componentes como en la web anterior: "URBANO + BENCH + ESTRATEGIAS"), vm_enlace.
 * - [vemas_modulos parte="strip|cards"]: módulos M1 a M5 usados en el estudio.
 * - Datos dinámicos de Bricks: {vm_tipo} {vm_tipo_url} {vm_tipo_plural} {vm_estudio_resumen} {vm_modulos_n}
 *   {vm_proyecto_min} {vm_archivo_titulo} {vm_tipos_nav}.
 */
defined( 'ABSPATH' ) || exit;

const VM_PT  = 'vm_proyecto';
const VM_TAX = 'vm_tipo';
const VM_RW  = '1.4.0'; // cambiar para volver a generar las reglas de URL

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

// URL vieja con otro tipo → 301 a la nueva.
add_action( 'template_redirect', function () {
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

/* ---------- Módulos ---------- */

function vm_modulos() {
	return array(
		'M1' => array( 'Mercado', 'Benchmarking' ),
		'M2' => array( 'Ciudad', 'Contexto urbano' ),
		'M3' => array( 'Clientes', 'Demanda potencial' ),
		'M4' => array( 'Comercial', 'Canales de venta' ),
		'M5' => array( 'Coyuntura y normativa', 'Contexto macro' ),
	);
}

// Componente del estudio → módulo. Mapeo propuesto por Lucuma (dudosos: SEGUNDA, FLASH, SECTORES, NODOS, UX).
function vm_componentes_def() {
	return array(
		'BENCH'             => array( 'M1', 'Benchmarking', 'Ticket, valor m², áreas y tipologías de la competencia directa, más los indicadores propios de VeMás.' ),
		'SEGUNDA'           => array( 'M1', 'Mercado de segunda', 'Precios y velocidad de reventa en la zona, para contrastar con la oferta nueva.' ),
		'FLASH'             => array( 'M1', 'Estudio flash', 'Lectura rápida de oferta y precios para tomar una decisión puntual.' ),
		'URBANO'            => array( 'M2', 'Contexto urbano', 'Flujos, accesibilidad, hitos, bordes y peligrosidad alrededor del terreno.' ),
		'NODOS'             => array( 'M2', 'Nodos urbanos', 'Centros de actividad cercanos que atraen o alejan a los compradores.' ),
		'USOS'              => array( 'M2', 'Usos y calidad urbana', 'Tráfico, densidad, parques, seguridad y uso actual de los lotes vecinos.' ),
		'FLUJOS'            => array( 'M2', 'Flujos peatonales', 'Quién pasa por la vitrina del proyecto y cómo se compone su público.' ),
		'ESPACIOS PÚBLICOS' => array( 'M2', 'Espacio público', 'Vocación y hábitos de uso de los espacios públicos en un radio caminable.' ),
		'SECTORES'          => array( 'M2', 'Sectores de inversión', 'Comparación de zonas para elegir dónde conviene desarrollar.' ),
		'POBLACIONAL'       => array( 'M3', 'Análisis poblacional', 'Población, nivel socioeconómico, ingreso familiar y ticket promedio del distrito.' ),
		'DEMANDA'           => array( 'M3', 'Demanda potencial', 'Perfiles de cliente, su origen geográfico y el tamaño de la demanda.' ),
		'UX'                => array( 'M3', 'Experiencia de usuario', 'Cómo recorren, entienden y valoran el producto los compradores.' ),
		'ESTRATEGIAS'       => array( 'M4', 'Estrategias comerciales', 'Cliente incógnito, canales que mejor funcionan y tendencias de venta.' ),
		'COYUNTURA'         => array( 'M5', 'Coyuntura', 'Contexto social y económico que puede ser un activo o un pasivo para el proyecto.' ),
		'NORMATIVA'         => array( 'M5', 'Normativa', 'Parámetros, planes y bonos del Estado que condicionan el desarrollo.' ),
	);
}

function vm_componentes( $post = null ) {
	$raw  = (string) get_post_meta( $post ?: get_the_ID(), 'vm_estudio', true );
	$def  = vm_componentes_def();
	$out  = array();
	foreach ( preg_split( '/\s*\+\s*/', mb_strtoupper( trim( $raw ) ) ) as $k ) {
		$k     = trim( $k );
		$alias = array( 'COYUNTURAL' => 'COYUNTURA', 'POBLACIÓN' => 'POBLACIONAL', 'ESPACIO PÚBLICO' => 'ESPACIOS PÚBLICOS' );
		$k     = $alias[ $k ] ?? $k;
		if ( '' === trim( $k, '- ' ) ) continue;
		$out[] = isset( $def[ $k ] ) ? array( 'key' => $k, 'm' => $def[ $k ][0], 't' => $def[ $k ][1], 'd' => $def[ $k ][2] )
			: array( 'key' => $k, 'm' => '', 't' => mb_convert_case( mb_strtolower( $k ), MB_CASE_TITLE ), 'd' => '' );
	}
	return $out;
}

function vm_modulos_usados( $post = null ) {
	$u = array_unique( array_filter( wp_list_pluck( vm_componentes( $post ), 'm' ) ) );
	sort( $u );
	return $u;
}

function vm_css_modulos() {
	static $done = false;
	if ( $done ) return '';
	$done = true;
	return '<style>
.vm-strip{display:flex;gap:8px;margin-top:28px}
.vm-strip span{width:44px;height:44px;border-radius:12px;display:grid;place-items:center;font-family:Montserrat,sans-serif;font-weight:700;font-size:13px;border:1.5px solid #E3E9E8;color:#9AA3A2;background:#fff}
.vm-strip span.on{background:#1C8E80;border-color:#1C8E80;color:#fff}
.vm-strip-t{font-size:14px;color:#5F6766;margin-top:12px}
.vm-mcards{display:grid;gap:16px}
.vm-mc{background:#fff;border:1px solid #E3E9E8;border-radius:20px;padding:28px 32px}
.vm-mc-h{display:flex;align-items:center;gap:14px;margin-bottom:18px}
.vm-mc-h b{width:40px;height:40px;flex:none;border-radius:10px;background:#1C8E80;color:#fff;display:grid;place-items:center;font-family:Montserrat,sans-serif;font-size:13px}
.vm-mc-h h3{font-family:Montserrat,sans-serif;font-size:20px;font-weight:700;line-height:1.15;margin:0;color:#191A1A}
.vm-mc-h small{display:block;font-size:13px;color:#5F6766;font-family:"Open Sans",sans-serif;font-weight:400}
.vm-comp{display:grid;grid-template-columns:22px 1fr;gap:2px 10px;padding:14px 0;border-top:1px solid #E3E9E8}
.vm-comp i{color:#1C8E80;padding-top:2px;font-style:normal}
.vm-comp h4{font-family:Montserrat,sans-serif;font-size:15.5px;font-weight:700;margin:0;color:#191A1A;line-height:1.3}
.vm-comp p{grid-column:2;font-size:14.5px;color:#5F6766;line-height:1.5;margin:0}
.vm-tipos{display:flex;gap:8px;flex-wrap:wrap}
.vm-tipos a{font-family:Montserrat,sans-serif;font-size:13px;font-weight:600;padding:8px 14px;border-radius:999px;border:1px solid #E3E9E8;color:#191A1A;text-decoration:none;transition:.2s}
.vm-tipos a:hover{border-color:#1C8E80;color:#1C8E80}.vm-tipos a.on{background:#1C8E80;border-color:#1C8E80;color:#fff}
@media(max-width:767px){.vm-mc{padding:22px 18px}.vm-strip span{width:38px;height:38px;font-size:12px}}
</style>';
}

add_shortcode( 'vemas_modulos', function ( $atts ) {
	$a      = shortcode_atts( array( 'parte' => 'cards' ), $atts );
	$usados = vm_modulos_usados();
	$mods   = vm_modulos();
	if ( 'strip' === $a['parte'] ) {
		$h = '<div class="vm-strip">';
		foreach ( array_keys( $mods ) as $m ) $h .= '<span class="' . ( in_array( $m, $usados, true ) ? 'on' : '' ) . '">' . $m . '</span>';
		$n = count( $usados );
		return vm_css_modulos() . $h . '</div><p class="vm-strip-t">' . $n . ' de 5 módulos en este estudio</p>';
	}
	$check = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 5 5 9-10"/></svg>';
	$comps = vm_componentes();
	$h     = '<div class="vm-mcards">';
	foreach ( $usados as $m ) {
		$h .= '<article class="vm-mc"><div class="vm-mc-h"><b>' . $m . '</b><h3>' . esc_html( $mods[ $m ][0] ) . '<small>' . esc_html( $mods[ $m ][1] ) . '</small></h3></div>';
		foreach ( $comps as $c ) {
			if ( $c['m'] !== $m ) continue;
			$h .= '<div class="vm-comp"><i>' . $check . '</i><h4>' . esc_html( $c['t'] ) . '</h4><p>' . esc_html( $c['d'] ) . '</p></div>';
		}
		$h .= '</article>';
	}
	return vm_css_modulos() . $h . '</div>';
} );

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
		case 'vm_modulos_n':
			return (string) count( vm_modulos_usados( $id ) );
		case 'vm_estudio_resumen':
			$c = vm_componentes( $id );
			if ( ! $c ) return 'Estudio de mercado';
			return 1 === count( $c ) ? 'Estudio de ' . mb_strtolower( $c[0]['t'] ) : count( $c ) . ' análisis cruzados';
		case 'vm_archivo_titulo':
			if ( is_tax( VM_TAX ) ) return 'Proyectos ' . vm_tipo_plural( get_queried_object() );
			return 'Estudios en Lima Metropolitana y provincias';
		case 'vm_tipos_nav':
			$cur = is_tax( VM_TAX ) ? get_queried_object_id() : 0;
			$h   = '<nav class="vm-tipos"><a href="' . esc_url( get_post_type_archive_link( VM_PT ) ) . '" class="' . ( $cur ? '' : 'on' ) . '">Todos</a>';
			foreach ( get_terms( array( 'taxonomy' => VM_TAX, 'hide_empty' => true ) ) as $t ) {
				$h .= '<a href="' . esc_url( get_term_link( $t ) ) . '" class="' . ( $cur === $t->term_id ? 'on' : '' ) . '">' . esc_html( $t->name ) . '</a>';
			}
			return vm_css_modulos() . $h . '</nav>';
	}
	return null;
}

function vm_tags() {
	return array( 'vm_tipo', 'vm_tipo_url', 'vm_tipo_plural', 'vm_proyecto_min', 'vm_modulos_n', 'vm_estudio_resumen', 'vm_archivo_titulo', 'vm_tipos_nav' );
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
