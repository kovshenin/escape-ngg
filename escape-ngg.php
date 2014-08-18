<?php
/**
 * Plugin Name: Escape NextGen Gallery
 * Description: Converts NextGen Galleries to native WordPress Galleries. Read code for instructions.
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

if ( defined( 'WP_CLI' ) && WP_CLI )
	require_once( __DIR__ . '/wp-cli.php' );


/**
 *
 *
 * @package
 **/
class Escape_NextGen_Gallery {

	/**
	 * The number of images converted
	 *
	 * @var int
	 **/
	public $images_count;

	/**
	 * The number of posts converted
	 *
	 * @var int
	 **/
	public $posts_count;

	/**
	 * Any warnings encountered
	 *
	 * @var array
	 **/
	public $warnings;

	/**
	 * Information about what's happened
	 *
	 * @var array
	 **/
	public $infos;

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

		$this->images_count = 0;
		$this->posts_count = 0;
		$this->warnings = array();
		$this->infos = array();
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
		if ( ! isset( $_GET['escape_ngg_please'] ) || ! current_user_can( 'install_plugins' ) )
			return;

		$limit = -1;

		if ( isset( $_GET[ 'escape_ngg_limit' ] ) ) {
			$limit = (int) $_GET[ 'escape_ngg_limit' ];
		}

		error_reporting( E_ALL );
		ini_set( 'display_errors', 1 );
		set_time_limit( 600 );

		$post_ids = $this->get_post_ids( $limit );

		foreach ( $post_ids as $post_id ) {
			$this->process_post( $post_id );
		}

		$this->infos[] = sprintf( "Updated %d posts and %d images.", $this->posts_count, $this->images_count );

		foreach ( $this->infos as $info )
			printf( '<span style="color: #090;">%s</span><br />', esc_html( $info ) );

		foreach ( $this->warnings as $warning )
			printf( '<span style="color: #900;">%s</span><br />', esc_html( $warning ) );

