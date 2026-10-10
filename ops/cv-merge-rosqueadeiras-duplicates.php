<?php
/**
 * Safe one-group duplicate consolidation: BACKUP is authoritative.
 * Defaults to read-only plan. No product is deleted or unpublished.
 * Run with wp eval-file, not from HTTP.
 */
defined('ABSPATH') || exit;
if (!defined('WP_CLI') || !WP_CLI) throw new RuntimeException('WP-CLI required.');
global $wpdb;
$mode = getenv('CV_MERGE_MODE') ?: 'plan';
if (!in_array($mode, ['plan','apply'], true)) throw new RuntimeException('Invalid mode.');
$confirm = getenv('CV_MERGE_CONFIRM') ?: '';
if ($mode==='apply' && $confirm!=='CV_ROSQUEADEIRAS_MERGE_APPROVED') {
    throw new RuntimeException('Explicit operation confirmation missing.');
}
if ($mode==='apply' && (!class_exists('CV_Core_Catalog_Rules')
    || !method_exists('CV_Core_Catalog_Rules','redirect_old_category_urls')
    || has_action('template_redirect', ['CV_Core_Catalog_Rules','redirect_old_category_urls'])===false)) {
    throw new RuntimeException('301 redirect module is not active.');
}
$limit = max(1,min(30,(int)(getenv('CV_MERGE_LIMIT')?:20)));
$dir = getenv('CV_MERGE_OUTPUT_DIR') ?: sys_get_temp_dir().'/cv-category-duplicate-merge';
if (!is_dir($dir) && !wp_mkdir_p($dir)) throw new RuntimeException('Output directory unavailable.');
$key = 'maquinas-p-industria-metal>engenhos-de-furar-furadoras>rosqueadeiras-maquinas-de-roscar';
$canonical_id = 1900;
$source_id = 3522;
$source_slug = 'rosqueadeiras';
$name = static fn($x) => sanitize_title(html_entity_decode(wp_strip_all_tags((string)$x),ENT_QUOTES|ENT_HTML5,'UTF-8'));
$backup = [];$page=1;$totalpages=0;
do {
    $url=add_query_arg(['per_page'=>100,'page'=>$page,'_fields'=>'id,name,slug,parent','hide_empty'=>'false','orderby'=>'id','order'=>'asc'],
      'https://backup.chavevertical.com/wp-json/wp/v2/product_cat');
    $r=wp_remote_get($url,['timeout'=>30,'sslverify'=>true,'redirection'=>2,'headers'=>['Accept'=>'application/json']]);
    if (is_wp_error($r) || wp_remote_retrieve_response_code($r)!==200)
        throw new RuntimeException('Backup API unavailable; refuse merge.');
    $arr=json_decode(wp_remote_retrieve_body($r),true);
    if (!is_array($arr))throw new RuntimeException('Malformed backup categories.');
    foreach ($arr as $x) {
        $id=(int)($x['id']??0);
        if (!$id || isset($backup[$id])) throw new RuntimeException('Duplicate/invalid source IDs.');
        $backup[$id]=['id'=>$id,'name'=>(string)$x['name'],'slug'=>(string)$x['slug'],'parent'=>(int)$x['parent']];
    }
    if(!$totalpages){$totalpages=(int)wp_remote_retrieve_header($r,'x-wp-totalpages');}
    $page++;
    if($page>40)throw new RuntimeException('Backup pagination unsafe.');
}while($totalpages?$page<=$totalpages:count($arr)===100);
if(count($backup)<850 || !isset($backup[$source_id]) || $backup[$source_id]['slug']!==$source_slug)
    throw new RuntimeException('Backup taxonomy mismatch.');
$tree = static function($id,$terms)use($name){
    $values=[];$seen=[];$cursor=(int)$id;
    while($cursor){
        if(isset($seen[$cursor]) || !isset($terms[$cursor]) || count($seen)>20)return null;
        $seen[$cursor]=true;
        $values[]=$name($terms[$cursor]['name']);
        $cursor=(int)$terms[$cursor]['parent'];
    }
    return implode('>',array_reverse($values));
};
$matches=[];
foreach ($backup as $id=>$t)if($tree($id,$backup)===$key)$matches[]=$id;
if($matches!==[$source_id])throw new RuntimeException('Backup does not have precisely one matching category.');
$localterms=get_terms(['taxonomy'=>'product_cat','hide_empty'=>false,'orderby'=>'term_id','order'=>'ASC']);
if(is_wp_error($localterms))throw new RuntimeException('WooCommerce taxonomy unavailable.');
$locals=[];$children=[];
foreach($localterms as $t){
    $id=(int)$t->term_id;
    $locals[$id]=['id'=>$id,'name'=>$t->name,'slug'=>$t->slug,'parent'=>(int)$t->parent,
      'term_taxonomy_id'=>(int)$t->term_taxonomy_id,'description'=>$t->description,'count'=>$t->count];
    $children[(int)$t->parent][]=$id;
}
if(!isset($locals[$canonical_id]) || $locals[$canonical_id]['slug']!==$source_slug
    || $tree($canonical_id,$locals)!==$key || (int)get_option('default_product_cat',0)===$canonical_id)
    throw new RuntimeException('Canonical category no longer matches BACKUP.');
