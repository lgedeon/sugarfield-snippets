<?php
/**
 * Plugin Name: Sugarfield Snippets
 * Plugin URI:
 * Description: Snippets are similar to posts but can be included inside posts, pages, widget areas, menus, and well... literally anywhere else!
 * Author: Luke Gedeon
 * Version: 0.2-dev
 * Author URI: http://luke.gedeon.name/
 * Text Domain: sugarfield-snippets
 */

/*
 * Calls snippets from shortcodes, menus, widgets, PHP template tag, or action hooks.
 *
 * Works with:
 *   Tigerridge Theme - A rapid prototyping theme. Replaces template files with a single index.html that simply gets the content.
 *   OnyxEye Templates - Calls template files and parts in all the Sugarfield ways - uses ob filter to split header.php into <head> and <body>
 *   Widget Instance by global_1981 - Get widget with data already in it from a sidebar - http://wordpress.org/plugins/widget-instance/
 *
 */

/**
 * todo: need shortcodes to:
 *   done - include menus and widget-areas
 *   declare section widths based on responsive framework columns
 * todo: add option to create widget-areas
 * todo: call snippets from menus
 * todo: call snippets from widgets
 * todo: call snippets from PHP template tags
 * todo: call snippets from action hooks
 * todo: write a plugin dependency plugin - scoping here:
 *    should give a download url, description url, a description of what is added by using the two together
 *    should handle installing and checking for updates
 *    can monitor updates from wp.org and github
 *    relationship type: framework, feature, modifier, symbiant
 * todo: write user-side documentation
 * todo: create shortcode builder
 * todo: make cpt pages say sugarfield
 * todo: test recursion
 * todo: are there any translatable strings other than "Snippet"?
 */

class Sugarfield_Snippets {

	private static $_instance;

	private $_data_sets = array();

	private $_allowed_functions = array();

	static function get_instance() {
		if ( ! isset( self::$_instance ) ) {
			self::$_instance = new Sugarfield_Snippets();
		}

		return self::$_instance;
	}

	function __construct () {
		add_action( 'init',                      array( $this, 'init' ) );
		add_action( 'edit_form_after_title',     array( $this, 'edit_form_after_title' ) );
	}

	function init() {
		$single = 'Snippet';
		$plural = 'Snippets';
		register_post_type( 'sugarfield_snippets',
			array(
				'labels' => array(
					'name'               => $plural,
					'singular_name'      => $single,
					'menu_name'          => $plural,
					'name_admin_bar'     => $single,
					'add_new'            => 'Add New',
					'add_new_item'       => 'Add New ' . $single,
					'new_item'           => 'New ' . $single,
					'edit_item'          => 'Edit ' . $single,
					'view_item'          => 'View ' . $single,
					'all_items'          => 'All ' . $plural,
					'search_items'       => 'Search ' . $plural,
					'parent_item_colon'  => 'Parent ' . $plural .':',
					'not_found'          => 'No ' . $plural . ' found.',
					'not_found_in_trash' => 'No ' . $plural . ' found in Trash.',
				),
				'public'              => false,
				'show_ui'             => true,
//				'show_in_nav_menus'   => false,
//				'exclude_from_search' => true,
//				'publicly_queryable'  => false,
//				'has_archive'         => false,
			)
		);

		add_shortcode( 'sugarfield-get',     array( $this, 'shortcode_sugarfield_get' ) );
		add_shortcode( 'sugarfield-snippet', array( $this, 'shortcode_sugarfield_snippet' ) );

		$this->_allowed_functions = apply_filters( 'sugarfield_allowed_functions', array(
			'get_the_content',
			'get_the_date',               // todo allow a few more functions
			'get_the_author',
		) );
	}

	function edit_form_after_title( ) {
		$post = get_post();
		if ( 'sugarfield_snippets' == $post->post_type ) {
			echo "<p>Snippet slug: &nbsp; \"{$post->post_name}\" &nbsp; &mdash; &nbsp; id: {$post->ID}</p></span>";
		}
	}

	function do_snippet( $snippet, $data ) {
		// store $data for use by shortcodes or other content filters
		$this->_data_sets[] = $data;

		if ( $snippet = get_post( $snippet ) ) {
			$content = apply_filters( 'the_content', $snippet->post_content );

			array_pop( $this->_data_sets );

			return $content;
		}
	}

	function get_snippet_data( $field = '', $parent = 0, $return_all = false ) {
		$data = $this->_data_sets[ max( 0, count( $this->_data_sets ) - intval( $parent ) - 1 ) ];

		if ( $return_all || is_string( $data ) || is_numeric( $data ) ) {
			return $data;
		} elseif ( is_object( $data ) && isset( $data->{ $field } ) ) {
			return $data->{ $field };
		} elseif ( is_array( $data ) && isset( $data[ $field ] ) ) {
			return $data[ $field ];
		}
	}

