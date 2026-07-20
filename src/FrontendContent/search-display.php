<?php

namespace hws_base_tools;

use HWS\BaseTools\FrontendContent\SearchDisplayFeature;

add_action( 'init', [ SearchDisplayFeature::class, 'register_shortcode' ], 4 );
