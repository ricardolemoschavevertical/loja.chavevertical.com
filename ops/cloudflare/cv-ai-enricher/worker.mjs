/**
 * CV AI Enricher Cloudflare — isolated Worker.
 * D1 stores only queues/lists/proposals, WooCommerce remains the source of truth.
 * No product write is possible unless the Worker AND WordPress explicitly opt in.
 */
const UUID=/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;
const MAX_ITEMS=250;
const FIELDS=['name','description','short_description','meta_description','focus_keyword'];
const MODEL='@cf/meta/llama-3.1-8b-instruct-fast';
const now=()=>new Date().toISOString();
const isUUID=(x)=>UUID.test(String(x||''));
const fail=(message,status=400)=>json({ok:false,error:String(message).slice(0,220)},status);
const json=(x,status=200)=>Response.json(x,{status,headers:{'cache-control':'no-store','x-content-type-options':'nosniff'}});
const clean=(s,n=250)=>String(s??'').slice(0,n);
const enabled=x=>String(x||'').toLowerCase()==='true';

async function sameToken(a,b){
  if(!a||!b)return false;
  const dig=async s=>new Uint8Array(await crypto.subtle.digest('SHA-256',new TextEncoder().encode(s)));
  const [x,y]=await Promise.all([dig(a),dig(b)]);
  let mismatch=0;for(let i=0;i<x.length;i++)mismatch |= x[i]^y[i];
  return mismatch===0;
}
async function auth(request,env){
  const expected=String(env.CV_AI_TOKEN||'');
  if(expected.length<40)return fail('Worker sem chave de acesso configurada.',503);
  const got=request.headers.get('x-cv-ai-token')||
    String(request.headers.get('authorization')||'').replace(/^Bearer\s+/i,'');
  return await sameToken(got,expected)?null:fail('Não autorizado.',401);
}
async function payload(request){
  const raw=await request.text();
  if(raw.length>200000)throw Error('Pedido demasiado grande.');
  const p=JSON.parse(raw||'{}');
  if(!p||Array.isArray(p)||typeof p!=='object')throw Error('JSON inválido.');
  return p;
}

/**
 * The admin chooses the provider, model and account policy in WooCommerce.
 * Config and Gemini credentials are Cloudflare Secrets, never sent on Queue
 * messages, never persisted in D1 and never returned from this Worker.
 */
