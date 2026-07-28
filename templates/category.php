<?php

defined( 'ABSPATH' ) || exit;

get_header();
\HWS\BaseTools\BrandTemplates\BrandTemplateRenderer::render( \HWS\BaseTools\BrandTemplates\BrandTemplateRegistry::CATEGORY );
get_footer();
