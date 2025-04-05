<?php
require_once(ABSPATH . WPINC . '/feed.php');

function openai_link_content_init() {
    global $wpdb;
    $scraped_content_table = $wpdb->prefix . 'openai_scraped_content';
    $rss_feeds_table = $wpdb->prefix . 'openai_rss_feeds';

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['scrape_rss_feed'])) {
            handle_scrape_rss_feed($wpdb, $scraped_content_table, $rss_feeds_table);
        } elseif (isset($_POST['add_scraped_content'])) {
            handle_add_scraped_content($wpdb, $scraped_content_table);
        } elseif (isset($_POST['add_rss_feed'])) {
            handle_add_rss_feed($wpdb, $rss_feeds_table);
        }
    }

    // Handle RSS feed deletion
    if (isset($_GET['action']) && $_GET['action'] === 'delete_rss_feed' && isset($_GET['feed_id'])) {
        $feed_id = intval($_GET['feed_id']);
        handle_delete_rss_feed($wpdb, $rss_feeds_table, $feed_id);
    }

    // Handle bulk actions for scraped content
    if (isset($_POST['action']) && $_POST['action'] != '-1') {
        $action = $_POST['action'];
        $selected_contents = isset($_POST['content']) ? $_POST['content'] : array();

        if ($action == 'bulk_delete' && !empty($selected_contents)) {
            $deleted = 0;
            foreach ($selected_contents as $content_id) {
                $wpdb->delete($scraped_content_table, array('id' => $content_id), array('%d'));
                $deleted++;
            }
            echo '<div class="updated"><p>' . $deleted . ' scraped content(s) permanently deleted.</p></div>';
        }
    }

    // Handle bulk actions for RSS feeds
    if (isset($_POST['action']) && $_POST['action'] == 'bulk_delete_rss' && isset($_POST['rss_feed'])) {
        $deleted = 0;
        foreach ($_POST['rss_feed'] as $feed_id) {
            $wpdb->delete($rss_feeds_table, array('id' => $feed_id), array('%d'));
            $deleted++;
        }
        echo '<div class="updated"><p>' . $deleted . ' RSS feed(s) permanently deleted.</p></div>';
    }

    // Create tables if they don't exist
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS $scraped_content_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        url text NOT NULL,
        content longtext NOT NULL,
        date_scraped datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    $sql = "CREATE TABLE IF NOT EXISTS $rss_feeds_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        title varchar(255) NOT NULL,
        url varchar(255) NOT NULL,
        date_added datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta($sql);

    // Handle delete action
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
        $id = intval($_GET['id']);
        $wpdb->delete($scraped_content_table, array('id' => $id), array('%d'));
        echo '<div class="updated"><p>Scraped content deleted successfully.</p></div>';
    }

    // Display forms and list of scraped content
    ?>
    <div class="wrap">
        <h1>Link Content</h1>
        
        <?php display_scrape_rss_feed_form($wpdb, $rss_feeds_table); ?>
        
        <?php display_add_scraped_content_form(); ?>
        
        <?php display_add_rss_feed_form(); ?>

        <?php display_scraped_contents($wpdb, $scraped_content_table); ?>

        <?php display_rss_feeds($wpdb, $rss_feeds_table); ?>
    </div>

    <!-- View Content Modal -->
    <div id="view-content-modal" style="display:none; position: fixed; z-index: 1; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4);">
        <div style="background-color: #fefefe; margin: 15% auto; padding: 20px; border: 1px solid #888; width: 80%;">
            <h2>Scraped Content</h2>
            <div id="content-display"></div>
            <button onclick="document.getElementById('view-content-modal').style.display='none';" class="button">Close</button>
        </div>
    </div>

    <script>
    function viewContent(id) {
        var xhr = new XMLHttpRequest();
        xhr.onreadystatechange = function() {
            if (this.readyState == 4 && this.status == 200) {
                document.getElementById('content-display').innerHTML = this.responseText;
                document.getElementById('view-content-modal').style.display = 'block';
            }
        };
        xhr.open('GET', '<?php echo admin_url('admin-ajax.php'); ?>?action=view_scraped_content&id=' + id, true);
        xhr.send();
    }
    </script>
    <?php
}

