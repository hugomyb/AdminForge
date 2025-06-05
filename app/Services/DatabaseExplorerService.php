<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseExplorerService
{
    /**
     * Lister toutes les bases de données disponibles
     */
    public function getAllDatabases(): array
    {
        try {
            $databases = DB::select('SHOW DATABASES');
            return collect($databases)
                ->pluck('Database')
                ->filter(function ($database) {
                    // Filtrer les bases système
                    return !in_array($database, [
                        'information_schema',
                        'performance_schema',
                        'mysql',
                        'sys'
                    ]);
                })
                ->values()
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Lister toutes les tables d'une base de données
     */
    public function getTablesForDatabase(string $database): array
    {
        try {
            // Utiliser une connexion dédiée pour éviter d'affecter la connexion principale
            $config = config('database.connections.mysql');
            $config['database'] = $database;
            $connectionName = 'temp_tables_' . uniqid();

            try {
                config(["database.connections.{$connectionName}" => $config]);

                $tables = DB::connection($connectionName)->select('SHOW TABLES');
                $tableKey = "Tables_in_{$database}";

                $result = collect($tables)
                    ->pluck($tableKey)
                    ->map(function ($tableName) use ($database) {
                        return [
                            'name' => $tableName,
                            'row_count' => $this->getTableRowCount($database, $tableName),
                            'columns' => $this->getTableColumns($database, $tableName)
                        ];
                    })
                    ->toArray();

                return $result;
            } finally {
                DB::purge($connectionName);
                $connections = config('database.connections');
                unset($connections[$connectionName]);
                config(['database.connections' => $connections]);
            }
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Obtenir les colonnes d'une table
     */
    public function getTableColumns(string $database, string $table): array
    {
        try {
            $columns = DB::select("DESCRIBE `{$database}`.`{$table}`");
            
            return collect($columns)->map(function ($column) {
                return [
                    'name' => $column->Field,
                    'type' => $column->Type,
                    'null' => $column->Null === 'YES',
                    'key' => $column->Key,
                    'default' => $column->Default,
                    'extra' => $column->Extra
                ];
            })->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Obtenir le nombre de lignes d'une table
     */
    public function getTableRowCount(string $database, string $table): int
    {
        try {
            $result = DB::select("SELECT COUNT(*) as count FROM `{$database}`.`{$table}`");
            return $result[0]->count ?? 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Obtenir les index d'une table
     */
    public function getTableIndexes(string $database, string $table): array
    {
        try {
            $indexes = DB::select("SHOW INDEX FROM `{$database}`.`{$table}`");
            
            return collect($indexes)->map(function ($index) {
                return [
                    'name' => $index->Key_name,
                    'column' => $index->Column_name,
                    'unique' => !$index->Non_unique,
                    'type' => $index->Index_type
                ];
            })->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Obtenir les clés étrangères d'une table
     */
    public function getTableForeignKeys(string $database, string $table): array
    {
        try {
            $foreignKeys = DB::select("
                SELECT 
                    COLUMN_NAME,
                    REFERENCED_TABLE_NAME,
                    REFERENCED_COLUMN_NAME,
                    CONSTRAINT_NAME
                FROM information_schema.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = ? 
                AND TABLE_NAME = ? 
                AND REFERENCED_TABLE_NAME IS NOT NULL
            ", [$database, $table]);
            
            return collect($foreignKeys)->map(function ($fk) {
                return [
                    'column' => $fk->COLUMN_NAME,
                    'referenced_table' => $fk->REFERENCED_TABLE_NAME,
                    'referenced_column' => $fk->REFERENCED_COLUMN_NAME,
                    'constraint_name' => $fk->CONSTRAINT_NAME
                ];
            })->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Obtenir les données d'une table avec pagination
     */
    public function getTableData(string $database, string $table, int $limit = 100, int $offset = 0): array
    {
        try {
            $data = DB::select("SELECT * FROM `{$database}`.`{$table}` LIMIT {$limit} OFFSET {$offset}");
            
            return [
                'data' => collect($data)->map(function ($row) {
                    return (array) $row;
                })->toArray(),
                'total' => $this->getTableRowCount($database, $table)
            ];
        } catch (\Exception $e) {
            return [
                'data' => [],
                'total' => 0
            ];
        }
    }

    /**
     * Exécuter une requête SQL personnalisée
     */
    public function executeQuery(string $query, string $database = null): array
    {
        try {
            // Si une base de données spécifique est demandée, créer une connexion dédiée
            if ($database) {
                $results = $this->executeQueryOnDatabase($query, $database);
            } else {
                $results = DB::select($query);
            }

            return [
                'success' => true,
                'data' => collect($results)->map(function ($row) {
                    return (array) $row;
                })->toArray(),
                'message' => 'Requête exécutée avec succès'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'data' => [],
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Exécuter une requête sur une base de données spécifique sans affecter la connexion principale
     */
    private function executeQueryOnDatabase(string $query, string $database): array
    {
        // Créer une configuration de connexion temporaire pour la base de données spécifiée
        $config = config('database.connections.mysql');
        $config['database'] = $database;

        // Créer une connexion temporaire avec un nom unique
        $connectionName = 'temp_sql_playground_' . uniqid();

        try {
            // Configurer la connexion temporaire
            config(["database.connections.{$connectionName}" => $config]);

            // Exécuter la requête sur cette connexion
            $results = DB::connection($connectionName)->select($query);

            return $results;
        } finally {
            // Nettoyer la connexion temporaire
            DB::purge($connectionName);

            // Supprimer la configuration temporaire
            $connections = config('database.connections');
            unset($connections[$connectionName]);
            config(['database.connections' => $connections]);
        }
    }

    /**
     * Obtenir les informations générales d'une base de données
     */
    public function getDatabaseInfo(string $database): array
    {
        try {
            $tables = $this->getTablesForDatabase($database);
            $totalTables = count($tables);
            $totalRows = collect($tables)->sum('row_count');

            // Taille de la base de données
            $sizeQuery = DB::select("
                SELECT
                    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
                FROM information_schema.tables
                WHERE table_schema = ?
            ", [$database]);

            $sizeMb = $sizeQuery[0]->size_mb ?? 0;

            return [
                'name' => $database,
                'total_tables' => $totalTables,
                'total_rows' => $totalRows,
                'size_mb' => $sizeMb,
                'tables' => $tables
            ];
        } catch (\Exception $e) {
            return [
                'name' => $database,
                'total_tables' => 0,
                'total_rows' => 0,
                'size_mb' => 0,
                'tables' => []
            ];
        }
    }

    /**
     * Détecter les colonnes qui sont potentiellement des clés étrangères dans les résultats
     */
    public function detectForeignKeyColumns(array $queryResults, string $database): array
    {
        if (empty($queryResults)) {
            return [];
        }

        $foreignKeyColumns = [];
        $columns = array_keys($queryResults[0]);

        foreach ($columns as $column) {
            // Détecter les colonnes qui se terminent par _id
            if (preg_match('/^(.+)_id$/', $column, $matches)) {
                $potentialTable = $matches[1];

                // Essayer au pluriel d'abord (convention Laravel)
                $potentialTables = [$potentialTable . 's', $potentialTable];

                // Vérifier si la table existe
                foreach ($potentialTables as $tableName) {
                    if ($this->tableExists($database, $tableName)) {
                        $foreignKeyColumns[$column] = [
                            'referenced_table' => $tableName,
                            'referenced_column' => 'id'
                        ];
                        break;
                    }
                }
            }
        }

        return $foreignKeyColumns;
    }

    /**
     * Vérifier si une table existe dans la base de données
     */
    public function tableExists(string $database, string $table): bool
    {
        try {
            $result = DB::select("
                SELECT COUNT(*) as count
                FROM information_schema.tables
                WHERE table_schema = ? AND table_name = ?
            ", [$database, $table]);

            return $result[0]->count > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Récupérer les informations d'un enregistrement pour le tooltip
     */
    public function getRecordTooltipInfo(string $database, string $table, string $column, $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            // Créer une connexion temporaire pour la base de données spécifiée
            $connectionName = 'temp_tooltip_' . uniqid();
            $config = config('database.connections.mysql');
            $config['database'] = $database;

            config(["database.connections.{$connectionName}" => $config]);

            // Récupérer les colonnes de la table pour identifier les champs d'affichage
            $columns = $this->getTableColumns($database, $table);
            $displayColumns = $this->getDisplayColumns($columns);

            if (empty($displayColumns)) {
                return null;
            }

            // Construire la requête
            $selectColumns = implode(', ', array_map(function($col) {
                return "`{$col}`";
            }, $displayColumns));

            $query = "SELECT {$selectColumns} FROM `{$table}` WHERE `{$column}` = ? LIMIT 1";
            $result = DB::connection($connectionName)->select($query, [$value]);

            if (empty($result)) {
                return null;
            }

            $record = (array) $result[0];

            return [
                'table' => $table,
                'data' => $record,
                'display_columns' => $displayColumns
            ];

        } catch (\Exception $e) {
            return null;
        } finally {
            // Nettoyer la connexion temporaire
            if (isset($connectionName)) {
                DB::purge($connectionName);
                $connections = config('database.connections');
                unset($connections[$connectionName]);
                config(['database.connections' => $connections]);
            }
        }
    }

    /**
     * Identifier les colonnes les plus appropriées pour l'affichage
     */
    private function getDisplayColumns(array $columns): array
    {
        $displayColumns = [];
        $columnNames = array_column($columns, 'name');

        // Priorité aux colonnes communes d'affichage
        $preferredColumns = ['name', 'title', 'label', 'nom', 'titre', 'email', 'username', 'login'];

        foreach ($preferredColumns as $preferred) {
            if (in_array($preferred, $columnNames)) {
                $displayColumns[] = $preferred;
                break; // Prendre seulement la première trouvée
            }
        }

        // Si aucune colonne préférée trouvée, prendre les premières colonnes non-id
        if (empty($displayColumns)) {
            foreach ($columnNames as $columnName) {
                if (!in_array($columnName, ['id', 'created_at', 'updated_at', 'deleted_at']) &&
                    !preg_match('/_id$/', $columnName)) {
                    $displayColumns[] = $columnName;
                    if (count($displayColumns) >= 2) break; // Limiter à 2 colonnes
                }
            }
        }

        // Fallback: au moins inclure l'id si rien d'autre
        if (empty($displayColumns) && in_array('id', $columnNames)) {
            $displayColumns[] = 'id';
        }

        return $displayColumns;
    }
}
