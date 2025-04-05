<?php
require_once plugin_dir_path(__FILE__) . 'scheduled_frontend.php';
require_once plugin_dir_path(__FILE__) . 'scheduled_content.php';


function handle_rss_feed_scheduling_automation($wpdb, $form_data, $rss_feeds_table, $prompts_table, $scraped_content_table, $prompt, $title_option, $custom_title, $featured_image_option, $schedule_time, $rss_interval, $tag_option, $manual_tags, $tag_prompt_id, $include_content_for_tags, $automation_id) {
    $rss_feed_id = $form_data['rss_feed_id'];
    $rss_post_count = $form_data['rss_post_count'];
    $feed = $form_data['rss_feed'];
    error_log("handle_rss_feed_scheduling_automation called");
    if ($feed) {
        $feed_url = $feed->url;
        $rss_items = fetch_rss_items($feed_url, $rss_post_count);
        $current_item2 = 0;

        $results = array();
        foreach ($rss_items as $item) {
            try {
                ++$current_item2;
                $result = process_rss_item_automation($item, $form_data, $automation_id, $current_item2);
                $results[] = $result;
                error_log("Processed RSS item: " . $result['title']);
            } catch (Exception $e) {
                error_log("Error processing RSS item: " . $e->getMessage());
            }
        }
        
        return $results;
    } else {
        error_log("Selected RSS feed not found.");
        return array('error' => 'Selected RSS feed not found.');
    }
}


///-----------------------------------
function add_rss_processing_automation_script() {
    error_log("add_rss_processing_automation_script called");
    ?>
    <script>
function processRSSFeed(data) {
    // **Add the action parameter and nonce**
    data.action = 'process_rss_feed_automation';
    data.security = openai_ajax_object.nonce;

    // Include the automation ID
    data.automation_id = data.automation_id; // Ensure automation_id is set in data

    // Rest of your AJAX code...

    console.log('Attempting to process RSS feed with data:', data);

    jQuery.ajax({
        url: openai_ajax_object.ajax_url,
        type: 'POST',
        data: data,
        dataType: 'json',
        success: function(response) {
            console.log('AJAX response:', response);
            if (response.success) {
                // Update progress bar and message
                jQuery("#rss-progress-bar").progressbar("value", response.data.progress);
                jQuery("#rss-progress-message").append("<p>" + response.data.message + "</p>");
                
                // Display processing result if available
                if (response.data.result) {
                    console.log('Item processing result:', response.data.result);
                    jQuery("#rss-progress-message").append("<p>Processed: " + JSON.stringify(response.data.result) + "</p>");
                }

                // Scroll to the bottom of the progress updates
                jQuery("#rss-progress-message").scrollTop(jQuery("#rss-progress-message")[0].scrollHeight);
                
                if (response.data.done) {
                    // All items processed; close the dialog
                    console.log('All items processed. Closing dialog.');
                    jQuery("#rss-processing-dialog").dialog("option", "buttons", {
                        "Close": function() {
                            jQuery(this).dialog("close");
                        }
                    });
                } else if (response.data.next_item !== null && response.data.next_item !== undefined) {
                    // Log the next item to be processed
                    console.log('Preparing to process next item:', response.data.next_item);
                    
                    // Clone the data object to preserve all fields
                    var nextData = jQuery.extend({}, data);
                    nextData.current_item = response.data.next_item;

                    console.log('Next item data:', nextData);

                    // Schedule the next AJAX call
                    setTimeout(function() {
                        processRSSFeed(nextData);
                    }, 1000); // 1-second delay between requests
                }
            } else {
                // Handle error responses
                console.error('Error in AJAX response:', response);
                var errorMessage = response.data && response.data.message ? response.data.message : 'Unknown error occurred';
                jQuery("#rss-progress-message").append("<p style='color: red;'>Error: " + errorMessage + "</p>");
                jQuery("#rss-processing-dialog").dialog("option", "buttons", {
                    "Close": function() {
                        jQuery(this).dialog("close");
                    }
                });
            }
            // Ensure the progress message container scrolls to the latest message
            jQuery("#rss-progress-message").scrollTop(jQuery("#rss-progress-message")[0].scrollHeight);
        },
        error: function(jqXHR, textStatus, errorThrown) {
            // Log AJAX errors
            console.error('AJAX error:', textStatus, errorThrown);
            var errorMessage = 'An error occurred while processing the RSS feed';
            
            if (jqXHR.responseText) {
                console.log('Raw response:', jqXHR.responseText);
                try {
                    // Attempt to parse JSON from the response
                    var jsonStartIndex = jqXHR.responseText.indexOf('{');
                    var jsonEndIndex = jqXHR.responseText.lastIndexOf('}') + 1;
                    var jsonString = jqXHR.responseText.substring(jsonStartIndex, jsonEndIndex);
                    var jsonResponse = JSON.parse(jsonString);
                    if (jsonResponse.data && jsonResponse.data.message) {
                        errorMessage = jsonResponse.data.message;
                    }
                } catch (e) {
                    // Log parsing errors
                    console.error('Error parsing JSON response:', e);
                    errorMessage += ': ' + jqXHR.responseText;
                }
            }
            
            // Display the error message
            jQuery("#rss-progress-message").append("<p style='color: red;'>" + errorMessage + "</p>");
            jQuery("#rss-progress-message").scrollTop(jQuery("#rss-progress-message")[0].scrollHeight);
            jQuery("#rss-processing-dialog").dialog("option", "buttons", {
                "Close": function() {
                    jQuery(this).dialog("close");
                }
            });
        }
    });
}
</script>
    <?php
}

