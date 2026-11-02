<?php
require dirname(__DIR__).'/lib/hexa-wordpress-plugin-core/tests/sitemap-policy.php';
require dirname(__DIR__).'/src/SitemapTools/Recommendations.php';
$options['rank-math-options-titles']=['local_address'=>['streetAddress'=>'123 Example Street','addressLocality'=>'Example City','addressCountry'=>'US']];
foreach(['company_website'=>true,'ecommerce_website'=>true,'news_outlet'=>false,'podcast_website'=>false,'personal_website'=>false,''=>false,'other'=>false] as $type=>$expected){
 $r=\HWS\BaseTools\SitemapTools\Recommendations::local_policy(['local'=>[]],$type);
 check($r['local']['recommended']===$expected,'Local recommendation respects primary site type');
}
$options['rank-math-options-titles']['local_address']['streetAddress']='PO Box 123';
$r=\HWS\BaseTools\SitemapTools\Recommendations::local_policy(['local'=>[]],'company_website');check(!$r['local']['recommended'],'PO Box is not a street address');
$options['rank-math-options-titles']['local_address']=[];
$r=\HWS\BaseTools\SitemapTools\Recommendations::local_policy(['local'=>[]],'company_website');check(!$r['local']['recommended'],'Missing address remains off');
echo "$tests total sitemap and local-policy assertions passed\n";
