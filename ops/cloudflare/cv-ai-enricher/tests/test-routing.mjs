import assert from 'node:assert/strict';
import test from 'node:test';
import worker from '../worker.mjs';

const JOB = '2fd20bcd-1111-4222-8333-123456789abc';
const originalFetch = globalThis.fetch;

async function runQueue({provider,failWorkers=false,jobsEnabled=true}) {
  const calls=[];
  const dbChanges=[];
  const job={
    id:JOB,list_id:'00000000-0000-4000-8000-000000000001',
    product_id:42,state:'queued',attempts:0,list_status:'active'
  };
  const env={
    CV_AI_TOKEN:'x'.repeat(80),
    WOO_BASE_URL:'https://loja.chavevertical.com',
    ENABLE_AI_JOBS:'false',
    AI_GATEWAY_ID:'cv-ai-enricher',
    CV_AI_CONFIG_JSON:JSON.stringify({
      provider,jobs_enabled:jobsEnabled,
      workers_ai_model:'@cf/meta/llama-3.1-8b-instruct-fast',
      byok_model:'google/gemini-2.5-flash'
    }),
    DB:{
      prepare(sql){
        return {
          bind(...params){
            return {
              async first(){assert.match(sql,/SELECT j\.\*/);return job;},
              async run(){
                dbChanges.push({sql,params});
                return {meta:{changes:1}};
              }
            };
          }
        };
      }
    },
    AI:{
      async run(model,input,options){
        calls.push({model,input,options});
        if(failWorkers && model.startsWith('@cf/')){
          throw Error('Cloudflare Workers AI 3036 - 429 quota exceeded');
        }
        return {response:JSON.stringify({
          name:'Teste de produto',
          description:'Descrição sem alterações comerciais.',
          short_description:'Descrição breve.',
          meta_description:'Meta descrição do produto.',
          focus_keyword:'Teste de produto'
        })};
      }
    }
  };
  const msg={
    body:{jobId:JOB},
    ack(){this.acked=true;},
    retry(o){this.retried=o;},
    acked:false,retried:null
  };
  globalThis.fetch=async (url,options) => {
    assert.match(String(url),/^https:\/\/loja\.chavevertical\.com\/wp-json\/cv-ai\/v1\/products\/42$/);
    assert.equal(options.method,'GET','normalização não pode alterar WooCommerce');
    return Response.json({product:{
      id:42,name:'Teste de produto',description:'',
      short_description:'',sku:'SKU42',category_paths:['Ferramentas'],
      category_validation:{ok:true},modified_gmt:'2026-10-10T15:00:00Z'
    }});
  };
  try{await worker.queue({messages:[msg]},env);}
  finally{globalThis.fetch=originalFetch;}
  const saved=dbChanges.find(c=>c.sql.includes("state='review'"));
  return {calls,dbChanges,msg,saved};
}

test('Neurons first: calls Workers AI through authenticated gateway without a product write',async()=>{
  const out=await runQueue({provider:'workers_ai'});
  assert.equal(out.calls.length,1);
  assert.equal(out.calls[0].model,'@cf/meta/llama-3.1-8b-instruct-fast');
  assert.deepEqual(out.calls[0].options,{gateway:{id:'cv-ai-enricher',skipCache:true}});
  assert.equal(out.msg.acked,true);
  const src=JSON.parse(out.saved.params[1]);
  assert.equal(src.ai_execution.provider,'workers_ai');
});

test('BYOK explicitly selected: calls the external model through AI Gateway',async()=>{
  const out=await runQueue({provider:'byok'});
  assert.equal(out.calls.length,1);
  assert.equal(out.calls[0].model,'google/gemini-2.5-flash');
  assert.equal(out.msg.acked,true);
  const src=JSON.parse(out.saved.params[1]);
  assert.equal(src.ai_execution.provider,'byok');
});

test('Hybrid: BYOK fallback only after Workers AI quota error',async()=>{
  const out=await runQueue({provider:'hybrid',failWorkers:true});
  assert.deepEqual(out.calls.map(x=>x.model),[
    '@cf/meta/llama-3.1-8b-instruct-fast','google/gemini-2.5-flash'
  ]);
  assert.equal(out.msg.acked,true);
  const src=JSON.parse(out.saved.params[1]);
  assert.equal(src.ai_execution.provider,'byok_fallback');
});

test('Disabled queue: consumes no Neurons, BYOK credits or WooCommerce calls',async()=>{
  const out=await runQueue({provider:'hybrid',jobsEnabled:false});
  assert.equal(out.calls.length,0);
  assert.deepEqual(out.msg.retried,{delaySeconds:3600});
  assert.equal(out.msg.acked,false);
});
