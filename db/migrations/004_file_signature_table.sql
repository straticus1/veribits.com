-- File Signature Verification Table
-- Stores cryptographic signature verification results for various file types

CREATE TABLE IF NOT EXISTS file_signature_checks (
    id SERIAL PRIMARY KEY,
    user_id UUID REFERENCES users(id) ON DELETE CASCADE,
    filename VARCHAR(512) NOT NULL,
    file_size INTEGER NOT NULL,
    file_hash VARCHAR(64) NOT NULL,
    signature_type VARCHAR(255) NOT NULL,
    is_valid BOOLEAN NOT NULL,
    signer_info JSONB,
    key_fingerprint VARCHAR(128),
    badge_id VARCHAR(64) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_file_sig_user_id ON file_signature_checks(user_id);
CREATE INDEX IF NOT EXISTS idx_file_sig_badge_id ON file_signature_checks(badge_id);
CREATE INDEX IF NOT EXISTS idx_file_sig_created_at ON file_signature_checks(created_at);
CREATE INDEX IF NOT EXISTS idx_file_sig_valid ON file_signature_checks(is_valid);

COMMENT ON TABLE file_signature_checks IS 'Stores cryptographic signature verification results for PGP, JAR, AIR, macOS binaries, and hash files';
COMMENT ON COLUMN file_signature_checks.signature_type IS 'Type of signature: PGP, JAR, AIR, macOS Code Signature, Hash';
COMMENT ON COLUMN file_signature_checks.signer_info IS 'JSON object containing signer details specific to signature type';
COMMENT ON COLUMN file_signature_checks.badge_id IS 'Unique identifier for shareable verification badge';
