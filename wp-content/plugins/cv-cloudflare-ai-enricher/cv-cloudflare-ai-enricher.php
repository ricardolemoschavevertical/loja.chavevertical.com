<?php
/**
 * Plugin Name: CV AI Enricher - Cloudflare
 * Description: Ponte segura WooCommerce <-> Cloudflare Workers, com categorias obtidas em tempo real.
 * Version: 0.3.0
 * Author: CHAVE VERTICAL
 * Requires Plugins: woocommerce, chavevertical-core
 * Requires PHP: 7.4
 */
defined('ABSPATH') || exit;

final class CV_Cloudflare_AI_Enricher {
    const NS = 'cv-ai/v1';
    const SETTINGS = 'cv_cfai_settings';

    public static function init() {
        add_action('rest_api_init', [__CLASS__, 'routes']);
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_post_cv_cfai_settings', [__CLASS__, 'save_settings']);
    }

    public static function token() {
        return defined('CV_CFAI_TOKEN') ? (string) CV_CFAI_TOKEN : '';
    }

    public static function worker_url() {
        $o = get_option(self::SETTINGS, []);
        $url = defined('CV_CFAI_WORKER_URL') ? (string) CV_CFAI_WORKER_URL : (is_array($o) ? (string)($o['worker_url'] ?? '') : '');
        $url = untrailingslashit($url);
        return ($url && wp_http_validate_url($url) && wp_parse_url($url,PHP_URL_SCHEME)==='https') ? $url : '';
    }

    public static function auth(WP_REST_Request $request) {
        $expected = self::token();
        $received = (string)$request->get_header('x-cv-ai-token');
        if (strlen($expected) < 40 || !$received || !hash_equals($expected, $received)) {
            return new WP_Error('cv_ai_forbidden','Não autorizado.',['status'=>401]);
        }
        return true;
    }

    private static function live_rules($product_id=0, $scope='enricher') {
        if (!class_exists('CV_Core_Catalog_Rules')) {
            return new WP_Error('cv_ai_core_missing','Gestor de categorias não instalado.',['status'=>503]);
        }
        return $product_id
            ? CV_Core_Catalog_Rules::product_status(absint($product_id),$scope)
            : CV_Core_Catalog_Rules::tree($scope);
    }

    public static function routes() {
        register_rest_route(self::NS,'/catalog/categories',[
            'methods'=>'GET','permission_callback'=>[__CLASS__,'auth'],
            'callback'=>static function(){ return rest_ensure_response(self::live_rules()); }
        ]);
        register_rest_route(self::NS,'/catalog/categories/validate',[
            'methods'=>'POST','permission_callback'=>[__CLASS__,'auth'],
            'callback'=>static function($r){
                $b=$r->get_json_params();
                if(!is_array($b))return new WP_Error('cv_ai_json','JSON inválido.',['status'=>400]);
                $scope=($b['scope']??'')==='mcp'?'mcp':'enricher';
                if(!empty($b['product_id']))return rest_ensure_response(self::live_rules(absint($b['product_id']),$scope));
                if(!class_exists('CV_Core_Catalog_Rules'))return self::live_rules();
                return rest_ensure_response(CV_Core_Catalog_Rules::check_ids($b['category_ids']??null,$scope));
            }
        ]);
        register_rest_route(self::NS,'/products/(?P<id>[0-9]+)',[
            'methods'=>'GET','permission_callback'=>[__CLASS__,'auth'],
            'callback'=>[__CLASS__,'snapshot']
        ]);
        register_rest_route(self::NS,'/products/(?P<id>[0-9]+)/apply',[
            'methods'=>'POST','permission_callback'=>[__CLASS__,'auth'],
            'callback'=>[__CLASS__,'apply']
        ]);
    }

    private static function modified($p) {
        $date=$p->get_date_modified('edit');
        return $date?gmdate('Y-m-d\TH:i:s\Z',$date->getTimestamp()):'';
    }

