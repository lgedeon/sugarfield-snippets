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
 *   Tigerridge Theme - A rapid prototyping theme. Replaces template files a single index.html that only fires actions.
 *   OnyxEye Templates - Calls template files and parts all the Sugarfield ways - uses ob filter to split header.php into <head> and <body>
 *
 */

/**
 * todo: need shortcodes to:
 *   done - handle looping
 *   done - handle template tags like the_title, the_post, the_content, site_title, tagline, etc.
 *   include menus and widget-areas
 *   declare section widths based on responsive framework columns
 * todo: add option to create widget-areas
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

		add_shortcode( 'snippet-data',       array( $this, 'shortcode_snippet_data' ) );
		add_shortcode( 'snippet-post',       array( $this, 'shortcode_snippet_post' ) );
		add_shortcode( 'snippet-site',       array( $this, 'shortcode_snippet_site' ) );
		add_shortcode( 'sugarfield-snippet', array( $this, 'shortcode_sugarfield_snippet' ) );
	}

	function edit_form_after_title( ) {
		$post = get_post();
		if ( 'sugarfield_snippets' == $post->post_type ) {
			echo "<p>Snippet slug: &nbsp; \"{$post->post_name}\" &nbsp; &mdash; &nbsp; id: {$post->ID}</p></span>";
		}
	}

	// Convenience wrapper for other shortcodes and widgets - not used by shortcodes in this class
	function get_snippets( $args ) {
		$args['post_type'] = 'sugarfield_snippets';
		$query = new WP_Query( $args );
		return $query->query( $args );
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

	function shortcode_snippet_data( $atts ) {
		$atts = shortcode_atts( array( 'field' => '', 'parent' => '0' ), $atts );

		return $this->get_snippet_data( $atts['field'], $atts['parent'] );
	}

	function shortcode_snippet_post( $atts ) {
		$atts = shortcode_atts( array( 'field' => '' ), $atts );

		$post = get_post();

		if ( in_array( $atts['field'], array( 'get_the_content' ) ) ) { //todo allow a few more functions - must return its value, no echo
			return call_user_func( $atts['field'] );
		} elseif ( isset( $post->{$atts['field']} ) ) {
			return $post->{$atts['field']};
		}

	}

	function shortcode_snippet_site( $atts ) {
		$atts = shortcode_atts( array( 'field' => '' ), $atts );

//		if ( in_array( $atts['field'], array( 'get_the_content' ) ) ) {
			return get_bloginfo( $atts['field'] );
//		}

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

/*
 * get_content
 * intercept shortcodes of the form [sugarfield-get field="value"]
 * Fields:
 *   title         = get a snippet by title
 *   site-field    = get blog-title, tagline, header image, etc.
 *   blog-field    = alias for site-field
 *   network-field = network name, url, etc.
 *   post-field    = get title, excerpt, date, etc.
 *   widget-area   = get a whole widget-area
 *   menu          = get a menu (or maybe a menu location)
 *
 * return filtered content
 */




