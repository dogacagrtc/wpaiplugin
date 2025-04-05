<?php
require_once plugin_dir_path(__FILE__) . 'csv.php';

function anthropic_interact_with_api($prompt, $content) {
    $url = 'https://api.anthropic.com/v1/messages';

    // Your Anthropic API key
    $apiKey = 'THIS SSHOULD BE AN API KEY HERE'; 

    // Claude model
    $model = 'claude-3-sonnet-20240229';
    $maxTokens = 2048;
    $temperature = 0.6;    

    $messages = [
        [
            'role' => 'user',
            'content' => $prompt . "\n\n" . $content
        ]
    ];

    $data = [
        'model' => $model,
        'max_tokens' => $maxTokens,
        'temperature' => $temperature,
        'messages' => $messages
    ];

    $jsonData = json_encode($data);

    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $jsonData,
        CURLOPT_HTTPHEADER => [
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01',
            'content-type: application/json'
        ],
    ]);

    $response = curl_exec($curl);

    curl_close($curl);

    $responseData = json_decode($response, true);

    if (isset($responseData['content'][0]['text'])) {
        return $responseData['content'][0]['text'];
    } else {
        error_log('Anthropic API Error: ' . print_r($responseData, true));
        return 'Error: Unable to process the request. Check error logs for details.';
    }
}

function openai_interact_with_api($prompt, $content) {
    $api_key = get_option('openai_main_options')['api_key'];
    $full_prompt = 'Prompt:' . $prompt . 'Content : ' . $content;

    error_log("OpenAI API: Sending request with prompt: " . $full_prompt);

    $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
        ),
        'body' => json_encode(array(
            'model' => 'gpt-3.5-turbo',
            'messages' => array(
                array('role' => 'system', 'content' => $prompt),
                array('role' => 'user', 'content' => $content)
            ),
            'max_tokens' => 4000,
        )),
        'timeout' => 360,  // Increased timeout to 360 seconds (6 minutes)
    ));

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        error_log('OpenAI API Error: ' . $error_message);
        return 'Error: Unable to process the request. ' . $error_message;
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    error_log('OpenAI API Response: ' . print_r($body, true));

    if (isset($body['choices'][0]['message']['content'])) {
        return $body['choices'][0]['message']['content'];
    } else {
        if (isset($body['error'])) {
            $error_message = $body['error']['message'];
            error_log('OpenAI API Error: ' . $error_message);
            return 'Error: ' . $error_message;
        } else {
            error_log('OpenAI API Error: Unexpected response format');
            return 'Error: Unexpected response from OpenAI. Check error logs for details.';
        }
    }
}


function openai_handle_post() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'openai_texts';

    if (isset($_POST['new_text']) && !empty($_POST['text_content'])) {
        $text_content = sanitize_text_field($_POST['text_content']);
        $wpdb->insert(
            $table_name,
            ['text_content' => $text_content],
            ['%s']
        );
        echo '<div>Text added successfully!</div>';
    } elseif (isset($_POST['update_text']) && !empty($_POST['text_content']) && isset($_POST['text_id'])) {
        $text_content = sanitize_text_field($_POST['text_content']);
        $text_id = intval($_POST['text_id']);
        $wpdb->update(
            $table_name,
            ['text_content' => $text_content],
            ['id' => $text_id],
            ['%s'],
            ['%d']
        );
        echo '<div>Text updated successfully!</div>';
    } elseif (isset($_GET['delete'])) {
        $text_id = intval($_GET['delete']);
        $wpdb->delete($table_name, ['id' => $text_id], ['%d']);
        echo '<div>Text deleted successfully!</div>';
    }
}