///-----------------------------------

add_action('wp_ajax_process_rss_feed_automation', 'process_rss_feed_automation_ajax');
// Note: Removed 'wp_ajax_nopriv_process_rss_feed' unless non-logged-in users should access

function process_rss_feed_automation_ajax() {
        // Enable error logging for debugging purposes
    ini_set('log_errors', 1);
    ini_set('error_log', WP_CONTENT_DIR . '/debug.log');
    error_log("process_rss_feed_automation_ajax called.");

    // Disable error reporting and output buffering
    @error_reporting(0);
    @ini_set('display_errors', 0);
    ob_start();

    header('Content-Type: application/json');

    try {
        // Verify nonce for security
        if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'openai_ajax_nonce_automation')) {
            throw new Exception('Nonce verification failed.');
        }

        // Get the automation ID from the AJAX request
        $automation_id = isset($_POST['automation_id']) ? intval($_POST['automation_id']) : 0;
        if ($automation_id === 0) {
            throw new Exception('Invalid automation ID.');
        }

        // Retrieve form data from the database using automation_id
        global $wpdb;
        $table_name = $wpdb->prefix . 'content_automations';
        $automation = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $automation_id));

        if (!$automation) {
            throw new Exception('Automation not found.');
        }

        $form_data = maybe_unserialize($automation->form_data);

        // Now you can access $form_data['rss_feed']->url and other variables
        $feed_url = $form_data['rss_feed']->url;
        $rss_post_count = $form_data['rss_post_count'];

        $current_item = $form_data['current_item'];
        $time_ran = $automation->time_ran;
        $interval_minutes = $automation->interval_minutes;

        // Log incoming request details
        error_log("Received AJAX request with current_item: $current_item");

        if ($current_item === 0) {
            // Initial call: Fetch RSS items
            $rss_items = fetch_rss_items($feed_url, $rss_post_count);
            update_option('openai_rss_items_automation', $rss_items);
            error_log("Fetched and stored " . count($rss_items) . " RSS items.");

            wp_send_json_success(array(
                'message' => "Found " . count($rss_items) . " items in the feed.",
                'progress' => 0,
                'total_items' => count($rss_items),
                'next_item' => (count($rss_items) > 0) ? 1 : null
            ));
        } else {
            // Subsequent calls: Process specified item
            $rss_items = get_option('openai_rss_items_automation    ', array());

            // Log the number of items retrieved from the option
            error_log("Retrieved " . count($rss_items) . " items from openai_rss_items option.");

            if ($current_item <= count($rss_items)) {
                $item = $rss_items[$current_item - 1];
                error_log("Processing item $current_item: " . print_r($item, true));

                // Process the current RSS item
                $result = process_rss_item_automation($item, $form_data, $automation_id);

                // Calculate progress and determine next item
                $progress = ($current_item / count($rss_items)) * 100;
                $next_item = $current_item + 1;
                $is_done = $next_item > count($rss_items);


                // Log processing outcome
                error_log("Processed item $current_item. Progress: $progress%. Next item: " . ($is_done ? 'null' : $next_item));

                wp_send_json_success(array(
                    'message' => "Processed item $current_item of " . count($rss_items),
                    'progress' => $progress,
                    'next_item' => $is_done ? null : $next_item,
                    'result' => $result,
                    'done' => $is_done
                ));
            } else {
                throw new Exception('Invalid item index: ' . $current_item);
            }
        }
    } catch (Exception $e) {
        // Log any exceptions and send an error response
        error_log("process_rss_feed_automation_ajax Exception: " . $e->getMessage());
        wp_send_json_error(array('message' => $e->getMessage()));
    }

    // Clear output buffer and end execution
    ob_end_clean();
    wp_die();
}

