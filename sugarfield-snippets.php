<?php
/**
 * Plugin Name: Sugarfield Snippets
 * Plugin URI:
 * Description: Snippets are similar to posts but can be included inside posts, pages, widget areas, menus, and well... literally anywhere else!
 * Author: Luke Gedeon
 * Version: 0.3-dev
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
 * todo: write a plugin dependency plugin - scoping here:
 *    should give a download url, description url, a description of what is added by using the two together
 *    should handle installing and checking for updates
 *    can monitor updates from wp.org and github
 *    relationship type: framework, feature, modifier, symbiont
 * todo: write user-side documentation
 * todo: create shortcode builder
 * todo: test recursion
 * todo: are there any translatable strings other than "Snippet"?
 *
 * Other shortcodes we might want later: related posts/category/recent posts, site/network/post info, template files,
 * widget areas, menu (locations),
 *
 * Interesting thought, maybe we could grab fields from other blogs on the network or from the network itself,
 * but that might be a different plugin
 *
 * also output a list of categories or tags with a template defining how that looks
 *
 */

class Sugarfield_Snippets {

	const POST_TYPE = 'snippet';

	private $_single_functions = array(
		'function'            => '',
		'widget_area'         => '',
		'sidebar'             => '',
		'menu'                => '',
		'menu_location'       => '',
		'site'                => '',
		'blog'                => '',
		// parameters is not a function of course, but this really just whitelists the fields we accept.
		'parameters'           => '',
		// enqueue css or js files
		'enqueue'             => '',
		// save snippet content as css or js file
		'save_as'             => '',
	);

	private $_field_functions = array(
		'post'                 => array(
			'title'                => 'get_the_title',
			'post_class'           => 'post_class',
			'categories'           => 'the_category',
			'content'              => array( __CLASS__, 'get_filtered_content') ,
			'excerpt'              => 'get_the_excerpt',
			'ID'                   => 'get_the_ID',
			'tags'                 => 'get_the_tag_list',
			'image'                => 'wp_get_attachment_image',
			'date'                 => 'get_the_date',
			'time'                 => 'get_the_time',
			'author'               => 'get_the_author',
			'shortlink'            => 'wp_get_shortlink',
			'taxonomy'             => array( __CLASS__, 'get_the_terms'),
		),
		'site'                => array(),
		'body_class'          => 'body_class',
	);

	private $_field_parameters = array(
		'post'                 => array(
			'taxonomy'             => 'get_taxonomy',
		),
	);

	private $_query_atts = array(
		'p'                   => '',
		'slug'                => '',
		'pagename'            => '',
		'post_type'           => '',
		'query'               => '',
		'template_snippet'    => '',
		'template_part'       => '',
		'template_name'       => '',
	);

	// Basic structure while I am thinking of it, but intended for a future release... undoc'd feature for now :)
	private $_allowed_functions = array(
		'get_the_content',
		'get_the_date',               // todo allow a few more functions
		'get_the_author',
	);

	private static $_instance;

	static function get_instance() {
		if ( ! isset( self::$_instance ) ) {
			self::$_instance = new Sugarfield_Snippets();
		}

		return self::$_instance;
	}

	/*
	 * test
	 */
	function __construct () {
		add_action( 'init',                           array( $this, 'init' ) );
		add_action( 'edit_form_after_title',          array( $this, 'edit_form_after_title' ) );
		add_filter( 'sugarfield_template_processor',  array( $this, 'filter__sugarfield_template_processor' ), 10, 2 );
		add_filter( 'sugarfield_parameter_processor', array( $this, 'filter__sugarfield_parameter_processor' ), 10, 2 );
		add_action( 'save_post',                      array( $this, 'action__save_post' ), 10, 2 );
	}

	function init() {

		$single = 'Snippet';
		$plural = 'Snippets';
		register_post_type( self::POST_TYPE,
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
//				'show_in_nav_menus'   => true,
//				'exclude_from_search' => true,
//				'publicly_queryable'  => false,
//				'has_archive'         => false,
			)
		);

		add_shortcode( 'sugarfield', array( $this, 'shortcode__sugarfield' ) );
		
		// initialize dynamic menu locations and widget areas
		// todo add button to regenerate list of locations and areas
		$option = get_option( 'sugarfield_snippets', array() );