    public static function snapshot(WP_REST_Request $request) {
        $p=wc_get_product(absint($request['id']));
        if(!$p)return new WP_Error('cv_ai_not_found','Produto não encontrado.',['status'=>404]);
        $id=$p->get_id();
        $cats=self::live_rules($id);
        if(is_wp_error($cats))return $cats;
        $brand=[];
        if(taxonomy_exists('product_brand')) {
            $brand=wp_get_post_terms($id,'product_brand',['fields'=>'names']);
            if(is_wp_error($brand))$brand=[];
        }
        return rest_ensure_response(['product'=>[
            'id'=>$id,'sku'=>$p->get_sku('edit'),'name'=>$p->get_name('edit'),
            'description'=>$p->get_description('edit'),
            'short_description'=>$p->get_short_description('edit'),
            'brand_names'=>$brand,'categories'=>$cats['category_paths']??[],
            'category_ids'=>$cats['category_ids']??[],
            'category_paths'=>$cats['category_paths']??[],
            'category_validation'=>$cats,'status'=>$p->get_status('edit'),
            'modified_gmt'=>self::modified($p)
        ]]);
    }

    public static function apply(WP_REST_Request $request) {
        // Fail closed until a separate review of the entire workflow has succeeded.
        if(!defined('CV_CFAI_ALLOW_PRODUCT_APPLY') || CV_CFAI_ALLOW_PRODUCT_APPLY!==true) {
            return new WP_Error('cv_ai_apply_disabled','Aplicação de produtos está desativada por segurança.',['status'=>403]);
        }
        $p=wc_get_product(absint($request['id']));
        if(!$p)return new WP_Error('cv_ai_not_found','Produto não encontrado.',['status'=>404]);
        $body=$request->get_json_params();
        if(!is_array($body))return new WP_Error('cv_ai_json','JSON inválido.',['status'=>400]);
        $job=(string)($body['job_id']??'');
        $expected=(string)($body['expected_modified_gmt']??'');
        $signature=(string)($body['expected_category_signature']??'');
        $fields=$body['fields']??null;
        if(!preg_match('/^[a-f0-9-]{36}$/i',$job) || !preg_match('/^[a-f0-9]{64}$/i',$signature)
           || !$expected || !is_array($fields) || !$fields) {
            return new WP_Error('cv_ai_invalid','Proposta incompleta.',['status'=>400]);
        }
        if($p->get_meta('_cv_cfai_last_job',true)===$job)return rest_ensure_response(['ok'=>true,'already_applied'=>true]);
        $state=self::live_rules($p->get_id());
        if(is_wp_error($state))return $state;
        if(empty($state['ok']) || !hash_equals($state['category_signature']??'', $signature)
            || !hash_equals(self::modified($p),$expected)) {
            return new WP_Error('cv_ai_stale','O produto ou a árvore de categorias mudou.',['status'=>409]);
        }
        $allowed=['name','description','short_description','meta_description','focus_keyword'];
        foreach($fields as $key=>$value) {
            if(!in_array($key,$allowed,true)||!is_string($value)) {
                return new WP_Error('cv_ai_field','Campo não autorizado.',['status'=>400]);
            }
        }
        $id=$p->get_id();
        $before=['name'=>$p->get_name('edit'),'description'=>$p->get_description('edit'),
            'short_description'=>$p->get_short_description('edit'),
            'meta_description'=>get_post_meta($id,'rank_math_description',true),
            'focus_keyword'=>get_post_meta($id,'rank_math_focus_keyword',true)];
        if(isset($fields['name'])){
            $name=sanitize_text_field($fields['name']);
            if(!$name || mb_strlen($name)>220)return new WP_Error('cv_ai_title','Título inválido.',['status'=>422]);
            $p->set_name($name);
        }
        if(isset($fields['description']))$p->set_description(wp_kses_post($fields['description']));
        if(isset($fields['short_description']))$p->set_short_description(wp_kses_post($fields['short_description']));
        if(!$p->save())return new WP_Error('cv_ai_save','Não foi possível gravar o produto.',['status'=>500]);
        if(isset($fields['meta_description']))update_post_meta($id,'rank_math_description',sanitize_text_field(mb_substr($fields['meta_description'],0,165)));
        if(isset($fields['focus_keyword']))update_post_meta($id,'rank_math_focus_keyword',sanitize_text_field(mb_substr($fields['focus_keyword'],0,220)));
        $history=$p->get_meta('_cv_cfai_history',true);
        if(!is_array($history))$history=[];
        $history[]=['job_id'=>$job,'at'=>gmdate('c'),'before'=>$before];
        $p->update_meta_data('_cv_cfai_history',array_slice($history,-10));
        $p->update_meta_data('_cv_cfai_last_job',$job);
        $p->save_meta_data();
        clean_post_cache($id);
        return rest_ensure_response(['ok'=>true,'product_id'=>$id,'modified_gmt'=>self::modified(wc_get_product($id))]);
    }

