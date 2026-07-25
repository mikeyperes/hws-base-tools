<?php

namespace hws_base_tools;

use HWS\BaseTools\ContentTypes\SharedContentTypes;

defined( 'ABSPATH' ) || exit;

function enable_cpt_testimonial(): bool {
    return SharedContentTypes::register_type( SharedContentTypes::TESTIMONIAL );
}
