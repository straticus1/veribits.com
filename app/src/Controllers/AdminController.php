<?php
namespace VeriBits\Controllers;

use VeriBits\Utils\Response;
use VeriBits\Utils\Database;
use VeriBits\Utils\Logger;

class AdminController {
    public function runMigrations(): void {
        try {
            $results = [];
            $pdo = Database::getConnection();

            // Create all required tables
            $tables = $this->getTableDefinitions();

            foreach ($tables as $name => $sql) {
                try {
                    $pdo->exec($sql);
                    $results[] = [
                        'table' => $name,
                        'status' => 'success'
                    ];
                    Logger::info('Table created/updated successfully', ['table' => $name]);
                } catch (\Exception $e) {
                    // Ignore "already exists" errors
                    if (strpos($e->getMessage(), 'already exists') === false) {
                        $results[] = [
                            'table' => $name,
                            'status' => 'error',
                            'message' => $e->getMessage()
                        ];
                        Logger::error('Table creation failed', [
                            'table' => $name,
                            'error' => $e->getMessage()
                        ]);
                    } else {
                        $results[] = [
                            'table' => $name,
                            'status' => 'exists'
                        ];
                    }
                }
            }

            Response::success([
                'tables' => $results,
                'total' => count($tables)
            ], 'Migrations completed');

        } catch (\Exception $e) {
            Logger::error('Migration process failed', ['error' => $e->getMessage()]);
            Response::error('Migration process failed: ' . $e->getMessage(), 500);
        }
    }

    private function getTableDefinitions(): array {
        return [
            'users' => "
                CREATE TABLE IF NOT EXISTS users (
                    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                    email VARCHAR(320) UNIQUE NOT NULL,
                    password VARCHAR(255) NOT NULL,
                    status VARCHAR(50) DEFAULT 'active',
                    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
                );
                CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
                CREATE INDEX IF NOT EXISTS idx_users_status ON users(status);
            ",
            'api_keys' => "
                CREATE TABLE IF NOT EXISTS api_keys (
                    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                    user_id UUID REFERENCES users(id) ON DELETE CASCADE,
                    key VARCHAR(255) UNIQUE NOT NULL,
                    name VARCHAR(100),
                    revoked BOOLEAN DEFAULT FALSE,
                    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
                );
                CREATE INDEX IF NOT EXISTS idx_api_keys_user ON api_keys(user_id);
                CREATE INDEX IF NOT EXISTS idx_api_keys_key ON api_keys(key);
            ",
            'billing_accounts' => "
                CREATE TABLE IF NOT EXISTS billing_accounts (
                    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                    user_id UUID UNIQUE REFERENCES users(id) ON DELETE CASCADE,
                    plan VARCHAR(50) DEFAULT 'free',
                    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
                );
                CREATE INDEX IF NOT EXISTS idx_billing_user ON billing_accounts(user_id);
            ",
            'quotas' => "
                CREATE TABLE IF NOT EXISTS quotas (
                    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                    user_id UUID REFERENCES users(id) ON DELETE CASCADE,
                    period VARCHAR(20) NOT NULL,
                    allowance INTEGER NOT NULL,
                    used INTEGER DEFAULT 0,
                    reset_at TIMESTAMPTZ,
                    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
                );
                CREATE INDEX IF NOT EXISTS idx_quotas_user ON quotas(user_id);
            ",
            'email_verifications' => "
                CREATE TABLE IF NOT EXISTS email_verifications (
                    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                    user_id UUID REFERENCES users(id) ON DELETE SET NULL,
                    verification_type VARCHAR(50) NOT NULL,
                    domain VARCHAR(255),
                    email VARCHAR(255),
                    result_status VARCHAR(50),
                    has_issues BOOLEAN DEFAULT FALSE,
                    score INTEGER,
                    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
                );
                CREATE INDEX IF NOT EXISTS idx_email_verifications_user ON email_verifications(user_id);
                CREATE INDEX IF NOT EXISTS idx_email_verifications_domain ON email_verifications(domain);
                CREATE INDEX IF NOT EXISTS idx_email_verifications_type ON email_verifications(verification_type);
                CREATE INDEX IF NOT EXISTS idx_email_verifications_created ON email_verifications(created_at DESC);
            "
        ];
    }
}
