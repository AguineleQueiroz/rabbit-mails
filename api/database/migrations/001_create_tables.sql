-- Extensão para UUID
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- Tabela principal de e-mails
CREATE TABLE emails (
    id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    recipient     VARCHAR(255) NOT NULL,
    subject       VARCHAR(255) NOT NULL,
    body          TEXT NOT NULL,
    status        VARCHAR(50) NOT NULL DEFAULT 'pending',
    -- pending | queued | processing | sent | failed | dead
    attempts      INT NOT NULL DEFAULT 0,
    max_attempts  INT NOT NULL DEFAULT 3,
    error_message TEXT,
    queued_at     TIMESTAMP,
    processed_at  TIMESTAMP,
    created_at    TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at    TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_emails_status     ON emails(status);
CREATE INDEX idx_emails_created_at ON emails(created_at DESC);

-- Histórico de eventos de fila
CREATE TABLE queue_events (
    id         UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email_id   UUID NOT NULL REFERENCES emails(id),
    event      VARCHAR(50) NOT NULL,
    -- published | consumed | processing | sent | failed | retried | dead_lettered | requeued
    worker_id  VARCHAR(100),
    payload    JSONB,
    created_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_queue_events_email_id   ON queue_events(email_id);
CREATE INDEX idx_queue_events_created_at ON queue_events(created_at DESC);

-- Log de heartbeat dos consumers
CREATE TABLE consumers_log (
    id               UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    worker_id        VARCHAR(100) NOT NULL UNIQUE,
    status           VARCHAR(50) NOT NULL DEFAULT 'active',
    -- active | idle | stopped
    last_heartbeat   TIMESTAMP NOT NULL DEFAULT NOW(),
    emails_processed INT NOT NULL DEFAULT 0,
    emails_failed    INT NOT NULL DEFAULT 0,
    started_at       TIMESTAMP NOT NULL DEFAULT NOW()
);
