<?php
/** Two independent REST dispatches released by one socket barrier against the actual MySQL unique key. */
function nl_concurrent(array $payload): array {
    global $wpdb;
    if(!function_exists('pcntl_fork'))throw new RuntimeException('pcntl extension required for concurrent test');
    $pairs=[stream_socket_pair(STREAM_PF_UNIX,STREAM_SOCK_STREAM,0),stream_socket_pair(STREAM_PF_UNIX,STREAM_SOCK_STREAM,0)];
    $wpdb->close();$pids=[];
    for($i=0;$i<2;$i++){
        $pid=pcntl_fork();if($pid<0)throw new RuntimeException('fork failed');
        if($pid===0){
            while(ob_get_level())ob_end_clean();
            foreach($pairs as $j=>$pair){fclose($pair[0]);if($j!==$i)fclose($pair[1]);}
            fread($pairs[$i][1],1);
            $wpdb=new wpdb(DB_USER,DB_PASSWORD,DB_NAME,DB_HOST);$wpdb->set_prefix($GLOBALS['table_prefix']);
            $_SERVER['REMOTE_ADDR']='198.51.100.'.(130+$i);
            $r=new WP_REST_Request('POST','/hws-base-tools/v1/newsletter');$r->set_body_params($payload);
            try{$response=rest_do_request($r);$result=['http'=>$response->get_status(),'body'=>$response->get_data()];}
            catch(Throwable $e){$result=['error'=>$e->getMessage()];}
            fwrite($pairs[$i][1],json_encode($result));fclose($pairs[$i][1]);exit(0);
        }
        $pids[]=$pid;
    }
    foreach($pairs as $pair){fclose($pair[1]);fwrite($pair[0],'G');}
    $results=[];foreach($pairs as $pair){stream_set_timeout($pair[0],20);$results[]=json_decode(stream_get_contents($pair[0]),true);fclose($pair[0]);}
    foreach($pids as $pid)pcntl_waitpid($pid,$status);
    $wpdb=new wpdb(DB_USER,DB_PASSWORD,DB_NAME,DB_HOST);$wpdb->set_prefix($GLOBALS['table_prefix']);
    return $results;
}
