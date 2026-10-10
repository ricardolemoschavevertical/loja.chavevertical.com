<?php
/**
 * Final reconciliation of the five ambiguous duplicate category groups.
 * Only operates after phase 2 has left exactly 901 categories.
 * Protected terms 15 ("Uncategorized") and 2248 ("Predefinido") are never removed.
 * With six duplicates gone, 893 commercial category paths must match the
 * 893 unique paths of the 894-entry historical backup.
 *
 * Default mode is read-only. Apply requires SQL backup and explicit environment
 * confirmation. Does not edit pricing, stock, product statuses, or product names.
 */
defined('ABSPATH') || exit;
if (!defined('WP_CLI') || !WP_CLI) throw new RuntimeException('Use WP-CLI.');
$mode = getenv('CV_FINAL_MERGE_MODE') ?: 'plan';
if (!in_array($mode,['plan','apply'],true)) throw new RuntimeException('Modo inválido.');
$dir = getenv('CV_FINAL_MERGE_DIR') ?: sys_get_temp_dir().'/cv-final-category-sync';
if (!is_dir($dir) && !wp_mkdir_p($dir)) throw new RuntimeException('Pasta de auditoria indisponível.');
global $wpdb;

$protected = [15,2248];
$groups = [
 ['reference'=>188, 'keeper'=>64,  'remove'=>[495]],             // Oficina Automóvel / Aquecedores
 ['reference'=>178, 'keeper'=>185, 'remove'=>[499]],             // Ferramentas Pneumáticas / Enroladores
 ['reference'=>1436,'keeper'=>246, 'remove'=>[501]],             // Máquinas Indústria Metal / Fresadoras
 ['reference'=>132, 'keeper'=>324, 'remove'=>[504]],             // Máquinas Indústria Metal / Combinadas
 ['reference'=>1553,'keeper'=>144, 'remove'=>[3634,23118]]       // Construção Civil / Rebitadoras / Consumíveis
];
// Whitelisted target term ID => legacy reference term ID.
// The temporary rename of 23106 resolves the one true two-way slug swap.
$auxiliary = [
 23451=>16106, // Ambiente > Aquecedores
 23106=>1123,  // Ar Comprimido > Enroladores
 23070=>4487,  // Carpintaria > Máquinas Combinadas
 23093=>125,   // Fresadoras > Fresadoras
 23121=>112,   // Outros > Consumíveis
 23680=>1958   // Bateria / Alternador > Multímetros
];

$source=[];$page=1;$total=0;
do {
    $url=add_query_arg(['per_page'=>100,'page'=>$page,'orderby'=>'id','order'=>'asc','hide_empty'=>'false',
        '_fields'=>'id,name,slug,parent,link'], 'https://backup.chavevertical.com/wp-json/wp/v2/product_cat');
    $res=wp_remote_get($url,['timeout'=>30,'redirection'=>2,'sslverify'=>true,'headers'=>['Accept'=>'application/json']]);
    if (is_wp_error($res) || wp_remote_retrieve_response_code($res)!==200)
        throw new RuntimeException('Backup indisponível na página '.$page);
    $data=json_decode(wp_remote_retrieve_body($res),true);
    if (!is_array($data)) throw new RuntimeException('Categorias de backup inválidas.');
    foreach($data as $x) {
        $id=(int)$x['id'];
        if (!$id || isset($source[$id])) throw new RuntimeException('ID de backup duplicado.');
        $source[$id]=['id'=>$id,'name'=>html_entity_decode(wp_strip_all_tags($x['name']),ENT_QUOTES|ENT_HTML5,'UTF-8'),
            'slug'=>(string)$x['slug'],'parent'=>(int)$x['parent'],'url'=>(string)($x['link']??'')];
    }
    if(!$total)$total=(int)wp_remote_retrieve_header($res,'x-wp-totalpages');
    $page++;
    if($page>50)throw new RuntimeException('Excesso de páginas de categorias no backup');
} while($total ? $page<=$total : count($data)===100);
if (count($source)!==894) throw new RuntimeException('Backup alterou-se; execução bloqueada.');

