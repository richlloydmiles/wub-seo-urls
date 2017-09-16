<?php
/**
* Plugin Name: Wub SEO URLS
* Plugin URI: https://wubpress.com
* Description: Creates Custom URL structures for WooCommerce
* Version: 2.3
* Author: Richard Miles
* Author URI: https://wubpress.com
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

ob_start();

/**
 * Constants
 */

define('WUB_PLUGIN_PATH', plugin_dir_path( __FILE__ ));
define('WUB_OPTION', 'wub_options');

Class Wub_seo_url {

  public static $obj = '';
  public static $pattern = [];
  public static $type = '';
  public static $taxonomy = '';
  public static $post_type = '';
  public static $paged = false;

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
  * wub_seo_rewrite_rules function to be hooked into the init action and creates rewrite
  * rules
  */

  public function wub_seo_rewrite_rules() {

    global $wp;

    $pattern = $this->wub_is_valid(1);

    if (!$pattern) {
      return;
    }

    $rewrite_string = '^';
    foreach ($pattern as $value) {
      if(end($pattern) == $value) {
        self::$obj = $value;
        continue;
      }
      $rewrite_string .= $value . '/';
    }

    $pag_rewrite_string .= $rewrite_string . '('. self::$obj . ')' . '(/page/([0-9]+))/?(.*?)$';

    $rewrite_string .= '('. self::$obj . ')' . '/?(.*?)$';
    $query = 'index.php?' . self::$taxonomy[0]->taxonomy . '='.self::$obj;

    if (self::$type === 'tax') {
      if (self::$paged) {
        $pag_query = 'index.php?' . self::$taxonomy . '='.self::$obj . '&paged=$matches[3]';
        add_rewrite_rule($pag_rewrite_string, $pag_query , 'top');
      } else {
        add_rewrite_rule($rewrite_string, $query , 'top');
      }
    } else {
      $query = 'index.php?post_type='.self::$post_type.'&name=' . self::$obj;
      add_rewrite_rule($rewrite_string, $query, 'top' );
    }
    flush_rewrite_rules();
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

  public function custom_get_post_types() {

    global $wpdb;

    $a = $wpdb->get_results("SELECT DISTINCT post_type FROM $wpdb->posts WHERE post_type NOT IN ('post', 'page', 'attachment', 'revision', 'nav_menu_item') AND post_status = 'publish'");

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

    include_once(ABSPATH.'wp-admin/includes/plugin.php');

    $is_poly_active = is_plugin_active('polylang/polylang.php');

    if ($is_poly_active) {
      $language =  pll_current_language();
    }

    $wub_current_post_type_name;

    $rewrite_string = '';

    $sql_post_types = $this->custom_get_post_types();

    $post_types = [];

    foreach ($sql_post_types as $post_type) {
      $post_types[] = $post_type->post_type;
    }

    //loops through each post type and matching taxonomy that has been selected
    foreach ($post_types as $post_type) {
      $taxonomy_name = get_option('wub_post_type_' . $post_type);
      if ($taxonomy===$taxonomy_name) {
        $wub_current_post_type_name = $taxonomy_name;
      }
    }


    //return if not set or default
    if (!isset($wub_current_post_type_name) || $wub_current_post_type_name === 'default') {
      return $url;
    }

    $parent_term = get_term($term->parent, $wub_current_post_type_name);

    if (isset($parent_term->slug)) {
      $grand_parent_term = get_term($parent_term->parent, $wub_current_post_type_name);
      if (isset($grand_parent_term->slug)) {
        if ($is_poly_active) {
          $rewrite_string = $language . '/'. $grand_parent_term->slug . '/'. $parent_term->slug . '/' . $term->slug . '/';
        } else {

          $rewrite_string = $grand_parent_term->slug . '/'. $parent_term->slug . '/' . $term->slug . '/';
        }

      } else {
        if ($is_poly_active) {
          $rewrite_string =  $language . '/'. $parent_term->slug . '/' . $term->slug . '/';
        } else {
          $rewrite_string = $parent_term->slug . '/' . $term->slug . '/';
        }

      }
    } else {
      if ($is_poly_active) {
        $rewrite_string = $language . '/'. $term->slug . '/';
      } else {
        $rewrite_string = $term->slug . '/';
      }
    }

    $tax = get_taxonomy($wub_current_post_type_name);

    if (strpos($_SERVER['REQUEST_URI'], $tax->rewrite['slug']) !== false) {
      header ('HTTP/1.1 301 Moved Permanently');
      header ('Location: ' . home_url(user_trailingslashit($rewrite_string)));
      exit();
    }

    return home_url(user_trailingslashit($rewrite_string));
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

    $sql_post_types = $this->custom_get_post_types();

    $post_types = [];

    foreach ($sql_post_types as $post_type) {
      $post_types[] = $post_type->post_type;
    }


    //loops through each post type and matching taxonomy that has been selected
    foreach ($post_types as $post_type) {
      $taxonomy_name = get_option('wub_post_type_' . $post_type);
      if ($post_type===$post->post_type && isset($taxonomy_name)) {
        $wub_current_post_type_name = $taxonomy_name;
      }
    }

    if (class_exists('WPSEO_Primary_Term')) {
      $cat = new WPSEO_Primary_Term($wub_current_post_type_name, $post->ID);
      $cat = $cat->get_primary_term();

    }

    $rewrite_string = '';

    if ($cat) {

      $term = get_term($cat);

      $parent_term = get_term($term->parent, $wub_current_post_type_name);

      if (isset($parent_term->slug)) {
        $grand_parent_term = get_term($parent_term->parent, $wub_current_post_type_name);

        if (isset($grand_parent_term->slug)) {

          $great_grand_parent_term = get_term($grand_parent_term->parent, $wub_current_post_type_name);

          if(isset($great_grand_parent_term->slug)) {
            $rewrite_string .= $great_grand_parent_term->slug . '/' . $grand_parent_term->slug . '/' . $parent_term->slug . '/' . $term->slug.'/';
          } else {
            $rewrite_string .= $grand_parent_term->slug . '/' . $parent_term->slug . '/' . $term->slug.'/';
          }
        } else {
          $rewrite_string .= $parent_term->slug . '/' . $term->slug.'/';
        }
      } else {
        $rewrite_string .= $term->slug.'/';
      }
    } else {

      if (!isset($wub_current_post_type_name) || $wub_current_post_type_name==='default') {
        return $post_link;
      }

      $terms = wp_get_post_terms($post->ID, $wub_current_post_type_name, ['fields' => 'slugs', 'orderby' => 'parent', 'order' => 'DESC']);

      if (isset($terms)) {
        foreach ($terms as $term) {
          $rewrite_string .= $term.'/';
        }
      }
    }

    $rewrite_string .= $post->post_name . '/';

    if (strpos($_SERVER['REQUEST_URI'], $post->post_type . '/'.$post->post_name) !== false) {
      header ('HTTP/1.1 301 Moved Permanently');
      header ('Location: ' . home_url(user_trailingslashit($rewrite_string)));
      exit();
    }


    return home_url(user_trailingslashit($rewrite_string));
  }
  public function get_term_by_taxonomy($term) {
    global $wpdb;
    $a = $wpdb->get_results($wpdb->prepare("SELECT wp_term_taxonomy.taxonomy FROM wp_term_taxonomy INNER JOIN wp_terms ON wp_term_taxonomy.term_id=wp_terms.term_id WHERE wp_terms.slug = %s", $term));

    return $a;
  }

  public function get_wub_post($post_name) {
    global $wpdb;
    $a = $wpdb->get_results($wpdb->prepare("SELECT post_name, post_type FROM wp_posts WHERE post_name = '%s'", $post_name));

    self::$post_type=$post = $a[0]->post_type;

    return $a[0]->post_name;
  }


  public function get_wub_tax($tax_name) {
    global $wpdb;
    $a = $wpdb->get_results($wpdb->prepare("SELECT slug FROM wp_terms WHERE slug = %s", $tax_name));

    return $a[0]->slug;
  }

  public function wub_is_valid($num = 0) {

    $current_url = array_filter(explode('/', $_SERVER['REQUEST_URI']), 'strlen');
    $wub_post_or_tax  = '';

    if ($current_url[(string) $num] === 'page') {
      $pag_tax = $this->get_term_by_taxonomy($this->get_wub_tax($current_url[(string) ($num - 1)]));
      $pag_tax = $pag_tax[0]->taxonomy;
      self::$taxonomy = $pag_tax;
      $taxObject = get_taxonomy($pag_tax);
      $wub_post_or_tax = $pag_tax;
      self::$post_type=$taxObject->object_type;
      self::$type = 'tax';
      self::$paged = true;

      return self::$pattern;

    }

    if ($this->get_wub_tax($current_url[(string) $num])) {
      $wub_post_or_tax = $this->get_wub_tax($current_url[(string) $num]);

      self::$type = 'tax';
      self::$taxonomy = $this->get_term_by_taxonomy($wub_post_or_tax);
      $taxObject = get_taxonomy($wub_post_or_tax);
      self::$post_type=$taxObject->object_type;
    } else {
      $wub_post_or_tax = $this->get_wub_post($current_url[(string) $num]);
      self::$type = 'post';
    }

    if (!count($current_url) || (empty($wub_post_or_tax))) {
      return false;
    } else {
      self::$pattern[] = $wub_post_or_tax;
    }

    if(count($current_url) === $num) {
      return self::$pattern;
    }
    return $this->wub_is_valid($num + 1);
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

require_once(WUB_PLUGIN_PATH . 'includes/class-wp-license-manager-client.php');

if (is_admin()) {
  $license_manager = new Wp_License_Manager_Client(
    'wub-seo-urls',
    'Wub SEO',
    'wub-seo-urls',
    'https://wubpress.com/api/license-manager/v1',
    'plugin',
    __FILE__
  );
}

add_action('wp_ajax_wub_post_type', 'wub_post_type_callback');

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

add_action('wp_ajax_wub_get_all_post_type', 'wub_get_all_post_type_callback');

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

add_action('wp_ajax_wub_get_flush_checked', 'wub_get_flush_checked_callback');

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

ob_end_clean();