function handle_scrape_rss_feed($wpdb, $scraped_content_table, $rss_feeds_table) {
    $feed_id = intval($_POST['feed_id']);
    $num_posts = intval($_POST['num_posts']);
    $feed = $wpdb->get_row($wpdb->prepare("SELECT * FROM $rss_feeds_table WHERE id = %d", $feed_id));
    
    if ($feed) {
        $rss = fetch_feed($feed->url);
        if (!is_wp_error($rss)) {
            $maxitems = $rss->get_item_quantity($num_posts);
            $rss_items = $rss->get_items(0, $maxitems);
            
            $scraped_count = 0;
            foreach ($rss_items as $item) {
                $title = $item->get_title();
                $content = $item->get_content();
                $url = $item->get_permalink();
                
                // Check if content already exists
                $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $scraped_content_table WHERE url = %s", $url));
                
                if (!$exists) {
                    $wpdb->insert(
                        $scraped_content_table,
                        array(
                            'url' => $url,
                            'content' => $content,
                            'date_scraped' => current_time('mysql')
                        ),
                        array('%s', '%s', '%s')
                    );
                    $scraped_count++;
                }
            }
            echo '<div class="updated"><p>' . $scraped_count . ' new items scraped from RSS feed.</p></div>';
        } else {
            echo '<div class="error"><p>Error fetching RSS feed: ' . $rss->get_error_message() . '</p></div>';
        }
    } else {
        echo '<div class="error"><p>RSS feed not found.</p></div>';
    }
}

function handle_add_scraped_content($wpdb, $scraped_content_table) {
    $url = esc_url_raw($_POST['url']);
    $scrapedContent = scrapeWebContent($url);
    
    if ($scrapedContent !== false && strpos($scrapedContent, "Error:") !== 0) {
        $wpdb->insert(
            $scraped_content_table,
            array(
                'url' => $url,
                'content' => $scrapedContent,
                'date_scraped' => current_time('mysql')
            ),
            array('%s', '%s', '%s')
        );
        echo '<div class="updated"><p>Content scraped and saved successfully.</p></div>';
    } else {
        echo '<div class="error"><p>Error scraping content: ' . $scrapedContent . '</p></div>';
    }
}

function handle_add_rss_feed($wpdb, $rss_feeds_table) {
    $feed_title = sanitize_text_field($_POST['feed_title']);
    $feed_url = esc_url_raw($_POST['feed_url']);

    if (!empty($feed_title) && !empty($feed_url)) {
        $wpdb->insert(
            $rss_feeds_table,
            array(
                'title' => $feed_title,
                'url' => $feed_url,
            ),
            array('%s', '%s')
        );
        echo '<div class="updated"><p>RSS feed added successfully.</p></div>';
    } else {
        echo '<div class="error"><p>Please enter both a title and URL for the RSS feed.</p></div>';
    }
}

function handle_delete_rss_feed($wpdb, $rss_feeds_table, $feed_id) {
    $result = $wpdb->delete(
        $rss_feeds_table,
        array('id' => $feed_id),
        array('%d')
    );

    if ($result !== false) {
        echo '<div class="updated"><p>RSS feed deleted successfully.</p></div>';
    } else {
        echo '<div class="error"><p>Error deleting RSS feed.</p></div>';
    }
}

function display_scrape_rss_feed_form($wpdb, $rss_feeds_table) {
    ?>
    <h2>Scrape RSS Feed</h2>
    <form method="post" action="">
        <table class="form-table">
            <tr>
                <th scope="row"><label for="feed_id">Select RSS Feed</label></th>
                <td>
                    <select name="feed_id" id="feed_id" required>
                        <option value="">Select a feed</option>
                        <?php
                        $rss_feeds = $wpdb->get_results("SELECT id, title FROM $rss_feeds_table ORDER BY title ASC");
                        foreach ($rss_feeds as $feed) {
                            echo '<option value="' . esc_attr($feed->id) . '">' . esc_html($feed->title) . '</option>';
                        }
                        ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="num_posts">Number of Posts to Scrape</label></th>
                <td>
                    <input type="number" name="num_posts" id="num_posts" min="1" max="50" value="5" required>
                </td>
            </tr>
        </table>
        <p class="submit">
            <input type="submit" name="scrape_rss_feed" class="button button-primary" value="Scrape RSS Feed">
        </p>
    </form>
    <?php
}

