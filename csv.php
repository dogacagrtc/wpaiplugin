<?php
function process_csv_file($file_path, $column_name, $batch_size, $prompt_id) {
    $row = 1;
    $processed = 0;
    $batch = [];
    
    if (($handle = fopen($file_path, "r")) !== FALSE) {
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            if ($row == 1) {
                // Find the index of the specified column
                $column_index = array_search($column_name, $data);
                if ($column_index === false) {
                    fclose($handle);
                    return "Error: Column '$column_name' not found in CSV file.";
                }
                $row++;
                continue;
            }
            
            $text = $data[$column_index];
            $batch[] = $text;
            
            if (count($batch) >= $batch_size) {
                process_batch($batch, $prompt_id);
                $processed += count($batch);
                $batch = [];
            }
            
            $row++;
        }
        
        // Process any remaining items
        if (!empty($batch)) {
            process_batch($batch, $prompt_id);
            $processed += count($batch);
        }
        
        fclose($handle);
    }
    
    return "Processed $processed items from the CSV file.";
}

function process_batch($batch, $prompt_id) {
    global $wpdb;
    $prompts_table = $wpdb->prefix . 'openai_texts';
    
    $prompt = $wpdb->get_var($wpdb->prepare("SELECT text_content FROM $prompts_table WHERE id = %d", $prompt_id));
    
    foreach ($batch as $text) {
        $generated_content = openai_interact_with_api($prompt, $text);
        
        if (!is_array($generated_content) && !empty($generated_content)) {
            $post_id = wp_insert_post(array(
                'post_title'    => wp_trim_words($generated_content, 10, '...'), // Generate a title from the content
                'post_content'  => $generated_content,
                'post_status'   => 'draft',
                'post_author'   => get_current_user_id(),
                'post_type'     => 'post',
            ));
            
            if (is_wp_error($post_id)) {
                error_log('Failed to create post from CSV: ' . $post_id->get_error_message());
            }
        } else {
            error_log('Failed to generate content for CSV item: ' . print_r($generated_content, true));
        }
    }
}
?>