// Function to generate images using OpenAI’s DALL-E
function generateImages($prompt, $title, $content) {
    // Replace ‘YOUR_OPENAI_API_KEY’ with your actual OpenAI API key
    $full_prompt = 'Create an image based on the following prompt: ' . $prompt . 'Content : ' . $title . ' : ' . $content;
    error_log("OpenAI Image API: Sending request with prompt: " . $full_prompt);
    $apiKey = get_option('openai_main_options')['api_key'];
    // Set the DALL-E API endpoint
    $apiEndpoint = 'https://api.openai.com/v1/images';
    // Prepare the data for the API request
    $data = [
    'prompt' => $full_prompt,
    'model' => 'image-alpha-001', // You may need to adjust the model name
    'n' => 1, // Number of images to generate
    ];
    // Initialize cURL session
    $ch = curl_init($apiEndpoint);
    // Set cURL options
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ]);
    // Execute the cURL request
    $response = curl_exec($ch);
    // Close cURL session
    curl_close($ch);
    // Decode the JSON response
    $responseData = json_decode($response, true);
    // Get the generated image URL
    $imageUrl = $responseData['data'][0]['url'];
    // Output the image or perform further processing

    if (isset($responseData['data'][0]['url'])) {
        return $responseData['data'][0]['url'];  // Return the image URL
    } else {
        if (isset($responseData['error'])) {
            $error_message = $responseData['error']['message'];
            error_log('OpenAI Image API Error: ' . $error_message);
            return 'Error: ' . $error_message;
        } else {
            error_log('OpenAI Image API Error: Unexpected response format');
            return 'Error: Unexpected response from OpenAI. Check error logs for details.';
        }
    }
}

function openai_generate_image($prompt, $title, $generated_tags) {
    $api_key = get_option('openai_main_options')['api_key'];
    $url = 'https://api.openai.com/v1/images/generations';
    $full_prompt =  $prompt . 'Tags : ' . $generated_tags;

    // Prepare the request body
    $body = json_encode([
        'model' => 'dall-e-3',
        'prompt' => $full_prompt,
        'n' => 1,
        'size' => '1024x1024',
        'response_format' => 'url'
    ]);

    error_log("OpenAI Image API: Sending request with prompt: " . $full_prompt);

    // Send the request to the OpenAI API
    $response = wp_remote_post($url, array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
        ),
        'body' => $body,
        'timeout' => 60,  // Set a reasonable timeout
    ));

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        error_log('OpenAI Image API Error: ' . $error_message);
        return 'Error: Unable to generate image. ' . $error_message;
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    error_log('OpenAI Image API Response: ' . print_r($body, true));

    if (isset($body['data'][0]['url'])) {
        return $body['data'][0]['url'];  // Return the image URL
    } else {
        if (isset($body['error'])) {
            $error_message = $body['error']['message'];
            error_log('OpenAI Image API Error: ' . $error_message);
            return 'Error: ' . $error_message;
        } else {
            error_log('OpenAI Image API Error: Unexpected response format');
            return 'Error: Unexpected response from OpenAI. Check error logs for details.';
        }
    }
}

function scrapeWebContent($url) {
    // Initialize cURL session
    $ch = curl_init();

    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');

    // Execute cURL session and get the content
    $content = curl_exec($ch);

    // Check for cURL errors
    if (curl_errno($ch)) {
        return "Error: " . curl_error($ch);
    }

    // Close cURL session
    curl_close($ch);

    // Detect the character encoding of the content
    $encoding = mb_detect_encoding($content, 'UTF-8, ISO-8859-1, ASCII', true);

    // Convert the content to UTF-8 if it's not already
    if ($encoding !== 'UTF-8') {
        $content = mb_convert_encoding($content, 'UTF-8', $encoding);
    }

    // Use DOMDocument to parse the HTML
    $dom = new DOMDocument('1.0', 'UTF-8');
    @$dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

    // Create a DOMXPath object
    $xpath = new DOMXPath($dom);

    // Find all paragraph elements
    $paragraphs = $xpath->query('//p');

    $scrapedContent = '';

    foreach ($paragraphs as $p) {
        $text = trim($p->textContent);
        if (strlen($text) > 50) {  // Only include paragraphs with substantial content
            $scrapedContent .= $text . "\n\n";
        }
    }

    // Clean up the content
    $scrapedContent = preg_replace('/\s+/', ' ', $scrapedContent);
    $scrapedContent = trim($scrapedContent);

    // Remove the specified content from the beginning and end
    $scrapedContent = preg_replace('/^To revisit this article, visit My Profile, then View saved stories\.\s*/', '', $scrapedContent);
    $scrapedContent = preg_replace('/Politics Lab: Get the newsletter and listen to the podcast.*$/s', '', $scrapedContent);

    // Normalize special characters
    $scrapedContent = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $scrapedContent);

    // Remove any remaining non-ASCII characters
    $scrapedContent = preg_replace('/[^\x20-\x7E]/','', $scrapedContent);

    return $scrapedContent ?: "No content found.";
}
function handle_manual_image_upload($post_id) {
    $upload_overrides = array('test_form' => false);
    $uploaded_file = wp_handle_upload($_FILES['featured_image'], $upload_overrides);

    if ($uploaded_file && !isset($uploaded_file['error'])) {
        $file_name_and_location = $uploaded_file['file'];
        $file_title_for_media_library = $_FILES['featured_image']['name'];

        $attachment = array(
            'post_mime_type' => $uploaded_file['type'],
            'post_title' => addslashes($file_title_for_media_library),
            'post_content' => '',
            'post_status' => 'inherit'
        );

        $attach_id = wp_insert_attachment($attachment, $file_name_and_location, $post_id);
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attach_id, $file_name_and_location);
        wp_update_attachment_metadata($attach_id, $attach_data);
        set_post_thumbnail($post_id, $attach_id);
    }
}