$keeper_link=get_term_link($canonical_id,'product_cat');
if(is_wp_error($keeper_link))throw new RuntimeException('Invalid canonical category URL.');
$keep_path=wp_parse_url($keeper_link,PHP_URL_PATH);
$dup=[];
foreach($locals as $id=>$cat)if($id!==$canonical_id && $tree($id,$locals)===$key)$dup[]=$id;
if(count($dup)<1) {
    echo 'CV_CATEGORY_MERGE_ALREADY_CLEAN '.wp_json_encode(['canonical'=>$canonical_id,'backup_count'=>count($backup),'loja_count'=>count($locals)]).PHP_EOL;
    return;
}
if(count($dup)>130)throw new RuntimeException('Unexpected number of duplicates.');
$redirects=get_option('cv_core_category_slug_redirects',[]);
if(!is_array($redirects))throw new RuntimeException('Bad SEO redirect option.');
$allowed_meta=['order','display_type'];
$eligible=[];$skipped=[];$snapshots=[];$relationships = [];
foreach($dup as $id){
    $cat=$locals[$id];
    $issues=[];
    if(!empty($children[$id]))$issues[]='has_children';
    if((int)get_option('default_product_cat',0)===$id)$issues[]='default_category';
    if($cat['slug']===$source_slug)$issues[]='same_slug_as_canonical';
    if(trim($cat['description'])!=='')$issues[]='has_description';
    $meta=get_term_meta($id);
    if(!is_array($meta))$meta=[];
    foreach($meta as $mkey=>$values){
        if(in_array($mkey,$allowed_meta,true))continue;
        $nonempty=array_filter((array)$values,static fn($v)=>!($v==='' || $v===null || $v==='0'));
        if($nonempty)$issues[]='nontrivial_meta:'.$mkey;
    }
    $oldlink=get_term_link($id,'product_cat');
    if(is_wp_error($oldlink))$issues[]='invalid_category_url';
    $oldpath=is_wp_error($oldlink)?null:wp_parse_url($oldlink,PHP_URL_PATH);
    if(!$oldpath || !str_starts_with($oldpath,'/categoria-produto/'))$issues[]='unexpected_category_permalink';
    if($oldpath===$keep_path)$issues[]='same_url_as_canonical';
    if(isset($redirects[$oldpath]) && $redirects[$oldpath]!==$keep_path)$issues[]='previous_redirect_conflict';
    $rel=$wpdb->get_col($wpdb->prepare(
      "SELECT object_id FROM {$wpdb->term_relationships} WHERE term_taxonomy_id=%d",
      $cat['term_taxonomy_id']));
    if($wpdb->last_error)throw new RuntimeException('SQL relationship read error.');
    $posts=[];
    foreach($rel as $post_id){
        $post=get_post((int)$post_id);
        if(!$post || $post->post_type!=='product') {$issues[]='non_product_relationship';break;}
        $attached=wp_get_post_terms((int)$post_id,'product_cat',['fields'=>'ids']);
        if(is_wp_error($attached)) {$issues[]='unreadable_product_categories';break;}
        $posts[]=['id'=>(int)$post_id,'status'=>$post->post_status,'before_category_ids'=>array_map('intval',$attached)];
    }
    $snapshots[$id]=['term'=>$cat,'meta'=>$meta,'old_url'=>$oldlink,'old_path'=>$oldpath,'relationships'=>$posts];
    if($issues)$skipped[]=['id'=>$id,'issues'=>$issues,'associated_objects'=>count($rel)];
    else$eligible[]=['id'=>$id,'old_path'=>$oldpath,'products'=>count($posts)];
}
usort($eligible,static fn($a,$b)=>($b['products']<=>$a['products'])?:($a['id']<=>$b['id']));
$plan=['timestamp_utc'=>gmdate('c'),'mode'=>$mode,'backup_categories'=>count($backup),
  'local_categories'=>count($locals),'backup_source_id'=>$source_id,'category_key'=>$key,
  'canonical_id'=>$canonical_id,'canonical_slug'=>$source_slug,'canonical_path'=>$keep_path,
  'total_duplicates'=>count($dup),'eligible'=>count($eligible),'skipped'=>count($skipped),
  'limit'=>$limit,'will_process'=>array_slice($eligible,0,$limit),
  'skipped_details'=>$skipped,'source'=>$backup[$source_id]];
