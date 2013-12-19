<?php
/**
 * Plugin Name: Escape NextGen Gallery
 * Plugin Description: Converts NextGen Galleries to native WordPress Galleries. Read code for instructions.
 * Author: Konstantin Kovshenin
 * License: GPLv3
 * Version: 1.0
 *
 * This plugin will scan through all your posts and pages for the [nggallery] shortcode. 
 * It will loop through all images associated with that gallery and recreate them as native 
 * WordPress attachments instead. Finally it will replace the [nggallery] shortcode with 
 * the [gallery] shortcode native to WordPress.
 *
 * Instructions: Backup! Activate the plugin and browse to yourdomain.com/wp-admin/?escape_ngg_please=1
 * When you're done you can delete the gallery dir, and the wp_ngg_* tables in your database. Keep the backups though.
 *
 * Limitations: 
 * - doesn't work with shortcodes other than [nggallery]
 * - doesn't work when more than one gallery on page
 *
 * @uses media_sideload_image to recreate your attachment posts
 */

add_action( 'admin_init', function() {

/**
 * 
 * 
 * @package 
 **/
class Escape_NextGen_Gallery {

	/**
	 * A version integer.
	 *
	 * @var int
	 **/
	var $version;

	/**
	 * Singleton stuff.
	 * 
	 * @access @static
	 * 
	 * @return Escape_NextGen_Gallery object
	 */
	static public function init() {
		static $instance = false;

		if ( ! $instance )
			$instance = new Escape_NextGen_Gallery;

		return $instance;

	}

	/**
	 * Class constructor
	 *
	 * @return null
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'action_admin_init' ) );

		$this->version = 1;
	}

	// HOOKS
	// =====

	/**
	 * Hooks the WP action admin_init
	 *
	 * @action admin_init
	 *
	 * @return void
	 **/
	public function action_admin_init() {
	global $post, $wpdb;

	if ( ! isset( $_GET['escape_ngg_please'] ) || ! current_user_can( 'install_plugins' ) )
		return;

	error_reporting( E_ALL );
	ini_set( 'display_errors', 1 );
	set_time_limit( 600 );

	$uploads = wp_upload_dir();
	$baseurl = $uploads['baseurl'];
	$count = array(
		'posts' => 0,
		'images' => 0,
	);

	$query = array(
		's' => '[nggallery',
		'post_type' => array( 'post', 'page' ),
		'post_status' => 'any',
		'posts_per_page' => 50,
		'offset' => 0,
	);


	while ( $posts = get_posts( $query ) ) {
		foreach ( $posts as $post ) {
			$query['offset']++;
			$matches = null;

			preg_match( '#nggallery id(\s)*="?(\s)*(?P<id>\d+)#i', $post->post_content, $matches );
			if ( ! isset( $matches['id'] ) ) {
				printf( "Could not match gallery id in %d<br />", $post->ID );
				continue;
			}

			// If there are existing images attached the post, 
			// let's remember to exclude them from our new gallery.
			$existing_attachments_ids = get_posts( array(
				'post_type' => 'attachment',
				'post_status' => 'inherit',
				'post_parent' => $post->ID,
				'post_mime_type' => 'image',
				'fields' => 'ids',
			) );

			$gallery_id = $matches['id'];
			$path = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}ngg_gallery WHERE gid = ". intval( $gallery_id ), ARRAY_A  );
			$images = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}ngg_pictures WHERE galleryid = ". intval( $gallery_id ) . " ORDER BY sortorder, pid ASC" );

			if ( ! $path || ! $images ) {
				printf( "Could not find images for nggallery %d<br />", $gallery_id );
				continue;
			}

			foreach ( $images as $image ) {
				$url = home_url( trailingslashit( $path['path'] ) . $image->filename );
				$url = apply_filters( 'engg_image_url', $url, $path['path'], $image->filename );
				

				// Let's use a hash trick here to find our attachment post after it's been sideloaded.
				$hash = md5( 'attachment-hash' . $url . $image->description . time() . rand( 1, 999 ) );

				media_sideload_image( $url, $post->ID, $hash );
				$attachments = get_posts( array(
					'post_parent' => $post->ID,
					's' => $hash,
					'post_type' => 'attachment',
					'posts_per_page' => -1,
				) );

				if ( ! $attachments || ! is_array( $attachments ) || count( $attachments ) != 1 ) {
					printf( "Could not insert attachment for %d<br />", $post->ID );
					continue;
				}

				// Titles should fallback to the filename.
				if ( ! trim( $image->alttext ) ) {
					$image->alttext = $image->filename;
				}

				$attachment = $attachments[0];
				$attachment->post_title = $image->alttext;
				$attachment->post_content = $image->description;
				$attachment->menu_order = $image->sortorder;

				update_post_meta( $attachment->ID, '_wp_attachment_image_alt', $image->alttext );

				wp_update_post( $attachment );
				$count['images']++;
				printf( "Added attachment for %d<br />", $post->ID );
			}

			// Construct the [gallery] shortcode
			$attr = array();
			if ( $existing_attachments_ids )
				$attr['exclude'] = implode( ',', $existing_attachments_ids );

			$gallery = '[gallery';
			foreach ( $attr as $key => $value )
				$gallery .= sprintf( ' %s="%s"', esc_attr( $key ), esc_attr( $value ) );
			$gallery .= ']';

			// Booyaga!
			$pristine_content = $post->post_content;
			$post->post_content = preg_replace( '#\[nggallery[^\]]*\]#i', $gallery, $post->post_content );
			$post->post_content = apply_filters( 'engg_post_content', $post->post_content, $pristine_content, $attr, $post, $gallery );
			wp_update_post( $post );
			$query['offset']--; // Since this post will no longer contain the [nggallery] it won't count against our offset
			$count['posts']++;
			printf( "Updated post %d<br />", $post->ID );
		}
	}

	printf( "Updated %d posts with %d images.", $count['posts'], $count['images'] );
	die();
	}

	// CALLBACKS
	// =========

	// UTILITIES
	// =========

}

// Initiate the singleton
Escape_NextGen_Gallery::init();