function process_rss_item_automation($item, $post_data, $automation_id, $current_item2) {
    global $wpdb;
    $prompts_table = $wpdb->prefix . 'openai_texts';
    $scraped_content_table = $wpdb->prefix . 'openai_scraped_content';

    global $wpdb;
    $table_name = $wpdb->prefix . 'content_automations';
    $automation = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $automation_id));

    if (!$automation) {
        throw new Exception('Automation not found.');
    }
    $time_ran = $automation->time_ran;
    $interval_minutes = $automation->interval_minutes;


    try {
        $current_item = isset($post_data['current_item']) ? intval($post_data['current_item']) : 1;





        // Retrieve and sanitize necessary POST data
        error_log("process_rss_item_automation called");
        $title_option = isset($post_data['title_option']) ? sanitize_text_field($post_data['title_option']) : 'generate';
        $custom_title = isset($post_data['custom_title']) ? sanitize_text_field($post_data['custom_title']) : '';
        $prompt = isset($post_data['prompt']) ? sanitize_text_field($post_data['prompt']) : '';
        $featured_image_option = isset($post_data['featured_image_option']) ? sanitize_text_field($post_data['featured_image_option']) : 'none';
        $schedule_time = isset($post_data['schedule_time']) ? sanitize_text_field($post_data['schedule_time']) : current_time('mysql');
        $rss_interval = isset($post_data['rss_interval']) ? intval($post_data['rss_interval']) : 0;
        $tag_option = isset($post_data['tag_option']) ? sanitize_text_field($post_data['tag_option']) : 'manual';
        $manual_tags = isset($post_data['manual_tags']) ? sanitize_text_field($post_data['manual_tags']) : '';
        $tag_prompt_id = isset($post_data['tag_prompt_id']) ? intval($post_data['tag_prompt_id']) : 0;
        $include_content_for_tags = isset($post_data['include_content_for_tags']) ? 1 : 0;
        $title_prompt_id = isset($post_data['title_prompt_id']) ? intval($post_data['title_prompt_id']) : 0;
        // Calculate the actual schedule time based on the interval (in days) and current item
        $interval_seconds = $rss_interval * 24 * 3600; // Convert days to seconds
        $actual_schedule_time = date('Y-m-d H:i:s', strtotime($schedule_time) + (($current_item2 - 1) * $interval_seconds));
        $actual_schedule_time2 = date('Y-m-d H:i:s', strtotime($actual_schedule_time) + ($interval_minutes * 60 * $time_ran));

        // Get content based on source
        $content_source = isset($post_data['content_source']) ? sanitize_text_field($post_data['content_source']) : 'manual';
        $feed_content = get_content_based_on_source($wpdb, $scraped_content_table, $content_source);
        // Validate required fields
        if (empty($prompt)) {
            throw new Exception('Prompt is empty.');
        }
        $post_content = scrapeWebContent($item['permalink']);
        error_log("schedule_time: " . $schedule_time);
        error_log("actual_schedule_time: " . $actual_schedule_time);
        error_log("actual_schedule_time2: " . $actual_schedule_time2);
        error_log("current_item: " . $current_item2);
        error_log("time_ran: " . $time_ran);
        error_log("interval_minutes: " . $interval_minutes);
        error_log("automation_id: " . $automation_id);
        // Generate Post Title using OpenAI
        $post_title = generate_post_title_automation($wpdb, $title_prompt_id, $prompts_table, $title_option, $custom_title, $post_content, $scheduled_count);

        // Generate Post Content using OpenAI
        $content_prompt = $prompt;
        $generated_content = anthropic_interact_with_api($content_prompt, $post_content);

        // Schedule the Post with the calculated time
        $post_id = schedule_post($post_title, $generated_content, $actual_schedule_time2);
        if (!$post_id) {
            throw new Exception('Failed to create post.');
        }


        // Handle tags
        $tag_prompt = $wpdb->get_var($wpdb->prepare("SELECT text_content FROM $prompts_table WHERE id = %d", $tag_prompt_id));

        $generated_tags = openai_interact_with_api($tag_prompt, $generated_content);
        wp_set_post_tags($post_id, explode(',', $generated_tags));
        error_log("Generated tags: " . $generated_tags);
        error_log("Generated content: " . $generated_content);
        handle_featured_image_automation($wpdb, $prompts_table, $post_data['featured_image_option'], $post_id, $post_title, $generated_content, $post_data, $generated_tags);

        // Return processing result
        return array(
            'title' => $post_title,
            'post_id' => $post_id,
            'content_length' => strlen($generated_content),
            'image_result' => ($featured_image_option === 'auto') ? $image_url : 'none',
            'scheduled_time' => $actual_schedule_time2, // Add this line to return the actual scheduled time
        );
    } catch (Exception $e) {
        // Log the exception and return an error
        error_log("process_rss_item Exception: " . $e->getMessage());
        return array('error' => $e->getMessage());
    }
}





