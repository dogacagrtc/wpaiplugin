<?php






function openai_prompt_options_validate($input) {
    $newinput = array();
    $newinput['prompt'] = trim($input['prompt']);
    return $newinput;
}

function openai_prompts_section_text() {
    echo '<p>Enter your Prompt settings here.</p>';
}

function openai_setting_string() {
    $options = get_option('openai_main_options');
    echo "<input id='openai_api_key' name='openai_main_options[api_key]' size='40' type='text' value='{$options['api_key']}' />";
}







function openai_prompts_init() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'openai_texts';

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $prompt_title = sanitize_text_field($_POST['prompt_title']);
        $prompt_text = sanitize_textarea_field($_POST['prompt_text']);
        $prompt_category = sanitize_text_field($_POST['prompt_category']);  // Change this line

        if (isset($_POST['new_prompt'])) {
            $wpdb->insert($table_name, array('title' => $prompt_title, 'text_content' => $prompt_text, 'prompt_category' => $prompt_category), array('%s', '%s', '%s'));
            echo '<div class="updated"><p>New prompt added successfully.</p></div>';
        } elseif (isset($_POST['update_prompt'])) {
            $prompt_id = intval($_POST['prompt_id']);
            $wpdb->update($table_name, array('title' => $prompt_title, 'text_content' => $prompt_text, 'prompt_category' => $prompt_category), array('id' => $prompt_id), array('%s', '%s', '%s'), array('%d'));
            echo '<div class="updated"><p>Prompt updated successfully.</p></div>';
        } elseif (isset($_POST['delete_prompt'])) {
            $prompt_id = intval($_POST['prompt_id']);
            $wpdb->delete($table_name, array('id' => $prompt_id), array('%d'));
            echo '<div class="updated"><p>Prompt deleted successfully.</p></div>';
        }
    }

    // Fetch all prompts
    $prompts = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id DESC");

    // Display the prompts management interface
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Manage Prompts</h1>
        <a href="#" class="page-title-action" onclick="document.getElementById('new-prompt-form').style.display='block'; return false;">Add New</a>

        <form id="new-prompt-form" method="post" style="display:none; margin-top: 20px;">
            <input type="text" name="prompt_title" placeholder="Enter prompt title" required style="width: 100%; margin-bottom: 10px;">
            <textarea name="prompt_text" rows="3" cols="100" placeholder="Enter prompt text" required></textarea>
            <select name="prompt_category" required>  <!-- Change this line -->
                <option value="content">Content</option>
                <option value="image">Image</option>
                <option value="title">Title</option>
                <option value="tag">Tag</option>  <!-- Add this line -->
            </select>
            <input type="submit" name="new_prompt" class="button button-primary" value="Add New Prompt">
        </form>

        <hr class="wp-header-end">

        <?php if (empty($prompts)) : ?>
            <p>No prompts found.</p>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped posts">
                <thead>
                    <tr>
                        <th scope="col" class="manage-column column-title column-primary">Title</th>
                        <th scope="col" class="manage-column">Prompt Text</th>
                        <th scope="col" class="manage-column">Prompt Category</th> <!-- Change this line -->
                        <th scope="col" class="manage-column column-date">Actions</th>
                    </tr>
                </thead>
                <tbody id="the-list">
                    <?php foreach ($prompts as $prompt) : ?>
                        <tr id="post-<?php echo $prompt->id; ?>">
                            <td class="title column-title has-row-actions column-primary">
                                <strong><?php echo esc_html($prompt->title); ?></strong>
                                <div class="row-actions">
                                    <span class="edit"><a href="#" onclick="editPrompt(<?php echo $prompt->id; ?>, '<?php echo esc_js($prompt->title); ?>', '<?php echo esc_js($prompt->text_content); ?>', '<?php echo esc_js($prompt->prompt_category); ?>'); return false;">Edit</a> | </span>
                                    <span class="trash"><a href="#" onclick="deletePrompt(<?php echo $prompt->id; ?>); return false;" class="submitdelete">Delete</a></span>
                                </div>
                            </td>
                            <td><?php echo esc_html(substr($prompt->text_content, 0, 100)) . (strlen($prompt->text_content) > 100 ? '...' : ''); ?></td>
                            <td><?php echo esc_html($prompt->prompt_category); ?></td> <!-- Change this line -->
                            <td class="date column-date">
                                <a href="#" onclick="editPrompt(<?php echo $prompt->id; ?>, '<?php echo esc_js($prompt->title); ?>', '<?php echo esc_js($prompt->text_content); ?>', '<?php echo esc_js($prompt->prompt_category); ?>'); return false;" class="button button-secondary">Edit</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Edit Prompt Modal -->
    <div id="edit-prompt-modal" style="display:none; position: fixed; z-index: 1; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4);">
        <div style="background-color: #fefefe; margin: 15% auto; padding: 20px; border: 1px solid #888; width: 80%;">
            <h2>Edit Prompt</h2>
            <form id="edit-prompt-form" method="post">
                <input type="hidden" id="edit-prompt-id" name="prompt_id">
                <input type="text" id="edit-prompt-title" name="prompt_title" placeholder="Enter prompt title" required style="width: 100%; margin-bottom: 10px;">
                <textarea id="edit-prompt-text" name="prompt_text" rows="5" cols="100" required></textarea>
                <select id="edit-prompt_category" name="prompt_category" required>  <!-- Change this line -->
                    <option value="content">Content</option>
                    <option value="image">Image</option>
                    <option value="title">Title</option>
                    <option value="tag">Tag</option>  <!-- Add this line -->
                </select>
                <br><br>
                <input type="submit" name="update_prompt" class="button button-primary" value="Update Prompt">
                <button type="button" onclick="document.getElementById('edit-prompt-modal').style.display='none';" class="button">Cancel</button>
            </form>
        </div>
    </div>

    <form id="delete-prompt-form" method="post" style="display:none;">
        <input type="hidden" id="delete-prompt-id" name="prompt_id">
        <input type="hidden" name="delete_prompt" value="1">
    </form>

    <script>
    function editPrompt(id, title, text, prompt_category) {  // Change this line
        document.getElementById('edit-prompt-id').value = id;
        document.getElementById('edit-prompt-title').value = title;
        document.getElementById('edit-prompt-text').value = text;
        document.getElementById('edit-prompt_category').value = prompt_category;  // Change this line
        document.getElementById('edit-prompt-modal').style.display = 'block';
    }

    function deletePrompt(id) {
        if (confirm('Are you sure you want to delete this prompt?')) {
            document.getElementById('delete-prompt-id').value = id;
            document.getElementById('delete-prompt-form').submit();
        }
    }
    </script>
    <?php
}










?>