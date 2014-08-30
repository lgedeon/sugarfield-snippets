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
 *   Tigerridge Theme - A rapid prototyping theme. Replaces template files with a single index.html that only fires actions.
 *   OnyxEye Templates - Calls template files and parts in all the Sugarfield ways - uses ob filter to split header.php into <head> and <body>
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
 */

class Sugarfield_Snippets {

	private static $_instance;

	private $_data_sets = array();

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

	function get_snippet_data( $field = '', $return_all = false ) {
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
		                               'widget-area' => '', 'widget' => '', 'menu' => '', 'parameter' => '' ), $atts );

		if ( ! empty( $atts['blog'] ) ) {
			$atts['site'] = $atts['blog'];
		}

		$parameter = sanitize_text_field( $atts['parameter'] );

		if ( ! empty( $atts['field'] ) ) {
			return $this->get_snippet_data( $atts['field'] );

		} elseif ( ! empty( $atts['post'] ) ) {
			$post = get_post();
			return $post->{$atts['post']};

		} elseif ( ! empty( $atts['widget-area'] ) ) {
			return dynamic_sidebar( sanitize_key( $atts['widget-area'] ) );

		} elseif ( ! empty( $atts['widget'] ) ) {
			ob_start();
			the_widget( sanitize_text_field( $atts['widget'] ), $parameter );
			return ob_get_clean();

		} elseif ( ! empty( $atts['menu'] ) ) {
			$menu_args = ( json_decode( $atts['menu'], true ) ) ?: array( 'menu' => sanitize_text_field( $atts['menu'] ) );
			ob_start();
			wp_nav_menu( $menu_args );
			return ob_get_clean();

		} elseif ( ! empty( $atts['site'] ) ) {
			return get_bloginfo( $atts['site'] );

		} elseif ( ! empty( $atts['function'] ) && in_array( $atts['function'], array(
				'get_the_content',
				'get_the_date',               // todo allow a few more functions
				'get_the_author',
				) ) ) {
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


