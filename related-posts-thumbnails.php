<?php /*
  Plugin Name:  Related Posts Thumbnails
  Plugin URI:   http://wordpress.shaldybina.com/plugins/related-posts-thumbnails/
  Description:  Showing related posts thumbnails under the post.
  Version:      1.2.4
  Author:       Maria Shaldybina
  Author URI:   http://shaldybina.com/
*/
/*  Copyright 2010  Maria I Shaldybina

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.
*/
class RelatedPostsThumbnails {
	/* Default values. PHP 4 compatible */
	var $single_only = '1';
	var $auto = '1';
	var $top_text = '<h3>Related posts:</h3>';
	var $number = 3;
	var $relation = 'categories';
	var $poststh_name = 'thumbnail';
	var $background = '#FFFFFF';
	var $hoverbackground = '#EEEEEF';
	var $border_color = '#DDDDDD';
	var $font_color = '#333333';
	var $font_family = 'Arial';
	var $font_size = '12';
	var $text_length = '100';
	var $excerpt_length = '0';
	var $custom_field = '';
	var $custom_height = '100';
	var $custom_width = '100';
	var $text_block_height = '75';
	var $thsource = 'post-thumbnails';
	var $categories_all = '1';
	var $devmode = '0';

	function RelatedPostsThumbnails() { // initialization
		load_plugin_textdomain( 'related-posts-thumbnails', false, basename( dirname( __FILE__ ) ) . '/locale' );
		$this->default_image = WP_PLUGIN_URL . '/related-posts-thumbnails/img/default.png';
		if ( get_option( 'relpoststh_auto', $this->auto ) )
			add_filter( 'the_content', array( $this, 'auto_show' ) );
		add_action( 'admin_menu',  array( $this, 'admin_menu' ) );
		add_shortcode( 'related-posts-thumbnails' , array( $this, 'get_html' ) );
	}

	function auto_show( $content ) { // Automatically displaying related posts under post body
		return $content . $this->get_html( true );
	}

	function get_html( $show_top = false ) { // Getting related posts HTML
		if ( $this->is_relpoststh_show() )
			return $this->get_thumbnails( $show_top );
		return '';
	}

