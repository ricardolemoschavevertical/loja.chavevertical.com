<?php
/** Isolated live taxonomy checks, no WordPress database needed. */
define('ABSPATH','/wordpress/');
$GLOBALS['categories']=[
 (object)['term_id'=>1,'parent'=>0,'name'=>'Ferramentas Manuais','slug'=>'ferramentas-manuais'],
 (object)['term_id'=>2,'parent'=>1,'name'=>'Chaves','slug'=>'chaves'],
 (object)['term_id'=>3,'parent'=>2,'name'=>'Roquetes','slug'=>'roquetes'],
 (object)['term_id'=>4,'parent'=>0,'name'=>'Elevação','slug'=>'elevacao'],
 (object)['term_id'=>99,'parent'=>0,'name'=>'Predefinido','slug'=>'sem-categoria'],
];
$GLOBALS['options']=['default_product_cat'=>99];
function absint($x){return abs((int)$x);}
function taxonomy_exists($t){return $t==='product_cat';}
function get_terms($args){return $GLOBALS['categories'];}
function get_option($key,$default=null){return $GLOBALS['options'][$key]??$default;}
function update_option($key,$value){$GLOBALS['options'][$key]=$value;return true;}
function wp_json_encode($v,$options=0){return json_encode($v,$options);}
function wc_get_product($id){return $id===123?new class {public function get_id(){return 123;}}:false;}
function wp_get_post_terms($id,$taxonomy,$args){return [3,99];}
class WP_Error {private $text;public function __construct($code,$text){$this->text=$text;}public function get_error_message(){return $this->text;}}
function is_wp_error($v){return $v instanceof WP_Error;}
require __DIR__.'/../wp-content/plugins/chavevertical-core/includes/class-cv-core-catalog-rules.php';
function check($ok,$reason){if (!$ok) throw new RuntimeException($reason);}
$tree=CV_Core_Catalog_Rules::tree('mcp');
check($tree['total']===5,'Categorias vazias tambem constam da arvore.');
check($tree['source']==='woocommerce_live_product_cat','A origem tem de ser product_cat.');
$allowed=CV_Core_Catalog_Rules::check_ids([3,99],'mcp');
check($allowed['ok'] && $allowed['valid_category_ids']===[3] && $allowed['ignored_default_ids']===[99],'Default nao pode ser categoria destino.');
check(!CV_Core_Catalog_Rules::check_ids([777777],'mcp')['ok'],'Categoria inventada nunca aceite.');
CV_Core_Catalog_Rules::save_roots(['mcp'=>[2],'enricher'=>[4]]);
check(CV_Core_Catalog_Rules::check_ids([3],'mcp')['ok'],'Descendentes de ramo autorizado devem ser aceites.');
check(!CV_Core_Catalog_Rules::check_ids([4],'mcp')['ok'],'MCP nao pode utilizar ramo externo.');
check(CV_Core_Catalog_Rules::check_ids([4],'enricher')['ok'],'Politica independente de cada agente.');
$before=CV_Core_Catalog_Rules::product_status(123,'mcp');
$GLOBALS['categories'][2]->parent=4;
CV_Core_Catalog_Rules::invalidate_cache();
$after=CV_Core_Catalog_Rules::product_status(123,'mcp');
check(!$after['ok'],'Mover ramo deve bloquear a autorizacao anterior.');
check($before['category_signature']!==$after['category_signature'],'A assinatura deve detetar uma mudanca de hierarquia.');
echo '9 verificacoes de categorias: OK'.PHP_EOL;