add_action('wp_ajax_start_automation', 'start_automation_ajax');
add_action('wp_ajax_load_automations', 'load_automations_ajax');
add_action('wp_ajax_delete_automation', 'delete_automation_ajax');

function start_automation_ajax() {
    error_log("start_automation_ajax called");
    global $wpdb;
    $automation_minutes = intval($_POST['automation_minutes']);
    $prompts_table = $wpdb->prefix . 'openai_texts';
    $prompt_id = isset($_POST['prompt_id']) ? intval($_POST['prompt_id']) : 0;
    // Collect all form data
    $form_data = array(
        'feed_url' => isset($_POST['feed_url']) ? sanitize_text_field($_POST['feed_url']) : '',
        'rss_post_count' => isset($_POST['rss_post_count']) ? intval($_POST['rss_post_count']) : 0,
        'current_item' => isset($_POST['current_item']) ? intval($_POST['current_item']) : 0,
        'title_prompt_id' => isset($_POST['title_prompt_id']) ? intval($_POST['title_prompt_id']) : 0,
        'include_content_text' => isset($_POST['include_content_text']) ? (bool)$_POST['include_content_text'] : false,
        'content_source' => isset($_POST['content_source']) ? sanitize_text_field($_POST['content_source']) : 'manual',
        'prompt_id' => isset($_POST['prompt_id']) ? intval($_POST['prompt_id']) : 0,
        'post_title_format' => isset($_POST['post_title_format']) ? sanitize_text_field($_POST['post_title_format']) : '',
        'post_content_format' => isset($_POST['post_content_format']) ? sanitize_textarea_field($_POST['post_content_format']) : '',
        'categories' => isset($_POST['post_category']) ? array_map('intval', $_POST['post_category']) : array(),
        'tags' => isset($_POST['tags_input']) ? sanitize_text_field($_POST['tags_input']) : '',
        'featured_image' => isset($_POST['featured_image']) ? sanitize_text_field($_POST['featured_image']) : '',
        'schedule_date' => isset($_POST['schedule_date']) ? sanitize_text_field($_POST['schedule_date']) : '',
        'schedule_time' => isset($_POST['schedule_time']) ? sanitize_text_field($_POST['schedule_time']) : current_time('mysql'),
        'title_option' => isset($_POST['title_option']) ? sanitize_text_field($_POST['title_option']) : 'generate',
        'custom_title' => isset($_POST['custom_title']) ? sanitize_text_field($_POST['custom_title']) : '',
        'prompt' => isset($_POST['prompt']) ? sanitize_text_field($_POST['prompt']) : '',
        'featured_image_option' => isset($_POST['featured_image_option']) ? sanitize_text_field($_POST['featured_image_option']) : 'none',
        'rss_interval' => isset($_POST['rss_interval']) ? intval($_POST['rss_interval']) : 0,
        'tag_option' => isset($_POST['tag_option']) ? sanitize_text_field($_POST['tag_option']) : 'manual',
        'manual_tags' => isset($_POST['manual_tags']) ? sanitize_text_field($_POST['manual_tags']) : '',
        'tag_prompt_id' => isset($_POST['tag_prompt_id']) ? intval($_POST['tag_prompt_id']) : 0,
        'include_content_for_tags' => isset($_POST['include_content_for_tags']) ? (bool)$_POST['include_content_for_tags'] : false,
        'table_name' => $wpdb->prefix . 'openai_scheduled_content',
        'rss_feeds_table' => $wpdb->prefix . 'openai_rss_feeds',
        'prompts_table' => $wpdb->prefix . 'openai_texts',
        'scraped_content_table' => $wpdb->prefix . 'openai_scraped_content',
        'prompt' => $wpdb->get_var($wpdb->prepare("SELECT text_content FROM $prompts_table WHERE id = %d", $prompt_id)),
        // Add the new variables
        'rss_feed_id' => isset($_POST['rss_feed_id']) ? intval($_POST['rss_feed_id']) : 0,
        'image_prompt_id' => isset($_POST['image_prompt_id']) ? intval($_POST['image_prompt_id']) : 0,
        'include_content_text' => isset($_POST['include_content_text']) ? true : false,
    );

    // Add the RSS feed data
    if ($form_data['rss_feed_id'] > 0) {
        $rss_feeds_table = $wpdb->prefix . 'openai_rss_feeds';
        $feed = $wpdb->get_row($wpdb->prepare("SELECT * FROM $rss_feeds_table WHERE id = %d", $form_data['rss_feed_id']));
        if ($feed) {
            $form_data['rss_feed'] = $feed;
        }
    }

    // Save the automation details to the database
    $automation_id = save_automation($automation_minutes, $form_data);

    if ($automation_id) {
        // Create a new table for this automation
        create_automation_table($automation_id);

        // Schedule the next run
        wp_schedule_single_event(time() + ($automation_minutes * 60), 'run_content_automation', array($automation_id));

        wp_send_json_success(array('message' => 'Automation started successfully'));
    } else {
        wp_send_json_error(array('message' => 'Failed to start automation'));
    }
}

