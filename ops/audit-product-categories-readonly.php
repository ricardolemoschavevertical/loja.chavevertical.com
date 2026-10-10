<?php
/**
 * Auditoria de categorias do WooCommerce - apenas leitura.
 * Executar: wp --path=/home/chavevertical-loja/htdocs/loja.chavevertical.com eval-file ops/audit-product-categories-readonly.php
 * Não altera produtos, termos, opções, cache nem categorias.
 */
defined('ABSPATH') || exit;
global $wpdb;

if (!taxonomy_exists('product_cat')) {
    throw new RuntimeException('Taxonomia product_cat indisponível.');
}
$terms = get_terms(['taxonomy'=>'product_cat','hide_empty'=>false,'orderby'=>'term_id','order'=>'ASC']);
if (is_wp_error($terms)) {
    throw new RuntimeException($terms->get_error_message());
}
$items=[];
foreach ($terms as $t) {
    $id=(int)$t->term_id;
    $items[$id]=[
        'id'=>$id,'nome'=>(string)$t->name,'slug'=>(string)$t->slug,'pai_id'=>(int)$t->parent,
        'contagem_woocommerce'=>(int)$t->count,
        'diretos_publicados'=>0,'diretos_todos_estados'=>0,'descendentes'=>0,
        'subarvore_associacoes_publicadas'=>0,'subarvore_associacoes_todos_estados'=>0,
        'subcategorias_diretas'=>0,'tem_imagem'=>false,'predefinido'=>false,
        'caminho'=>'','profundidade'=>0,'raiz_id'=>0,'estado_hierarquia'=>'ok',
        'alertas'=>[]
    ];
}
$default=(int)get_option('default_product_cat',0);
$rows=$wpdb->get_results(
    "SELECT tt.term_id AS term_id,
      COUNT(DISTINCT CASE WHEN p.post_status = 'publish' THEN p.ID ELSE NULL END) AS publicados,
      COUNT(DISTINCT p.ID) AS todos
     FROM {$wpdb->term_taxonomy} tt
     INNER JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
     INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id
     WHERE tt.taxonomy = 'product_cat' AND p.post_type = 'product'
     AND p.post_status IN ('publish','private','draft','pending','future')
     GROUP BY tt.term_id", ARRAY_A);
if ($wpdb->last_error) {
    throw new RuntimeException('Falha na leitura de associações de produtos: '.$wpdb->last_error);
}
foreach ($rows as $row) {
    $id=(int)$row['term_id'];
    if (!isset($items[$id])) continue;
    $items[$id]['diretos_publicados']=(int)$row['publicados'];
    $items[$id]['diretos_todos_estados']=(int)$row['todos'];
}
$thumbs=$wpdb->get_results(
    "SELECT tm.term_id, tm.meta_value FROM {$wpdb->termmeta} tm
     INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id=tm.term_id AND tt.taxonomy='product_cat'
     WHERE tm.meta_key='thumbnail_id' AND tm.meta_value REGEXP '^[1-9][0-9]*$'", ARRAY_A);
foreach ($thumbs as $row) {
    $id=(int)$row['term_id'];
    if (isset($items[$id])) $items[$id]['tem_imagem']=true;
}
$children=[];
foreach ($items as $id=>$item) {
    $pid=$item['pai_id'];
    if ($pid && isset($items[$pid])) {
        $children[$pid][]=$id;
        $items[$pid]['subcategorias_diretas']++;
    }
}
foreach ($items as $id=>&$item) {
    $seen=[];$names=[];$ids=[];$cursor=$id;
    for ($i=0;$i<=count($items);$i++) {
        if (!$cursor) break;
        if (isset($seen[$cursor])) {$item['estado_hierarquia']='ciclo';break;}
        if (!isset($items[$cursor])) {$item['estado_hierarquia']='pai_inexistente';break;}
        $seen[$cursor]=true;
        $names[]=$items[$cursor]['nome'];
        $ids[]=$cursor;
        $cursor=$items[$cursor]['pai_id'];
    }
    $item['caminho']=implode(' > ',array_reverse($names));
    $item['profundidade']=max(0,count($ids)-1);
    $item['raiz_id']=count($ids)?end($ids):0;
    $item['predefinido']=($id===$default);
}
unset($item);

$order=array_keys($items);
usort($order, static function($a,$b) use ($items) {
    return $items[$b]['profundidade']<=>$items[$a]['profundidade'];
});
foreach ($order as $id) {
    $items[$id]['subarvore_associacoes_publicadas'] += $items[$id]['diretos_publicados'];
    $items[$id]['subarvore_associacoes_todos_estados'] += $items[$id]['diretos_todos_estados'];
    $parent=$items[$id]['pai_id'];
    if ($parent && isset($items[$parent]) && $items[$id]['estado_hierarquia']==='ok') {
        $items[$parent]['subarvore_associacoes_publicadas'] += $items[$id]['subarvore_associacoes_publicadas'];
        $items[$parent]['subarvore_associacoes_todos_estados'] += $items[$id]['subarvore_associacoes_todos_estados'];
        $items[$parent]['descendentes'] += $items[$id]['descendentes']+1;
    }
}

$groups=[];$slugReview=[];$wrongCount=[];$roots=[];$emptyBranches=[];$emptyLeaves=[];
$generic=['acessorios'=>true,'outros'=>true,'diversos'=>true,'pecas'=>true,'maquinas'=>true,'ferramentas'=>true,'equipamentos'=>true,'componentes'=>true,'consumiveis'=>true,'varios'=>true];
foreach ($items as $id=>&$item) {
    $canonical=sanitize_title($item['nome']);
    $groups[$canonical][]=$id;
    if ($item['estado_hierarquia']!=='ok') $item['alertas'][]='hierarquia_invalida';
    if ($item['predefinido']) $item['alertas'][]='categoria_predefinida';
    if ($item['subarvore_associacoes_todos_estados']===0) {
        $item['alertas'][]='subarvore_sem_produtos';
        if ($item['subcategorias_diretas']>0) $emptyBranches[]=$id;
        else $emptyLeaves[]=$id;
    }
    if ($item['diretos_publicados']===0 && $item['diretos_todos_estados']>0)
        $item['alertas'][]='so_produtos_nao_publicados_diretamente';
    if ($item['profundidade']>=5) $item['alertas'][]='hierarquia_profunda';
    if ($item['contagem_woocommerce']!==$item['diretos_publicados']) {
        $wrongCount[]=$id;
    }
    $similar=0;
    similar_text($canonical,$item['slug'],$similar);
    if ($canonical!==$item['slug'] && $similar<46 && strlen($canonical)>=9 && strlen($item['slug'])>=6) {
        $item['alertas'][]='slug_historico_a_rever_sem_alterar';
        $slugReview[]=[$id,round($similar)];
    }
    if ($item['pai_id']===0 || !isset($items[$item['pai_id']])) $roots[]=$id;
}
unset($item);
usort($roots,static function($a,$b)use($items){return ($items[$b]['descendentes']<=>$items[$a]['descendentes'])?:strcmp($items[$a]['nome'],$items[$b]['nome']);});
$groupRows=[];$siblingDups=[];
foreach ($groups as $label=>$ids) {
    if (count($ids)<2) continue;
    $parents=array_unique(array_map(static function($id)use($items){return $items[$id]['pai_id'];},$ids));
    $sameParent=count($parents)<count($ids);
    $examples=array_map(static function($id)use($items){return ['id'=>$id,'caminho'=>$items[$id]['caminho'],'produtos'=>$items[$id]['subarvore_associacoes_todos_estados']];},$ids);
    $one=['nome_normalizado'=>$label,'quantidade'=>count($ids),'generico'=>isset($generic[$label]),'repetido_no_mesmo_pai'=>$sameParent,'ocorrencias'=>$examples];
    $groupRows[]=$one;
    if ($sameParent) $siblingDups[]=$one;
}
usort($groupRows,static function($a,$b){return $b['quantidade']<=>$a['quantidade'];});
usort($slugReview,static function($a,$b){return $a[1]<=>$b[1];});
usort($emptyBranches,static function($a,$b)use($items){return $items[$b]['descendentes']<=>$items[$a]['descendentes'];});
$depths=[];$withProducts=0;$withPub=0;$onlyDraft=0;$deep=0;
foreach ($items as $item) {
    $d=$item['profundidade'];
    $depths[$d]=($depths[$d]??0)+1;
    if ($item['subarvore_associacoes_todos_estados']>0) $withProducts++;
    if ($item['subarvore_associacoes_publicadas']>0) $withPub++;
    if ($item['diretos_publicados']===0 && $item['diretos_todos_estados']>0) $onlyDraft++;
    if ($d>=5) $deep++;
}
ksort($depths,SORT_NUMERIC);
$rootRows=[];
foreach ($roots as $id) {
    $x=$items[$id];
    $rootRows[]=['id'=>$id,'nome'=>$x['nome'],'descendentes'=>$x['descendentes'],
        'associacoes_publicadas_subarvore'=>$x['subarvore_associacoes_publicadas'],
        'associacoes_todos_estados_subarvore'=>$x['subarvore_associacoes_todos_estados'],
        'raiz_vazia'=>$x['subarvore_associacoes_todos_estados']===0];
}
$summary=[
    'gerado_em_utc'=>gmdate('c'),'origem'=>'WooCommerce product_cat em tempo real',
    'total_categorias'=>count($items),'categorias_principais'=>count($roots),
    'subcategorias'=>count($items)-count($roots),
    'niveis_por_profundidade'=>$depths,
    'categorias_subarvore_com_produtos'=>$withProducts,
    'categorias_subarvore_sem_produtos'=>count($items)-$withProducts,
    'folhas_sem_produtos'=>count($emptyLeaves),
    'ramos_inteiros_sem_produtos'=>count($emptyBranches),
    'categorias_com_produtos_publicados_na_subarvore'=>$withPub,
    'categorias_com_produtos_nao_publicados_mas_sem_publicados_diretamente'=>$onlyDraft,
    'nomes_repetidos_grupos'=>count($groupRows),
    'duplicacoes_no_mesmo_pai_grupos'=>count($siblingDups),
    'slugs_muito_diferentes_do_nome'=>count($slugReview),
    'contagem_woo_diferente_da_contagem_publicada'=>count($wrongCount),
    'categorias_profundidade_5_ou_mais'=>$deep,
    'categoria_predefinida_id'=>$default,
    'categoria_predefinida_produtos'=>$items[$default]['diretos_todos_estados']??null,
    'hierarquias_invalidas'=>count(array_filter($items,static function($x){return $x['estado_hierarquia']!=='ok';}))
];
$dir=getenv('CV_AUDIT_OUTPUT_DIR')?:__DIR__.'/../audit-output';
if (!is_dir($dir) && !mkdir($dir,0770,true) && !is_dir($dir)) throw new RuntimeException('Falha ao criar pasta temporária de auditoria.');
$report=['resumo'=>$summary,'raizes'=>$rootRows,'nomes_repetidos'=>$groupRows,'duplicacoes_no_mesmo_pai'=>$siblingDups,
    'ramos_sem_produtos'=>array_map(static function($id)use($items){return ['id'=>$id,'caminho'=>$items[$id]['caminho'],'descendentes'=>$items[$id]['descendentes'],'tem_imagem'=>$items[$id]['tem_imagem']];},$emptyBranches),
    'slugs_muito_diferentes'=>array_map(static function($x)use($items){return ['id'=>$x[0],'caminho'=>$items[$x[0]]['caminho'],'slug'=>$items[$x[0]]['slug'],'similaridade_pct'=>$x[1]];},$slugReview),
    'categorias'=>array_values($items)];
file_put_contents($dir.'/categorias-auditoria.json',wp_json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
$csv=fopen($dir.'/categorias-auditoria.csv','wb');
fwrite($csv,"\xEF\xBB\xBF");
fputcsv($csv,['ID','Nome','Slug','ID pai','Caminho atual','Profundidade','Subcategorias diretas','Descendentes',
'Produtos publicados diretos','Produtos diretos todos estados','Associacoes publicadas na subarvore','Associacoes todos estados na subarvore',
'Imagem','Predefinido','Integridade hierarquia','Alertas'], ';');
foreach ($items as $x) {
    fputcsv($csv,[$x['id'],$x['nome'],$x['slug'],$x['pai_id'],$x['caminho'],$x['profundidade'],
    $x['subcategorias_diretas'],$x['descendentes'],$x['diretos_publicados'],$x['diretos_todos_estados'],
    $x['subarvore_associacoes_publicadas'],$x['subarvore_associacoes_todos_estados'],
    $x['tem_imagem']?'SIM':'NÃO',$x['predefinido']?'SIM':'NÃO',$x['estado_hierarquia'],implode(' | ',$x['alertas'])],';');
}
fclose($csv);
$md="# Auditoria de categorias WooCommerce (apenas leitura)\n\n";
$md.="Gerado em ".gmdate('Y-m-d H:i').' UTC. Nenhuma categoria/produto foi alterado.\n\n';
foreach ($summary as $key=>$value) $md.='- **'.$key.'**: '.(is_array($value)?wp_json_encode($value):$value)."\n";
$md.="\n## Categorias principais e respetivo volume\n\n| ID | Nome | Subcategorias totais | Associações publicadas no ramo |\n|---:|---|---:|---:|\n";
foreach ($rootRows as $r) $md.="| {$r['id']} | ".str_replace('|','/', $r['nome'])." | {$r['descendentes']} | {$r['associacoes_publicadas_subarvore']} |\n";
$md.="\n## Nota de interpretação\n\nAssociações em subárvores são somas de relações diretas por categoria e podem contar o mesmo produto várias vezes se estiver em várias categorias do ramo. Vazio = nenhum produto associado em estados publicados/não publicados elegíveis, mas é preciso confirmar páginas, links, SEO e imagens antes de consolidar ou eliminar. Slugs antigos não devem ser corrigidos sem verificação de URL/canónico/redirecionamento.\n";
file_put_contents($dir.'/relatorio-categorias.md',$md);
echo 'CV_AUDIT_SUMMARY '.wp_json_encode($summary,JSON_UNESCAPED_UNICODE)."\n";
foreach ($rootRows as $r) echo 'CV_AUDIT_ROOT '.wp_json_encode($r,JSON_UNESCAPED_UNICODE)."\n";
foreach (array_slice($siblingDups,0,20) as $g) echo 'CV_AUDIT_SIBLING_DUP '.wp_json_encode($g,JSON_UNESCAPED_UNICODE)."\n";
foreach (array_slice(array_values(array_filter($groupRows,static function($r){return !$r['generico'];})),0,22) as $g) echo 'CV_AUDIT_REPEAT '.wp_json_encode($g,JSON_UNESCAPED_UNICODE)."\n";
foreach (array_slice($emptyBranches,0,30) as $id) echo 'CV_AUDIT_EMPTY_BRANCH '.wp_json_encode(['id'=>$id,'caminho'=>$items[$id]['caminho'],'descendentes'=>$items[$id]['descendentes'],'tem_imagem'=>$items[$id]['tem_imagem']],JSON_UNESCAPED_UNICODE)."\n";
foreach (array_slice($slugReview,0,20) as $row) echo 'CV_AUDIT_ODD_SLUG '.wp_json_encode(['id'=>$row[0],'caminho'=>$items[$row[0]]['caminho'],'slug'=>$items[$row[0]]['slug'],'similaridade_pct'=>$row[1]],JSON_UNESCAPED_UNICODE)."\n";
echo 'CV_AUDIT_REPORT_FILES '.$dir.'/categorias-auditoria.csv '.$dir.'/categorias-auditoria.json '.$dir.'/relatorio-categorias.md'."\n";