function handle_auto_image_upload($post_id, $image_url, $custom_filename) {
    // Download the image from the URL and set it as the featured image
    $upload_dir = wp_upload_dir();
    $image_data = file_get_contents($image_url);
    if ($image_data === false) {
        error_log('Failed to download image from URL: ' . $image_url);
        return;
    }

    // Use the custom filename provided by the user
    $filename = $custom_filename . '_' . time() . '.png';

    if (wp_mkdir_p($upload_dir['path'])) {
        $file = $upload_dir['path'] . '/' . $filename;
    } else {
        $file = $upload_dir['basedir'] . '/' . $filename;
    }

    $bytes_written = file_put_contents($file, $image_data);
    if ($bytes_written === false) {
        error_log('Failed to save image file');
        return;
    }

    $wp_filetype = wp_check_filetype($filename, null);
    $attachment = array(
        'post_mime_type' => $wp_filetype['type'],
        'post_title' => sanitize_file_name($filename),
        'post_content' => '',
        'post_status' => 'inherit'
    );

    $attach_id = wp_insert_attachment($attachment, $file, $post_id);
    if (is_wp_error($attach_id)) {
        error_log('Failed to create attachment: ' . $attach_id->get_error_message());
        return;
    }

    require_once(ABSPATH . 'wp-admin/includes/image.php');
    $attach_data = wp_generate_attachment_metadata($attach_id, $file);
    wp_update_attachment_metadata($attach_id, $attach_data);
    set_post_thumbnail($post_id, $attach_id);
}

function openai_interact_with_api_for_rss($prompt, $content, $type = 'content') {
    $api_key = get_option('openai_main_options')['api_key'];
    $full_prompt = 'Prompt: ' . $prompt . ' Content: ' . $content;

    error_log("OpenAI API (RSS): Sending request with prompt type: " . $type);

    // Corrected model name
    $model = ($type === 'title') ? 'gpt-4' : 'gpt-3.5-turbo';

    $max_tokens = ($type === 'title') ? 50 : 1000; // Adjust token limits based on whether it's a title or content

    $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
        ),
        'body' => json_encode(array(
            'model' => $model,
            'messages' => array(
                array('role' => 'system', 'content' => 'You are processing RSS feed content. Respond concisely and relevantly.'),
                array('role' => 'user', 'content' => $full_prompt)
            ),
            'max_tokens' => $max_tokens,
            'temperature' => 0.7,
        )),
        'timeout' => 30, // Reduced timeout for RSS processing
    ));

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        error_log('OpenAI API (RSS) Error: ' . $error_message);
        return array('error' => 'Unable to process the RSS request. ' . $error_message);
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    error_log('OpenAI API (RSS) Response: ' . print_r($body, true));

    if (isset($body['choices'][0]['message']['content'])) {
        $generated_content = trim($body['choices'][0]['message']['content']);
        error_log("Generated {$type}: " . $generated_content);
        return ($type === 'title') ? substr($generated_content, 0, 100) : $generated_content; // Limit title length
    } else {
        if (isset($body['error'])) {
            $error_message = $body['error']['message'];
            error_log('OpenAI API (RSS) Error: ' . $error_message);
            return array('error' => $error_message);
        } else {
            error_log('OpenAI API (RSS) Error: Unexpected response format');
            return array('error' => 'Unexpected response from OpenAI for RSS processing. Check error logs for details.');
        }
    }
}








?>