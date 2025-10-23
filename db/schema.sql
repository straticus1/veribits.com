-- VeriBits Postgres Schema (RDS)
-- Attribution: After Dark Systems
CREATE EXTENSION IF NOT EXISTS pgcrypto;
CREATE TABLE IF NOT EXISTS users (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  email TEXT UNIQUE NOT NULL,
  password TEXT,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  status TEXT NOT NULL DEFAULT 'active',
  email_verified BOOLEAN NOT NULL DEFAULT false,
  last_login TIMESTAMPTZ
);
CREATE TABLE IF NOT EXISTS api_keys (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID REFERENCES users(id) ON DELETE CASCADE,
  key TEXT UNIQUE NOT NULL,
  name TEXT,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  revoked BOOLEAN NOT NULL DEFAULT false
);
CREATE TABLE IF NOT EXISTS verifications (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID REFERENCES users(id) ON DELETE SET NULL,
  api_key_id UUID REFERENCES api_keys(id) ON DELETE SET NULL,
  kind TEXT NOT NULL, input JSONB NOT NULL, result JSONB NOT NULL, score INT NOT NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE TABLE IF NOT EXISTS quotas (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID REFERENCES users(id) ON DELETE CASCADE,
  period TEXT NOT NULL, allowance INT NOT NULL, used INT NOT NULL DEFAULT 0,
  UNIQUE(user_id, period)
);
CREATE TABLE IF NOT EXISTS webhooks (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID REFERENCES users(id) ON DELETE CASCADE,
  url TEXT NOT NULL,
  secret TEXT NOT NULL,
  events JSONB NOT NULL DEFAULT '["*"]',
  active BOOLEAN NOT NULL DEFAULT true,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
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
CREATE TABLE IF NOT EXISTS billing_accounts (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID REFERENCES users(id) ON DELETE CASCADE,
  plan TEXT NOT NULL DEFAULT 'free', currency TEXT NOT NULL DEFAULT 'USD',
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE TABLE IF NOT EXISTS invoices (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  billing_account_id UUID REFERENCES billing_accounts(id) ON DELETE CASCADE,
  period TEXT NOT NULL, amount_cents INT NOT NULL, status TEXT NOT NULL DEFAULT 'open',
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(), paid_at TIMESTAMPTZ
);
CREATE TABLE IF NOT EXISTS malware_scans (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID REFERENCES users(id) ON DELETE SET NULL,
  api_key_id UUID REFERENCES api_keys(id) ON DELETE SET NULL,
  file_hash TEXT NOT NULL,
  file_size_bytes BIGINT NOT NULL,
  scan_status TEXT NOT NULL, -- 'clean', 'infected', 'error'
  threats_found JSONB,
  clamav_version TEXT,
  signature_version TEXT,
  scan_time_ms INT,
  badge_id TEXT UNIQUE,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_malware_scans_hash ON malware_scans(file_hash);
CREATE INDEX IF NOT EXISTS idx_malware_scans_badge ON malware_scans(badge_id);
CREATE TABLE IF NOT EXISTS archive_inspections (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID REFERENCES users(id) ON DELETE SET NULL,
  api_key_id UUID REFERENCES api_keys(id) ON DELETE SET NULL,
  file_hash TEXT NOT NULL,
  archive_type TEXT NOT NULL, -- 'zip', 'tar', 'tar.gz', 'tar.bz2', 'tar.xz'
  total_files INT NOT NULL,
  total_size_bytes BIGINT NOT NULL,
  compression_ratio NUMERIC(10,2),
  contents JSONB NOT NULL,
  suspicious_flags JSONB,
  integrity_status TEXT NOT NULL, -- 'ok', 'corrupted', 'suspicious'
  badge_id TEXT UNIQUE,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_archive_inspections_hash ON archive_inspections(file_hash);
CREATE INDEX IF NOT EXISTS idx_archive_inspections_badge ON archive_inspections(badge_id);
CREATE TABLE IF NOT EXISTS dns_checks (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID REFERENCES users(id) ON DELETE SET NULL,
  api_key_id UUID REFERENCES api_keys(id) ON DELETE SET NULL,
  domain TEXT NOT NULL,
  check_type TEXT NOT NULL, -- 'full', 'records', 'security', 'email', 'propagation'
  dns_records JSONB,
  dnssec_status TEXT,
  blacklist_status JSONB,
  email_config JSONB, -- SPF, DKIM, DMARC
  propagation_results JSONB,
  health_score INT,
  issues_found JSONB,
  badge_id TEXT UNIQUE,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_dns_checks_domain ON dns_checks(domain);
CREATE INDEX IF NOT EXISTS idx_dns_checks_badge ON dns_checks(badge_id);
CREATE INDEX IF NOT EXISTS idx_dns_checks_created ON dns_checks(created_at DESC);
CREATE TABLE IF NOT EXISTS ssl_checks (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID REFERENCES users(id) ON DELETE SET NULL,
  api_key_id UUID REFERENCES api_keys(id) ON DELETE SET NULL,
  check_type TEXT NOT NULL, -- 'website', 'certificate', 'key_match'
  domain TEXT,
  certificate_info JSONB NOT NULL,
  issuer_info JSONB,
  validity_info JSONB NOT NULL,
  subject_key_identifier TEXT,
  authority_key_identifier TEXT,
  key_match_result JSONB,
  security_score INT,
  warnings JSONB,
  badge_id TEXT UNIQUE,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_ssl_checks_domain ON ssl_checks(domain);
CREATE INDEX IF NOT EXISTS idx_ssl_checks_badge ON ssl_checks(badge_id);
CREATE INDEX IF NOT EXISTS idx_ssl_checks_created ON ssl_checks(created_at DESC);
CREATE TABLE IF NOT EXISTS id_verifications (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID REFERENCES users(id) ON DELETE SET NULL,
  api_key_id UUID REFERENCES api_keys(id) ON DELETE SET NULL,
  verification_status TEXT NOT NULL, -- 'verified', 'failed', 'pending', 'error'
  id_document_hash TEXT NOT NULL,
  selfie_hash TEXT NOT NULL,
  external_verification_id TEXT,
  verification_details JSONB,
  confidence_score NUMERIC(5,2),
  face_match_score NUMERIC(5,2),
  document_type TEXT,
  extracted_data JSONB,
  warnings JSONB,
  badge_id TEXT UNIQUE,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_id_verifications_status ON id_verifications(verification_status);
CREATE INDEX IF NOT EXISTS idx_id_verifications_badge ON id_verifications(badge_id);
CREATE INDEX IF NOT EXISTS idx_id_verifications_user ON id_verifications(user_id);
CREATE INDEX IF NOT EXISTS idx_id_verifications_created ON id_verifications(created_at DESC);
