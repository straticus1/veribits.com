-- Migration: Email Verification Analytics
-- Date: 2025-10-26
-- Description: Tables for tracking email verification and analysis usage

-- Email verification analytics
CREATE TABLE IF NOT EXISTS email_verifications (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID REFERENCES users(id) ON DELETE SET NULL,
  verification_type VARCHAR(50) NOT NULL, -- 'dea', 'spf', 'dkim', 'dmarc', 'mx', 'headers', 'blacklist', 'score'
  domain VARCHAR(255),
  email VARCHAR(255),
  result_status VARCHAR(50), -- 'pass', 'fail', 'warning', 'not_found'
  has_issues BOOLEAN DEFAULT FALSE,
  score INTEGER, -- for deliverability score
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Indexes for email_verifications
CREATE INDEX IF NOT EXISTS idx_email_verifications_user ON email_verifications(user_id);
CREATE INDEX IF NOT EXISTS idx_email_verifications_domain ON email_verifications(domain);
CREATE INDEX IF NOT EXISTS idx_email_verifications_type ON email_verifications(verification_type);
CREATE INDEX IF NOT EXISTS idx_email_verifications_created ON email_verifications(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_email_verifications_status ON email_verifications(result_status);

-- Comments
COMMENT ON TABLE email_verifications IS 'Tracks email verification and analysis requests for analytics';
COMMENT ON COLUMN email_verifications.verification_type IS 'Type of verification: dea, spf, dkim, dmarc, mx, headers, blacklist, score';
COMMENT ON COLUMN email_verifications.result_status IS 'Result status: pass, fail, warning, not_found';
COMMENT ON COLUMN email_verifications.has_issues IS 'Whether the verification found any issues or warnings';
COMMENT ON COLUMN email_verifications.score IS 'Deliverability score (0-100) for score verification type';
