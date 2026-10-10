<?php
/**
 * Recover ONLY missing 301 category redirects after an interrupted phase 2.
 * Does not merge, delete, rename, or reparent any product category.
 * Enforces exact 926 -> 901 expected term ID/hierarchy transitions and
 * confirms product transfers recorded in the previous migration journal.
 */
defined('ABSPATH') || exit;
if(!defined('WP_CLI') || !WP_CLI)throw new RuntimeException('WP-CLI required.');
$mode=getenv('CV_MISPLACED_RECOVER_MODE')?:'plan';
if(!in_array($mode,['plan','apply'],true))throw new RuntimeException('Invalid mode.');
$srcfile=getenv('CV_MISPLACED_RECOVER_SNAPSHOT');
$planfile=getenv('CV_MISPLACED_RECOVER_PLAN');
$journalfile=getenv('CV_MISPLACED_RECOVER_JOURNAL');
$dir=getenv('CV_MISPLACED_RECOVER_DIR');
if(!$srcfile||!is_file($srcfile)||!$planfile||!is_file($planfile)||!$journalfile||!is_file($journalfile)||!$dir)
    throw new RuntimeException('Recovery evidence files missing.');
if(!is_dir($dir)&&!wp_mkdir_p($dir))throw new RuntimeException('Recovery output unavailable.');
$source=json_decode(file_get_contents($srcfile),true);
$plan=json_decode(file_get_contents($planfile),true);
if(!is_array($source)||!is_array($plan)||!isset($source['loja'],$plan['actions']))
    throw new RuntimeException('Malformed source recovery evidence.');
if(count($source['loja'])!==926||count($plan['actions'])!==27)
    throw new RuntimeException('Original inventory / action count changed.');
$before=[];$deleted=[];$reparent=[];$rename=[];
foreach($source['loja'] as $row){
    $id=(int)$row['id'];
    if(!$id || isset($before[$id]))throw new RuntimeException('Invalid source term ID '.$id);
    $before[$id]=$row;
}
foreach($plan['actions'] as $action){
    $id=(int)$action['old'];$to=(int)$action['keep'];
    if(!isset($before[$id],$before[$to])||isset($deleted[$id])||isset($reparent[$id]))
        throw new RuntimeException('Unexpected operation IDs '.$id);
    if($action['mode']==='merge'){
        $deleted[$id]=$to;
        $rename[$to]=(string)$action['source_slug'];
    }elseif($action['mode']==='reparent'){
        $reparent[$id]=$to;
    }else throw new RuntimeException('Unsupported recovery operation '.$id);
}
if(count($deleted)!==25||count($reparent)!==2)
    throw new RuntimeException('Recovery expects 25 merges and two reparent operations.');

$terms=get_terms(['taxonomy'=>'product_cat','hide_empty'=>false,'orderby'=>'term_id','order'=>'ASC']);
if(is_wp_error($terms)||count($terms)!==901)throw new RuntimeException('Live taxonomy is not exactly 901 categories.');
$after=[];$canonicalPaths=[];
foreach($terms as $term){
    $id=(int)$term->term_id;
    if(!isset($before[$id])||isset($deleted[$id]))throw new RuntimeException('Unexpected live term ID '.$id);
    $old=$before[$id];
    $expectedParent=$reparent[$id]??(int)$old['parent'];
    $expectedSlug=$rename[$id]??(string)$old['slug'];
    $actualName=html_entity_decode(wp_strip_all_tags($term->name),ENT_QUOTES|ENT_HTML5,'UTF-8');
    if((int)$term->parent!==(int)$expectedParent||
       (string)$term->slug!==$expectedSlug||
       $actualName!==(string)$old['name'])
        throw new RuntimeException('Live term modified outside expected migration: '.$id);
    $url=get_term_link($term);
    if(is_wp_error($url))throw new RuntimeException('Invalid live category permalink '.$id);
    $path=wp_parse_url($url,PHP_URL_PATH);
    if(!is_string($path)||!str_starts_with($path,'/categoria-produto/'))
        throw new RuntimeException('Unexpected live permalink structure '.$id);
    if(isset($canonicalPaths[$path]))throw new RuntimeException('Two terms share a canonical URL');
    $canonicalPaths[$path]=$id;
    $after[$id]=['term'=>$term,'url'=>$url,'path'=>$path];
}
if(count($after)!==901 || count($before)-count($deleted)!==901)
    throw new RuntimeException('Term count or IDs do not match exact recovery plan.');
foreach([15,2248] as $id)if(!isset($after[$id]))throw new RuntimeException('System category removed '.$id);

