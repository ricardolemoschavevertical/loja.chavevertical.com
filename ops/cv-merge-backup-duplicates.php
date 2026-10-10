<?php
/**
 * Consolida apenas categorias WooCommerce repetidas com MESMO caminho de nomes,
 * UMA correspondente no backup, e UMA categoria com o slug exato do backup.
 *
 * NUNCA cria categorias; nao altera categorias cujo destino nao e inequívoco.
 * Modo padrao: plan. Apply requer sinais de confirmacao e backup SQL verificado.
 */
defined('ABSPATH') || exit;
$mode = getenv('CV_CATEGORY_MERGE_MODE') ?: 'plan';
$path = getenv('CV_CATEGORY_MERGE_SNAPSHOT');
$dir  = getenv('CV_CATEGORY_MERGE_OUTPUT_DIR');
if (!in_array($mode,['plan','apply'],true) || !$path || !is_file($path) || !$dir) {
    throw new RuntimeException('Configuracao de auditoria invalida');
}
if (!is_dir($dir) && !wp_mkdir_p($dir)) throw new RuntimeException('Output dir not writable');
$manifest=json_decode(file_get_contents($path),true);
if (!is_array($manifest) || !isset($manifest['backup'],$manifest['loja'],$manifest['duplicates'])) {
    throw new RuntimeException('Inventario original invalido');
}
$s=[];$t=[];
foreach ($manifest['backup'] as $row) $s[(int)$row['id']]=$row;
foreach ($manifest['loja'] as $row) $t[(int)$row['id']]=$row;
if (count($s)!==894 || count($t)!==1067) {
    throw new RuntimeException('Auditoria historica incompleta; rever antes de mexer na loja');
}
$roots=[];$groups=[];
foreach ($manifest['duplicates'] as $g) {
    if (empty($g['candidate_auto_merge'])) continue;
    $master=(int)$g['exact_backup_slug_id'];
    if (count($g['source_ids'])!==1 || !isset($t[$master],$s[$g['source_ids'][0]]) ||
        $t[$master]['slug']!==$s[$g['source_ids'][0]]['slug'] ||
        !in_array($master,$g['target_ids'],true)) {
        throw new RuntimeException('Grupo com destino ou slug duvidoso: '.$g['name_key']);
    }
    $ids=array_values(array_filter(array_map('intval',$g['target_ids']),
        static fn($id)=>$id!==$master));
    if (!$ids) continue;
    foreach ($ids as $id) {
        if (!isset($t[$id]) || $t[$id]['name_key']!==$g['name_key'] ||
            $t[$id]['slug']===$t[$master]['slug']) {
            throw new RuntimeException('Categoria duplicada nao verificada: '.$id);
        }
        if (isset($roots[$id])) throw new RuntimeException('Categoria repetida no plano: '.$id);
        $roots[$id]=$master;
    }
    $groups[]=[
      'path'=>$g['name_key'],'master'=>$master,
      'source'=>(int)$g['source_ids'][0],
      'removed'=>$ids,'count'=>count($ids),
      'depth'=>substr_count($g['name_key'],'>')
    ];
}
usort($groups,static function($a,$b) {
    return ($b['depth']<=>$a['depth']) ?: ($b['count']<=>$a['count']);
});
$expectedRemove=count($roots);
if (count($groups)!==34 || $expectedRemove!==141) {
    throw new RuntimeException('Plano inesperado: '.count($groups).' grupos / '.$expectedRemove.' remocoes');
}
$default=(int)get_option('default_product_cat',0);
if (isset($roots[$default]) || !taxonomy_exists('product_cat')) {
    throw new RuntimeException('Categoria predefinida protegida');
}
$now=get_terms(['taxonomy'=>'product_cat','hide_empty'=>false,'orderby'=>'term_id','order'=>'ASC']);
if (is_wp_error($now) || count($now)!==1067) {
    throw new RuntimeException('A arvore de categorias mudou desde a auditoria. Nova revisao necessaria.');
}
foreach ($now as $term) {
    $id=(int)$term->term_id;
    if (!isset($t[$id]) || html_entity_decode(wp_strip_all_tags((string)$term->name),ENT_QUOTES|ENT_HTML5,'UTF-8') !== (string)$t[$id]['name'] ||
        (string)$term->slug !== (string)$t[$id]['slug'] ||
        (int)$term->parent !== (int)$t[$id]['parent']) {
        throw new RuntimeException('Categoria alterada desde auditoria: '.$id);
    }
}
if (count($now)!==count($t)) throw new RuntimeException('Inventario desatualizado');
$report=[
    'mode'=>$mode,'backup_count'=>count($s),'loja_before'=>count($t),
    'groups'=>count($groups),'duplicates_to_merge'=>$expectedRemove,
    'expected_after'=>count($t)-$expectedRemove,
    'expected_status'=>'sem_alteracoes_ate_confirmacao',
    'product_assignments_to_move'=>0,'child_categories_to_move'=>0,
    'preflight_utc'=>gmdate('c'),
    'group_details'=>[]
];
foreach($groups as $g) {
   $assign=0;$kids=0;
   foreach ($g['removed'] as $id) {
      $counts=$t[$id]['product_status_counts'];
      if (is_array($counts))$assign+=array_sum($counts);
      foreach ($t as $candidate) if ((int)$candidate['parent']===$id)$kids++;
   }
   $report['product_assignments_to_move']+=$assign;
   $report['child_categories_to_move']+=$kids;
   $report['group_details'][]=[
      'path'=>$g['path'],'backup_id'=>$g['source'],'keep_id'=>$g['master'],
      'remove_ids'=>$g['removed'],'product_assignments'=>$assign,'children'=>$kids
   ];
}
file_put_contents($dir.'/preflight-plan.json',wp_json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo 'CV_MERGE_PREFLIGHT '.wp_json_encode([
   'mode'=>$mode,'groups'=>count($groups),'duplicates'=>$expectedRemove,
   'assignments'=>$report['product_assignments_to_move'],'children'=>$report['child_categories_to_move'],
   'after'=>$report['expected_after']
],JSON_UNESCAPED_UNICODE).PHP_EOL;
if ($mode!=='apply') return;

$sql=getenv('CV_CATEGORY_MERGE_SQL_BACKUP');
if (getenv('CV_CATEGORY_MERGE_APPROVAL')!=='MERGE_BACKUP_IDENTICAL_CATEGORY_PATHS'
    || !$sql || !is_file($sql) || filesize($sql)<10000
    || !class_exists('CV_Core_Catalog_Rules')
    || !method_exists('CV_Core_Catalog_Rules','redirect_old_category_urls')
    || has_action('template_redirect',['CV_Core_Catalog_Rules','redirect_old_category_urls']) === false) {
    throw new RuntimeException('Backup SQL ou controle de redirecionamento/confirmacao em falta');
}
if (get_option('cv_category_merge_operation_lock',false)) {
    throw new RuntimeException('Existe outra migracao de categorias em curso');
}
if (!add_option('cv_category_merge_operation_lock',[
    'time'=>gmdate('c'),'slug'=>'cv-backup-category-consolidation','user'=>'github-actions'
], '',false)) {
    throw new RuntimeException('Impossivel bloquear operacoes simultaneas');
}
global $wpdb;
$redirects=get_option('cv_core_category_slug_redirects',[]);
if (!is_array($redirects))$redirects=[];
$original_urls=[];
foreach ($now as $term) {
    $url=get_term_link($term);
    if (!is_wp_error($url))$original_urls[(int)$term->term_id]=$url;
}
$removed_urls=[];$merged=[];$errors=[];$moved_products=0;$moved_children=0;$deleted=0;
$journal=$dir.'/operacoes.ndjson';
$log=static function($row)use($journal) {
    file_put_contents($journal,wp_json_encode(['at'=>gmdate('c')]+$row,JSON_UNESCAPED_UNICODE)."\n",FILE_APPEND|LOCK_EX);
};
$as_path=static function($url) {
    if (!is_string($url))return '';
    $path=wp_parse_url($url,PHP_URL_PATH);
    return is_string($path)&&str_starts_with($path,'/categoria-produto/')?$path:'';
};
$write_redirect=static function($old,$new)use(&$redirects,$as_path,$log) {
    $p=$as_path($old);$to=$as_path($new);
    if (!$p || !$to || $p===$to) return false;
    if (isset($redirects[$p]) && $redirects[$p]!==$to) {
       // Uma URL antiga ja registada pode mudar de destino se categoria foi fundida.
       $log(['type'=>'redirect_replaced','from'=>$p,'previous'=>$redirects[$p],'to'=>$to]);
    }
    $redirects[$p]=$to;
    update_option('cv_core_category_slug_redirects',$redirects,false);
    $saved=get_option('cv_core_category_slug_redirects',[]);
    if (!is_array($saved) || ($saved[$p]??null)!==$to) throw new RuntimeException('301 nao persistiu: '.$p);
    return true;
};
try {
    foreach ($groups as $g) {
        $keep=(int)$g['master'];
        $keepterm=get_term($keep,'product_cat');
        if (is_wp_error($keepterm) || !$keepterm || $keepterm->slug!==$t[$keep]['slug'])
            throw new RuntimeException('Destino mudou, ID '.$keep);
        foreach ($g['removed'] as $old) {
            $old=(int)$old;
            $term=get_term($old,'product_cat');
            if (is_wp_error($term) || !$term || (string)$term->slug!==$t[$old]['slug'])
                throw new RuntimeException('Duplicado mudou antes da fusao: '.$old);
            $kids=get_terms(['taxonomy'=>'product_cat','parent'=>$old,
                  'hide_empty'=>false,'fields'=>'ids']);
            if (is_wp_error($kids))throw new RuntimeException('Falha a obter descendentes '.$old);
            foreach ($kids as $child) {
                $child=(int)$child;
                if ($child===$keep || $child===$old) throw new RuntimeException('Hierarquia circular');
                $before_child=get_term($child,'product_cat');
                $res=wp_update_term($child,'product_cat',['parent'=>$keep]);
                if (is_wp_error($res)) throw new RuntimeException('Erro a mover filho '.$child.': '.$res->get_error_message());
                $check=get_term($child,'product_cat');
                if (!$check || is_wp_error($check) || (int)$check->parent!==$keep ||
                     (string)$check->slug!==(string)$before_child->slug) {
                    throw new RuntimeException('Desvio inesperado no slug/ramo do filho '.$child);
                }
                $moved_children++;
                $log(['type'=>'move_child','id'=>$child,'old_parent'=>$old,'new_parent'=>$keep]);
            }

            $objects=$wpdb->get_results($wpdb->prepare(
                "SELECT tr.object_id, p.post_type
                   FROM {$wpdb->term_relationships} tr
                   INNER JOIN {$wpdb->posts} p ON p.ID=tr.object_id
                   WHERE tr.term_taxonomy_id=%d", (int)$term->term_taxonomy_id),ARRAY_A);
            if ($wpdb->last_error)throw new RuntimeException('Erro ao verificar produtos da categoria '.$old);
            foreach ($objects as $object) {
                $pid=(int)$object['object_id'];
                if ($object['post_type']!=='product') {
                    throw new RuntimeException('Termo associado a objeto nao-produto '.$old.':'.$pid);
                }
                $added=wp_set_object_terms($pid,[$keep],'product_cat',true);
                if (is_wp_error($added))throw new RuntimeException('Nao conseguiu associar produto '.$pid.' a '.$keep);
                $ok=wp_remove_object_terms($pid,[$old],'product_cat');
                if (is_wp_error($ok) || !$ok) throw new RuntimeException('Falhou remover duplicado do produto '.$pid);
                foreach (['rank_math_primary_product_cat','_yoast_wpseo_primary_product_cat'] as $key) {
                    if ((int)get_post_meta($pid,$key,true)===$old)
                        update_post_meta($pid,$key,(string)$keep);
                }
                $final_ids=wp_get_object_terms($pid,'product_cat',['fields'=>'ids']);
                if (is_wp_error($final_ids) || !in_array($keep,array_map('intval',$final_ids),true)
                    || in_array($old,array_map('intval',$final_ids),true))
                    throw new RuntimeException('Associacao nao verificada '.$pid.':'.$old.'>'.$keep);
                $moved_products++;
                $log(['type'=>'product_reassigned','id'=>$pid,'from'=>$old,'to'=>$keep]);
            }
            $remaining=$wpdb->get_var($wpdb->prepare(
               "SELECT COUNT(*) FROM {$wpdb->term_relationships} WHERE term_taxonomy_id=%d",
               (int)$term->term_taxonomy_id));
            $other=get_terms(['taxonomy'=>'product_cat','parent'=>$old,'hide_empty'=>false,'fields'=>'ids']);
            if ((int)$remaining!==0 || is_wp_error($other) || count($other)!==0)
                throw new RuntimeException('Nao eliminar categoria com filhos/produtos '.$old);
            $masterurl=get_term_link($keep,'product_cat');
            if (is_wp_error($masterurl))throw new RuntimeException('URL canonical invalido '.$keep);
            $write_redirect($original_urls[$old]??'',$masterurl);
            $log(['type'=>'before_delete','from'=>$old,'to'=>$keep,'name'=>$term->name,'slug'=>$term->slug]);
            $res=wp_delete_term($old,'product_cat');
            if (!$res || is_wp_error($res))
                throw new RuntimeException('Falha ao apagar duplicado '.$old);
            $removed_urls[$old]=$keep;
            $deleted++;
            $log(['type'=>'delete_duplicate','from'=>$old,'to'=>$keep]);
        }
        $merged[]=['path'=>$g['path'],'master'=>$keep,'removed'=>count($g['removed'])];
        echo 'CV_MERGE_GROUP '.wp_json_encode(end($merged),JSON_UNESCAPED_UNICODE).PHP_EOL;
    }
    foreach ($original_urls as $id=>$original) {
        if (isset($removed_urls[$id])) {
            $current=get_term_link((int)$removed_urls[$id],'product_cat');
        } else {
            $category=get_term((int)$id,'product_cat');
            if (!$category || is_wp_error($category))continue;
            $current=get_term_link($category);
        }
        if (!is_wp_error($current))$write_redirect($original,$current);
    }
    $after=get_terms(['taxonomy'=>'product_cat','hide_empty'=>false,'fields'=>'ids']);
    if (is_wp_error($after) || count($after)!==926 || $deleted!==141)
       throw new RuntimeException('Falha validacao contagem apos consolidacao');
    foreach ($roots as $from=>$to) {
       if (term_exists((int)$from,'product_cat') || !term_exists((int)$to,'product_cat'))
           throw new RuntimeException('Falha ao validar estado de fusao '.$from);
    }
    $done=['status'=>'success','before'=>1067,'after'=>926,'deleted'=>$deleted,
      'groups'=>count($merged),'products_reassigned'=>$moved_products,
      'children_reparented'=>$moved_children,'redirects_total'=>count($redirects),
      'ended_utc'=>gmdate('c'),'group_details'=>$merged];
    file_put_contents($dir.'/resultado-final.json',wp_json_encode($done,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    echo 'CV_MERGE_DONE '.wp_json_encode($done,JSON_UNESCAPED_UNICODE).PHP_EOL;
} catch (Throwable $e) {
    $log(['type'=>'halt','message'=>$e->getMessage(),'removed'=>$deleted,'products'=>$moved_products]);
    file_put_contents($dir.'/resultado-parcial.json',wp_json_encode([
        'status'=>'halted','message'=>$e->getMessage(),'deleted'=>$deleted,
        'products_reassigned'=>$moved_products,'moved_children'=>$moved_children
    ],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    throw $e;
} finally {
    delete_option('cv_category_merge_operation_lock');
}