$terms=get_terms(['taxonomy'=>'product_cat','hide_empty'=>false,'orderby'=>'term_id','order'=>'ASC']);
if(is_wp_error($terms)||count($terms)!==901) throw new RuntimeException('Loja não tem as 901 categorias esperadas.');
$target=[];$oldLinks=[];
foreach($terms as $term) {
    $id=(int)$term->term_id;
    $target[$id]=$term;
    $link=get_term_link($term);
    if (is_wp_error($link))throw new RuntimeException('URL de categoria inválido '.$id);
    $oldLinks[$id]=$link;
}
foreach($protected as $id) if(!isset($target[$id])) throw new RuntimeException('Categoria de sistema desapareceu.');

$normalized = static function($x) {
    return sanitize_title(html_entity_decode(wp_strip_all_tags((string)$x),ENT_QUOTES|ENT_HTML5,'UTF-8'));
};
$pathKey = static function($id,$index)use($normalized) {
    $parts=[];$seen=[];$cursor=(int)$id;
    while($cursor) {
        if(isset($seen[$cursor]) || !isset($index[$cursor]) || count($seen)>25) return null;
        $seen[$cursor]=true;
        $row=$index[$cursor];
        $parts[]=$normalized(is_object($row)?$row->name:$row['name']);
        $cursor=(int)(is_object($row)?$row->parent:$row['parent']);
    }
    return implode('>',array_reverse($parts));
};
$planned=[];$scheduledIDs=[];$toDelete=[];
foreach($groups as $g) {
    $ref=$source[$g['reference']]??null;
    if(!$ref)throw new RuntimeException('Categoria de referência não existe.');
    $desired=$ref['slug'];$referencePath=$pathKey($g['reference'],$source);
    $ids=array_merge([$g['keeper']],$g['remove']);
    $records=[];$images=[];
    foreach($ids as $id) {
        if(in_array($id,$protected,true) || isset($scheduledIDs[$id])) throw new RuntimeException('ID protegido/repetido '.$id);
        $scheduledIDs[$id]=true;
        if(!isset($target[$id]) || $pathKey($id,$target)!==$referencePath)
            throw new RuntimeException('Caminho errado no grupo '.$id);
        $x=$target[$id];
        $image=(int)get_term_meta($id,'thumbnail_id',true);
        if($image)$images[]=$image;
        $children=get_terms(['taxonomy'=>'product_cat','parent'=>$id,'hide_empty'=>false,'fields'=>'ids']);
        if(is_wp_error($children))throw new RuntimeException('Erro a consultar subcategorias '.$id);
        $records[]=['id'=>$id,'slug'=>$x->slug,'count'=>(int)$x->count,'children_ids'=>array_map('intval',$children),
            'thumbnail_id'=>$image,'has_description'=>trim($x->description)!==''];
    }
    foreach($g['remove'] as $id)$toDelete[$id]=$g['keeper'];
    $planned[]=['reference_id'=>$g['reference'],'path'=>$referencePath,'desired_slug'=>$desired,
      'keep'=>$g['keeper'],'remove'=>$g['remove'],'records'=>$records,
      'alternate_thumbnail_ids'=>array_values(array_diff(array_unique($images),[(int)get_term_meta($g['keeper'],'thumbnail_id',true)]))];
}
if(count($planned)!==5||count($toDelete)!==6)throw new RuntimeException('Grupo final inesperado.');
foreach($auxiliary as $id=>$src) {
    if(!isset($target[$id],$source[$src]) || $pathKey($id,$target)!==$pathKey($src,$source))
        throw new RuntimeException('Categoria auxiliar difere do backup '.$id);
}
$desiredSlugs=[];
foreach($planned as $p)$desiredSlugs[$p['keep']]=$p['desired_slug'];
foreach($auxiliary as $id=>$sid)$desiredSlugs[$id]=$source[$sid]['slug'];
foreach($desiredSlugs as $id=>$slug) {
    if(!$slug || sanitize_title($slug)!==$slug)throw new RuntimeException('Slug histórico inválido '.$id);
    $owners=get_terms(['taxonomy'=>'product_cat','slug'=>$slug,'hide_empty'=>false,'fields'=>'ids']);
    if(is_wp_error($owners))throw new RuntimeException('Falhou verificação de slug '.$slug);
    foreach($owners as $owner) {
        $owner=(int)$owner;
        if($owner===$id)continue;
        // Owners must be removed or renamed by the *same* atomic plan.
        if(!isset($toDelete[$owner]) && !isset($desiredSlugs[$owner]))
            throw new RuntimeException('Slug pretendido ocupado por termo fora do plano: '.$slug.' ('.$owner.')');
        if(isset($desiredSlugs[$owner]) && $desiredSlugs[$owner]===$slug)
            throw new RuntimeException('Dois termos planeados com o mesmo slug: '.$slug);
    }
}
$usedSlugs=[];
foreach($desiredSlugs as $id=>$slug) {
    if(isset($usedSlugs[$slug]))throw new RuntimeException('Novo slug duplicado entre termos alvo '.$slug);
    $usedSlugs[$slug]=$id;
}
$plan=[
    'mode'=>$mode,'generated_utc'=>gmdate('c'),'backup_categories'=>894,'loja_before'=>901,
    'groups'=>$planned,'auxiliary_renames'=>$desiredSlugs,
    'category_ids_to_delete'=>array_keys($toDelete),'protected_term_ids'=>$protected,
    'expected_final_total'=>895,'expected_commercial_unique_paths'=>893,
    'total_alternate_images_to_preserve'=>array_sum(array_map(static fn($g)=>count($g['alternate_thumbnail_ids']),$planned))
];
if(file_put_contents($dir.'/plan.json',wp_json_encode($plan,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE))===false)
    throw new RuntimeException('Falha a guardar plano.');
