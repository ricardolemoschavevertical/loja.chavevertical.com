<?php
/**
 * Export both live category trees without writing to WordPress.
 * Run: CV_TAXONOMY_AUDIT_DIR=/tmp/... wp eval-file ops/cv-audit-backup-taxonomy.php
 */
defined('ABSPATH') || exit;
if (!taxonomy_exists('product_cat')) { throw new RuntimeException('Missing product_cat'); }
$base = 'https://backup.chavevertical.com/wp-json/wp/v2/product_cat';
$decode = static fn($x) => html_entity_decode(wp_strip_all_tags((string)$x), ENT_QUOTES | ENT_HTML5, 'UTF-8');
$source = []; $page = 1; $pages = 0;
do {
    $url = add_query_arg(['per_page'=>100,'page'=>$page,'orderby'=>'id','order'=>'asc',
        '_fields'=>'id,name,slug,parent,count,description,link','hide_empty'=>'false'],$base);
    $r=wp_remote_get($url,['timeout'=>35,'redirection'=>2,'sslverify'=>true,
       'headers'=>['Accept'=>'application/json'],'user-agent'=>'ChaveVertical-TaxonomyAudit/1.0']);
    if (is_wp_error($r) || wp_remote_retrieve_response_code($r)!==200) {
        throw new RuntimeException('Failed to load all backup categories, page '.$page);
    }
    $rows=json_decode(wp_remote_retrieve_body($r),true);
    if (!is_array($rows)) throw new RuntimeException('Invalid backup response');
    foreach($rows as $row) {
        $id=(int)$row['id'];
        if (!$id || isset($source[$id])) throw new RuntimeException('Bad backup ID');
        $source[$id]=[
          'id'=>$id,'name'=>$decode($row['name']),'slug'=>(string)$row['slug'],
          'parent'=>(int)$row['parent'],'count'=>(int)($row['count']??0),
          'description'=>(string)($row['description']??''),'url'=>(string)($row['link']??''),
          'image_id'=>0
        ];
    }
    if (!$pages) {$pages=(int)wp_remote_retrieve_header($r,'x-wp-totalpages');}
    $page++;
} while ($page<=min(50,$pages ?: 50) && count($rows)===100);
if (count($source)<500) throw new RuntimeException('Backup taxonomy not fully available');

