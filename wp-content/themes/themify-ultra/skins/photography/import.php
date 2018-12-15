<?php

defined( 'ABSPATH' ) or die;

$GLOBALS['processed_terms'] = array();
$GLOBALS['processed_posts'] = array();

require_once ABSPATH . 'wp-admin/includes/post.php';
require_once ABSPATH . 'wp-admin/includes/taxonomy.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

function themify_import_post( $post ) {
	global $processed_posts, $processed_terms;

	if ( ! post_type_exists( $post['post_type'] ) ) {
		return;
	}

	/* Menu items don't have reliable post_title, skip the post_exists check */
	if( $post['post_type'] !== 'nav_menu_item' ) {
		$post_exists = post_exists( $post['post_title'], '', $post['post_date'] );
		if ( $post_exists && get_post_type( $post_exists ) == $post['post_type'] ) {
			$processed_posts[ intval( $post['ID'] ) ] = intval( $post_exists );
			return;
		}
	}

	if( $post['post_type'] == 'nav_menu_item' ) {
		if( ! isset( $post['tax_input']['nav_menu'] ) || ! term_exists( $post['tax_input']['nav_menu'], 'nav_menu' ) ) {
			return;
		}
		$_menu_item_type = $post['meta_input']['_menu_item_type'];
		$_menu_item_object_id = $post['meta_input']['_menu_item_object_id'];

		if ( 'taxonomy' == $_menu_item_type && isset( $processed_terms[ intval( $_menu_item_object_id ) ] ) ) {
			$post['meta_input']['_menu_item_object_id'] = $processed_terms[ intval( $_menu_item_object_id ) ];
		} else if ( 'post_type' == $_menu_item_type && isset( $processed_posts[ intval( $_menu_item_object_id ) ] ) ) {
			$post['meta_input']['_menu_item_object_id'] = $processed_posts[ intval( $_menu_item_object_id ) ];
		} else if ( 'custom' != $_menu_item_type ) {
			// associated object is missing or not imported yet, we'll retry later
			// $missing_menu_items[] = $item;
			return;
		}
	}

	$post_parent = ( $post['post_type'] == 'nav_menu_item' ) ? $post['meta_input']['_menu_item_menu_item_parent'] : (int) $post['post_parent'];
	$post['post_parent'] = 0;
	if ( $post_parent ) {
		// if we already know the parent, map it to the new local ID
		if ( isset( $processed_posts[ $post_parent ] ) ) {
			if( $post['post_type'] == 'nav_menu_item' ) {
				$post['meta_input']['_menu_item_menu_item_parent'] = $processed_posts[ $post_parent ];
			} else {
				$post['post_parent'] = $processed_posts[ $post_parent ];
			}
		}
	}

	/**
	 * for hierarchical taxonomies, IDs must be used so wp_set_post_terms can function properly
	 * convert term slugs to IDs for hierarchical taxonomies
	 */
	if( ! empty( $post['tax_input'] ) ) {
		foreach( $post['tax_input'] as $tax => $terms ) {
			if( is_taxonomy_hierarchical( $tax ) ) {
				$terms = explode( ', ', $terms );
				$post['tax_input'][ $tax ] = array_map( 'themify_get_term_id_by_slug', $terms, array_fill( 0, count( $terms ), $tax ) );
			}
		}
	}

	$post['post_author'] = (int) get_current_user_id();
	$post['post_status'] = 'publish';

	$old_id = $post['ID'];

	unset( $post['ID'] );
	$post_id = wp_insert_post( $post, true );
	if( is_wp_error( $post_id ) ) {
		return false;
	} else {
		$processed_posts[ $old_id ] = $post_id;

		if( isset( $post['has_thumbnail'] ) && $post['has_thumbnail'] ) {
			$placeholder = themify_get_placeholder_image();
			if( ! is_wp_error( $placeholder ) ) {
				set_post_thumbnail( $post_id, $placeholder );
			}
		}

		return $post_id;
	}
}

function themify_get_placeholder_image() {
	static $placeholder_image = null;

	if( $placeholder_image == null ) {
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();
		global $wp_filesystem;
		$upload = wp_upload_bits( $post['post_name'] . '.jpg', null, $wp_filesystem->get_contents( THEMIFY_DIR . '/img/image-placeholder.jpg' ) );

		if ( $info = wp_check_filetype( $upload['file'] ) )
			$post['post_mime_type'] = $info['type'];
		else
			return new WP_Error( 'attachment_processing_error', __( 'Invalid file type', 'themify' ) );

		$post['guid'] = $upload['url'];
		$post_id = wp_insert_attachment( $post, $upload['file'] );
		wp_update_attachment_metadata( $post_id, wp_generate_attachment_metadata( $post_id, $upload['file'] ) );

		$placeholder_image = $post_id;
	}

	return $placeholder_image;
}

function themify_import_term( $term ) {
	global $processed_terms;

	if( $term_id = term_exists( $term['slug'], $term['taxonomy'] ) ) {
		if ( is_array( $term_id ) ) $term_id = $term_id['term_id'];
		if ( isset( $term['term_id'] ) )
			$processed_terms[ intval( $term['term_id'] ) ] = (int) $term_id;
		return (int) $term_id;
	}

	if ( empty( $term['parent'] ) ) {
		$parent = 0;
	} else {
		$parent = term_exists( $term['parent'], $term['taxonomy'] );
		if ( is_array( $parent ) ) $parent = $parent['term_id'];
	}

	$id = wp_insert_term( $term['name'], $term['taxonomy'], array(
		'parent' => $parent,
		'slug' => $term['slug'],
		'description' => $term['description'],
	) );
	if ( ! is_wp_error( $id ) ) {
		if ( isset( $term['term_id'] ) ) {
			$processed_terms[ intval($term['term_id']) ] = $id['term_id'];
			return $term['term_id'];
		}
	}

	return false;
}

function themify_get_term_id_by_slug( $slug, $tax ) {
	$term = get_term_by( 'slug', $slug, $tax );
	if( $term ) {
		return $term->term_id;
	}

	return false;
}

function themify_undo_import_term( $term ) {
	$term_id = term_exists( $term['slug'], $term['taxonomy'] );
	if ( $term_id ) {
		if ( is_array( $term_id ) ) $term_id = $term_id['term_id'];
		if ( isset( $term_id ) ) {
			wp_delete_term( $term_id, $term['taxonomy'] );
		}
	}
}

/**
 * Determine if a post exists based on title, content, and date
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param array $args array of database parameters to check
 * @return int Post ID if post exists, 0 otherwise.
 */
function themify_post_exists( $args = array() ) {
	global $wpdb;

	$query = "SELECT ID FROM $wpdb->posts WHERE 1=1";
	$db_args = array();

	foreach ( $args as $key => $value ) {
		$value = wp_unslash( sanitize_post_field( $key, $value, 0, 'db' ) );
		if( ! empty( $value ) ) {
			$query .= ' AND ' . $key . ' = %s';
			$db_args[] = $value;
		}
	}

	if ( !empty ( $args ) )
		return (int) $wpdb->get_var( $wpdb->prepare($query, $args) );

	return 0;
}

function themify_undo_import_post( $post ) {
	if( $post['post_type'] == 'nav_menu_item' ) {
		$post_exists = themify_post_exists( array(
			'post_name' => $post['post_name'],
			'post_modified' => $post['post_date'],
			'post_type' => 'nav_menu_item',
		) );
	} else {
		$post_exists = post_exists( $post['post_title'], '', $post['post_date'] );
	}
	if( $post_exists && get_post_type( $post_exists ) == $post['post_type'] ) {
		/**
		 * check if the post has been modified, if so leave it be
		 *
		 * NOTE: posts are imported using wp_insert_post() which modifies post_modified field
		 * to be the same as post_date, hence to check if the post has been modified,
		 * the post_modified field is compared against post_date in the original post.
		 */
		if( $post['post_date'] == get_post_field( 'post_modified', $post_exists ) ) {
			wp_delete_post( $post_exists, true ); // true: bypass trash
		}
	}
}

