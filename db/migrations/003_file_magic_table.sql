-- File Magic Number Analysis Table
-- Stores file magic number analysis results and verification badges

CREATE TABLE IF NOT EXISTS file_magic_checks (
    id SERIAL PRIMARY KEY,
    user_id UUID REFERENCES users(id) ON DELETE CASCADE,
    filename VARCHAR(512) NOT NULL,
    file_size INTEGER NOT NULL,
    magic_number BYTEA NOT NULL,
    detected_type VARCHAR(255) NOT NULL,
    detected_extension VARCHAR(50) NOT NULL,
    detected_mime VARCHAR(255) NOT NULL,
    file_hash VARCHAR(64) NOT NULL,
    badge_id VARCHAR(64) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_file_magic_user_id ON file_magic_checks(user_id);
CREATE INDEX IF NOT EXISTS idx_file_magic_badge_id ON file_magic_checks(badge_id);
CREATE INDEX IF NOT EXISTS idx_file_magic_created_at ON file_magic_checks(created_at);

COMMENT ON TABLE file_magic_checks IS 'Stores file magic number analysis results and verification badges';
COMMENT ON COLUMN file_magic_checks.magic_number IS 'Binary magic number bytes from file header';
COMMENT ON COLUMN file_magic_checks.file_hash IS 'SHA-256 hash of the analyzed file';
COMMENT ON COLUMN file_magic_checks.badge_id IS 'Unique identifier for shareable verification badge';
