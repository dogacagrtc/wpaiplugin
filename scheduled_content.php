<?php
require_once plugin_dir_path(__FILE__) . 'scheduled_frontend.php';
require_once plugin_dir_path(__FILE__) . 'automation.php';
require_once plugin_dir_path(__FILE__) . 'csv.php';
add_action('wp_ajax_process_rss_feed', 'process_rss_feed_ajax');
// Note: Removed 'wp_ajax_nopriv_process_rss_feed' unless non-logged-in users should access

function process_rss_feed_ajax() {
    // Enable error logging for debugging purposes
    ini_set('log_errors', 1);
    ini_set('error_log', WP_CONTENT_DIR . '/debug.log');
    error_log("process_rss_feed_ajax called.");

    // Disable error reporting and output buffering
    @error_reporting(0);
    @ini_set('display_errors', 0);
    ob_start();

    header('Content-Type: application/json');

    try {
        // Verify nonce for security
        if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'openai_ajax_nonce')) {
            throw new Exception('Nonce verification failed.');
        }

        // Retrieve and sanitize POST data
        $feed_url = isset($_POST['feed_url']) ? sanitize_text_field($_POST['feed_url']) : '';
        $rss_post_count = isset($_POST['rss_post_count']) ? intval($_POST['rss_post_count']) : 0;
        $current_item = isset($_POST['current_item']) ? intval($_POST['current_item']) : 0;

        // Log incoming request details
        error_log("Received AJAX request with current_item: $current_item");

        if ($current_item === 0) {
            // Initial call: Fetch RSS items
            $rss_items = fetch_rss_items($feed_url, $rss_post_count);
            update_option('openai_rss_items', $rss_items);
            error_log("Fetched and stored " . count($rss_items) . " RSS items.");

            wp_send_json_success(array(
                'message' => "Found " . count($rss_items) . " items in the feed.",
                'progress' => 0,
                'total_items' => count($rss_items),
                'next_item' => (count($rss_items) > 0) ? 1 : null
            ));
        } else {
            // Subsequent calls: Process specified item
            $rss_items = get_option('openai_rss_items', array());

            // Log the number of items retrieved from the option
            error_log("Retrieved " . count($rss_items) . " items from openai_rss_items option.");

            if ($current_item <= count($rss_items)) {
                $item = $rss_items[$current_item - 1];
                error_log("Processing item $current_item: " . print_r($item, true));

                // Process the current RSS item
                $result = process_rss_item($item, $_POST);

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
        error_log("process_rss_feed_ajax Exception: " . $e->getMessage());
        wp_send_json_error(array('message' => $e->getMessage()));
    }

    // Clear output buffer and end execution
    ob_end_clean();
    wp_die();
}


function openai_scheduled_content_init() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'openai_scheduled_content';
    $rss_feeds_table = $wpdb->prefix . 'openai_rss_feeds';
    $prompts_table = $wpdb->prefix . 'openai_texts';
    $scraped_content_table = $wpdb->prefix . 'openai_scraped_content';

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule_content'])) {
        handle_content_scheduling($wpdb, $rss_feeds_table, $prompts_table, $scraped_content_table);
    }

    // Handle bulk delete action
    if (isset($_POST['action']) && $_POST['action'] == 'bulk_delete' && isset($_POST['post'])) {
        handle_bulk_delete();
    }

    // Display the form
    display_scheduling_form($wpdb, $prompts_table, $rss_feeds_table, $scraped_content_table);
}

