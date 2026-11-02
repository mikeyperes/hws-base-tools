<?php
namespace HWS\BaseTools\SearchConsole;
use Hexa\PluginCore\Sitemaps\RecommendationRegistry;
final class SearchConsoleService {
    private GoogleClient $client;
    public function __construct(?GoogleClient $client=null){$this->client=$client??new GoogleClient;}
    private ?array $resolved=null;
    /** Exact domain, closest parents (never a single-label suffix), then HTTPS URL prefix. */
    public static function candidates(): array {
        $host=strtolower(rtrim((string)wp_parse_url(home_url('/'),PHP_URL_HOST),'.'));
        $labels=explode('.',$host);$properties=['sc-domain:'.$host];
        while(count($labels)>2){array_shift($labels);$properties[]='sc-domain:'.implode('.',$labels);}
        $properties[]='https://'.$host.'/';
        return $properties;
    }
    public static function property(): string {return (new self)->resolve()['property'];}
    private function resolve(): array {
        if(null!==$this->resolved) return $this->resolved;
        $attempts=[];$unknown=null;
        foreach(self::candidates() as $property) {
            $response=$this->client->request('https://www.googleapis.com/webmasters/v3/sites/'.rawurlencode($property));
            $attempts[]=['property'=>$property,'ok'=>$response['ok'],'http_code'=>$response['http_code'],'reason'=>$response['reason']??'','message'=>$response['message']];
            $candidate=['property'=>$property,'registered'=>$response['ok']?true:(404===$response['http_code']?false:null),'property_response'=>$response];
            if($response['ok']) return $this->resolved=$candidate+['property_attempts'=>$attempts];
            if(404!==$response['http_code'] && null===$unknown) $unknown=$candidate;
            // Authentication, transport and provider failures cannot be resolved by another property.
            if(!in_array($response['http_code'],[403,404],true)) break;
        }
        return $this->resolved=($unknown??$candidate)+['property_attempts'=>$attempts];
    }
    private function unavailable(): ?array {
        $resolved=$this->resolve();
        return true===$resolved['registered']?null:['property'=>$resolved['property'],'accepted'=>false]+$resolved['property_response'];
    }
    public static function own_url(string $url): bool {
        $home=wp_parse_url(home_url('/'));$input=wp_parse_url($url);
        return !empty($input['host']) && strtolower($input['host'])===strtolower($home['host']) && in_array($input['scheme']??'',['http','https'],true) && empty($input['user']) && empty($input['pass']) && empty($input['port']);
    }
    private function base(): string {return 'https://www.googleapis.com/webmasters/v3/sites/'.rawurlencode($this->resolve()['property']);}
    public function status(): array {
        $property=self::candidates()[0];
        $result=['configured'=>GoogleClient::configured(),'property'=>$property,'console_url'=>'https://search.google.com/search-console?resource_id='.rawurlencode($property),'registered'=>null,'owners'=>[],'owners_scope'=>'Direct verified owners only; Google does not expose all delegated users here.'];
        if (!$result['configured']) return $result+['message'=>'Google service-account key is not configured.'];
        $resolved=$this->resolve();$property=$resolved['property'];$site=$resolved['property_response'];
        $result=array_replace($result,$resolved);
        $result['console_url']='https://search.google.com/search-console?resource_id='.rawurlencode($property);
        $result['permission']=$site['data']['permissionLevel']??null;
        $owners=$this->client->request('https://www.googleapis.com/siteVerification/v1/webResource');
        foreach (($owners['data']['items']??[]) as $item) {
            $identifier=$item['site']['identifier']??'';
            if ($identifier===$property || 'sc-domain:'.$identifier===$property) $result['owners']=array_values(array_unique(array_map('strtolower',$item['owners']??[])));
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
        if($failure=$this->unavailable()) return ['id'=>$id,'url'=>$rows[$id]['url']]+$failure;
        return ['property'=>$this->resolve()['property'],'id'=>$id,'url'=>$rows[$id]['url']]+$this->client->request($this->base().'/sitemaps/'.rawurlencode($rows[$id]['url']),'PUT');
    }
    public function submit_all(): array {
        $results=[];
        foreach(RecommendationRegistry::lookup()['rows'] as $row) if($row['recommended'] && $row['current'] && $row['available']) $results[]=$this->submit($row['id']);
        return ['property'=>$this->resolve()['property'],'results'=>$results,'count'=>count($results)];
    }
    public function inspect(string $url): array {
        if (!self::own_url($url)) return ['ok'=>false,'message'=>'Choose a page on this site.'];
        if($failure=$this->unavailable()) return $failure;
        return ['property'=>$this->resolve()['property']]+$this->client->request('https://searchconsole.googleapis.com/v1/urlInspection/index:inspect','POST',['inspectionUrl'=>$url,'siteUrl'=>$this->resolve()['property'],'languageCode'=>'en-US']);
    }
    public function reindex(string $url): array {
        if (!self::own_url($url)) return ['ok'=>false,'message'=>'Choose a page on this site.'];
        if($failure=$this->unavailable()) return $failure;
        $r=['property'=>$this->resolve()['property']]+$this->client->request('https://indexing.googleapis.com/v3/urlNotifications:publish','POST',['url'=>$url,'type'=>'URL_UPDATED']);
        $r['accepted']=$r['ok'];
        if (403===$r['http_code']) $r['message']='Google Indexing API returned 403. The API may be disabled in the Google project or this account lacks permission. '.$r['message'];
        $r['notice']='Google supports this API for JobPosting and BroadcastEvent pages. Acceptance never proves indexing.';
        return $r;
    }
}
