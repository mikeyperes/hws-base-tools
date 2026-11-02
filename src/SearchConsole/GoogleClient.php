<?php
namespace HWS\BaseTools\SearchConsole;
use Hexa\PluginCore\CredentialVault\CredentialStore;

/** Direct Google service-account transport; credentials never leave this server except signed assertions to Google. */
final class GoogleClient {
    public const KEY='google-service-account';
    private ?string $token=null;
    public static function configured(): bool {return (new CredentialStore)->exists('hws-base-tools',self::KEY);}
    public static function save(string $json): array {
        $key=json_decode($json,true);
        if (!function_exists('openssl_encrypt') || !function_exists('openssl_sign')) return ['ok'=>false,'message'=>'OpenSSL is required for protected storage and Google signing.'];
        if (!is_array($key) || 'service_account'!==($key['type']??'') || !is_email($key['client_email']??'') || empty($key['private_key']) || !openssl_pkey_get_private($key['private_key'])) return ['ok'=>false,'message'=>'A valid Google service-account JSON key is required.'];
        // Keep only the signing fields. Never trust uploaded OAuth endpoints.
        $safe=['type'=>'service_account','client_email'=>strtolower($key['client_email']),'private_key'=>$key['private_key'],'project_id'=>sanitize_text_field($key['project_id']??'')];
        (new CredentialStore)->store('hws-base-tools',self::KEY,wp_json_encode($safe));
        return ['ok'=>true,'configured'=>self::configured()];
    }
    private function authorize() {
        if ($this->token) return $this->token;
        $key=json_decode((string)(new CredentialStore)->get('hws-base-tools',self::KEY),true);
        if (!$key) return new \WP_Error('missing_key','Google service-account key is not configured.');
        $enc=static fn($v)=>rtrim(strtr(base64_encode(wp_json_encode($v)),'+/','-_'),'=');
        $now=time();
        $jwt=$enc(['alg'=>'RS256','typ'=>'JWT']).'.'.$enc(['iss'=>$key['client_email'],'scope'=>'https://www.googleapis.com/auth/webmasters https://www.googleapis.com/auth/siteverification https://www.googleapis.com/auth/indexing','aud'=>'https://oauth2.googleapis.com/token','iat'=>$now,'exp'=>$now+3600]);
        $signature='';
        if (!openssl_sign($jwt,$signature,$key['private_key'],OPENSSL_ALGO_SHA256)) return new \WP_Error('signing_failed','Google signing failed.');
        $jwt.='.'.rtrim(strtr(base64_encode($signature),'+/','-_'),'=');
        $r=wp_remote_post('https://oauth2.googleapis.com/token',['timeout'=>20,'body'=>['grant_type'=>'urn:ietf:params:oauth:grant-type:jwt-bearer','assertion'=>$jwt]]);
        if (is_wp_error($r)) return new \WP_Error('google_transport','Google authentication could not be reached.');
        $body=json_decode(wp_remote_retrieve_body($r),true);
        if (200!==wp_remote_retrieve_response_code($r) || empty($body['access_token'])) return new \WP_Error('google_auth','Google rejected service-account authentication (HTTP '.wp_remote_retrieve_response_code($r).').');
        return $this->token=$body['access_token'];
    }
    public function request(string $url,string $method='GET',?array $body=null): array {
        // Callers construct URLs from fixed Google endpoints only.
        $host=wp_parse_url($url,PHP_URL_HOST);
        if (!in_array($host,['www.googleapis.com','searchconsole.googleapis.com','indexing.googleapis.com'],true)) return ['ok'=>false,'http_code'=>0,'message'=>'Unsupported Google endpoint.'];
        $token=$this->authorize();
        if (is_wp_error($token)) return ['ok'=>false,'http_code'=>0,'message'=>$token->get_error_message(),'reason'=>$token->get_error_code()];
        $args=['method'=>$method,'timeout'=>25,'redirection'=>0,'headers'=>['Authorization'=>'Bearer '.$token,'Content-Type'=>'application/json']];
        if (null!==$body) $args['body']=wp_json_encode($body);
        $response=wp_remote_request($url,$args);
        if (is_wp_error($response)) return ['ok'=>false,'http_code'=>0,'message'=>'Google request could not be completed.'];
        $code=wp_remote_retrieve_response_code($response);
        $data=json_decode(wp_remote_retrieve_body($response),true)?:[];
        $reason=$data['error']['errors'][0]['reason']??$data['error']['status']??'';
        $message=$data['error']['message']??'';
        return ['ok'=>$code>=200 && $code<300,'http_code'=>$code,'reason'=>$reason,'message'=>$message,'data'=>$code>=200 && $code<300?$data:[]];
    }
}
