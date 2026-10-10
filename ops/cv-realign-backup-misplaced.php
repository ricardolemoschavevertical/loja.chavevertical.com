<?php
/**
 * Reatribui categorias historicas colocadas em ramos errados a uma unica categoria
 * correspondente no backup (mesmo slug original e mesmo nome da folha).
 * Categorias sem destino univoco sao apenas movidas se o pai historico for unico.
 * Nao faz nada em modo plan. Guardar SQL antes de aplicar.
 */
defined('ABSPATH') || exit;
$mode=getenv('CV_MISPLACED_MODE')?:'plan';
$path=getenv('CV_MISPLACED_SNAPSHOT');
$dir=getenv('CV_MISPLACED_DIR');
if (!in_array($mode,['plan','apply'],true)||!$path||!is_file($path)||!$dir)throw new RuntimeException('Parametros de auditoria em falta');
if (!is_dir($dir)&&!wp_mkdir_p($dir))throw new RuntimeException('Sem pasta de trabalho');
$snap=json_decode(file_get_contents($path),true);
if (!is_array($snap)||!isset($snap['backup'],$snap['loja'],$snap['unmatched_target_ids']))
    throw new RuntimeException('Snapshot historico incompleto');
$s=[];$t=[];$sbyslug=[];$tbykey=[];
foreach($snap['backup'] as $v) {
    $id=(int)$v['id'];$s[$id]=$v;$sbyslug[$v['slug']][]=$id;
}
foreach($snap['loja'] as $v) {
    $id=(int)$v['id'];$t[$id]=$v;$tbykey[$v['name_key']][]=$id;
}
if(count($s)!==894||count($t)!==926)throw new RuntimeException('Numero de categorias nao coincide com a 1a fusao');
$actions=[];$excluded=[];
foreach($snap['unmatched_target_ids'] as $old) {
    $old=(int)$old;
    if(!isset($t[$old]))throw new RuntimeException('ID antigo desaparecido');
    if($old===(int)get_option('default_product_cat',0) || $t[$old]['name']==='Predefinido') {
        $excluded[]=$old;continue;
    }
    $sourceIds=$sbyslug[$t[$old]['slug']]??[];
    if(count($sourceIds)!==1)throw new RuntimeException('Slug de origem ambiguo: '.$old);
    $source=$s[$sourceIds[0]];
    $last=static fn($str)=>array_slice(explode('>',(string)$str),-1)[0];
    if($last($source['name_key'])!==$last($t[$old]['name_key']))
        throw new RuntimeException('Nome sem correspondencia inequívoca para '.$old);
    $targets=$tbykey[$source['name_key']]??[];
    if(count($targets)===1) {
        if((int)$targets[0]===$old)throw new RuntimeException('Categoria ja esta no percurso correto '.$old);
        $actions[]=['mode'=>'merge','old'=>$old,'keep'=>(int)$targets[0],
            'source_id'=>(int)$source['id'],'source_slug'=>$source['slug'],
            'old_name_key'=>$t[$old]['name_key'],'destination'=>$source['name_key']];
    } elseif ((count($targets)===0||count($targets)>1)) {
        // A categoria ja contem exatamente o slug da fonte; preservar o seu ID e
        // reparar APENAS a posicao se a categoria pai da fonte for univoca.
        $sourceParent=(int)$source['parent'];
        if(!$sourceParent||!isset($s[$sourceParent]))throw new RuntimeException('Pai original indisponivel para '.$old);
        $parents=$tbykey[$s[$sourceParent]['name_key']]??[];
        if(count($parents)!==1)throw new RuntimeException('Pai de destino ambiguo para '.$old);
        $actions[]=['mode'=>'reparent','old'=>$old,'keep'=>(int)$parents[0],
            'source_id'=>(int)$source['id'],'source_slug'=>$source['slug'],
            'old_name_key'=>$t[$old]['name_key'],'destination'=>$source['name_key']];
    } else throw new RuntimeException('Correspondencia ambigua '.$old);
}
usort($actions, static function($a,$b) {
    $da=substr_count($a['old_name_key'],'>');$db=substr_count($b['old_name_key'],'>');
    return ($db<=>$da) ?: ($a['old']<=>$b['old']);
});
$toMerge=count(array_filter($actions,static fn($a)=>$a['mode']==='merge'));
$toMove=count($actions)-$toMerge;
if(count($actions)!==27||$toMerge!==25||$toMove!==2||count($excluded)!==2)
    throw new RuntimeException('Plano nao corresponde a 25 fusoes e 2 correcoes de pai');

