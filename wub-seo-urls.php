<?php
/**
* Plugin Name: Wub SEO URLS
* Plugin URI: http://woocommerce-url-builder.co.za/
* Description: Fixes urls for wooCommerce
* Version: 1.91
* Author: Richard Miles
* Author URI: http://woocommerce-url-builder.co.za/
* License: GPL12
*/

/**
 * Todo List:
 * Make Admin settings page form ajax
 * Add Flush permalinks Trashcan to the admin menu bar
 * Add only Heirachical Primary Categories option to settings page and create functionality
 * Put Licensing settings on same page as plugin settings
 * Add product tag url routing
 */

// If this file is called directly, abort.
if (!defined( 'WPINC' )) {
  die;
}

/**
 * Constants
 */

define('WUB_PLUGIN_PATH', plugin_dir_path( __FILE__ ));
define('WUB_OPTION', 'wub_options');

Class Wub_seo_url {

  /**
   * $admin_page_title Page title for Wub SEO URLS Settings page
   * @var string
   */
  private $admin_page_title = 'Wub SEO URLS Settings';

  /**
   * $admin_menu_tab Menu tab that falls under the settings tab in the WordPress admin area
   * @var string
   */
  private $admin_menu_tab =   'SEO urls';

  /**
   * $admin_page_slug Page `get` variable that distinguishes this page from other General Options pages
   * @var string
   */
  private $admin_page_slug = 'wub-seo-urls';

  /**
   * __construct Method that is run when Object of Wub_seo_url is instansiated
   */
  function __construct() {
    //actions
    add_action('init', [$this, 'wub_seo_rewrite_rules']);

    //filters
    add_filter('term_link', [$this, 'wub_term_custom_link'], 10, 3);
    add_filter('post_type_link', [$this, 'wub_post_custom_link'] , 10, 2);
  }

  /**
   * custom_get_terms custom method for getting terms before they are initialised via theme
   * @param  string $term taxonomy that gets passed in to get the terms from
   * @return array collection of term objects
   */
  public function custom_get_terms($term) {
    global $wpdb;

    $out = [];

    //gets all terms from taxonomy ($term)
    $a = $wpdb->get_results($wpdb->prepare("SELECT t.name,t.slug,t.term_group,x.term_taxonomy_id,x.term_id,x.taxonomy,x.description,x.parent,x.count
      FROM {$wpdb->prefix}term_taxonomy x
      LEFT JOIN {$wpdb->prefix}terms t ON (t.term_id = x.term_id)
      WHERE x.taxonomy=%s;",$term));

    foreach ($a as $b) {
      //create instance of term and save into object
      $obj = new stdClass();
      $obj->term_id = $b->term_id;
      $obj->name = $b->name;
      $obj->slug = $b->slug;
      $obj->term_group = $b->term_group;
      $obj->term_taxonomy_id = $b->term_taxonomy_id;
      $obj->taxonomy = $b->taxonomy;
      $obj->description = $b->description;
      $obj->parent = $b->parent;
      $obj->count = $b->count;
      $out[] = $obj;
    }

    return $out;
  }

  /**
  * wub_seo_rewrite_rules function to be hooked into the init action and creates rewrite rules
  */
  public function wub_seo_rewrite_rules() {

    foreach (get_post_types(['public'=>true, '_builtin'=>false], 'names') as $post_type) {
      $taxonomy_name = get_option('wub_post_type_' . $post_type);
      if ($taxonomy_name!=='default') {

          // fetch all posts which have no assigned term
        $posts = $this->custom_get_posts($post_type, $taxonomy_name, $this->custom_get_terms($taxonomy_name));

        //adds default rewrite rules
        foreach ($posts as $post) {

         if ($post->post_type == $post_type) {
           $terms = wp_get_post_terms($post->ID, $taxonomy_name, ['fields' => 'slugs', 'orderby' => 'term_id']);

           $rewrite_string = '^';

           foreach ($terms as $term) {
             $rewrite_string .= $term.'/';
           }
           $rewrite_string .= '('. $post->post_name . ')' . '/?$';

            // create post link with grandparent term hierachical prefix
           add_rewrite_rule($rewrite_string, 'index.php?post_type='.$post_type.'&name=$matches[1]', 'top' );

         }
       }

          //gets categories of taxonomy
       $categories = $this->custom_get_terms($taxonomy_name);

       foreach ($categories as $category) {
              //gets and checks if there is a parent term of category
        $parent_term = get_term($category->parent, $taxonomy_name);

        if (isset($parent_term->slug)) {
                //gets and checks if there is a grandparent term of category
          $grand_parent_term = get_term($parent_term->parent, $taxonomy_name);

          if (isset($grand_parent_term->slug)) {

                  //create term link to grandparent term
            add_rewrite_rule('^'.$grand_parent_term->slug.'/'.$parent_term->slug.'/'.$category->slug.'/?$', 'index.php?'.$taxonomy_name.'='.$category->slug.'&child_of='.$parent_term->slug,'top');

                  //create term link to grandparent term with pagination
            add_rewrite_rule('^'.$grand_parent_term->slug.'/'.$parent_term->slug.'/'.$category->slug.'(/page/([0-9]+))/?$', 'index.php?'.$taxonomy_name.'='.$category->slug.'&child_of='.$parent_term->slug.'&paged=$matches[2]','top');

          } else {
                  //create term link to parent term
            add_rewrite_rule('^'.$parent_term->slug.'/'.$category->slug.'/?$', 'index.php?'.$taxonomy_name.'='.$category->slug.'&child_of='.$parent_term->slug,'top');

                  //create term link to parent term with pagination
            add_rewrite_rule('^'.$parent_term->slug.'/'.$category->slug.'(/page/([0-9]+))/?$', 'index.php?'.$taxonomy_name.'='.$category->slug.'&child_of='.$parent_term->slug.'&paged=$matches[2]', 'top');
          }

        } else {
              //create term link to term
          add_rewrite_rule('^'.$category->slug.'/?$', 'index.php?'.$taxonomy_name.'='.$category->slug,'top');

              //create term link to term with pagination
          add_rewrite_rule('^'.$category->slug.'(/page/([0-9]+))/?$', 'index.php?'.$taxonomy_name.'='.$category->slug.'&paged=$matches[2]','top');
        }
      }
    }
  }
}

  /**
   * [custom_get_posts Custom get posts query to run before init action is run to add post types
   * @param  string $post_type Post type name to look for
   * @param  string $taxonomy  taxonomy name to look for
   * @param  array  $terms     list of terms not to include
   * @return object            collection of posts
   */
  public function custom_get_posts($post_type, $taxonomy, $terms = array()) {

    global $wpdb;

    $slugs = $this->slug_array_to_csv($terms);

    $a = $wpdb->get_results("SELECT ID, post_name, post_type FROM $wpdb->posts
      LEFT JOIN $wpdb->term_relationships ON($wpdb->posts.ID = $wpdb->term_relationships.object_id)
      LEFT JOIN $wpdb->term_taxonomy ON($wpdb->term_relationships.term_taxonomy_id = $wpdb->term_taxonomy.term_taxonomy_id)
      LEFT JOIN $wpdb->terms ON($wpdb->term_taxonomy.term_id = $wpdb->terms.term_id)
      WHERE $wpdb->terms.name  NOT IN ($slugs)
      AND $wpdb->posts.post_status = 'publish'
      AND $wpdb->posts.post_type = '$post_type'
      ");

    return $a;
  }

  /**
   * csv_users_ids() creates a string csv version of the users array.
   *
   * @return string commas separated values
   */
  public function slug_array_to_csv($terms) {
    $slugs = [];

    if (isset($terms)) {

      foreach ($terms as $term) {
        $slugs[] = "'" . $term->slug . "'";
      }
    }

    if($slugs) {
      return implode(", ", $slugs);
    } else {
      return false;
    }
  }

  /**
   * wub_term_custom_link filters through term links
   * @param  string $url      url of term
   * @param  string $term     term to filter
   * @param  string $taxonomy taxonomy of term
   * @return string           new term link
   */
  public function wub_term_custom_link( $url, $term, $taxonomy) {

    $wub_current_post_type_name;

    //loops through each post type and matching taxonomy that has been selected
    foreach (get_post_types(['public'=>true, '_builtin'=>false], 'names') as $post_type) {
      $taxonomy_name = get_option('wub_post_type_' . $post_type);
      if ($taxonomy===$taxonomy_name) {
        $wub_current_post_type_name = $taxonomy_name;
      }
    }

    //return if not set or default
    if (!isset($wub_current_post_type_name) || $wub_current_post_type_name==='default') {
      return $url;
    }

    $parent_term = get_term($term->parent, $wub_current_post_type_name);
    if (isset($parent_term->slug)) {
      $grand_parent_term = get_term($parent_term->parent, $wub_current_post_type_name);
      if (isset($grand_parent_term->slug)) {
       return get_home_url() . '/'. $grand_parent_term->slug . '/'. $parent_term->slug . '/' . $term->slug . '/';
     } else {
       return get_home_url() . '/'. $parent_term->slug . '/' . $term->slug . '/';
     }
   } else {
    return get_home_url() . '/'. $term->slug . '/';
  }
}

/**
 * wub_post_custom_link filters through post links
 * @param  string  $post_link default post link
 * @param  integer $id        id of post type
 * @return string             string of new post link
 */
public function wub_post_custom_link($post_link, $id = 0) {
  $post = get_post($id);

  $wub_current_post_type_name;

  //loops through each post type and matching taxonomy that has been selected
  foreach (get_post_types(['public'=>true, '_builtin'=>false], 'names') as $post_type) {
    $taxonomy_name = get_option('wub_post_type_' . $post_type);
    if ($post_type===$post->post_type && isset($taxonomy_name)) {
      $wub_current_post_type_name = $taxonomy_name;
    }
  }

  if (!isset($wub_current_post_type_name) || $wub_current_post_type_name==='default') {
    return $post_link;
  }

  $terms = wp_get_post_terms($post->ID, $wub_current_post_type_name, ['fields' => 'slugs', 'orderby' => 'term_id']);

  if (isset($terms)) {
    foreach ($terms as $term) {
     $rewrite_string .= $term.'/';
   }
 }


 $rewrite_string .= $post->post_name . '/';

 return home_url(user_trailingslashit($rewrite_string));
}
}

//instaniate object of wub_seo_url post type
$wub_seo_url = new Wub_seo_url();

register_deactivation_hook( __FILE__, 'wub_flush_rewrite_rules' );
register_activation_hook( __FILE__, 'wub_flush_rewrite_rules' );

//runs on activation and deactivation
function wub_flush_rewrite_rules() {
  flush_rewrite_rules();
}


add_action('init', 'wub_flush_permalinks_on_save');
function wub_flush_permalinks_on_save($post_id) {
  $checked = get_option('wub_checked');
  if (!empty($checked)) {
    flush_rewrite_rules();
  }
}

require_once( WUB_PLUGIN_PATH . 'includes/class-wp-license-manager-client.php' );

if ( is_admin() ) {
  $license_manager = new Wp_License_Manager_Client(
    'wub-seo-urls',
    'Wub SEO',
    'wub-seo-urls',
    'https://wubpress.com/api/license-manager/v1',
    'plugin',
    __FILE__
    );
}

add_action( 'wp_ajax_wub_post_type', 'wub_post_type_callback' );

function wub_post_type_callback() {

  $option_name = 'wub_post_type_' . $_POST['wub_post_type'];

  $new_value = $_POST['wub_post_type_value'];

  if (get_option($option_name) !== false ) {
    // The option already exists, so we just update it.
    update_option($option_name, $new_value);
  } else {
    // The option hasn't been added yet. We'll add it with $autoload set to 'no'.
    $deprecated = null;
    $autoload = 'no';
    add_option($option_name, $new_value, $deprecated, $autoload);
  }

  flush_rewrite_rules();

  echo ucfirst($_POST['wub_post_type']) . ' post type value has been updated to `' . $new_value . '`';

  wp_die();
}

add_action( 'wp_ajax_wub_get_all_post_type', 'wub_get_all_post_type_callback' );

function wub_get_all_post_type_callback() {

  $post_type_values = [];

  foreach (get_post_types(['public'=>true, '_builtin'=>false], 'names') as $post_type) {
    unset($post_object);
    $post_object->name = $post_type;
    $post_object->value = get_option('wub_post_type_' . $post_type);
    $post_type_values[] = $post_object;
  }

  echo json_encode($post_type_values);

  wp_die();
}

add_action( 'wp_ajax_wub_get_flush_checked', 'wub_get_flush_checked_callback' );

function wub_get_flush_checked_callback() {

  echo get_option('wub_checked');

  wp_die();
}


add_action( 'wp_ajax_wub_flush_permalinks_ajax', 'wub_flush_permalinks_ajax_callback' );

function wub_flush_permalinks_ajax_callback() {
  flush_rewrite_rules();
  echo 'Successfully Flushed Permalinks';

  wp_die();
}

add_action( 'wp_ajax_wub_flush_on_post_save', 'wub_flush_on_post_save_callback' );

function wub_flush_on_post_save_callback() {

  $option_name = 'wub_checked';

  $new_value = $_POST['wub_checked'];

  if (get_option($option_name) !== false ) {
    // The option already exists, so we just update it.
    update_option($option_name, $new_value);
  } else {
    // The option hasn't been added yet. We'll add it with $autoload set to 'no'.
    $deprecated = null;
    $autoload = 'no';
    add_option($option_name, $new_value, $deprecated, $autoload);
  }

  echo 'Flush on Post save option updated';

  wp_die();
}




