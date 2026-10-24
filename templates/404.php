<?php
/** HWS Base Tools 404 page: the site's header and footer around the chosen design. */
defined( 'ABSPATH' ) || exit;

get_header();
echo \HWS\BaseTools\NotFoundPage\NotFoundPage::markup( (string) \HWS\BaseTools\NotFoundPage\NotFoundSettings::all()['design'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in markup().
get_footer();
