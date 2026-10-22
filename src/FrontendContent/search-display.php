<?php

namespace hws_base_tools;

use HWS\BaseTools\FrontendContent\SearchQueryFeature;

add_action( 'init', [ SearchQueryFeature::class, 'register_query_engine' ], 3 );