	function get_thumbnails( $show_top = false ) { // Retrieve Related Posts HTML for output
		$output					= '';
		$debug					= 'Developer mode initialisation;';
		$time					= microtime(true);
		$posts_number           = get_option( 'relpoststh_number', $this->number );
		if ( $posts_number <= 0 ) // return nothing if this parameter was set to <= 0
			return $this->finish_process( $output, $debug . 'Posts number is 0;', $time );
		$id						= get_the_ID();
		$relation				= get_option( 'relpoststh_relation', $this->relation );
		$poststhname			= get_option( 'relpoststh_poststhname', $this->poststhname );
		$text_length			= get_option( 'relpoststh_textlength', $this->text_length );
		$excerpt_length			= get_option( 'relpoststh_excerptlength', $this->excerpt_length );
		$thsource				= get_option( 'relpoststh_thsource', $this->thsource );
		$categories_show_all	= get_option( 'relpoststh_show_categoriesall',
											  get_option( 'relpoststh_categoriesall',
														  $this->categories_all ) );
		/* Get random posts according to given rules */
		global $wpdb;
		$query = "SELECT distinct ID FROM $wpdb->posts ";
		$where = " WHERE post_type = 'post' AND post_status = 'publish' AND ID<>" . $id; // not the current post
		$startdate = get_option( 'relpoststh_startdate' );
		if ( !empty( $startdate ) && preg_match( '/^\d\d\d\d-\d\d-\d\d$/', $startdate ) ) { // If startdate was set
			$debug .= "Startdate: $startdate;";
			$where .= " AND post_date >= '" . $startdate . "'";
		}

		/* Get taxonomy terms */
		$join = '';
		$whichterm = '';
		$select_terms = array();
		if ( $categories_show_all != '1') { // if only specific categories were selected
			$select_terms = unserialize( get_option( 'relpoststh_show_categories',
													 get_option( 'relpoststh_categories' ) ) );
			if ( empty( $select_terms ) ) // if no categories were specified intentionally return nothing
				return $this->finish_process( $output, $debug . 'No categories were selected;', $time );
		}
		$debug .= "Relation: $relation;";
		if ( $relation != 'no' ) { // relation was set
			if ( !empty( $select_terms ) ) { // intersect categories selected and post's
				$debug .= 'With specified categories;';
				if ( $relation == 'categories' || $relation == 'both' ) {
					$object_terms = wp_get_object_terms( $id, array('category'), array( 'fields' => 'ids' ) );
					if ( is_array( $object_terms ) && is_array( $select_terms ) )
						$select_terms = array_intersect( $select_terms, $object_terms );
				}
				if ( $relation == 'tags' || $relation == 'both' ) {
					$object_terms = wp_get_object_terms( $id, array( 'post_tag' ), array( 'fields' => 'ids' ) );
					$select_terms = array_merge( $select_terms, $object_terms );
				}
			}
			else { // all categories were selected just get everything
				if ( $relation == 'categories' )
					$taxonomy = array( 'category' );
				elseif ( $relation == 'tags' )
					$taxonomy = array( 'post_tag' );
				else
					$taxonomy = array( 'category', 'post_tag' );
				$select_terms = wp_get_object_terms( $id, $taxonomy, array( 'fields' => 'ids' ) );
			}
			if ( !is_array( $select_terms ) || empty( $select_terms ) ) // no terms to get taxonomy
				return $this->finish_process( $output, $debug . 'No taxonomy terms to get posts;', $time );
		}
		if ( !( $relation == 'no' && $categories_show_all == '1' ) ) { // skip join if no relation and show all
			$join = " INNER JOIN $wpdb->term_relationships ON ($wpdb->posts.ID = $wpdb->term_relationships.object_id) INNER JOIN $wpdb->term_taxonomy ON ($wpdb->term_relationships.term_taxonomy_id = $wpdb->term_taxonomy.term_taxonomy_id) ";
			$include_terms = "'" . implode( "', '", $select_terms ) . "'";
			$whichterm = " AND $wpdb->term_taxonomy.term_id IN ($include_terms) ";
		}
		$order = " ORDER BY rand() LIMIT " . $posts_number;
		$random_posts = $wpdb->get_results( $query . $join . $where . $whichterm . $order );

		/* Get posts by their IDs */
		$posts_in = array();
		if ( is_array( $random_posts ) && count( $random_posts ) ) {
			foreach ( $random_posts as $random_post )
				$posts_in[] = $random_post->ID;
		}
		$posts = array();
		$q = new WP_Query;
		$posts = $q->query( array( 'caller_get_posts' => true,
								   'post__in' => $posts_in,
								   'posts_per_page'   => $posts_number ) );

		if ( ! ( is_array( $posts ) && count( $posts ) > 0 ) ) { // no posts
			$debug .= 'No posts found';
			return $this->finish_process( $output, $debug, $time );
		}
		else
			$debug .= 'Found ' . count( $posts ) . ' posts;';

		/* Calculating sizes */
		if ( $thsource == 'custom-field' ) {
			$debug .= 'Custom sizes;';
			$width = get_option( 'relpoststh_customwidth', $this->custom_width );
			$height = get_option( 'relpoststh_customheight', $this->custom_height );
		}
		else { // post-thumbnails source
			if ( $poststhname == 'thumbnail' || $poststhname == 'medium' || $poststhname == 'large' ) { // get thumbnail size for basic sizes
				$debug .= 'Basic sizes;';
				$width = get_option( "{$poststhname}_size_w" );
				$height = get_option( "{$poststhname}_size_h" );
			}
			elseif ( current_theme_supports( 'post-thumbnails' ) ) { // get sizes for theme supported thumbnails
				global $_wp_additional_image_sizes;
				if ( isset( $_wp_additional_image_sizes[ $poststhname ] ) ) {
					$debug .= 'Additional sizes;';
					$width = $_wp_additional_image_sizes[ $poststhname ][ 'width' ];
					$height = $_wp_additional_image_sizes[ $poststhname ][ 'height' ];					
				}
				else
					$debug .= 'No additional sizes;';
			}
		}
		// displaying square if one size is not cropping
		if ( $height == 9999 )
			$height = $width;
		if ( $width == 9999 )
			$width = $height;
		// theme is not supporting but settings were not changed
		if ( empty( $width ) ) {
			$debug .= 'Using default width;';
			$width = get_option( "thumbnail_size_w" );
		}
		if ( empty( $height ) ) {
			$debug .= 'Using default height;';
			$height = get_option( "thumbnail_size_h" );
		}
		$debug .= 'Got sizes '.$width.'x'.$height.';';
		// rendering related posts HTML
		if ( $show_top )
			$output .= stripslashes( get_option( 'relpoststh_top_text', $this->top_text ) );
		$output .= '<div style="clear: both"></div><div style="border: 0pt none ; margin: 0pt; padding: 0pt;">';
		foreach( $posts as $post ) {
			$image = '';
			$url = '';
			if ( $thsource == 'custom-field' ) {
				$debug .= 'Using custom field;';
				$url = get_post_meta( $post->ID, get_option( 'relpoststh_customfield', $this->custom_field ), true );
				$theme_resize_url = get_option( 'relpoststh_theme_resize_url', '' );
				if ( !empty( $theme_resize_url ) )
					$url = $theme_resize_url . '?src=' . $url . '&w=' . $width . '&h=' . $height . '&zc=1&q=90';
			}
			else {
				$from_post_body = true;
				if ( current_theme_supports( 'post-thumbnails' ) ) { // using built in Wordpress feature
					$post_thumbnail_id = get_post_thumbnail_id( $post->ID );
					$debug .= 'Post-thumbnails enabled in theme;';
					if ( $post_thumbnail_id !== false ) { // post has thumbnail
						$debug .= 'Post has thumbnail;';
						$image = wp_get_attachment_image_src( $post_thumbnail_id, $poststhname );
						$url = $image[0];
						$from_post_body = false;
					}
					else
						$debug .= 'Post has no thumbnail;';
				}
				if ( $from_post_body ) { // Theme does not support post-thumbnails, or post does not have assigned thumbnail
					$debug .= 'Getting image from post body;';
					$wud = wp_upload_dir();
					preg_match_all( '|<img.*?src=[\'"](' . $wud['baseurl'] . '.*?)[\'"].*?>|i', $post->post_content, $matches ); // searching for the first uploaded image in text
					if ( isset( $matches ) ) $image = $matches[1][0];
					else
						$debug .= 'No image was found;';
					if ( strlen( trim( $image ) ) > 0 ) {
						$image_sizes = @getimagesize( $image );
						if ( $image_sizes === false )
							$debug .= 'Unable to determine parsed image size';
						if ( $image_sizes !== false && isset( $image_sizes[0] ) && $image_sizes[0] == $width ) { // if this image is the same size as we need
							$debug .= 'Image used is the required size;';
							$url = $image;
						}
						else { // if not, search for resized thumbnail according to Wordpress thumbnails naming function
							$debug .= 'Changing image according to Wordpress standards;';
							$url = preg_replace( '/(-[0-9]+x[0-9]+)?(\.[^\.]*)$/', '-' . $width . 'x' . $height . '$2', $image );
						}
					}
					else
						$debug .= 'Found wrong formatted image;';
				}
			}

			$debug .= 'Image URL: '.$url.';';
			if ( empty($url) || ( ini_get( 'allow_url_fopen' ) && false === @fopen( $url, 'r' ) ) ) { // parsed URL is empty or no file if can check
				$debug .= 'Image is empty or no file. Using default image;';
				$url = get_option( 'relpoststh_default_image', $this->default_image );
			}

			$title = $this->process_text_cut( $post->post_title, $text_length );
			$post_excerpt = ( empty( $post->post_excerpt ) ) ? $post->post_content : $post->post_excerpt;
			$excerpt = $this->process_text_cut( $post_excerpt, $excerpt_length );

			if ( !empty($title) && !empty($excerpt) ) {
				$title = '<b>' . $title . '</b>';
				$excerpt = '<br/>' . $excerpt;
			}

			$debug .= 'Using title with size ' . $text_length . '. Using excerpt with size ' . $excerpt_length . ';';
			$output .= '<a onmouseout="this.style.backgroundColor=\'' . get_option( 'relpoststh_background', $this->background ) . '\'" onmouseover="this.style.backgroundColor=\'' . get_option( 'relpoststh_hoverbackground', $this->hoverbackground ) . '\'" style="border-right: 1px solid ' . get_option( 'relpoststh_bordercolor', $this->border_color ) . '; border-bottom: medium none; margin: 0pt; padding: 6px; display: block; float: left; text-decoration: none; text-align: left; cursor: pointer;" href="' . get_permalink( $post->ID ) . '">';
			$output .= '<div style="border: 0pt none ; margin: 0pt; padding: 0pt; width: ' . $width . 'px; height: ' . ( $height + get_option( 'relpoststh_textblockheight', $this->text_block_height ) ) . 'px;">';
			$output .= '<div style="border: 0pt none ; margin: 0pt; padding: 0pt; background: transparent url(' . $url . ') no-repeat scroll 0% 0%; -moz-background-clip: border; -moz-background-origin: padding; -moz-background-inline-policy: continuous; width: ' . $width . 'px; height: ' . $height . 'px;"></div>';
			$output .= '<div style="border: 0pt none; margin: 3px 0pt 0pt; padding: 0pt; font-family: ' . get_option( 'relpoststh_fontfamily', $this->font_family ) . '; font-style: normal; font-variant: normal; font-weight: normal; font-size: ' . get_option( 'relpoststh_fontsize', $this->font_size ) . 'px; line-height: normal; font-size-adjust: none; font-stretch: normal; -x-system-font: none; color: ' . get_option( 'relpoststh_fontcolor', $this->font_color ) . ';">' . $title . $excerpt . '</div>';
			$output .= '</div>';
			$output .= '</a>';

		} // end foreach
		$output .= '</div><div style="clear: both"></div>';
		return $this->finish_process( $output, $debug, $time );
	}

