<?php
/*
Plugin Name: Lightning Import
Plugin URI: #
Description: Import Products into WooCommerce
Version: 1.0.3
Author: Lightning Import
Author URI: https://lightningimport.com
 */

//include the classes
include_once dirname(__FILE__) . '/include/lightningimport-hook-pre-get-posts.php';
include_once dirname(__FILE__) . '/include/lightningimport-admin.php';
include_once dirname(__FILE__) . '/include/lightningimport-apihelper.php';
include_once dirname(__FILE__) . '/include/lightningimport-imagehelper.php';
include_once dirname(__FILE__) . '/include/lightningimport-searchhelper.php';

//This hook runs at plugin install and runs the function for creating the db objects below.
register_activation_hook(__FILE__, 'lightningimport_plugin_activation');

//Register hook to run at deactivation to clean up plugin
register_deactivation_hook(__FILE__, 'lightningimport_plugin_deactivation');

//Runs action at plugin activation
function lightningimport_plugin_activation()
{

    //Obtain plugin directory path
    $plugin_dir = plugin_dir_path(__FILE__);

    //Get the script that creates the custom table for product attributes
    $drop_create_dbobjects_location = $plugin_dir . '/sql/dropcreatecustomtables.sql';
    $create_dbobjects_location = $plugin_dir . '/sql/createcustomtables.sql';
    $create_dbobjects_locationsql = file_get_contents($create_dbobjects_location);
    $drop_create_dbobjects_locationsql = file_get_contents($drop_create_dbobjects_location);
    //Use global wpdb here to get the prefix.
    global $wpdb;
    if ($wpdb->prefix != 'wp_') {
        //Repplace sql with appropriate prefix if necessary
        $create_dbobjects_locationsql = str_replace("wp_", $wpdb->prefix, $create_dbobjects_location);
        $drop_create_dbobjects_locationsql = str_replace("wp_", $wpdb->prefix, $drop_create_dbobjects_locationsql);
    }
    //Run the query
    $wpdb->query($drop_create_dbobjects_locationsql);
    $wpdb->query($create_dbobjects_locationsql);

    //Load

    //Call database creation procedure.
    //First parameter causes the existing tables to drop before creation. This clears any product attributes that were already loaded.
    $wpdb->query('CALL lightningimport_create_db_objects(0)');

}

lightningimport_admin::lightningimport_RunRequirementCheck();

add_filter('query_vars', 'lightningimport_query_vars');
add_action('wp', 'lightningimport_parse_request');

function lightningimport_parse_request($wp)
{
    if (array_key_exists('action', $wp->query_vars) && array_key_exists('index', $wp->query_vars) && array_key_exists('filter', $wp->query_vars)
        && $wp->query_vars['action'] == 'lightningimport_GetDropDownList') {
        lightningimport_searchhelper::lightningimport_GetDropDownList($wp->query_vars['index'], $wp->query_vars['filter']);
    }
}

// Adding the id var so that WP recognizes it
function lightningimport_query_vars($vars)
{
    array_push($vars, 'action', 'index', 'filter');
    return $vars;
}

/**
 * On deactivation, remove all functions from the scheduled action hook.
 */
function lightningimport_plugin_deactivation()
{
    $customsearchformfile = $theme_dir = get_stylesheet_directory() . '/product-searchform.php';
    delete_option('FastBulkWCImportOptions');
    wp_clear_scheduled_hook('slnzDataScheduleAction');
    wp_clear_scheduled_hook('slnzImageScheduleAction');
    if (file_exists($customsearchformfile)) {
        unlink($customsearchformfile);
    }
}

//Load js scripts for custom search functionality. array('jquery') forces jqeury to load first
add_action('wp_enqueue_scripts', 'lightningimport_load_js_scripts');
function lightningimport_load_js_scripts()
{
    wp_enqueue_script('the_js', plugins_url('/js/lightningimport-search.js', __FILE__), array('jquery'), true);
}

add_action('wp_enqueue_scripts', 'lightningimport_admin::lightningimport_initCss');

if (!defined('ABSPATH')) {
    exit;
}
//Exit if accessed directly

//help functions
if (!function_exists('notempty')) {
    function notempty($var)
    {
        return ($var === "0" || $var);
    }
}

//Set global settings to allow for larger uploads and better performance on large requests.
$Product = '';
$memory_limit = (int) (@ini_get('memory_limit'));
$max_upload = (int) (@ini_get('upload_max_filesize'));
$max_post = (int) (@ini_get('post_max_size'));

$upload_mb = min($memory_limit, $max_upload, $max_post) . 'MB';
unset($memory_limit, $max_upload, $max_post);

@ini_set('auto_detect_line_endings', true);

add_action('wp_ajax_slnz_import_trigger', 'slnz_import_trigger');
add_action('wp_ajax_nopriv_slnz_import_trigger', 'slnz_import_trigger');

function slnz_import_trigger()
{
    try {
        lightningimport_apihelper::lightningimport_ImportProductData();
        lightningimport_imagehelper::lightningimport_ImportImageData();
    } catch (Exception $ex) {
        lightningimport_apihelper::lightningimport_writeToLog('An uncaught exception occurred during import processing: ' . print_r($ex, true));
    }
}
