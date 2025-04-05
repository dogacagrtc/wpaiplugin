<?php
/*
Plugin Name: OpenAI Integration2
Description: Allows interaction with OpenAI's API to send prompts and schedule posts with automation.
Version: 1.1
Author: Dogac A.
*/


// **Include all necessary plugin files**
include_once('prompts.php');
include_once('functions.php');
include_once('pages.php');
include_once('settings.php');
include_once('scheduled_content.php');
include_once('link_content.php');

// **Register Activation Hook for Database Tables**
function openai_install() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // **Create openai_texts table**
    $table_name = $wpdb->prefix . 'openai_texts';
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        title VARCHAR(255) NOT NULL,
        text_content longtext NOT NULL,
        prompt_category ENUM('content', 'image', 'title', 'tag') NOT NULL,  
        UNIQUE KEY id (id)
    ) $charset_collate;";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // **Create openai_scraped_content table**
    $table_name = $wpdb->prefix . 'openai_scraped_content';
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        url text NOT NULL,
        content longtext NOT NULL,
        date_scraped datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta($sql);

    // **Create openai_rss_feeds table**
    $table_name = $wpdb->prefix . 'openai_rss_feeds';
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        title varchar(255) NOT NULL,
        url varchar(255) NOT NULL,
        date_added datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'openai_install');

// **Admin Initialization for Settings**
add_action('admin_init', 'openai_admin_init');

function openai_admin_init(){
    // Main settings
    register_setting('openai-main-options', 'openai_main_options', 'openai_options_validate');
    add_settings_section('openai_main', 'Main Settings', 'openai_section_text', 'openai-plugin');
    add_settings_field('openai_api_key', 'API Key', 'openai_setting_string', 'openai-plugin', 'openai_main');

    // Content settings
    register_setting('openai-content-options', 'openai_content_options', 'openai_content_validate');
    add_settings_section('openai_content_main', 'Main Settings', 'openai_content_text', 'myplugin-content2');
    add_settings_field('openai_content', 'Content', 'openai_setting_content', 'myplugin-content2', 'openai_content_main');

    // Prompt settings
    register_setting('openai_prompt_options', 'openai_prompt_options', 'openai_prompt_options_validate');
    add_settings_section('openai_prompts_main', 'Prompt Settings', 'openai_prompts_section_text', 'openai-prompts');
    add_settings_field('openai_prompt', 'Prompt', 'openai_setting_prompt', 'openai-prompts', 'openai_prompts_main');
}

// **Admin Menu Setup**
add_action('admin_menu', 'openai_plugin_setup_menu');

function openai_plugin_setup_menu(){
    add_menu_page('OpenAI Plugin Page', 'MyPlugin', 'manage_options', 'openai-plugin', 'openai_init');
    add_submenu_page('openai-plugin', 'Prompts', 'Manage Prompts', 'manage_options', 'myplugin-prompts', 'openai_prompts_init');
    add_submenu_page('openai-plugin', 'Scheduled Content', 'Scheduled Content', 'manage_options', 'myplugin-scheduled-content', 'openai_scheduled_content_init');
    add_submenu_page('openai-plugin', 'Plugin Settings', 'Settings', 'manage_options', 'myplugin-settings', 'settings_init');
    add_submenu_page('openai-plugin', 'Link Content', 'Link Content', 'manage_options', 'myplugin-link-content', 'openai_link_content_init');
}

// **Cron Job for Publishing Scheduled Content**
function openai_check_table_structure() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'openai_scheduled_content';

    $result = $wpdb->get_results("SHOW TABLES LIKE '$table_name'");
    if (count($result) == 0) {
        echo "Table does not exist!";
        return;
    }

    $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
    echo "Table structure:<br>";
    foreach ($columns as $column) {
        echo $column->Field . " - " . $column->Type . "<br>";
    }
}

// **Function to Publish Scheduled Content**
function publish_scheduled_content() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'openai_scheduled_content';

    $current_time = current_time('mysql');
    $scheduled_posts = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM $table_name WHERE scheduled_time <= %s AND status = 'scheduled'",
            $current_time
        )
    );

    foreach ($scheduled_posts as $scheduled_post) {
        $post_id = wp_insert_post(array(
            'post_title'    => $scheduled_post->title,
            'post_content'  => $scheduled_post->content,
            'post_status'   => 'publish',
            'post_author'   => get_current_user_id(),
            'post_type'     => 'post',
        ));

        if ($post_id) {
            // Update the status in the scheduled_content table
            $wpdb->update(
                $table_name,
                array('status' => 'published'),
                array('id' => $scheduled_post->id),
                array('%s'),
                array('%d')
            );

            error_log('Published scheduled post with ID: ' . $post_id);
        } else {
            error_log('Failed to publish scheduled post: ' . $scheduled_post->id);
        }
    }
}
// Hook the function to run as a cron event
add_action('openai_publish_scheduled_content', 'publish_scheduled_content');

// **Register the Cron Event on Plugin Activation**
register_activation_hook(__FILE__, 'openai_activate_cron');

function openai_activate_cron() {
    if (!wp_next_scheduled('openai_publish_scheduled_content')) {
        wp_schedule_event(time(), 'fifteen_minutes', 'openai_publish_scheduled_content');
    }
}

// **Add a Custom Cron Schedule for Every 15 Minutes**
add_filter('cron_schedules', 'add_fifteen_minutes_cron_schedule');
function add_fifteen_minutes_cron_schedule($schedules) {
    $schedules['fifteen_minutes'] = array(
        'interval' => 15 * 60,
        'display'  => __('Every 15 minutes'),
    );
    return $schedules;
}

// **Enqueue Admin Scripts**
function openai_enqueue_admin_scripts($hook) {
    // **Check if the current admin page is related to the plugin**
    if (strpos($hook, 'myplugin') !== false) { // Adjust 'myplugin' to match your admin page slug
        wp_enqueue_script('jquery-ui-dialog');
        wp_enqueue_script('jquery-ui-progressbar');
        wp_enqueue_style('wp-jquery-ui-dialog');
        wp_enqueue_script('openai-ajax', plugin_dir_url(__FILE__) . 'js/openai-ajax.js', array('jquery'), '1.0', true);
        wp_localize_script('openai-ajax', 'openai_ajax_object', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('openai_ajax_nonce')
        ));
    }
}
add_action('admin_enqueue_scripts', 'openai_enqueue_admin_scripts');

register_activation_hook(__FILE__, 'create_automation_tables');
register_activation_hook(__FILE__, 'create_content_automations_table');

if (isset($_POST['schedule_content'])) {
    // ... existing validation ...

    $content_source = sanitize_text_field($_POST['content_source']);

    if ($content_source === 'csv_file') {
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            echo '<div class="error"><p>Error uploading CSV file.</p></div>';
            return;
        }

        $csv_column = sanitize_text_field($_POST['csv_column']);
        $csv_batch_size = intval($_POST['csv_batch_size']);

        $upload_dir = wp_upload_dir();
        $csv_file_path = $upload_dir['path'] . '/' . basename($_FILES['csv_file']['name']);

        if (move_uploaded_file($_FILES['csv_file']['tmp_name'], $csv_file_path)) {
            $result = process_csv_file($csv_file_path, $csv_column, $csv_batch_size, $prompt_id);
            echo '<div class="updated"><p>' . $result . '</p></div>';
        } else {
            echo '<div class="error"><p>Failed to move uploaded file.</p></div>';
        }
    } else {
        // ... existing code for other content sources ...
    }
}

?>