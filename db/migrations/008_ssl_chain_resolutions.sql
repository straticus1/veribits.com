-- Migration: SSL Certificate Chain Resolution Tables
-- Date: 2025-10-26
-- Description: Table for tracking SSL certificate chain resolution analytics

-- SSL chain resolutions tracking
CREATE TABLE IF NOT EXISTS ssl_chain_resolutions (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID REFERENCES users(id) ON DELETE SET NULL,
  input_type VARCHAR(20) NOT NULL, -- 'url', 'pem', 'pkcs12', 'pkcs7'
  domain VARCHAR(255),
  leaf_cert_fingerprint VARCHAR(64), -- SHA-256 fingerprint
  missing_count INTEGER NOT NULL DEFAULT 0,
  resolved_count INTEGER NOT NULL DEFAULT 0,
  chain_complete BOOLEAN NOT NULL DEFAULT FALSE,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Indexes for ssl_chain_resolutions
CREATE INDEX IF NOT EXISTS idx_ssl_chain_resolutions_user ON ssl_chain_resolutions(user_id);
CREATE INDEX IF NOT EXISTS idx_ssl_chain_resolutions_domain ON ssl_chain_resolutions(domain);
CREATE INDEX IF NOT EXISTS idx_ssl_chain_resolutions_fingerprint ON ssl_chain_resolutions(leaf_cert_fingerprint);
CREATE INDEX IF NOT EXISTS idx_ssl_chain_resolutions_created ON ssl_chain_resolutions(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_ssl_chain_resolutions_input_type ON ssl_chain_resolutions(input_type);
CREATE INDEX IF NOT EXISTS idx_ssl_chain_resolutions_complete ON ssl_chain_resolutions(chain_complete);

-- Comments
COMMENT ON TABLE ssl_chain_resolutions IS 'Tracks SSL certificate chain resolution attempts and results for analytics';
COMMENT ON COLUMN ssl_chain_resolutions.input_type IS 'Type of input: url, pem, pkcs12, or pkcs7';
COMMENT ON COLUMN ssl_chain_resolutions.domain IS 'Domain name if available from URL or certificate CN';
COMMENT ON COLUMN ssl_chain_resolutions.leaf_cert_fingerprint IS 'SHA-256 fingerprint of the leaf certificate';
COMMENT ON COLUMN ssl_chain_resolutions.missing_count IS 'Number of missing certificates in the chain';
COMMENT ON COLUMN ssl_chain_resolutions.resolved_count IS 'Number of certificates found/resolved';
COMMENT ON COLUMN ssl_chain_resolutions.chain_complete IS 'Whether the chain is complete (ends with self-signed root)';
