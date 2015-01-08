<?php
/**
 * Plugin Name: Escape NextGen Gallery
 * Description: Converts NextGen Galleries to native WordPress Galleries. Read code for instructions.
 * Author: Konstantin Kovshenin
 * License: GPLv3
 * Version: 1.1
 *
 * This plugin will scan through all your posts and pages for the [nggallery] and [singlepic] shortcode. 
 * It will loop through all images associated with that gallery and recreate them as native 
 * WordPress attachments instead. Finally it will replace the [nggallery] shortcode with 
 * the [gallery] shortcode native to WordPress.
 *
 * Instructions: Backup! Activate the plugin and browse to yourdomain.com/wp-admin/?escape_ngg_please=1
 * When you're done you can delete the gallery dir, and the wp_ngg_* tables in your database. Keep the backups though.
 *
 * Limitations: 
 * - doesn't work with shortcodes other than [nggallery] and [singlepic]
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
   * Relation of NG picture IDs to media library picture IDs
   * to avoid importing double pictures
   *
   * Format: array( <ng_pid> => <wp_pid> );
   * @var array
   **/ 
  public $pid_dict;


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
    
		// If there are existing images attached the post, 
		// let's remember to exclude them from our new gallery.
		$existing_attachments_ids = get_posts( array(
			'post_type' => 'attachment',
			'post_status' => 'inherit',
			'post_parent' => $post->ID,
			'post_mime_type' => 'image',
			'fields' => 'ids',
		) );

		$pristine_content = $post->post_content;

    $post->post_content = preg_replace_callback( '/\[nggallery id(\s)*="?(\s)*(?P<id>\d+\s*)\]/i', 
                           function($matches) use($post, $existing_attachments_ids) { return $this->replace_nggallery($post->ID, $matches, $existing_attachments_ids); }, 
                           $post->post_content );
    
    $post->post_content = preg_replace_callback( '/\[singlepic id=(?P<id>\d+)\s+w=(?P<width>\d*)\s+h=(?P<height>\d*)\s+float=(?P<float>left|right|center|none)\s*\]/i', 
                           function($matches) use($post, $existing_attachments_ids) { return $this->replace_singlepic($post->ID, $matches, $existing_attachments_ids); }, 
                           $post->post_content );

		wp_update_post( $post );
		$this->posts_count++;
		$this->infos[] = sprintf( "Updated post %d", $post->ID );	
  }

  private function replace_nggallery($post_id, $matches, $existing_attachments_ids) {

		global $wpdb;

		if ( ! isset( $matches['id'] ) ) {
			$this->warnings[] = sprintf( "Could not match gallery id in %d", $post_id );
			return;
		}


		$gallery_id = $matches['id'];
		$path = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}ngg_gallery WHERE gid = ". intval( $gallery_id ), ARRAY_A  );
		$images = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}ngg_pictures WHERE galleryid = ". intval( $gallery_id ) . " ORDER BY sortorder, pid ASC" );

		if ( ! $path || ! $images ) {
			$this->warnings[] = sprintf( "Could not find images for nggallery %d", $gallery_id );
			return;
		}

		foreach ( $images as $image ) {

      $this->attach_image($post_id, $path, $image, true);

		}

		if ( 0 == $this->images_count ) {
			$this->warnings[] = sprintf( "Could not load images for nggallery %d", $gallery_id );
			return;
		}

		// Construct the [gallery] shortcode
		$attr = array();
		if ( $existing_attachments_ids )
			$attr['exclude'] = implode( ',', $existing_attachments_ids );

		$gallery = '[gallery';
		foreach ( $attr as $key => $value )
			$gallery .= sprintf( ' %s="%s"', esc_attr( $key ), esc_attr( $value ) );
		$gallery .= ']';

    return $gallery;
  }

  private function replace_singlepic($post_id, $matches) {

		global $wpdb;

		if ( ! isset( $matches['id'] ) ) {
			$this->warnings[] = sprintf( "Could not match pic id in %d", $post_id );
			return;
		}

		$picture_id = $matches['id'];
		$image = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}ngg_pictures WHERE pid = ". intval( $picture_id ) );
		if ( ! $image ) {
			$this->warnings[] = sprintf( "Could not find image for id %d", $picture_id );
			return;
		}
		$path  = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}ngg_gallery  WHERE gid = ". intval( $image->galleryid ), ARRAY_A  );

		if ( ! $path ) {
			$this->warnings[] = sprintf( "Could not find image for id %d", $picture_id );
			return;
		}

    $attachment = $this->attach_image($post_id, $path, $image, false);

		// Construct the replacement code

    $full_url   = wp_get_attachment_image_src( $attachment->ID, "full" )[0];
    $medium_url = wp_get_attachment_image_src( $attachment->ID, "medium" )[0];

    switch($matches['float']) {

       case 'center': 
         $align = 'aligncenter';
         break;

       case 'right': 
         $align = 'alignright';
         break;

       case 'left': 
       default:
         $align = 'alignleft';
    }

    $width = $matches['width'];
    $height = $matches['height'];

    $alt = $attachment->post_excerpt;
    $img_tag = '<a href="'.$full_url.'"><img src="'.$medium_url.'" alt="'.$alt.'" height="'.$height.'" class="'.$align.' size-medium" /></a>';

    return $img_tag;
  }


  /* when inserting gallery, we need to force a new attachment because wordpress
   * only allows attaching an image to a single post.
   * when inserting singlepic, we can safely refer to an already existing attachment 
   * and thus avoid duplicates
   */
  private function attach_image($post_id, $path, $image, $force = false) {

    if(!$force && isset($this->pid_dict[$image->pid])) {

      return get_post($this->pid_dict[$image->pid]);
    }

    $url = home_url( trailingslashit( $path['path'] ) . $image->filename );
    $url = apply_filters( 'engg_image_url', $url, $path['path'], $image->filename );


    // Let's use a hash trick here to find our attachment post after it's been sideloaded.
    $hash = md5( 'attachment-hash' . $url . $image->description . time() . rand( 1, 999 ) );

    $result = media_sideload_image( $url, $post_id, $hash );

    if ( is_wp_error( $result ) ) {
      $this->warnings[] = sprintf( "Error loading %s: %s", $url, $result->get_error_message() );
      continue;
    } else {
      $attachments = get_posts( array(
        'post_parent' => $post_id,
        's' => $hash,
        'post_type' => 'attachment',
        'posts_per_page' => -1,
      ) );

      if ( ! $attachments || ! is_array( $attachments ) || count( $attachments ) != 1 ) {
        $this->warnings[] = sprintf( "Could not insert attachment for %d", $post_id );
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
    $attachment->post_excerpt = $image->description;
    $attachment->menu_order = $image->sortorder;

    update_post_meta( $attachment->ID, '_wp_attachment_image_alt', $image->alttext );

    wp_update_post( $attachment );
    $this->images_count++;
    $this->infos[] = sprintf( "Added attachment for %d", $post_id );

    // Save relation to potentially avoid future duplicates
    $this->pid_dict[$image->pid] = $attachment->ID;

    return $attachment;
  }


	/**
	 * @param int $limit How many posts to get
	 *
	 * @return void
	 **/
	public function get_post_ids( $limit = -1 ) {
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
    $gallery_posts = $query->posts;

		$args = array(
			's'           => '[singlepic',
			'post_type'   => array( 'post', 'page' ),
			'post_status' => 'any',
			'nopaging'    => true,
			'fields'      => 'ids',
			'posts_per_page' => $limit
		);
		
		$args = apply_filters( 'escape_ngg_query_args', $args );
		
		$query = new WP_Query( $args );
    $singlepic_posts = $query->posts;

    return array_merge($gallery_posts, $singlepic_posts);
	}
}

// Initiate the singleton
Escape_NextGen_Gallery::init();
