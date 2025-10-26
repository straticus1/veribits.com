#!/bin/bash
set -e

# Database connection info from environment
DB_HOST="${DB_HOST:-localhost}"
DB_PORT="${DB_PORT:-5432}"
DB_USER="${DB_USERNAME:-postgres}"
DB_NAME="${DB_DATABASE:-veribits}"

echo "Running database migrations..."
echo "Host: $DB_HOST"
echo "Database: $DB_NAME"

# Run all migration files in order
for file in /var/www/db/migrations/*.sql; do
    if [ -f "$file" ]; then
        echo "Running migration: $(basename $file)"
        PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -f "$file"
        if [ $? -eq 0 ]; then
            echo "✓ $(basename $file) completed successfully"
        else
            echo "✗ $(basename $file) failed"
            exit 1
        fi
    fi
done

echo "All migrations completed successfully!"
