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
     * Exécuter une requête SQL avec pagination côté base de données
     */
    public function executeQueryWithPagination(string $query, string $database = null, int $page = 1, int $perPage = 25): array
    {
        try {
            // Détecter si l'utilisateur a déjà spécifié LIMIT/OFFSET
            $existingPagination = $this->extractExistingPagination($query);
            $hasUserPagination = $existingPagination['hasLimit'] || $existingPagination['hasOffset'];

            // Obtenir le nombre total de résultats en tenant compte de la pagination utilisateur
            $totalCount = $this->getQueryTotalCount($query, $database);

            // Calculer l'offset pour la pagination système
            $offset = ($page - 1) * $perPage;

            // Ajouter la pagination en respectant les clauses utilisateur existantes
            $paginatedQuery = $this->addPaginationToQuery($query, $offset, $perPage);

            // Exécuter la requête paginée
            if ($database) {
                $results = $this->executeQueryOnDatabase($paginatedQuery, $database);
            } else {
                $results = DB::select($paginatedQuery);
            }

            return [
                'success' => true,
                'data' => collect($results)->map(function ($row) {
                    return (array) $row;
                })->toArray(),
                'total_count' => $totalCount,
                'current_page' => $page,
                'per_page' => $perPage,
                'total_pages' => (int) ceil($totalCount / $perPage),
                'message' => 'Requête exécutée avec succès',
                'has_user_pagination' => $hasUserPagination,
                'user_pagination_info' => $existingPagination
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'data' => [],
                'total_count' => 0,
                'current_page' => $page,
                'per_page' => $perPage,
                'total_pages' => 0,
                'message' => $e->getMessage(),
                'has_user_pagination' => false,
                'user_pagination_info' => []
            ];
        }
    }

    /**
     * Méthode simplifiée pour obtenir le nombre total sans gestion LIMIT/OFFSET
     */
    private function getQueryTotalCountSimple(string $query, string $database = null): int
    {
        try {
            // Créer une requête COUNT simple
            $countQuery = "SELECT COUNT(*) as total FROM ({$query}) as count_query";

            if ($database) {
                $result = $this->executeQueryOnDatabase($countQuery, $database);
            } else {
                $result = DB::select($countQuery);
            }

            return $result[0]->total ?? 0;
        } catch (\Exception $e) {
            // Si la requête COUNT échoue, essayer d'exécuter la requête originale et compter
            try {
                if ($database) {
                    $results = $this->executeQueryOnDatabase($query, $database);
                } else {
                    $results = DB::select($query);
                }
                return count($results);
            } catch (\Exception $e2) {
                return 0;
            }
        }
    }

    /**
     * Ajouter une pagination simple sans gestion des LIMIT/OFFSET existants
     */
    private function addSimplePagination(string $query, int $offset, int $limit): string
    {
        // Supprimer le point-virgule final s'il existe
        $query = rtrim(trim($query), ';');

        // Ajouter LIMIT et OFFSET à la fin
        return $query . " LIMIT {$limit} OFFSET {$offset}";
    }

    /**
     * Obtenir le nombre total de résultats d'une requête (VERSION COMPLEXE DÉSACTIVÉE)
     */
    private function getQueryTotalCount(string $query, string $database = null): int
    {
        try {
            // Extraire les clauses LIMIT/OFFSET existantes pour le comptage
            $existingPagination = $this->extractExistingPagination($query);

            // Pour le comptage, on utilise la requête sans LIMIT/OFFSET
            $queryForCount = $existingPagination['queryWithoutPagination'];

            // Si l'utilisateur avait spécifié un LIMIT, le total ne peut pas dépasser cette valeur
            $userLimit = $existingPagination['limitValue'];

            // Créer une requête COUNT en wrappant la requête sans pagination
            $countQuery = "SELECT COUNT(*) as total FROM ({$queryForCount}) as count_query";

            if ($database) {
                $result = $this->executeQueryOnDatabase($countQuery, $database);
            } else {
                $result = DB::select($countQuery);
            }

            $totalCount = $result[0]->total ?? 0;

            // Si l'utilisateur avait spécifié un LIMIT, on limite le total à cette valeur
            if ($userLimit && $totalCount > $userLimit) {
                $totalCount = $userLimit;
            }

            return $totalCount;
        } catch (\Exception $e) {
            // Si la requête COUNT échoue, essayer d'exécuter la requête originale et compter
            try {
                // Extraire la pagination pour utiliser la requête sans LIMIT/OFFSET
                $existingPagination = $this->extractExistingPagination($query);
                $queryForCount = $existingPagination['queryWithoutPagination'];

                if ($database) {
                    $results = $this->executeQueryOnDatabase($queryForCount, $database);
                } else {
                    $results = DB::select($queryForCount);
                }

                $totalCount = count($results);

                // Appliquer la limite utilisateur si elle existe
                $userLimit = $existingPagination['limitValue'];
                if ($userLimit && $totalCount > $userLimit) {
                    $totalCount = $userLimit;
                }

                return $totalCount;
            } catch (\Exception $e2) {
                return 0;
            }
        }
    }

    /**
     * Ajouter LIMIT et OFFSET à une requête SQL en gérant les clauses existantes
     */
    private function addPaginationToQuery(string $query, int $offset, int $limit): string
    {
        // Supprimer le point-virgule final s'il existe
        $query = rtrim(trim($query), ';');

        // Vérifier si la requête contient déjà LIMIT ou OFFSET
        $existingPagination = $this->extractExistingPagination($query);

        if ($existingPagination['hasLimit'] || $existingPagination['hasOffset']) {
            // Si l'utilisateur a déjà spécifié LIMIT/OFFSET, on respecte sa requête
            // mais on doit adapter notre pagination
            return $this->adaptUserPaginationToSystemPagination($query, $existingPagination, $offset, $limit);
        }

        // Ajouter LIMIT et OFFSET normalement si pas de clauses existantes
        return $query . " LIMIT {$limit} OFFSET {$offset}";
    }

    /**
     * Extraire les clauses LIMIT et OFFSET existantes d'une requête
     */
    private function extractExistingPagination(string $query): array
    {
        $result = [
            'hasLimit' => false,
            'hasOffset' => false,
            'limitValue' => null,
            'offsetValue' => null,
            'queryWithoutPagination' => $query
        ];

        // Pattern pour détecter LIMIT et OFFSET (insensible à la casse)
        $pattern = '/\s+(LIMIT\s+(\d+))(\s+OFFSET\s+(\d+))?\s*$/i';

        if (preg_match($pattern, $query, $matches)) {
            $result['hasLimit'] = true;
            $result['limitValue'] = (int) $matches[2];

            if (isset($matches[4]) && $matches[4] !== '') {
                $result['hasOffset'] = true;
                $result['offsetValue'] = (int) $matches[4];
            }

            // Supprimer les clauses LIMIT/OFFSET de la requête
            $result['queryWithoutPagination'] = preg_replace($pattern, '', $query);
        } else {
            // Vérifier seulement OFFSET (cas rare mais possible)
            $offsetPattern = '/\s+OFFSET\s+(\d+)\s*$/i';
            if (preg_match($offsetPattern, $query, $matches)) {
                $result['hasOffset'] = true;
                $result['offsetValue'] = (int) $matches[1];
                $result['queryWithoutPagination'] = preg_replace($offsetPattern, '', $query);
            }
        }

        return $result;
    }

    /**
     * Adapter la pagination utilisateur à la pagination système
     */
    private function adaptUserPaginationToSystemPagination(string $query, array $existingPagination, int $systemOffset, int $systemLimit): string
    {
        // Si l'utilisateur a spécifié LIMIT/OFFSET, on doit calculer la pagination relative
        $userLimit = $existingPagination['limitValue'];
        $userOffset = $existingPagination['offsetValue'] ?? 0;

        // Calculer les nouvelles valeurs en tenant compte de la pagination système
        $newOffset = $userOffset + $systemOffset;

        // Pour le LIMIT, on prend le minimum entre ce que l'utilisateur veut restant et notre limite système
        $remainingUserLimit = max(0, $userLimit - $systemOffset);
        $newLimit = min($remainingUserLimit, $systemLimit);

        // Reconstruire la requête avec les nouvelles valeurs
        $baseQuery = $existingPagination['queryWithoutPagination'];

        if ($newLimit > 0) {
            return $baseQuery . " LIMIT {$newLimit} OFFSET {$newOffset}";
        } else {
            // Retourner une requête qui ne retournera aucun résultat
            return $baseQuery . " LIMIT 0";
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
     * Créer une nouvelle base de données
     */
    public function createDatabase(string $databaseName): array
    {
        try {
            // Valider le nom de la base de données
            if (!$this->isValidDatabaseName($databaseName)) {
                return [
                    'success' => false,
                    'message' => 'Le nom de la base de données contient des caractères non autorisés. Utilisez uniquement des lettres, chiffres et underscores.'
                ];
            }

            // Vérifier si la base de données existe déjà
            if ($this->databaseExists($databaseName)) {
                return [
                    'success' => false,
                    'message' => "La base de données '{$databaseName}' existe déjà."
                ];
            }

            // Créer la base de données
            DB::statement("CREATE DATABASE `{$databaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            return [
                'success' => true,
                'message' => "Base de données '{$databaseName}' créée avec succès."
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la création de la base de données : ' . $e->getMessage()
            ];
        }
    }

    /**
     * Valider le nom d'une base de données
     */
    private function isValidDatabaseName(string $name): bool
    {
        // Le nom doit contenir uniquement des lettres, chiffres et underscores
        // et ne pas commencer par un chiffre
        return preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name) && strlen($name) <= 64;
    }

    /**
     * Vérifier si une base de données existe
     */
    private function databaseExists(string $databaseName): bool
    {
        try {
            $databases = $this->getAllDatabases();
            return in_array($databaseName, $databases);
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
