<?php
declare(strict_types=1);
namespace HWS\BaseTools\FormProtection;
final class AttemptLog {
    private static bool $ready=false;
    public static function table():string {global $wpdb;return $wpdb->prefix.'hws_base_tools_form_blocks';}
    public static function exists():bool {global $wpdb;$table=self::table();return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($table)))===$table;}
    private static function install():void {
        if(self::$ready)return;
        global $wpdb;$table=self::table();
        if(!self::exists()){
            require_once ABSPATH.'wp-admin/includes/upgrade.php';
            dbDelta("CREATE TABLE $table (id bigint(20) unsigned NOT NULL AUTO_INCREMENT, occurred_at datetime NOT NULL, form_key varchar(128) NOT NULL, ip varchar(45) NOT NULL, reason varchar(32) NOT NULL, PRIMARY KEY (id), KEY occurred_at (occurred_at), KEY form_key (form_key)) ".$wpdb->get_charset_collate().';');
        }
        self::$ready=true;
    }
    public static function record(string $form,string $ip,string $reason):void {
        if(!in_array($reason,['honeypot','too_fast','missing_token','invalid_token','turnstile'],true))return;
        self::install();global $wpdb;$table=self::table();
        $wpdb->insert($table,['occurred_at'=>gmdate('Y-m-d H:i:s'),'form_key'=>substr($form,0,128),'ip'=>filter_var($ip,FILTER_VALIDATE_IP)?$ip:'','reason'=>$reason],['%s','%s','%s','%s']);
        self::prune();
    }
    public static function prune():void {
        if(!self::exists())return;
        global $wpdb;$table=self::table();$days=Settings::get()['retention_days'];
        $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE occurred_at < %s",gmdate('Y-m-d H:i:s',time()-$days*86400)));
        $cutoff=$wpdb->get_var("SELECT id FROM $table ORDER BY id DESC LIMIT 1 OFFSET 4999");
        if($cutoff)$wpdb->query($wpdb->prepare("DELETE FROM $table WHERE id < %d",$cutoff));
    }
    public static function read(int $limit=100):array {
        if(!self::exists())return ['available'=>false,'attempts'=>[]];
        global $wpdb;$table=self::table();
        return ['available'=>true,'attempts'=>$wpdb->get_results($wpdb->prepare("SELECT id,occurred_at,form_key,ip,reason FROM $table ORDER BY id DESC LIMIT %d",max(1,min(1000,$limit))),ARRAY_A)];
    }
}
