<?php namespace hws_base_tools;


// Hook to modify allowed MIME types for file uploads
function snippet_enable_svg_uploads(){
add_filter('upload_mimes', __NAMESPACE__ . '\\add_file_types_to_uploads');
}

// Add SVG support to file uploads
if (!function_exists(__NAMESPACE__ . '\\add_file_types_to_uploads')) {
    /**
     * Adds support for SVG file uploads in WordPress by extending the allowed MIME types.
     *
     * @param array $file_types The existing array of allowed MIME types for file uploads.
     * @return array Modified array of MIME types to include SVG.
     */
    function add_file_types_to_uploads($file_types) {
        // Define the new file type (SVG)
        $new_filetypes = array(
            'svg' => 'image/svg+xml' // Add support for SVG files
        );

        // Merge the new file type with existing MIME types
        return array_merge($file_types, $new_filetypes);
    }
} else {
    write_log("⚠️ Warning: " . __NAMESPACE__ . "\\add_file_types_to_uploads function is already declared", true);
}