    public static function remote($method,$route,$body=null) {
        $url=self::worker_url();
        $token=self::token();
        if(!$url || strlen($token)<40)return new WP_Error('cv_ai_not_ready','Configure URL do Worker e token no wp-config.php.');
        $args=['method'=>$method,'timeout'=>15,'redirection'=>0,
            'headers'=>['Accept'=>'application/json','Content-Type'=>'application/json','X-CV-AI-Token'=>$token]];
        if($body!==null)$args['body']=wp_json_encode($body);
        $response=wp_remote_request($url.'/'.ltrim($route,'/'),$args);
        if(is_wp_error($response))return $response;
        $data=json_decode(wp_remote_retrieve_body($response),true);
        $status=(int)wp_remote_retrieve_response_code($response);
        if($status<200||$status>=300||!is_array($data))
            return new WP_Error('cv_ai_worker_error','Cloudflare HTTP '.$status,['status'=>$status]);
        return $data;
    }

    public static function menu() {
        add_submenu_page('woocommerce','CV AI Enricher','CV AI Enricher','manage_woocommerce',
            'cv-cloudflare-ai',[__CLASS__,'page']);
    }
    public static function page() {
        if(!current_user_can('manage_woocommerce'))return;
        $saved=get_option(self::SETTINGS,[]);
        $url=self::worker_url();
        echo '<div class="wrap"><h1>CV AI Enricher — Cloudflare</h1>';
        echo '<p>Fase de instalação segura. Normalização automática e aplicação desativadas por defeito.</p>';
        echo '<p>Worker: <code>'.esc_html($url?:'Não configurado').'</code>. Token: '.(strlen(self::token())>=40?'Configurado':'Não configurado').'.</p>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        wp_nonce_field('cv_cfai_settings');
        echo '<input type="hidden" name="action" value="cv_cfai_settings">';
        echo '<p><label>URL do Worker <input class="regular-text" name="worker_url" type="url" value="'.esc_attr($saved['worker_url']??'').'"></label></p>';
        submit_button('Guardar URL');
        echo '</form></div>';
    }
    public static function save_settings() {
        if(!current_user_can('manage_woocommerce'))wp_die('Permissão insuficiente.');
        check_admin_referer('cv_cfai_settings');
        $url=esc_url_raw(trim((string)wp_unslash($_POST['worker_url']??'')));
        if($url && (!wp_http_validate_url($url)||wp_parse_url($url,PHP_URL_SCHEME)!=='https'))
            wp_die('O URL do Worker deve ser HTTPS válido.');
        update_option(self::SETTINGS,['worker_url'=>$url],false);
        wp_safe_redirect(admin_url('admin.php?page=cv-cloudflare-ai&updated=1'));
        exit;
    }
}
add_action('plugins_loaded',static function(){
    if(class_exists('WooCommerce') && class_exists('CV_Core_Catalog_Rules'))
        CV_Cloudflare_AI_Enricher::init();
},20);