function create_automation_table($automation_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automation_' . $automation_id;
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        variable_name varchar(255) NOT NULL,
        variable_value longtext NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

function load_automations_ajax() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'content_automations';

    $automations = $wpdb->get_results("SELECT * FROM $table_name ORDER BY next_run ASC");

    ob_start();
    foreach ($automations as $automation) {
        ?>
        <tr>
            <td><?php echo $automation->id; ?></td>
            <td><?php echo $automation->interval_minutes; ?> minutes</td>
            <td><?php echo date('Y-m-d H:i:s', $automation->next_run); ?></td>
            <td>
                <button class="button edit-automation" data-id="<?php echo $automation->id; ?>">Edit</button>
                <button class="button delete-automation" data-id="<?php echo $automation->id; ?>">Delete</button>
            </td>
        </tr>
        <?php
    }
    $html = ob_get_clean();

    echo $html;
    wp_die();
}

function delete_automation_ajax() {
    global $wpdb;
    $automation_id = intval($_POST['automation_id']);

    // Remove the scheduled event
    wp_clear_scheduled_hook('run_content_automation', array($automation_id));

    // Delete the automation from the database
    $table_name = $wpdb->prefix . 'content_automations';
    $wpdb->delete($table_name, array('id' => $automation_id), array('%d'));

    // Delete the automation-specific table
    $automation_table = $wpdb->prefix . 'automation_' . $automation_id;
    $wpdb->query("DROP TABLE IF EXISTS $automation_table");

    wp_send_json_success(array('message' => 'Automation deleted successfully'));
}