	function finish_process( $output, $debug, $time ) {
		$devmode = get_option( 'relpoststh_devmode', $this->devmode );
		if ( $devmode ) {
			$time = microtime(true) - $time;
			$debug .= "Plugin execution time: $time sec;";
			$output .= '<!-- '.$debug.' -->';
		}
		return $output;
	}

	function process_text_cut( $text, $length ) {
		if ($length == 0)
			return '';
		else {
			$text = strip_shortcodes( strip_tags( $text ) );
			return ( ( strlen( $text ) > $length ) ? substr( $text, 0, $length) . '...' : $text );
		}
	}

	function is_relpoststh_show() { // Checking display options
		if ( is_page() || ( ! is_single() && get_option( 'relpoststh_single_only', $this->single_only ) ) ) { // single only
			return false;
		}
		/* Check categories */
		$id = get_the_ID();
		$categories_all = get_option( 'relpoststh_categoriesall', $this->categories_all );
		if ( $categories_all != '1') { // only specific categories were selected
			$post_categories = wp_get_object_terms( $id, array( 'category' ), array( 'fields' => 'ids' ) );
			$relpoststh_categories = unserialize( get_option( 'relpoststh_categories' ) );
			if ( !is_array( $relpoststh_categories ) || !is_array( $post_categories ) ) // no categories were selcted or post doesn't belong to any
				return false;
			$common_categories = array_intersect( $relpoststh_categories, $post_categories );
			if ( empty( $common_categories ) ) // post doesn't belong to specified categories
				return false;
		}
		return true;
	}

