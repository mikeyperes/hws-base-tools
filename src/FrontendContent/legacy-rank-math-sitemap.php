<?php

namespace hws_base_tools;

function disable_rankmath_sitemap_caching(): void {
    add_filter( 'rank_math/sitemap/enable_caching', '__return_false' );
}
