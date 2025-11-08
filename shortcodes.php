<?php
namespace hws_base_tools;

/**
 * Shortcode callback
 *
 * @return string Current year (e.g. 2025)
 */
function display_year_shortcode() {
    return date('Y');
}
