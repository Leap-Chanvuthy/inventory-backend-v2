<?php

namespace App\Service\KPI\Support;

use Illuminate\Support\Facades\Schema;

class KPISchemaInspector
{
    protected array $tableCache = [];
    protected array $columnCache = [];

    public function tableExists(string $table): bool
    {
        if (!array_key_exists($table, $this->tableCache)) {
            $this->tableCache[$table] = Schema::hasTable($table);
        }

        return $this->tableCache[$table];
    }

    public function columnExists(string $table, string $column): bool
    {
        $key = $table . '.' . $column;

        if (!array_key_exists($key, $this->columnCache)) {
            $this->columnCache[$key] = $this->tableExists($table) && Schema::hasColumn($table, $column);
        }

        return $this->columnCache[$key];
    }

    public function firstExistingColumn(string $table, array $columns): ?string
    {
        foreach ($columns as $column) {
            if ($this->columnExists($table, $column)) {
                return $column;
            }
        }

        return null;
    }
}
