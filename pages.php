<?php 

function openai_content_text() {
    echo '<p>Enter your Content settings here2.</p>';
}

function openai_content_validate($input) {
    $newinput = array();
    $newinput['content'] = trim($input['content']);
    return $newinput;
}
function openai_setting_content() {
    $options = get_option('openai_content_options');
    echo "<textarea id='openai_content' name='openai_content_options[content]' rows='5' cols='40'>{$options['content']}</textarea>";
}



function openai_init(){
    ?>
    <div class="wrap">
        <h2>Dashboard</h2>
        <form method="post" action="options.php">
            <?php
            settings_fields('openai-content-options');
            do_settings_sections('myplugin-content2');
            submit_button('Save Settings');
            ?>
        </form>
        <form method="post">
            <input type="hidden" name="action" value="send_to_openai">
            <input type="submit" value="Send to OpenAI" class="button button-primary">
        </form>
        <?php
        // Check for POST request to trigger API interaction
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'send_to_openai') {
            echo '<h3>Response from OpenAI:</h3>';
            echo '<pre>';
            echo openai_interact_with_api(); // Call the function to interact with OpenAI API
            echo '</pre>';
        }
        ?>
    </div>
    <?php
}






















?>