		die();
	}

	// UTILITIES
	// =========

	/**
	 *
	 *
	 *
	 * @return int A count of posts with
	 * @author Simon Wheatley
	 **/
	public function count() {
		return count( $this->get_post_ids() );
	}

	/**
	 * Processes an actual post
	 *
	 * @param int $post_id The ID for a post
	 * @return void
	 * @author Simon Wheatley
	 **/
	public function process_post( $post_id ) {
		global $wpdb;
		$post = get_post( $post_id );
		$matches = null;

		if ( preg_match_all( '#nggallery id(\s)*="?(\s)*(?P<id>\d+)#i', $post->post_content, $matches, PREG_SET_ORDER ) == 0 ) {
			if ( preg_match_all ( '/<img class="ngg_displayed_gallery[\s\w]*"\s*src="http(s)?:\/\/[\w.]*\/nextgen-attach_to_post\/preview\/id--(?P<id>\d+)"/i', $post->post_content, $matches, PREG_SET_ORDER ) == 0 ) {
				$this->warnings[] = sprintf( "Could not match gallery id in %d", $post->ID );
				return;
			}
			else {
				$version = 2;
			}
		}
		else {
			$version = 1;
		}

		foreach ($matches as $match) {
			$gallery_id = $match['id'];
			if ( $version == 2 ) {
				$settings_string = $wpdb->get_var( "SELECT post_content FROM {$wpdb->prefix}posts WHERE ID =". intval( $gallery_id ) );
				$settings = $this->unserialize($settings_string);
				$old_id = $gallery_id;
				$gallery_id = $settings['container_ids'][0];
			}
			$path = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}ngg_gallery WHERE gid = ". intval( $gallery_id ), ARRAY_A);
			$images = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}ngg_pictures WHERE galleryid = ". intval( $gallery_id ) . " ORDER BY sortorder, pid ASC" );

			if ( ! $path || ! $images ) {
				$this->warnings[] = sprintf( "Could not find images for nggallery %d", $gallery_id );
				return;
			}
			$attachments_ids = array();
			foreach ( $images as $image ) {
				$url = home_url( trailingslashit( $path['path'] ) . $image->filename );
				$url = apply_filters( 'engg_image_url', $url, $path['path'], $image->filename );


				// Let's use a hash trick here to find our attachment post after it's been sideloaded.
				$hash = md5( 'attachment-hash' . $url . $image->description . time() . rand( 1, 999 ) );

				$result = media_sideload_image( $url, $post->ID, $hash );
				if ( is_wp_error( $result ) ) {
					$this->warnings[] = sprintf( "Error loading %s: %s", $url, $result->get_error_message() );
					continue;
				} else {
					$attachments = get_posts( array(
						'post_parent' => $post->ID,
						's' => $hash,
						'post_type' => 'attachment',
						'posts_per_page' => -1,
					) );

					if ( ! $attachments || ! is_array( $attachments ) || count( $attachments ) != 1 ) {
						$this->warnings[] = sprintf( "Could not insert attachment for %d", $post->ID );
						continue;
					}
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
				$attachments_ids[] = $attachment->ID;

				wp_update_post( $attachment );
				$this->images_count++;
				$this->infos[] = sprintf( "Added attachment for %d", $post->ID );
			}

			if ( 0 == $this->images_count ) {
				$this->warnings[] = sprintf( "Could not load images for nggallery %d", $gallery_id );
				return;
			}

			// Construct the [gallery] shortcode
			$attr = array();
			$attr['ids'] = implode( ',', $attachments_ids );

			$gallery = '[gallery link="file"';
			foreach ( $attr as $key => $value ) {
				$gallery .= sprintf( ' %s="%s"', esc_attr( $key ), esc_attr( $value ) );
				$gallery .= ']';
			}
			// Booyaga!
			$pristine_content = $post->post_content;
			if ( $version == 1 ) {
				$post->post_content = preg_replace( "#\[nggallery id(\s)*=\"?(\s)*($gallery_id)(\s)*\]#i", $gallery, $post->post_content );
			}
			else if ( $version == 2 ) {
				$post->post_content = preg_replace( '/<img class="ngg_displayed_gallery[\s\w]*"\s*src="http(s)?:\/\/[\w.]*\/nextgen-attach_to_post\/preview\/id--' . $old_id . '"[^>]*>/i', $gallery, $post->post_content );
			}
			$post->post_content = apply_filters( 'engg_post_content', $post->post_content, $pristine_content, $attr, $post, $gallery );
			wp_update_post( $post );
			$this->infos[] = sprintf( "Updated post %d", $post->ID );
		}

			$this->posts_count++;
	}
	/**
	 * @param int $limit How many posts to get
	 *
	 * @return void
	 **/
	public function get_post_ids( $limit = -1 ) {
		// Nextgen galleries on the 1.x branch.
		$args = array(
			's'           => '[nggallery',
			'post_type'   => array( 'post', 'page' ),
			'post_status' => 'any',
			'nopaging'    => true,
			'fields'      => 'ids',
			'posts_per_page' => $limit
		);

		$args = apply_filters( 'escape_ngg_query_args', $args );

		$query = new WP_Query( $args );
		$posts = (array)$query->posts;

		// Nextgen galleries on the 2.x branch.
		$args = array(
			's'           => 'ngg_displayed_gallery',
			'post_type'   => array( 'post', 'page' ),
			'post_status' => 'any',
			'nopaging'    => true,
			'fields'      => 'ids',
			'posts_per_page' => $limit
		);

		$args = apply_filters( 'escape_ngg_query_args', $args );

		$query = new WP_Query( $args );
		$posts = $posts + $query->posts;

		return $posts;
	}
	
	/* This function taken from Nextgen Gallery source code to handle
	 * the 'serialization' format they use.
	 */
	private function unserialize($value)
	{
		$retval = NULL;

		if (is_string($value))
		{
			$retval = stripcslashes($value);

			if (strlen($value) > 1)
			{
				//Using json_decode here because PHP's unserialize is not Unicode safe
				$retval = json_decode(base64_decode($retval), TRUE);

				// JSON Decoding failed. Perhaps it's PHP serialized data?
				if ($retval === NULL) {
					$er = error_reporting(0);
					$retval = unserialize($value);
					error_reporting($er);
				}
			}
		}

	return $retval;
	}

}

// Initiate the singleton
Escape_NextGen_Gallery::init();
