Escape NextGen Gallery
======================

This plugin will scan through all your posts and pages for the [nggallery] and [singlepic] shortcode. This modified version also respects multiple occurences of nggallery tags on a single page or post

It will loop through all images associated with that gallery and recreate them as native WordPress attachments instead. Finally it will replace the [nggallery] shortcode with the [gallery] shortcode native to WordPress.

For all [singlepic] shortcodes it will insert the link and img tag with matching dimension and alignment in the post content and also attach it to the post if it is not already part of the gallery.

## Instructions

1. **Backup!**
2. Activate the plugin and browse to yourdomain.com/wp-admin/?escape_ngg_please=1
3. When you're done you can delete the gallery dir, and the wp_ngg_* tables in your database. Keep the backups though.

## Notes

@uses [`media_sideload_image`](http://codex.wordpress.org/Function_Reference/media_sideload_image) to recreate your attachment posts.

Compatible with [wp-cli](http://wp-cli.org/).
