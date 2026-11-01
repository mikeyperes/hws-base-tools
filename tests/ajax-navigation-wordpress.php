<?php
// Run through wp eval-file tests/ajax-navigation-wordpress.php in the plugin source.
use HWS\BaseTools\AjaxNavigation\AjaxNavigationModule;
$html='<html><head><script src="a.js"></script><script data-turbo-eval="true">const template="<script src=\'must-not-change\'>";</script><script type="application/ld+json">{}</script><style>.sample{color:red}</style><link rel="stylesheet" href="sample.css"></head></html>';
$out=AjaxNavigationModule::inert_turbo_scripts($html);
if(substr_count($out,'data-turbo-eval="false"')!==3) throw new RuntimeException('Expected exactly three actual script elements');
if(!str_contains($out,'const template="<script src=\'must-not-change\'>";')) throw new RuntimeException('Script contents changed');
if(!str_contains($out,'type="application/ld+json"')) throw new RuntimeException('JSON-LD type changed');
if(AjaxNavigationModule::inert_turbo_scripts($out)!==$out) throw new RuntimeException('Markup is not idempotent');
if(AjaxNavigationModule::inert_turbo_scripts('{"status":true}')!=='{"status":true}') throw new RuntimeException('JSON changed');
if(substr_count($out,'data-hws-ajax-style=')!==2) throw new RuntimeException('Page CSS ownership markers missing');
echo "AJAX navigation WordPress parser: 6 checks passed\n";