function handle_content_scheduling($wpdb, $rss_feeds_table, $prompts_table, $scraped_content_table) {
    $content_source = sanitize_text_field($_POST['content_source']);
    $prompt_id = intval($_POST['prompt_id']);
    $title_option = sanitize_text_field($_POST['title_option']);
    $custom_title = sanitize_text_field($_POST['custom_title']);
    $featured_image_option = sanitize_text_field($_POST['featured_image_option']);
    $schedule_time = sanitize_text_field($_POST['schedule_time']);
    $tag_option = sanitize_text_field($_POST['tag_option']);
    $manual_tags = sanitize_text_field($_POST['manual_tags']);
    $tag_prompt_id = intval($_POST['tag_prompt_id']);
    $include_content_for_tags = isset($_POST['include_content_for_tags']) ? 1 : 0;

    // Fetch the prompt text
    $prompt = $wpdb->get_var($wpdb->prepare("SELECT text_content FROM $prompts_table WHERE id = %d", $prompt_id));

    // Start output buffering
    ob_start();

    // Display loading message
    echo '<div id="loading-message" style="display: none;">
        <h2>Scheduling Content</h2>
        <p>Please wait while we process your request...</p>
        <div id="progress-updates"></div>
    </div>';

    // Flush the output buffer
    ob_flush();
    flush();

    // Show the loading message
    echo '<script>document.getElementById("loading-message").style.display = "block";</script>';
    ob_flush();
    flush();

    if ($content_source === 'rss_feed') {
        $rss_interval = intval($_POST['rss_interval']);
        handle_rss_feed_scheduling($wpdb, $rss_feeds_table, $prompts_table, $scraped_content_table, $prompt, $title_option, $custom_title, $featured_image_option, $schedule_time, $rss_interval, $tag_option, $manual_tags, $tag_prompt_id, $include_content_for_tags);
    } else {
        handle_manual_or_scraped_scheduling($wpdb, $prompts_table, $scraped_content_table, $content_source, $prompt, $title_option, $custom_title, $featured_image_option, $schedule_time, $tag_option, $manual_tags, $tag_prompt_id, $include_content_for_tags);
    }

    // Hide the loading message
    echo '<script>document.getElementById("loading-message").style.display = "none";</script>';
    ob_flush();
    flush();

    // End output buffering
    ob_end_flush();
}

