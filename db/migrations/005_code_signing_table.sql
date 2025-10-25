-- Code Signing Operations Table
-- Tracks all code signing operations with tier limits

CREATE TABLE IF NOT EXISTS code_signing_operations (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID REFERENCES users(id) ON DELETE SET NULL,
  api_key_id UUID REFERENCES api_keys(id) ON DELETE SET NULL,

  -- Operation details
  operation_type TEXT NOT NULL, -- 'exe', 'jar', 'air', 'msi', 'dll'
  file_hash TEXT NOT NULL,
  file_size_bytes BIGINT NOT NULL,
  original_filename TEXT,

  -- Signing details
  certificate_type TEXT NOT NULL, -- 'test' (self-signed), 'user' (user-provided), 'premium' (CA-issued)
  certificate_subject TEXT,
  certificate_thumbprint TEXT,
  timestamp_url TEXT,

  -- Result
  signed_file_url TEXT, -- S3 location of signed file
  signature_verified BOOLEAN NOT NULL DEFAULT false,
  signing_status TEXT NOT NULL, -- 'success', 'failed', 'pending'
  error_message TEXT,

  -- Metadata
  user_ip TEXT,
  badge_id TEXT UNIQUE,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  expires_at TIMESTAMPTZ -- Signed files expire after 30 days for free tier
);

CREATE INDEX IF NOT EXISTS idx_code_signing_user ON code_signing_operations(user_id);
CREATE INDEX IF NOT EXISTS idx_code_signing_hash ON code_signing_operations(file_hash);
CREATE INDEX IF NOT EXISTS idx_code_signing_badge ON code_signing_operations(badge_id);
CREATE INDEX IF NOT EXISTS idx_code_signing_created ON code_signing_operations(created_at DESC);

-- Code Signing Quotas by Plan
-- Free: 1 signing per month
-- Monthly: 500 signings per month
-- Annual: 2,500 signings per year
-- Enterprise: 10,000 signings per month

CREATE TABLE IF NOT EXISTS code_signing_quotas (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID REFERENCES users(id) ON DELETE CASCADE,
  plan_type TEXT NOT NULL DEFAULT 'free', -- 'free', 'monthly', 'annual', 'enterprise'
  period TEXT NOT NULL, -- 'month' or 'year'
  allowance INT NOT NULL,
  used INT NOT NULL DEFAULT 0,
  resets_at TIMESTAMPTZ NOT NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  UNIQUE(user_id, period)
);

CREATE INDEX IF NOT EXISTS idx_code_signing_quotas_user ON code_signing_quotas(user_id);

-- Anonymous/IP-based tracking for free tier
CREATE TABLE IF NOT EXISTS anonymous_code_signing (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  ip_address TEXT NOT NULL,
  signings_used INT NOT NULL DEFAULT 0,
  period_start TIMESTAMPTZ NOT NULL DEFAULT now(),
  period_end TIMESTAMPTZ NOT NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_anonymous_code_signing_ip ON anonymous_code_signing(ip_address);
CREATE INDEX IF NOT EXISTS idx_anonymous_code_signing_period ON anonymous_code_signing(period_end);