function save_automation($interval_minutes, $form_data) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'content_automations';

    $wpdb->insert(
        $table_name,
        array(
            'interval_minutes' => $interval_minutes,
            'form_data' => maybe_serialize($form_data),
            'next_run' => time() + ($interval_minutes * 60)
        ),
        array('%d', '%s', '%d')
    );

    return $wpdb->insert_id; // This returns the automation_id
}

add_action('run_content_automation', 'execute_content_automation');

function my_custom_automation_function($form_data) {
    // Your custom automation logic goes here
    // This function will use the data from the form to create content
    // For example:
    // $content_source = $form_data['content_source'];
    // $prompt_id = $form_data['prompt_id'];
    // ... (rest of your automation logic)


//// FORM DATA YA VERİ GÖNDERİYORUM FAKAT HEPSİ GEÇMİYOR, TÜM VERİLERİ GÖNDERMEYİ DENEMEM GEREK 
}

function execute_content_automation($automation_id) {
    error_log("execute_content_automation called");
    global $wpdb;
    $table_name = $wpdb->prefix . 'content_automations';
    $automation_table = $wpdb->prefix . 'automation_' . $automation_id;

    $automation = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $automation_id));

    if (!$automation) {
        error_log("Automation ID: $automation_id not found in the database.");
        return;
    }

    error_log("Raw form_data from database: " . print_r($automation->form_data, true));

    $form_data = maybe_unserialize($automation->form_data);

    


    if (!is_array($form_data)) {
        error_log("Unserialized form_data is not an array. Type: " . gettype($form_data));
        error_log("Unserialized form_data content: " . print_r($form_data, true));
        return;
    }

    // Log the start of the automation
    error_log("Starting automation ID: $automation_id");

    try {
        // Prepare the necessary parameters for handle_rss_feed_scheduling_automation
        $rss_feeds_table = $wpdb->prefix . 'openai_rss_feeds';
        $prompts_table = $wpdb->prefix . 'openai_texts';
        $scraped_content_table = $wpdb->prefix . 'openai_scraped_content';

        $prompt = isset($form_data['prompt']) ? $form_data['prompt'] : '';
        $title_option = isset($form_data['title_option']) ? $form_data['title_option'] : 'generate';
        $custom_title = isset($form_data['custom_title']) ? $form_data['custom_title'] : '';
        $featured_image_option = isset($form_data['featured_image_option']) ? $form_data['featured_image_option'] : 'none';
        $schedule_time = isset($form_data['schedule_time']) ? $form_data['schedule_time'] : current_time('mysql');
        $rss_interval = isset($form_data['rss_interval']) ? intval($form_data['rss_interval']) : 0;
        $tag_option = isset($form_data['tag_option']) ? $form_data['tag_option'] : 'manual';
        $manual_tags = isset($form_data['manual_tags']) ? $form_data['manual_tags'] : '';
        $tag_prompt_id = isset($form_data['tag_prompt_id']) ? intval($form_data['tag_prompt_id']) : 0;
        $include_content_for_tags = isset($form_data['include_content_for_tags']) ? 1 : 0;


        $content_automations_table = $wpdb->prefix . 'content_automations'; // Using $wpdb->prefix to include the correct table prefix
        
        $time_ran = $automation->time_ran;
        $interval_minutes = $automation->interval_minutes;
        ++$time_ran; // Increment the time_ran
        
        // Update the database with the new time_ran value
        $updated = $wpdb->update(
            $content_automations_table,
            array('time_ran' => $time_ran), // Data to update
            array('id' => $automation_id), // Where clause
            array('%d'), // Data format (integer for time_ran)
            array('%d')  // Where clause format (integer for ID)
        );
        
        if ($updated === false) {
            // Handle the error
            echo "Update failed: " . $wpdb->last_error;
        } else {
            echo "Update successful.";
        }
        
        
        // Call handle_rss_feed_scheduling_automation
        $result = handle_rss_feed_scheduling_automation(
            $wpdb, 
            $form_data, 
            $rss_feeds_table, 
            $prompts_table, 
            $scraped_content_table, 
            $prompt, 
            $title_option, 
            $custom_title, 
            $featured_image_option, 
            $schedule_time, 
            $rss_interval, 
            $tag_option, 
            $manual_tags, 
            $tag_prompt_id, 
            $include_content_for_tags,
            $automation_id,
        );

        // Store result in the automation-specific table
        if (is_array($result)) {
            foreach ($result as $key => $value) {
                $wpdb->insert(
                    $automation_table,
                    array(
                        'variable_name' => $key,
                        'variable_value' => maybe_serialize($value)
                    ),
                    array('%s', '%s')
                );
            }
        }

        // Log the successful completion
        error_log("Automation ID: $automation_id completed successfully");

    } catch (Exception $e) {
        // Log any errors that occur during processing
        error_log("Error in automation ID: $automation_id - " . $e->getMessage());
    }

    // Update the next run time
    $next_run = time() + ($automation->interval_minutes * 60);
    $wpdb->update(
        $table_name,
        array('next_run' => $next_run),
        array('id' => $automation_id),
        array('%d'),
        array('%d')
    );

    // Schedule the next run
    wp_schedule_single_event($next_run, 'run_content_automation', array($automation_id));
}



