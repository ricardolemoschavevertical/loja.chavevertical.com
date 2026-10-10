<?php
/**
 * The WooCommerce admin is the control plane; Cloudflare is only the executor.
 * Based on the original WooCommerce Gemini Enricher's keys, model and MCP UX.
 *
 * IMPORTANT: This is an admin interface, not an automatic normalization daemon.
 * Access requires manage_options and a WP nonce for every mutation.
 */
defined('ABSPATH') || exit;

final class CV_CFAI_Admin {
    private static $notice = '';
    private static $notice_error = false;
    private static $one_time_mcp_token = '';
    private static $models = [];

    public static function init() {
        add_action('admin_init', [__CLASS__, 'process']);
    }

    private static function val($name, $limit=800) {
        $raw = isset($_POST[$name]) ? wp_unslash($_POST[$name]) : '';
        return is_string($raw) ? trim(substr($raw, 0, $limit)) : '';
    }
    private static function tab() {
        $tab=isset($_GET['tab'])?sanitize_key(wp_unslash($_GET['tab'])):'overview';
        return in_array($tab,['overview','providers','lists','mcp','cloudflare'],true)?$tab:'overview';
    }
    private static function url($tab) {
        return admin_url('admin.php?page=cv-cloudflare-ai&tab='.rawurlencode($tab));
    }
    private static function announce($text, $error=false) {
        self::$notice = $text;
        self::$notice_error = $error;
    }
    private static function remote($method,$route,$body=null) {
        if (!class_exists('CV_Cloudflare_AI_Enricher')) return new WP_Error('cv_ai_missing','Plugin não disponível.');
        return CV_Cloudflare_AI_Enricher::remote($method,$route,$body);
    }
    private static function configured() {
        $o=get_option('cv_cfai_public_provider',[]);
        return is_array($o) ? $o : [];
    }
    private static function parse_keys($raw) {
        $entries=[];$lines=preg_split('/\r\n|\r|\n/', trim((string)$raw));
        if(count($lines)>20)return new WP_Error('cv_ai_keys','Máximo de 20 contas Gemini.');
        foreach($lines as $line) {
            $line=trim($line);
            if($line==='')continue;
            $parts=array_map('trim',explode('|',$line));
            $key=$parts[0]??'';
            if(!preg_match('/^[A-Za-z0-9_-]{20,256}$/D',$key))
                return new WP_Error('cv_ai_key','Existe uma chave Gemini com formato inválido.');
            $label=sanitize_text_field($parts[1]??'Conta Gemini');
            $tier=sanitize_key($parts[2]??'auto');
            if(!in_array($tier,['free','paid','auto'],true))$tier='auto';
            $entries[]=['key'=>$key,'label'=>substr($label,0,70),'tier'=>$tier];
        }
        if(count($entries)<1)return new WP_Error('cv_ai_keys','Não foi indicada nenhuma chave Gemini.');
        // One API key per entry; preserve key order as in the original plugin.
        $dedupe=[];
        foreach($entries as $e)$dedupe[$e['key']]=$e;
        return array_values($dedupe);
    }
    private static function sync_keys($cf_token, $keys) {
        $payload=wp_json_encode($keys,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $sent=CV_CFAI_Secrets::put_cloudflare_secret($cf_token,'GEMINI_API_KEYS_JSON',$payload);
        if(is_wp_error($sent))return $sent;
        $safe=[];
        foreach($keys as $entry)$safe[]=[
            'label'=>$entry['label'],
            'tier'=>$entry['tier'],
            'masked'=>'••••'.substr($entry['key'],-4)
        ];
        update_option('cv_cfai_gemini_key_labels',$safe,false);
        return count($safe);
    }
    private static function check($result,$success) {
        if(is_wp_error($result)){self::announce($result->get_error_message(),true);return;}
        self::announce($success);
    }

    public static function process() {
        if(!isset($_POST['cv_cfai_admin_action']))return;
        if(!current_user_can('manage_options'))wp_die('A gestão de credenciais requer permissões de administrador.');
        check_admin_referer('cv_cfai_admin','cv_cfai_nonce');
        nocache_headers();
        $action=sanitize_key(self::val('cv_cfai_admin_action',64));
        $cf_token=self::val('cf_api_token',1024);
        switch($action) {
            case 'pair':
                if(self::val('confirm_pair',16)!=='yes'){
                    self::announce('Confirma a rotação da credencial de ligação WordPress–Cloudflare.',true);
                    break;
                }
                $r=CV_CFAI_Secrets::pair_worker($cf_token);
                self::check($r,'Worker emparelhado; segredo guardado cifrado no WordPress.');break;

            case 'gemini_keys':
                $raw=self::val('gemini_keys',12000);
                $entries=self::parse_keys($raw);
                if(is_wp_error($entries)){self::announce($entries->get_error_message(),true);break;}
                $r=self::sync_keys($cf_token,$entries);
                self::check($r,is_wp_error($r)?'':'Chaves Gemini sincronizadas com os Secrets da Cloudflare ('.(int)$r.' contas).');
                break;

            case 'import_original_keys':
                if(self::val('confirm_original',16)!=='yes'){
                    self::announce('Confirma a sincronização das chaves do plugin original.',true);break;
                }
                $entries=self::parse_keys((string)get_option('wcge_db_keys',''));
                if(is_wp_error($entries)){self::announce('Não foi possível obter chaves Gemini do plugin original.',true);break;}
                $r=self::sync_keys($cf_token,$entries);
                self::check($r,is_wp_error($r)?'':'Contas Gemini do plugin original sincronizadas ('.(int)$r.'). O original mantém as suas definições.');
                break;

            case 'provider':
                $provider=sanitize_key(self::val('ai_provider',24));
                $model=self::val('gemini_model',80);
                $strategy=sanitize_key(self::val('key_strategy',24));
                $queue=self::val('queue_enabled',8)==='yes';
                if(!in_array($provider,['gemini','workers_ai'],true)
                    || !preg_match('/^gemini-[a-zA-Z0-9.-]{3,60}$/D',$model)
                    || !in_array($strategy,['auto','free','paid','primary'],true)) {
                    self::announce('Fornecedor, modelo ou seleção de contas inválidos.',true);break;
                }
                if($queue && self::val('confirm_queue',8)!=='yes'){
                    self::announce('Para ativar uma fila de IA é necessária confirmação adicional.',true);break;
                }
                if($provider==='gemini' && !get_option('cv_cfai_gemini_key_labels',[])) {
                    self::announce('Sincroniza primeiro as chaves Gemini.',true);break;
                }
                $config=[
                    'provider'=>$provider,
                    'gemini_model'=>$model,
                    'key_strategy'=>$strategy,
                    'jobs_enabled'=>$queue,
                    // Never enable product auto-apply from provider settings.
                    'product_apply_enabled'=>false
                ];
                $r=CV_CFAI_Secrets::put_cloudflare_secret($cf_token,'CV_AI_CONFIG_JSON',
                    wp_json_encode($config,JSON_UNESCAPED_SLASHES));
                if(is_wp_error($r)){self::announce($r->get_error_message(),true);break;}
                update_option('cv_cfai_public_provider',$config,false);
                self::announce('Definições sincronizadas com a Cloudflare. A aplicação a produtos continua bloqueada.');
                break;

            case 'detect_models':
                $r=self::remote('GET','/v1/providers/gemini/models');
                if(is_wp_error($r)){self::announce($r->get_error_message(),true);break;}
                self::$models=array_slice(is_array($r['models']??null)?$r['models']:[],0,80);
                self::announce('Modelos disponíveis consultados na Google através da Cloudflare.');
                break;

            case 'test_link':
                $r=self::remote('GET','/v1/lists');
                self::check($r,'Ligação autenticada WordPress–Worker operacional.');
                break;

            case 'new_list':
                $name=sanitize_text_field(self::val('list_name',120));
                $raw=self::val('product_refs',10000);
                if(!$name || !$raw){self::announce('Introduz nome da lista e IDs/SKUs.',true);break;}
                $refs=preg_split('/[\s,;]+/u',$raw,-1,PREG_SPLIT_NO_EMPTY);
                if(count($refs)>250){self::announce('O máximo por lista é de 250 produtos.',true);break;}
                $items=[];$error='';
                foreach($refs as $ref){
                    $pid=ctype_digit($ref)?absint($ref):wc_get_product_id_by_sku(sanitize_text_field($ref));
                    $p=$pid?wc_get_product($pid):false;
                    if(!$p || $p->is_type('variation')){$error='Referência inexistente ou variação: '.substr($ref,0,70);break;}
                    if(!class_exists('CV_Core_Catalog_Rules')){$error='Política central de categorias indisponível.';break;}
                    $check=CV_Core_Catalog_Rules::product_status($pid,'enricher');
                    if(is_wp_error($check)||empty($check['ok'])){
                        $error='Produto '.$pid.' fora das categorias autorizadas.';break;
                    }
                    $items[$pid]=['product_id'=>(int)$pid,'sku'=>$p->get_sku('edit')];
                }
                if($error){self::announce($error,true);break;}
                $r=self::remote('POST','/v1/lists',['name'=>$name,'items'=>array_values($items)]);
                self::check($r,'Lista criada na D1; nenhum produto foi modificado ou colocado em processamento.');
                break;

            case 'pause_list':
            case 'start_list':
                $id=self::val('list_id',48);
                if(!preg_match('/^[a-f0-9-]{36}$/Di',$id)){self::announce('ID da lista inválido.',true);break;}
                if($action==='start_list' && self::val('confirm_queue',8)!=='yes'){
                    self::announce('Confirma o arranque da fila.',true);break;
                }
                $r=self::remote('POST','/v1/lists/'.$id.'/'.($action==='start_list'?'start':'pause'),[]);
                self::check($r,$action==='start_list'?'Lista enviada para a Queue.':'Lista colocada em pausa.');
                break;

            case 'mcp_generate':
                if(defined('CV_MCP_BRIDGE_TOKEN')&&strlen((string)CV_MCP_BRIDGE_TOKEN)>=40){
                    self::announce('O token MCP atual é gerido em wp-config.php. Não é possível rodá-lo neste painel.',true);break;
                }
                self::$one_time_mcp_token=CV_CFAI_Secrets::rotate_mcp_token();
                self::announce('Token MCP criado. Copia-o agora: não ficará visível depois de saíres desta página.');
                break;

            case 'mcp_revoke':
                if(defined('CV_MCP_BRIDGE_TOKEN')&&strlen((string)CV_MCP_BRIDGE_TOKEN)>=40){
                    self::announce('Revoga primeiro a constante CV_MCP_BRIDGE_TOKEN no wp-config.php.',true);break;
                }
                CV_CFAI_Secrets::revoke_mcp_token();
                self::announce('Token MCP revogado; novas chamadas serão recusadas.');
                break;

            default:
                self::announce('Ação desconhecida.',true);
        }
    }

    private static function opening($action,$tab) {
        echo '<form method="post" action="'.esc_url(self::url($tab)).'">';
        wp_nonce_field('cv_cfai_admin','cv_cfai_nonce');
        echo '<input type="hidden" name="cv_cfai_admin_action" value="'.esc_attr($action).'">';
    }

    private static function end($label, $class='primary') {
        submit_button($label,$class);
        echo '</form>';
    }

    private static function help($text) {
        echo '<p class="description">'.esc_html($text).'</p>';
    }

    private static function cf_input() {
        echo '<p><label><strong>Token da API Cloudflare (só para esta operação)</strong><br>';
        echo '<input type="password" autocomplete="new-password" spellcheck="false" name="cf_api_token" class="regular-text" required>';
        echo '</label></p>';
        self::help('Necessita de permissão limitada de escrita nos Secrets do Worker cv-ai-enricher. O token não é guardado na BD do WordPress.');
    }

    public static function render() {
        if(!current_user_can('manage_options'))wp_die('Acesso reservado a administradores.');
        $tab=self::tab();
        $tabs=['overview'=>'Painel','providers'=>'Motores e API Keys','lists'=>'Listas e Fila','mcp'=>'ChatGPT / MCP','cloudflare'=>'Cloudflare / Ligação'];
        $worker=CV_Cloudflare_AI_Enricher::worker_url();
        $paired=(bool)CV_CFAI_Secrets::open();
        $mcp=CV_CFAI_Secrets::mcp_token_ready();
        echo '<div class="wrap"><h1>CV AI Enricher — WooCommerce e Cloudflare</h1>';
        echo '<p>Gestão central no WooCommerce; Cloudflare executa tarefas e armazena listas. O MCP original mantém-se disponível.</p>';
        echo '<nav class="nav-tab-wrapper" aria-label="Separadores CV AI Enricher">';
        foreach($tabs as $name=>$label)echo '<a class="nav-tab '.($tab===$name?'nav-tab-active':'').'" href="'.esc_url(self::url($name)).'">'.esc_html($label).'</a>';
        echo '</nav>';
        if(self::$notice){
            echo '<div class="notice '.(self::$notice_error?'notice-error':'notice-success').'"><p>'.esc_html(self::$notice).'</p></div>';
        }
        echo '<div style="max-width:1100px;margin-top:18px;">';
        if($tab==='overview')self::overview($worker,$paired,$mcp);
        if($tab==='providers')self::providers();
        if($tab==='lists')self::lists($paired);
        if($tab==='mcp')self::mcp($mcp);
        if($tab==='cloudflare')self::cloudflare($worker,$paired);
        echo '</div></div>';
    }

    private static function overview($worker,$paired,$mcp) {
        $health=[];
        if($worker) {
            $response=wp_remote_get($worker.'/health',['timeout'=>5,'redirection'=>0]);
            if(!is_wp_error($response) && wp_remote_retrieve_response_code($response)===200)
                $health=json_decode(wp_remote_retrieve_body($response),true) ?: [];
        }
        echo '<h2>Estado da integração</h2><table class="widefat striped"><tbody>';
        $rows=[
            'WooCommerce'=>'Origem de produtos, categorias e painel de controlo',
            'Worker'=>($health['ok']??false)?'Online — '.($health['version']??''):'Não verificado',
            'Credencial de ligação'=>$paired?'Emparelhada (cifrada)':'Por emparelhar',
            'Fornecedor IA'=>esc_html($health['provider']??'Workers AI (padrão)'),
            'Processamento IA'=>!empty($health['processing_enabled'])?'Ligado':'Desligado (seguro)',
            'Aplicação automática'=>!empty($health['product_apply_enabled'])?'Ativa no Worker (WordPress mantém bloqueio)':'Bloqueada',
            'MCP novo'=>$mcp?'Token configurado':'Sem token; acesso recusado'
        ];
        foreach($rows as $k=>$v)echo '<tr><th style="width:240px">'.esc_html($k).'</th><td>'.esc_html($v).'</td></tr>';
        echo '</tbody></table>';
        echo '<h2>Arquitetura</h2><p><strong>WooCommerce</strong> → Worker Cloudflare → D1/Queues → Gemini ou Workers AI → proposta para rever no WooCommerce.</p>';
        echo '<p><strong>ChatGPT</strong> → Endpoint MCP WordPress → ferramentas autorizadas → listas e produtos; nunca diretamente à D1.</p>';
        echo '<p><a class="button" href="'.esc_url(self::url('providers')).'">Gerir Gemini</a> ';
        echo '<a class="button" href="'.esc_url(self::url('mcp')).'">Gerir ligação ChatGPT</a></p>';
    }

    private static function providers() {
        $saved=self::configured();
        $keys=get_option('cv_cfai_gemini_key_labels',[]);
        if(!is_array($keys))$keys=[];
        echo '<h2>Contas e chaves Gemini</h2><p>Tal como no plugin original, podes usar várias chaves com rótulos e prioridade Free/Paid. As chaves são enviadas para os Secrets Cloudflare e não ficam guardadas aqui.</p>';
        if($keys){
            echo '<table class="widefat striped"><thead><tr><th>Conta</th><th>Plano</th><th>Chave</th></tr></thead><tbody>';
            foreach($keys as $k)echo '<tr><td>'.esc_html($k['label']??'').'</td><td>'.esc_html(strtoupper($k['tier']??'auto')).'</td><td><code>'.esc_html($k['masked']??'').'</code></td></tr>';
            echo '</tbody></table>';
        }
        self::opening('gemini_keys','providers');
        echo '<p><label><strong>Substituir a lista completa de chaves Gemini</strong><br>';
        echo '<textarea name="gemini_keys" class="large-text code" rows="6" autocomplete="off" spellcheck="false" required placeholder="CHAVE | Nome da conta | free"></textarea></label></p>';
        self::help('Formato do plugin original: CHAVE | Rótulo | free ou paid. Uma linha por conta. Não volta a mostrar as chaves depois de guardar.');
        self::cf_input();
        self::end('Sincronizar chaves na Cloudflare');
        echo '<hr><h3>Importar chaves já existentes no plugin original</h3>';
        self::opening('import_original_keys','providers');
        echo '<p><label><input type="checkbox" name="confirm_original" value="yes" required> Importar as chaves Gemini existentes sem alterar o plugin original.</label></p>';
        self::cf_input();
        self::end('Importar do Gemini Enricher 4.10.2','secondary');
        echo '<hr><h2>Motor e modelo para a fila</h2>';
        self::opening('provider','providers');
        echo '<table class="form-table"><tbody><tr><th>Fornecedor</th><td><select name="ai_provider">';
        foreach(['workers_ai'=>'Cloudflare Workers AI','gemini'=>'Google Gemini'] as $value=>$label)
            echo '<option value="'.esc_attr($value).'" '.selected($saved['provider']??'workers_ai',$value,false).'>'.esc_html($label).'</option>';
        echo '</select></td></tr>';
        echo '<tr><th>Modelo Gemini</th><td><input name="gemini_model" class="regular-text" value="'.esc_attr($saved['gemini_model']??'gemini-2.5-flash').'" required>';
        self::help('Identificador de um modelo que suporta generateContent, por exemplo gemini-2.5-flash.');echo '</td></tr>';
        echo '<tr><th>Seleção de contas</th><td><select name="key_strategy">';
        foreach(['auto'=>'Automático (free primeiro)','free'=>'Apenas Free','paid'=>'Apenas Paid','primary'=>'Primeira conta'] as $v=>$label)
            echo '<option value="'.esc_attr($v).'" '.selected($saved['key_strategy']??'auto',$v,false).'>'.esc_html($label).'</option>';
        echo '</select></td></tr>';
        echo '<tr><th>Fila de normalização</th><td><label><input type="checkbox" name="queue_enabled" value="yes" '.checked(!empty($saved['jobs_enabled']),true,false).'> Permitir processar tarefas na Cloudflare</label>';
        echo '<p><label><input type="checkbox" name="confirm_queue" value="yes"> Confirmo que esta alteração pode iniciar chamadas pagas à IA.</label></p>';
        self::help('A aplicação direta aos produtos continua desativada, mesmo com a fila ligada.');echo '</td></tr></tbody></table>';
        self::cf_input();
        self::end('Guardar fornecedor e modelo');
        echo '<hr><h3>Detetar modelos Gemini</h3>';
        self::opening('detect_models','providers');
        self::end('Consultar modelos disponíveis através da Cloudflare','secondary');
        if(self::$models){
            echo '<ul style="columns:2">';
            foreach(self::$models as $model)echo '<li><code>'.esc_html(is_array($model)?($model['name']??''):$model).'</code></li>';
            echo '</ul>';
        }
    }

    private static function lists($paired) {
        echo '<h2>Listas de normalização e fila</h2>';
        if(!$paired){echo '<div class="notice notice-warning inline"><p>Emparelha primeiro o Worker no separador Cloudflare.</p></div>';return;}
        $data=self::remote('GET','/v1/lists');
        if(is_wp_error($data))echo '<p>'.esc_html($data->get_error_message()).'</p>';
        else {
            $lists=is_array($data['lists']??null)?$data['lists']:[];
            echo '<table class="widefat striped"><thead><tr><th>Lista</th><th>Produtos</th><th>Estado</th><th>Em revisão</th><th>Aplicados</th><th>Falhas</th><th>Ações</th></tr></thead><tbody>';
            if(!$lists)echo '<tr><td colspan="7">Ainda não existem listas Cloudflare.</td></tr>';
            foreach(array_slice($lists,0,80) as $item){
                $id=sanitize_text_field($item['id']??'');
                echo '<tr><td>'.esc_html($item['name']??'').'</td><td>'.(int)($item['total']??0).'</td><td>'.esc_html($item['status']??'').'</td><td>'.(int)($item['review']??0).'</td><td>'.(int)($item['applied']??0).'</td><td>'.(int)($item['failed']??0).'</td><td>';
                echo '<a href="'.esc_url(add_query_arg('list_id',$id,self::url('lists'))).'">Ver tarefas</a>';
                echo '</td></tr>';
            }
            echo '</tbody></table>';
        }
        echo '<h3>Nova lista</h3>';
        self::opening('new_list','lists');
        echo '<p><label>Nome <input name="list_name" maxlength="120" class="regular-text" required></label></p>';
        echo '<p><label>IDs ou SKUs, um por linha<br><textarea name="product_refs" class="large-text code" rows="5" required></textarea></label></p>';
        self::help('Até 250 produtos por lista. Cada produto é validado na árvore real de categorias WooCommerce; não são criadas categorias.');
        self::end('Criar lista na D1 (sem iniciar IA)');
        $id=isset($_GET['list_id'])?sanitize_text_field(wp_unslash($_GET['list_id'])):'';
        if(!preg_match('/^[a-f0-9-]{36}$/Di',$id))return;
        echo '<hr><h3>Tarefas da lista '.esc_html($id).'</h3>';
        $jobs=self::remote('GET','/v1/lists/'.$id.'/jobs');
        if(is_wp_error($jobs))echo '<p>'.esc_html($jobs->get_error_message()).'</p>';
        else {
            echo '<table class="widefat striped"><thead><tr><th>Tarefa</th><th>Produto</th><th>Estado</th><th>Tentativas</th><th>Erro</th></tr></thead><tbody>';
            foreach(array_slice((array)($jobs['jobs']??[]),0,100) as $job)
                echo '<tr><td><code>'.esc_html($job['id']??'').'</code></td><td>'.(int)($job['product_id']??0).'</td><td>'.esc_html($job['state']??'').'</td><td>'.(int)($job['attempts']??0).'</td><td>'.esc_html($job['error']??'').'</td></tr>';
            echo '</tbody></table>';
        }
        self::opening('start_list','lists');
        echo '<input type="hidden" name="list_id" value="'.esc_attr($id).'">';
        echo '<p><label><input type="checkbox" name="confirm_queue" value="yes" required> Iniciar o processamento desta lista, sujeito à autorização da Cloudflare.</label></p>';
        self::end('Iniciar fila de tarefas','secondary');
        self::opening('pause_list','lists');
        echo '<input type="hidden" name="list_id" value="'.esc_attr($id).'">';
        self::end('Pausar lista','secondary');
    }

    private static function mcp($mcp) {
        echo '<h2>Ligação MCP ao ChatGPT</h2>';
        echo '<p>O MCP é servido pelo WordPress e consulta o WooCommerce. A Cloudflare não substitui a ligação ChatGPT–WooCommerce.</p>';
        echo '<table class="widefat striped"><tbody>';
        echo '<tr><th>Endpoint MCP novo</th><td><code>'.esc_html(rest_url('cv-mcp/v1/mcp')).'</code></td></tr>';
        echo '<tr><th>Autenticação do MCP novo</th><td>'.($mcp?'Bearer configurado; token oculto':'Sem token; acesso recusado').'</td></tr>';
        $legacy=function_exists('wcge_chatgpt_mcp_endpoint');
        echo '<tr><th>MCP original (OAuth / Bearer)</th><td>'.($legacy
            ?'<code>'.esc_html(wcge_chatgpt_mcp_endpoint()).'</code>'
            :'Não encontrado como plugin ativo nesta instalação.').'</td></tr>';
        echo '</tbody></table>';
        if($legacy)echo '<p><a class="button button-secondary" href="'.esc_url(admin_url('options-general.php?page=wcge-settings')).'">Abrir definições MCP original</a></p>';
        echo '<p><strong>Compatibilidade:</strong> o Bridge novo suporta autenticação Bearer, mas ainda não oferece paridade integral de ferramentas nem OAuth. Não substituas uma ligação ChatGPT MCP ativa sem validar o método de autenticação do cliente.</p>';
        if(self::$one_time_mcp_token!==''){
            echo '<div class="notice notice-warning inline"><p><strong>Token MCP — copiar agora:</strong></p>';
            echo '<p><input type="text" class="large-text code" readonly autocomplete="off" value="'.esc_attr(self::$one_time_mcp_token).'"></p>';
            echo '<p>O WordPress guarda apenas o hash SHA-256; não é possível consultar novamente o token.</p></div>';
        }
        self::opening('mcp_generate','mcp');
        self::end($mcp?'Rodar token do novo MCP':'Gerar token do novo MCP','secondary');
        self::opening('mcp_revoke','mcp');
        self::end('Revogar token do novo MCP','secondary');
        echo '<h3>Permissões</h3><p>O novo MCP aceita apenas ferramentas com autorização Bearer. Escritas continuam bloqueadas pelo servidor até aprovação explícita e ativação controlada.</p>';
    }

    private static function cloudflare($worker,$paired) {
        echo '<h2>Ligação segura à Cloudflare</h2>';
        echo '<p><strong>Worker:</strong> <code>'.esc_html($worker?:'Não definido').'</code></p>';
        echo '<p><strong>Estado do segredo partilhado:</strong> '.($paired?'Configurado (cifrado no WordPress)':'Por configurar').'</p>';
        echo '<p><strong>Conta Cloudflare:</strong> <code>'.esc_html(CV_CFAI_Secrets::ACCOUNT).'</code> | <strong>Script:</strong> <code>'.esc_html(CV_CFAI_Secrets::WORKER).'</code></p>';
        echo '<p>O token Cloudflare que introduzires abaixo é usado apenas para sincronizar segredos. Não fica na base de dados nem é colocado no GitHub.</p>';
        self::opening('pair','cloudflare');
        self::cf_input();
        echo '<p><label><input type="checkbox" value="yes" name="confirm_pair" required> Confirmo a criação/rotação da credencial de ligação WordPress–Cloudflare.</label></p>';
        self::end($paired?'Rodar e emparelhar novo segredo':'Emparelhar WordPress com Worker');
        echo '<hr>';
        self::opening('test_link','cloudflare');
        self::end('Testar autenticação e listar tarefas','secondary');
        echo '<p>Para criar um API Token Cloudflare, concede apenas as permissões necessárias sobre o Worker. Nunca uses a chave global da conta.</p>';
    }
}
