<?php
/**
 * Comparar a taxonomia WooCommerce local com a copia historica backup.chavevertical.com.
 * Por omissao: leitura e relatorio. Nunca muda termos sem CV_CATEGORY_SYNC_MODE=apply
 * e CV_CATEGORY_SYNC_CONFIRM=APLICAR_SLUGS_VALIDADOS.
 */
defined('ABSPATH') || exit;
if (!taxonomy_exists('product_cat')) { throw new RuntimeException('Taxonomia product_cat indisponivel'); }

$mode = getenv('CV_CATEGORY_SYNC_MODE') ?: 'plan';
if (!in_array($mode, ['plan','apply'], true)) { throw new RuntimeException('Modo desconhecido'); }
if ($mode === 'apply' && getenv('CV_CATEGORY_SYNC_CONFIRM') !== 'APLICAR_SLUGS_VALIDADOS') {
    throw new RuntimeException('Aplicacao nao autorizada: confirme o sinalizador.');
}
$api_base = 'https://backup.chavevertical.com/wp-json/wp/v2/product_cat';
$outdir = getenv('CV_CATEGORY_SYNC_OUTPUT_DIR') ?: sys_get_temp_dir() . '/cv-category-backup-compare';
if (!is_dir($outdir) && !wp_mkdir_p($outdir)) { throw new RuntimeException('Pasta de relatorio indisponivel'); }
$clean_name = static function($str) {
    return html_entity_decode(wp_strip_all_tags((string)$str), ENT_QUOTES | ENT_HTML5, 'UTF-8');
};
$key_name = static function($str) use ($clean_name) {
    return sanitize_title($clean_name($str));
};
$source = []; $page = 1; $total_pages = null;
do {
    $url = add_query_arg(['per_page'=>100,'page'=>$page,'orderby'=>'id','order'=>'asc',
       '_fields'=>'id,name,slug,parent,link','hide_empty'=>'false'], $api_base);
    $response = wp_remote_get($url, [
        'timeout'=>30, 'redirection'=>2, 'sslverify'=>true,
        'headers'=>['Accept'=>'application/json'], 'user-agent'=>'ChaveVertical-CategoryAudit/1.0'
    ]);
    if (is_wp_error($response)) throw new RuntimeException('Falha a ler o backup: '.$response->get_error_message());
    $status = wp_remote_retrieve_response_code($response);
    if ($status !== 200) throw new RuntimeException('Backup REST respondeu HTTP '.$status.' na pagina '.$page);
    $json = json_decode(wp_remote_retrieve_body($response),true);
    if (!is_array($json)) throw new RuntimeException('JSON de origem invalido na pagina '.$page);
    foreach ($json as $row) {
        if (!isset($row['id'],$row['name'],$row['slug'],$row['parent'])) {
            throw new RuntimeException('Origem sem campos essenciais');
        }
        $id=(int)$row['id'];
        if ($id<1 || isset($source[$id])) throw new RuntimeException('ID origem invalido/repetido');
        $source[$id] = [
            'id'=>$id,'name'=>$clean_name($row['name']),'slug'=>(string)$row['slug'],
            'parent'=>(int)$row['parent'],'link'=>(string)($row['link']??'')
        ];
    }
    if ($total_pages===null) {
        $raw = wp_remote_retrieve_header($response,'x-wp-totalpages');
        $total_pages = is_numeric($raw) ? (int)$raw : 0;
        if ($total_pages>50) throw new RuntimeException('Limite de paginas inesperado no backup');
    }
    $count = count($json); $page++;
    if ($page>51) throw new RuntimeException('Limite de leitura da origem excedido');
} while ($total_pages ? $page<=$total_pages : $count===100);
if (count($source)<50) throw new RuntimeException('Origem incompleta: menos de 50 categorias');

$target_terms=get_terms(['taxonomy'=>'product_cat','hide_empty'=>false,'orderby'=>'term_id','order'=>'ASC']);
if (is_wp_error($target_terms)) throw new RuntimeException('Taxonomia de destino indisponivel');
$target=[];
foreach ($target_terms as $term) {
    $id=(int)$term->term_id;
    $link=get_term_link($term);
    if (is_wp_error($link)) $link='';
    $target[$id]=['id'=>$id,'name'=>$clean_name($term->name),
      'slug'=>(string)$term->slug,'parent'=>(int)$term->parent,'link'=>$link];
}

