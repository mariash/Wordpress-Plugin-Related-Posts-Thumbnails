<?php /*
  Plugin Name:  Related Posts Thumbnails
  Plugin URI:   http://wordpress.shaldybina.com/plugins/related-posts-thumbnails/
  Description:  Showing related posts thumbnails under the post.
  Version:      1.0
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

	/* Default values */
	public $single_only = '1';
	public $top_text = '<h3>Related posts:</h3>';
	public $number = 3;
	public $relation = 'categories';
	public $default_image;
	public $poststh_name = 'thumbnail';
	public $background = '#FFFFFF';
	public $hoverbackground = '#EEEEEF';
	public $border_color = '#DDDDDD';
	public $font_color = '#333333';
	public $font_family = 'Arial';
	public $font_size = '12';
	public $text_length = '100';

	function RelatedPostsThumbnails() { // initialization
		load_plugin_textdomain( 'related-posts-thumbnails', false, basename( dirname( __FILE__ ) ) . '/locale' );
		$this->default_image = WP_PLUGIN_URL . '/related-posts-thumbnails/img/default.png';
		add_filter( 'the_content', array( $this, 'relpoststh_show' ) );
		add_action( 'admin_menu',  array( $this, 'admin_menu' ) );
	}

	function relpoststh_show($content) { // Displaying related posts on the site
		if ( $this->is_relpoststh_show() ) {
			$content .= get_option( 'relpoststh_top_text', $this->top_text );
			$content .= $this->relpoststh_get();
		}
		return $content;
	}

	function relpoststh_get() { // Retrieve Related Posts HTML for output
		$id           = get_the_ID();
		$relation     = get_option( 'relpoststh_relation', $this->relation );
		$posts_number = get_option( 'relpoststh_number', $this->number );
		$poststhname  = get_option( 'relpoststh_poststhname', $this->poststhname );
		$text_length  = get_option( 'relpoststh_textlength', $this->text_length );

		$args = array( 'orderby'          => 'rand',
					   'caller_get_posts' => true,
					   'posts_per_page'   => $posts_number,
					   'post__not_in'     => array( $id ) );

		$posts = array();
		$q = new WP_Query;

		if ( $poststhname == 'thumbnail' || $poststhname == 'medium' || $poststhname == 'large' ) { // get thumbnail size for basic sizes
			$width = get_option( "{$poststhname}_size_w" );
			$height = get_option( "{$poststhname}_size_h" );
		}
		elseif ( current_theme_supports( 'post-thumbnails' ) ) { // get sizes for theme supported thumbnails
			global $_wp_additional_image_sizes;
			$width = $_wp_additional_image_sizes[ $poststhname ][ 'width' ];
			$height = $_wp_additional_image_sizes[ $poststhname ][ 'height' ];
		}

		/* Getting posts by relation */
		if ( $relation == 'categories' || $relation == 'both' ) {
			$query_args = array( 'tag__in' => wp_get_object_terms( $id, array( 'post_tag' ), array( 'fields' => 'ids' ) ) );
			$posts = array_merge( $posts, $q->query( array_merge( $args, $query_args ) ) );
		}

		if ( $relation == 'tags' || $relation == 'both' ) {
			$query_args = array( 'category__in' => wp_get_object_terms( $id, array( 'category' ), array( 'fields' => 'ids' ) ) );
			$posts = array_merge( $posts, $q->query( array_merge( $args, $query_args ) ) );
		}

		if ( $relation == 'both' ) {
			foreach ( $posts as $post ) {
				$posts_unique[ $post->ID ] = $post;
			}
			shuffle( $posts_unique );
			$posts = array_slice( $posts_unique, 0, $posts_number );
		}

		if ( count( $posts ) ) { // rendering related posts HTML
			$output = '<div style="clear: both"></div><div style="border: 0pt none ; margin: 0pt; padding: 0pt;">';
			foreach( $posts as $post ) {
				$image = '';
				$url = '';
				if ( current_theme_supports( 'post-thumbnails' ) && has_post_thumbnail( $post->ID ) ) { // using built in Wordpress feature
					$post_thumbnail_id = get_post_thumbnail_id( $post->ID );
					if ( $post_thumbnail_id ) {
						$image = wp_get_attachment_image_src( $post_thumbnail_id, $poststhname );
						$url = $image[0];
					}
				}
				else { // Theme does not support post-thumbnails, or post does not have assigned thumbnail
					$wud = wp_upload_dir();
					preg_match_all( '|<img.*?src=[\'"](' . $wud['baseurl'] . '.*?)[\'"].*?>|i', $post->post_content, $matches ); // searching for the first uploaded image in text
					if ( isset( $matches ) ) $image = $matches[1][0];
					if ( strlen( trim( $image ) ) > 0 ) {
						$image_sizes = getimagesize( $image );
						if ( $image_sizes[0] == $width ) { // if this image is the same size as we need
							$url = $image;
						}
						else { // if not, search for resized thumbnail according to Wordpress thumbnails naming function
							$url = preg_replace( '/(-[0-9]+x[0-9]+)?(\.[^\.]*)$/', '-' . $width . 'x' . $height . '$2', $image );
						}
					}
				}
				if ( empty( $url ) || false === file( $url ) ) { // using default image if no image was found or no such file on server
					$url = get_option( 'relpoststh_default_image', $this->default_image );
				}
				$title = ( strlen( $post->post_title ) > $text_length ) ? substr( $post->post_title, 0, $text_length) . '...' : $post->post_title;
				$output .= '<a onmouseout="this.style.backgroundColor=\'' . get_option( 'relpoststh_background', $this->background ) . '\'" onmouseover="this.style.backgroundColor=\'' . get_option( 'relpoststh_hoverbackground', $this->hoverbackground ) . '\'" style="border-right: 1px solid ' . get_option( 'relpoststh_bordercolor', $this->border_color ) . '; border-bottom: medium none; margin: 0pt; padding: 6px; display: block; float: left; text-decoration: none; text-align: left; cursor: pointer;" href="' . get_permalink( $post->ID ) . '">';
				$output .= '<div style="border: 0pt none ; margin: 0pt; padding: 0pt; width: ' . $width . 'px; height: ' . ( $height + 75 ) . 'px;">';
				$output .= '<div style="border: 0pt none ; margin: 0pt; padding: 0pt; background: transparent url(' . $url . ') no-repeat scroll 0% 0%; -moz-background-clip: border; -moz-background-origin: padding; -moz-background-inline-policy: continuous; width: ' . $width . 'px; height: ' . $height . 'px;"></div>';
				$output .= '<div style="border: 0pt none; margin: 3px 0pt 0pt; padding: 0pt; font-family: ' . get_option( 'relpoststh_fontfamily', $this->font_family ) . '; font-style: normal; font-variant: normal; font-weight: normal; font-size: ' . get_option( 'relpoststh_fontsize', $this->font_size ) . 'px; line-height: normal; font-size-adjust: none; font-stretch: normal; -x-system-font: none; color: ' . get_option( 'relpoststh_fontcolor', $this->font_color ) . ';">' . $title . '</div>';
				$output .= '</div>';
				$output .= '</a>';

			} // end foreach
			$output .= '</div><div style="clear: both"></div>';
		} // end if found posts
		return $output;
	}

	function is_relpoststh_show() { // Checking display options
		return ( is_single() || ! get_option( 'relpoststh_single_only', $this->single_only ) );
	}

	/**
	 * Related Posts Thumbnails
	 */
	function admin_menu() {
		$page = add_options_page( __( 'Related Posts Thumbnails', 'related-posts-thumbnails' ), __( 'Related Posts Thumbs', 'related-posts-thumbnails' ), 'administrator', 'related-posts-thumbnails', array( $this, 'admin_interface' ) );
	}

	function admin_interface() { // Admin interface
		if ( $_POST['action'] == 'update' ) {
			if ( !current_user_can( 'manage_options' ) ) {
				wp_die( __( 'No access', 'related-posts-thumbnails' ) );
			}
			check_admin_referer( 'related-posts-thumbnails' );
			update_option( 'relpoststh_single_only', $_POST['relpoststh_single_only'] );
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
			echo "<div class='updated fade'><p>" . __( 'Settings updated', 'related-posts-thumbnails' ) ."</p></div>";
		}
		$available_sizes = array( 'thumbnail' => 'thumbnail', 'medium' => 'medium' );
		if ( current_theme_supports( 'post-thumbnails' ) ) {
			global $_wp_additional_image_sizes;
			$available_sizes = array_merge( $available_sizes, $_wp_additional_image_sizes );
		}
		$relpoststh_single_only = get_option( 'relpoststh_single_only', $this->single_only );
		$relpoststh_relation = get_option( 'relpoststh_relation', $this->relation );
		?>
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
						<th scope="row"><?php _e( 'Display options', 'related-posts-thumbnails' ); ?>:</th>
						<td>
							<input type="checkbox" name="relpoststh_single_only" id="relpoststh_single_only" value="1" <?php if ( $relpoststh_single_only ) echo 'checked="checked"'; ?>/>
							<label for="relpoststh_single_only"><?php _e( 'Show on single posts only', 'related-posts-thumbnails' ); ?></label><br />
						</td>
					</tr>
					<tr>
						<th scope="row"><?php _e( 'Top text', 'related-posts-thumbnails' ); ?>:</th>
						<td>
							<input type="text" name="relpoststh_top_text" value="<?php echo get_option( 'relpoststh_top_text', $this->top_text ); ?>" size="50"/>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php _e( 'Number of similar posts to display', 'related-posts-thumbnails' ); ?>:</th>
						<td>
							<input type="text" name="relpoststh_number" value="<?php echo get_option( 'relpoststh_number', $this->number ); ?>" size="2"/>
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
						</td>
					</tr>
				</table>
			</div>
			<div class="postbox">
				<h3><?php _e( 'Thumbnails options', 'related-posts-thumbnails' ); ?>:</h3>
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
					<tr>
						<th scope="row"><?php _e( 'Default image URL', 'related-posts-thumbnails' ); ?>:</th>
						<td>
							<input type="text" name="relpoststh_default_image" value="<?php echo get_option('relpoststh_default_image', $this->default_image );?>" size="50"/>
						</td>
					</tr>
				</table>
			</div>

			<input name="Submit" value="<?php _e( 'Save Changes', 'related-posts-thumbnails' ); ?>" type="submit">
		</div>
	</form>
</div>
<?php
	}
}

add_action( 'init', 'related_posts_thumbnails' );

function related_posts_thumbnails() {
	global $related_posts_thumbnails;
	$related_posts_thumbnails = new RelatedPostsThumbnails();
}
?>
