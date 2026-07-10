<?php

namespace hws_base_tools;

use HWS\BaseTools\FeatureCatalog\ShortcodeCatalog;

function display_shortcode_tests(): void {
    ShortcodeCatalog::render();
}