$make_path = static function($id, $index) use ($key_name) {
    $ids=[];$labels=[];$keys=[];$seen=[];$cursor=$id;
    while ($cursor>0) {
        if (isset($seen[$cursor]) || !isset($index[$cursor])) return null;
        $seen[$cursor]=true; $node=$index[$cursor];
        $ids[]=$cursor;$labels[]=$node['name'];$keys[]=$key_name($node['name']);
        $cursor=(int)$node['parent'];
        if (count($ids)>20) return null;
    }
    return [
        'ids'=>array_reverse($ids),
        'names'=>array_reverse($labels),
        'key'=>implode('>',array_reverse($keys)),
        'path'=>implode(' > ',array_reverse($labels))
    ];
};
$source_paths=[];$target_paths=[];$s_by_path=[];$t_by_path=[];
foreach ($source as $id=>$row) {
    $info=$make_path($id,$source);$source_paths[$id]=$info;
    if ($info!==null) $s_by_path[$info['key']][]=$id;
}
foreach ($target as $id=>$row) {
    $info=$make_path($id,$target);$target_paths[$id]=$info;
    if ($info!==null) $t_by_path[$info['key']][]=$id;
}
$slug_owners=[];
foreach ($target as $id=>$term) $slug_owners[$term['slug']][]=$id;
$rows=[];$stats=[
  'backup_total'=>count($source),'loja_total'=>count($target),'iguais'=>0,'alterar'=>0,
  'sem_correspondencia'=>0,'ambiguas'=>0,'colisoes'=>0,'hierarquias_invalidas'=>0,
  'sem_destino_backup'=>0,
];$matched_source=[];$proposals=[];
foreach ($target as $id=>$row) {
    $tpath=$target_paths[$id];
    $r=[
        'loja_id'=>$id,'loja_nome'=>$row['name'],'loja_caminho'=>$tpath['path']??'',
        'loja_slug'=>$row['slug'],'loja_url'=>$row['link'],
        'backup_id'=>'','backup_slug'=>'','backup_caminho'=>'','backup_url'=>'',
        'estado'=>'','motivo'=>'','slug_conflito_id'=>''
    ];
    if (!$tpath) {
        $r['estado']='hierarquia_invalida';
        $r['motivo']='Percurso de categoria invalido na loja';
    } else {
        $key=$tpath['key']; $candidates=$s_by_path[$key]??[];
        if (!$candidates) {
            $r['estado']='sem_correspondencia';
            $r['motivo']='Percurso completo nao existente no backup';
        } else {
            $match=0;
            if (count($candidates)===1 && count($t_by_path[$key])===1) {
                $match=$candidates[0];
            } else {
                // Apenas um slug ja identico pode resolver um nome duplicado sem inferir.
                $same=array_values(array_filter($candidates,static function($x) use($source,$row) {
                    return $source[$x]['slug']===$row['slug'];
                }));
                if (count($same)===1) $match=$same[0];
            }
            if (!$match) {
                $r['estado']='ambiguas';
                $r['motivo']='Categorias homonimas no mesmo percurso; exige desambiguacao manual';
            } else {
                $matched_source[$match]=true;
                $original=$source[$match];
                $r['backup_id']=$match;
                $r['backup_slug']=$original['slug'];
                $r['backup_caminho']=$source_paths[$match]['path'];
                $r['backup_url']=$original['link'];
                if ($row['slug']===$original['slug']) {
                    $r['estado']='iguais';
                    $r['motivo']='Slug ja corresponde ao backup';
                } else {
                    $owners=array_values(array_diff($slug_owners[$original['slug']]??[],[$id]));
                    if ($owners) {
                        $r['estado']='colisoes';
                        $r['slug_conflito_id']=implode(',',$owners);
                        $r['motivo']='Slug de destino ja utilizado por outra categoria na loja';
                    } else if ($original['slug']==='' || sanitize_title($original['slug'])!==$original['slug']) {
                        $r['estado']='ambiguas';
                        $r['motivo']='Slug de origem invalido para WordPress';
                    } else {
                        $r['estado']='alterar';
                        $r['motivo']='Correspondencia unica de percurso e slug livre';
                        $proposals[$id]=$original['slug'];
                    }
                }
            }
        }
    }
    $stats[$r['estado']]++;
    $rows[]=$r;
}
// Resolver dependencias seguras: uma categoria pode libertar o slug pedido por outra.
// Não tocar em ocupantes sem correspondência unívoca com o backup.
for ($pass=0; $pass<50; $pass++) {
    $promoted=0;
    foreach ($rows as &$r) {
        if ($r['estado']!=='colisoes') continue;
        $owners=array_values(array_filter(array_map('absint',explode(',',$r['slug_conflito_id']))));
        if (!$owners) continue;
        $can_free=true;
        foreach ($owners as $owner) {
            if (!isset($proposals[$owner]) || $proposals[$owner]===$r['backup_slug']) {
                $can_free=false;break;
            }
        }
        if (!$can_free || in_array($r['backup_slug'],$proposals,true)) continue;
        $proposals[$r['loja_id']]=$r['backup_slug'];
        $r['estado']='alterar';
        $r['motivo']='Slug libertado por outra correcao segura anterior';
        $r['slug_conflito_id']='';
        $stats['colisoes']--;
        $stats['alterar']++;
        $promoted++;
    }
    unset($r);
    if (!$promoted) break;
}
$stats['sem_destino_backup']=count($source)-count($matched_source);
$stats['propostas_validas']=count($proposals);
$stats['revisao_necessaria']=$stats['ambiguas']+$stats['colisoes']+$stats['sem_correspondencia']+$stats['hierarquias_invalidas'];
$stats['origem']='https://backup.chavevertical.com';
$stats['destino']='https://loja.chavevertical.com';
$stats['modo']=$mode;
$stats['gerado_utc']=gmdate('c');
$manifest=['estatisticas'=>$stats,'correspondencias'=>$rows,
  'sem_destino_backup'=>array_values(array_map(static function($x)use($source_paths) {
      return ['id'=>$x['id'],'nome'=>$x['name'],'slug'=>$x['slug'],'caminho'=>$source_paths[$x['id']]['path']??''];
  },array_filter($source,static function($x)use($matched_source){return !isset($matched_source[$x['id']]);})))];