$plan_json=wp_json_encode($plan,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
if(file_put_contents($dir.'/plan.json',$plan_json)===false)throw new RuntimeException('Cannot write plan.');
echo 'CV_MERGE_PLAN '.wp_json_encode([
 'backup_count'=>count($backup),'loja_count'=>count($locals),'duplicates'=>count($dup),
 'eligible'=>count($eligible),'skipped'=>count($skipped),'batch_count'=>min($limit,count($eligible))
],JSON_UNESCAPED_UNICODE).PHP_EOL;
foreach(array_slice($skipped,0,8) as $x)echo 'CV_MERGE_SKIPPED '.wp_json_encode($x,JSON_UNESCAPED_UNICODE).PHP_EOL;
if($mode==='plan')return;
$work=array_slice($eligible,0,$limit);
if(!$work)throw new RuntimeException('Nothing eligible to merge.');
$backup_file=$dir.'/snapshot-before.json';
$backup_payload=['timestamp_utc'=>gmdate('c'),'plan'=>$plan,'canonical'=>$locals[$canonical_id],
   'canonical_meta'=>get_term_meta($canonical_id),
   'canonical_relationships'=>$wpdb->get_col($wpdb->prepare(
      "SELECT object_id FROM {$wpdb->term_relationships} WHERE term_taxonomy_id=%d",
      $locals[$canonical_id]['term_taxonomy_id'])),
   'original_redirects'=>$redirects,'duplicates'=>array_intersect_key($snapshots,array_flip(array_column($work,'id')))];
if(file_put_contents($backup_file,wp_json_encode($backup_payload,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE))===false
   || !filesize($backup_file))throw new RuntimeException('Cannot persist pre-change snapshot.');
$done=[];$failures=[];
foreach($work as $entry){
    $id=(int)$entry['id'];
    $current=get_term($id,'product_cat');
    if(!$current || is_wp_error($current)
       || $current->slug!==$locals[$id]['slug']
       || (int)$current->parent!==$locals[$id]['parent']
       || $tree($id,$locals)!==$key
       || get_term_children($id,'product_cat')) {
        $failures[]=['id'=>$id,'reason'=>'category_changed_since_preflight'];
        break;
    }
    $source_data=$snapshots[$id];
    $object_ids=array_column($source_data['relationships'],'id');
    try {
        foreach($object_ids as $post_id){
            $now_ids=wp_get_post_terms($post_id,'product_cat',['fields'=>'ids']);
            if(is_wp_error($now_ids) || !in_array($id,array_map('intval',$now_ids),true))
                throw new RuntimeException('Post changed during merge: '.$post_id);
            $up=wp_set_object_terms($post_id,[$canonical_id],'product_cat',true);
            if(is_wp_error($up)) throw new RuntimeException('Cannot attach canonical term to product '.$post_id);
            $verify=wp_get_post_terms($post_id,'product_cat',['fields'=>'ids']);
            if(is_wp_error($verify) || !in_array($canonical_id,array_map('intval',$verify),true))
                throw new RuntimeException('Product canonical association not saved: '.$post_id);
        }
        $delete=wp_delete_term($id,'product_cat');
        if($delete!==true)throw new RuntimeException('wp_delete_term returned failure.');
        if(term_exists($id,'product_cat'))throw new RuntimeException('Deleted term still exists.');
        foreach($object_ids as $post_id){
            $verify=wp_get_post_terms($post_id,'product_cat',['fields'=>'ids']);
            if(is_wp_error($verify) || !in_array($canonical_id,array_map('intval',$verify),true))
                throw new RuntimeException('Post lost canonical term: '.$post_id);
        }
        $redirects[$entry['old_path']]=$keep_path;
        if(!update_option('cv_core_category_slug_redirects',$redirects,false) &&
          get_option('cv_core_category_slug_redirects',[])!==$redirects)
            throw new RuntimeException('301 redirect persistence failed.');
        $item=['deleted_id'=>$id,'canonical_id'=>$canonical_id,'posts_preserved'=>count($object_ids),
          'old_url'=>$source_data['old_url'],'redirect_to'=>$keep_path,'when_utc'=>gmdate('c')];
        if(file_put_contents($dir.'/journal.ndjson',wp_json_encode($item,JSON_UNESCAPED_UNICODE)."\n",FILE_APPEND|LOCK_EX)===false)
            throw new RuntimeException('Journal write failure.');
        $done[]=$item;
        echo 'CV_MERGE_DONE '.wp_json_encode(['deleted_id'=>$id,'posts_preserved'=>count($object_ids)],JSON_UNESCAPED_UNICODE).PHP_EOL;
    }catch(Throwable $e){
        $failures[]=['id'=>$id,'reason'=>$e->getMessage()];
        break;
    }
}
$results=['deleted'=>count($done),'preserved_product_relationships'=>array_sum(array_column($done,'posts_preserved')),
   'errors'=>$failures,'remaining_in_original_inventory'=>count($dup)-count($done),
   'snapshot'=>$backup_file,'updated_utc'=>gmdate('c')];
file_put_contents($dir.'/result.json',wp_json_encode($results,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo 'CV_MERGE_RESULT '.wp_json_encode([
  'deleted'=>$results['deleted'],'product_associations_preserved'=>$results['preserved_product_relationships'],
  'errors'=>$failures,'remaining'=>$results['remaining_in_original_inventory']],JSON_UNESCAPED_UNICODE).PHP_EOL;
if($failures)throw new RuntimeException('Partial merge: see snapshot and journal. Do not retry before review.');
