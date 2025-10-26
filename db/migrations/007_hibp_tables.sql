-- Migration: Have I Been Pwned Integration Tables
-- Date: 2025-10-25
-- Description: Tables for caching HIBP email and password breach checks

-- Email breach checks cache
CREATE TABLE IF NOT EXISTS hibp_email_checks (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID REFERENCES users(id) ON DELETE SET NULL,
  api_key_id UUID REFERENCES api_keys(id) ON DELETE SET NULL,
  email TEXT NOT NULL,
  breach_data JSONB NOT NULL,
  breach_count INT NOT NULL DEFAULT 0,
  checked_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Indexes for email checks
CREATE INDEX IF NOT EXISTS idx_hibp_email_checks_email ON hibp_email_checks(email);
CREATE INDEX IF NOT EXISTS idx_hibp_email_checks_checked_at ON hibp_email_checks(checked_at DESC);
CREATE INDEX IF NOT EXISTS idx_hibp_email_checks_user ON hibp_email_checks(user_id);
CREATE INDEX IF NOT EXISTS idx_hibp_email_checks_created ON hibp_email_checks(created_at DESC);

-- Password breach checks cache (only stores hash, never plaintext)
CREATE TABLE IF NOT EXISTS hibp_password_checks (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID REFERENCES users(id) ON DELETE SET NULL,
  api_key_id UUID REFERENCES api_keys(id) ON DELETE SET NULL,
  password_hash TEXT NOT NULL, -- SHA-1 hash only
  pwned BOOLEAN NOT NULL,
  occurrences INT NOT NULL DEFAULT 0,
  checked_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Indexes for password checks
CREATE INDEX IF NOT EXISTS idx_hibp_password_checks_hash ON hibp_password_checks(password_hash);
CREATE INDEX IF NOT EXISTS idx_hibp_password_checks_checked_at ON hibp_password_checks(checked_at DESC);
CREATE INDEX IF NOT EXISTS idx_hibp_password_checks_user ON hibp_password_checks(user_id);
CREATE INDEX IF NOT EXISTS idx_hibp_password_checks_created ON hibp_password_checks(created_at DESC);

-- Rate limiting table for HIBP requests
CREATE TABLE IF NOT EXISTS hibp_rate_limits (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  rate_key TEXT NOT NULL,
  expires_at TIMESTAMPTZ NOT NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Index for rate limiting
CREATE INDEX IF NOT EXISTS idx_hibp_rate_limits_key ON hibp_rate_limits(rate_key);
CREATE INDEX IF NOT EXISTS idx_hibp_rate_limits_expires ON hibp_rate_limits(expires_at);

-- Cleanup old cache entries (runs daily)
-- Delete email checks older than 1 day
DELETE FROM hibp_email_checks WHERE checked_at < NOW() - INTERVAL '1 day';

-- Delete password checks older than 1 day
DELETE FROM hibp_password_checks WHERE checked_at < NOW() - INTERVAL '1 day';

-- Delete expired rate limits
DELETE FROM hibp_rate_limits WHERE expires_at < NOW();

COMMENT ON TABLE hibp_email_checks IS 'Cache for HaveIBeenPwned email breach lookups - expires after 1 day';
COMMENT ON TABLE hibp_password_checks IS 'Cache for HaveIBeenPwned password breach lookups - stores SHA-1 hash only, never plaintext - expires after 1 day';
COMMENT ON TABLE hibp_rate_limits IS 'Rate limiting for HIBP API requests - 5/min anonymous, 50/min authenticated';