	function admin_menu() {
		$page = add_options_page( __( 'Related Posts Thumbnails', 'related-posts-thumbnails' ), __( 'Related Posts Thumbs', 'related-posts-thumbnails' ), 'administrator', 'related-posts-thumbnails', array( $this, 'admin_interface' ) );
	}

	function admin_interface() { // Admin interface
		if ( $_POST['action'] == 'update' ) {
			if ( !current_user_can( 'manage_options' ) ) {
				wp_die( __( 'No access', 'related-posts-thumbnails' ) );
			}
			check_admin_referer( 'related-posts-thumbnails' );
			$validation = true;
			if ( !empty($_POST['relpoststh_year']) || !empty($_POST['relpoststh_month']) || !empty($_POST['relpoststh_year']) ) { // check date
				$set_date = sprintf( '%04d-%02d-%02d', $_POST['relpoststh_year'], $_POST['relpoststh_month'], $_POST['relpoststh_day'] );
				if ( checkdate( intval($_POST['relpoststh_month']), intval($_POST['relpoststh_day']), intval($_POST['relpoststh_year']) ) === false ) {
					$validation = false;
					$error = __( 'Wrong date', 'related-posts-thumbnails' ) . ': ' . sprintf( '%d/%d/%d', $_POST['relpoststh_month'], $_POST['relpoststh_day'], $_POST['relpoststh_year'] );
				}
			}
			else
				$set_date = '';
			if ( $validation ) {
				update_option( 'relpoststh_single_only', $_POST['relpoststh_single_only'] );
				update_option( 'relpoststh_auto', $_POST['relpoststh_auto'] );
				update_option( 'relpoststh_top_text', $_POST['relpoststh_top_text'] );
				update_option( 'relpoststh_number', $_POST['relpoststh_number'] );
				update_option( 'relpoststh_relation', $_POST['relpoststh_relation'] );
				update_option( 'relpoststh_default_image', $_POST['relpoststh_default_image'] );
				update_option( 'relpoststh_poststhname', $_POST['relpoststh_poststhname'] );
				update_option( 'relpoststh_background', $_POST['relpoststh_background'] );
				update_option( 'relpoststh_hoverbackground', $_POST['relpoststh_hoverbackground'] );
				update_option( 'relpoststh_bordercolor', $_POST['relpoststh_bordercolor'] );
				update_option( 'relpoststh_fontcolor', $_POST['relpoststh_fontcolor'] );
				update_option( 'relpoststh_fontsize', $_POST['relpoststh_fontsize'] );
				update_option( 'relpoststh_fontfamily', $_POST['relpoststh_fontfamily'] );
				update_option( 'relpoststh_textlength', $_POST['relpoststh_textlength'] );
				update_option( 'relpoststh_excerptlength', $_POST['relpoststh_excerptlength'] );
				update_option( 'relpoststh_thsource', $_POST['relpoststh_thsource'] );
				update_option( 'relpoststh_customfield', $_POST['relpoststh_customfield'] );
				update_option( 'relpoststh_theme_resize_url', $_POST['relpoststh_theme_resize_url'] );
				update_option( 'relpoststh_customwidth', $_POST['relpoststh_customwidth'] );
				update_option( 'relpoststh_customheight', $_POST['relpoststh_customheight'] );
				update_option( 'relpoststh_textblockheight', $_POST['relpoststh_textblockheight'] );
				update_option( 'relpoststh_categoriesall', $_POST['relpoststh_categoriesall'] );
				update_option( 'relpoststh_categories', serialize( $_POST['relpoststh_categories'] ) );
				update_option( 'relpoststh_show_categoriesall', $_POST['relpoststh_show_categoriesall'] );
				update_option( 'relpoststh_show_categories', serialize( $_POST['relpoststh_show_categories'] ) );
				update_option( 'relpoststh_devmode', $_POST['relpoststh_devmode'] );
				update_option( 'relpoststh_startdate', $set_date );
				echo "<div class='updated fade'><p>" . __( 'Settings updated', 'related-posts-thumbnails' ) ."</p></div>";
			}
			else {
				echo "<div class='error fade'><p>" . __( 'Settings update failed', 'related-posts-thumbnails' ) . '. '. $error . "</p></div>";
			}
		}
		$available_sizes = array( 'thumbnail' => 'thumbnail', 'medium' => 'medium' );
		if ( current_theme_supports( 'post-thumbnails' ) ) {
			global $_wp_additional_image_sizes;
			if ( is_array($_wp_additional_image_sizes ) ) {
				$available_sizes = array_merge( $available_sizes, $_wp_additional_image_sizes );
			}
		}
		$relpoststh_single_only = get_option( 'relpoststh_single_only', $this->single_only );
		$relpoststh_auto = get_option( 'relpoststh_auto', $this->auto );
		$relpoststh_relation = get_option( 'relpoststh_relation', $this->relation );
		$relpoststh_thsource = get_option( 'relpoststh_thsource', $this->thsource );
		$relpoststh_devmode = get_option( 'relpoststh_devmode', $this->devmode );
		$relpoststh_categoriesall = get_option( 'relpoststh_categoriesall', $this->categories_all );
		$relpoststh_categories = unserialize( get_option( 'relpoststh_categories' ) );
		$relpoststh_show_categories = unserialize( get_option( 'relpoststh_show_categories', get_option( 'relpoststh_categories' ) ) );
		$relpoststh_show_categoriesall = get_option( 'relpoststh_show_categoriesall', $relpoststh_categoriesall );
		$relpoststh_startdate = explode( '-', get_option( 'relpoststh_startdate' ) );
		$thsources = array( 'post-thumbnails' => 'Post thumbnails', 'custom-field' => 'Custom field' );
		$categories = get_categories();
		?>
<script type="text/javascript">
	jQuery(document).ready(function($) {
		$(".select_all").click(function(){
			if (this.checked) {
				$(this).parent().find("div.select_specific").hide();
			}
			else {
				$(this).parent().find("div.select_specific").show();
			}
		});
		$('#relpoststh_thsource').change(function(){
			if (this.value == 'post-thumbnails') {
				$('#relpoststh-post-thumbnails').show();
				$('#relpoststh-custom-field').hide();
			}
			else {
				$('#relpoststh-post-thumbnails').hide();
				$('#relpoststh-custom-field').show();
			}
		});
	});
</script>
<div class="wrap">
	<div class="icon32" id="icon-options-general"><br></div>
	<h2><?php _e( 'Related Posts Thumbnails Settings', 'related-posts-thumbnails' ); ?></h2>
	<form action="?page=related-posts-thumbnails" method="POST">
		<input type="hidden" name="action" value="update" />
		<?php wp_nonce_field( 'related-posts-thumbnails' ); ?>
		<div class="metabox-holder">
			<div class="postbox">
				<h3><?php _e( 'General Display Options', 'related-posts-thumbnails' ); ?>:</h3>
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><?php _e( 'Automatically append to the post content', 'related-posts-thumbnails' ); ?>:</th>
						<td>
							<input type="checkbox" name="relpoststh_auto" id="relpoststh_auto" value="1" <?php if ( $relpoststh_auto ) echo 'checked="checked"'; ?>/>
							<label for="relpoststh_auto"><?php _e( 'Or use <b>&lt;?php get_related_posts_thumbnails(); ?&gt;</b> in the Loop', 'related-posts-thumbnails' ); ?></label><br />
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e( 'Developer mode', 'related-posts-thumbnails' ); ?>:</th>
						<td>
							<input type="checkbox" name="relpoststh_devmode" id="relpoststh_devmode" value="1" <?php if ( $relpoststh_devmode ) echo 'checked="checked"'; ?>/>
							<label for="relpoststh_devmode"><?php _e( 'This will add debugging information in HTML source', 'related-posts-thumbnails' ); ?></label><br />
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e( 'Page type', 'related-posts-thumbnails' ); ?>:</th>
						<td>
							<input type="checkbox" name="relpoststh_single_only" id="relpoststh_single_only" value="1" <?php if ( $relpoststh_single_only ) echo 'checked="checked"'; ?>/>
							<label for="relpoststh_single_only"><?php _e( 'Show on single posts only', 'related-posts-thumbnails' ); ?></label><br />
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e( 'Categories on which related thumbnails will appear', 'related-posts-thumbnails' ); ?>:</th>
						<td>
							<?php $this->display_categories_list( $relpoststh_categoriesall, $categories, $relpoststh_categories, 'relpoststh_categoriesall', 'relpoststh_categories' ); ?>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e( 'Categories that will appear in related thumbnails', 'related-posts-thumbnails' ); ?>:</th>
						<td>
							<?php $this->display_categories_list( $relpoststh_show_categoriesall, $categories, $relpoststh_show_categories, 'relpoststh_show_categoriesall', 'relpoststh_show_categories' ); ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php _e( 'Include only posts after', 'related-posts-thumbnails' ); ?>:</th>
						<td>
							<?php _e( 'Year' ); ?>: <input type="text" name="relpoststh_year" size="4" value="<?php echo $relpoststh_startdate[0]; ?>"> <?php _e( 'Month' ); ?>: <input type="text" name="relpoststh_month" size="2" value="<?php echo $relpoststh_startdate[1]; ?>"> <?php _e( 'Day' ); ?>: <input type="text" name="relpoststh_day" size="2" value="<?php echo $relpoststh_startdate[2]; ?>"> <label for="relpoststh_excerptlength"><?php _e( 'Leave empty for all posts dates', 'related-posts-thumbnails' ); ?></label><br />

						</td>
					</tr>
					<tr>
						<th scope="row"><?php _e( 'Top text', 'related-posts-thumbnails' ); ?>:</th>
						<td>
							<input type="text" name="relpoststh_top_text" value="<?php echo stripslashes( htmlspecialchars( get_option( 'relpoststh_top_text', $this->top_text ) ) ); ?>" size="50"/>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php _e( 'Number of similar posts to display', 'related-posts-thumbnails' ); ?>:</th>
						<td>
							<input type="text" name="relpoststh_number" value="<?php echo get_option( 'relpoststh_number', $this->number ); ?>" size="2"/>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php _e( 'Default image URL', 'related-posts-thumbnails' ); ?>:</th>
						<td>
							<input type="text" name="relpoststh_default_image" value="<?php echo get_option('relpoststh_default_image', $this->default_image );?>" size="50"/>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php _e( 'Thumbnails source', 'related-posts-thumbnails' ); ?>:</th>
						<td>
							<select name="relpoststh_thsource"  id="relpoststh_thsource">
								<?php foreach ( $thsources as $name => $title ) : ?>
								<option value="<?php echo $name; ?>" <?php if ( $relpoststh_thsource == $name ) echo 'selected'; ?>><?php echo $title; ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				</table>
			</div>
			<div class="postbox" id="relpoststh-post-thumbnails" <?php if ( $relpoststh_thsource != 'post-thumbnails' ) : ?> style="display:none" <?php endif; ?>>
				<h3><?php _e( 'Thumbnails source', 'related-posts-thumbnails' ); ?>:</h3>
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><?php _e( 'Post-thumbnails name', 'related-posts-thumbnails' ); ?>:</th>
						<td>
							<select name="relpoststh_poststhname">
								<?php foreach ( $available_sizes as $size_name => $size ) : ?>
								<option <?php if ( $size_name == get_option('relpoststh_poststhname', $this->poststhname) ) echo 'selected'; ?>><?php echo $size_name; ?></option>
								<?php endforeach; ?>
							</select>
							<?php if ( !current_theme_supports( 'post-thumbnails' ) ) : ?>
							(<?php _e( 'Your theme has to support post-thumbnails to have more choices', 'related-posts-thumbnails' ); ?>)
							<?php endif; ?>
						</td>
					</tr>
				</table>
			</div>
			<div class="postbox" id="relpoststh-custom-field" <?php if ( $relpoststh_thsource != 'custom-field' ) : ?> style="display:none" <?php endif; ?>>
				<h3><?php _e( 'Thumbnails source', 'related-posts-thumbnails' ); ?>:</h3>
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><?php _e( 'Custom field name', 'related-posts-thumbnails' ); ?>:</th>
						<td>
							<input type="text" name="relpoststh_customfield" value="<?php echo get_option('relpoststh_customfield', $this->custom_field );?>" size="50"/>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e( 'Size', 'related-posts-thumbnails' ); ?>:</th>
						<td>
							<?php _e( 'Width', 'related-posts-thumbnails' ); ?>: <input type="text" name="relpoststh_customwidth" value="<?php echo get_option('relpoststh_customwidth', $this->custom_width );?>" size="3"/>px x 
							<?php _e( 'Height', 'related-posts-thumbnails' ); ?>: <input type="text" name="relpoststh_customheight" value="<?php echo get_option('relpoststh_customheight', $this->custom_height );?>" size="3"/>px
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e( 'Theme resize url', 'related-posts-thumbnails' ); ?>:</th>
						<td>
							<input type="text" name="relpoststh_theme_resize_url" value="<?php echo get_option('relpoststh_theme_resize_url', '' );?>" size="50"/>
							(<?php _e( 'If your theme resizes images, enter URL to its resizing PHP file', 'related-posts-thumbnails' ); ?>)
						</td>
					</tr>
				</table>
			</div>
			<div class="postbox">
				<h3><?php _e( 'Style options', 'related-posts-thumbnails' ); ?>:</h3>
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><?php _e( 'Background color', 'related-posts-thumbnails' ); ?>:</th>
						<td>
							<input type="text" name="relpoststh_background" value="<?php echo get_option( 'relpoststh_background', $this->background ); ?>" size="7"/>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e( 'Background color on mouse over', 'related-posts-thumbnails' ); ?>:</th>
						<td>
							<input type="text" name="relpoststh_hoverbackground" value="<?php echo get_option( 'relpoststh_hoverbackground', $this->hoverbackground ); ?>" size="7"/>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e( 'Border color', 'related-posts-thumbnails' ); ?>:</th>
						<td>
							<input type="text" name="relpoststh_bordercolor" value="<?php echo get_option( 'relpoststh_bordercolor', $this->border_color )?>" size="7"/>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e( 'Font color', 'related-posts-thumbnails' ); ?>:</th>
						<td>
							<input type="text" name="relpoststh_fontcolor" value="<?php echo get_option( 'relpoststh_fontcolor', $this->font_color ); ?>" size="7"/>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e( 'Font family', 'related-posts-thumbnails' ); ?>:</th>
						<td>
							<input type="text" name="relpoststh_fontfamily" value="<?php echo get_option( 'relpoststh_fontfamily', $this->font_family )?>" size="50"/>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e( 'Font size', 'related-posts-thumbnails' ); ?>:</th>
						<td>
							<input type="text" name="relpoststh_fontsize" value="<?php echo get_option( 'relpoststh_fontsize', $this->font_size )?>" size="7"/>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e( 'Text maximum length', 'related-posts-thumbnails' ); ?>:</th>
						<td>
							<input type="text" name="relpoststh_textlength" value="<?php echo get_option( 'relpoststh_textlength', $this->text_length )?>" size="7"/>
							<label for="relpoststh_textlength"><?php _e( 'Set 0 for no title', 'related-posts-thumbnails' ); ?></label><br />
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e( 'Excerpt maximum length', 'related-posts-thumbnails' ); ?>:</th>
						<td>
							<input type="text" name="relpoststh_excerptlength" value="<?php echo get_option( 'relpoststh_excerptlength', $this->excerpt_length )?>" size="7"/>
							<label for="relpoststh_excerptlength"><?php _e( 'Set 0 for no excerpt', 'related-posts-thumbnails' ); ?></label><br />
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e( 'Text block height', 'related-posts-thumbnails' ); ?>:</th>
						<td>
							<input type="text" name="relpoststh_textblockheight" value="<?php echo get_option( 'relpoststh_textblockheight', $this->text_block_height )?>" size="7"/> px
						</td>
					</tr>
				</table>
			</div>
			<div class="postbox">
				<h3><?php _e( 'Relation Builder Options', 'related-posts-thumbnails' ); ?>:</h3>
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><?php _e( 'Relation based on', 'related-posts-thumbnails' ); ?>:</th>
						<td>
							<input type="radio" name="relpoststh_relation" id="relpoststh_relation_categories" value="categories" <?php if ( $relpoststh_relation == 'categories' ) echo 'checked="checked"'; ?>/>
							<label for="relpoststh_relation_categories"><?php _e( 'Categories', 'related-posts-thumbnails' ); ?></label><br />
							<input type="radio" name="relpoststh_relation" id="relpoststh_relation_tags" value="tags" <?php if ( $relpoststh_relation == 'tags' ) echo 'checked="checked"'; ?>/>
							<label for="relpoststh_relation_tags"><?php _e( 'Tags', 'related-posts-thumbnails' ); ?></label><br />
							<input type="radio" name="relpoststh_relation" id="relpoststh_relation_both" value="both" <?php if ( $relpoststh_relation == 'both' ) echo 'checked="checked"'; ?>/>
							<label for="relpoststh_relation_both"><?php _e( 'Categories and Tags', 'related-posts-thumbnails' ); ?></label><br />
							<input type="radio" name="relpoststh_relation" id="relpoststh_relation_no" value="no" <?php if ( $relpoststh_relation == 'no' ) echo 'checked="checked"'; ?>/>
							<label for="relpoststh_relation_no"><?php _e( 'Random', 'related-posts-thumbnails' ); ?></label><br />
						</td>
					</tr>
				</table>
			</div>
			<input name="Submit" value="<?php _e( 'Save Changes', 'related-posts-thumbnails' ); ?>" type="submit">
		</div>
	</form>
</div>
<p style="margin-top: 40px;"><small><?php _e('If you experience some problems with this plugin please let me know about it on <a href="http://wordpress.shaldybina.com/plugins/related-posts-thumbnails/">Plugin\'s homepage</a>. If you think this plugin is awesome please vote on <a href="http://wordpress.org/extend/plugins/related-posts-thumbnails/">Wordpress plugin page</a>. Thanks!', 'related-posts-thumbnails' ); ?></small></p>
<?php
	}

