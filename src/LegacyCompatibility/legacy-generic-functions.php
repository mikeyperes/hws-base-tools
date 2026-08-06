<?php

namespace hws_base_tools;

/**
 * Compatibility facade for the historical generic callback library.
 *
 * Implementations are grouped by responsibility under GenericLibrary while
 * the public hws_base_tools\* callbacks remain available to deployed sites.
 */
require_once __DIR__ . '/GenericLibrary/generic-foundation.php';
require_once __DIR__ . '/GenericLibrary/generic-wordpress-status.php';
require_once __DIR__ . '/GenericLibrary/generic-server-diagnostics.php';
require_once __DIR__ . '/GenericLibrary/generic-site-operations.php';
require_once __DIR__ . '/GenericLibrary/generic-structure-renderers.php';
require_once __DIR__ . '/GenericLibrary/generic-launch-readiness.php';
