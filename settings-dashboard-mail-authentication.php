<?php
namespace hws_base_tools;
defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/src/MailAuthentication/MailAuthenticationAdmin.php';
\HWS\BaseTools\MailAuthentication\MailAuthenticationAdmin::register();
function render_tab_mail_authentication(): void { \HWS\BaseTools\MailAuthentication\MailAuthenticationAdmin::render(); }
