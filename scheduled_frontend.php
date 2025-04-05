<?php
function display_scheduling_form($wpdb, $prompts_table, $rss_feeds_table, $scraped_content_table) {
    ?>
    <div class="wrap">
        <h1>Schedule Content</h1>
        <form method="post" enctype="multipart/form-data">
            <div id="poststuff">
                <!-- Title Options Box -->
                <div class="postbox">
                    <h2 class="hndle"><span>Title Options</span></h2>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th><label>Title Option:</label></th>
                                <td>
                                    <label><input type="radio" name="title_option" value="custom" checked onchange="toggleTitleOption(this.value)"> Custom Title</label><br>
                                    <label><input type="radio" name="title_option" value="generate" onchange="toggleTitleOption(this.value)"> Generate Title</label>
                                </td>
                            </tr>
                            <tr id="custom_title_row">
                                <th><label for="custom_title">Custom Title:</label></th>
                                <td><input type="text" name="custom_title" id="custom_title" class="regular-text"></td>
                            </tr>
                            <tr id="title_prompt_row" style="display:none;">
                                <th><label for="title_prompt_id">Select Title Prompt:</label></th>
                                <td>
                                    <select name="title_prompt_id" id="title_prompt_id">
                                        <?php
                                        $prompts = $wpdb->get_results("SELECT id, title FROM $prompts_table WHERE prompt_category = 'title' ORDER BY id DESC");
                                        foreach ($prompts as $prompt) {
                                            echo '<option value="' . esc_attr($prompt->id) . '">' . esc_html($prompt->title) . '</option>';
                                        }
                                        ?>
                                    </select>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div id="post-body" class="metabox-holder columns-2">
                    <div id="post-body-content">
                        <!-- Content Details Box -->
                        <div class="postbox">
                            <h2 class="hndle"><span>Content Details</span></h2>
                            <div class="inside">
                                <table class="form-table">
                                    <tr>
                                        <th scope="row"><label for="prompt_id">Select Content Prompt:</label></th>
                                        <td>
                                            <select name="prompt_id" id="prompt_id" required>
                                                <?php
                                                $prompts = $wpdb->get_results("SELECT id, title FROM $prompts_table WHERE prompt_category = 'content' ORDER BY title ASC");
                                                foreach ($prompts as $prompt) {
                                                    echo '<option value="' . esc_attr($prompt->id) . '">' . esc_html($prompt->title) . '</option>';
                                                }
                                                ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label>Content Source:</label></th>
                                        <td>
                                            <label><input type="radio" name="content_source" value="manual" checked> Custom Content</label><br>
                                            <label><input type="radio" name="content_source" value="scraped"> Use Scraped Content</label><br>
                                            <label><input type="radio" name="content_source" value="rss_feed"> Use RSS Feed</label><br>
                                            <label><input type="radio" name="content_source" value="csv_file"> Use CSV File</label>
                                        </td>
                                    </tr>
                                    <tr id="manual_content_row">
                                        <th scope="row"><label for="content">Custom Content:</label></th>
                                        <td><textarea name="content" id="content" rows="5" cols="50"></textarea></td>
                                    </tr>
                                    <tr id="scraped_content_row" style="display:none;">
                                        <th scope="row"><label for="scraped_content_id">Select Scraped Content:</label></th>
                                        <td>
                                            <select name="scraped_content_id" id="scraped_content_id">
                                                <?php
                                                $scraped_contents = $wpdb->get_results("SELECT id, url FROM $scraped_content_table ORDER BY id DESC");
                                                foreach ($scraped_contents as $content) {
                                                    echo '<option value="' . esc_attr($content->id) . '">' . esc_html($content->url) . '</option>';
                                                }
                                                ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr id="rss_feed_row" style="display: none;">
                                        <th scope="row"><label for="rss_feed_id">RSS Feed:</label></th>
                                        <td>
                                            <select name="rss_feed_id" id="rss_feed_id">
                                                <?php
                                                $rss_feeds = $wpdb->get_results("SELECT id, title FROM $rss_feeds_table ORDER BY title ASC");
                                                foreach ($rss_feeds as $feed) {
                                                    echo '<option value="' . esc_attr($feed->id) . '">' . esc_html($feed->title) . '</option>';
                                                }
                                                ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr id="rss_post_count_row" style="display: none;">
                                        <th scope="row"><label for="rss_post_count">Number of Posts to Scrape:</label></th>
                                        <td><input type="number" name="rss_post_count" id="rss_post_count" min="1" max="50" value="5"></td>
                                    </tr>
                                    <tr id="rss_interval_row" style="display:none;">
                                        <th scope="row"><label for="rss_interval">Interval between posts:</label></th>
                                        <td>
                                            <select name="rss_interval" id="rss_interval">
                                                <option value="1">1 day</option>
                                                <option value="3">3 days</option>
                                                <option value="5">5 days</option>
                                                <option value="7">7 days</option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr id="csv_file_row" style="display:none;">
                                        <th scope="row"><label for="csv_file">Upload CSV File:</label></th>
                                        <td><input type="file" name="csv_file" id="csv_file" accept=".csv"></td>
                                    </tr>
                                    <tr id="csv_column_row" style="display:none;">
                                        <th scope="row"><label for="csv_column">CSV Column Name:</label></th>
                                        <td><input type="text" name="csv_column" id="csv_column" placeholder="Enter the column name containing the text"></td>
                                    </tr>
                                    <tr id="csv_batch_size_row" style="display:none;">
                                        <th scope="row"><label for="csv_batch_size">Batch Size:</label></th>
                                        <td><input type="number" name="csv_batch_size" id="csv_batch_size" min="1" max="50" value="10"></td>
                                    </tr>
                                    <tr>
                                        <th><label for="schedule_time">Schedule Time:</label></th>
                                        <td><input type="datetime-local" name="schedule_time" id="schedule_time" required></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div id="postbox-container-1" class="postbox-container">
                        <!-- Featured Image Box -->
                        <div class="postbox">
                            <h2 class="hndle"><span>Featured Image</span></h2>
                            <div class="inside">
                                <table class="form-table">
                                    <tr>
                                        <th><label>Featured Image Option:</label></th>
                                        <td>
                                            <label><input type="radio" name="featured_image_option" value="none" checked onchange="toggleImageOption(this.value)"> No Featured Image</label><br>
                                            <label><input type="radio" name="featured_image_option" value="manual" onchange="toggleImageOption(this.value)"> Manual Upload</label><br>
                                            <label><input type="radio" name="featured_image_option" value="auto" onchange="toggleImageOption(this.value)"> Auto-generate Image</label>
                                        </td>
                                    </tr>
                                    <tr id="manual_image_row" style="display:none;">
                                        <th><label for="featured_image">Upload Featured Image:</label></th>
                                        <td><input type="file" name="featured_image" id="featured_image" accept="image/*"></td>
                                    </tr>
                                    <tr id="auto_image_row" style="display:none;">
                                        <th><label for="image_prompt_id">Select Image Prompt:</label></th>
                                        <td>
                                            <select name="image_prompt_id" id="image_prompt_id">
                                                <?php
                                                $prompts = $wpdb->get_results("SELECT id, title FROM $prompts_table WHERE prompt_category = 'image' ORDER BY id DESC");
                                                foreach ($prompts as $prompt) {
                                                    echo '<option value="' . esc_attr($prompt->id) . '">' . esc_html($prompt->title) . '</option>';
                                                }
                                                ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><label for="include_content_text">Include Content Text in Image Prompt:</label></th>
                                        <td>
                                            <label><input type="checkbox" name="include_content_text" id="include_content_text" value="1"> Include the content text in the image prompt</label>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><label for="custom_image_name">Custom Image Filename:</label></th>
                                        <td><input type="text" name="custom_image_name" id="custom_image_name" class="regular-text" placeholder="Enter custom filename (optional)"></td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <!-- Tags Box -->
                        <div class="postbox">
                            <h2 class="hndle"><span>Tags</span></h2>
                            <div class="inside">
                                <table class="form-table">
                                    <tr>
                                        <th><label>Tag Option:</label></th>
                                        <td>
                                            <label><input type="radio" name="tag_option" value="manual" checked onchange="toggleTagOption(this.value)"> Manual Tags</label><br>
                                            <label><input type="radio" name="tag_option" value="generate" onchange="toggleTagOption(this.value)"> Generate Tags</label>
                                        </td>
                                    </tr>
                                    <tr id="manual_tags_row">
                                        <th><label for="manual_tags">Manual Tags:</label></th>
                                        <td><input type="text" name="manual_tags" id="manual_tags" class="regular-text" placeholder="Enter tags separated by commas"></td>
                                    </tr>
                                    <tr id="tag_prompt_row" style="display:none;">
                                        <th><label for="tag_prompt_id">Select Tag Prompt:</label></th>
                                        <td>
                                            <select name="tag_prompt_id" id="tag_prompt_id">
                                                <?php
                                                $prompts = $wpdb->get_results("SELECT id, title FROM $prompts_table WHERE prompt_category = 'tag' ORDER BY id DESC");
                                                foreach ($prompts as $prompt) {
                                                    echo '<option value="' . esc_attr($prompt->id) . '">' . esc_html($prompt->title) . '</option>';
                                                }
                                                ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr id="include_content_for_tags_row" style="display:none;">
                                        <th><label for="include_content_for_tags">Include Content in Tag Prompt:</label></th>
                                        <td>
                                            <input type="checkbox" name="include_content_for_tags" id="include_content_for_tags" value="1">
                                            <label for="include_content_for_tags">Include the content text in the tag generation prompt</label>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <!-- Automation Box -->
                        <div class="postbox">
                            <h2 class="hndle"><span>Automation</span></h2>
                            <div class="inside">
                                <table class="form-table">
                                    <tr>
                                        <th><label for="automation_minutes">Automation Interval (minutes):</label></th>
                                        <td>
                                            <input type="number" name="automation_minutes" id="automation_minutes" min="1" value="60">
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <p class="submit">
                <input type="submit" name="schedule_content" class="button button-primary" value="Schedule Content">
                <input type="button" id="start_automation" class="button button-secondary" value="Start Automation">
            </p>
        </form>

        <h2>Scheduled Posts</h2>
        <form method="post">
            <div class="tablenav top">
                <div class="alignleft actions bulkactions">
                    <label for="bulk-action-selector-top" class="screen-reader-text">Select bulk action</label>
                    <select name="action" id="bulk-action-selector-top">
                        <option value="-1">Bulk Actions</option>
                        <option value="bulk_delete">Delete</option>
                    </select>
                    <input type="submit" id="doaction" class="button action" value="Apply">
                </div>
            </div>
            <table class="wp-list-table widefat fixed striped posts">
                <thead>
                    <tr>
                        <td id="cb" class="manage-column column-cb check-column">
                            <label class="screen-reader-text" for="cb-select-all-1">Select All</label>
                            <input id="cb-select-all-1" type="checkbox">
                        </td>
                        <th scope="col" class="manage-column column-title column-primary">Title</th>
                        <th scope="col" class="manage-column column-date">Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $scheduled_posts = get_posts(array(
                        'post_status' => 'future',
                        'posts_per_page' => -1,
                        'orderby' => 'date',
                        'order' => 'ASC',
                    ));

                    if ($scheduled_posts) {
                        foreach ($scheduled_posts as $post) {
                            echo '<tr>';
                            echo '<th scope="row" class="check-column">';
                            echo '<label class="screen-reader-text" for="cb-select-' . $post->ID . '">Select ' . esc_html($post->post_title) . '</label>';
                            echo '<input id="cb-select-' . $post->ID . '" type="checkbox" name="post[]" value="' . $post->ID . '">';
                            echo '</th>';
                            echo '<td class="title column-title has-row-actions column-primary">';
                            echo '<strong><a class="row-title" href="' . get_edit_post_link($post->ID) . '">' . esc_html($post->post_title) . '</a></strong>';
                            echo '<div class="row-actions">';
                            echo '<span class="edit"><a href="' . get_edit_post_link($post->ID) . '">Edit</a> | </span>';
                            echo '<span class="trash"><a href="' . get_delete_post_link($post->ID) . '" class="submitdelete">Trash</a></span>';
                            echo '</div>';
                            echo '</td>';
                            echo '<td class="date column-date">' . get_the_date('Y/m/d g:i a', $post) . '</td>';
                            echo '</tr>';
                        }
                    } else {
                        echo '<tr><td colspan="3">No scheduled posts found.</td></tr>';
                    }
                    ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td class="manage-column column-cb check-column">
                            <label class="screen-reader-text" for="cb-select-all-2">Select All</label>
                            <input id="cb-select-all-2" type="checkbox">
                        </td>
                        <th scope="col" class="manage-column column-title column-primary">Title</th>
                        <th scope="col" class="manage-column column-date">Date</th>
                    </tr>
                </tfoot>
            </table>
            <div class="tablenav bottom">
                <div class="alignleft actions bulkactions">
                    <label for="bulk-action-selector-bottom" class="screen-reader-text">Select bulk action</label>
                    <select name="action2" id="bulk-action-selector-bottom">
                        <option value="-1">Bulk Actions</option>
                        <option value="bulk_delete">Delete</option>
                    </select>
                    <input type="submit" id="doaction2" class="button action" value="Apply">
                </div>
            </div>
        </form>

        <h2>Current Automations</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Automation ID</th>
                    <th>Interval (minutes)</th>
                    <th>Next Run</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="automations-list">
                <!-- Automations will be populated here via AJAX -->
            </tbody>
        </table>

        <style>
            #poststuff #post-body.columns-2 {
                margin-right: 320px;
            }
            #post-body-content {
                float: left;
                width: 100%;
            }
            #postbox-container-1 {
                float: right;
                margin-right: -320px;
                width: 300px;
            }
            .form-table th {
                width: 150px;
                padding: 15px 10px;
            }
            .form-table td {
                padding: 15px 10px;
            }
            .form-table input[type="text"], 
            .form-table select, 
            .form-table textarea {
                width: 95%;
            }
            #postbox-container-1 .form-table th {
                width: 100%;
                display: block;
            }
            #postbox-container-1 .form-table td {
                width: 100%;
                display: block;
                padding-left: 0;
            }
            #postbox-container-1 .form-table input[type="text"],
            #postbox-container-1 .form-table select,
            #postbox-container-1 .form-table input[type="number"] {
                width: 100%;
            }
            .submit {
                clear: both;
                padding-top: 20px;
            }
            .wp-list-table {
                margin-top: 20px;
            }
            #loading-message {
                background-color: #f1f1f1;
                border: 1px solid #ddd;
                padding: 20px;
                margin-top: 20px;
                border-radius: 5px;
            }
            #progress-updates {
                max-height: 200px;
                overflow-y: auto;
                border: 1px solid #ccc;
                padding: 10px;
                margin-top: 10px;
                background-color: #fff;
            }
        </style>

        <script>
        function toggleTitleOption(value) {
            document.getElementById('custom_title_row').style.display = value === 'custom' ? 'table-row' : 'none';
            document.getElementById('title_prompt_row').style.display = value === 'generate' ? 'table-row' : 'none';
        }

        function toggleImageOption(value) {
            document.getElementById('manual_image_row').style.display = value === 'manual' ? 'table-row' : 'none';
            document.getElementById('auto_image_row').style.display = value === 'auto' ? 'table-row' : 'none';
        }

        function toggleTagOption(value) {
            document.getElementById('manual_tags_row').style.display = value === 'manual' ? 'table-row' : 'none';
            document.getElementById('tag_prompt_row').style.display = value === 'generate' ? 'table-row' : 'none';
            document.getElementById('include_content_for_tags_row').style.display = value === 'generate' ? 'table-row' : 'none';
        }

        jQuery(document).ready(function($) {
            // Handle "Select All" checkbox
            $('.wp-list-table .check-column input[type="checkbox"]').change(function() {
                var isChecked = $(this).prop('checked');
                $('.wp-list-table tbody .check-column input[type="checkbox"]').prop('checked', isChecked);
            });

            // Update "Select All" checkbox when individual checkboxes change
            $('.wp-list-table tbody .check-column input[type="checkbox"]').change(function() {
                var allChecked = $('.wp-list-table tbody .check-column input[type="checkbox"]:checked').length === $('.wp-list-table tbody .check-column input[type="checkbox"]').length;
                $('.wp-list-table .check-column input[type="checkbox"]').prop('checked', allChecked);
            });

            // Confirmation for bulk delete
            $('.tablenav select').change(function() {
                if ($(this).val() == 'bulk_delete') {
                    return confirm('Are you sure you want to delete the selected posts? This action cannot be undone.');
                }
            });

            $('input[name="content_source"]').change(function() {
                var selectedSource = $(this).val();
                $('#manual_content_row, #scraped_content_row, #rss_feed_row, #rss_post_count_row, #rss_interval_row, #csv_file_row, #csv_column_row, #csv_batch_size_row').hide();
                if (selectedSource === 'manual') {
                    $('#manual_content_row').show();
                } else if (selectedSource === 'scraped') {
                    $('#scraped_content_row').show();
                } else if (selectedSource === 'rss_feed') {
                    $('#rss_feed_row, #rss_post_count_row, #rss_interval_row').show();
                } else if (selectedSource === 'csv_file') {
                    $('#csv_file_row, #csv_column_row, #csv_batch_size_row').show();
                }
    });

            $('#start_automation').click(function() {
                var automationMinutes = $('#automation_minutes').val();
                var formData = $('form').serializeArray();
                
                // Add additional fields that might not be captured by serializeArray()
                formData.push({name: 'action', value: 'start_automation'});
                formData.push({name: 'automation_minutes', value: automationMinutes});
                formData.push({name: 'include_content_text', value: $('#include_content_text').is(':checked') ? '1' : '0'});
                formData.push({name: 'include_content_for_tags', value: $('#include_content_for_tags').is(':checked') ? '1' : '0'});

                // Convert formData to an object
                var formDataObject = {};
                $.each(formData, function(i, field) {
                    formDataObject[field.name] = field.value;
                });

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formDataObject,
                    success: function(response) {
                        alert(response.message);
                        loadAutomations();
                    },
                    error: function() {
                        alert('Error starting automation');
                    }
                });
            });

            function loadAutomations() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'load_automations'
                    },
                    success: function(response) {
                        $('#automations-list').html(response);
                    },
                    error: function() {
                        alert('Error loading automations');
                    }
                });
            }

            loadAutomations();

            $(document).on('click', '.edit-automation', function() {
                var automationId = $(this).data('id');
                // Implement edit functionality
            });

            $(document).on('click', '.delete-automation', function() {
                var automationId = $(this).data('id');
                if (confirm('Are you sure you want to delete this automation?')) {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'delete_automation',
                            automation_id: automationId
                        },
                        success: function(response) {
                            alert(response.message);
                            loadAutomations();
                        },
                        error: function() {
                            alert('Error deleting automation');
                        }
                    });
                }
            });
        });
        </script>
    </div>
    <?php
}