$terms=get_terms(['taxonomy'=>'product_cat','hide_empty'=>false,'orderby'=>'term_id','order'=>'ASC']);
if (is_wp_error($terms)) throw new RuntimeException('Woo taxonomy fetch failed');
$target=[];
foreach($terms as $t) {
   $id=(int)$t->term_id;
   $link=get_term_link($t);if (is_wp_error($link)) $link='';
   $target[$id]=[
     'id'=>$id,'name'=>$decode($t->name),'slug'=>(string)$t->slug,
     'parent'=>(int)$t->parent,'count'=>(int)$t->count,
     'description'=>(string)$t->description,'url'=>(string)$link,
     'image_id'=>(int)get_term_meta($id,'thumbnail_id',true),
     'ttid'=>(int)$t->term_taxonomy_id
   ];
}
global $wpdb;
$q=$wpdb->get_results(
  "SELECT tt.term_id AS id, p.post_status AS state, COUNT(DISTINCT p.ID) AS n
   FROM {$wpdb->term_taxonomy} tt
   JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id=tt.term_taxonomy_id
   JOIN {$wpdb->posts} p ON p.ID=tr.object_id
   WHERE tt.taxonomy='product_cat' AND p.post_type IN ('product')
   GROUP BY tt.term_id,p.post_status",ARRAY_A);
if ($wpdb->last_error) throw new RuntimeException('SQL counting error: '.$wpdb->last_error);
foreach ($target as &$row) $row['product_status_counts']=[];
unset($row);
foreach ($q as $row) if (isset($target[(int)$row['id']]))
   $target[(int)$row['id']]['product_status_counts'][(string)$row['state']]=(int)$row['n'];

$getPath=static function($id,$map) {
  $parts=[];$slugs=[];$seen=[];$cursor=(int)$id;
  while ($cursor) {
    if (isset($seen[$cursor]) || !isset($map[$cursor]) || count($seen)>30) return null;
    $seen[$cursor]=true;
    $parts[]=sanitize_title($map[$cursor]['name']);
    $slugs[]=$map[$cursor]['slug'];
    $cursor=$map[$cursor]['parent'];
  }
  return ['name_key'=>implode('>',array_reverse($parts)),
          'slug_key'=>implode('/',array_reverse($slugs))];
};
$sourceGroups=[];$targetGroups=[];$sBySlug=[];
foreach($source as $id=>&$row) {
    $p=$getPath($id,$source);$row['name_key']=$p['name_key']??null;$row['slug_path']=$p['slug_key']??null;
    if ($p) { $sourceGroups[$p['name_key']][]=$id;$sBySlug[$row['slug']][]=$id;}
}
unset($row);
foreach($target as $id=>&$row) {
    $p=$getPath($id,$target);$row['name_key']=$p['name_key']??null;$row['slug_path']=$p['slug_key']??null;
    if ($p) $targetGroups[$p['name_key']][]=$id;
}
unset($row);
$dups=[];$unmatched=[];$missing=[];$confidence=[];
foreach($targetGroups as $key=>$ids) {
    $s=$sourceGroups[$key]??[];
    if (!$s) {
      foreach ($ids as $id) $unmatched[]=$id;
    }
    if (count($ids)>1) {
      $chosen=null;
      if(count($s)===1) {
          $matching=array_values(array_filter($ids,static fn($id)=>$target[$id]['slug']===$source[$s[0]]['slug']));
          if(count($matching)===1)$chosen=$matching[0];
      }
      $dups[]=[
        'name_key'=>$key,'source_ids'=>$s,'target_ids'=>$ids,
        'target_count'=>count($ids),'backup_count'=>count($s),'exact_backup_slug_id'=>$chosen,
        'candidate_auto_merge'=count($s)===1 && $chosen!==null,
        'target_product_associations'=>array_sum(array_map(static fn($id)=>array_sum($target[$id]['product_status_counts']),$ids)),
        'target_children'=>array_sum(array_map(static function($id)use($target) {
             $n=0;foreach($target as $t)if($t['parent']===$id)$n++;return $n;
        },$ids))
      ];
    }
}
foreach($sourceGroups as $key=>$ids)if(!isset($targetGroups[$key]))foreach($ids as $id)$missing[]=$id;
usort($dups,static fn($a,$b)=>($b['target_count']<=>$a['target_count']));
$stats=[
 'backup_total'=>count($source),'loja_total'=>count($target),
 'total_target_repeated_name_paths'=>count($dups),
 'repeated_groups_source_single'=>count(array_filter($dups,static fn($x)=>$x['backup_count']===1)),
 'auto_merge_candidate_groups'=>count(array_filter($dups,static fn($x)=>$x['candidate_auto_merge'])),
 'auto_merge_candidate_excess'=>array_sum(array_map(static fn($x)=>$x['candidate_auto_merge']?$x['target_count']-1:0,$dups)),
 'unmatched_target_rows'=>count($unmatched),'missing_target_rows'=>count($missing),
 'creation_utc'=>gmdate('c'),
];
$dir=getenv('CV_TAXONOMY_AUDIT_DIR')?:sys_get_temp_dir().'/cv-taxonomy-compare';
if (!is_dir($dir)&&!wp_mkdir_p($dir)) throw new RuntimeException('Cannot create output directory');
$data=['stats'=>$stats,'backup'=>array_values($source),'loja'=>array_values($target),
       'duplicates'=>$dups,'unmatched_target_ids'=>$unmatched,'missing_target_ids'=>$missing];
file_put_contents($dir.'/cv-category-full-inventory.json',wp_json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
file_put_contents($dir.'/cv-category-merge-candidates.json',wp_json_encode([
    'stats'=>$stats,'duplicates'=>$dups],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo 'CV_CAT_FULL_AUDIT '.wp_json_encode($stats,JSON_UNESCAPED_UNICODE).PHP_EOL;
foreach (array_slice($dups,0,36) as $d) {
    echo 'CV_CAT_GROUP '.wp_json_encode($d,JSON_UNESCAPED_UNICODE).PHP_EOL;
}
