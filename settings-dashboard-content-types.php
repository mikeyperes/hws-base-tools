<?php
namespace hws_base_tools;
require_once __DIR__ . '/src/AdminDashboard/ContentTypesTab.php';
function render_tab_content_types(): void {
    \HWS\BaseTools\AdminDashboard\ContentTypesTab::render();
}