// Add this function to create the necessary database table
function create_content_automations_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'content_automations';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        interval_minutes int(11) NOT NULL,
        form_data text NOT NULL,
        next_run int(11) NOT NULL,
        time_ran int(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
function generate_post_title_automation($wpdb, $title_prompt_id, $prompts_table, $title_option, $custom_title, $content, $count) {
    if ($title_option === 'generate') {
        // Assuming 'update_progress' is handled elsewhere or can be omitted
        $title_prompt_text = $wpdb->get_var($wpdb->prepare("SELECT text_content FROM $prompts_table WHERE id = %d", $title_prompt_id));
        $generated_title = anthropic_interact_with_api($title_prompt_text, $content);
        return wp_strip_all_tags($generated_title);
    } elseif ($title_option === 'custom') {
        $title = $custom_title . ' ' . ($count + 1);
        return sanitize_text_field($title);
    }
    return sanitize_text_field($custom_title);
}

function handle_featured_image_automation($wpdb, $prompts_table, $featured_image_option, $post_id, $post_title, $content, $form_data, $generated_tags) {
    
    if ($featured_image_option === 'auto') {
        $image_prompt_id = $form_data['image_prompt_id'];
        $image_prompt_text = $wpdb->get_var($wpdb->prepare("SELECT text_content FROM $prompts_table WHERE id = %d", $image_prompt_id));
        
        // Add this new code to handle include_content_text
        if ( true === $form_data['include_content_text']) {
            $content = substr($content, 0, 500);
        } else {
            $content = '';
        }
        
        $image_url = openai_generate_image($image_prompt_text, $post_title, $generated_tags);
        
        if ($image_url && strpos($image_url, "Error:") !== 0) {
            $custom_filename = sanitize_title($post_title) . '_' . $post_id;
            handle_auto_image_upload($post_id, $image_url, $custom_filename);
        } else {
        }
    } elseif ($featured_image_option === 'manual' && isset($_FILES['featured_image'])) {
        handle_manual_image_upload($post_id);
    }
}