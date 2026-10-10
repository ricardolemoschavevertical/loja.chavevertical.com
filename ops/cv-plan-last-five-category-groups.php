<?php
/**
 * Remaining five ambiguous homonym groups, after the safe 926 -> 901 realignment.
 * PLAN ONLY. The five chosen keepers are explicit and must match live taxonomy
 * AND the historical WooCommerce backup. Never mutates products or terms.
 */
defined('ABSPATH') || exit;
if ((getenv('CV_LAST_CATEGORY_MODE') ?: 'plan') !== 'plan') {
    throw new RuntimeException('Só existe modo de leitura para os cinco grupos ambíguos.');
}
$dir=getenv('CV_LAST_CATEGORY_OUTPUT_DIR') ?: sys_get_temp_dir().'/cv-category-last-five';
if (!is_dir($dir) && !wp_mkdir_p($dir)) throw new RuntimeException('Diretório de auditoria indisponível.');
$expected=[
 ['backup_id'=>188,'keep'=>64,'others'=>[495]],
 ['backup_id'=>178,'keep'=>185,'others'=>[499]],
 ['backup_id'=>1436,'keep'=>246,'others'=>[501]],
 ['backup_id'=>132,'keep'=>324,'others'=>[504]],
 ['backup_id'=>1553,'keep'=>144,'others'=>[3634,23118]]
];
$terms=get_terms(['taxonomy'=>'product_cat','hide_empty'=>false]);
if (is_wp_error($terms))throw new RuntimeException($terms->get_error_message());
if (count($terms)!==901)throw new RuntimeException('A árvore atual ainda não tem 901 categorias. Não avaliar fusões finais.');
$map=[];
foreach($terms as $term) $map[(int)$term->term_id]=$term;
$normalize=static function($s) {
    return sanitize_title(html_entity_decode(wp_strip_all_tags((string)$s), ENT_QUOTES|ENT_HTML5, 'UTF-8'));
};
$getPath=static function($id,$lookup)use($normalize) {
    $seen=[];$parts=[];$cursor=(int)$id;
    while($cursor) {
        if(!isset($lookup[$cursor]) || isset($seen[$cursor]) || count($seen)>25)return null;
        $seen[$cursor]=true;
        $t=$lookup[$cursor];
        $parts[]=$normalize(is_object($t)?$t->name:$t['name']);
        $cursor=(int)(is_object($t)?$t->parent:$t['parent']);
    }
    return implode('>',array_reverse($parts));
};
$source=[];$page=1;$pages=0;
do {
    $url=add_query_arg(['per_page'=>100,'page'=>$page,'orderby'=>'id','order'=>'asc','hide_empty'=>'false',
       '_fields'=>'id,name,slug,parent,count,link'], 'https://backup.chavevertical.com/wp-json/wp/v2/product_cat');
    $response=wp_remote_get($url,['timeout'=>30,'redirection'=>2,'sslverify'=>true,'headers'=>['Accept'=>'application/json']]);
    if(is_wp_error($response)||wp_remote_retrieve_response_code($response)!==200)
        throw new RuntimeException('Backup indisponível no inventário final');
    $rows=json_decode(wp_remote_retrieve_body($response),true);
    if(!is_array($rows))throw new RuntimeException('Resposta de backup inválida');
    foreach($rows as $r) {
        $id=(int)$r['id'];
        if(!$id || isset($source[$id]))throw new RuntimeException('ID de referência inválido/repetido');
        $source[$id]=['id'=>$id,'name'=>$r['name'],'slug'=>$r['slug'],'parent'=>(int)$r['parent'],'url'=>$r['link']??''];
    }
    if(!$pages)$pages=(int)wp_remote_retrieve_header($response,'x-wp-totalpages');
    $page++;
    if($page>50)throw new RuntimeException('Muitas páginas na referência');
}while($pages?$page<=$pages:count($rows)===100);
if(count($source)!==894)throw new RuntimeException('O backup deixou de corresponder a 894 categorias');
$lists=[];$conflicts=[];$overlap=[];
foreach($expected as $g) {
    $ref=$source[$g['backup_id']]??null;
    if(!$ref)throw new RuntimeException('Categoria de backup ausente '.$g['backup_id']);
    $refPath=$getPath($g['backup_id'],$source);
    $all=array_merge([$g['keep']],$g['others']);
    $path=null;$records=[];
    foreach($all as $id) {
        if(isset($overlap[$id]))throw new RuntimeException('ID partilhado entre grupos '.$id);
        $overlap[$id]=true;
        $term=$map[$id]??null;
        if(!$term)throw new RuntimeException('ID alvo desapareceu: '.$id);
        $key=$getPath($id,$map);
        if($path===null)$path=$key;
        if(!$key || $key!==$path || $key!==$refPath)
            throw new RuntimeException('Percurso de categorias não corresponde ao backup '.$id);
        $children=array_values(array_map('intval',get_terms([
            'taxonomy'=>'product_cat','parent'=>$id,'hide_empty'=>false,'fields'=>'ids'
        ])));
        $termMeta=get_term_meta($id);
        if(!is_array($termMeta))$termMeta=[];
        $link=get_term_link($term);
        $records[]=[
           'id'=>$id,'name'=>$term->name,'slug'=>$term->slug,'parent'=>(int)$term->parent,
           'count_published'=>(int)$term->count,'children_ids'=>$children,
           'thumbnail_id'=>(int)get_term_meta($id,'thumbnail_id',true),
           'description_has_content'=>trim($term->description)!=='',
           'meta_keys'=>array_keys($termMeta),
           'url'=>is_wp_error($link)?null:$link
        ];
    }
    $keeper=$records[0];
    $desired=(string)$ref['slug'];
    $owners=get_terms(['taxonomy'=>'product_cat','slug'=>$desired,'hide_empty'=>false,'fields'=>'ids']);
    if(is_wp_error($owners))throw new RuntimeException('Consulta de slugs falhou');
    $outside=array_values(array_diff(array_map('intval',$owners),$all));
    if($outside)$conflicts[]=['group'=>$refPath,'reason'=>'desired_slug_taken_outside_group','id'=>$outside];
    $otherImages=array_values(array_unique(array_filter(array_map(static function($r)use($keeper) {
       return $r['thumbnail_id']!==$keeper['thumbnail_id']?$r['thumbnail_id']:0;
    },array_slice($records,1)))));
    $lists[]=[
      'backup_id'=>$g['backup_id'],'backup_path'=>$refPath,'backup_slug'=>$desired,
      'keep'=>$g['keep'],'remove'=>$g['others'],'all'=>$records,
      'number_of_removals'=>count($g['others']),
      'extra_images_to_preserve_as_metadata'=>$otherImages,
      'new_slug_required'=>$keeper['slug']!==$desired,
      'is_candidate'=>!$outside
    ];
}
$summary=['source'=>'WooCommerce live & backup','generated_utc'=>gmdate('c'),
 'backup_total'=>count($source),'loja_total'=>count($map),
 'groups'=>count($lists),'potential_duplicates_to_merge'=>array_sum(array_column($lists,'number_of_removals')),
 'protected_system_categories'=>[15,2248],
 'conflict_count'=>count($conflicts),'conflicts'=>$conflicts,
 'would_be_count_excluding_system_terms'=>count($map)-array_sum(array_column($lists,'number_of_removals'))-2];
file_put_contents($dir.'/five-groups-plan.json',
    wp_json_encode(['summary'=>$summary,'groups'=>$lists],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo 'CV_LAST_FIVE_PLAN '.wp_json_encode($summary,JSON_UNESCAPED_UNICODE).PHP_EOL;
foreach($lists as $g)echo 'CV_LAST_FIVE_GROUP '.wp_json_encode([
  'path'=>$g['backup_path'],'backup_slug'=>$g['backup_slug'],'keep'=>$g['keep'],
  'remove'=>$g['remove'],'preserved_images'=>$g['extra_images_to_preserve_as_metadata']
],JSON_UNESCAPED_UNICODE).PHP_EOL;