$terms=get_terms(['taxonomy'=>'product_cat','hide_empty'=>false,'orderby'=>'term_id','order'=>'ASC']);
if(is_wp_error($terms)||count($terms)!==926)throw new RuntimeException('Woo mudou desde o snapshot');
foreach($terms as $term) {
    $id=(int)$term->term_id;
    if (!isset($t[$id]) || (string)$term->slug !==(string)$t[$id]['slug'] ||
        (int)$term->parent !==(int)$t[$id]['parent'] ||
        html_entity_decode(wp_strip_all_tags($term->name),ENT_QUOTES|ENT_HTML5,'UTF-8')!==$t[$id]['name']) {
        throw new RuntimeException('Categoria modificada desde snapshot, ID '.$id);
    }
}
$plan=['mode'=>$mode,'backup_total'=>count($s),'loja_before'=>926,
    'merge'=>25,'reparent'=>2,'expected_after'=>901,'protected_ids'=>$excluded,
    'actions'=>$actions,'created_at_utc'=>gmdate('c')];
file_put_contents($dir.'/plano-reorganizar.json',wp_json_encode($plan,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo 'CV_MISPLACED_PLAN '.wp_json_encode([
    'mode'=>$mode,'merges'=>25,'reparents'=>2,'before'=>926,'after'=>901,'protected'=>$excluded
],JSON_UNESCAPED_UNICODE).PHP_EOL;
if($mode==='plan')return;
$sql=getenv('CV_MISPLACED_SQL_BACKUP');
if(getenv('CV_MISPLACED_CONFIRM')!=='ALINHAR_CATEGORIAS_AO_BACKUP'
    || !$sql||!is_file($sql)||filesize($sql)<10000
    || !class_exists('CV_Core_Catalog_Rules')
    || has_action('template_redirect',['CV_Core_Catalog_Rules','redirect_old_category_urls'])===false
    || get_option('cv_category_merge_operation_lock',false)) {
    throw new RuntimeException('Nao autorizado/sem backup ou com outra migracao em curso');
}
if(!add_option('cv_category_merge_operation_lock',['type'=>'misplaced','time'=>gmdate('c')],'',false))
    throw new RuntimeException('Falhou bloqueio de escrita');
global $wpdb;
$original_links=[];
foreach($terms as $term){
    $url=get_term_link($term);
    if(!is_wp_error($url))$original_links[$term->term_id]=$url;
}
$redirects=get_option('cv_core_category_slug_redirects',[]);
if(!is_array($redirects))$redirects=[];
$changes=[];$deleted=[];$moved=[];$products=0;
$logfile=$dir.'/journal.ndjson';
$log=static function($row)use($logfile) {
    file_put_contents($logfile,wp_json_encode(['time'=>gmdate('c')]+$row,JSON_UNESCAPED_UNICODE)."\n",FILE_APPEND|LOCK_EX);
};
$pathOf=static function($url){
    $path=is_string($url)?wp_parse_url($url,PHP_URL_PATH):'';
    return is_string($path)&&str_starts_with($path,'/categoria-produto/')?$path:'';
};
$addRedirect=static function($before,$after)use(&$redirects,$pathOf) {
    $one=$pathOf($before);$two=$pathOf($after);
    if(!$one||!$two||$one===$two)return;
    $redirects[$one]=$two;
    update_option('cv_core_category_slug_redirects',$redirects,false);
    $save=get_option('cv_core_category_slug_redirects',[]);
    if(!is_array($save)||($save[$one]??null)!==$two)
        throw new RuntimeException('Falhou redirecionamento SEO '.$one);
};
try {
    foreach($actions as $a) {
        $old=(int)$a['old'];$to=(int)$a['keep'];$slug=$a['source_slug'];
        $term=get_term($old,'product_cat');$dest=get_term($to,'product_cat');
        if(!$term||is_wp_error($term)||!$dest||is_wp_error($dest))
            throw new RuntimeException('Termo nao disponivel, origem '.$old);
        if($term->slug!==$slug)throw new RuntimeException('Slug historico mudou '.$old);
        if($a['mode']==='reparent') {
            $res=wp_update_term($old,'product_cat',['parent'=>$to]);
            $check=get_term($old,'product_cat');
            if(is_wp_error($res)||!$check||(int)$check->parent!==$to||$check->slug!==$slug)
                throw new RuntimeException('Falha ao mudar categoria para o ramo historico '.$old);
            $moved[]=$old;
            $log(['op'=>'reparent','id'=>$old,'new_parent'=>$to]);
        } else {
            $kids=get_terms(['taxonomy'=>'product_cat','hide_empty'=>false,
                'parent'=>$old,'fields'=>'ids']);
            if(is_wp_error($kids)||count($kids))
                throw new RuntimeException('Fusao bloqueada; existem filhos da categoria '.$old);
            $objects=$wpdb->get_results($wpdb->prepare(
                "SELECT tr.object_id,p.post_type FROM {$wpdb->term_relationships} tr
                 INNER JOIN {$wpdb->posts} p ON p.ID=tr.object_id
                 WHERE tr.term_taxonomy_id=%d",(int)$term->term_taxonomy_id),ARRAY_A);
            if($wpdb->last_error)throw new RuntimeException('Falha SQL a ler produtos');
            foreach($objects as $object) {
                $pid=(int)$object['object_id'];
                if($object['post_type']!=='product')
                    throw new RuntimeException('Objeto nao produto associado '.$pid);
                $added=wp_set_object_terms($pid,[$to],'product_cat',true);
                if(is_wp_error($added))throw new RuntimeException('Falha ao atribuir categoria ao produto '.$pid);
                $removed=wp_remove_object_terms($pid,[$old],'product_cat');
                if(is_wp_error($removed)||!$removed)
                    throw new RuntimeException('Falha ao retirar categoria antiga do produto '.$pid);
                foreach(['rank_math_primary_product_cat','_yoast_wpseo_primary_product_cat'] as $field) {
                    if((int)get_post_meta($pid,$field,true)===$old)update_post_meta($pid,$field,(string)$to);
                }
                $afterIds=wp_get_object_terms($pid,'product_cat',['fields'=>'ids']);
                if(is_wp_error($afterIds)||!in_array($to,array_map('intval',$afterIds),true)||
                    in_array($old,array_map('intval',$afterIds),true))
                    throw new RuntimeException('Produto alterado incorretamente '.$pid);
                $products++;
                $log(['op'=>'product','id'=>$pid,'from'=>$old,'to'=>$to]);
            }
            $remaining=(int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->term_relationships} WHERE term_taxonomy_id=%d",
                (int)$term->term_taxonomy_id));
            if($remaining!==0)throw new RuntimeException('Categoria ainda com associacoes '.$old);
            $log(['op'=>'before_delete','id'=>$old,'keep'=>$to,'slug'=>$term->slug]);
            $deletedTerm=wp_delete_term($old,'product_cat');
            if(is_wp_error($deletedTerm)||!$deletedTerm)
                throw new RuntimeException('Falha ao eliminar duplicado '.$old);
            $deleted[$old]=$to;
            // O slug original esta agora livre; atribui-lo ao termo da hierarquia correta.
            $owners=get_terms(['taxonomy'=>'product_cat','slug'=>$slug,
                'hide_empty'=>false,'fields'=>'ids']);
            if(is_wp_error($owners)||array_diff(array_map('intval',$owners),[$to]))
                throw new RuntimeException('Slug do backup passou a ser ocupado '.$slug);
            $edited=wp_update_term($to,'product_cat',['slug'=>$slug]);
            $check=get_term($to,'product_cat');
            if(is_wp_error($edited)||!$check||$check->slug!==$slug)
                throw new RuntimeException('Falha ao restaurar slug historico '.$to);
            $newurl=get_term_link($to,'product_cat');
            if(is_wp_error($newurl))throw new RuntimeException('URL final invalido '.$to);
            $addRedirect($original_links[$old]??'',$newurl);
            $log(['op'=>'merged','from'=>$old,'into'=>$to,'slug'=>$slug]);
        }
        $changes[]=$a;
        echo 'CV_MISPLACED_ITEM '.wp_json_encode(['id'=>$old,'action'=>$a['mode'],'to'=>$to],JSON_UNESCAPED_UNICODE).PHP_EOL;
    }
    foreach($original_links as $id=>$oldurl) {
        if(isset($deleted[$id]))$newurl=get_term_link((int)$deleted[$id],'product_cat');
        else {
            $cat=get_term((int)$id,'product_cat');
            if(is_wp_error($cat)||!$cat)continue;
            $newurl=get_term_link($cat);
        }
        if(!is_wp_error($newurl))$addRedirect($oldurl,$newurl);
    }
    $after=get_terms(['taxonomy'=>'product_cat','hide_empty'=>false,'fields'=>'ids']);
    if(is_wp_error($after)||count($after)!==901||count($deleted)!==25||count($moved)!==2)
        throw new RuntimeException('Contagem final nao validada');
    $done=['status'=>'success','before'=>926,'after'=>901,
      'merges'=>count($deleted),'reparented'=>count($moved),'product_assignments_transferred'=>$products,
      'redirects'=>count($redirects),'completed_at'=>gmdate('c')];
    file_put_contents($dir.'/resultado-final.json',wp_json_encode($done,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    echo 'CV_MISPLACED_DONE '.wp_json_encode($done,JSON_UNESCAPED_UNICODE).PHP_EOL;
} catch(Throwable $error) {
    $log(['op'=>'error','message'=>$error->getMessage()]);
    file_put_contents($dir.'/resultado-parcial.json',wp_json_encode([
        'status'=>'error','message'=>$error->getMessage(),'done'=>count($changes),
        'merged'=>count($deleted),'moved'=>count($moved)],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    throw $error;
} finally {
    delete_option('cv_category_merge_operation_lock');
}
