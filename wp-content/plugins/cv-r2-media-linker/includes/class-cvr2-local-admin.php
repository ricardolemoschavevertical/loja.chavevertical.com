<?php
defined( 'ABSPATH' ) || exit;

final class CVR2_Local_Admin {
    public static function render(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }
        $nonce = wp_create_nonce( 'cvr2_local' );
        $fields = CVR2_Local_CSV::fields();
        ?>
        <h2>Catálogo local — importar mais depressa, com imagens em separado</h2>
        <p>O catálogo é guardado num ficheiro JSONL privado <strong>no servidor da loja</strong>, não no browser.
        O ficheiro conserva as referências das imagens R2 e, no caso da origem REST, todas as variações;
        não transfere nem duplica os próprios ficheiros de imagem.</p>
        <div class="cvr2-local-grid">
            <section class="cvr2-card">
                <h3>1. Preparar catálogo a partir do site original</h3>
                <p>Consulta WooCommerce REST e guarda os dados completos no disco. Os produtos ainda não são alterados.</p>
                <button class="button button-primary" id="cvr2-local-rest">Preparar catálogo REST</button>
                <p class="description">Se existirem produtos variáveis, as respetivas variações também ficam no ficheiro.</p>
            </section>
            <section class="cvr2-card">
                <h3>Alternativa: importar CSV do Windows</h3>
                <p>Carrega o CSV, associa cada campo às colunas pretendidas e prepara um ficheiro local.</p>
                <input id="cvr2-local-csv" type="file" accept=".csv,text/csv">
                <button class="button" id="cvr2-local-upload">Carregar e pré-visualizar CSV</button>
                <div id="cvr2-local-csv-preview" hidden>
                    <h4>Pré-visualização (5 linhas)</h4>
                    <div id="cvr2-local-csv-table" style="overflow-x:auto;max-width:100%"></div>
                    <h4>Escolher as colunas a importar</h4>
                    <p class="description"><strong>SKU obrigatório.</strong> Um campo não selecionado nunca é alterado nos produtos existentes. Para produtos novos, selecionar também Nome e Slug.</p>
                    <div id="cvr2-local-mapping" class="cvr2-local-mapping"></div>
                    <button class="button button-primary" id="cvr2-local-csv-start">Preparar ficheiro a partir das colunas selecionadas</button>
                </div>
            </section>
            <section class="cvr2-card">
                <h3>2. Importar produtos a partir do ficheiro</h3>
                <p>Grava no WooCommerce os produtos, slugs, stock, preços e SEO <strong>sem tocar nas imagens</strong>.</p>
                <button class="button button-primary" id="cvr2-local-products">Importar produtos sem imagens</button>
                <p><strong>Categorias:</strong> apenas categorias que já existem no destino. Se faltar alguma, o produto é assinalado com erro e não é criado.</p>
            </section>
            <section class="cvr2-card">
                <h3>3. Associar imagens do R2</h3>
                <p>Lê as referências guardadas no ficheiro e utiliza a associação de imagens já existente neste plugin.</p>
                <button class="button button-primary" id="cvr2-local-images">Associar imagens mais tarde</button>
                <p class="description">Esta fase não volta a importar preços, stock ou descrições. Não apaga imagens se a referência estiver vazia.</p>
            </section>
        </div>
        <div class="cvr2-card" style="margin-top:16px">
            <h3>Estado, retoma e relatório</h3>
            <div class="cvr2-local-controls">
                <button class="button" id="cvr2-local-resume">Retomar operação</button>
                <button class="button" id="cvr2-local-pause">Pausar / continuar importação</button>
                <button class="button" id="cvr2-local-refresh">Atualizar estado</button>
                <button class="button" id="cvr2-local-report">Descarregar relatório CSV completo</button>
                <button class="button button-link-delete" id="cvr2-local-delete">Eliminar ficheiro local e estado</button>
            </div>
            <p id="cvr2-local-snapshot-info">Sem catálogo preparado.</p>
            <p id="cvr2-local-import-info">Sem importação ativa.</p>
            <progress id="cvr2-local-progress" max="100" value="0" style="width:100%;height:18px"></progress>
            <pre id="cvr2-local-log" style="white-space:pre-wrap;max-height:350px;overflow:auto;padding:12px;background:#f6f7f7">Pronto.</pre>
            <p class="description">As operações são processadas em pequenos lotes e guardam o progresso.
            Mantém este separador aberto durante o processamento; se o browser fechar, utiliza «Retomar operação».
            O ficheiro privado é substituído quando preparas outro catálogo. Não executar importações simultâneas.</p>
        </div>
        <style>
            .cvr2-local-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;max-width:1600px}
            .cvr2-local-controls{display:flex;flex-wrap:wrap;gap:10px}
            .cvr2-local-mapping{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px 24px;margin-bottom:14px}
            .cvr2-local-mapping label{display:flex;gap:8px;justify-content:space-between;align-items:center}
            .cvr2-local-mapping select{min-width:45%;max-width:65%}
            @media(max-width:900px){.cvr2-local-grid,.cvr2-local-mapping{grid-template-columns:1fr}}
        </style>
        <script>
        (() => {
            const nonce = <?php echo wp_json_encode( $nonce ); ?>;
            const fields = <?php echo wp_json_encode( $fields, JSON_UNESCAPED_UNICODE ); ?>;
            let upload = null, snapshot = null, current = null, active = false, stopped = false;
            const byId = (id) => document.getElementById(id);
            const write = (s) => { byId('cvr2-local-log').textContent = String(s || ''); };
            const sleep = (ms) => new Promise(resolve => window.setTimeout(resolve, ms));
            async function call(action, args = {}, file = null) {
                const body = new FormData();
                body.set('action', action);
                body.set('nonce', nonce);
                for (const [key,value] of Object.entries(args)) body.set(key, String(value));
                if (file) body.set('file', file, file.name);
                const response = await fetch(ajaxurl, {method:'POST',credentials:'same-origin',body});
                const json = await response.json();
                if (!json.success) throw Error(String(json.data?.message || 'Falhou o pedido ao WordPress.'));
                return json.data;
            }
            const format = (v) => Number(v || 0).toLocaleString('pt-PT');
            const normalized = (x) => String(x).normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().replace(/[^a-z0-9]/g,'');
            function display(snap, imp) {
                snapshot = snap || {};
                current = imp || {};
                byId('cvr2-local-snapshot-info').textContent = snapshot.id
                    ? 'Catálogo: ' + (snapshot.source === 'csv' ? 'CSV' : 'REST') +
                      ' | Estado: ' + (snapshot.status || '?') + ' | Registos preparados: ' + format(snapshot.records) +
                      (snapshot.total ? ' / ' + format(snapshot.total) : '') +
                      ' | Ficheiro: ' + format(snapshot.bytes) + ' bytes'
                    : 'Sem catálogo preparado.';
                byId('cvr2-local-import-info').textContent = current.run_id
                    ? 'Importação: ' + (current.phase === 'images' ? 'imagens' : 'produtos') +
                      ' | Estado: ' + current.status + ' | ' + format(current.processed) + '/' + format(current.total) +
                      ' | Concluídos: ' + format(current.success) + ' | Erros: ' + format(current.failed) +
                      ' | Ignorados: ' + format(current.skipped)
                    : 'Sem importação ativa.';
                const total = current.status === 'running' || current.status === 'paused' || current.status === 'done'
                    ? Number(current.total || 0) : Number(snapshot.total || 0);
                const count = current.status === 'running' || current.status === 'paused' || current.status === 'done'
                    ? Number(current.processed || 0) : Number(snapshot.records || 0);
                byId('cvr2-local-progress').value = total ? Math.min(100, Math.round(100 * count / total)) : 0;
                const recent = Array.isArray(current.recent) ? current.recent : [];
                if (recent.length) write(recent.map(row => [row.result, row.sku, row.name, row.message].filter(Boolean).join(' | ')).join('\n'));
            }
            async function refresh() {
                const state = await call('cvr2_local_status');
                display(state.snapshot, state.import);
                return state;
            }
            function launch(fn) {
                if (active) { write('Já existe uma operação neste separador.'); return; }
                active = true; stopped = false;
                fn().catch(e => write('Operação interrompida: ' + e.message + '. O checkpoint ficou guardado.'))
                    .finally(() => { active = false; });
            }
            async function prepareLoop() {
                while (!stopped) {
                    const data = await call('cvr2_local_prepare_step');
                    display(data, current);
                    write('A preparar catálogo ' + String(data.source || '') + ': ' + format(data.records) + ' registos.');
                    if (data.status === 'ready') { write('Catálogo local pronto: ' + format(data.records) + ' registos. Podes importar os produtos.'); break; }
                    await sleep(80);
                }
            }
            async function importLoop() {
                const state = await refresh();
                if (state.import.status !== 'running') { write('Importação não está em execução.'); return; }
                const runId = String(state.import.run_id || '');
                while (!stopped) {
                    const data = await call('cvr2_local_import_step', {run_id:runId});
                    display(snapshot, data);
                    if (data.status !== 'running') {
                        write('Operação ' + data.status + ': ' + format(data.processed) + '/' + format(data.total) +
                              ' | Concluídos: ' + format(data.success) + ' | Erros: ' + format(data.failed));
                        break;
                    }
                    await sleep(80);
                }
            }
            const bind = (id, fn) => { byId(id).addEventListener('click', fn); };
            bind('cvr2-local-rest', () => launch(async () => {
                if (!confirm('Preparar novo catálogo REST? O catálogo anterior deixará de estar selecionado.')) return;
                display(await call('cvr2_local_rest_start'), current);
                await prepareLoop();
            }));
            bind('cvr2-local-products', () => launch(async () => {
                if (!confirm('Importar os produtos do ficheiro local sem alterar as imagens?')) return;
                current = await call('cvr2_local_import_start', {phase:'products'});
                display(snapshot, current);
                await importLoop();
            }));
            bind('cvr2-local-images', () => launch(async () => {
                if (!confirm('Associar agora as imagens R2 aos produtos existentes?')) return;
                current = await call('cvr2_local_import_start', {phase:'images'});
                display(snapshot, current);
                await importLoop();
            }));
            bind('cvr2-local-upload', () => launch(async () => {
                const file = byId('cvr2-local-csv').files?.[0];
                if (!file) { write('Escolhe primeiro um ficheiro CSV.'); return; }
                upload = await call('cvr2_local_csv_upload', {}, file);
                const heads = upload.headers || [];
                const table = document.createElement('table');
                table.className = 'widefat striped';
                const header = document.createElement('tr');
                heads.forEach(h => { const th=document.createElement('th');th.textContent=h;header.appendChild(th); });
                table.appendChild(header);
                for (const row of upload.preview || []) {
                    const tr = document.createElement('tr');
                    heads.forEach((_,i)=>{const td=document.createElement('td');td.textContent=String(row[i]||'');tr.appendChild(td);});
                    table.appendChild(tr);
                }
                byId('cvr2-local-csv-table').replaceChildren(table);
                const wrapper = byId('cvr2-local-mapping');
                wrapper.replaceChildren();
                Object.entries(fields).forEach(([key,label]) => {
                    const item = document.createElement('label');
                    item.appendChild(document.createTextNode(label));
                    const select = document.createElement('select');
                    select.dataset.field = key;
                    const blank = document.createElement('option');
                    blank.value = ''; blank.textContent = 'Não importar';
                    select.appendChild(blank);
                    heads.forEach(h => {
                        const opt = document.createElement('option');
                        opt.value = h; opt.textContent = h;
                        if (normalized(h) === normalized(key) || (key === 'name' && normalized(h)==='nome') ||
                           (key === 'regular_price' && normalized(h)==='preconormal') ||
                           (key === 'categories' && normalized(h)==='categorias') ||
                           (key === 'images' && normalized(h)==='imagens')) opt.selected=true;
                        select.appendChild(opt);
                    });
                    item.appendChild(select);wrapper.appendChild(item);
                });
                byId('cvr2-local-csv-preview').hidden = false;
                write('CSV carregado (' + format(upload.size) + ' bytes). Seleciona o mapeamento e prepara o catálogo.');
            }));
            bind('cvr2-local-csv-start', () => launch(async () => {
                if (!upload) throw Error('É necessário carregar um CSV.');
                const mapping = {};
                for (const select of byId('cvr2-local-mapping').querySelectorAll('select[data-field]')) {
                    if (select.value) mapping[select.dataset.field] = select.value;
                }
                if (!mapping.sku) throw Error('Seleciona a coluna SKU.');
                if (!confirm('Preparar o ficheiro local apenas com as colunas selecionadas?')) return;
                display(await call('cvr2_local_csv_start', {csv_id:upload.id, mapping:JSON.stringify(mapping)}), current);
                await prepareLoop();
            }));
            bind('cvr2-local-refresh', () => launch(async () => {await refresh();write('Estado atualizado.');}));
            // Pause must remain clickable while the importer loop is active.
            bind('cvr2-local-pause', async () => {
                stopped = true;
                try {
                    const data = await call('cvr2_local_pause');
                    display(snapshot, data);
                    write(data.status === 'paused' ? 'Importação pausada. Podes retomar mais tarde.' : 'Importação pronta para retomar.');
                } catch (error) {
                    write('Erro ao pausar: ' + error.message);
                }
            });
            bind('cvr2-local-resume', () => launch(async () => {
                const data = await refresh();
                if (data.snapshot.status === 'building') return prepareLoop();
                if (data.import.status === 'paused') {
                    current = await call('cvr2_local_pause');
                    return importLoop();
                }
                if (data.import.status === 'running') return importLoop();
                write('Não há operação por retomar.');
            }));
            bind('cvr2-local-report', () => {
                const form = document.createElement('form');
                form.method = 'POST'; form.action = ajaxurl; form.target = '_blank';
                for (const [key,value] of Object.entries({action:'cvr2_local_report', nonce})) {
                    const field = document.createElement('input');
                    field.type = 'hidden'; field.name = key; field.value = String(value);
                    form.appendChild(field);
                }
                document.body.appendChild(form); form.submit(); form.remove();
            });
            bind('cvr2-local-delete', () => launch(async () => {
                if (!confirm('Eliminar o catálogo privado e os estados guardados? Esta operação não elimina produtos WooCommerce.')) return;
                await call('cvr2_local_delete');
                upload = null; byId('cvr2-local-csv-preview').hidden=true;
                display({}, {});write('Ficheiros temporários eliminados.');
            }));
            refresh().catch(e => write(e.message));
        })();
        </script>
        <?php
    }
}
