<?php
/**
 * Sage includes
 *
 * The $sage_includes array determines the code library included in your theme.
 * Add or remove files to the array as needed. Supports child theme overrides.
 *
 * Please note that missing files will produce a fatal error.
 *
 * @link https://github.com/roots/sage/pull/1042
 */
$sage_includes = [
  'lib/assets.php',    // Scripts and stylesheets
  'lib/extras.php',    // Custom functions
  'lib/setup.php',     // Theme setup
  'lib/titles.php',    // Page titles
  'lib/wrapper.php',   // Theme wrapper class
  'lib/customizer.php', // Theme customizer
];

foreach ($sage_includes as $file) {
  if (!$filepath = locate_template($file)) {
    trigger_error(sprintf(__('Error locating %s for inclusion', 'konkurrent'), $file), E_USER_ERROR);
  }

  require_once $filepath;
}
unset($file, $filepath);

function konkurrent_category_list($num){
    $temp = get_the_category();
    $count = count($temp);// Getting the total number of categories the post is filed in.
    $cat_string = '';
    for( $i=0; $i<$num&&$i<$count; $i++ ){
        $cat_string.='<a href="'.esc_url(get_category_link( $temp[$i]->cat_ID )).'">'.esc_html($temp[$i]->cat_name).'</a>';
        if($i!=$num-1&&$i+1<$count)
        $cat_string.='/';
    }
    echo $cat_string;
}

function konkurrent_tag_list($num){
    $temp=get_the_tags();
    $count=count($temp);
    $cat_string = '';
    for($i=0;$i<$num&&$i<$count;$i++){
        $cat_string.='<a href="'.esc_url(get_tag_link( $temp[$i]->term_id )).'">'.esc_html($temp[$i]->name).'</a>';
        if($i!=$num-1&&$i+1<$count)
        $cat_string.='/';
    }
    echo $cat_string;
}

function konkurrent_sanitize_select( $input, $setting ) {

    // Ensure input is a slug.
    $input = sanitize_key( $input );
    // Get list of choices from the control associated with the setting.
    $choices = $setting->manager->get_control( $setting->id )->choices;
    // If the input is a valid key, return it; otherwise, return the default.
    return ( array_key_exists( $input, $choices ) ? $input : $setting->default );

}

function konkurrent_sanitize_powered_by( $input ) {
    return wp_kses( $input, array(
        'a' => array(
            'href' => array(),
            'title' => array()
        ),
        'br' => array(),
        'em' => array(),
        'strong' => array(),
    ) );
}

function konkurrent_content($post_id) {
    
    if(has_excerpt($post_id)) {
        $content = get_the_excerpt($post_id);
    } else {
        $content_post = get_post($post_id);
        $content = $content_post->post_content;
        $content = substr($content, 0, 200);
        $content = apply_filters('the_content', $content);
    }
    return $content;
}