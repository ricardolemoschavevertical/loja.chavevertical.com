-- D1 EU: cv-ai-enricher-db. Não contém catálogo WooCommerce.
CREATE TABLE IF NOT EXISTS lists (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'draft' CHECK(status IN ('draft','active','paused')),
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE TABLE IF NOT EXISTS list_items (
    list_id TEXT NOT NULL,
    product_id INTEGER NOT NULL,
    sku TEXT NOT NULL DEFAULT '',
    PRIMARY KEY(list_id,product_id)
);
CREATE TABLE IF NOT EXISTS jobs (
    id TEXT PRIMARY KEY,
    list_id TEXT NOT NULL,
    product_id INTEGER NOT NULL,
    state TEXT NOT NULL DEFAULT 'queued' CHECK(state IN ('queued','processing','retry','review','applying','applied','failed','stale')),
    attempts INTEGER NOT NULL DEFAULT 0,
    source_version TEXT,
    source_json TEXT,
    proposal_json TEXT,
    error TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE(list_id,product_id)
);
CREATE INDEX IF NOT EXISTS jobs_by_list_state ON jobs(list_id,state);
CREATE INDEX IF NOT EXISTS jobs_by_state_time ON jobs(state,updated_at);
