<?php

defined( 'ABSPATH' ) || exit;

get_header();
\HWS\BaseTools\BrandTemplates\BrandTemplateRenderer::render( \HWS\BaseTools\BrandTemplates\BrandTemplateRegistry::AUTHOR );
get_footer();