echo 'CV_FINAL_CATEGORY_PLAN '.wp_json_encode([
 'mode'=>$mode,'groups'=>5,'remove_count'=>6,'before'=>901,'after'=>895,
 'rename_count'=>count($desiredSlugs),'alternate_images'=>$plan['total_alternate_images_to_preserve']
],JSON_UNESCAPED_UNICODE).PHP_EOL;
if($mode==='plan')return;

// Apply requires a verified SQL taxonomy dump and an independent GitHub approval gate.
$sql=getenv('CV_FINAL_MERGE_SQL_BACKUP');
if(getenv('CV_FINAL_MERGE_CONFIRM')!=='CV_APLICAR_5_GRUPOS_AUDITADOS'
   || !$sql || !is_file($sql) || filesize($sql)<10000
   || !class_exists('CV_Core_Catalog_Rules')
   || !method_exists('CV_Core_Catalog_Rules','redirect_old_category_urls')
   || has_action('template_redirect',['CV_Core_Catalog_Rules','redirect_old_category_urls'])===false) {
    throw new RuntimeException('Confirmação, backup ou redirecionamentos em falta.');
}
$lock='cv_category_merge_operation_lock';
if(get_option($lock,false))throw new RuntimeException('Outra migração de categorias em curso.');
if(!add_option($lock,['type'=>'final-five','time'=>gmdate('c')],'',false))
    throw new RuntimeException('Não foi possível bloquear operações concorrentes.');
$originalRedirects=get_option('cv_core_category_slug_redirects',[]);
if(!is_array($originalRedirects))throw new RuntimeException('Estado de redirecionamentos inválido.');
$affectedTerms=array_keys($scheduledIDs);
foreach(array_keys($auxiliary) as $id)$affectedTerms[]=$id;
$affectedTerms=array_values(array_unique($affectedTerms));
$snapshot=['plan'=>$plan,'old_links'=>$oldLinks,'old_redirects'=>$originalRedirects,
    'terms'=>[],'associated_products'=>[],'timestamp_utc'=>gmdate('c')];