file_put_contents($outdir.'/comparacao-com-backup.json',wp_json_encode($manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
$csv=fopen($outdir.'/comparacao-com-backup.csv','wb');
fwrite($csv,"\xEF\xBB\xBF");
$headers=array_keys($rows[0]);fputcsv($csv,$headers,';');
foreach ($rows as $row) fputcsv($csv,array_values($row),';');
fclose($csv);
echo 'CV_BACKUP_COMPARE_SUMMARY '.wp_json_encode($stats,JSON_UNESCAPED_UNICODE).PHP_EOL;
$top=array_slice(array_values(array_filter($rows,static function($r){return $r['estado']==='alterar';})),0,35);
foreach ($top as $r) echo 'CV_BACKUP_CHANGE '.wp_json_encode($r,JSON_UNESCAPED_UNICODE).PHP_EOL;
foreach (array_slice(array_values(array_filter($rows,static function($r){return $r['estado']==='colisoes';})),0,15) as $r)
    echo 'CV_BACKUP_COLLISION '.wp_json_encode($r,JSON_UNESCAPED_UNICODE).PHP_EOL;
foreach (array_slice(array_values(array_filter($rows,static function($r){return $r['estado']==='ambiguas';})),0,15) as $r)
    echo 'CV_BACKUP_AMBIGUOUS '.wp_json_encode($r,JSON_UNESCAPED_UNICODE).PHP_EOL;
if ($mode !== 'apply') return;

// Aplicar apenas as propostas com correspondencia unica. Registar backups ANTES de qualquer escrita.
if (!empty($stats['hierarquias_invalidas'])) throw new RuntimeException('Hierarquia corrompida, aplicacao bloqueada');
$backup_file=$outdir.'/snapshot-categorias-loja-antes.json';
if (file_put_contents($backup_file,wp_json_encode($target,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE))===false)
    throw new RuntimeException('Falha ao preservar snapshot de destino');
$oldlinks=[];
foreach ($target as $id=>$row) $oldlinks[$id]=$row['link'];
$results=[];$changed=0;$fails=0;$pending=$proposals;$iterations=0;
while ($pending && $iterations++<=count($proposals)+2) {
    $progress=false;
    foreach ($pending as $id=>$newslug) {
        $current=get_term($id,'product_cat');
        if (!$current || is_wp_error($current) || $current->slug!==$target[$id]['slug']) {
            $results[]=['id'=>$id,'estado'=>'ignorado','motivo'=>'Categoria mudou desde o inventario'];
            unset($pending[$id]);$progress=true;continue;
        }
        $owners=get_terms(['taxonomy'=>'product_cat','slug'=>$newslug,'hide_empty'=>false,'fields'=>'ids']);
        if (is_wp_error($owners)) {
            $results[]=['id'=>$id,'estado'=>'falha','motivo'=>'Falha ao verificar disponibilidade do slug'];
            $fails++;unset($pending[$id]);$progress=true;continue;
        }
        $busy=array_values(array_diff(array_map('intval',$owners),[$id]));
        if ($busy) {
            // Esperar pela operacao que ira libertar esse slug.
            if (count(array_diff($busy,array_keys($pending)))===0) continue;
            $results[]=['id'=>$id,'estado'=>'ignorado','motivo'=>'Slug ocupado por categoria que nao pode ser alterada'];
            unset($pending[$id]);$progress=true;continue;
        }
        $updated=wp_update_term($id,'product_cat',['slug'=>$newslug]);
        if (is_wp_error($updated)) {
            $results[]=['id'=>$id,'estado'=>'falha','motivo'=>$updated->get_error_message()];
            $fails++;unset($pending[$id]);$progress=true;continue;
        }
        $check=get_term($id,'product_cat');
        if (is_wp_error($check) || !$check || $check->slug!==$newslug) {
            $results[]=['id'=>$id,'estado'=>'falha','motivo'=>'Slug final nao corresponde ao backup'];
            $fails++;unset($pending[$id]);$progress=true;continue;
        }
        $changed++;
        $results[]=['id'=>$id,'estado'=>'atualizado','de'=>$target[$id]['slug'],'para'=>$newslug];
        unset($pending[$id]);$progress=true;
    }
    if (!$progress) {
        foreach ($pending as $id=>$newslug) {
            $results[]=['id'=>$id,'estado'=>'ignorado','motivo'=>'Dependencia ciclica/colisao sem resolucao automatica'];
        }
        break;
    }
}
// Guardar redirecionamentos para todos os percursos alterados, inclusive descendentes.
$redirects=get_option('cv_core_category_slug_redirects',[]);
if (!is_array($redirects)) $redirects=[];
foreach ($target as $id=>$before) {
    $after=get_term_link((int)$id,'product_cat');
    $before_url=$oldlinks[$id];
    if (is_wp_error($after) || !$before_url || $after===$before_url) continue;
    $oldpath=wp_parse_url($before_url,PHP_URL_PATH);
    $newpath=wp_parse_url($after,PHP_URL_PATH);
    if (!$oldpath || !$newpath || $oldpath===$newpath || !str_starts_with($oldpath,'/categoria-produto/') || !str_starts_with($newpath,'/categoria-produto/')) continue;
    $redirects[$oldpath]=$newpath;
}
update_option('cv_core_category_slug_redirects',$redirects,false);
$applied=['propostas'=>count($proposals),'alterados'=>$changed,'falhas'=>$fails,'redirecionamentos'=>count($redirects),'resultados'=>$results];
file_put_contents($outdir.'/resultado-aplicacao.json',wp_json_encode($applied,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo 'CV_BACKUP_APPLY_SUMMARY '.wp_json_encode([
 'propostas'=>count($proposals),'alterados'=>$changed,'falhas'=>$fails,'redirecionamentos_total'=>count($redirects)
],JSON_UNESCAPED_UNICODE).PHP_EOL;
if ($fails>0) throw new RuntimeException('Algumas atualizacoes falharam; consultar relatorio');
