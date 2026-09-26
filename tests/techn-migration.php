<?php
/** Disposable WordPress only. MIGRATION_PLAN JSON lists id,file,version,asset. LOCAL_PACKAGES optionally supplies local verified release fixtures. */
wp_set_current_user(1);
$plan=json_decode(file_get_contents(getenv('MIGRATION_PLAN')),true);
function migration_assert($ok,$message){if(!$ok){throw new RuntimeException($message);}echo "PASS: $message\n";}
$before=get_plugins();$network_before=get_site_option('active_sitewide_plugins',[]);$active_before=get_option('active_plugins',[]);
foreach($plan as $id=>$e){
 migration_assert(isset($before[$e['file']]) && version_compare($e['version'],$before[$e['file']]['Version'],'>'),$id.' original version '.$before[$e['file']]['Version'].' detected');
 update_option('migration_preserve_'.$id,['sentinel'=>$id,'data'=>['unchanged']]);
}
$observed=[];$local=getenv('LOCAL_PACKAGES');
$http=function($pre,$args,$url)use(&$observed,$local,$plan){
 $observed[]=$url;
 if(!$local){return $pre;}
 if($url===TNUC_CATALOGUE_URL){return ['response'=>['code'=>200],'headers'=>[],'body'=>file_get_contents(getenv('MIGRATION_CATALOGUE'))];}
 foreach($plan as $id=>$e){
  if($url==='https://github.com/cchatterton/'.$id.'/releases/download/v'.$e['version'].'/'.$e['asset']){
   copy($local.'/'.$id.'/'.$e['asset'],$args['filename']);return ['response'=>['code'=>200],'headers'=>[],'body'=>''];
  }
 }
 return new WP_Error('unexpected_http','Unexpected request: '.$url);
};
add_filter('pre_http_request',$http,10,3);
tnuc_put('check',[]);tnuc_put('batch',[]);
$result=tnuc_refresh();migration_assert(!is_wp_error($result),'single catalogue check succeeds');
migration_assert(count($observed)===1 && $observed[0]===TNUC_CATALOGUE_URL,'all update discovery uses one aggregate request');
$read_count=count($observed);
for($i=0;$i<10;$i++){get_site_transient('update_plugins');}
foreach($plan as $id=>$e){apply_filters('plugin_row_meta',[],$e['file'],[],'all');}
migration_assert(count($observed)===$read_count,'all legacy plugin rows and repeated reads make zero extra HTTP requests');
$job=tnuc_start_batch(array_keys($plan),'update');migration_assert(!is_wp_error($job),'ten-plugin batch starts');
$steps=0;
while($job['status']==='running' && $steps++<count($plan)+2){
 $job=tnuc_step_batch($job['id']);
 migration_assert(!is_wp_error($job),'batch step '.$steps.' returns a result');
 $item=$job['items'][$steps-1];migration_assert($item['status']==='success',$item['id'].' update succeeds: '.($item['message']??''));
}
migration_assert($job['status']==='complete','all ten updates complete');
$after=get_plugins();
foreach($plan as $id=>$e){
 migration_assert($after[$e['file']]['Version']===$e['version'],$id.' target version installed');
 migration_assert(get_option('migration_preserve_'.$id)===['sentinel'=>$id,'data'=>['unchanged']],$id.' stored setting preserved');
 migration_assert(tnuc_migration_status(tnuc_registry()[$id])==='Controller integration',$id.' fully migrated');
}
migration_assert(get_site_option('active_sitewide_plugins',[])===$network_before,'network activation scope preserved');
migration_assert(get_option('active_plugins',[])===$active_before,'site activation scope preserved');
$repeat=tnuc_step_batch($job['id']);migration_assert($repeat['status']==='complete','completed batch safely idempotent');
remove_filter('pre_http_request',$http,10);
echo "Bulk migration validation complete.\n";
