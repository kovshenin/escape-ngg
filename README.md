Escape NextGen Gallery
======================

This plugin will scan through all your posts and pages for the [nggallery] shortcode, as well as Nextgen 2.x branch-style img tag placeholders.

It will loop through all images associated with that gallery and recreate them as native WordPress attachments instead. Finally it will replace the [nggallery] shortcode with the [gallery] shortcode native to WordPress.

## Instructions

1. **Backup!**
2. Activate the plugin and browse to yourdomain.com/wp-admin/?escape_ngg_please=1
3. When you're done you can delete the gallery dir, and the wp_ngg_* tables in your database. Keep the backups though.

## Limitations 
- doesn't work with shortcodes other than [nggallery]

## Notes

@uses [`media_sideload_image`](http://codex.wordpress.org/Function_Reference/media_sideload_image) to recreate your attachment posts.

Compatible with [wp-cli](http://wp-cli.org/).
