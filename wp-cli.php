<?php

/**
 * Commands to help you escape NextGen Gallery
 * 
 */
class ENGG_Command extends WP_CLI_Command {

	public function __construct() {

	}

	/**
	 * Count the number of posts with NextGen Gallery Shortcodes in this site.
	 *
	 * ## OPTIONS
	 *
	 * ## EXAMPLES
	 *
	 * wp escape-ngg count
	 * 
	 */
	public function count( $args, $assoc_args ) {
		WP_CLI::log( Escape_NextGen_Gallery::init()->count() );
	}

	/**
	 * Convert the NextGen Gallery Shortcodes in posts in this site into WordPress gallery shortcodes.
	 *
	 * ## OPTIONS
	 *
	 * ## EXAMPLES
	 *
	 * wp escape-ngg convert
	 * 
	 */
	public function convert( $args, $assoc_args ) {

	}

}

WP_CLI::add_command( 'escape-ngg', 'ENGG_Command' );