function handle_rss_feed_scheduling($wpdb, $rss_feeds_table, $prompts_table, $scraped_content_table, $prompt, $title_option, $custom_title, $featured_image_option, $schedule_time, $rss_interval, $tag_option, $manual_tags, $tag_prompt_id, $include_content_for_tags) {
    $rss_feed_id = intval($_POST['rss_feed_id']);
    $rss_post_count = intval($_POST['rss_post_count']);
    $feed = $wpdb->get_row($wpdb->prepare("SELECT * FROM $rss_feeds_table WHERE id = %d", $rss_feed_id));
    
    if ($feed) {
        $ajax_data = array(
            'action' => 'process_rss_feed',
            'feed_url' => $feed->url,
            'rss_post_count' => $rss_post_count,
            'prompt' => $prompt,
            'title_option' => $title_option,
            'custom_title' => $custom_title,
            'featured_image_option' => $featured_image_option,
            'schedule_time' => $schedule_time,
            'rss_interval' => $rss_interval, // Make sure this is passed
            'title_prompt_id' => intval($_POST['title_prompt_id']),
            'image_prompt_id' => intval($_POST['image_prompt_id']),
            'include_content_text' => isset($_POST['include_content_text']) ? 1 : 0,
            'tag_option' => $tag_option,
            'manual_tags' => $manual_tags,
            'tag_prompt_id' => $tag_prompt_id,
            'include_content_for_tags' => $include_content_for_tags
        );
        
        echo '<div id="rss-processing-dialog" title="Processing RSS Feed">
            <div id="rss-progress-bar"></div>
            <div id="rss-progress-message"></div>
        </div>';
        
        echo '<script>
        jQuery(document).ready(function($) {
            $("#rss-processing-dialog").dialog({
                autoOpen: true,
                modal: true,
                width: 500,
                height: 300,
                closeOnEscape: false,
                open: function(event, ui) {
                    $(".ui-dialog-titlebar-close", ui.dialog | ui).hide();
                }
            });
            
            $("#rss-progress-bar").progressbar({
                value: 0
            });
            
            console.log(\'Attempting to process RSS feed with data:\', ' . json_encode($ajax_data) . ');
            processRSSFeed(' . json_encode($ajax_data) . ');
        });
        </script>';
    } else {
        echo '<div class="error"><p>Selected RSS feed not found.</p></div>';
    }
}

function fetch_rss_items($feed_url, $rss_post_count) {
    include_once(ABSPATH . WPINC . '/feed.php');
    $rss = fetch_feed($feed_url);
    if (is_wp_error($rss)) {
        throw new Exception("Error fetching RSS feed: " . $rss->get_error_message());
    }
    $maxitems = $rss->get_item_quantity($rss_post_count);
    $rss_items = $rss->get_items(0, $maxitems);
    
    // **Convert SimplePie_Item objects to arrays for storage**
    $items_array = array();
    foreach ($rss_items as $item) {
        $items_array[] = array(
            'title' => $item->get_title(),
            'content' => $item->get_content(),
            'permalink' => $item->get_permalink(),
            'date' => $item->get_date('Y-m-d H:i:s')
        );
    }
    error_log("Fetched RSS items: " . print_r($items_array, true)); // **Log fetched items**
    return $items_array;
}

function process_rss_item($item, $post_data) {
    global $wpdb;
    $prompts_table = $wpdb->prefix . 'openai_texts';
    $scraped_content_table = $wpdb->prefix . 'openai_scraped_content';

    try {
        // Retrieve and sanitize necessary POST data
        $title_option = isset($post_data['title_option']) ? sanitize_text_field($post_data['title_option']) : 'generate';
        $custom_title = isset($post_data['custom_title']) ? sanitize_text_field($post_data['custom_title']) : '';
        $prompt = isset($post_data['prompt']) ? sanitize_text_field($post_data['prompt']) : '';
        $featured_image_option = isset($post_data['featured_image_option']) ? sanitize_text_field($post_data['featured_image_option']) : 'none';
        $schedule_time = isset($post_data['schedule_time']) ? sanitize_text_field($post_data['schedule_time']) : current_time('mysql');
        $rss_interval = isset($post_data['rss_interval']) ? intval($post_data['rss_interval']) : 0;
        $current_item = isset($post_data['current_item']) ? intval($post_data['current_item']) : 1;
        $tag_option = isset($post_data['tag_option']) ? sanitize_text_field($post_data['tag_option']) : 'manual';
        $manual_tags = isset($post_data['manual_tags']) ? sanitize_text_field($post_data['manual_tags']) : '';
        $tag_prompt_id = isset($post_data['tag_prompt_id']) ? intval($post_data['tag_prompt_id']) : 0;
        $include_content_for_tags = isset($post_data['include_content_for_tags']) ? 1 : 0;

        // Calculate the actual schedule time based on the interval (in days) and current item
        $interval_seconds = $rss_interval * 24 * 3600; // Convert days to seconds
        $actual_schedule_time = date('Y-m-d H:i:s', strtotime($schedule_time) + (($current_item - 1) * $interval_seconds));

        // Get content based on source
        $content_source = isset($post_data['content_source']) ? sanitize_text_field($post_data['content_source']) : 'manual';
        $feed_content = get_content_based_on_source($wpdb, $scraped_content_table, $content_source);
        // Validate required fields
        if (empty($prompt)) {
            throw new Exception('Prompt is empty.');
        }
        $post_content = scrapeWebContent($item['permalink']);

        // Generate Post Title using OpenAI
        $post_title = generate_post_title($wpdb, $prompts_table, $title_option, $custom_title, $post_content, $scheduled_count);

        // Generate Post Content using OpenAI
        $content_prompt = $prompt;
        $generated_content = openai_interact_with_api($content_prompt, $post_content);

        // Schedule the Post with the calculated time
        $post_id = schedule_post($post_title, $generated_content, $actual_schedule_time);
        if (!$post_id) {
            throw new Exception('Failed to create post.');
        }

        handle_featured_image($wpdb, $prompts_table, $post_data['featured_image_option'], $post_id, $post_title, $generated_content);

        // Handle tags
        $tag_prompt = $wpdb->get_var($wpdb->prepare("SELECT text_content FROM $prompts_table WHERE id = %d", $tag_prompt_id));

        $generated_tags = openai_interact_with_api($tag_prompt, $generated_content);
        wp_set_post_tags($post_id, explode(',', $generated_tags));
        error_log("Generated tags: " . $generated_tags);
        error_log("Generated content: " . $generated_content);

        // Return processing result
        return array(
            'title' => $post_title,
            'post_id' => $post_id,
            'content_length' => strlen($generated_content),
            'image_result' => ($featured_image_option === 'auto') ? $image_url : 'none',
            'scheduled_time' => $actual_schedule_time, // Add this line to return the actual scheduled time
        );
    } catch (Exception $e) {
        // Log the exception and return an error
        error_log("process_rss_item Exception: " . $e->getMessage());
        return array('error' => $e->getMessage());
    }
}


function handle_manual_or_scraped_scheduling($wpdb, $prompts_table, $scraped_content_table, $content_source, $prompt, $title_option, $custom_title, $featured_image_option, $schedule_time, $tag_option, $manual_tags, $tag_prompt_id, $include_content_for_tags) {
    update_progress("Fetching content based on selected source: " . $content_source);
    $content = get_content_based_on_source($wpdb, $scraped_content_table, $content_source);

    if (empty($prompt) || empty($content)) {
        update_progress("Error: Prompt or content is empty.");
    } else {
        update_progress("Content fetched successfully. Sending to OpenAI API for processing...");
        $openai_response = openai_interact_with_api($prompt, $content);
        update_progress("OpenAI processing complete.");

        update_progress("Generating post title...");
        $post_title = generate_post_title($wpdb, $prompts_table, $title_option, $custom_title, $content, 0);

        update_progress("Scheduling post...");
        $post_id = schedule_post($post_title, $openai_response, $schedule_time);

        if ($post_id) {
            update_progress("Post scheduled successfully. Post ID: " . $post_id);
            update_progress("Handling featured image...");
            handle_featured_image($wpdb, $prompts_table, $featured_image_option, $post_id, $post_title, $content);

            // Handle tags
            if ($tag_option === 'manual') {
                wp_set_post_tags($post_id, explode(',', $manual_tags));
            } else {
                // Generate tags using AI
                $tag_prompt = $wpdb->get_var($wpdb->prepare("SELECT text_content FROM $prompts_table WHERE id = %d", $tag_prompt_id));
                if ($include_content_for_tags) {
                    $tag_prompt .= "\n\nContent:\n" . $openai_response;
                }
                $generated_tags = openai_interact_with_api($tag_prompt, $openai_response);
                wp_set_post_tags($post_id, explode(',', $generated_tags));
            }
        } else {
            update_progress("Error scheduling content.");
        }
    }
}

function get_content_based_on_source($wpdb, $scraped_content_table, $content_source) {
    if ($content_source === 'manual') {
        return isset($_POST['content']) ? sanitize_textarea_field($_POST['content']) : '';
    } elseif ($content_source === 'scraped') {
        $scraped_content_id = isset($_POST['scraped_content_id']) ? intval($_POST['scraped_content_id']) : 0;
        return $wpdb->get_var($wpdb->prepare("SELECT content FROM $scraped_content_table WHERE id = %d", $scraped_content_id));
    }
    return '';
}

function generate_post_title($wpdb, $prompts_table, $title_option, $custom_title, $content, $count) {
    if ($title_option === 'generate') {
        // Assuming 'update_progress' is handled elsewhere or can be omitted
        $title_prompt_id = isset($_POST['title_prompt_id']) ? intval($_POST['title_prompt_id']) : 0;
        $title_prompt_text = $wpdb->get_var($wpdb->prepare("SELECT text_content FROM $prompts_table WHERE id = %d", $title_prompt_id));
        $generated_title = openai_interact_with_api($title_prompt_text, $content);
        return wp_strip_all_tags($generated_title);
    } elseif ($title_option === 'custom') {
        $title = $custom_title . ' ' . ($count + 1);
        return sanitize_text_field($title);
    }
    return sanitize_text_field($custom_title);
}

function schedule_post($post_title, $post_content, $schedule_time) {
    $post_id = wp_insert_post(array(
        'post_title'    => $post_title,
        'post_content'  => $post_content,
        'post_status'   => 'future',
        'post_author'   => get_current_user_id(),
        'post_type'     => 'post',
        'post_date'     => $schedule_time,
    ));

    if (is_wp_error($post_id)) {
        error_log('Failed to insert post: ' . $post_id->get_error_message());
        return false;
    }

    return $post_id;
}

function handle_featured_image($wpdb, $prompts_table, $featured_image_option, $post_id, $post_title, $content = '') {
    
    if ($featured_image_option === 'auto') {
        $image_prompt_id = intval($_POST['image_prompt_id']);
        $image_prompt_text = $wpdb->get_var($wpdb->prepare("SELECT text_content FROM $prompts_table WHERE id = %d", $image_prompt_id));
        
        // Add this new code to handle include_content_text
        $include_content_text = isset($_POST['include_content_text']) ? true : false;
        if ($include_content_text && !empty($content)) {
            $image_prompt_text .= ' ' . substr($content, 0, 500); // Limit to first 500 characters
        }
        
        $image_url = openai_generate_image($image_prompt_text . ' ' . $post_title);
        
        if ($image_url && strpos($image_url, "Error:") !== 0) {
            $custom_filename = sanitize_title($post_title);
            handle_auto_image_upload($post_id, $image_url, $custom_filename);
        } else {
        }
    } elseif ($featured_image_option === 'manual' && isset($_FILES['featured_image'])) {
        handle_manual_image_upload($post_id);
    }
}

function store_scraped_content($wpdb, $scraped_content_table, $url, $content) {
    $wpdb->insert(
        $scraped_content_table,
        array(
            'url' => $url,
            'content' => $content,
            'date_scraped' => current_time('mysql')
        ),
        array('%s', '%s', '%s')
    );
}

function handle_bulk_delete() {
    if (!isset($_POST['post']) || !is_array($_POST['post'])) {
        echo '<div class="error"><p>No posts selected for deletion.</p></div>';
        return;
    }

    $deleted = 0;
    foreach ($_POST['post'] as $post_id) {
        if (wp_delete_post(intval($post_id), true)) {
            $deleted++;
        }
    }
    echo '<div class="updated"><p>' . $deleted . ' post(s) permanently deleted.</p></div>';
}


// Add a helper function to update progress
function update_progress($message) {
    $escaped_message = esc_js($message);
    echo "<script>
        var progressDiv = document.getElementById('progress-updates');
        progressDiv.innerHTML += '<p>' + '{$escaped_message}' + '</p>';
        progressDiv.scrollTop = progressDiv.scrollHeight;
    </script>";
    ob_flush();
    flush();
}

// Add this to the existing PHP file, preferably at the end
function enqueue_rss_processing_scripts() {
    wp_enqueue_script('jquery-ui-dialog');
    wp_enqueue_script('jquery-ui-progressbar');
    wp_enqueue_style('wp-jquery-ui-dialog');
    
    // Localize the script with new data
    wp_localize_script('jquery', 'openai_ajax_object', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('openai_ajax_nonce'),
    ));
}
add_action('admin_enqueue_scripts', 'enqueue_rss_processing_scripts');

function add_rss_processing_script() {
    ?>
    <script>
function processRSSFeed(data) {
    // **Add the action parameter and nonce**
    data.action = 'process_rss_feed';
    data.security = openai_ajax_object.nonce;

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
add_action('admin_footer', 'add_rss_processing_script');