function display_add_scraped_content_form() {
    ?>
    <h2>Add Scraped Content</h2>
    <form method="post">
        <input type="url" name="url" placeholder="Enter URL to scrape" required style="width: 300px;">
        <input type="submit" name="add_scraped_content" class="button button-primary" value="Scrape Content">
    </form>
    <?php
}

function display_add_rss_feed_form() {
    ?>
    <h2>Add RSS Feed</h2>
    <form method="post" action="">
        <table class="form-table">
            <tr>
                <th scope="row"><label for="feed_title">Feed Title</label></th>
                <td><input type="text" name="feed_title" id="feed_title" class="regular-text" required></td>
            </tr>
            <tr>
                <th scope="row"><label for="feed_url">Feed URL</label></th>
                <td><input type="url" name="feed_url" id="feed_url" class="regular-text" required></td>
            </tr>
        </table>
        <p class="submit">
            <input type="submit" name="add_rss_feed" class="button button-primary" value="Add RSS Feed">
        </p>
    </form>
    <?php
}

function display_scraped_contents($wpdb, $scraped_content_table) {
    ?>
    <h2>Scraped Contents</h2>
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
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <td id="cb" class="manage-column column-cb check-column">
                        <label class="screen-reader-text" for="cb-select-all-1">Select All</label>
                        <input id="cb-select-all-1" type="checkbox">
                    </td>
                    <th scope="col" class="manage-column column-url">URL</th>
                    <th scope="col" class="manage-column column-date">Date Scraped</th>
                    <th scope="col" class="manage-column column-actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $scraped_contents = $wpdb->get_results("SELECT * FROM $scraped_content_table ORDER BY date_scraped DESC");
                if ($scraped_contents) {
                    foreach ($scraped_contents as $content) {
                        echo '<tr>';
                        echo '<th scope="row" class="check-column">';
                        echo '<label class="screen-reader-text" for="cb-select-' . $content->id . '">Select scraped content</label>';
                        echo '<input id="cb-select-' . $content->id . '" type="checkbox" name="content[]" value="' . $content->id . '">';
                        echo '</th>';
                        echo '<td class="url column-url">' . esc_url($content->url) . '</td>';
                        echo '<td class="date column-date">' . esc_html($content->date_scraped) . '</td>';
                        echo '<td class="actions column-actions">';
                        echo '<a href="#" onclick="viewContent(' . $content->id . '); return false;" class="button button-secondary">View</a> ';
                        echo '<a href="' . admin_url('admin.php?page=myplugin-link-content&action=delete&id=' . $content->id) . '" class="button button-secondary" onclick="return confirm(\'Are you sure you want to delete this scraped content?\')">Delete</a>';
                        echo '</td>';
                        echo '</tr>';
                    }
                } else {
                    echo '<tr><td colspan="4">No scraped content found.</td></tr>';
                }
                ?>
            </tbody>
            <tfoot>
                <tr>
                    <td class="manage-column column-cb check-column">
                        <label class="screen-reader-text" for="cb-select-all-2">Select All</label>
                        <input id="cb-select-all-2" type="checkbox">
                    </td>
                    <th scope="col" class="manage-column column-url">URL</th>
                    <th scope="col" class="manage-column column-date">Date Scraped</th>
                    <th scope="col" class="manage-column column-actions">Actions</th>
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
    <?php
}

function display_rss_feeds($wpdb, $rss_feeds_table) {
    $rss_feeds = $wpdb->get_results("SELECT * FROM $rss_feeds_table ORDER BY id DESC");
    ?>
    <h2>RSS Feeds</h2>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Title</th>
                <th>URL</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rss_feeds as $feed) : ?>
                <tr>
                    <td><?php echo esc_html($feed->title); ?></td>
                    <td><?php echo esc_url($feed->url); ?></td>
                    <td>
                        <a href="<?php echo admin_url('admin.php?page=myplugin-link-content&action=delete_rss_feed&feed_id=' . $feed->id); ?>" 
                           onclick="return confirm('Are you sure you want to delete this RSS feed?');"
                           class="button button-small">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

// Add AJAX action for viewing content
add_action('wp_ajax_view_scraped_content', 'view_scraped_content');
function view_scraped_content() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'openai_scraped_content';
    $id = intval($_GET['id']);
    $content = $wpdb->get_var($wpdb->prepare("SELECT content FROM $table_name WHERE id = %d", $id));
    echo nl2br(esc_html($content));
    wp_die();
}

