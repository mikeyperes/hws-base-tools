<? 
hws_ct_activate_snippets();
function hws_ct_activate_snippets() {
   $settings_snippets = hws_ct_get_settings_snippets();

    if (!empty($settings_snippets)) {
        write_log('Global $settings_snippets variable detected. Processing snippets...');
    } else {
        write_log('Global $settings_snippets variable is empty or not detected.');
    }

    foreach ($settings_snippets as $snippet) {
        $snippet_id = $snippet['id'];
        $function_to_call = $snippet['function'];

        // Check if the snippet is enabled
        $is_enabled = get_option($snippet_id, false);

        // Log snippet information
        write_log("Processing snippet: {$snippet['name']} (ID: $snippet_id)");

        if ($is_enabled) {
            write_log("Snippet $snippet_id is enabled. Preparing to activate.");
            if (function_exists($function_to_call)) {
                // Call the function to activate the snippet
                call_user_func($function_to_call);
                write_log("Snippet $snippet_id activated by calling $function_to_call.");
            } else {
                write_log("Function $function_to_call does not exist for snippet $snippet_id.");
            }
        } else {
            write_log("Snippet $snippet_id is not enabled.");
        }
    }
}