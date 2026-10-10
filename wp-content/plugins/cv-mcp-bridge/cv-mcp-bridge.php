<?php
/**
 * Plugin Name: CV MCP Bridge - Chave Vertical
 * Description: Ponte MCP independente com ferramentas WooCommerce e delegação segura para Cloudflare.
 * Version: 0.4.0
 * Author: CHAVE VERTICAL
 * Requires Plugins: woocommerce, chavevertical-core
 * Requires PHP: 7.4
 */
defined('ABSPATH') || exit;

final class CV_MCP_Bridge {
    public static function init() {
        add_action('rest_api_init',[__CLASS__,'routes']);
    }
    public static function routes() {
        register_rest_route('cv-mcp/v1','/mcp',[
            'methods'=>'POST','permission_callback'=>[__CLASS__,'authenticate'],
            'callback'=>[__CLASS__,'handle']
        ]);
    }
    public static function authenticate(WP_REST_Request $r) {
        $given=trim((string)$r->get_header('authorization'));
        if(stripos($given,'Bearer ')===0)$given=substr($given,7);
        if(!$given)$given=(string)$r->get_header('x-cv-mcp-token');
        if(class_exists('CV_CFAI_Secrets')) {
            $valid=CV_CFAI_Secrets::verify_mcp_token($given);
        } elseif(defined('CV_MCP_BRIDGE_TOKEN') && strlen((string)CV_MCP_BRIDGE_TOKEN)>=40) {
            $valid=strlen($given)>=40 && hash_equals((string)CV_MCP_BRIDGE_TOKEN,$given);
        } else {
            $hash=(string)get_option('cv_mcp_bridge_token_sha256','');
            $valid=(bool)preg_match('/^[a-f0-9]{64}$/D',$hash)
                && strlen($given)>=40 && hash_equals($hash,hash('sha256',$given));
        }
        if(!$valid) return new WP_Error('cv_mcp_forbidden','Autenticação MCP necessária.',['status'=>401]);
        return true;
    }

    public static function tools() {
        $id=['type'=>'integer','minimum'=>1];
        $str=['type'=>'string'];
        $schema=static function($p,$required=[]){
            return ['type'=>'object','properties'=>$p,'required'=>$required,'additionalProperties'=>false];
        };
        return [
          ['name'=>'woocommerce_get_category_tree','description'=>'Consultar taxonomia product_cat viva e os ramos autorizados para o MCP.','inputSchema'=>$schema([])],
          ['name'=>'woocommerce_validate_product_categories','description'=>'Validar categorias atuais de um produto antes de qualquer operação.','inputSchema'=>$schema(['product_id'=>$id],['product_id'])],
          ['name'=>'woocommerce_search_products','description'=>'Pesquisar produtos por texto ou SKU sem modificar dados.','inputSchema'=>$schema(['query'=>$str,'limit'=>$id],['query'])],
          ['name'=>'woocommerce_get_product','description'=>'Obter dados de um produto WooCommerce, apenas leitura.','inputSchema'=>$schema(['product_id'=>$id],['product_id'])],
          ['name'=>'cv_ai_list_lists','description'=>'Consultar listas de normalização na Cloudflare.','inputSchema'=>$schema([])],
          ['name'=>'cv_ai_list_jobs','description'=>'Consultar tarefas de uma lista, apenas leitura.','inputSchema'=>$schema(['list_id'=>$str],['list_id'])],
          ['name'=>'cv_ai_create_list','description'=>'Criar lista com confirmação, apenas após ativar escrita MCP.','inputSchema'=>$schema(['name'=>$str,'product_refs'=>['type'=>'array','items'=>['oneOf'=>[$str,$id]]],'confirm_write'=>['type'=>'boolean']],['name','product_refs','confirm_write'])],
          ['name'=>'cv_ai_start_list','description'=>'Iniciar fila após confirmação explícita e ativação das escritas.','inputSchema'=>$schema(['list_id'=>$str,'confirm_write'=>['type'=>'boolean']],['list_id','confirm_write'])],
          ['name'=>'cv_ai_apply_job','description'=>'Aplicar proposta revista. Bloqueado por defeito no MCP e no Enricher.','inputSchema'=>$schema(['job_id'=>$str,'confirm_write'=>['type'=>'boolean']],['job_id','confirm_write'])]
        ];
    }

