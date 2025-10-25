-- Performance Indexes Migration
-- Adds indexes to improve query performance

-- Users table indexes
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_users_status ON users(status);
CREATE INDEX IF NOT EXISTS idx_users_created ON users(created_at DESC);

-- Verifications table indexes (if not already exist)
CREATE INDEX IF NOT EXISTS idx_verifications_user ON verifications(user_id);
CREATE INDEX IF NOT EXISTS idx_verifications_api_key ON verifications(api_key_id);
CREATE INDEX IF NOT EXISTS idx_verifications_kind ON verifications(kind);
CREATE INDEX IF NOT EXISTS idx_verifications_created ON verifications(created_at DESC);

-- API keys indexes
CREATE INDEX IF NOT EXISTS idx_api_keys_user ON api_keys(user_id);
CREATE INDEX IF NOT EXISTS idx_api_keys_key ON api_keys(key);
CREATE INDEX IF NOT EXISTS idx_api_keys_revoked ON api_keys(revoked);

-- Quotas indexes
CREATE INDEX IF NOT EXISTS idx_quotas_user ON quotas(user_id);
CREATE INDEX IF NOT EXISTS idx_quotas_period ON quotas(period);

-- Billing accounts indexes
CREATE INDEX IF NOT EXISTS idx_billing_accounts_user ON billing_accounts(user_id);
CREATE INDEX IF NOT EXISTS idx_billing_accounts_plan ON billing_accounts(plan);

-- Invoices indexes
CREATE INDEX IF NOT EXISTS idx_invoices_billing_account ON invoices(billing_account_id);
CREATE INDEX IF NOT EXISTS idx_invoices_status ON invoices(status);
CREATE INDEX IF NOT EXISTS idx_invoices_created ON invoices(created_at DESC);

-- Webhooks indexes
CREATE INDEX IF NOT EXISTS idx_webhooks_user ON webhooks(user_id);
CREATE INDEX IF NOT EXISTS idx_webhooks_active ON webhooks(active);

-- Webhook events indexes
CREATE INDEX IF NOT EXISTS idx_webhook_events_webhook ON webhook_events(webhook_id);
CREATE INDEX IF NOT EXISTS idx_webhook_events_delivered ON webhook_events(delivered);
CREATE INDEX IF NOT EXISTS idx_webhook_events_created ON webhook_events(created_at DESC);

-- Anonymous scans indexes
CREATE INDEX IF NOT EXISTS idx_anonymous_scans_ip ON anonymous_scans(ip_address);
CREATE INDEX IF NOT EXISTS idx_anonymous_scans_period ON anonymous_scans(period_end);
CREATE INDEX IF NOT EXISTS idx_anonymous_scans_ip_period ON anonymous_scans(ip_address, period_end);

-- Composite indexes for common queries
CREATE INDEX IF NOT EXISTS idx_verifications_user_created ON verifications(user_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_api_keys_user_revoked ON api_keys(user_id, revoked);
