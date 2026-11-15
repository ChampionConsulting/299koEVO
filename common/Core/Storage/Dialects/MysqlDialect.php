<?php

namespace Core\Storage\Dialects;

use Core\Interfaces\SqlDialectInterface;

class MysqlDialect implements SqlDialectInterface
{
    public function limit(int $limit, int $offset = 0): string
    {
        return " LIMIT " . $limit . ($offset > 0 ? " OFFSET " . $offset : "");
    }

    public function jsonExtract(string $field, string $path): string
    {
        return "JSON_EXTRACT($field, '$" . $path . "')";
    }

    public function fulltextMatch(array $columns, string $placeholder): string
    {
        return "MATCH(" . implode(', ', $columns) . ") AGAINST($placeholder IN BOOLEAN MODE)";
    }

    public function proximitySql(string $latColumn, string $lonColumn, string $latValue, string $lonValue): string
    {
        // MySQL ST_X is Longitude, ST_Y is Latitude for POINT(lon lat)
        // La formule Haversine standard est : 
        // 6371 * acos(cos(rad(lat1)) * cos(rad(lat2)) * cos(rad(lon2) - rad(lon1)) + sin(rad(lat1)) * sin(rad(lat2)))

        if (empty($lonColumn)) {
            // latColumn est en fait une colonne POINT
            $latColSql = "ST_Y($latColumn)";
            $lonColSql = "ST_X($latColumn)";
        } else {
            $latColSql = $latColumn;
            $lonColSql = $lonColumn;
        }

        return "(6371 * acos(GREATEST(-1.0, LEAST(1.0, cos(radians($latValue)) * cos(radians($latColSql)) * cos(radians($lonColSql) - radians($lonValue)) + sin(radians($latValue)) * sin(radians($latColSql))))))";
    }

    public function conditional(string $condition, string $ifTrue, string $ifFalse): string
    {
        return "IF($condition, $ifTrue, $ifFalse)";
    }

    public function dateSub(string $field, int $interval, string $unit): string
    {
        return "DATE_SUB($field, INTERVAL $interval $unit)";
    }

    public function now(): string
    {
        return "NOW()";
    }

    public function tableExists(string $tableName): string
    {
        return "SHOW TABLES LIKE '$tableName'";
    }

    public function getDateFormat(): string
    {
        return "Y-m-d H:i:s";
    }

    public function getColumnsSql(string $tableName): string
    {
        $clean = trim($tableName, '` ');
        return "SHOW COLUMNS FROM `$clean`";
    }

    public function getName(): string
    {
        return "mysql";
    }

    public function getCreateTableSql(string $table, string $pk, array $columns, array $indexes = []): string
    {
        $pkTypeSpec = strtolower((string) ($columns[$pk] ?? ''));
        $isStringPk = ($pk !== 'id' && strpos($pkTypeSpec, 'int') === false && strpos($pkTypeSpec, 'serial') === false)
            || strpos($pkTypeSpec, 'varchar') !== false
            || strpos($pkTypeSpec, 'char') !== false
            || strpos($pkTypeSpec, 'text') !== false
            || strpos($pkTypeSpec, 'string') !== false;

        $indexedCols = array_flip(array_filter($indexes, 'is_string'));
        $lines = [$isStringPk ? "`$pk` VARCHAR(64) PRIMARY KEY" : "`$pk` INT AUTO_INCREMENT PRIMARY KEY"];
        foreach ($columns as $name => $type) {
            if ($name === $pk) continue;
            $sqlType = $this->mapToSqlType((string) $type);
            if (isset($indexedCols[$name]) && ($sqlType === 'TEXT' || $sqlType === 'LONGTEXT')) {
                $sqlType = 'VARCHAR(191)';
            }
            $lines[] = "`$name` $sqlType";
        }

        foreach ($indexes as $col) {
            if (!is_string($col) || $col === '') {
                continue;
            }
            $colType = strtoupper((string) ($columns[$col] ?? ''));
            // Safeguard: indexed VARCHAR(255) on older MySQL or TEXT needs <= 191 prefix in utf8mb4
            if (strpos($colType, 'TEXT') !== false || (isset($columns[$col]) && preg_match('/varchar\s*\(\s*(\d+)\s*\)/i', (string) $columns[$col], $vm) && (int) $vm[1] > 191)) {
                $lines[] = "INDEX `idx_$col` (`$col`(191))";
            } else {
                $lines[] = "INDEX `idx_$col` (`$col`)";
            }
        }

        return "CREATE TABLE IF NOT EXISTS `$table` (" . implode(', ', $lines) . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    }

    public function getAlterTableSql(string $table, string $column, string $type): string
    {
        $sqlType = $this->mapToSqlType($type);
        return "ALTER TABLE `$table` ADD `$column` $sqlType";
    }

    public function getIndexSql(string $table, string $column): string
    {
        return "CREATE INDEX `idx_$column` ON `$table`(`$column`)";
    }

    private function mapToSqlType(string $type): string
    {
        $trimmed = trim($type);
        $lower = strtolower($trimmed);
        if (preg_match('/^varchar\s*\(\s*\d+\s*\)$/i', $trimmed)) return strtoupper($trimmed);
        if (preg_match('/^tinyint\s*\(\s*\d+\s*\)$/i', $trimmed)) return strtoupper($trimmed);
        if (preg_match('/^decimal\s*\(\s*\d+\s*,\s*\d+\s*\)$/i', $trimmed)) return strtoupper($trimmed);
        if ($lower === 'datetime' || $lower === 'timestamp' || $lower === 'date' || $lower === 'bigint' || $lower === 'longtext') {
            return strtoupper($trimmed);
        }
        if (strpos($lower, 'int') !== false) return "INT";
        if (strpos($lower, 'bool') !== false) return "TINYINT(1)";
        if (strpos($lower, 'decimal') !== false) return "DECIMAL(12,4)";
        if (strpos($lower, 'float') !== false || strpos($lower, 'double') !== false) return "DOUBLE";
        if (strpos($lower, 'text') !== false || $lower === 'string') return "TEXT";
        if (strpos($lower, 'json') !== false || strpos($lower, 'array') !== false) return "JSON";
        if (strpos($lower, 'date') !== false) return "DATETIME";
        return "VARCHAR(255)";
    }
}