    private static function data($id,$out,$error=false) {
        return ['jsonrpc'=>'2.0','id'=>$id,'result'=>[
            'content'=>[['type'=>'text','text'=>wp_json_encode($out,JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]],
            'isError'=>$error
        ]];
    }
    private static function error($id,$code,$message) {
        return ['jsonrpc'=>'2.0','id'=>$id,'error'=>['code'=>$code,'message'=>$message]];
    }
    public static function handle(WP_REST_Request $request) {
        $b=$request->get_json_params();
        if(!is_array($b))return rest_ensure_response(self::error(null,-32700,'JSON inválido'));
        $method=(string)($b['method']??'');
        $id=$b['id']??null;
        if($method==='notifications/initialized')return new WP_REST_Response(null,202);
        if($method==='initialize')return rest_ensure_response(['jsonrpc'=>'2.0','id'=>$id,'result'=>[
            'protocolVersion'=>'2025-03-26','capabilities'=>['tools'=>['listChanged'=>false]],
            'serverInfo'=>['name'=>'cv-mcp-bridge','version'=>'0.4.0']]]);
        if($method==='ping')return rest_ensure_response(['jsonrpc'=>'2.0','id'=>$id,'result'=>(object)[]]);
        if($method==='tools/list')return rest_ensure_response(['jsonrpc'=>'2.0','id'=>$id,'result'=>['tools'=>self::tools()]]);
        if($method!=='tools/call')return rest_ensure_response(self::error($id,-32601,'Método MCP não suportado.'));
        $params=$b['params']??[];
        if(!is_array($params))return rest_ensure_response(self::error($id,-32602,'Parâmetros inválidos.'));
        $name=(string)($params['name']??'');
        $args=$params['arguments']??[];
        if(!is_array($args))$args=[];
        try {
            $out=self::run_tool($name,$args);
            $error=empty($out['ok']);
            return rest_ensure_response(self::data($id,$out,$error));
        }catch(Throwable $e){
            return rest_ensure_response(self::data($id,['ok'=>false,'error'=>'Falha ao executar ferramenta.'],true));
        }
    }

    private static function live($id=0) {
        if(!class_exists('CV_Core_Catalog_Rules'))return ['ok'=>false,'error'=>'Política central indisponível.'];
        $result=$id?CV_Core_Catalog_Rules::product_status($id,'mcp'):CV_Core_Catalog_Rules::tree('mcp');
        return is_wp_error($result)?['ok'=>false,'error'=>$result->get_error_message()]:$result;
    }
    private static function remote($method,$route,$body=null) {
        if(!class_exists('CV_Cloudflare_AI_Enricher'))return ['ok'=>false,'error'=>'CV AI Enricher não ativo.'];
        $result=CV_Cloudflare_AI_Enricher::remote($method,$route,$body);
        return is_wp_error($result)?['ok'=>false,'error'=>$result->get_error_message()]:$result;
    }
    public static function run_tool($name,$args) {
        if($name==='woocommerce_get_category_tree')return self::live();
        if($name==='woocommerce_validate_product_categories')return self::live(absint($args['product_id']??0));
        if($name==='woocommerce_search_products'){
            $query=sanitize_text_field((string)($args['query']??''));
            if(mb_strlen($query)<2)return ['ok'=>false,'error'=>'Introduza pelo menos 2 caracteres.'];
            $limit=max(1,min(30,absint($args['limit']??10)));
            $products=wc_get_products(['search'=>$query,'limit'=>$limit,'status'=>['publish','pending','draft'],'return'=>'objects']);
            $skuId=wc_get_product_id_by_sku($query);
            if($skuId)array_unshift($products,wc_get_product($skuId));
            $found=[];
            foreach($products as $p){
                if(!$p)continue;
                $found[$p->get_id()]=['id'=>$p->get_id(),'sku'=>$p->get_sku(),
                    'name'=>$p->get_name(),'status'=>$p->get_status()];
            }
            return ['ok'=>true,'products'=>array_slice(array_values($found),0,$limit)];
        }
        if($name==='woocommerce_get_product'){
            $p=wc_get_product(absint($args['product_id']??0));
            if(!$p)return ['ok'=>false,'error'=>'Produto não encontrado.'];
            $id=$p->get_id();
            return ['ok'=>true,'product'=>['id'=>$id,'sku'=>$p->get_sku(),'name'=>$p->get_name(),
                'description'=>$p->get_description(),'short_description'=>$p->get_short_description(),
                'regular_price'=>$p->get_regular_price(),'stock_quantity'=>$p->get_stock_quantity(),
                'status'=>$p->get_status(),'permalink'=>get_permalink($id)]];
        }
        if($name==='cv_ai_list_lists')return self::remote('GET','/v1/lists');
        if($name==='cv_ai_list_jobs'){
            $id=(string)($args['list_id']??'');
            if(!preg_match('/^[a-f0-9-]{36}$/i',$id))return ['ok'=>false,'error'=>'ID da lista inválido.'];
            return self::remote('GET','/v1/lists/'.$id.'/jobs');
        }
        if(in_array($name,['cv_ai_create_list','cv_ai_start_list','cv_ai_apply_job'],true)){
            if(!defined('CV_MCP_BRIDGE_ALLOW_WRITES') || CV_MCP_BRIDGE_ALLOW_WRITES!==true)
                return ['ok'=>false,'error'=>'Escritas MCP desativadas por segurança.'];
            if(($args['confirm_write']??null)!==true)
                return ['ok'=>false,'error'=>'É obrigatório confirm_write=true.'];
            if($name==='cv_ai_create_list'){
                $label=sanitize_text_field((string)($args['name']??''));
                $refs=$args['product_refs']??[];
                if(!$label || !is_array($refs) || count($refs)>250)
                    return ['ok'=>false,'error'=>'Nome ou referências inválidas.'];
                $items=[];
                foreach($refs as $ref){
                    $id=(is_int($ref)||ctype_digit((string)$ref))?absint($ref):wc_get_product_id_by_sku(sanitize_text_field((string)$ref));
                    if(!$id || !wc_get_product($id))continue;
                    $state=self::live($id);
                    if(empty($state['ok']))return ['ok'=>false,'error'=>'Categoria não autorizada: produto '.$id];
                    $items[$id]=['product_id'=>$id];
                }
                if(!$items)return ['ok'=>false,'error'=>'Nenhum produto válido.'];
                return self::remote('POST','/v1/lists',['name'=>$label,'items'=>array_values($items)]);
            }
            $id=(string)($args[$name==='cv_ai_start_list'?'list_id':'job_id']??'');
            if(!preg_match('/^[a-f0-9-]{36}$/i',$id))return ['ok'=>false,'error'=>'ID inválido.'];
            return self::remote('POST',$name==='cv_ai_start_list'
                ? '/v1/lists/'.$id.'/start' : '/v1/jobs/'.$id.'/apply',[]);
        }
        return ['ok'=>false,'error'=>'Ferramenta não suportada.'];
    }
}
add_action('plugins_loaded',static function(){
    if(class_exists('WooCommerce'))CV_MCP_Bridge::init();
},25);
