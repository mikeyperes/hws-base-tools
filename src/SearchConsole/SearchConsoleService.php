<?php
namespace HWS\BaseTools\SearchConsole;
use Hexa\PluginCore\Sitemaps\RecommendationRegistry;
final class SearchConsoleService {
    private GoogleClient $client;
    public function __construct(?GoogleClient $client=null){$this->client=$client??new GoogleClient;}
    public static function property(): string {return (string)get_option('hws_base_tools_search_console_property','sc-domain:'.wp_parse_url(home_url('/'),PHP_URL_HOST));}
    public static function own_url(string $url): bool {
        $home=wp_parse_url(home_url('/'));$input=wp_parse_url($url);
        return !empty($input['host']) && strtolower($input['host'])===strtolower($home['host']) && in_array($input['scheme']??'',['http','https'],true) && empty($input['user']) && empty($input['pass']) && empty($input['port']);
    }
    private function base(): string {return 'https://www.googleapis.com/webmasters/v3/sites/'.rawurlencode(self::property());}
    public function status(): array {
        $property=self::property();
        $result=['configured'=>GoogleClient::configured(),'property'=>$property,'console_url'=>'https://search.google.com/search-console?resource_id='.rawurlencode($property),'registered'=>null,'owners'=>[],'owners_scope'=>'Direct verified owners only; Google does not expose all delegated users here.'];
        if (!$result['configured']) return $result+['message'=>'Google service-account key is not configured.'];
        $site=$this->client->request($this->base());
        $result['property_response']=$site;
        $result['registered']=$site['ok']?true:(404===$site['http_code']?false:null);
        $result['permission']=$site['data']['permissionLevel']??null;
        $owners=$this->client->request('https://www.googleapis.com/siteVerification/v1/webResource');
        foreach (($owners['data']['items']??[]) as $item) {
            $identifier=$item['site']['identifier']??'';
            if ($identifier===$property || 'sc-domain:'.$identifier===$property || $identifier===home_url('/')) $result['owners']=array_values(array_unique(array_map('strtolower',$item['owners']??[])));
        }
        $result['owners_response']=['ok'=>$owners['ok'],'http_code'=>$owners['http_code'],'message'=>$owners['message']];
        $sitemaps=$this->client->request($this->base().'/sitemaps');
        $result['sitemaps_response']=$sitemaps;
        $result['sitemaps']=$sitemaps['data']['sitemap']??[];
        return $result;
    }
    public function submit(string $id): array {
        $rows=array_column(RecommendationRegistry::lookup()['rows'],null,'id');
        if (!isset($rows[$id]) || !self::own_url($rows[$id]['url'])) return ['ok'=>false,'message'=>'Choose a registered sitemap on this site.'];
        return ['id'=>$id,'url'=>$rows[$id]['url']]+$this->client->request($this->base().'/sitemaps/'.rawurlencode($rows[$id]['url']),'PUT');
    }
    public function submit_all(): array {
        $results=[];
        foreach(RecommendationRegistry::lookup()['rows'] as $row) if($row['recommended'] && $row['current'] && $row['available']) $results[]=$this->submit($row['id']);
        return ['results'=>$results,'count'=>count($results)];
    }
    public function inspect(string $url): array {
        if (!self::own_url($url)) return ['ok'=>false,'message'=>'Choose a page on this site.'];
        return $this->client->request('https://searchconsole.googleapis.com/v1/urlInspection/index:inspect','POST',['inspectionUrl'=>$url,'siteUrl'=>self::property(),'languageCode'=>'en-US']);
    }
    public function reindex(string $url): array {
        if (!self::own_url($url)) return ['ok'=>false,'message'=>'Choose a page on this site.'];
        $r=$this->client->request('https://indexing.googleapis.com/v3/urlNotifications:publish','POST',['url'=>$url,'type'=>'URL_UPDATED']);
        $r['accepted']=$r['ok'];
        if (403===$r['http_code']) $r['message']='Google Indexing API returned 403. The API may be disabled in the Google project or this account lacks permission. '.$r['message'];
        $r['notice']='Google supports this API for JobPosting and BroadcastEvent pages. Acceptance never proves indexing.';
        return $r;
    }
}