	function display_categories_list( $categoriesall, $categories, $selected_categories, $all_name, $specific_name ) {
	?>
		<input id="<?php echo $all_name; ?>" class="select_all" type="checkbox" name="<?php echo $all_name; ?>" value="1" <?php if ( $categoriesall == '1' ) echo 'checked="checked"'; ?>/>
		<label for="<?php echo $all_name; ?>"><?php _e( 'All', 'related-posts-thumbnails' ); ?></label>
		<div class="select_specific" <?php if ( $categoriesall == '1' ) : ?> style="display:none" <?php endif; ?>>
			<?php foreach ( $categories as $category ) : ?>
			<input type="checkbox" name="<?php echo $specific_name; ?>[]" id="<?php echo $specific_name; ?>_<?php echo $category->category_nicename; ?>" value="<?php echo $category->cat_ID; ?>" <?php if ( in_array( $category->cat_ID, (array)$selected_categories ) ) echo 'checked="checked"'; ?>/>
			<label for="<?php echo $specific_name; ?>_<?php echo $category->category_nicename; ?>"><?php echo $category->cat_name; ?></label><br />
			<?php endforeach; ?>
		</div>
	<?php
	}
}

add_action( 'init', 'related_posts_thumbnails' );

function related_posts_thumbnails() {
	global $related_posts_thumbnails;
	$related_posts_thumbnails = new RelatedPostsThumbnails();
}

function get_related_posts_thumbnails()
{
	global $related_posts_thumbnails;
	echo $related_posts_thumbnails->get_html();
}

/**
 * Related Posts Widget, will be displayed on post page
 */
class RelatedPostsThumbnailsWidget extends WP_Widget {
	function RelatedPostsThumbnailsWidget() {
		parent::WP_Widget(false, $name = 'Related Posts Thumbnails');
	}

	function widget($args, $instance) {
		if ( is_single() && !is_page() ) { // display on post page only
			extract( $args );
			$title = apply_filters('widget_title', $instance['title']);
			echo $before_widget;
			if ( $title )
				echo $before_title . $title . $after_title;
			get_related_posts_thumbnails();
			echo $after_widget;
		}
	}

	function update($new_instance, $old_instance) {
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
		return $instance;
	}

	function form($instance) {
		$title = esc_attr($instance['title']);
		?>
		<p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?> <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" /></label></p>
		<?php
	}

} // class RelatedPostsThumbnailsWidget

add_action( 'widgets_init', create_function( '', 'return register_widget("RelatedPostsThumbnailsWidget");' ) );
?>
