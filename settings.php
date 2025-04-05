<?php

function openai_settings_text() {
    echo '<p>Enter your API Key and Settings here.</p>';
}
function openai_options_validate($input) {
    $newinput = array();
    $newinput['api_key'] = trim($input['api_key']);
    return $newinput;
}

function openai_setting_prompt() {
    $options2 = get_option('openai_prompt_options');
    echo "<input id='openai_prompt' name='openai_prompt_options[prompt]' size='40' type='text' value='{$options2['prompt']}' />";
}





function settings_init(){
    ?>
    <div class="wrap">
        <h2>Settings</h2>
        <form method="post" action="options.php">
            <?php
            settings_fields('openai-main-options');
            do_settings_sections('openai-plugin');
            submit_button('Save Settings');
            ?>
        </form>
    </div>
    <?php
}

?>