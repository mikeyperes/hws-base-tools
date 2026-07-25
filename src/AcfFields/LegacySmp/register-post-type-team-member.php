<?php

namespace hws_base_tools;

use HWS\BaseTools\ContentTypes\SharedContentTypes;

defined( 'ABSPATH' ) || exit;

function enable_smp_cpt_teammember(): bool {
    return SharedContentTypes::register_type( SharedContentTypes::TEAM_MEMBER );
}
