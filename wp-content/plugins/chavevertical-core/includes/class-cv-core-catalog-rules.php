<?php
/**
 * WooCommerce live category policy shared by Chave Vertical AI Enricher and MCP.
 * Categories are always read from the current product_cat taxonomy, never JSON.
 */
defined('ABSPATH') || exit;

final class CV_Core_Catalog_Rules {
    const OPTION = 'cv_core_catalog_allowed_roots';
    const SCOPES = ['enricher', 'mcp'];
    private static $cache = [];

    public static function init() {
        add_action('rest_api_init', [__CLASS__, 'routes']);
        add_action('admin_menu', [__CLASS__, 'menu'], 30);
        add_action('admin_post_cv_core_catalog_roots', [__CLASS__, 'save_admin']);
        add_action('created_product_cat', [__CLASS__, 'invalidate_cache']);
        add_action('edited_product_cat', [__CLASS__, 'invalidate_cache']);
        add_action('delete_product_cat', [__CLASS__, 'invalidate_cache']);
    }
    public static function invalidate_cache() { self::$cache = []; }

    public static function roots($scope) {
        $policies = get_option(self::OPTION, []);
        $raw = is_array($policies) && isset($policies[$scope]) && is_array($policies[$scope]) ? $policies[$scope] : [];
        $ids = array_values(array_unique(array_filter(array_map('absint', $raw))));
        sort($ids, SORT_NUMERIC);
        return $ids;
    }
    public static function save_roots($data) {
        $next = [];
        foreach (self::SCOPES as $scope) {
            $value = isset($data[$scope]) && is_array($data[$scope]) ? $data[$scope] : [];
            $next[$scope] = array_values(array_unique(array_filter(array_map('absint', $value))));
            sort($next[$scope], SORT_NUMERIC);
        }
        update_option(self::OPTION, $next, false);
        self::invalidate_cache();
    }
    public static function tree($scope = 'enricher') {
        if (!in_array($scope, self::SCOPES, true)) {
            return new WP_Error('cv_catalog_scope', 'Âmbito inválido.', ['status'=>400]);
        }
        if (isset(self::$cache[$scope])) return self::$cache[$scope];
        if (!taxonomy_exists('product_cat')) {
            return new WP_Error('cv_catalog_taxonomy', 'WooCommerce indisponível.', ['status'=>503]);
        }
        $terms = get_terms(['taxonomy'=>'product_cat','hide_empty'=>false,'orderby'=>'term_id','order'=>'ASC']);
        if (is_wp_error($terms)) return $terms;
        $by_id = [];
        foreach ($terms as $term) {
            $id = (int) $term->term_id;
            $by_id[$id] = ['id'=>$id,'parent'=>(int)$term->parent,'name'=>(string)$term->name,'slug'=>(string)$term->slug];
        }
        ksort($by_id, SORT_NUMERIC);
        $default = (int) get_option('default_product_cat', 0);
        $roots = self::roots($scope);
        $categories = [];
        foreach ($by_id as $id=>$row) {
            $names=[]; $ancestors=[]; $visited=[]; $cursor=$id; $integrity=true;
            for ($n=0; $n<64 && $cursor; $n++) {
                if (isset($visited[$cursor]) || !isset($by_id[$cursor])) { $integrity=false; break; }
                $visited[$cursor]=true;
                $names[]=$by_id[$cursor]['name'];
                if ($cursor!==$id) $ancestors[]=$cursor;
                $cursor=$by_id[$cursor]['parent'];
            }
            if ($cursor) $integrity=false;
            $ancestors=array_reverse($ancestors);
            $allowed=$integrity && $id!==$default && (!$roots || (bool)array_intersect($roots,array_merge($ancestors,[$id])));
            $categories[]=$row+[
                'path'=>implode(' > ',array_reverse($names)),
                'ancestors'=>$ancestors,'depth'=>count($ancestors),
                'is_default'=>$id===$default,
                'allowed'=>$allowed,'integrity_ok'=>$integrity
            ];
        }
        self::$cache[$scope]=[
            'ok'=>true,'source'=>'woocommerce_live_product_cat','scope'=>$scope,
            'default_category_id'=>$default,'allowed_root_ids'=>$roots,
            'version'=>hash('sha256',wp_json_encode([$by_id,$default,$roots],JSON_UNESCAPED_UNICODE)),
            'total'=>count($categories),'categories'=>$categories
        ];
        return self::$cache[$scope];
    }
    public static function check_ids($ids, $scope = 'enricher') {
        $tree = self::tree($scope);
        if (is_wp_error($tree)) return $tree;
        if (!is_array($ids)) return new WP_Error('cv_catalog_invalid_ids','Categorias inválidas.',['status'=>422]);
        $map=[];
        foreach ($tree['categories'] as $category) $map[$category['id']]=$category;
        $valid=[];$invalid=[];$defaults=[];
        foreach ($ids as $raw) {
            if (!(is_int($raw) || (is_string($raw) && ctype_digit($raw))) || (int)$raw<1) {
                $invalid[]=$raw;continue;
            }
            $id=(int)$raw;
            if ($id===$tree['default_category_id']) {$defaults[$id]=$id;continue;}
            if (!isset($map[$id]) || !$map[$id]['allowed']) {$invalid[]=$id;continue;}
            $valid[$id]=$id;
        }
        $valid=array_values($valid);sort($valid,SORT_NUMERIC);
        return [
            'ok'=>!$invalid && (bool)$valid,'scope'=>$scope,
            'valid_category_ids'=>$valid,'invalid_category_ids'=>array_values($invalid),
            'ignored_default_ids'=>array_values($defaults),
            'category_paths'=>array_values(array_map(static function($id)use($map){return $map[$id]['path'];},$valid)),
            'tree_version'=>$tree['version']
        ];
    }
    public static function product_status($product_id,$scope='enricher') {
        $product=wc_get_product(absint($product_id));
        if (!$product) return new WP_Error('cv_catalog_missing_product','Produto inexistente.',['status'=>404]);
        $ids=wp_get_post_terms($product->get_id(),'product_cat',['fields'=>'ids']);
        if (is_wp_error($ids)) return $ids;
        $state=self::check_ids(array_map('absint',$ids),$scope);
        if (is_wp_error($state)) return $state;
        $tree=self::tree($scope);
        $map=[];foreach ($tree['categories'] as $category) $map[$category['id']]=$category;
        $snapshot=[];
        foreach ($state['valid_category_ids'] as $id) {
            $path=array_merge($map[$id]['ancestors'],[$id]);
            $snapshot[]=array_map(static function($x)use($map){
                return [$x,$map[$x]['parent']??null,$map[$x]['slug']??null,$map[$x]['name']??null];
            },$path);
        }
        $state['category_ids']=array_values(array_unique(array_map('absint',$ids)));
        sort($state['category_ids'],SORT_NUMERIC);
        $state['category_signature']=hash('sha256',wp_json_encode([
            $scope,$state['category_ids'],$snapshot,$tree['default_category_id'],
            $tree['allowed_root_ids'],$state['invalid_category_ids']
        ],JSON_UNESCAPED_UNICODE));
        if (!$state['ok']) $state['reason']='Produto sem categoria válida/autorizada na árvore atual WooCommerce.';
        return $state;
    }
    public static function routes() {
        register_rest_route('cv-catalog/v1','/categories',[
            'methods'=>'GET','permission_callback'=>'__return_true',
            'callback'=>static function($request) {
                $scope=(string)($request->get_param('scope')?:'enricher');
                $result=self::tree($scope);
                if (is_wp_error($result)) return $result;
                $response=rest_ensure_response($result);
                $response->header('Cache-Control','no-store, max-age=0');
                return $response;
            }
        ]);
        register_rest_route('cv-catalog/v1','/validate',[
            'methods'=>'POST','permission_callback'=>static function(){return current_user_can('manage_woocommerce');},
            'callback'=>static function($request) {
                $scope=(string)($request->get_param('scope')?:'enricher');
                $id=$request->get_param('product_id');
                $result=$id ? self::product_status(absint($id),$scope) : self::check_ids($request->get_param('category_ids'),$scope);
                return is_wp_error($result)?$result:rest_ensure_response($result);
            }
        ]);
    }
    public static function menu() {
        add_submenu_page('chave-vertical','Categorias autorizadas','Categorias autorizadas','manage_woocommerce','cv-catalog-rules',[__CLASS__,'admin_page']);
    }
    public static function save_admin() {
        if (!current_user_can('manage_woocommerce')) wp_die('Sem permissões.');
        check_admin_referer('cv_core_catalog_roots');
        $tree=self::tree('enricher');
        if (is_wp_error($tree)) wp_die(esc_html($tree->get_error_message()));
        $ids=array_fill_keys(array_column($tree['categories'],'id'),true);
        $policy=[];
        foreach (self::SCOPES as $scope) {
            $values=isset($_POST['roots'][$scope]) && is_array($_POST['roots'][$scope]) ? wp_unslash($_POST['roots'][$scope]) : [];
            $policy[$scope]=[];
            foreach ($values as $value) {
                $id=absint($value);
                if ($id && $id!==$tree['default_category_id'] && isset($ids[$id])) $policy[$scope][]=$id;
            }
        }
        self::save_roots($policy);
        wp_safe_redirect(admin_url('admin.php?page=cv-catalog-rules&saved=1'));exit;
    }
    public static function admin_page() {
        if (!current_user_can('manage_woocommerce')) return;
        $tree=self::tree('enricher');
        echo '<div class="wrap"><h1>Categorias autorizadas</h1><p>A árvore WooCommerce é lida diretamente de product_cat, incluindo categorias vazias. Não existe ficheiro JSON de categorias.</p>';
        if (isset($_GET['saved'])) echo '<div class="notice notice-success"><p>Políticas guardadas.</p></div>';
        if (is_wp_error($tree)) {echo '<p>'.esc_html($tree->get_error_message()).'</p></div>';return;}
        echo '<p>'.absint($tree['total']).' categorias. Versão: <code>'.esc_html(substr($tree['version'],0,12)).'</code>. Sem seleção, todas as categorias existentes (exceto Predefinido) são autorizadas.</p>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        wp_nonce_field('cv_core_catalog_roots');
        echo '<input type="hidden" name="action" value="cv_core_catalog_roots">';
        foreach (self::SCOPES as $scope) {
            $roots=self::roots($scope);
            echo '<p><label><strong>'.esc_html($scope==='mcp'?'MCP / agentes':'AI Enricher').'</strong><br><select name="roots['.esc_attr($scope).'][]" multiple size="12" style="min-width:580px;max-width:100%">';
            foreach ($tree['categories'] as $cat) {
                if ($cat['is_default'] || !$cat['integrity_ok']) continue;
                echo '<option value="'.absint($cat['id']).'" '.selected(in_array($cat['id'],$roots,true),true,false).'>'.esc_html($cat['path']).' (ID '.absint($cat['id']).')</option>';
            }
            echo '</select></label></p>';
        }
        submit_button('Guardar permissões por ramos');
        echo '</form></div>';
    }
}
