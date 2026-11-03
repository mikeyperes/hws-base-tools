<?php
use HWS\BaseTools\Newsletter\NewsletterModule as N;
$s=N::store();$list='nl2-scan-'.bin2hex(random_bytes(5));$n=0;
$check=function($ok,$label)use(&$n){if(!$ok)throw new RuntimeException('FAIL '.$label);$n++;echo "PASS $label\n";};
try{
 for($i=0;$i<3;$i++)$s->add($list,'scan-'.$i.'@example.com');
 $a=$s->scan(0,1,$list);$check(count($a['items'])===1&&$a['has_more'],'bounded first page');
 $id=$a['next_after_id'];$upper=$a['upper_id'];
 $later=$s->add($list,'scan-later@example.com');
 $check($later['id']>$upper,'later insert exceeds snapshot');
 $s->delete($list,$id);$b=$s->scan($id,1,$list,$upper);
 $check(count($b['items'])===1&&$b['next_after_id']>$id,'deleted cursor row does not skip next row');
 $c=$s->scan($b['next_after_id'],500,$list,$upper);
 $check(count($c['items'])===1&&!$c['has_more'],'snapshot finishes before later insert');
 $all=$s->scan(0,500,$list);$check(count($all['items'])===3&&$all['upper_id']===$later['id'],'fresh snapshot reconciles deletion and insertion');
 $check(count($s->scan(0,0,$list)['items'])===1,'lower limit clamps to one');
 $check(count($s->scan(0,999999,$list)['items'])===3,'upper limit safely clamps');
 $check($s->scan(0,1,$list,0)['items']===[],'empty snapshot cannot acquire later rows');
 try{$s->scan(0,1,'../bad');$ok=false;}catch(InvalidArgumentException $e){$ok=true;}$check($ok,'collection injection rejected');
 $bad=static function($sql)use($s){return str_starts_with($sql,'SELECT * FROM '.$s->table().' WHERE id >')?'SELECT * FROM hws_nl2_scan_missing_table':$sql;};
 add_filter('query',$bad);global $wpdb;$prior=$wpdb->suppress_errors(true);
 try{$s->scan(0,1,$list,$upper);$ok=false;}catch(RuntimeException $e){$ok=true;}finally{remove_filter('query',$bad);$wpdb->suppress_errors($prior);}
 $check($ok,'storage read failure never masquerades as empty success');
 $bad_max=static function($sql){return str_starts_with($sql,'SELECT MAX(id) FROM ')?'SELECT * FROM hws_nl2_scan_missing_table':$sql;};
 add_filter('query',$bad_max);$prior=$wpdb->suppress_errors(true);
 try{$s->scan(0,1,$list);$ok=false;}catch(RuntimeException $e){$ok=true;}finally{remove_filter('query',$bad_max);$wpdb->suppress_errors($prior);}
 $check($ok,'snapshot bound read failure is reported');
 echo "PASS $n snapshot scan assertions\n";
}finally{global $wpdb;$wpdb->query($wpdb->prepare('DELETE FROM '.$s->table().' WHERE collection=%s',$list));$wpdb->query($wpdb->prepare('DELETE FROM '.$s->deletions_table().' WHERE collection=%s',$list));}
