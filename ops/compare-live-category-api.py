#!/usr/bin/env python3
"""Read-only, public REST category reconciliation between legacy and new Woo stores."""
from __future__ import annotations
import collections
import csv
import html
import json
import os
import re
import time
import unicodedata
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

SITES={"backup":"https://backup.chavevertical.com","loja":"https://loja.chavevertical.com"}
FIELDS="id,name,slug,parent,count,link"

def fetch(host):
    categories = {}
    p = 1
    totalpages = None
    while True:
        params=urllib.parse.urlencode({"page":p,"per_page":100,"hide_empty":"false",
            "orderby":"id","order":"asc","_fields":FIELDS})
        url=host+"/wp-json/wp/v2/product_cat?"+params
        error=None
        for attempt in range(3):
            try:
                req=urllib.request.Request(url, headers={"User-Agent":"CV-Category-Reconciliation/1.0",
                    "Accept":"application/json"})
                with urllib.request.urlopen(req, timeout=28) as r:
                    if r.status != 200: raise RuntimeError(f"HTTP {r.status}")
                    if totalpages is None: totalpages=int(r.headers.get("x-wp-totalpages","0") or 0)
                    rows=json.load(r)
                    if not isinstance(rows,list): raise RuntimeError("REST did not return an array")
                    break
            except (urllib.error.URLError, TimeoutError, OSError, ValueError, json.JSONDecodeError) as ex:
                error=ex
                if attempt>=2: raise RuntimeError(f"Could not fetch {url}: {error}") from ex
                time.sleep(1.5*(attempt+1))
        for row in rows:
            tid=int(row["id"])
            if tid in categories:raise RuntimeError(f"Duplicate id {tid}")
            categories[tid]={
                "id":tid,"name":html.unescape(re.sub(r"<[^>]+>","",str(row.get("name","")))),
                "slug":str(row.get("slug","")),
                "parent":int(row.get("parent",0)),
                "count":int(row.get("count",0)),
                "url":str(row.get("link",""))
            }
        print(f"CAT_API_FETCH host={host} page={p} page_size={len(rows)} total_so_far={len(categories)}",flush=True)
        if (totalpages and p>=totalpages) or (not totalpages and len(rows)<100):break
        p+=1
        if p>50:raise RuntimeError("Unexpected 50+ pages")
    if len(categories)<400: raise RuntimeError(f"Suspiciously incomplete taxonomy: {len(categories)}")
    return categories

def norm_name(s):
    s=unicodedata.normalize("NFKD",s)
    s="".join(c for c in s if not unicodedata.combining(c))
    return re.sub(r"[^a-z0-9]+","-",s.lower()).strip("-")

def full_path(tid, index):
    parts=[];ids=[];seen=set();cur=tid
    while cur:
        if cur in seen or cur not in index:raise RuntimeError(f"Invalid parent chain {tid}")
        seen.add(cur)
        item=index[cur]
        parts.append(norm_name(item["name"]))
        ids.append(cur)
        cur=item["parent"]
        if len(ids)>30: raise RuntimeError("Excessive taxonomy depth")
    return ">".join(reversed(parts)),list(reversed(ids))

def enrich(index):
    for id,row in index.items():
        key,parents=full_path(id,index)
        row["path_key"]=key
        row["ancestors"]=parents[:-1]
        row["depth"]=len(parents)-1
        row["path"]=" > ".join(index[x]["name"] for x in parents)
    return index

def compare(source,target):
    src=collections.defaultdict(list);dst=collections.defaultdict(list)
    slugowners=collections.defaultdict(list)
    for row in source.values():src[row["path_key"]].append(row)
    for row in target.values():
        dst[row["path_key"]].append(row)
        slugowners[row["slug"]].append(row["id"])
    union=sorted(src.keys()|dst.keys())
    states=[];counts=collections.Counter()
    for key in union:
        a=src.get(key,[]);b=dst.get(key,[])
        d={"path":key,"backup_count":len(a),"loja_count":len(b),
           "backup":[{"id":x["id"],"slug":x["slug"],"count":x["count"],"url":x["url"]} for x in a],
           "loja":[{"id":x["id"],"slug":x["slug"],"count":x["count"],"url":x["url"]} for x in b]}
        if not a:state="extra_na_loja"
        elif not b:state="em_falta_na_loja"
        elif len(a)!=1 or len(b)!=1:state="duplicado_ou_ambiguo"
        elif a[0]["slug"]==b[0]["slug"]:state="slug_igual"
        else:
            outside=[id for id in slugowners[a[0]["slug"]] if id!=b[0]["id"]]
            state="slug_colisao" if outside else "slug_diferente_livre"
            d["other_owners"]=outside
        d["state"]=state;counts[state]+=1;states.append(d)
    return {
        "summary":{
            "source":"live_public_WordPress_REST",
            "backup_categories":len(source),
            "loja_categories":len(target),
            "unique_backup_paths":len(src),
            "unique_loja_paths":len(dst),
            "backup_duplicate_paths":sum(len(v)>1 for v in src.values()),
            "loja_duplicate_paths":sum(len(v)>1 for v in dst.values()),
            "state_counts":dict(counts)
        },
        "comparison":states
    }

def main():
    source=enrich(fetch(SITES["backup"]))
    target=enrich(fetch(SITES["loja"]))
    d=compare(source,target)
    out=Path(os.environ.get("CV_PUBLIC_CATEGORY_AUDIT_OUTPUT_DIR","/tmp/cv-public-category-reconcile"))
    out.mkdir(parents=True,exist_ok=True)
    (out/"comparacao-slugs-publica.json").write_text(json.dumps(d,ensure_ascii=False,indent=2),encoding="utf-8")
    with (out/"comparacao-slugs-publica.csv").open("w",encoding="utf-8-sig",newline="") as f:
        w=csv.writer(f,delimiter=";")
        w.writerow(["Estado","Percurso normalizado","Contagem backup","Contagem loja","IDs backup",
         "Slugs backup","IDs loja","Slugs loja","IDs que ocupam slug"])
        for x in d["comparison"]:
            w.writerow([x["state"],x["path"],x["backup_count"],x["loja_count"],
              "|".join(str(y["id"]) for y in x["backup"]),
              "|".join(y["slug"] for y in x["backup"]),
              "|".join(str(y["id"]) for y in x["loja"]),
              "|".join(y["slug"] for y in x["loja"]),
              "|".join(str(v) for v in x.get("other_owners",[]))])
    print("CV_PUBLIC_CAT_SUMMARY "+json.dumps(d["summary"],ensure_ascii=False),flush=True)
    for x in d["comparison"]:
        if x["state"] not in ("slug_igual",):
            print("CV_PUBLIC_CAT_DIFF "+json.dumps(x,ensure_ascii=False),flush=True)
    return 0
if __name__=="__main__":
    raise SystemExit(main())