function activeConfig(env){
  let o={};
  try{
    const parsed=JSON.parse(String(env.CV_AI_CONFIG_JSON||'{}'));
    if(parsed && typeof parsed==='object' && !Array.isArray(parsed))o=parsed;
  }catch(_e){return {provider:'workers_ai',gemini_model:'gemini-2.5-flash',
    key_strategy:'auto',jobs_enabled:false};}
  return {
    provider:o.provider==='gemini'?'gemini':'workers_ai',
    gemini_model:/^gemini-[a-z0-9.-]{3,60}$/i.test(String(o.gemini_model||''))
      ?String(o.gemini_model):'gemini-2.5-flash',
    key_strategy:['auto','free','paid','primary'].includes(o.key_strategy)?o.key_strategy:'auto',
    jobs_enabled:o.jobs_enabled===true
  };
}
function jobsEnabled(env){
  return enabled(env.ENABLE_AI_JOBS) || activeConfig(env).jobs_enabled;
}
function availableGeminiKeys(env){
  let raw=[];
  try {
    const decoded=JSON.parse(String(env.GEMINI_API_KEYS_JSON||'[]'));
    if(Array.isArray(decoded))raw=decoded;
  }catch(_e){return [];}
  if(!raw.length && typeof env.GEMINI_API_KEY==='string' && env.GEMINI_API_KEY.length>=20)
    raw=[{key:env.GEMINI_API_KEY,label:'principal',tier:'auto'}];
  return raw.filter(x=>x&&typeof x.key==='string'&&/^[A-Za-z0-9_-]{20,256}$/.test(x.key))
    .map(x=>({key:x.key,label:String(x.label||'Gemini').slice(0,60),
      tier:['free','paid','auto'].includes(x.tier)?x.tier:'auto'}))
    .slice(0,20);
}
function selectedGeminiKeys(env){
  const config=activeConfig(env),list=availableGeminiKeys(env);
  if(config.key_strategy==='primary')return list.slice(0,1);
  if(config.key_strategy==='free')return list.filter(x=>x.tier==='free');
  if(config.key_strategy==='paid')return list.filter(x=>x.tier==='paid');
  return list.sort((a,b)=>Number(a.tier==='paid')-Number(b.tier==='paid'));
}
async function callGemini(env,prompt){
  const config=activeConfig(env);
  const keys=selectedGeminiKeys(env);
  if(!keys.length)throw Error('Gemini selecionado sem contas autorizadas.');
  const url='https://generativelanguage.googleapis.com/v1beta/models/'+
    encodeURIComponent(config.gemini_model)+':generateContent';
  const body=JSON.stringify({
    contents:[{role:'user',parts:[{text:prompt}]}],
    generationConfig:{temperature:0.1,maxOutputTokens:3500,
      responseMimeType:'application/json'}
  });
  let last='Nenhuma conta respondeu.';
  for(const entry of keys){
    try{
      const r=await fetch(url,{
        method:'POST',
        headers:{'x-goog-api-key':entry.key,'Content-Type':'application/json',
          'Accept':'application/json'},
        body,redirect:'error'
      });
      if(r.status===429||r.status===503||r.status===500){
        last='Gemini HTTP '+r.status+' (limite ou indisponibilidade)';
        continue;
      }
      if(!r.ok){
        const e=new Error('Gemini HTTP '+r.status);
        e.http=r.status;throw e;
      }
      const result=await r.json();
      const parts=result?.candidates?.[0]?.content?.parts||[];
      const output=parts.filter(x=>typeof x.text==='string').map(x=>x.text).join('\n').trim();
      if(!output)throw Error('Gemini respondeu sem conteúdo JSON.');
      return {response:output};
    }catch(e){
      last=clean(e.message,110);
      if(e.http===401||e.http===403||e.http===400)throw e;
    }
  }
  throw Error('Falha em todas as contas Gemini disponíveis: '+last);
}
async function listGeminiModels(env){
  const key=selectedGeminiKeys(env)[0]?.key||availableGeminiKeys(env)[0]?.key;
  if(!key)return fail('Nenhuma chave Gemini foi sincronizada no Worker.',409);
  const r=await fetch('https://generativelanguage.googleapis.com/v1beta/models?pageSize=100',
    {headers:{'x-goog-api-key':key,'Accept':'application/json'},redirect:'error'});
  if(!r.ok)return fail('A Google recusou a consulta de modelos (HTTP '+r.status+').',502);
  const result=await r.json();
  const models=(Array.isArray(result.models)?result.models:[])
    .filter(x=>Array.isArray(x.supportedGenerationMethods)&&x.supportedGenerationMethods.includes('generateContent'))
    .map(x=>({name:String(x.name||'').replace(/^models\//,''),
      display_name:clean(x.displayName||'',120)}))
    .filter(x=>/^gemini-[a-z0-9.-]+$/i.test(x.name)).slice(0,100);
  return json({ok:true,provider:'gemini',models});
}
function requireBindings(env){
  if(!env.DB||!env.JOBS||!env.AI)return 'Bindings DB/JOBS/AI ainda não disponíveis.';
  if(!env.CV_AI_TOKEN)return 'Token de autorização por configurar.';
  return null;
}
function wooOrigin(env){
  const url=new URL(String(env.WOO_BASE_URL||'https://loja.chavevertical.com'));
  if(url.protocol!=='https:' || url.username || url.password
     || !['loja.chavevertical.com','chavevertical.com'].includes(url.hostname))
    throw Error('Origem WooCommerce não autorizada.');
  return url.origin;
}
async function woo(env,method,path,body){
  const origin=wooOrigin(env);
  const response=await fetch(origin+path,{method,redirect:'error',
    headers:{'accept':'application/json','content-type':'application/json','x-cv-ai-token':env.CV_AI_TOKEN},
    body:body===undefined?undefined:JSON.stringify(body)});
  const data=await response.json().catch(()=>({}));
  if(!response.ok){
    const e=new Error('WooCommerce HTTP '+response.status+': '+clean(data.message||data.error||'',100));
    e.http=response.status;throw e;
  }
  return data;
}
function proposal(result,source){
  let s=result?.response??result?.result??result;
  if(typeof s==='string'){
    s=s.trim().replace(/^\`\`\`(?:json)?\s*/i,'').replace(/\s*\`\`\`$/,'');
    s=JSON.parse(s);
  }
  if(!s||Array.isArray(s)||typeof s!=='object')throw Error('IA não devolveu objeto JSON.');
  for(const key of Object.keys(s))
    if(!FIELDS.includes(key))throw Error('Campo não permitido pela IA: '+key);
  const out={};
  for(const key of FIELDS){
    const value=s[key]??(key==='name'?source.name:key==='description'?source.description:key==='short_description'?source.short_description:'');
    if(typeof value!=='string'||value.length>20000)throw Error('Campo inválido da IA: '+key);
    out[key]=value;
  }
  if(out.name.length>220 || out.meta_description.length>500 || out.focus_keyword.length>250)
    throw Error('Texto da IA excede o limite editorial.');
  return out;
}
async function lists(env){
  const q=await env.DB.prepare(`SELECT l.id,l.name,l.status,l.created_at,
    (SELECT COUNT(*) FROM list_items i WHERE i.list_id=l.id) AS total,
    (SELECT COUNT(*) FROM jobs j WHERE j.list_id=l.id AND j.state='review') AS review,
    (SELECT COUNT(*) FROM jobs j WHERE j.list_id=l.id AND j.state='applied') AS applied,
    (SELECT COUNT(*) FROM jobs j WHERE j.list_id=l.id AND j.state='failed') AS failed
    FROM lists l ORDER BY l.created_at DESC LIMIT 100`).all();
  return json({ok:true,lists:q.results||[]});
}
async function makeList(env,req){
  const b=await payload(req);
  const title=clean(b.name,120).trim();
  if(!title || !Array.isArray(b.items)||!b.items.length||b.items.length>MAX_ITEMS)
    return fail('Lista deve ter nome e até 250 produtos.');
  const ids=new Map();
  for(const item of b.items){
    const id=Number(item?.product_id);
    if(!Number.isSafeInteger(id)||id<1)return fail('ID de produto inválido.');
    ids.set(id,clean(item.sku,80));
  }
  const uuid=crypto.randomUUID();
  const batch=[env.DB.prepare("INSERT INTO lists(id,name,status) VALUES (?,?,'draft')").bind(uuid,title)];
  for(const [id,sku] of ids)batch.push(env.DB.prepare("INSERT INTO list_items(list_id,product_id,sku) VALUES (?,?,?)").bind(uuid,id,sku));
  await env.DB.batch(batch);
  return json({ok:true,id:uuid,total:ids.size},201);
}
async function start(env,id){
  if(!jobsEnabled(env))return fail('Processamento por IA desativado; ativar no painel WooCommerce.',423);
  const x=await env.DB.prepare('SELECT id,status FROM lists WHERE id=?').bind(id).first();
  if(!x)return fail('Lista não encontrada.',404);
  await env.DB.prepare("UPDATE lists SET status='active' WHERE id=?").bind(id).run();
  const rows=(await env.DB.prepare('SELECT product_id FROM list_items WHERE list_id=? LIMIT 250').bind(id).all()).results||[];
  for(const row of rows){
    await env.DB.prepare("INSERT OR IGNORE INTO jobs(id,list_id,product_id,state,attempts) VALUES (?,?,?,'queued',0)")
      .bind(crypto.randomUUID(),id,row.product_id).run();
  }
  const pending=(await env.DB.prepare("SELECT id FROM jobs WHERE list_id=? AND state='queued' LIMIT 250").bind(id).all()).results||[];
  for(let i=0;i<pending.length;i+=40)
    await env.JOBS.sendBatch(pending.slice(i,i+40).map(j=>({body:{jobId:j.id}})));
  return json({ok:true,queued:pending.length});
}
async function jobDetail(env,id){
  const j=await env.DB.prepare('SELECT * FROM jobs WHERE id=?').bind(id).first();
  if(!j)return fail('Tarefa inexistente.',404);
  const {proposal_json,source_json,...other}=j;
  return json({ok:true,job:{...other,proposal:proposal_json?JSON.parse(proposal_json):null,
    source:source_json?JSON.parse(source_json):null}});
}
async function apply(env,id){
  if(!enabled(env.ENABLE_PRODUCT_APPLY))
    return fail('Aplicação WooCommerce bloqueada: é necessária aprovação explícita.',403);
  const j=await env.DB.prepare('SELECT * FROM jobs WHERE id=?').bind(id).first();
  if(!j)return fail('Tarefa não encontrada.',404);
  if(j.state!=='review')return fail('Tarefa ainda não aprovada para aplicação.',409);
  const claim=await env.DB.prepare("UPDATE jobs SET state='applying',updated_at=datetime('now') WHERE id=? AND state='review'").bind(id).run();
  if(!claim.meta?.changes)return fail('Tarefa já em processamento.',409);
  try {
    const src=JSON.parse(j.source_json||'{}');
    const out=await woo(env,'POST',`/wp-json/cv-ai/v1/products/${j.product_id}/apply`,{
      job_id:id,expected_modified_gmt:j.source_version,
      expected_category_signature:src.category_validation?.category_signature,
      fields:JSON.parse(j.proposal_json||'{}')
    });
    await env.DB.prepare("UPDATE jobs SET state='applied',error=NULL,updated_at=datetime('now') WHERE id=?").bind(id).run();
    return json({ok:true,applied:out});
  }catch(e){
    await env.DB.prepare("UPDATE jobs SET state='review',error=?,updated_at=datetime('now') WHERE id=?")
      .bind(clean(e.message,220),id).run();
    return fail('Aplicação recusada pela loja.',409);
  }
}
async function processMessage(env,message){
  if(!jobsEnabled(env)){message.retry({delaySeconds:3600});return;}
  const id=message.body?.jobId;
  if(!isUUID(id)){message.ack();return;}
  const j=await env.DB.prepare("SELECT j.*,l.status AS list_status FROM jobs j JOIN lists l ON j.list_id=l.id WHERE j.id=?")
    .bind(id).first();
  if(!j || j.list_status!=='active' || !['queued','retry'].includes(j.state)){message.ack();return;}
  const c=await env.DB.prepare("UPDATE jobs SET state='processing',attempts=attempts+1,updated_at=datetime('now') WHERE id=? AND state IN ('queued','retry')")
    .bind(id).run();
  if(!c.meta?.changes){message.ack();return;}
  try{
    const src=(await woo(env,'GET',`/wp-json/cv-ai/v1/products/${j.product_id}`)).product;
    if(!src?.id || !src.modified_gmt || !src.category_validation?.ok)
      throw Error('Produto sem validação de categorias da loja.');
    const input={sku:src.sku,name:clean(src.name,250),brand_names:src.brand_names,
      category_paths:src.category_paths,description:clean(src.description,7800),
      short_description:clean(src.short_description,2200)};
    const prompt='Responde APENAS com JSON válido contendo name,description,short_description,meta_description,focus_keyword. PT-PT. Marca MAIÚSCULAS; modelo e especificações apenas se confirmados; descrição 350-600 palavras com Aplicações e Vantagens; breve 8-15 pontos; nunca inventar dados ou alterar preço/SKU/stock/categoria/imagens/estado. Dados:\n'+JSON.stringify(input);
    const ai=activeConfig(env).provider==='gemini'
      ? await callGemini(env,prompt)
      : await env.AI.run(env.AI_MODEL||MODEL,{
          messages:[{role:'system',content:'Devolve somente JSON. Nunca inventar dados técnicos.'},{role:'user',content:prompt}],
          max_tokens:3800,temperature:0.1
        });
    const result=proposal(ai,src);
    await env.DB.prepare("UPDATE jobs SET state='review',source_version=?,source_json=?,proposal_json=?,error=NULL,updated_at=datetime('now') WHERE id=?")
      .bind(src.modified_gmt,JSON.stringify(src),JSON.stringify(result),id).run();
    message.ack();
  }catch(e){
    const final=j.attempts>=2 || (e.http && e.http<500 && e.http!==429);
    await env.DB.prepare("UPDATE jobs SET state=?,error=?,updated_at=datetime('now') WHERE id=?")
      .bind(final?'failed':'retry',clean(e.message,220),id).run();
    if(final)message.ack();else message.retry({delaySeconds:90});
  }
}

export default {
  async fetch(request,env) {
    try {
      const url=new URL(request.url),p=url.pathname.replace(/\/+$/,'')||'/';
      if(p==='/health'&&request.method==='GET') {
        const bindings=!!env.DB&&!!env.JOBS&&!!env.AI;
        const secret=String(env.CV_AI_TOKEN||'').length>=40;
        return json({ok:bindings&&secret,service:'cv-ai-enricher',version:'0.4.0',
          bindings_ready:bindings,auth_ready:secret,
          provider:activeConfig(env).provider,model:activeConfig(env).gemini_model,
          gemini_accounts_configured:availableGeminiKeys(env).length,
          processing_enabled:jobsEnabled(env),
          product_apply_enabled:enabled(env.ENABLE_PRODUCT_APPLY)},
          bindings&&secret?200:503);
      }
      const unauthorized=await auth(request,env);
      if(unauthorized)return unauthorized;
      const missing=requireBindings(env);
      if(missing)return fail(missing,503);
      if(p==='/v1/catalog/categories'&&request.method==='GET')
        return json(await woo(env,'GET','/wp-json/cv-ai/v1/catalog/categories'));
      if(p==='/v1/catalog/categories/validate'&&request.method==='POST')
        return json(await woo(env,'POST','/wp-json/cv-ai/v1/catalog/categories/validate',await payload(request)));
      if(p==='/v1/providers/gemini/models'&&request.method==='GET')return listGeminiModels(env);
      if(p==='/v1/lists'&&request.method==='GET')return lists(env);
      if(p==='/v1/lists'&&request.method==='POST')return makeList(env,request);
      let match=p.match(/^\/v1\/lists\/([\da-f-]+)(?:\/(start|pause|jobs))?$/i);
      if(match&&isUUID(match[1])){
        const id=match[1],action=match[2];
        if(action==='jobs'&&request.method==='GET'){
          const out=await env.DB.prepare("SELECT id,product_id,state,error,attempts FROM jobs WHERE list_id=? ORDER BY created_at DESC LIMIT 250").bind(id).all();
          return json({ok:true,jobs:out.results||[]});
        }
        if(action==='start'&&request.method==='POST')return start(env,id);
        if(action==='pause'&&request.method==='POST'){
          const r=await env.DB.prepare("UPDATE lists SET status='paused' WHERE id=?").bind(id).run();
          return r.meta?.changes?json({ok:true,status:'paused'}):fail('Lista não encontrada.',404);
        }
      }
      match=p.match(/^\/v1\/jobs\/([\da-f-]+)(?:\/(apply|retry))?$/i);
      if(match&&isUUID(match[1])){
        const id=match[1],action=match[2];
        if(!action&&request.method==='GET')return jobDetail(env,id);
        if(action==='apply'&&request.method==='POST')return apply(env,id);
        if(action==='retry'&&request.method==='POST'){
          if(!jobsEnabled(env))return fail('Processamento por IA desativado.',423);
          const changed=await env.DB.prepare("UPDATE jobs SET state='queued',attempts=0,error=NULL WHERE id=? AND state IN ('failed','stale')").bind(id).run();
          if(!changed.meta?.changes)return fail('Tarefa não disponível para repetir.',409);
          await env.JOBS.send({jobId:id});return json({ok:true,queued:true});
        }
      }
      return fail('Rota não encontrada.',404);
    }catch(e){return fail('Erro no processamento do pedido: '+clean(e.message,120),400);}
  },
  async queue(batch,env){
    for(const message of batch.messages) {
      try{await processMessage(env,message);}
      catch(e){message.retry({delaySeconds:120});}
    }
  }
};