	function shortcode_sugarfield_snippet( $atts, $content = '' ) {
		$data = array_diff_key( $atts, array( 'id' => '', 'slug' => '', 'query' => '' ) );
		if ( '' !== $content ) {
			$data['content'] = $content;
		}

		$atts = shortcode_atts( array( 'id' => '', 'slug' => '', 'query' => '' ), $atts );

		if ( $id = intval( $atts['id'] ) ) {
			$snippet = get_post( $id );
		} else {
			$snippet = get_page_by_path( $atts['slug'], OBJECT, 'sugarfield_snippets' );
		}

		if ( $query = $atts['query'] ) {
			$query = ( json_decode( $query, true ) ) ?: $query;
			$wp_query = new WP_Query( $query );

			if ( $wp_query->have_posts() ) {
				while ( $wp_query->have_posts() ) {
					$wp_query->the_post();
					$content .= $this->do_snippet( $snippet, $data );
				}
				wp_reset_postdata();
			}

			return $content;
		}


		return $this->do_snippet( $snippet, $data );
	}

	/*
	 * get_content
	 * intercept shortcodes of the form [sugarfield-get field="value"]
	 * Fields:
	 *   field       = get a field that was passed when calling a snippet
	 *   post        = get title, excerpt, date, etc. for the current post
	 *   site        = get blog-title, tagline, header image, etc.
	 *   blog        = alias for site
	 *   function    = return value of a function - must return a value, no echo, and (for now) not require parameters
	 *   network     = network name, url, etc. // not part of phase 1
	 *   widget-area = get a whole widget-area
	 *   menu        = get a menu (or maybe a menu location)
	 *
	 * return filtered content
	 */
	function shortcode_sugarfield_get( $atts ) {
		$atts = shortcode_atts( array( 'field' => '', 'post' => '', 'site' => '', 'blog' => '', 'function' => '',
		                               'sidebar' => '', 'widgetarea' => '', 'widget' => '', 'menu' => '',
		                               'parameter' => '' ), $atts );

		$atts['site'] = max( $atts['blog'], $atts['site'] );
		$atts['sidebar'] = max( $atts['sidebar'], $atts['widgetarea'] );

		$parameter = ( json_decode( $atts['parameter'], true ) ) ?: str_replace( "&#038;", "&", sanitize_text_field( $atts['parameter'] ) );

		if ( ! empty( $atts['field'] ) ) {
			return $this->get_snippet_data( $atts['field'] );

		} elseif ( ! empty( $atts['post'] ) ) {
			$post = get_post();
			return $post->{$atts['post']};

		} elseif ( ! empty( $atts['sidebar'] ) ) { // works with name or slug
			ob_start();
			dynamic_sidebar( $atts['sidebar'] );
			return ob_get_clean();

		} elseif ( ! empty( $atts['widget'] ) ) { // must use the widget's class name for now - todo allow title too
			// first check if this is a default widget
			$atts['widget'] = ( ( class_exists( "WP_Widget_" . $atts['widget'] ) ) ? "WP_Widget_" : "" ) . $atts['widget'];

			// if the request is not a class, maybe it is the name of a widget
			if ( ! class_exists( $atts['widget'] ) ) {
				global $wp_widget_factory;
				$atts['widget'] = key( wp_list_filter( $wp_widget_factory->widgets, array( 'name' => $atts['widget'] ) ) );
			}

			if ( class_exists( $atts['widget'] ) ) {
				ob_start();
				the_widget( $atts['widget'], $parameter );
				return ob_get_clean();
			}

		} elseif ( ! empty( $atts['menu'] ) ) {
			$menu_args = ( json_decode( $atts['menu'], true ) ) ?: array( 'menu' => sanitize_text_field( $atts['menu'] ) );
			ob_start();
			wp_nav_menu( $menu_args );
			return ob_get_clean();

		} elseif ( ! empty( $atts['site'] ) ) {
			return get_bloginfo( $atts['site'] ); // todo: test in network environment

		} elseif ( ! empty( $atts['function'] ) && in_array( $atts['function'], $this->_allowed_functions ) ) {
			return call_user_func( $atts['function'], $parameter );
		}

	}

}

Sugarfield_Snippets::get_instance();


/*
 * gets one or more snippets and returns a string of all processed snippets for each data-set passed
 */
function ss_do_snippets( $args, $data_sets = array() ) {
	$sugarfield = Sugarfield_Snippets::get_instance();
	$data_sets = ( isset( $data_sets[0] ) && ( is_array( $data_sets[0] ) ) || is_object( $data_sets[0] ) ) ? $data_sets : array( $data_sets );
	$content = '';

	if ( $snippets = $sugarfield->get_snippets( $args ) ) {
		foreach ( $data_sets as $data_set ) {
			foreach ( $snippets as $snippet ) {
				$content = $sugarfield->do_snippet( $snippet, $data_set );
			}
		}
	}

	return $content;
}


/* scrap heap ?
// Convenience wrapper for other shortcodes and widgets - not used by shortcodes in this class
function get_snippets( $args ) {
	$args['post_type'] = 'sugarfield_snippets';
	$query = new WP_Query( $args );
	return $query->query( $args );
}
*/