function themify_do_demo_import() {

	if ( isset( $GLOBALS["ThemifyBuilder_Data_Manager"] ) ) {
		remove_action( "save_post", array( $GLOBALS["ThemifyBuilder_Data_Manager"], "save_builder_text_only"), 10, 3 );
	}
$term = array (
  'term_id' => 11,
  'name' => 'Fashion',
  'slug' => 'fashion',
  'term_group' => 0,
  'taxonomy' => 'portfolio-category',
  'description' => '',
  'parent' => 0,
);

if( ERASEDEMO ) {
	themify_undo_import_term( $term );
} else {
	themify_import_term( $term );
}

$term = array (
  'term_id' => 14,
  'name' => 'Lifestyle',
  'slug' => 'lifestyle',
  'term_group' => 0,
  'taxonomy' => 'portfolio-category',
  'description' => '',
  'parent' => 0,
);

if( ERASEDEMO ) {
	themify_undo_import_term( $term );
} else {
	themify_import_term( $term );
}

$term = array (
  'term_id' => 2,
  'name' => 'Main Nav',
  'slug' => 'main-nav',
  'term_group' => 0,
  'taxonomy' => 'nav_menu',
  'description' => '',
  'parent' => 0,
);

if( ERASEDEMO ) {
	themify_undo_import_term( $term );
} else {
	themify_import_term( $term );
}

$term = array (
  'term_id' => 3,
  'name' => 'Footer Menu',
  'slug' => 'footer-menu',
  'term_group' => 0,
  'taxonomy' => 'nav_menu',
  'description' => '',
  'parent' => 0,
);

if( ERASEDEMO ) {
	themify_undo_import_term( $term );
} else {
	themify_import_term( $term );
}

$post = array (
  'ID' => 6,
  'post_date' => '2018-05-02 06:26:57',
  'post_date_gmt' => '2018-05-02 06:26:57',
  'post_content' => '<!--themify_builder_static--><ul data-id="slider-0-" data-visible="1" data-scroll="1" data-auto-scroll="off" data-speed="1" data-wrap="yes" data-arrow="yes" data-pagination="yes" data-effect="scroll" data-height="variable" data-pause-on-hover="resume" > 
 <li> <img src="https://themify.me/demo/themes/ultra-photography/files/2018/05/ethnic-flower-accesorries-1404x604.jpg" width="1404" height="604" alt="A photo album for Macy" srcset="https://themify.me/demo/themes/ultra-photography/files/2018/05/ethnic-flower-accesorries-1404x604.jpg 1404w, https://themify.me/demo/themes/ultra-photography/files/2018/05/ethnic-flower-accesorries-1402x602.jpg 1402w, https://themify.me/demo/themes/ultra-photography/files/2018/05/ethnic-flower-accesorries-1400x602.jpg 1400w" sizes="(max-width: 1404px) 100vw, 1404px" /> 
 <h3> A photo album for Macy </h3> </li> <li> <img src="https://themify.me/demo/themes/ultra-photography/files/2018/05/woman-holding-open-sign-1404x604.jpg" width="1404" height="604" alt="Hiring photo shot for a cafe" srcset="https://themify.me/demo/themes/ultra-photography/files/2018/05/woman-holding-open-sign-1404x604.jpg 1404w, https://themify.me/demo/themes/ultra-photography/files/2018/05/woman-holding-open-sign-1402x602.jpg 1402w, https://themify.me/demo/themes/ultra-photography/files/2018/05/woman-holding-open-sign-1400x602.jpg 1400w" sizes="(max-width: 1404px) 100vw, 1404px" /> 
 <h3> Hiring photo shot for a cafe </h3> </li> <li> <img src="https://themify.me/demo/themes/ultra-photography/files/2018/05/PeopleImages.com-ID1215896-1404x604.jpg" width="1404" height="604" alt="Some model shots for a piano brand" srcset="https://themify.me/demo/themes/ultra-photography/files/2018/05/PeopleImages.com-ID1215896-1404x604.jpg 1404w, https://themify.me/demo/themes/ultra-photography/files/2018/05/PeopleImages.com-ID1215896-1402x602.jpg 1402w, https://themify.me/demo/themes/ultra-photography/files/2018/05/PeopleImages.com-ID1215896-1400x602.jpg 1400w" sizes="(max-width: 1404px) 100vw, 1404px" /> 
 <h3> Some model shots for a piano brand </h3> </li> </ul> 
 
 <img src="https://themify.me/demo/themes/ultra-photography/files/2018/05/model-on-chair-604x700.jpg" width="604" height="700" alt="model-on-chair" /> 
 <h1>Ultra Photography</h1>
 <p>A photo studio by an award winning photographer, Albert McGregory.</p>
 <p>Since 2006, Albert has been taking photos for mid-size to large-size companies commercially. Lorem Ipsum has been the industry&#8217;s standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book. It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged.</p>
 
 <a href="https://themify.me/themes/ultra" >Learn more</a><!--/themify_builder_static-->',
  'post_title' => 'Home',
  'post_excerpt' => '',
  'post_name' => 'home',
  'post_modified' => '2018-05-09 18:02:06',
  'post_modified_gmt' => '2018-05-09 18:02:06',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/ultra-photography/?page_id=6',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar-none',
    'content_width' => 'full_width',
    'hide_page_title' => 'yes',
    'header_wrap' => 'transparent',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\",\\"modules\\":[{\\"mod_name\\":\\"slider\\",\\"mod_settings\\":{\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"layout_display_slider\\":\\"image\\",\\"post_type\\":\\"post\\",\\"taxonomy\\":\\"category\\",\\"blog_category_slider\\":\\"0|multiple\\",\\"portfolio_category_slider\\":\\"0|multiple\\",\\"posts_per_page_slider\\":\\"4\\",\\"order_slider\\":\\"desc\\",\\"orderby_slider\\":\\"date\\",\\"display_slider\\":\\"none\\",\\"img_content_slider\\":[{\\"img_url_slider\\":\\"https://themify.me/demo/themes/ultra-photography\\\\/files\\\\/2018\\\\/05\\\\/ethnic-flower-accesorries-1404x604.jpg\\",\\"img_title_slider\\":\\"A photo album for Macy\\"},{\\"img_url_slider\\":\\"https://themify.me/demo/themes/ultra-photography\\\\/files\\\\/2018\\\\/05\\\\/woman-holding-open-sign-1404x604.jpg\\",\\"img_title_slider\\":\\"Hiring photo shot for a cafe\\"},{\\"img_url_slider\\":\\"https://themify.me/demo/themes/ultra-photography\\\\/files\\\\/2018\\\\/05\\\\/PeopleImages.com-ID1215896-1404x604.jpg\\",\\"img_title_slider\\":\\"Some model shots for a piano brand\\"}],\\"layout_slider\\":\\"slider-caption-overlay\\",\\"img_w_slider\\":\\"1404\\",\\"img_h_slider\\":\\"604\\",\\"visible_opt_slider\\":\\"1\\",\\"auto_scroll_opt_slider\\":\\"off\\",\\"scroll_opt_slider\\":\\"1\\",\\"speed_opt_slider\\":\\"normal\\",\\"effect_slider\\":\\"scroll\\",\\"pause_on_hover_slider\\":\\"resume\\",\\"wrap_slider\\":\\"yes\\",\\"show_nav_slider\\":\\"yes\\",\\"show_arrow_slider\\":\\"yes\\",\\"show_arrow_buttons_vertical\\":\\"vertical\\",\\"left_margin_slider\\":\\"0\\",\\"right_margin_slider\\":\\"0\\",\\"height_slider\\":\\"variable\\"}}]}],\\"styling\\":{\\"background_type\\":\\"image\\",\\"background_slider_size\\":\\"large\\",\\"background_slider_mode\\":\\"fullcover\\",\\"background_video_options\\":\\"mute\\",\\"background_repeat\\":\\"repeat\\",\\"background_attachment\\":\\"scroll\\",\\"background_position\\":\\"center-center\\",\\"background_color\\":\\"#ededed\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"row_width\\":\\"fullwidth-content\\"}},{\\"row_order\\":\\"1\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col4-2\\",\\"modules\\":[{\\"mod_name\\":\\"image\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"margin_bottom\\":\\"2\\",\\"margin_bottom_unit\\":\\"em\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"style_image\\":\\"image-top\\",\\"url_image\\":\\"https://themify.me/demo/themes/ultra-photography\\\\/files\\\\/2018\\\\/05\\\\/model-on-chair.jpg\\",\\"width_image\\":\\"604\\",\\"auto_fullwidth\\":\\"1\\",\\"height_image\\":\\"700\\",\\"param_image\\":\\"regular\\"}}]},{\\"column_order\\":\\"1\\",\\"grid_class\\":\\"col4-2\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"font_size_h1\\":\\"7.5\\",\\"font_size_h1_unit\\":\\"em\\",\\"breakpoint_tablet_landscape\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"font_size_h1\\":\\"7.5\\",\\"font_size_h1_unit\\":\\"em\\"},\\"breakpoint_tablet\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"font_size_h1\\":\\"1.5\\",\\"font_size_h1_unit\\":\\"em\\"},\\"content_text\\":\\"<h1>Ultra Photography<\\\\/h1>\\"}},{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"font_family\\":\\"Playfair Display\\",\\"font_color\\":\\"#000000\\",\\"font_style_regular\\":\\"italic\\",\\"font_weight\\":\\"bold\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"content_text\\":\\"<p>A photo studio by an award winning photographer, Albert McGregory.<\\\\/p>\\\\n\\"}},{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"content_text\\":\\"<p>Since 2006, Albert has been taking photos for mid-size to large-size companies commercially. Lorem Ipsum has been the industry\\\\\\\\\\\'s standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book. It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged.<\\\\/p>\\"}},{\\"mod_name\\":\\"buttons\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"font_family\\":\\"Playfair Display\\",\\"font_style_regular\\":\\"italic\\",\\"font_weight\\":\\"bold\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"checkbox_padding_link_apply_all\\":\\"1\\",\\"checkbox_link_margin_apply_all\\":\\"1\\",\\"checkbox_link_border_apply_all\\":\\"1\\",\\"buttons_size\\":\\"normal\\",\\"buttons_style\\":\\"transparent\\",\\"display\\":\\"buttons-horizontal\\",\\"content_button\\":[{\\"label\\":\\"Learn more\\",\\"link\\":\\"https:\\\\/\\\\/themify.me\\\\/themes\\\\/ultra\\",\\"link_options\\":\\"regular\\",\\"button_color_bg\\":\\"red\\",\\"icon_alignment\\":\\"left\\"}]}}],\\"styling\\":{\\"background_type\\":\\"image\\",\\"background_slider_size\\":\\"large\\",\\"background_slider_mode\\":\\"fullcover\\",\\"background_video_options\\":\\"mute\\",\\"background_repeat\\":\\"repeat\\",\\"background_attachment\\":\\"scroll\\",\\"background_position\\":\\"center-center\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"padding_left_unit\\":\\"%\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\"}}],\\"desktop_dir\\":\\"rtl\\",\\"tablet_dir\\":\\"rtl\\",\\"tablet_landscape_dir\\":\\"rtl\\",\\"styling\\":{\\"background_type\\":\\"image\\",\\"background_slider_size\\":\\"large\\",\\"background_slider_mode\\":\\"fullcover\\",\\"background_video_options\\":\\"mute\\",\\"background_repeat\\":\\"repeat\\",\\"background_attachment\\":\\"scroll\\",\\"background_position\\":\\"center-center\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"padding_top\\":\\"5\\",\\"padding_top_unit\\":\\"%\\",\\"padding_bottom\\":\\"5\\",\\"padding_bottom_unit\\":\\"%\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\"}},{\\"row_order\\":\\"2\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\",\\"modules\\":[{\\"mod_name\\":\\"portfolio\\",\\"mod_settings\\":{\\"padding_right\\":\\"25\\",\\"padding_left\\":\\"25\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"layout_portfolio\\":\\"grid3\\",\\"type_query_portfolio\\":\\"category\\",\\"category_portfolio\\":\\"0|multiple\\",\\"portfolio_content_layout\\":\\"overlay\\",\\"post_filter\\":\\"no\\",\\"disable_masonry\\":\\"yes\\",\\"post_per_page_portfolio\\":\\"6\\",\\"order_portfolio\\":\\"desc\\",\\"orderby_portfolio\\":\\"date\\",\\"display_portfolio\\":\\"none\\",\\"img_width_portfolio\\":\\"440\\",\\"img_height_portfolio\\":\\"0\\"}}]}],\\"styling\\":{\\"background_type\\":\\"image\\",\\"background_slider_size\\":\\"large\\",\\"background_slider_mode\\":\\"fullcover\\",\\"background_video_options\\":\\"mute\\",\\"background_repeat\\":\\"repeat\\",\\"background_attachment\\":\\"scroll\\",\\"background_position\\":\\"center-center\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"breakpoint_mobile\\":{\\"background_type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"background_attachment\\":\\"scroll\\",\\"background_position\\":\\"center-center\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"padding_right\\":\\"0\\",\\"padding_left\\":\\"0\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\"},\\"row_width\\":\\"fullwidth-content\\"}},{\\"row_order\\":\\"3\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\"}]}]',
  ),
  'tax_input' => 
  array (
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 11,
  'post_date' => '2018-05-02 15:06:41',
  'post_date_gmt' => '2018-05-02 15:06:41',
  'post_content' => '<!--themify_builder_static--><h1>About</h1>
 <h1>Albert McGregory</h1>
 <p>Lorem Ipsum is simply dummy text of the printing and typesetting industry.</p>
 <p>Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry&#8217;s standard dummy text ever since the 1500s, when an un known printer took a galley of type and scrambled it to make a type specimen book. It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged.</p>
 
 <img src="https://themify.me/demo/themes/ultra-photography/files/2018/05/about-mcgregory-512x667.jpg" width="512" height="667" alt="about mcgregory" /> 
 
 <img src="https://themify.me/demo/themes/ultra-photography/files/2018/05/experience-828x560.jpg" width="828" height="560" alt="experience" srcset="https://themify.me/demo/themes/ultra-photography/files/2018/05/experience.jpg 828w, https://themify.me/demo/themes/ultra-photography/files/2018/05/experience-480x325.jpg 480w, https://themify.me/demo/themes/ultra-photography/files/2018/05/experience-768x519.jpg 768w, https://themify.me/demo/themes/ultra-photography/files/2018/05/experience-300x203.jpg 300w" sizes="(max-width: 828px) 100vw, 828px" /> 
 <h1>Skills</h1> <p><em><strong>Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry</strong></em></p> <p>Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry’s standard dummy text ever since the 1500s, when an un known printer took a galley of type and scrambled it to make a type specimen book. It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged.</p>
 
 <img src="https://themify.me/demo/themes/ultra-photography/files/2018/05/education-814x544.jpg" width="814" height="544" alt="education" srcset="https://themify.me/demo/themes/ultra-photography/files/2018/05/education.jpg 814w, https://themify.me/demo/themes/ultra-photography/files/2018/05/education-480x321.jpg 480w, https://themify.me/demo/themes/ultra-photography/files/2018/05/education-768x513.jpg 768w, https://themify.me/demo/themes/ultra-photography/files/2018/05/education-300x200.jpg 300w" sizes="(max-width: 814px) 100vw, 814px" /> 
 <h1>Education</h1> <p><em><strong>Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry</strong></em></p> <p>Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry’s standard dummy text ever since the 1500s, when an un known printer took a galley of type and scrambled it to make a type specimen book. It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged.</p>
 
 <img src="https://themify.me/demo/themes/ultra-photography/files/2018/05/gentlemen-at-roof-800x700.jpg" width="800" height="700" alt="gentlemen-at-roof" /> 
 <h1>Awards</h1> <p><em><strong>Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry.</strong></em></p> <ul> <li>2018 Best of the Photos</li> <li>Shot of Day &#8211; 2017</li> <li>Creative Photographer  &#8211; 2017</li> <li>Photo of the Year 2016</li> <li>International Photo Award &#8211; 2015</li> <li>Toronto Photography Award &#8211; 2014</li> <li>North America Photo Award &#8211; 2013</li> </ul>
 <h2>Tools</h2> <p>These are some of the tools I use.</p>
 
 <img src="https://themify.me/demo/themes/ultra-photography/files/2018/05/tool-camera.jpg" alt="Camera" /> Camera 
 
 <img src="https://themify.me/demo/themes/ultra-photography/files/2018/05/tool-imac.jpg" alt="iMac" /> iMac 
 
 <img src="https://themify.me/demo/themes/ultra-photography/files/2018/05/tool-photoshop.jpg" alt="Photoshop" /> Photoshop 
 
 <img src="https://themify.me/demo/themes/ultra-photography/files/2018/05/tool-camera-lens.jpg" alt="Camera Lens" /> Camera Lens 
 
 <img src="https://themify.me/demo/themes/ultra-photography/files/2018/05/tool-go-pro.jpg" alt="Go Pro" /> Go Pro 
 
 <img src="https://themify.me/demo/themes/ultra-photography/files/2018/05/tool-macbook-pro.jpg" alt="MacBook Pro" /> MacBook Pro<!--/themify_builder_static-->',
  'post_title' => 'About',
  'post_excerpt' => '',
  'post_name' => 'about',
  'post_modified' => '2018-05-09 16:56:19',
  'post_modified_gmt' => '2018-05-09 16:56:19',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/ultra-photography/?page_id=11',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar-none',
    'content_width' => 'full_width',
    'hide_page_title' => 'yes',
    'header_wrap' => 'transparent',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\"}],\\"styling\\":{\\"background_type\\":\\"image\\",\\"background_slider_size\\":\\"large\\",\\"background_slider_mode\\":\\"fullcover\\",\\"background_video_options\\":\\"mute\\",\\"background_image\\":\\"https://themify.me/demo/themes/ultra-photography\\\\/files\\\\/2018\\\\/05\\\\/mens-office-outfit.jpg\\",\\"background_repeat\\":\\"fullcover\\",\\"background_attachment\\":\\"scroll\\",\\"background_position\\":\\"center-center\\",\\"background_color\\":\\"#f5f5f5\\",\\"cover_color-type\\":\\"color\\",\\"cover_color\\":\\"#000000_0.59\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"padding_top\\":\\"250\\",\\"padding_bottom\\":\\"150\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\"}},{\\"row_order\\":\\"1\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col3-1\\",\\"grid_width\\":\\"25.4\\"},{\\"column_order\\":\\"1\\",\\"grid_class\\":\\"col3-1\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_color\\":\\"#ffffff\\",\\"background_repeat\\":\\"repeat\\",\\"text_align\\":\\"center\\",\\"padding_top\\":\\"20\\",\\"padding_bottom\\":\\"5\\",\\"margin_top\\":\\"-40\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"font_size_h1\\":\\"5\\",\\"font_size_h1_unit\\":\\"em\\",\\"breakpoint_mobile\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"text_align\\":\\"center\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"margin_top\\":\\"-20\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"font_size_h1\\":\\"2.4\\",\\"font_size_h1_unit\\":\\"em\\"},\\"content_text\\":\\"<h1>About<\\\\/h1>\\"}}],\\"grid_width\\":\\"41\\"},{\\"column_order\\":\\"2\\",\\"grid_class\\":\\"col3-1\\",\\"grid_width\\":\\"27.2\\"}]},{\\"row_order\\":\\"2\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col4-2\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"letter_spacing\\":\\"0\\",\\"letter_spacing_unit\\":\\"em\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"font_size_h1\\":\\"8.5\\",\\"font_size_h1_unit\\":\\"em\\",\\"breakpoint_tablet_landscape\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"letter_spacing_unit\\":\\"em\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"font_size_h1\\":\\"5\\",\\"font_size_h1_unit\\":\\"em\\"},\\"breakpoint_tablet\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"letter_spacing_unit\\":\\"em\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"font_size_h1\\":\\"1.5\\",\\"font_size_h1_unit\\":\\"em\\"},\\"breakpoint_mobile\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"letter_spacing_unit\\":\\"em\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"font_size_h1\\":\\"3.5\\",\\"font_size_h1_unit\\":\\"em\\"},\\"content_text\\":\\"<h1>Albert McGregory<\\\\/h1>\\",\\"custom_parallax_scroll_zindex\\":\\"1\\"}},{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"font_family\\":\\"Playfair Display\\",\\"font_color\\":\\"#000000\\",\\"font_style_regular\\":\\"italic\\",\\"font_weight\\":\\"bold\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"content_text\\":\\"<p>Lorem Ipsum is simply dummy text of the printing and typesetting industry.<\\\\/p>\\"}},{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"content_text\\":\\"<p>Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry\\\\\\\\\\\'s standard dummy text ever since the 1500s, when an un known printer took a galley of type and scrambled it to make a type specimen book. It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged.<\\\\/p>\\"}}],\\"styling\\":{\\"breakpoint_mobile\\":{\\"background_type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"background_attachment\\":\\"scroll\\",\\"background_position\\":\\"center-center\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"margin_bottom\\":\\"80\\",\\"checkbox_border_apply_all\\":\\"1\\"}}},{\\"column_order\\":\\"1\\",\\"grid_class\\":\\"col4-2\\",\\"modules\\":[{\\"mod_name\\":\\"image\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"style_image\\":\\"image-top\\",\\"url_image\\":\\"https://themify.me/demo/themes/ultra-photography\\\\/files\\\\/2018\\\\/05\\\\/about-mcgregory-512x667.jpg\\",\\"width_image\\":\\"512\\",\\"auto_fullwidth\\":\\"1\\",\\"height_image\\":\\"667\\",\\"param_image\\":\\"regular\\",\\"custom_parallax_scroll_speed\\":\\"1\\"}}],\\"styling\\":{\\"background_type\\":\\"image\\",\\"background_slider_size\\":\\"large\\",\\"background_slider_mode\\":\\"fullcover\\",\\"background_video_options\\":\\"mute\\",\\"background_repeat\\":\\"fullcover\\",\\"background_attachment\\":\\"scroll\\",\\"background_position\\":\\"center-center\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\"}}],\\"styling\\":{\\"background_type\\":\\"image\\",\\"background_slider_size\\":\\"large\\",\\"background_slider_mode\\":\\"fullcover\\",\\"background_video_options\\":\\"mute\\",\\"background_repeat\\":\\"fullcover\\",\\"background_attachment\\":\\"scroll\\",\\"background_position\\":\\"center-center\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"margin_top\\":\\"80\\",\\"margin_bottom\\":\\"80\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"breakpoint_mobile\\":{\\"background_type\\":\\"image\\",\\"background_repeat\\":\\"best-fit-image\\",\\"background_attachment\\":\\"scroll\\",\\"background_position\\":\\"right-bottom\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\"}}},{\\"row_order\\":\\"3\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col3-2\\",\\"modules\\":[{\\"mod_name\\":\\"image\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"margin_top\\":\\"30\\",\\"margin_bottom\\":\\"30\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"style_image\\":\\"image-top\\",\\"url_image\\":\\"https://themify.me/demo/themes/ultra-photography\\\\/files\\\\/2018\\\\/05\\\\/experience.jpg\\",\\"width_image\\":\\"828\\",\\"auto_fullwidth\\":\\"1\\",\\"height_image\\":\\"560\\",\\"param_image\\":\\"regular\\"}}],\\"styling\\":{\\"background_type\\":\\"image\\",\\"background_slider_size\\":\\"large\\",\\"background_slider_mode\\":\\"fullcover\\",\\"background_repeat\\":\\"repeat\\",\\"background_attachment\\":\\"scroll\\",\\"background_position\\":\\"center-center\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"checkbox_border_apply_all\\":\\"1\\"}},{\\"column_order\\":\\"1\\",\\"grid_class\\":\\"col3-1\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_image-gradient-angle\\":\\"90\\",\\"background_color\\":\\"#ffffff_0.90\\",\\"background_repeat\\":\\"repeat\\",\\"padding_top\\":\\"33\\",\\"padding_bottom\\":\\"26\\",\\"padding_left\\":\\"58\\",\\"margin_left\\":\\"-250\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"breakpoint_tablet\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"margin_left\\":\\"0\\",\\"checkbox_border_apply_all\\":\\"1\\"},\\"breakpoint_mobile\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"padding_right\\":\\"58\\",\\"margin_right\\":\\"0\\",\\"margin_left\\":\\"0\\",\\"checkbox_border_apply_all\\":\\"1\\"},\\"content_text\\":\\"<h1>Skills<\\\\/h1>\\\\n<p><em><strong>Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry<\\\\/strong><\\\\/em><\\\\/p>\\\\n<p>Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry’s standard dummy text ever since the 1500s, when an un known printer took a galley of type and scrambled it to make a type specimen book. It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged.<\\\\/p>\\",\\"custom_parallax_scroll_speed\\":\\"1\\",\\"custom_parallax_scroll_zindex\\":\\"1\\"}}]}],\\"column_alignment\\":\\"col_align_middle\\",\\"gutter\\":\\"gutter-none\\",\\"styling\\":{\\"background_type\\":\\"image\\",\\"background_slider_size\\":\\"large\\",\\"background_slider_mode\\":\\"fullcover\\",\\"background_repeat\\":\\"fullcover\\",\\"background_attachment\\":\\"scroll\\",\\"background_position\\":\\"center-center\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"padding_top\\":\\"3\\",\\"padding_top_unit\\":\\"%\\",\\"padding_bottom\\":\\"3\\",\\"padding_bottom_unit\\":\\"%\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\"}},{\\"row_order\\":\\"4\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col3-2\\",\\"modules\\":[{\\"mod_name\\":\\"image\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"margin_top\\":\\"30\\",\\"margin_bottom\\":\\"30\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"style_image\\":\\"image-top\\",\\"url_image\\":\\"https://themify.me/demo/themes/ultra-photography\\\\/files\\\\/2018\\\\/05\\\\/education.jpg\\",\\"width_image\\":\\"814\\",\\"auto_fullwidth\\":\\"1\\",\\"height_image\\":\\"544\\",\\"param_image\\":\\"regular\\"}}],\\"styling\\":{\\"background_type\\":\\"image\\",\\"background_slider_size\\":\\"large\\",\\"background_slider_mode\\":\\"fullcover\\",\\"background_repeat\\":\\"repeat\\",\\"background_attachment\\":\\"scroll\\",\\"background_position\\":\\"center-center\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"checkbox_border_apply_all\\":\\"1\\"}},{\\"column_order\\":\\"1\\",\\"grid_class\\":\\"col3-1\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_image-gradient-angle\\":\\"90\\",\\"background_color\\":\\"#ffffff_0.90\\",\\"background_repeat\\":\\"repeat\\",\\"padding_top\\":\\"33\\",\\"padding_right\\":\\"58\\",\\"padding_bottom\\":\\"26\\",\\"margin_right\\":\\"-250\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"breakpoint_tablet\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"padding_right\\":\\"0\\",\\"padding_left\\":\\"58\\",\\"margin_right\\":\\"0\\",\\"margin_left\\":\\"0\\",\\"checkbox_border_apply_all\\":\\"1\\"},\\"breakpoint_mobile\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"padding_right\\":\\"58\\",\\"margin_right\\":\\"0\\",\\"margin_left\\":\\"0\\",\\"checkbox_border_apply_all\\":\\"1\\"},\\"content_text\\":\\"<h1>Education<\\\\/h1>\\\\n<p><em><strong>Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry<\\\\/strong><\\\\/em><\\\\/p>\\\\n<p>Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry’s standard dummy text ever since the 1500s, when an un known printer took a galley of type and scrambled it to make a type specimen book. It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged.<\\\\/p>\\",\\"custom_parallax_scroll_speed\\":\\"1\\",\\"custom_parallax_scroll_zindex\\":\\"1\\"}}]}],\\"column_alignment\\":\\"col_align_middle\\",\\"gutter\\":\\"gutter-none\\",\\"desktop_dir\\":\\"rtl\\",\\"tablet_landscape_dir\\":\\"rtl\\",\\"styling\\":{\\"background_type\\":\\"image\\",\\"background_slider_size\\":\\"large\\",\\"background_slider_mode\\":\\"fullcover\\",\\"background_repeat\\":\\"fullcover\\",\\"background_attachment\\":\\"scroll\\",\\"background_position\\":\\"center-center\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"padding_top\\":\\"3\\",\\"padding_top_unit\\":\\"%\\",\\"padding_bottom\\":\\"3\\",\\"padding_bottom_unit\\":\\"%\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\"}},{\\"row_order\\":\\"5\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col3-2\\",\\"modules\\":[{\\"mod_name\\":\\"image\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"margin_top\\":\\"30\\",\\"margin_bottom\\":\\"30\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"style_image\\":\\"image-top\\",\\"url_image\\":\\"https://themify.me/demo/themes/ultra-photography\\\\/files\\\\/2018\\\\/05\\\\/gentlemen-at-roof.jpg\\",\\"width_image\\":\\"800\\",\\"height_image\\":\\"700\\",\\"param_image\\":\\"regular\\"}}],\\"styling\\":{\\"background_type\\":\\"image\\",\\"background_slider_size\\":\\"large\\",\\"background_slider_mode\\":\\"fullcover\\",\\"background_repeat\\":\\"repeat\\",\\"background_attachment\\":\\"scroll\\",\\"background_position\\":\\"center-center\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"checkbox_border_apply_all\\":\\"1\\"}},{\\"column_order\\":\\"1\\",\\"grid_class\\":\\"col3-1\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_image-gradient-angle\\":\\"90\\",\\"background_color\\":\\"#ffffff_0.90\\",\\"background_repeat\\":\\"repeat\\",\\"padding_top\\":\\"33\\",\\"padding_bottom\\":\\"26\\",\\"padding_left\\":\\"58\\",\\"margin_left\\":\\"-250\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"breakpoint_tablet\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"margin_left\\":\\"0\\",\\"checkbox_border_apply_all\\":\\"1\\"},\\"breakpoint_mobile\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"padding_right\\":\\"58\\",\\"margin_right\\":\\"0\\",\\"margin_left\\":\\"0\\",\\"checkbox_border_apply_all\\":\\"1\\"},\\"content_text\\":\\"<h1>Awards<\\\\/h1>\\\\n<p><em><strong>Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry.<\\\\/strong><\\\\/em><\\\\/p>\\\\n<ul>\\\\n<li>2018 Best of the Photos<\\\\/li>\\\\n<li>Shot of Day - 2017<\\\\/li>\\\\n<li>Creative Photographer  - 2017<\\\\/li>\\\\n<li>Photo of the Year 2016<\\\\/li>\\\\n<li>International Photo Award - 2015<\\\\/li>\\\\n<li>Toronto Photography Award - 2014<\\\\/li>\\\\n<li>North America Photo Award - 2013<\\\\/li>\\\\n<\\\\/ul>\\",\\"custom_parallax_scroll_speed\\":\\"1\\",\\"custom_parallax_scroll_zindex\\":\\"1\\"}}]}],\\"column_alignment\\":\\"col_align_middle\\",\\"gutter\\":\\"gutter-none\\",\\"styling\\":{\\"background_type\\":\\"image\\",\\"background_slider_size\\":\\"large\\",\\"background_slider_mode\\":\\"fullcover\\",\\"background_repeat\\":\\"fullcover\\",\\"background_attachment\\":\\"scroll\\",\\"background_position\\":\\"center-center\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"padding_top\\":\\"3\\",\\"padding_top_unit\\":\\"%\\",\\"padding_bottom\\":\\"3\\",\\"padding_bottom_unit\\":\\"%\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\"}},{\\"row_order\\":\\"6\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col3-1\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"content_text\\":\\"<h2>Tools<\\\\/h2>\\\\n<p>These are some of the tools I use.<\\\\/p>\\",\\"custom_parallax_scroll_speed\\":\\"1\\",\\"custom_parallax_scroll_reverse\\":\\"reverse\\"}}],\\"styling\\":{\\"background_type\\":\\"image\\",\\"background_slider_size\\":\\"large\\",\\"background_slider_mode\\":\\"fullcover\\",\\"background_video_options\\":\\"mute\\",\\"background_repeat\\":\\"repeat\\",\\"background_attachment\\":\\"scroll\\",\\"background_position\\":\\"center-center\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"text_align\\":\\"right\\",\\"padding_right\\":\\"5\\",\\"padding_right_unit\\":\\"%\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"border_right_color\\":\\"#dbdbdb\\",\\"border_right_width\\":\\"1\\"}},{\\"column_order\\":\\"1\\",\\"grid_class\\":\\"col3-2\\",\\"modules\\":[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col3-1\\",\\"modules\\":[{\\"mod_name\\":\\"image\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"style_image\\":\\"image-center\\",\\"url_image\\":\\"https://themify.me/demo/themes/ultra-photography\\\\/files\\\\/2018\\\\/05\\\\/tool-camera.jpg\\",\\"param_image\\":\\"regular\\",\\"caption_image\\":\\"Camera\\"}}]},{\\"column_order\\":\\"1\\",\\"grid_class\\":\\"col3-1\\",\\"modules\\":[{\\"mod_name\\":\\"image\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"style_image\\":\\"image-center\\",\\"url_image\\":\\"https://themify.me/demo/themes/ultra-photography\\\\/files\\\\/2018\\\\/05\\\\/tool-imac.jpg\\",\\"param_image\\":\\"regular\\",\\"caption_image\\":\\"iMac\\"}}]},{\\"column_order\\":\\"2\\",\\"grid_class\\":\\"col3-1\\",\\"modules\\":[{\\"mod_name\\":\\"image\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"style_image\\":\\"image-center\\",\\"url_image\\":\\"https://themify.me/demo/themes/ultra-photography\\\\/files\\\\/2018\\\\/05\\\\/tool-photoshop.jpg\\",\\"param_image\\":\\"regular\\",\\"caption_image\\":\\"Photoshop\\"}}]}]},{\\"row_order\\":\\"1\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col3-1\\",\\"modules\\":[{\\"mod_name\\":\\"image\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"style_image\\":\\"image-center\\",\\"url_image\\":\\"https://themify.me/demo/themes/ultra-photography\\\\/files\\\\/2018\\\\/05\\\\/tool-camera-lens.jpg\\",\\"param_image\\":\\"regular\\",\\"caption_image\\":\\"Camera Lens\\"}}]},{\\"column_order\\":\\"1\\",\\"grid_class\\":\\"col3-1\\",\\"modules\\":[{\\"mod_name\\":\\"image\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"style_image\\":\\"image-center\\",\\"url_image\\":\\"https://themify.me/demo/themes/ultra-photography\\\\/files\\\\/2018\\\\/05\\\\/tool-go-pro.jpg\\",\\"param_image\\":\\"regular\\",\\"caption_image\\":\\"Go Pro\\"}}]},{\\"column_order\\":\\"2\\",\\"grid_class\\":\\"col3-1\\",\\"modules\\":[{\\"mod_name\\":\\"image\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"style_image\\":\\"image-center\\",\\"url_image\\":\\"https://themify.me/demo/themes/ultra-photography\\\\/files\\\\/2018\\\\/05\\\\/tool-macbook-pro.jpg\\",\\"param_image\\":\\"regular\\",\\"caption_image\\":\\"MacBook Pro\\"}}]}]}]}],\\"styling\\":{\\"background_type\\":\\"image\\",\\"background_slider_size\\":\\"large\\",\\"background_slider_mode\\":\\"fullcover\\",\\"background_video_options\\":\\"mute\\",\\"background_repeat\\":\\"repeat\\",\\"background_attachment\\":\\"scroll\\",\\"background_position\\":\\"center-center\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"padding_top\\":\\"5\\",\\"padding_top_unit\\":\\"%\\",\\"padding_bottom\\":\\"7\\",\\"padding_bottom_unit\\":\\"%\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\"}},{\\"row_order\\":\\"7\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\"}]}]',
  ),
  'tax_input' => 
  array (
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 41,
  'post_date' => '2018-05-02 17:59:25',
  'post_date_gmt' => '2018-05-02 17:59:25',
  'post_content' => '',
  'post_title' => 'Portfolio',
  'post_excerpt' => '',
  'post_name' => 'portfolio',
  'post_modified' => '2018-05-09 17:26:32',
  'post_modified_gmt' => '2018-05-09 17:26:32',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/ultra-photography/?page_id=41',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar-none',
    'portfolio_query_category' => '0',
    'portfolio_layout' => 'grid3',
    'portfolio_content_layout' => 'overlay',
    'portfolio_disable_masonry' => 'yes',
    'portfolio_more_posts' => 'infinite',
    'portfolio_posts_per_page' => '9',
    'portfolio_display_content' => 'none',
    'portfolio_image_width' => '390',
    'portfolio_image_height' => '0',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\"}]}]',
  ),
  'tax_input' => 
  array (
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 46,
  'post_date' => '2018-05-02 18:00:23',
  'post_date_gmt' => '2018-05-02 18:00:23',
  'post_content' => '<!--themify_builder_static--><h1>Contact</h1>
 <h3>Ultra Studio</h3> <p>211 W25 Street, 4th Floor<br /> New York City 10001<br /> TEL: 278 874 7339</p>
 <h3>Opening Hours</h3> <p>Mon-Fri: 9am &#8211; 5pm<br /> Sat: 9am &#8211; 1pm<br /> Sun: Closed</p>
 <p>Have a project or an idea in mind? Contact me to book a free consultation. Or simply drop a line for suggestions and opinions. I normally reply within 24 hours.</p>
 
 <form action="https://themify.me/demo/themes/ultra-photography/wp-admin/admin-ajax.php" id="contact-0--form" method="post"> 
 <label for="contact-0--contact-name">Your Name *</label> <input type="text" name="contact-name" placeholder="" id="contact-0--contact-name" value="" required /> 
 <label for="contact-0--contact-email">Your Email *</label> <input type="text" name="contact-email" placeholder="" id="contact-0--contact-email" value="" required /> 
 <label for="contact-0--contact-subject">Subject *</label> <input type="text" name="contact-subject" placeholder="" id="contact-0--contact-subject" value="" required /> <label for="contact-0--contact-message">Message *</label> <textarea name="contact-message" placeholder="" id="contact-0--contact-message" rows="8" cols="45" required></textarea> 
 <label> <input type="checkbox" name="gdpr" value="1" required> I consent to my submitted data being collected and stored * </label> <button type="submit"> Send </button> </form><!--/themify_builder_static-->',
  'post_title' => 'Contact',
  'post_excerpt' => '',
  'post_name' => 'contact',
  'post_modified' => '2018-05-09 17:00:57',
  'post_modified_gmt' => '2018-05-09 17:00:57',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/ultra-photography/?page_id=46',
  'menu_order' => 0,
  'post_type' => 'page',
  'meta_input' => 
  array (
    'page_layout' => 'sidebar-none',
    'content_width' => 'full_width',
    'hide_page_title' => 'yes',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\"}],\\"styling\\":{\\"background_type\\":\\"image\\",\\"background_slider_size\\":\\"large\\",\\"background_slider_mode\\":\\"fullcover\\",\\"background_video_options\\":\\"mute\\",\\"background_image\\":\\"https://themify.me/demo/themes/ultra-photography\\\\/files\\\\/2018\\\\/05\\\\/man-in-front-of-computer.jpg\\",\\"background_repeat\\":\\"fullcover\\",\\"background_attachment\\":\\"scroll\\",\\"background_position\\":\\"center-center\\",\\"background_color\\":\\"#f5f5f5\\",\\"cover_color-type\\":\\"color\\",\\"cover_color\\":\\"#000000_0.59\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"padding_top\\":\\"250\\",\\"padding_bottom\\":\\"150\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\"}},{\\"row_order\\":\\"1\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col3-1\\",\\"grid_width\\":\\"25.4\\"},{\\"column_order\\":\\"1\\",\\"grid_class\\":\\"col3-1\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_color\\":\\"#ffffff\\",\\"background_repeat\\":\\"repeat\\",\\"text_align\\":\\"center\\",\\"padding_top\\":\\"20\\",\\"padding_bottom\\":\\"5\\",\\"margin_top\\":\\"-40\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"font_size_h1\\":\\"5\\",\\"font_size_h1_unit\\":\\"em\\",\\"breakpoint_mobile\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"text_align\\":\\"center\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"margin_top\\":\\"-20\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"font_size_h1\\":\\"2.4\\",\\"font_size_h1_unit\\":\\"em\\"},\\"content_text\\":\\"<h1>Contact<\\\\/h1>\\"}}],\\"grid_width\\":\\"41\\"},{\\"column_order\\":\\"2\\",\\"grid_class\\":\\"col3-1\\",\\"grid_width\\":\\"27.2\\"}]},{\\"row_order\\":\\"2\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col3-1\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"content_text\\":\\"<h3>Ultra Studio<\\\\/h3>\\\\n<p>211 W25 Street, 4th Floor<br \\\\/> New York City 10001<br \\\\/> TEL: 278 874 7339<\\\\/p>\\"}},{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"content_text\\":\\"<h3>Opening Hours<\\\\/h3>\\\\n<p>Mon-Fri: 9am - 5pm<br \\\\/> Sat: 9am - 1pm<br \\\\/> Sun: Closed<\\\\/p>\\"}}],\\"grid_width\\":\\"32.8\\",\\"styling\\":{\\"background_type\\":\\"image\\",\\"background_slider_size\\":\\"large\\",\\"background_slider_mode\\":\\"fullcover\\",\\"background_video_options\\":\\"mute\\",\\"background_repeat\\":\\"repeat\\",\\"background_attachment\\":\\"scroll\\",\\"background_position\\":\\"center-center\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"text_align\\":\\"right\\",\\"padding_right\\":\\"40\\",\\"padding_bottom_unit\\":\\"em\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"border_right_color\\":\\"#e3e3e3\\",\\"border_right_width\\":\\"1\\",\\"breakpoint_mobile\\":{\\"background_type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"background_attachment\\":\\"scroll\\",\\"background_position\\":\\"center-center\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"text_align\\":\\"left\\",\\"padding_bottom_unit\\":\\"em\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"border_right_style\\":\\"none\\"}}},{\\"column_order\\":\\"1\\",\\"grid_class\\":\\"col3-2\\",\\"modules\\":[{\\"mod_name\\":\\"text\\",\\"mod_settings\\":{\\"background_image-type\\":\\"image\\",\\"background_repeat\\":\\"repeat\\",\\"font_size\\":\\"1.3\\",\\"font_size_unit\\":\\"em\\",\\"line_height\\":\\"1.7\\",\\"line_height_unit\\":\\"em\\",\\"checkbox_padding_apply_all\\":\\"1\\",\\"margin_bottom\\":\\"50\\",\\"border_bottom_color\\":\\"#d9d9d9\\",\\"border_bottom_width\\":\\"1\\",\\"content_text\\":\\"<p>Have a project or an idea in mind? Contact me to book a free consultation. Or simply drop a line for suggestions and opinions. I normally reply within 24 hours.<\\\\/p>\\"}},{\\"mod_name\\":\\"contact\\",\\"mod_settings\\":{\\"checkbox_padding_apply_all\\":\\"1\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\",\\"checkbox_border_inputs_apply_all\\":\\"1\\",\\"checkbox_border_send_apply_all\\":\\"1\\",\\"checkbox_padding_success_message_apply_all\\":\\"1\\",\\"checkbox_margin_success_message_apply_all\\":\\"1\\",\\"checkbox_border_success_message_apply_all\\":\\"1\\",\\"checkbox_padding_error_message_apply_all\\":\\"1\\",\\"checkbox_margin_error_message_apply_all\\":\\"1\\",\\"checkbox_border_error_message_apply_all\\":\\"1\\",\\"layout_contact\\":\\"style2\\",\\"mail_contact\\":\\"info@themify.me\\",\\"gdpr\\":\\"accept\\",\\"field_name_label\\":\\"Your Name\\",\\"field_email_label\\":\\"Your Email\\",\\"field_subject_label\\":\\"Subject\\",\\"field_subject_require\\":\\"yes\\",\\"field_subject_active\\":\\"yes\\",\\"field_message_label\\":\\"Message\\",\\"field_extra\\":\\"{ \\\\\\\\\\\\\\"fields\\\\\\\\\\\\\\": [] }\\",\\"field_sendcopy_label\\":\\"Send a copy to myself\\",\\"field_order\\":\\"{}\\",\\"field_send_label\\":\\"Send\\",\\"field_send_align\\":\\"left\\"}}],\\"grid_width\\":\\"64\\"}],\\"col_mobile\\":\\"column-full\\",\\"styling\\":{\\"background_type\\":\\"image\\",\\"background_slider_size\\":\\"large\\",\\"background_slider_mode\\":\\"fullcover\\",\\"background_video_options\\":\\"mute\\",\\"background_repeat\\":\\"repeat\\",\\"background_attachment\\":\\"scroll\\",\\"background_position\\":\\"center-center\\",\\"cover_color-type\\":\\"color\\",\\"cover_color_hover-type\\":\\"hover_color\\",\\"padding_bottom\\":\\"8\\",\\"padding_bottom_unit\\":\\"%\\",\\"checkbox_margin_apply_all\\":\\"1\\",\\"checkbox_border_apply_all\\":\\"1\\"}},{\\"row_order\\":\\"3\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\"}]}]',
  ),
  'tax_input' => 
  array (
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 65,
  'post_date' => '2018-05-02 18:53:45',
  'post_date_gmt' => '2018-05-02 18:53:45',
  'post_content' => 'Duis dignissim curae; neque per mi placerat nibh tortor, venenatis diam litora. Eget ridiculus risus vehicula aliquet et ullamcorper porttitor magnis. Magnis, magnis metus vehicula. Tellus aptent elementum aliquet faucibus phasellus nostra sociosqu amet sapien in hendrerit. Massa pellentesque phasellus, ad leo euismod. Rutrum dictumst in facilisi. Pretium auctor dignissim lacus ridiculus lobortis etiam commodo sapien pellentesque lorem varius? Malesuada class class pretium torquent consequat praesent venenatis! Elementum sit molestie mi curae; cursus eget lacinia feugiat. Tellus nec interdum nisi quis lacinia a nibh.',
  'post_title' => 'Fashion Collection',
  'post_excerpt' => '',
  'post_name' => 'fashion-collection',
  'post_modified' => '2018-05-09 16:02:15',
  'post_modified_gmt' => '2018-05-09 16:02:15',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/ultra-photography/?post_type=portfolio&#038;p=65',
  'menu_order' => 0,
  'post_type' => 'portfolio',
  'meta_input' => 
  array (
    'post_layout' => 'gallery',
    'post_layout_gallery' => '[gallery size="full" ids="174,175,176,177,178,179,180,181,182,183"]',
    'project_date' => 'May 2018',
    'project_client' => 'Sportsman',
    'project_services' => 'Photography',
    'project_launch' => 'https://themify.me',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\"}]}]',
  ),
  'tax_input' => 
  array (
    'portfolio-category' => 'lifestyle',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 54,
  'post_date' => '2018-04-29 18:20:51',
  'post_date_gmt' => '2018-04-29 18:20:51',
  'post_content' => 'In pretium posuere elit sed lobortis. Praesent finibus ultrices augue, eget blandit mauris. Duis pulvinar, quam ut tristique euismod, velit turpis dignissim massa, et venenatis leo justo id urna. Aliquam arcu ante, sagittis eu rutrum a, efficitur eget nibh. In venenatis metus est, a sagittis turpis cursus quis. Fusce odio neque, placerat ut porttitor eget, congue vestibulum purus.',
  'post_title' => 'Summer Time',
  'post_excerpt' => '',
  'post_name' => 'summer-time',
  'post_modified' => '2018-05-04 06:23:16',
  'post_modified_gmt' => '2018-05-04 06:23:16',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/ultra-photography/?post_type=portfolio&#038;p=54',
  'menu_order' => 0,
  'post_type' => 'portfolio',
  'meta_input' => 
  array (
    'post_layout' => 'slider',
    'post_layout_slider' => '[gallery size="full" columns="1" ids="206,207,205"]',
    'project_date' => 'April 2018',
    'project_client' => 'Susan Morgan',
    'project_services' => 'Photography, Photo Edit',
    'project_launch' => 'https://themify.me/',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\"}]}]',
  ),
  'tax_input' => 
  array (
    'portfolio-category' => 'lifestyle',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 56,
  'post_date' => '2018-04-27 18:19:17',
  'post_date_gmt' => '2018-04-27 18:19:17',
  'post_content' => 'Duis dignissim curae; neque per mi placerat nibh tortor, venenatis diam litora. Eget ridiculus risus vehicula aliquet et ullamcorper porttitor magnis. Magnis, magnis metus vehicula. Tellus aptent elementum aliquet faucibus phasellus nostra sociosqu amet sapien in hendrerit. Massa pellentesque phasellus, ad leo euismod. Rutrum dictumst in facilisi. Pretium auctor dignissim lacus ridiculus lobortis etiam commodo sapien pellentesque lorem varius? Malesuada class class pretium torquent consequat praesent venenatis! Elementum sit molestie mi curae; cursus eget lacinia feugiat. Tellus nec interdum nisi quis lacinia a nibh.',
  'post_title' => 'Keep it Cool',
  'post_excerpt' => '',
  'post_name' => 'keep-it-cool',
  'post_modified' => '2018-05-03 08:11:37',
  'post_modified_gmt' => '2018-05-03 08:11:37',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/ultra-photography/?post_type=portfolio&#038;p=56',
  'menu_order' => 0,
  'post_type' => 'portfolio',
  'meta_input' => 
  array (
    'project_date' => 'April 2018',
    'project_client' => 'CityMan',
    'project_services' => 'Photography, Photo Edit',
    'project_launch' => 'https://themify.me/',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\"}]}]',
  ),
  'tax_input' => 
  array (
    'portfolio-category' => 'fashion',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 58,
  'post_date' => '2018-04-28 18:21:58',
  'post_date_gmt' => '2018-04-28 18:21:58',
  'post_content' => 'Sed egestas, ante et vulputate volutpat, eros pede semper est, vitae luctus metus libero eu augue. Morbi purus libero, faucibus adipiscing, commodo quis, gravida id, est. Sed lectus. Praesent elementum hendrerit tortor. Sed semper lorem at felis. Vestibulum volutpat, lacus a ultrices sagittis, mi neque euismod dui, eu pulvinar nunc sapien ornare nisl. Phasellus pede arcu, dapibus eu, fermentum et, dapibus sed, urna.

Morbi interdum mollis sapien. Sed ac risus. Phasellus lacinia, magna a ullamcorper laoreet, lectus arcu pulvinar risus, vitae facilisis libero dolor a purus. Sed vel lacus. Mauris nibh felis, adipiscing varius, adipiscing in, lacinia vel, tellus. Suspendisse ac urna. Etiam pellentesque mauris ut lectus. Nunc tellus ante, mattis eget, gravida vitae, ultricies ac, leo. Integer leo pede, ornare a, lacinia eu, vulputate vel, nisl.',
  'post_title' => 'Working Spirit',
  'post_excerpt' => '',
  'post_name' => 'working-spirit',
  'post_modified' => '2018-05-04 06:23:17',
  'post_modified_gmt' => '2018-05-04 06:23:17',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/ultra-photography/?post_type=portfolio&#038;p=58',
  'menu_order' => 0,
  'post_type' => 'portfolio',
  'meta_input' => 
  array (
    'project_date' => 'April 2018',
    'project_client' => 'Gentleman Wear',
    'project_services' => 'Photography, Photo Edit, Creative Styling',
    'project_launch' => 'https://themify.me/',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\"}]}]',
  ),
  'tax_input' => 
  array (
    'portfolio-category' => 'fashion',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 61,
  'post_date' => '2018-04-30 18:49:31',
  'post_date_gmt' => '2018-04-30 18:49:31',
  'post_content' => 'Donec nec justo eget felis facilisis fermentum. Aliquam porttitor mauris sit amet orci. Aenean dignissim pellentesque felis.

Morbi in sem quis dui placerat ornare. Pellentesque odio nisi, euismod in, pharetra a, ultricies in, diam. Sed arcu. Cras consequat.

Praesent dapibus, neque id cursus faucibus, tortor neque egestas auguae, eu vulputate magna eros eu erat. Aliquam erat volutpat. Nam dui mi, tincidunt quis, accumsan porttitor, facilisis luctus, metus.

Phasellus ultrices nulla quis nibh. Quisque a lectus. Donec consectetuer ligula vulputate sem tristique cursus. Nam nulla quam, gravida non, commodo a, sodales sit amet, nisi.',
  'post_title' => 'Young Models',
  'post_excerpt' => '',
  'post_name' => 'young-models',
  'post_modified' => '2018-05-09 16:03:54',
  'post_modified_gmt' => '2018-05-09 16:03:54',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/ultra-photography/?post_type=portfolio&#038;p=61',
  'menu_order' => 0,
  'post_type' => 'portfolio',
  'meta_input' => 
  array (
    'post_layout' => 'gallery',
    'post_layout_gallery' => '[gallery size="large" ids="195,196,197,198,199,200,201,202,203,204"]',
    'project_date' => 'April 2018',
    'project_client' => 'Summer Style.inc',
    'project_services' => 'Photography, Creative Styling',
    'project_launch' => 'https://themify.me/',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\"}]}]',
  ),
  'tax_input' => 
  array (
    'portfolio-category' => 'lifestyle',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 63,
  'post_date' => '2018-05-01 18:51:34',
  'post_date_gmt' => '2018-05-01 18:51:34',
  'post_content' => 'Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Phasellus hendrerit. Pellentesque aliquet nibh nec urna. In nisi neque, aliquet vel, dapibus id, mattis vel, nisi. Sed pretium, ligula sollicitudin laoreet viverra, tortor libero sodales leo, eget blandit nunc tortor eu nibh. Nullam mollis. Ut justo. Suspendisse potenti.

Sed egestas, ante et vulputate volutpat, eros pede semper est, vitae luctus metus libero eu augue. Morbi purus libero, faucibus adipiscing, commodo quis, gravida id, est. Sed lectus. Praesent elementum hendrerit tortor. Sed semper lorem at felis. Vestibulum volutpat, lacus a ultrices sagittis, mi neque euismod dui, eu pulvinar nunc sapien ornare nisl. Phasellus pede arcu, dapibus eu, fermentum et, dapibus sed, urna.

Morbi interdum mollis sapien. Sed ac risus. Phasellus lacinia, magna a ullamcorper laoreet, lectus arcu pulvinar risus, vitae facilisis libero dolor a purus. Sed vel lacus. Mauris nibh felis, adipiscing varius, adipiscing in, lacinia vel, tellus. Suspendisse ac urna. Etiam pellentesque mauris ut lectus. Nunc tellus ante, mattis eget, gravida vitae, ultricies ac, leo. Integer leo pede, ornare a, lacinia eu, vulputate vel, nisl.',
  'post_title' => 'People Series',
  'post_excerpt' => '',
  'post_name' => 'people-series',
  'post_modified' => '2018-05-09 16:02:59',
  'post_modified_gmt' => '2018-05-09 16:02:59',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/ultra-photography/?post_type=portfolio&#038;p=63',
  'menu_order' => 0,
  'post_type' => 'portfolio',
  'meta_input' => 
  array (
    'post_layout' => 'gallery',
    'post_layout_gallery' => '[gallery size="large" ids="184,185,186,187,188,189,190,191,192,193,194"]',
    'project_date' => 'May 2018',
    'project_client' => 'Bella Luna',
    'project_services' => 'Photography, Photo Edit',
    'project_launch' => 'https://themify.me/',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\"}]}]',
  ),
  'tax_input' => 
  array (
    'portfolio-category' => 'fashion',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 166,
  'post_date' => '2018-04-14 07:34:46',
  'post_date_gmt' => '2018-04-14 07:34:46',
  'post_content' => 'Praesent luctus, neque dictum feugiat maximus, sem enim maximus ipsum, sed ullamcorper est urna suscipit massa. Cras commodo eros nec eleifend vehicula. Praesent auctor augue in massa porta gravida. Nullam et ex eget diam mollis hendrerit id dignissim sem. Suspendisse viverra nibh a fringilla viverra.

Nulla facilisi. Praesent luctus, neque dictum feugiat maximus, sem enim maximus ipsum, sed ullamcorper est urna suscipit massa. Cras commodo eros nec eleifend vehicula. Praesent auctor augue in massa porta gravida. Nullam et ex eget diam mollis hendrerit id dignissim sem. Suspendisse viverra nibh a fringilla viverra.',
  'post_title' => 'Working Space',
  'post_excerpt' => '',
  'post_name' => 'working-space',
  'post_modified' => '2018-05-03 08:13:05',
  'post_modified_gmt' => '2018-05-03 08:13:05',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/ultra-photography/?post_type=portfolio&#038;p=166',
  'menu_order' => 0,
  'post_type' => 'portfolio',
  'meta_input' => 
  array (
    'project_date' => 'April 2018',
    'project_client' => 'Gentleman Wear',
    'project_services' => 'Photography, Creative Styling',
    'project_launch' => 'https://themify.me/',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\"}]}]',
  ),
  'tax_input' => 
  array (
    'portfolio-category' => 'lifestyle',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 140,
  'post_date' => '2018-04-26 06:48:18',
  'post_date_gmt' => '2018-04-26 06:48:18',
  'post_content' => 'Aliquam arcu ante, sagittis eu rutrum a, efficitur eget nibh. In venenatis metus est, a sagittis turpis cursus quis. Fusce odio neque, placerat ut porttitor eget, congue vestibulum purus.',
  'post_title' => 'Jump High',
  'post_excerpt' => '',
  'post_name' => 'jump-high',
  'post_modified' => '2018-05-03 08:11:39',
  'post_modified_gmt' => '2018-05-03 08:11:39',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/ultra-photography/?post_type=portfolio&#038;p=140',
  'menu_order' => 0,
  'post_type' => 'portfolio',
  'meta_input' => 
  array (
    'project_date' => 'April 2018',
    'project_client' => 'Bella Luna',
    'project_services' => 'Photography, Photo Edit, Creative Styling',
    'project_launch' => 'https://themify.me/',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\"}]}]',
  ),
  'tax_input' => 
  array (
    'portfolio-category' => 'fashion',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 142,
  'post_date' => '2018-04-25 07:08:41',
  'post_date_gmt' => '2018-04-25 07:08:41',
  'post_content' => 'Aliquam arcu ante, sagittis eu rutrum a, efficitur eget nibh. In venenatis metus est, a sagittis turpis cursus quis. Fusce odio neque, placerat ut porttitor eget, congue vestibulum purus. In pretium posuere elit sed lobortis. Praesent finibus ultrices augue, eget blandit mauris. Duis pulvinar, quam ut tristique euismod, velit turpis dignissim massa, et venenatis leo justo id urna.',
  'post_title' => 'I am A Gentleman',
  'post_excerpt' => '',
  'post_name' => 'i-am-a-gentleman',
  'post_modified' => '2018-05-03 08:11:47',
  'post_modified_gmt' => '2018-05-03 08:11:47',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/ultra-photography/?post_type=portfolio&#038;p=142',
  'menu_order' => 0,
  'post_type' => 'portfolio',
  'meta_input' => 
  array (
    'project_date' => 'April 2018',
    'project_client' => 'Kids Fashion',
    'project_services' => 'Photography, Creative Styling',
    'project_launch' => 'https://themify.me/',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\"}]}]',
  ),
  'tax_input' => 
  array (
    'portfolio-category' => 'fashion',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 144,
  'post_date' => '2018-04-24 07:12:38',
  'post_date_gmt' => '2018-04-24 07:12:38',
  'post_content' => 'Donec accumsan ipsum nec dolor aliquam pretium. Suspendisse consequat condimentum augue, vel ultricies quam efficitur quis. Aenean sapien turpis, tincidunt congue risus ac, eleifend condimentum nunc. Quisque porta nulla erat, et maximus nisi venenatis at.',
  'post_title' => 'Life Is Free',
  'post_excerpt' => '',
  'post_name' => 'life-is-free',
  'post_modified' => '2018-05-03 08:11:49',
  'post_modified_gmt' => '2018-05-03 08:11:49',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/ultra-photography/?post_type=portfolio&#038;p=144',
  'menu_order' => 0,
  'post_type' => 'portfolio',
  'meta_input' => 
  array (
    'project_date' => 'April 2018',
    'project_client' => 'Michael Ohara',
    'project_services' => 'Photography, Photo Edit',
    'project_launch' => 'https://themify.me/',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\"}]}]',
  ),
  'tax_input' => 
  array (
    'portfolio-category' => 'lifestyle',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 146,
  'post_date' => '2018-04-23 07:21:06',
  'post_date_gmt' => '2018-04-23 07:21:06',
  'post_content' => 'Donec nec justo eget felis facilisis fermentum. Aliquam porttitor mauris sit amet orci. Aenean dignissim pellentesque felis.

Morbi in sem quis dui placerat ornare. Pellentesque odio nisi, euismod in, pharetra a, ultricies in, diam. Sed arcu. Cras consequat.

Praesent dapibus, neque id cursus faucibus, tortor neque egestas auguae, eu vulputate magna eros eu erat. Aliquam erat volutpat. Nam dui mi, tincidunt quis, accumsan porttitor, facilisis luctus, metus. Phasellus ultrices nulla quis nibh. Quisque a lectus. Donec consectetuer ligula vulputate sem tristique cursus. Nam nulla quam, gravida non, commodo a, sodales sit amet, nisi.',
  'post_title' => 'Skater Girl',
  'post_excerpt' => '',
  'post_name' => 'skater-girl',
  'post_modified' => '2018-05-03 08:11:55',
  'post_modified_gmt' => '2018-05-03 08:11:55',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/ultra-photography/?post_type=portfolio&#038;p=146',
  'menu_order' => 0,
  'post_type' => 'portfolio',
  'meta_input' => 
  array (
    'project_date' => 'April 2018',
    'project_client' => 'Watch Me',
    'project_services' => 'Photography, Photo Edit, Creative Styling',
    'project_launch' => 'https://themify.me/',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\"}]}]',
  ),
  'tax_input' => 
  array (
    'portfolio-category' => 'lifestyle',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 148,
  'post_date' => '2018-04-21 07:23:51',
  'post_date_gmt' => '2018-04-21 07:23:51',
  'post_content' => 'Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Phasellus hendrerit. Pellentesque aliquet nibh nec urna. In nisi neque, aliquet vel, dapibus id, mattis vel, nisi. Sed pretium, ligula sollicitudin laoreet viverra, tortor libero sodales leo, eget blandit nunc tortor eu nibh. Nullam mollis. Ut justo. Suspendisse potenti.

Sed egestas, ante et vulputate volutpat, eros pede semper est, vitae luctus metus libero eu augue. Morbi purus libero, faucibus adipiscing, commodo quis, gravida id, est. Sed lectus. Praesent elementum hendrerit tortor. Sed semper lorem at felis. Vestibulum volutpat, lacus a ultrices sagittis, mi neque euismod dui, eu pulvinar nunc sapien ornare nisl. Phasellus pede arcu, dapibus eu, fermentum et, dapibus sed, urna.

Morbi interdum mollis sapien. Sed ac risus. Phasellus lacinia, magna a ullamcorper laoreet, lectus arcu pulvinar risus, vitae facilisis libero dolor a purus. Sed vel lacus. Mauris nibh felis, adipiscing varius, adipiscing in, lacinia vel, tellus. Suspendisse ac urna. Etiam pellentesque mauris ut lectus. Nunc tellus ante, mattis eget, gravida vitae, ultricies ac, leo. Integer leo pede, ornare a, lacinia eu, vulputate vel, nisl.',
  'post_title' => 'Choose Your Style',
  'post_excerpt' => '',
  'post_name' => 'choose-your-style',
  'post_modified' => '2018-05-03 08:12:02',
  'post_modified_gmt' => '2018-05-03 08:12:02',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/ultra-photography/?post_type=portfolio&#038;p=148',
  'menu_order' => 0,
  'post_type' => 'portfolio',
  'meta_input' => 
  array (
    'project_date' => 'April 2018',
    'project_client' => 'Hers',
    'project_services' => 'Photography, Creative Styling',
    'project_launch' => 'https://themify.me/',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\"}]}]',
  ),
  'tax_input' => 
  array (
    'portfolio-category' => 'fashion',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 151,
  'post_date' => '2018-04-20 07:25:16',
  'post_date_gmt' => '2018-04-20 07:25:16',
  'post_content' => 'Donec nec justo eget felis facilisis fermentum. Aliquam porttitor mauris sit amet orci. Aenean dignissim pellentesque felis.

Morbi in sem quis dui placerat ornare. Pellentesque odio nisi, euismod in, pharetra a, ultricies in, diam. Sed arcu. Cras consequat.

Praesent dapibus, neque id cursus faucibus, tortor neque egestas auguae, eu vulputate magna eros eu erat. Aliquam erat volutpat. Nam dui mi, tincidunt quis, accumsan porttitor, facilisis luctus, metus.

Phasellus ultrices nulla quis nibh. Quisque a lectus. Donec consectetuer ligula vulputate sem tristique cursus. Nam nulla quam, gravida non, commodo a, sodales sit amet, nisi.',
  'post_title' => 'I Will Be There',
  'post_excerpt' => '',
  'post_name' => 'i-will-be-there',
  'post_modified' => '2018-05-03 08:12:38',
  'post_modified_gmt' => '2018-05-03 08:12:38',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/ultra-photography/?post_type=portfolio&#038;p=151',
  'menu_order' => 0,
  'post_type' => 'portfolio',
  'meta_input' => 
  array (
    'project_date' => 'April 2018',
    'project_client' => 'Gentleman Wear',
    'project_services' => 'Photography, Photo Edit, Creative Styling',
    'project_launch' => 'https://themify.me/',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\"}]}]',
  ),
  'tax_input' => 
  array (
    'portfolio-category' => 'lifestyle',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 154,
  'post_date' => '2018-04-19 07:26:28',
  'post_date_gmt' => '2018-04-19 07:26:28',
  'post_content' => 'Duis dignissim curae; neque per mi placerat nibh tortor, venenatis diam litora. Eget ridiculus risus vehicula aliquet et ullamcorper porttitor magnis. Magnis, magnis metus vehicula. Tellus aptent elementum aliquet faucibus phasellus nostra sociosqu amet sapien in hendrerit. Massa pellentesque phasellus, ad leo euismod. Rutrum dictumst in facilisi. Pretium auctor dignissim lacus ridiculus lobortis etiam commodo sapien pellentesque lorem varius? Malesuada class class pretium torquent consequat praesent venenatis! Elementum sit molestie mi curae; cursus eget lacinia feugiat. Tellus nec interdum nisi quis lacinia a nibh.
<div id="themify_builder_content-219" class="themify_builder_content themify_builder_content-219 themify_builder" data-postid="219"></div>',
  'post_title' => 'My Life My Rules My Style',
  'post_excerpt' => '',
  'post_name' => 'my-life-my-rules-my-style',
  'post_modified' => '2018-05-03 08:12:44',
  'post_modified_gmt' => '2018-05-03 08:12:44',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/ultra-photography/?post_type=portfolio&#038;p=154',
  'menu_order' => 0,
  'post_type' => 'portfolio',
  'meta_input' => 
  array (
    'project_date' => 'April 2018',
    'project_client' => 'Gentleman Wear',
    'project_services' => 'Photography, Photo Edit, Creative Styling',
    'project_launch' => 'https://themify.me/',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\"}]}]',
  ),
  'tax_input' => 
  array (
    'portfolio-category' => 'lifestyle',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 157,
  'post_date' => '2018-04-18 07:27:57',
  'post_date_gmt' => '2018-04-18 07:27:57',
  'post_content' => '<div id="lipsum">

Nulla tortor ex, sodales id mollis ac, feugiat sit amet leo. Suspendisse porttitor laoreet neque, et bibendum lacus euismod id. In tincidunt, tortor vel fringilla elementum, magna purus lacinia ante, id egestas nisi justo vel eros. Pellentesque orci lorem, accumsan sed aliquam sed, pretium sed nunc. Maecenas consequat, justo sed rutrum sollicitudin, velit ante ultricies ante, et euismod arcu purus et leo. Morbi pretium non ex ut volutpat. Lorem ipsum dolor sit amet, consectetur adipiscing elit. In consequat rutrum nisl quis condimentum. Sed eget tincidunt nulla, posuere elementum ligula. Nulla facilisi. Praesent luctus, neque dictum feugiat maximus, sem enim maximus ipsum, sed ullamcorper est urna suscipit massa. Cras commodo eros nec eleifend vehicula. Praesent auctor augue in massa porta gravida. Nullam et ex eget diam mollis hendrerit id dignissim sem. Suspendisse viverra nibh a fringilla viverra.

</div>
&nbsp;',
  'post_title' => 'Thinking Seriously',
  'post_excerpt' => '',
  'post_name' => 'thinking-seriously',
  'post_modified' => '2018-05-03 08:12:46',
  'post_modified_gmt' => '2018-05-03 08:12:46',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/ultra-photography/?post_type=portfolio&#038;p=157',
  'menu_order' => 0,
  'post_type' => 'portfolio',
  'meta_input' => 
  array (
    'project_date' => 'April 2018',
    'project_client' => 'Kids Fashion',
    'project_services' => 'Photography, Creative Styling',
    'project_launch' => 'https://themify.me/',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\"}]}]',
  ),
  'tax_input' => 
  array (
    'portfolio-category' => 'lifestyle',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 160,
  'post_date' => '2018-04-17 07:29:27',
  'post_date_gmt' => '2018-04-17 07:29:27',
  'post_content' => 'Donec accumsan ipsum nec dolor aliquam pretium. Suspendisse consequat condimentum augue, vel ultricies quam efficitur quis. Aenean sapien turpis, tincidunt congue risus ac, eleifend condimentum nunc. Quisque porta nulla erat, et maximus nisi venenatis at. Aliquam arcu ante, sagittis eu rutrum a, efficitur eget nibh. In venenatis metus est, a sagittis turpis cursus quis. Fusce odio neque, placerat ut porttitor eget, congue vestibulum purus. In pretium posuere elit sed lobortis. Praesent finibus ultrices augue, eget blandit mauris. Duis pulvinar, quam ut tristique euismod, velit turpis dignissim massa, et venenatis leo justo id urna.',
  'post_title' => 'My New Bag',
  'post_excerpt' => '',
  'post_name' => 'my-new-bag',
  'post_modified' => '2018-05-03 08:12:51',
  'post_modified_gmt' => '2018-05-03 08:12:51',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/ultra-photography/?post_type=portfolio&#038;p=160',
  'menu_order' => 0,
  'post_type' => 'portfolio',
  'meta_input' => 
  array (
    'project_date' => 'April 2018',
    'project_client' => 'Gentleman Wear',
    'project_services' => 'Photography, Creative Styling',
    'project_launch' => 'https://themify.me/',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\"}]}]',
  ),
  'tax_input' => 
  array (
    'portfolio-category' => 'fashion',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 162,
  'post_date' => '2018-04-16 07:30:38',
  'post_date_gmt' => '2018-04-16 07:30:38',
  'post_content' => '<div id="lipsum">

Nulla tortor ex, sodales id mollis ac, feugiat sit amet leo. Suspendisse porttitor laoreet neque, et bibendum lacus euismod id. In tincidunt, tortor vel fringilla elementum, magna purus lacinia ante, id egestas nisi justo vel eros. Pellentesque orci lorem, accumsan sed aliquam sed, pretium sed nunc. Maecenas consequat, justo sed rutrum sollicitudin, velit ante ultricies ante, et euismod arcu purus et leo. Morbi pretium non ex ut volutpat. Lorem ipsum dolor sit amet, consectetur adipiscing elit. In consequat rutrum nisl quis condimentum. Sed eget tincidunt nulla, posuere elementum ligula. Nulla facilisi. Praesent luctus, neque dictum feugiat maximus, sem enim maximus ipsum, sed ullamcorper est urna suscipit massa. Cras commodo eros nec eleifend vehicula. Praesent auctor augue in massa porta gravida. Nullam et ex eget diam mollis hendrerit id dignissim sem. Suspendisse viverra nibh a fringilla viverra.

</div>',
  'post_title' => 'Happiness',
  'post_excerpt' => '',
  'post_name' => 'happiness',
  'post_modified' => '2018-05-03 08:12:56',
  'post_modified_gmt' => '2018-05-03 08:12:56',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/ultra-photography/?post_type=portfolio&#038;p=162',
  'menu_order' => 0,
  'post_type' => 'portfolio',
  'meta_input' => 
  array (
    'project_date' => 'April 2018',
    'project_client' => 'Kids Fashion',
    'project_services' => 'Photography, Creative Styling',
    'project_launch' => 'https://themify.me/',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\"}]}]',
  ),
  'tax_input' => 
  array (
    'portfolio-category' => 'fashion',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 164,
  'post_date' => '2018-04-15 07:31:52',
  'post_date_gmt' => '2018-04-15 07:31:52',
  'post_content' => 'Aliquam arcu ante, sagittis eu rutrum a, efficitur eget nibh. In venenatis metus est, a sagittis turpis cursus quis. Fusce odio neque, placerat ut porttitor eget, congue vestibulum purus. In pretium posuere elit sed lobortis. Praesent finibus ultrices augue, eget blandit mauris. Duis pulvinar, quam ut tristique euismod, velit turpis dignissim massa, et venenatis leo justo id urna.
<div id="themify_builder_content-201" class="themify_builder_content themify_builder_content-201 themify_builder" data-postid="201"></div>',
  'post_title' => 'Glamours Girl',
  'post_excerpt' => '',
  'post_name' => 'glamours-girl',
  'post_modified' => '2018-05-03 08:12:59',
  'post_modified_gmt' => '2018-05-03 08:12:59',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/ultra-photography/?post_type=portfolio&#038;p=164',
  'menu_order' => 0,
  'post_type' => 'portfolio',
  'meta_input' => 
  array (
    'project_date' => 'April 2018',
    'project_client' => 'Bella Luna',
    'project_services' => 'Photography, Creative Styling',
    'project_launch' => 'https://themify.me/',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\"}]}]',
  ),
  'tax_input' => 
  array (
    'portfolio-category' => 'fashion',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 168,
  'post_date' => '2018-04-13 07:36:19',
  'post_date_gmt' => '2018-04-13 07:36:19',
  'post_content' => 'Sedd aliquam sed, pretium sed nunc. Maecenas consequat, justo sed rutrum sollicitudin, velit ante ultricies ante, et euismod arcu purus et leo. Morbi pretium non ex ut volutpat. Lorem ipsum dolor sit amet, consectetur adipiscing elit. In consequat rutrum nisl quis condimentum. Sed eget tincidunt nulla, posuere elementum ligula.<span id="more-213"></span>',
  'post_title' => 'The Style of Metro',
  'post_excerpt' => '',
  'post_name' => 'the-style-of-metro',
  'post_modified' => '2018-05-03 08:13:10',
  'post_modified_gmt' => '2018-05-03 08:13:10',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/ultra-photography/?post_type=portfolio&#038;p=168',
  'menu_order' => 0,
  'post_type' => 'portfolio',
  'meta_input' => 
  array (
    'project_date' => 'April 2018',
    'project_client' => 'Gentleman Wear',
    'project_services' => 'Photography, Photo Edit, Creative Styling',
    'project_launch' => 'https://themify.me/',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\"}]}]',
  ),
  'tax_input' => 
  array (
    'portfolio-category' => 'fashion',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 170,
  'post_date' => '2018-04-11 07:45:42',
  'post_date_gmt' => '2018-04-11 07:45:42',
  'post_content' => 'Praesent luctus, neque dictum feugiat maximus, sem enim maximus ipsum, sed ullamcorper est urna suscipit massa. Cras commodo eros nec eleifend vehicula. Praesent auctor augue in massa porta gravida. Nullam et ex eget diam mollis hendrerit id dignissim sem. Suspendisse viverra nibh a fringilla viverra.',
  'post_title' => 'On Blue Dress',
  'post_excerpt' => '',
  'post_name' => 'on-blue-dress',
  'post_modified' => '2018-05-03 08:13:15',
  'post_modified_gmt' => '2018-05-03 08:13:15',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/ultra-photography/?post_type=portfolio&#038;p=170',
  'menu_order' => 0,
  'post_type' => 'portfolio',
  'meta_input' => 
  array (
    'project_date' => 'April 2018',
    'project_client' => 'Bella Luna',
    'project_services' => 'Photography, Photo Edit, Creative Styling',
    'project_launch' => 'https://themify.me/',
    '_themify_builder_settings_json' => '[{\\"row_order\\":\\"0\\",\\"cols\\":[{\\"column_order\\":\\"0\\",\\"grid_class\\":\\"col-full\\"}]}]',
  ),
  'tax_input' => 
  array (
    'portfolio-category' => 'fashion',
  ),
  'has_thumbnail' => true,
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 45,
  'post_date' => '2018-05-02 18:00:00',
  'post_date_gmt' => '2018-05-02 18:00:00',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '45',
  'post_modified' => '2018-05-02 18:00:48',
  'post_modified_gmt' => '2018-05-02 18:00:48',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/ultra-photography/?p=45',
  'menu_order' => 1,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'post_type',
    '_menu_item_menu_item_parent' => '0',
    '_menu_item_object_id' => '6',
    '_menu_item_object' => 'page',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'main-nav',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 51,
  'post_date' => '2018-05-02 18:01:12',
  'post_date_gmt' => '2018-05-02 18:01:12',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '51',
  'post_modified' => '2018-05-02 18:01:12',
  'post_modified_gmt' => '2018-05-02 18:01:12',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/ultra-photography/?p=51',
  'menu_order' => 1,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'post_type',
    '_menu_item_menu_item_parent' => '0',
    '_menu_item_object_id' => '11',
    '_menu_item_object' => 'page',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'footer-menu',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 44,
  'post_date' => '2018-05-02 18:00:00',
  'post_date_gmt' => '2018-05-02 18:00:00',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '44',
  'post_modified' => '2018-05-02 18:00:48',
  'post_modified_gmt' => '2018-05-02 18:00:48',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/ultra-photography/?p=44',
  'menu_order' => 2,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'post_type',
    '_menu_item_menu_item_parent' => '0',
    '_menu_item_object_id' => '11',
    '_menu_item_object' => 'page',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'main-nav',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 50,
  'post_date' => '2018-05-02 18:01:12',
  'post_date_gmt' => '2018-05-02 18:01:12',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '50',
  'post_modified' => '2018-05-02 18:01:12',
  'post_modified_gmt' => '2018-05-02 18:01:12',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/ultra-photography/?p=50',
  'menu_order' => 2,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'post_type',
    '_menu_item_menu_item_parent' => '0',
    '_menu_item_object_id' => '41',
    '_menu_item_object' => 'page',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'footer-menu',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 43,
  'post_date' => '2018-05-02 18:00:00',
  'post_date_gmt' => '2018-05-02 18:00:00',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '43',
  'post_modified' => '2018-05-02 18:00:48',
  'post_modified_gmt' => '2018-05-02 18:00:48',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/ultra-photography/?p=43',
  'menu_order' => 3,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'post_type',
    '_menu_item_menu_item_parent' => '0',
    '_menu_item_object_id' => '41',
    '_menu_item_object' => 'page',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'main-nav',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 49,
  'post_date' => '2018-05-02 18:01:12',
  'post_date_gmt' => '2018-05-02 18:01:12',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '49',
  'post_modified' => '2018-05-02 18:01:12',
  'post_modified_gmt' => '2018-05-02 18:01:12',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/ultra-photography/?p=49',
  'menu_order' => 3,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'post_type',
    '_menu_item_menu_item_parent' => '0',
    '_menu_item_object_id' => '46',
    '_menu_item_object' => 'page',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'footer-menu',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}

$post = array (
  'ID' => 48,
  'post_date' => '2018-05-02 18:00:48',
  'post_date_gmt' => '2018-05-02 18:00:48',
  'post_content' => ' ',
  'post_title' => '',
  'post_excerpt' => '',
  'post_name' => '48',
  'post_modified' => '2018-05-02 18:00:48',
  'post_modified_gmt' => '2018-05-02 18:00:48',
  'post_content_filtered' => '',
  'post_parent' => 0,
  'guid' => 'https://themify.me/demo/themes/ultra-photography/?p=48',
  'menu_order' => 4,
  'post_type' => 'nav_menu_item',
  'meta_input' => 
  array (
    '_menu_item_type' => 'post_type',
    '_menu_item_menu_item_parent' => '0',
    '_menu_item_object_id' => '46',
    '_menu_item_object' => 'page',
    '_menu_item_classes' => 
    array (
      0 => '',
    ),
  ),
  'tax_input' => 
  array (
    'nav_menu' => 'main-nav',
  ),
);

if( ERASEDEMO ) {
	themify_undo_import_post( $post );
} else {
	themify_import_post( $post );
}


function themify_import_get_term_id_from_slug( $slug ) {
	$menu = get_term_by( "slug", $slug, "nav_menu" );
	return is_wp_error( $menu ) ? 0 : (int) $menu->term_id;
}

	$widgets = get_option( "widget_themify-feature-posts" );
$widgets[1002] = array (
  'title' => 'Recent Posts',
  'category' => '0',
  'show_count' => '3',
  'show_date' => 'on',
  'show_thumb' => 'on',
  'display' => 'none',
  'hide_title' => NULL,
  'thumb_width' => '50',
  'thumb_height' => '50',
  'excerpt_length' => '55',
  'orderby' => 'date',
  'order' => 'DESC',
);
update_option( "widget_themify-feature-posts", $widgets );

$widgets = get_option( "widget_themify-twitter" );
$widgets[1003] = array (
  'title' => 'Latest Tweets',
  'username' => 'themify',
  'type' => '0',
  'timeline_height' => '400',
  'timeline_width' => '300',
  'show_count' => '3',
  'hide_timestamp' => NULL,
  'grid_embed_code' => '',
  'hide_footer' => NULL,
  'show_follow' => 'on',
  'follow_text' => '→ Follow me',
  'include_retweets' => 'on',
  'exclude_replies' => NULL,
);
update_option( "widget_themify-twitter", $widgets );

$widgets = get_option( "widget_themify-social-links" );
$widgets[1004] = array (
  'title' => '',
  'show_link_name' => NULL,
  'open_new_window' => NULL,
  'icon_size' => 'icon-medium',
  'orientation' => 'horizontal',
);
update_option( "widget_themify-social-links", $widgets );

$widgets = get_option( "widget_themify-social-links" );
$widgets[1005] = array (
  'title' => '',
  'show_link_name' => NULL,
  'open_new_window' => NULL,
  'icon_size' => 'icon-medium',
  'orientation' => 'horizontal',
);
update_option( "widget_themify-social-links", $widgets );

$widgets = get_option( "widget_text" );
$widgets[1006] = array (
  'title' => 'Widget 1',
  'text' => 'Optional widget here',
  'filter' => true,
  'visual' => true,
);
update_option( "widget_text", $widgets );

$widgets = get_option( "widget_text" );
$widgets[1007] = array (
  'title' => 'Widget 2',
  'text' => 'Display any widget here',
  'filter' => true,
  'visual' => true,
);
update_option( "widget_text", $widgets );

$widgets = get_option( "widget_text" );
$widgets[1008] = array (
  'title' => 'Widget 3',
  'text' => 'For example, phone #: 123-333-4567',
  'filter' => true,
  'visual' => true,
);
update_option( "widget_text", $widgets );

$widgets = get_option( "widget_nav_menu" );
$widgets[1009] = array (
  'nav_menu' => themify_import_get_term_id_from_slug( "footer-menu" ),
);
update_option( "widget_nav_menu", $widgets );

$widgets = get_option( "widget_text" );
$widgets[1010] = array (
  'title' => '',
  'text' => '211 W 25 Street, 4TH Floor<br/>
New York City 10001<br/>
TEL: <a href="tel:278-874-7339">278.874.7339</a>',
  'filter' => true,
  'visual' => true,
);
update_option( "widget_text", $widgets );



$sidebars_widgets = array (
  'sidebar-main' => 
  array (
    0 => 'themify-feature-posts-1002',
    1 => 'themify-twitter-1003',
  ),
  'social-widget' => 
  array (
    0 => 'themify-social-links-1004',
  ),
  'footer-social-widget' => 
  array (
    0 => 'themify-social-links-1005',
  ),
  'header-widget-1' => 
  array (
    0 => 'text-1006',
  ),
  'header-widget-2' => 
  array (
    0 => 'text-1007',
  ),
  'header-widget-3' => 
  array (
    0 => 'text-1008',
  ),
  'footer-widget-1' => 
  array (
    0 => 'nav_menu-1009',
  ),
  'footer-widget-2' => 
  array (
    0 => 'text-1010',
  ),
); 
update_option( "sidebars_widgets", $sidebars_widgets );

$menu_locations = array();
$menu = get_terms( "nav_menu", array( "slug" => "main-nav" ) );
if( is_array( $menu ) && ! empty( $menu ) ) $menu_locations["main-nav"] = $menu[0]->term_id;
$menu = get_terms( "nav_menu", array( "slug" => "footer-menu" ) );
if( is_array( $menu ) && ! empty( $menu ) ) $menu_locations["footer-nav"] = $menu[0]->term_id;
set_theme_mod( "nav_menu_locations", $menu_locations );


$homepage = get_posts( array( 'name' => 'home', 'post_type' => 'page' ) );
			if( is_array( $homepage ) && ! empty( $homepage ) ) {
				update_option( 'show_on_front', 'page' );
				update_option( 'page_on_front', $homepage[0]->ID );
			}
			
	ob_start(); ?>a:94:{s:16:"setting-page_404";s:1:"0";s:21:"setting-webfonts_list";s:11:"recommended";s:22:"setting-default_layout";s:8:"sidebar1";s:27:"setting-default_post_layout";s:9:"list-post";s:19:"setting-post_filter";s:3:"yes";s:23:"setting-disable_masonry";s:3:"yes";s:19:"setting-post_gutter";s:6:"gutter";s:30:"setting-default_layout_display";s:7:"content";s:25:"setting-default_more_text";s:4:"More";s:21:"setting-index_orderby";s:4:"date";s:19:"setting-index_order";s:4:"DESC";s:30:"setting-default_media_position";s:5:"above";s:31:"setting-image_post_feature_size";s:5:"blank";s:32:"setting-default_page_post_layout";s:8:"sidebar1";s:37:"setting-default_page_post_layout_type";s:7:"classic";s:42:"setting-default_page_single_media_position";s:5:"above";s:38:"setting-image_post_single_feature_size";s:5:"blank";s:31:"setting-image_post_single_width";s:4:"1400";s:27:"setting-default_page_layout";s:8:"sidebar1";s:38:"setting-default_portfolio_index_layout";s:12:"sidebar-none";s:43:"setting-default_portfolio_index_post_layout";s:5:"grid3";s:32:"setting-portfolio_content_layout";s:7:"overlay";s:29:"setting-portfolio_post_filter";s:3:"yes";s:33:"setting-portfolio_disable_masonry";s:2:"no";s:24:"setting-portfolio_gutter";s:9:"no-gutter";s:39:"setting-default_portfolio_index_display";s:4:"none";s:50:"setting-default_portfolio_index_post_meta_category";s:3:"yes";s:49:"setting-default_portfolio_index_unlink_post_image";s:3:"yes";s:39:"setting-default_portfolio_single_layout";s:12:"sidebar-none";s:54:"setting-default_portfolio_single_portfolio_layout_type";s:9:"fullwidth";s:50:"setting-default_portfolio_single_unlink_post_image";s:3:"yes";s:22:"themify_portfolio_slug";s:7:"project";s:53:"setting-customizer_responsive_design_tablet_landscape";s:4:"1024";s:43:"setting-customizer_responsive_design_tablet";s:3:"768";s:43:"setting-customizer_responsive_design_mobile";s:3:"680";s:33:"setting-mobile_menu_trigger_point";s:4:"1000";s:24:"setting-gallery_lightbox";s:8:"lightbox";s:27:"setting-script_minification";s:7:"disable";s:27:"setting-page_builder_expiry";s:1:"2";s:21:"setting-header_design";s:14:"header-overlay";s:28:"setting-exclude_site_tagline";s:2:"on";s:27:"setting-exclude_search_form";s:2:"on";s:19:"setting-exclude_rss";s:2:"on";s:22:"setting-header_widgets";s:4:"none";s:21:"setting-footer_design";s:15:"footer-left-col";s:38:"setting-exclude_footer_menu_navigation";s:2:"on";s:22:"setting-use_float_back";s:2:"on";s:22:"setting-footer_widgets";s:17:"footerwidget-2col";s:27:"setting-imagefilter_applyto";s:12:"featuredonly";s:29:"setting-color_animation_speed";s:1:"5";s:29:"setting-relationship_taxonomy";s:8:"category";s:37:"setting-relationship_taxonomy_entries";s:1:"3";s:45:"setting-relationship_taxonomy_display_content";s:4:"none";s:30:"setting-single_slider_autoplay";s:3:"off";s:27:"setting-single_slider_speed";s:6:"normal";s:28:"setting-single_slider_effect";s:5:"slide";s:28:"setting-single_slider_height";s:4:"auto";s:18:"setting-more_posts";s:8:"infinite";s:19:"setting-entries_nav";s:8:"numbered";s:25:"setting-img_php_base_size";s:5:"large";s:27:"setting-global_feature_size";s:5:"blank";s:22:"setting-link_icon_type";s:9:"font-icon";s:32:"setting-link_type_themify-link-0";s:10:"image-icon";s:33:"setting-link_title_themify-link-0";s:7:"Twitter";s:31:"setting-link_img_themify-link-0";s:111:"https://themify.me/demo/themes/ultra-photography/wp-content/themes/themify-ultra/themify/img/social/twitter.png";s:32:"setting-link_type_themify-link-1";s:10:"image-icon";s:33:"setting-link_title_themify-link-1";s:8:"Facebook";s:31:"setting-link_img_themify-link-1";s:112:"https://themify.me/demo/themes/ultra-photography/wp-content/themes/themify-ultra/themify/img/social/facebook.png";s:32:"setting-link_type_themify-link-2";s:10:"image-icon";s:33:"setting-link_title_themify-link-2";s:7:"Google+";s:31:"setting-link_img_themify-link-2";s:115:"https://themify.me/demo/themes/ultra-photography/wp-content/themes/themify-ultra/themify/img/social/google-plus.png";s:32:"setting-link_type_themify-link-3";s:10:"image-icon";s:33:"setting-link_title_themify-link-3";s:7:"YouTube";s:31:"setting-link_img_themify-link-3";s:111:"https://themify.me/demo/themes/ultra-photography/wp-content/themes/themify-ultra/themify/img/social/youtube.png";s:32:"setting-link_type_themify-link-4";s:10:"image-icon";s:33:"setting-link_title_themify-link-4";s:9:"Pinterest";s:31:"setting-link_img_themify-link-4";s:113:"https://themify.me/demo/themes/ultra-photography/wp-content/themes/themify-ultra/themify/img/social/pinterest.png";s:32:"setting-link_type_themify-link-5";s:9:"font-icon";s:33:"setting-link_title_themify-link-5";s:7:"Twitter";s:32:"setting-link_link_themify-link-5";s:27:"https://twitter.com/themify";s:33:"setting-link_ficon_themify-link-5";s:10:"fa-twitter";s:32:"setting-link_type_themify-link-6";s:9:"font-icon";s:33:"setting-link_title_themify-link-6";s:8:"Facebook";s:32:"setting-link_link_themify-link-6";s:28:"https://facebook.com/themify";s:33:"setting-link_ficon_themify-link-6";s:11:"fa-facebook";s:32:"setting-link_type_themify-link-8";s:9:"font-icon";s:33:"setting-link_title_themify-link-8";s:7:"YouTube";s:32:"setting-link_link_themify-link-8";s:32:"https://youtube.com/user/themify";s:33:"setting-link_ficon_themify-link-8";s:10:"fa-youtube";s:22:"setting-link_field_ids";s:273:"{"themify-link-0":"themify-link-0","themify-link-1":"themify-link-1","themify-link-2":"themify-link-2","themify-link-3":"themify-link-3","themify-link-4":"themify-link-4","themify-link-5":"themify-link-5","themify-link-6":"themify-link-6","themify-link-8":"themify-link-8"}";s:23:"setting-link_field_hash";s:2:"10";s:30:"setting-page_builder_is_active";s:6:"enable";s:46:"setting-page_builder_animation_parallax_scroll";s:6:"mobile";s:4:"skin";s:108:"https://themify.me/demo/themes/ultra-photography/wp-content/themes/themify-ultra/skins/photography/style.css";}<?php $themify_data = unserialize( ob_get_clean() );

	// fix the weird way "skin" is saved
	if( isset( $themify_data['skin'] ) ) {
		$parsed_skin = parse_url( $themify_data['skin'], PHP_URL_PATH );
		$basedir_skin = basename( dirname( $parsed_skin ) );
		$themify_data['skin'] = trailingslashit( get_template_directory_uri() ) . 'skins/' . $basedir_skin . '/style.css';
	}

	themify_set_data( $themify_data );
	
}
themify_do_demo_import();