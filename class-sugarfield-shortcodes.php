<?php
/**
 * The basic shortcode to put a snippet and it's fields on the page is handled by this plugin's core code, but this
 * class adds other shortcodes like related posts/category/recent posts, site/network/post info, template files,
 * widget areas, menu (locations),
 */

class Sugarfield_Shortcodes {

	function __construct() {
		add_action( 'init', array( $this, 'init' ) );
	}

	function init() {
		add_shortcode( 'thefirst', 'shortcode_thefirst' );
	}

	function shortcode_thefirst( $atts, $content = null ) {
		$atts = shortcode_atts( array(
			'posts' => 1,
		), $atts );
	}
}


/*
 * old notes
 *
 * intercept shortcodes of the form [sugarfield-snippet field="value"]
 * Fields:
 *   site-field    = get blog-title, tagline, header image, etc.
 *   blog-field    = alias for site-field
 *   network-field = network name, url, etc.
 *   post-field    = get title, excerpt, date, etc.
 *   widget-area   = get a whole widget-area
 *   menu          = get a menu (or maybe a menu location)
 *
 */