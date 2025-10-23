<?php
namespace VeriBits\Utils;

class Database {
    private static ?\PDO $connection = null;
    private static array $config = [];

    public static function connect(): \PDO {
        if (self::$connection !== null) {
            return self::$connection;
        }

        $host = Config::getRequired('DB_HOST');
        $port = Config::getInt('DB_PORT', 5432);
        $dbname = Config::getRequired('DB_DATABASE');
        $username = Config::getRequired('DB_USERNAME');
        $password = Config::getRequired('DB_PASSWORD');
        $driver = Config::get('DB_DRIVER', 'pgsql');

        $dsn = "$driver:host=$host;port=$port;dbname=$dbname";

        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
            \PDO::ATTR_PERSISTENT => true,
        ];

        try {
            self::$connection = new \PDO($dsn, $username, $password, $options);
            return self::$connection;
        } catch (\PDOException $e) {
            Logger::error('Database connection failed', ['error' => $e->getMessage()]);
            throw new \RuntimeException('Database connection failed: ' . $e->getMessage());
        }
    }

    public static function getConnection(): \PDO {
        return self::connect();
    }

    public static function query(string $sql, array $params = []): \PDOStatement {
        $pdo = self::connect();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetch(string $sql, array $params = []): ?array {
        $stmt = self::query($sql, $params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public static function fetchAll(string $sql, array $params = []): array {
        $stmt = self::query($sql, $params);
        return $stmt->fetchAll();
    }

    public static function insert(string $table, array $data): string {
        $fields = array_keys($data);
        $placeholders = array_map(fn($field) => ":$field", $fields);

        $sql = "INSERT INTO $table (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ") RETURNING id";
        $stmt = self::query($sql, $data);

        return $stmt->fetchColumn();
    }

    public static function update(string $table, array $data, array $where): int {
        $setClause = implode(', ', array_map(fn($field) => "$field = :$field", array_keys($data)));
        $whereClause = implode(' AND ', array_map(fn($field) => "$field = :where_$field", array_keys($where)));

        $params = array_merge($data, array_combine(
            array_map(fn($key) => "where_$key", array_keys($where)),
            array_values($where)
        ));

        $sql = "UPDATE $table SET $setClause WHERE $whereClause";
        $stmt = self::query($sql, $params);

        return $stmt->rowCount();
    }

    public static function delete(string $table, array $where): int {
        $whereClause = implode(' AND ', array_map(fn($field) => "$field = :$field", array_keys($where)));
        $sql = "DELETE FROM $table WHERE $whereClause";
        $stmt = self::query($sql, $where);

        return $stmt->rowCount();
    }

    public static function exists(string $table, array $where): bool {
        $whereClause = implode(' AND ', array_map(fn($field) => "$field = :$field", array_keys($where)));
        $sql = "SELECT 1 FROM $table WHERE $whereClause LIMIT 1";
        $stmt = self::query($sql, $where);

        return $stmt->fetchColumn() !== false;
    }

    public static function count(string $table, array $where = []): int {
        $whereClause = empty($where) ? '' : 'WHERE ' . implode(' AND ', array_map(fn($field) => "$field = :$field", array_keys($where)));
        $sql = "SELECT COUNT(*) FROM $table $whereClause";
        $stmt = self::query($sql, $where);

        return (int)$stmt->fetchColumn();
    }

    public static function beginTransaction(): void {
        self::connect()->beginTransaction();
    }

    public static function commit(): void {
        self::connect()->commit();
    }

    public static function rollback(): void {
        self::connect()->rollBack();
    }
}