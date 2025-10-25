-- VeriBits Core Tables Migration
-- Creates verification, quota, webhook, and anonymous tracking tables

-- Quotas for rate limiting
CREATE TABLE IF NOT EXISTS quotas (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID REFERENCES users(id) ON DELETE CASCADE,
  period TEXT NOT NULL,
  allowance INT NOT NULL,
  used INT NOT NULL DEFAULT 0,
  UNIQUE(user_id, period)
);

CREATE INDEX IF NOT EXISTS idx_quotas_user ON quotas(user_id);

-- Generic verifications table
CREATE TABLE IF NOT EXISTS verifications (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID REFERENCES users(id) ON DELETE SET NULL,
  api_key_id UUID REFERENCES api_keys(id) ON DELETE SET NULL,
  kind TEXT NOT NULL,
  input JSONB NOT NULL,
  result JSONB NOT NULL,
  score INT NOT NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_verifications_user ON verifications(user_id);
CREATE INDEX IF NOT EXISTS idx_verifications_api_key ON verifications(api_key_id);
CREATE INDEX IF NOT EXISTS idx_verifications_kind ON verifications(kind);
CREATE INDEX IF NOT EXISTS idx_verifications_created ON verifications(created_at DESC);

-- Webhooks configuration
CREATE TABLE IF NOT EXISTS webhooks (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID REFERENCES users(id) ON DELETE CASCADE,
  url TEXT NOT NULL,
  secret TEXT NOT NULL,
  events JSONB NOT NULL DEFAULT '["*"]',
  active BOOLEAN NOT NULL DEFAULT true,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_webhooks_user ON webhooks(user_id);
CREATE INDEX IF NOT EXISTS idx_webhooks_active ON webhooks(active);

-- Webhook delivery events
CREATE TABLE IF NOT EXISTS webhook_events (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  webhook_id UUID REFERENCES webhooks(id) ON DELETE CASCADE,
  event_type TEXT NOT NULL,
  payload JSONB NOT NULL,
  delivered BOOLEAN NOT NULL DEFAULT false,
  response_code INT,
  response_time_ms INT,
  error TEXT,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  delivered_at TIMESTAMPTZ
);

CREATE INDEX IF NOT EXISTS idx_webhook_events_webhook ON webhook_events(webhook_id);
CREATE INDEX IF NOT EXISTS idx_webhook_events_delivered ON webhook_events(delivered);
CREATE INDEX IF NOT EXISTS idx_webhook_events_created ON webhook_events(created_at DESC);

-- Anonymous scans tracking (fallback for Redis)
CREATE TABLE IF NOT EXISTS anonymous_scans (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  ip_address TEXT NOT NULL,
  scans_used INT NOT NULL DEFAULT 0,
  period_start TIMESTAMPTZ NOT NULL DEFAULT now(),
  period_end TIMESTAMPTZ NOT NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_anonymous_scans_ip ON anonymous_scans(ip_address);
CREATE INDEX IF NOT EXISTS idx_anonymous_scans_period ON anonymous_scans(period_end);
CREATE INDEX IF NOT EXISTS idx_anonymous_scans_ip_period ON anonymous_scans(ip_address, period_end);
