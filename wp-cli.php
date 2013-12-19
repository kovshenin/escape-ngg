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
	public function count() {
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
	public function convert() {
		WP_CLI::log( sprintf( 'Processing %d posts with NextGen Gallery shortcodes', Escape_NextGen_Gallery::init()->count() ) );
		set_time_limit( 0 );

		$uploads = wp_upload_dir();
		$baseurl = $uploads['baseurl'];

		$post_ids = Escape_NextGen_Gallery::init()->get_post_ids();
		
		foreach ( $post_ids as $post_id ) {
			Escape_NextGen_Gallery::init()->process_post( $post_id );
			break;
		}

		foreach ( Escape_NextGen_Gallery::init()->infos as $info )
			WP_CLI::log( $info );

		foreach ( Escape_NextGen_Gallery::init()->warnings as $warning )
			WP_CLI::warning( $warning );


		$lines = array(
			(object) array( 'Converted' => 'posts converted', 'Count' => Escape_NextGen_Gallery::init()->posts_count ),
			(object) array( 'Converted' => 'images converted', 'Count' => Escape_NextGen_Gallery::init()->images_count ),
		);
		$fields = array( 'Converted', 'Count' );
		\WP_CLI\Utils\format_items( 'table', $lines, $fields );
	}

}

WP_CLI::add_command( 'escape-ngg', 'ENGG_Command' );
