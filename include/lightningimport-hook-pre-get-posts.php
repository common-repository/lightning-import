<?php

function lightningimport_file_replace()
{
    //Get the options and if the search toggle is not set make the default true
    $options = get_option('lightningimport_lightningimportOptions', array('lightningimport_SearchWidgetToggle' => "1"));
    //error_log('Options: '.print_r($options,true));
    //Get options and only copy the product search if the toggle is set to true.
    if (isset($options['lightningimport_SearchWidgetToggle'])) {
        if ($options['lightningimport_SearchWidgetToggle'] == "1") {
            $plugin_dir = plugin_dir_path(__FILE__) . 'product-searchform.php';
            $theme_dir = get_stylesheet_directory() . '/product-searchform.php';

            if (!copy($plugin_dir, $theme_dir)) {
                echo "failed to copy $plugin_dir to $theme_dir...\n";
            }
            //error_log('Copied product search form to:'.print_r($theme_dir,true));
        }
    }
}

add_action('wp_head', 'lightningimport_file_replace');

/**
 * Add custom join and where statements to product search query
 * @param  mixed $q query object
 * @return void
 */
function lightningimport_pre_get_posts($query)
{
    // If 's' request variable is set but empty we manually change the parameters here to force it to search with our custom parameters
    if (isset($_GET['s']) && empty($_GET['s']) && $query->is_main_query()) {
        $query->is_search = true;
        $query->is_home = false;
        //error_log("Hit the wordpress set is_home for empty search and corrected. Here is the is search bool: ".$query->is_search);
    }
    if ($query->is_search) {
        add_filter('posts_join', 'lightningimport_search_post_join');
        add_filter('posts_where', 'lightningimport_search_post_excerpt');
        //error_log($GLOBALS['wp_query']->request);
        //error_log('Past the is_search check!');
    }
}

// hook into wp pre_get_posts
add_action('pre_get_posts', 'lightningimport_pre_get_posts', 1);
//error_log('Past the hook!');

function lightningimport_exclude_product_cat_children($wp_query)
{
    if (isset($wp_query->query_vars['product_cat']) && $wp_query->is_main_query()) {
        $wp_query->set('tax_query', array(
            array(
                'taxonomy' => 'product_cat',
                'field' => 'slug',
                'terms' => $wp_query->query_vars['product_cat'],
                'include_children' => false,
            ),
        )
        );
    }
}

//Do not think we need this for SDC implementations since categories are handled differently
//add_filter('pre_get_posts', 'lightningimport_exclude_product_cat_children');

/**
 * Add Custom Join Code for wp_mostmeta table
 * @param  string $join
 * @return string
 */
function lightningimport_search_post_join($join = '')
{

    global $wp_the_query;
    global $wpdb;
    $dbprefix = $wpdb->prefix;
    //error_log('Attempted to join query results!');
    // escape if not woocommerce searcg query
    if (empty($wp_the_query->query_vars['wc_query'])) {
        return $join;
    }

    $join .= " LEFT JOIN lightningimport_product_attributes AS spa ON (wp_posts.id = spa.post_id) ";
    //error_log($join);
    return $join;
}

/**
 * Add custom where statement to product search query
 * @param  string $where
 * @return string
 */
function lightningimport_search_post_excerpt($where = '')
{
    global $wp_the_query;
    global $wpdb;
    $dbprefix = $wpdb->prefix;
    //error_log('Adding custom where clause!');
    // escape if not woocommerce search query
    if (empty($wp_the_query->query_vars['wc_query'])) {
        return $where;
        //error_log("Non wc query");
    }
    //echo print_r($_POST);
    //error_log(print_r($_GET,true));

    $flist = [];

    for ($i = 1; $i < 9; $i++) {
        if (array_key_exists('f' . $i, $_GET)) {
            $flist[$i] = $_GET['f' . $i];
        }
    }
    //error_log('flist: '.print_r($flist,true));

    $lightningimport_where_array = array();

    for ($i = 1; $i <= count($flist); $i++) {
        //error_log("f".$i.": ".$flist[$i]);
        if (!empty($flist[$i]) && $flist[$i] != "--Select--") {$flist[$i] = "'%" . $flist[$i] . "%'";} else { $flist[$i] = "";}
        if (!empty($flist[$i])) {$lightningimport_where_array[] = "spa.f" . $i . " LIKE " . $flist[$i] . "";}
    }
    //error_log(print_r($lightningimport_where_array,true));
    /*use array to join to make the part of the where clause for year,make,model*/

    $lightningimport_filter = join(" and ", $lightningimport_where_array);

    if (empty($lightningimport_filter)) {
        $lightningimport_filter = "";
    } else {
        $lightningimport_filter = " and (" . $lightningimport_filter . ")";
    }
    //error_log($where);
    //This pulls the search term inputed by the user
    $searchterm = get_search_query();
    //error_log("Search term is: ".$searchterm);
    //This statement replaces the post_content segment in the where clause with our own additional where clause to search.
    $where = preg_replace("/OR \(" . $dbprefix . "posts.post_content LIKE ('%[^%]+%')\)/", " OR (spa.f1 LIKE '%$searchterm%' ) OR (spa.f2 LIKE '%$searchterm%' ) OR (spa.f3 LIKE '%$searchterm%' ) OR (spa.f4 LIKE '%$searchterm%' ) OR (spa.f5 LIKE '%$searchterm%' ) OR (spa.f6 LIKE '%$searchterm%' ) OR (spa.f7 LIKE '%$searchterm%' ) OR (spa.f8 LIKE '%$searchterm%' ) ", $where);

    $where .= $lightningimport_filter;
    //lightningimport_debug output lines
    //echo $where;
    //error_log($where);
    return $where;
}