foreach($affectedTerms as $id) {
    $t=$target[$id];$meta=get_term_meta($id);
    $snapshot['terms'][$id]=['id'=>$id,'name'=>$t->name,'slug'=>$t->slug,'parent'=>$t->parent,
        'description'=>$t->description,'meta'=>$meta];
}
foreach(array_keys($toDelete) as $old) {
    $t=$target[$old];
    $rows=$wpdb->get_results($wpdb->prepare(
      "SELECT p.ID,p.post_type,p.post_status FROM {$wpdb->term_relationships} tr
       JOIN {$wpdb->posts} p ON p.ID=tr.object_id
       WHERE tr.term_taxonomy_id=%d",(int)$t->term_taxonomy_id),ARRAY_A);
    if($wpdb->last_error)throw new RuntimeException('Falha SQL a ler associações '.$old);
    foreach($rows as $post) {
        if($post['post_type']!=='product')throw new RuntimeException('Categoria com associação a objeto não-produto '.$old);
        $pid=(int)$post['ID'];
        $all=wp_get_object_terms($pid,'product_cat',['fields'=>'ids']);
        if(is_wp_error($all))throw new RuntimeException('Falha ao ler categorias do produto '.$pid);
        $snapshot['associated_products'][$pid]=[
          'category_ids'=>array_values(array_map('intval',$all)),
          'rank_math_primary_product_cat'=>get_post_meta($pid,'rank_math_primary_product_cat',true),
          '_yoast_wpseo_primary_product_cat'=>get_post_meta($pid,'_yoast_wpseo_primary_product_cat',true)
        ];
    }
}
if(file_put_contents($dir.'/snapshot-before.json',
   wp_json_encode($snapshot,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE))===false)
    throw new RuntimeException('Falha a preservar estado antes de escrever.');
$journal=$dir.'/journal.ndjson';
$log=static function($row)use($journal) {
    if(file_put_contents($journal,wp_json_encode(['timestamp_utc'=>gmdate('c')]+$row,JSON_UNESCAPED_UNICODE)."\n",
       FILE_APPEND|LOCK_EX)===false)throw new RuntimeException('Falha a escrever journal.');
};
$renames=[];$removed=[];$productsMoved=0;$childrenMoved=0;
$setSlug=static function($id,$slug)use(&$renames,$log) {
    $before=get_term($id,'product_cat');
    if(!$before || is_wp_error($before))throw new RuntimeException('Termo inexistente ao alterar slug '.$id);
    if($before->slug===$slug)return;
    $res=wp_update_term($id,'product_cat',['slug'=>$slug]);
    $after=get_term($id,'product_cat');
    if(is_wp_error($res)||!$after||is_wp_error($after)||$after->slug!==$slug)
        throw new RuntimeException('Falha a restaurar slug exato '.$id.' '.$slug);
    $renames[]=['id'=>$id,'from'=>$before->slug,'to'=>$slug];
    $log(['op'=>'rename_slug','id'=>$id,'from'=>$before->slug,'to'=>$slug]);
};
$redirected=0;
try {
    foreach($planned as $group) {
        $keep=(int)$group['keep'];
        $keeper=get_term($keep,'product_cat');
        if(!$keeper||is_wp_error($keeper))throw new RuntimeException('Categoria principal desapareceu '.$keep);
        $archives=get_term_meta($keep,'cv_merged_category_legacy_snapshots',true);
        if(!is_array($archives))$archives=[];
        $altImages=get_term_meta($keep,'cv_merged_category_alternate_images',true);
        if(!is_array($altImages))$altImages=[];
        foreach($group['remove'] as $old) {
            $old=(int)$old;
            $oldTerm=get_term($old,'product_cat');
            if(!$oldTerm||is_wp_error($oldTerm)||$pathKey($old,$target)!==$group['path'])
                throw new RuntimeException('Termo duplicado alterado antes da fusão '.$old);
            $archives[$old]=$snapshot['terms'][$old];
            $photo=(int)get_term_meta($old,'thumbnail_id',true);
            if($photo && $photo!==(int)get_term_meta($keep,'thumbnail_id',true))$altImages[]=$photo;
            if(!$keeper->description && $oldTerm->description) {
                $desc=wp_update_term($keep,'product_cat',['description'=>$oldTerm->description]);
                if(is_wp_error($desc))throw new RuntimeException('Falhou preservar descrição '.$old);
                $keeper=get_term($keep,'product_cat');
            }
            $kids=get_terms(['taxonomy'=>'product_cat','hide_empty'=>false,'parent'=>$old,'fields'=>'ids']);
            if(is_wp_error($kids))throw new RuntimeException('Erro a ler subcategorias do termo '.$old);
            foreach($kids as $child) {
                $child=(int)$child;
                if($child===$keep)throw new RuntimeException('Hierarquia circular no termo '.$old);
                $changed=wp_update_term($child,'product_cat',['parent'=>$keep]);
                $check=get_term($child,'product_cat');
                if(is_wp_error($changed)||!$check||is_wp_error($check)||(int)$check->parent!==$keep)
                    throw new RuntimeException('Falha a mover subcategoria '.$child);
                $childrenMoved++;
                $log(['op'=>'reparent','id'=>$child,'from'=>$old,'to'=>$keep]);
            }
            $rows=$wpdb->get_results($wpdb->prepare(
              "SELECT tr.object_id,p.post_type FROM {$wpdb->term_relationships} tr
               INNER JOIN {$wpdb->posts} p ON p.ID=tr.object_id
               WHERE tr.term_taxonomy_id=%d",(int)$oldTerm->term_taxonomy_id),ARRAY_A);
            if($wpdb->last_error)throw new RuntimeException('Falha SQL a ler produtos da categoria '.$old);
            foreach($rows as $post) {
                if($post['post_type']!=='product')throw new RuntimeException('Objeto não-produto associado '.$old);
                $pid=(int)$post['object_id'];
                $assign=wp_set_object_terms($pid,[$keep],'product_cat',true);
                if(is_wp_error($assign))throw new RuntimeException('Falha a colocar produto na categoria correta '.$pid);
                $unlink=wp_remove_object_terms($pid,[$old],'product_cat');
                if(is_wp_error($unlink)||!$unlink)throw new RuntimeException('Falha a retirar categoria duplicada '.$pid);
                foreach(['rank_math_primary_product_cat','_yoast_wpseo_primary_product_cat'] as $key) {
                    if((int)get_post_meta($pid,$key,true)===$old) {
                        update_post_meta($pid,$key,(string)$keep);
                        if((int)get_post_meta($pid,$key,true)!==$keep)
                            throw new RuntimeException('Falha a preservar categoria SEO principal '.$pid);
                    }
                }
                $ids=wp_get_object_terms($pid,'product_cat',['fields'=>'ids']);
                if(is_wp_error($ids)||!in_array($keep,array_map('intval',$ids),true)
                    || in_array($old,array_map('intval',$ids),true))
                    throw new RuntimeException('Produto não validado após transferência '.$pid);
                $productsMoved++;
                $log(['op'=>'product','id'=>$pid,'from'=>$old,'to'=>$keep]);
            }
            $remaining=(int)$wpdb->get_var($wpdb->prepare(
               "SELECT COUNT(*) FROM {$wpdb->term_relationships} WHERE term_taxonomy_id=%d",
                (int)$oldTerm->term_taxonomy_id));
            $remainingKids=get_terms(['taxonomy'=>'product_cat','hide_empty'=>false,'parent'=>$old,'fields'=>'ids']);
            if($remaining!==0||is_wp_error($remainingKids)||$remainingKids)
                throw new RuntimeException('A categoria ainda tem produtos/filhos '.$old);
            $log(['op'=>'before_delete','old'=>$old,'keeper'=>$keep]);
            $del=wp_delete_term($old,'product_cat');
            if(is_wp_error($del)||!$del||term_exists($old,'product_cat'))
                throw new RuntimeException('Falhou apagar a categoria duplicada '.$old);
            $removed[$old]=$keep;
            $log(['op'=>'delete_duplicate','old'=>$old,'keeper'=>$keep]);
        }
        update_term_meta($keep,'cv_merged_category_legacy_snapshots',$archives);
        $altImages=array_values(array_unique(array_map('intval',$altImages)));
        if($altImages)update_term_meta($keep,'cv_merged_category_alternate_images',$altImages);
        if(get_term_meta($keep,'cv_merged_category_legacy_snapshots',true)!==$archives)
            throw new RuntimeException('Falhou preservar metadados originais do grupo '.$keep);
    }
    if(count($removed)!==6)throw new RuntimeException('Remoções incompletas');

    // Ordenação explícita das renomeações resolve dependências e o único ciclo.
    $setSlug(64,$source[188]['slug']);
    $setSlug(23451,$source[16106]['slug']);
    $temp='cv-migracao-temp-enroladores-23106-20261010';
    $existing=get_terms(['taxonomy'=>'product_cat','slug'=>$temp,'hide_empty'=>false,'fields'=>'ids']);
    if(is_wp_error($existing)||$existing)throw new RuntimeException('Slug temporário já ocupado');
    $setSlug(23106,$temp);
    $setSlug(185,$source[178]['slug']);
    $setSlug(23106,$source[1123]['slug']);
    $setSlug(324,$source[132]['slug']);
    $setSlug(23070,$source[4487]['slug']);
    $setSlug(246,$source[1436]['slug']);
    $setSlug(23093,$source[125]['slug']);
    $setSlug(23121,$source[112]['slug']);
    $setSlug(23680,$source[1958]['slug']);

    $now=get_terms(['taxonomy'=>'product_cat','hide_empty'=>false,'orderby'=>'term_id','order'=>'ASC']);
    if(is_wp_error($now)||count($now)!==895)throw new RuntimeException('Total final de categorias diferente de 895.');
    $after=[];$activePaths=[];
    foreach($now as $term) {
        $id=(int)$term->term_id;
        $after[$id]=$term;
        $link=get_term_link($term);
        if(is_wp_error($link))throw new RuntimeException('URL final em erro '.$id);
        $path=wp_parse_url($link,PHP_URL_PATH);
        if(is_string($path))$activePaths[$path]=$id;
    }
    $wantedKeys=[];
    foreach($source as $id=>$row) {
        $key=$pathKey($id,$source);
        if(!$key)throw new RuntimeException('Backup com pai inexistente');
        $wantedKeys[$key]=true;
    }
    $commercial=[];
    foreach($after as $id=>$term) {
        if(in_array($id,$protected,true))continue;
        $key=$pathKey($id,$after);
        if(!$key||isset($commercial[$key]))throw new RuntimeException('Ainda existe categoria comercial duplicada '.$id);
        $commercial[$key]=$id;
    }
    if(count($wantedKeys)!==893||count($commercial)!==893 ||
       array_diff_key($wantedKeys,$commercial)||array_diff_key($commercial,$wantedKeys))
        throw new RuntimeException('Percursos comerciais ainda não são idênticos ao backup.');
    $srcByKey=[];
    foreach($source as $id=>$row)$srcByKey[$pathKey($id,$source)][]=$row['slug'];
    foreach($commercial as $key=>$id) {
        if(!in_array($after[$id]->slug,$srcByKey[$key],true))
            throw new RuntimeException('Slug comercial divergente da referência '.$id.' '.$key);
    }
    // Single option write: do not issue hundreds of cache-invalidating option writes.
    $redirects=$originalRedirects;
    $asPath=static function($url) {
        if(!is_string($url))return '';
        $path=wp_parse_url($url,PHP_URL_PATH);
        return is_string($path)&&str_starts_with($path,'/categoria-produto/')?$path:'';
    };
    $redirectConflicts=[];
    foreach($oldLinks as $id=>$oldUrl) {
        $newId=$removed[$id]??$id;
        if(!isset($after[$newId]))continue;
        $newUrl=get_term_link($after[$newId]);
        if(is_wp_error($newUrl))throw new RuntimeException('Nova URL inválida '.$newId);
        $oldPath=$asPath($oldUrl);$newPath=$asPath($newUrl);
        if(!$oldPath||!$newPath||$oldPath===$newPath)continue;
        if(isset($activePaths[$oldPath]) && $activePaths[$oldPath]!==$newId) {
            $redirectConflicts[]=['old'=>$oldPath,'dest'=>$newPath,'canonical_id'=>$activePaths[$oldPath]];
            continue;
        }
        $redirects[$oldPath]=$newPath;
        $redirected++;
    }
    if($redirectConflicts)throw new RuntimeException('URLs históricas tornaram-se URLs canónicas de outra categoria.');
    update_option('cv_core_category_slug_redirects',$redirects,false);
    $stored=get_option('cv_core_category_slug_redirects',[]);
    if(!is_array($stored)||$stored!==$redirects)throw new RuntimeException('301 não persistiram.');
    $output=['status'=>'success','before'=>901,'after'=>895,'removed'=>count($removed),
      'groups'=>count($planned),'renames'=>count($renames),'moved_products'=>$productsMoved,
      'reparented_children'=>$childrenMoved,'new_redirects'=>$redirected,
      'redirects_total'=>count($redirects),'commercial_unique_paths'=>count($commercial),
      'matched_backup_unique_paths'=>count($wantedKeys),'system_terms'=>$protected,'finished_utc'=>gmdate('c')];
    file_put_contents($dir.'/result.json',wp_json_encode($output,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    echo 'CV_FINAL_CATEGORY_DONE '.wp_json_encode($output,JSON_UNESCAPED_UNICODE).PHP_EOL;
} catch(Throwable $error) {
    $log(['op'=>'halt','error'=>$error->getMessage(),'removed'=>count($removed),'renamed'=>count($renames)]);
    file_put_contents($dir.'/partial.json',wp_json_encode([
      'status'=>'halt','error'=>$error->getMessage(),'removed'=>$removed,'renamed'=>$renames
    ],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    throw $error;
} finally {
    delete_option($lock);
}
