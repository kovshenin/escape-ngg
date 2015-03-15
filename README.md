Escape NextGen Gallery
======================

This plugin will scan through all your posts and pages for the [nggallery] shortcode.

It will loop through all images associated with that gallery and recreate them as native WordPress attachments instead. Finally it will replace the [nggallery] shortcode with the [gallery] shortcode native to WordPress.

## Instructions

1. **Backup!**
2. Activate the plugin and browse to:
  * for version 1.* yourdomain.com/wp-admin/?escape_ngg_please=1 
    * limitations
      - doesn't work with shortcodes other than [nggallery]
      - doesn't work when more than one gallery on page
      - Compatible with [wp-cli](http://wp-cli.org/).
  * for photocrati yourdomain.com/wp-admin/?escape_ngg_base_page_id=<pageId> that will be used as parent for galleries pages, will be move all existing galleries 
3. When you're done you can delete the gallery dir, and the wp_ngg_* tables in your database. Keep the backups though.

## Notes

@uses [`media_sideload_image`](http://codex.wordpress.org/Function_Reference/media_sideload_image) to recreate your attachment posts.
