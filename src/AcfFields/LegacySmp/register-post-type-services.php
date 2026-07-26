<?php

namespace hws_base_tools;

use HWS\BaseTools\ContentTypes\SharedContentTypes;

defined( 'ABSPATH' ) || exit;

function enable_hws_cpt_services(): bool {
    return SharedContentTypes::register_type( SharedContentTypes::SERVICES );
}