// Every migrated product relationship was journalled before its old term was deleted.
$productEntries=[];$completedDeletes=[];
$lines=file($journalfile,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
if(!is_array($lines)||!$lines)throw new RuntimeException('Missing migration journal contents');
foreach($lines as $line){
    $event=json_decode($line,true);
    if(!is_array($event))throw new RuntimeException('Malformed migration journal line.');
    if(($event['op']??'')==='product')$productEntries[]=$event;
    if(($event['op']??'')==='merged')$completedDeletes[(int)$event['from']]=true;
}
foreach(array_keys($deleted) as $id)if(!isset($completedDeletes[$id]))
    throw new RuntimeException('Journal does not confirm all 25 merged categories: '.$id);
foreach($productEntries as $event){
    $pid=(int)($event['id']??0);$old=(int)($event['from']??0);$keep=(int)($event['to']??0);
    if(!$pid||!isset($deleted[$old])||$deleted[$old]!==$keep)
        throw new RuntimeException('Journal product operation outside approved plan.');
    $ids=wp_get_object_terms($pid,'product_cat',['fields'=>'ids']);
    if(is_wp_error($ids)||!in_array($keep,array_map('intval',$ids),true)||
       in_array($old,array_map('intval',$ids),true))
        throw new RuntimeException('Product lost its intended category: '.$pid);
}

$requiredRedirects=[];
$conflicts=[];
foreach($before as $id=>$row){
    $oldURL=$row['url']??'';
    $oldPath=wp_parse_url($oldURL,PHP_URL_PATH);
    $toId=$deleted[$id]??$id;
    $newPath=$after[$toId]['path']??null;
    if(!is_string($oldPath)||!str_starts_with($oldPath,'/categoria-produto/')||!$newPath)
        throw new RuntimeException('Invalid old or new permalink '.$id);
    if($oldPath===$newPath)continue;
    if(isset($canonicalPaths[$oldPath]) && $canonicalPaths[$oldPath]!==$toId){
        $conflicts[]=['term_id'=>$id,'old'=>$oldPath,'current_canonical_term'=>$canonicalPaths[$oldPath],'desired'=>$newPath];
        continue;
    }
    if(isset($requiredRedirects[$oldPath]) && $requiredRedirects[$oldPath]!==$newPath)
        throw new RuntimeException('Conflicting redirect destinations '.$oldPath);
    $requiredRedirects[$oldPath]=$newPath;
}
if($conflicts) {
    file_put_contents($dir.'/redirect-conflicts.json',wp_json_encode($conflicts,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    throw new RuntimeException('Historical URLs now canonical elsewhere; manual redirect mapping needed.');
}
$current=get_option('cv_core_category_slug_redirects',[]);
if(!is_array($current))throw new RuntimeException('Current redirects are invalid.');
$missing=[];$oldConflicts=[];$updated=$current;
foreach($requiredRedirects as $old=>$new){
    if(($current[$old]??null)!==$new){
        if(isset($current[$old]))$oldConflicts[]=[$old,$current[$old],$new];
        $missing[$old]=$new;
        $updated[$old]=$new;
    }
}
if($oldConflicts) {
    file_put_contents($dir.'/redirect-changes.json',wp_json_encode($oldConflicts,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    // Never silently overwrite a previously established 301.
    throw new RuntimeException('Existing redirects differ from expected; manual review required.');
}
$summary=[
    'mode'=>$mode,'source_categories'=>count($before),'live_categories'=>count($after),
    'merged'=>count($deleted),'reparented'=>count($reparent),
    'product_journal_entries'=>count($productEntries),
    'redirects_required'=>count($requiredRedirects),
    'redirects_missing'=>count($missing),
    'existing_redirects'=>count($current),
    'timestamp_utc'=>gmdate('c')
];
file_put_contents($dir.'/recovery-plan.json',wp_json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo 'CV_MISPLACED_RECOVER_PLAN '.wp_json_encode($summary,JSON_UNESCAPED_UNICODE).PHP_EOL;
if($mode==='plan')return;
if(getenv('CV_MISPLACED_RECOVER_CONFIRM')!=='RESTORE_ONLY_VERIFIED_CATEGORY_REDIRECTS')
    throw new RuntimeException('Explicit recovery approval flag missing.');
$lock=get_option('cv_category_merge_operation_lock',false);
if($lock) {
    if(!is_array($lock)||($lock['type']??'')!=='misplaced')
        throw new RuntimeException('Unknown category operation lock; refusing recovery.');
}
// Runner shell separately verifies no old WP-CLI process remains.
if($missing){
    if(!update_option('cv_core_category_slug_redirects',$updated,false) &&
       get_option('cv_core_category_slug_redirects',[])!==$updated)
        throw new RuntimeException('Failed to persist repaired 301 redirects.');
}
$check=get_option('cv_core_category_slug_redirects',[]);
foreach($requiredRedirects as $old=>$new)if(($check[$old]??null)!==$new)
    throw new RuntimeException('Redirect verification failed '.$old);
if($lock && !delete_option('cv_category_merge_operation_lock'))
    throw new RuntimeException('Failed to remove stale lock after verified recovery.');
$result=[
    'status'=>'success','before'=>926,'after'=>901,
    'merges'=>25,'reparented'=>2,
    'product_assignments_transferred'=>count($productEntries),
    'redirects'=>count($check),
    'recovered_missing_redirects'=>count($missing),
    'completed_at'=>gmdate('c'),'recovery'=>true
];
file_put_contents($dir.'/resultado-final.json',wp_json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo 'CV_MISPLACED_RECOVER_DONE '.wp_json_encode($result,JSON_UNESCAPED_UNICODE).PHP_EOL;