		if ( isset( $option['generated_menus'] ) ) {
			foreach ( $option['generated_menus'] as $menu ) {
				register_nav_menu( $menu, $menu . ' <small><i>(automatically generated by Sugarfield)</i></small>' );
			}
		}

		if ( isset( $option['generated_sidebars'] ) ) {
			foreach ( $option['generated_sidebars'] as $sidebar ) {
				register_sidebar( $sidebar );
			}
		}
	}

	function edit_form_after_title( ) {
		$post = get_post();
		if ( self::POST_TYPE == $post->post_type ) {
			echo "<p>Snippet slug: &nbsp; \"{$post->post_name}\" &nbsp; &mdash; &nbsp; id: {$post->ID}</p></span>";
		}
	}


	function shortcode__sugarfield( $atts, $content = '' ) {
		if ( isset( $atts['field'] ) ) {
			return $this->do_field( $atts['field'] );
		}

		$single_function = array_intersect_key( $atts, $this->_single_functions );

		if ( count( $single_function ) ) {
			return $this->do_function( $single_function );
		}

		ob_start();


		// Ignore any values we don't expect and prevent missing array keys.
		$atts = shortcode_atts( $this->_query_atts, $atts );

		// "pagename" and "slug" are synonyms. The first is used in WP_Query. The second is used in the UI.
		if ( '' === $atts['slug'] ) {
			$atts['slug'] = $atts['pagename'];
		}

		$post_type = post_type_exists( $atts['post_type'] ) ? $atts['post_type'] : self::POST_TYPE;

		// If nothing else we have the main query... or maybe nothing.
		$snippets = isset( $GLOBALS['wp_query']->posts ) ? $GLOBALS['wp_query']->posts : array();

		// Get the content we will be using.
		if ( intval( $atts['p'] ) ) {
			$snippets = array( get_post( intval( $atts['p'] ) ) );
		} elseif ( '' !== $atts['slug'] ) {
			$snippets = array( get_page_by_path( $atts['slug'], OBJECT, $post_type ) );
		} elseif ( intval( $atts['query'] ) && $query = get_post( intval( $atts['query'] ) ) ) {
			$snippets = $this->parse_query_in_post( $query );
		} elseif ( $query = get_page_by_path( $atts['query'], OBJECT, $post_type ) ) {
			$snippets = $this->parse_query_in_post( $query );
		} // todo: handle a few more options... maybe a tax query

		// Now render all the things
		foreach ( $snippets as $snippet ) {
			$this->render_template( $atts, $snippet );
		}

		wp_reset_postdata();

		return ob_get_clean();
	}

	//pseudo code
	function parse_query_in_post ( $query_post ) {
		$content = $query_post->content;
		$query = ( json_decode( $content, true ) ) ?: json_decode( $content, true );
		$wp_query = new WP_Query( $query );
		return $wp_query->posts;
	}

	// expects pre-processed $atts array
	function render_template ( $atts, $snippet ) {
		global $post;

		// todo this can lead to an inifinite loop if $snippet === $post already
		$post = $snippet;
		setup_postdata( $post );

		if ( "" !== $atts['template_part'] ) {
			get_template_part( $atts['template_part'], $atts['template_name'] );
			return true;
		}

		if ( intval( $atts['template_snippet'] ) ) {
			$template = get_post( intval( $atts['template_snippet'] ) );
		} elseif ( '' !== $atts['template_snippet'] ) {
			$template = get_page_by_path( $atts['template_snippet'], OBJECT, self::POST_TYPE );
		} else {
			$template = null;
		}

		if ( $template ) {
			/*
			 * Use this filter to apply twig or other processors to expand or replace the_content() using
			 * the template chosen by the user. The default processor is also hooked here so it can be removed.
			 */
			echo apply_filters( 'sugarfield_template_processor', get_the_content(), $template->post_content );
		} else {
			the_content();
		}
	}

	/*
	 * By default we ignore the content and just process the template for shortcodes that can pull in the content
	 * if needed. Other template processors may need the content, so it is available if needed.
	 */
	function filter__sugarfield_template_processor ( $content, $template ) {
		return do_shortcode( $template );
	}

	function filter__sugarfield_parameter_processor ( $parameters ) {
		return ( json_decode( $parameters, true ) ) ?: str_replace( "&#038;", "&", sanitize_text_field( $parameters ) );
	}


	function do_field( $field ) {
		$function = $this->_field_functions;
		$parameters = array();
		$field = explode( '.', $field );

		foreach ( $field as $key => $f ) {
			/*
			 * If $function contains a callable of the form array( __CLASS__, 'method' ) and our field request has a
			 * parameter of 0 or 1 then $function[$f] exists. So we need to check if $function is callable before
			 * drilling deeper. We don't have to worry about it being callable too soon either because
			 * array('title' => 'get_the_title') isn't callable.
			 */
			if ( isset( $function[$f] ) && ! is_callable( $function ) ) {
				$function = $function[$f];

			// Now if $function is callable, then anything left in field is assumed to be parameters.
			} elseif ( is_callable( $function ) ) {
				$parameters = array_map( 'html_entity_decode', array_slice( $field, $key ) );
				break;

			// And if we can't find a match, there must have been a typo somewhere. Not much we can do about that.
			} else {
				return false;
			}
		}

		if ( is_callable( $function ) && count( $parameters ) >= helper_count_required_args( $function ) ) {
			return call_user_func_array( $function, $parameters );
		}


	}

	/*
	 * Fields:
	 *   post          = get title, excerpt, date, etc. for the current post
	 *   site          = get blog-title, tagline, header image, etc.
	 *   blog          = alias for site
	 *   function      = return value of a function - must return a value, no echo, and (for now) not require parameters
	 *   network       = network name, url, etc. // not part of phase 1
	 *   widget_area   = get a whole widget area
	 *   menu          = get a menu
	 *   menu_location = get a menu location
	 *
	 * @return string
	 */
	function do_function( $atts ) {

		$atts = shortcode_atts( $this->_single_functions, $atts );

		$atts['site'] = max( $atts['blog'], $atts['site'] );
		$atts['widget_area'] = max( $atts['sidebar'], $atts['widget_area'] );

		$parameters = apply_filters( 'sugarfield_parameter_processor', $atts['parameters'] );

		if ( ! empty( $atts['widget_area'] ) ) { // works with name or slug
			ob_start();
			$this->maybe_register_widget_area( $atts['widget_area'], $parameters );
			dynamic_sidebar( sanitize_key( $atts['widget_area'] ) );

			return ob_get_clean();

		} elseif ( ! empty( $atts['menu_location'] ) ) {
			ob_start();
			$this->maybe_register_menu_location( $atts['menu_location'] );
			$parameters['theme_location'] = $atts['menu_location'];
			wp_nav_menu( $parameters );
			return ob_get_clean();

		} elseif ( ! empty( $atts['widget'] ) ) { // must use the widget's class name for now - todo allow title too
			// first check if this is a default widget
			if ( class_exists( "WP_Widget_" . $atts['widget'] ) ) {
				$atts['widget'] = "WP_Widget_" . $atts['widget'];
			}

			// if the request is not a class, maybe it is the name of a widget
			if ( ! class_exists( $atts['widget'] ) ) {
				global $wp_widget_factory;
				$atts['widget'] = key( wp_list_filter( $wp_widget_factory->widgets, array( 'name' => $atts['widget'] ) ) );
			}

			if ( class_exists( $atts['widget'] ) ) {
				ob_start();
				the_widget( $atts['widget'], $parameters );

				return ob_get_clean();
			}

		// usage [sugarfield enqueue="style.css"]
		} elseif ( ! empty( $atts['enqueue'] ) ) {
			$filename = sanitize_file_name( $atts['enqueue'] );
			if ( '.css' == substr( $filename, -4 ) ) {
				wp_enqueue_style( sanitize_key( $filename ), $this->get_snippets_dir( 'url' ) . $filename );
			} elseif ( '.js' == substr( $filename, -3 ) ) {
				wp_enqueue_script( sanitize_key( $filename ), $this->get_snippets_dir( 'url' ) . $filename );
			}

		// usage [sugarfield save_as="style.css"] -- actually, could turn this into a field in the editor instead
		} elseif ( ! empty( $atts['save_as'] ) ) {
			$this->output_snippet_as_file( get_the_ID(), sanitize_file_name( $atts['save_as'] ) );


		} elseif ( ! empty( $atts['menu'] ) ) {
			ob_start();
			$parameters['menu'] = $atts['menu']; // Accepts (matching in order) id, slug, name.
			wp_nav_menu( $parameters );
			return ob_get_clean();

		} elseif ( ! empty( $atts['site'] ) ) {
			return get_bloginfo( $atts['site'] ); // todo: test in network environment

		} elseif ( ! empty( $atts['function'] ) && in_array( $atts['function'], $this->_allowed_functions ) ) {
			return call_user_func( $atts['function'], $parameters );
		}

	}

	function get_filtered_content( $more_link_text = null, $strip_teaser = false ) {
		$content = get_the_content( $more_link_text, $strip_teaser );

		/**
		 * Apply same filter on the post content that is used in the_content().
		 *
		 * @param string $content Content of the current post.
		 */
		$content = apply_filters( 'the_content', $content );
		$content = str_replace( ']]>', ']]&gt;', $content );
		return $content;
	}

	function get_the_terms( $taxonomy, $before = '', $sep = ' ', $after = '' ) {
		if ( taxonomy_exists( $taxonomy ) ) {
			return get_the_term_list( get_the_ID(), $taxonomy, $before, $sep, $after );
		}
	}

	function maybe_register_widget_area( $widget_area, $args = array() ) {
		global $wp_registered_sidebars;

		$defaults = array(
			'name'        => $widget_area,
			'id'          => sanitize_key( $widget_area ),
			'description' => 'Automatically generated by Sugarfield Snippets',
		);

		$args = wp_parse_args( $args, $defaults );

		if ( ! isset( $wp_registered_sidebars[ $args['id'] ] ) ) {
			register_sidebar( $args );
			$option = get_option( 'sugarfield_snippets', array() );
			$option['generated_sidebars'][ $args['id'] ] = $args;
			update_option( 'sugarfield_snippets', $option );
		}
	}

	function maybe_register_menu_location( $location ) {
		global $_wp_registered_nav_menus;

		if ( ! isset( $_wp_registered_nav_menus[ $location ] ) ) {
			register_nav_menu( $location, $location . ' <small><i>(automatically generated by Sugarfield)</i></small>' );
			$option = get_option( 'sugarfield_snippets', array() );
			$option['generated_menus'][ $location ] = $location;
			update_option( 'sugarfield_snippets', $option );
		}
	}

	function output_snippet_as_file( $snippet_id, $filename ) {
		require_once( ABSPATH . 'wp-admin/includes/file.php' );
		global $wp_filesystem;

		// use a static variable to stop recursion
		static $doing_output = false;

		if ( $doing_output || ! intval( $snippet_id ) ) {
			return '';
		}

		$doing_output = true;

		/*
		 * Run the content through the shortcode processor to eliminate the save_as shortcode and take advantage
		 * of any variables. //todo before launch sanitize the content
		 */
		$post = get_post( intval( $snippet_id ) );
		$file_content = do_shortcode( $post->post_content );

		$upload_dir = $this->get_snippets_dir();

		WP_Filesystem();
		$wp_filesystem->mkdir( $upload_dir );
		$wp_filesystem->put_contents( $upload_dir . $filename, $file_content, 0644 );

	}

	// pass 'url' to get url format
	function get_snippets_dir( $format = 'dir' ) {
		if ( in_array( $format, array( 'dir', 'url' ) ) ) {
			$upload_dir = wp_upload_dir();
			return trailingslashit( $upload_dir[ 'base' . $format ] ) . 'snippet_cache/';
		}
	}

	function action__save_post( $post_id, $post ) {
		// refresh cache by processing shortcodes - we might even do a full content cache here someday
		do_shortcode( $post->post_content );
	}
}

Sugarfield_Snippets::get_instance();


// A big cat drove even frightened green heroes into jumping killers like many new or pretty quiet red singers that understood what xavier yelled


// todo store as an option and address all concerns at https://core.trac.wordpress.org/ticket/14671
function helper_count_required_args ( $function ) {
	static $arg_counts = array();

	$key = is_scalar( $function ) ? $function : serialize( $function );
	
	if ( isset( $arg_counts[$key] ) ) {
		return $arg_counts[$key];
	}
		
	if ( is_string( $function ) && function_exists( $function ) ) {
		$r = new ReflectionFunction( $function );
	} elseif ( isset( $function[0], $function[1] ) && method_exists( $function[0], $function[1] ) ) {
		$r = new ReflectionMethod( $function[0], $function[1] );
	} else {
		return $arg_counts[$key] = false;
	}

	return $arg_counts[$key] = $r->getNumberOfRequiredParameters();
}

function helper_die_on_nth( $n ) {
	static $i = 1;
	if ( $i == $n ) {
		echo "n = $n"; die();
	}
	$i++;
}
