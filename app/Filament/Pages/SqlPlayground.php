<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Services\DatabaseExplorerService;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use App\Services\OpenAIService;

class SqlPlayground extends Page implements HasForms, HasActions
{
    use InteractsWithForms;
    use InteractsWithActions;

    protected static ?string $navigationIcon = 'heroicon-o-code-bracket';
    protected static string $view = 'filament.pages.sql-playground';
    protected static ?string $title = 'SQL Playground';
    protected static ?string $navigationLabel = 'SQL Playground';
    protected static ?int $navigationSort = 1;
    protected ?string $maxContentWidth = 'full';

    public string $sqlQuery = '';
    public string $selectedDatabase = '';
    public array $queryResults = [];
    public array $queryHistory = [];
    public bool $hasError = false;
    public string $errorMessage = '';
    public float $executionTime = 0;
    public int $affectedRows = 0;
    public string $aiPrompt = '';
    public bool $showAiPanel = false;
    public array $foreignKeyColumns = [];
    public string $queryExplanation = '';
    public bool $queryExecuted = false;
    private bool $isExecuting = false;

    // Propriétés de pagination
    public int $currentPage = 1;
    public int $perPage = 25;
    public int $totalResults = 0;
    public array $paginatedResults = [];
    public string $originalQuery = ''; // Stocker la requête originale pour la pagination

    public function mount(): void
    {
        $this->loadQueryHistory();

        // Ne pas sélectionner automatiquement une base de données
        // L'utilisateur doit faire un choix conscient
        $this->selectedDatabase = '';
    }

    protected function getDatabaseService(): DatabaseExplorerService
    {
        return app(DatabaseExplorerService::class);
    }

    public function getDatabases(): array
    {
        return $this->getDatabaseService()->getAllDatabases();
    }

    public function updatedSelectedDatabase(): void
    {
        // Effacer les résultats précédents quand on change de base de données
        $this->clearResults();

        // Déclencher l'événement pour mettre à jour l'éditeur SQL
        $this->dispatch('database-changed');
    }

    public function executeQuery(): void
    {
        // Protection contre la double exécution
        if ($this->isExecuting) {
            return;
        }

        $this->isExecuting = true;

        try {
            // Vérifier qu'une base de données est sélectionnée
            if (empty($this->selectedDatabase)) {
                Notification::make()
                    ->title('Base de données requise')
                    ->body('Veuillez sélectionner une base de données avant d\'exécuter une requête')
                    ->warning()
                    ->send();
                return;
            }

            if (empty(trim($this->sqlQuery))) {
                Notification::make()
                    ->title('Requête requise')
                    ->body('Veuillez saisir une requête SQL')
                    ->warning()
                    ->send();
                return;
            }

            $startTime = microtime(true);

            // Stocker la requête originale pour la pagination
            $this->originalQuery = $this->sqlQuery;

            // Exécuter la requête avec pagination côté base de données
            $result = $this->getDatabaseService()->executeQueryWithPagination(
                $this->sqlQuery,
                $this->selectedDatabase,
                $this->currentPage,
                $this->perPage
            );

            $this->executionTime = microtime(true) - $startTime;

            if ($result['success']) {
                $this->queryResults = $result['data'];
                $this->paginatedResults = $result['data']; // Les données sont déjà paginées
                $this->hasError = false;
                $this->errorMessage = '';
                $this->affectedRows = $result['total_count'];
                $this->queryExecuted = true;

                // Initialiser la pagination avec les données du service
                $this->totalResults = $result['total_count'];
                $this->currentPage = $result['current_page'];

                // Détecter les colonnes de clés étrangères
                $this->foreignKeyColumns = $this->getDatabaseService()->detectForeignKeyColumns(
                    $this->queryResults,
                    $this->selectedDatabase
                );

                // Ajouter à l'historique
                $this->addToHistory($this->sqlQuery, true);

                // Déclencher le scroll vers les résultats
                $this->dispatch('scroll-to-results');

                Notification::make()
                    ->title('Succès')
                    ->body("Requête exécutée en " . round($this->executionTime * 1000, 2) . "ms")
                    ->success()
                    ->send();
            } else {
                $this->hasError = true;
                $this->errorMessage = $result['message'];
                $this->queryResults = [];

                // Ajouter à l'historique même en cas d'erreur
                $this->addToHistory($this->sqlQuery, false);

                // Déclencher le scroll vers l'erreur
                $this->dispatch('scroll-to-error');

                Notification::make()
                    ->title('Erreur SQL')
                    ->body('Une erreur SQL s\'est produite. Consultez les détails ci-dessous.')
                    ->danger()
                    ->send();
            }

        } catch (\Exception $e) {
            $this->hasError = true;
            $this->errorMessage = $e->getMessage();
            $this->queryResults = [];

            // Déclencher le scroll vers l'erreur
            $this->dispatch('scroll-to-error');

            Notification::make()
                ->title('Erreur')
                ->body('Une erreur s\'est produite. Consultez les détails ci-dessous.')
                ->danger()
                ->send();
        } finally {
            // Réinitialiser le flag d'exécution
            $this->isExecuting = false;
        }
    }

    public function clearResults(): void
    {
        $this->queryResults = [];
        $this->hasError = false;
        $this->errorMessage = '';
        $this->executionTime = 0;
        $this->affectedRows = 0;
        $this->foreignKeyColumns = [];
        $this->queryExecuted = false;

        // Réinitialiser la pagination
        $this->currentPage = 1;
        $this->totalResults = 0;
        $this->paginatedResults = [];
        $this->originalQuery = '';
    }

    /**
     * Met à jour les résultats paginés en rechargeant depuis la base de données
     */
    protected function updatePaginatedResults(): void
    {
        if (empty($this->originalQuery)) {
            $this->paginatedResults = [];
            return;
        }

        try {
            $result = $this->getDatabaseService()->executeQueryWithPagination(
                $this->originalQuery,
                $this->selectedDatabase,
                $this->currentPage,
                $this->perPage
            );

            if ($result['success']) {
                $this->paginatedResults = $result['data'];
                $this->queryResults = $result['data']; // Synchroniser pour les autres fonctionnalités

                // Mettre à jour les colonnes de clés étrangères pour la nouvelle page
                $this->foreignKeyColumns = $this->getDatabaseService()->detectForeignKeyColumns(
                    $this->paginatedResults,
                    $this->selectedDatabase
                );
            }
        } catch (\Exception $e) {
            $this->paginatedResults = [];
            $this->queryResults = [];
        }
    }

    /**
     * Aller à une page spécifique
     */
    public function goToPage(int $page): void
    {
        $maxPage = $this->getTotalPages();

        if ($page < 1) {
            $page = 1;
        } elseif ($page > $maxPage) {
            $page = $maxPage;
        }

        if ($this->currentPage !== $page) {
            $this->currentPage = $page;
            $this->updatePaginatedResults();
        }
    }

    /**
     * Aller à la page suivante
     */
    public function nextPage(): void
    {
        $this->goToPage($this->currentPage + 1);
    }

    /**
     * Aller à la page précédente
     */
    public function previousPage(): void
    {
        $this->goToPage($this->currentPage - 1);
    }

    /**
     * Obtenir le nombre total de pages
     */
    public function getTotalPages(): int
    {
        if ($this->totalResults === 0) {
            return 1;
        }

        return (int) ceil($this->totalResults / $this->perPage);
    }

    /**
     * Vérifier s'il y a une page suivante
     */
    public function hasNextPage(): bool
    {
        return $this->currentPage < $this->getTotalPages();
    }

    /**
     * Vérifier s'il y a une page précédente
     */
    public function hasPreviousPage(): bool
    {
        return $this->currentPage > 1;
    }

    /**
     * Obtenir les informations de pagination pour l'affichage
     */
    public function getPaginationInfo(): array
    {
        if ($this->totalResults === 0) {
            return [
                'start' => 0,
                'end' => 0,
                'total' => 0,
                'current_page' => 1,
                'total_pages' => 1
            ];
        }

        $start = ($this->currentPage - 1) * $this->perPage + 1;
        $end = min($this->currentPage * $this->perPage, $this->totalResults);

        return [
            'start' => $start,
            'end' => $end,
            'total' => $this->totalResults,
            'current_page' => $this->currentPage,
            'total_pages' => $this->getTotalPages()
        ];
    }

    /**
     * Récupérer tous les résultats pour l'export (sans pagination)
     */
    private function getAllResultsForExport(): array
    {
        if (empty($this->originalQuery)) {
            return [];
        }

        try {
            $result = $this->getDatabaseService()->executeQuery($this->originalQuery, $this->selectedDatabase);

            if ($result['success']) {
                return $result['data'];
            }

            return [];
        } catch (\Exception $e) {
            return [];
        }
    }

    public function clearQuery(): void
    {
        \Log::info('clearQuery() appelée');
        $this->sqlQuery = '';
        $this->clearResults();

        // Émettre un événement pour mettre à jour l'éditeur
        \Log::info('Émission de l\'événement update-sql-editor avec query vide');
        $this->dispatch('update-sql-editor', query: '');
    }

    public function loadQueryFromHistory(string $query): void
    {
        $this->sqlQuery = $query;

        // Émettre un événement pour mettre à jour l'éditeur
        $this->dispatch('update-sql-editor', query: $query);
    }

    public function clearHistory(): void
    {
        $this->queryHistory = [];
        session()->forget('sql_query_history');

        Notification::make()
            ->title('Historique effacé')
            ->body('L\'historique des requêtes a été effacé')
            ->success()
            ->send();
    }

    protected function addToHistory(string $query, bool $success): void
    {
        $historyItem = [
            'query' => $query,
            'success' => $success,
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'database' => $this->selectedDatabase,
            'execution_time' => $this->executionTime
        ];

        // Ajouter au début du tableau
        array_unshift($this->queryHistory, $historyItem);

        // Limiter à 50 entrées
        $this->queryHistory = array_slice($this->queryHistory, 0, 50);

        // Sauvegarder en session
        session(['sql_query_history' => $this->queryHistory]);
    }

    protected function loadQueryHistory(): void
    {
        $this->queryHistory = session('sql_query_history', []);
    }

    public function getExampleQueries(): array
    {
        return [
            'SELECT * FROM users LIMIT 10',
            'SHOW TABLES',
            'DESCRIBE users',
            'SELECT COUNT(*) as total FROM users',
            'SHOW DATABASES',
            'SELECT TABLE_NAME, TABLE_ROWS FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()',
        ];
    }

    public function loadExampleQuery(string $query): void
    {
        $this->sqlQuery = $query;

        // Émettre un événement pour mettre à jour l'éditeur
        $this->dispatch('update-sql-editor', query: $query);
    }

    public function toggleAiPanel(): void
    {
        $this->showAiPanel = !$this->showAiPanel;
    }

    public function generateQueryFromAi(): void
    {
        if (empty(trim($this->aiPrompt))) {
            Notification::make()
                ->title('Erreur')
                ->body('Veuillez saisir une description pour générer la requête')
                ->danger()
                ->send();
            return;
        }

        $openAiService = app(OpenAIService::class);

        if (!$openAiService->isEnabled()) {
            Notification::make()
                ->title('IA non disponible')
                ->body('Veuillez configurer votre clé API OpenAI dans les paramètres')
                ->warning()
                ->send();
            return;
        }

        // Obtenir la structure des tables de la base sélectionnée
        $tableStructure = [];
        if ($this->selectedDatabase) {
            $tables = $this->getDatabaseService()->getTablesForDatabase($this->selectedDatabase);
            foreach ($tables as $table) {
                $tableStructure[$table['name']] = $table['columns'];
            }
        }

        $result = $openAiService->generateSqlQuery($this->aiPrompt, $tableStructure);

        if ($result['success']) {
            $this->sqlQuery = $result['query'];
            $this->showAiPanel = false;

            // Émettre un événement pour mettre à jour l'éditeur
            $this->dispatch('update-sql-editor', query: $result['query']);

            Notification::make()
                ->title('Requête générée')
                ->body('La requête SQL a été générée avec succès')
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Erreur IA')
                ->body($result['message'])
                ->danger()
                ->send();
        }
    }

    public function improveQuery(): void
    {
        if (empty(trim($this->sqlQuery))) {
            Notification::make()
                ->title('Erreur')
                ->body('Veuillez saisir une requête à améliorer')
                ->danger()
                ->send();
            return;
        }

        $openAiService = app(OpenAIService::class);

        if (!$openAiService->isEnabled()) {
            Notification::make()
                ->title('IA non disponible')
                ->body('Veuillez configurer votre clé API OpenAI dans les paramètres')
                ->warning()
                ->send();
            return;
        }

        $result = $openAiService->improveSqlQuery($this->sqlQuery);

        if ($result['success']) {
            $this->sqlQuery = $result['improved_query'];

            // Émettre un événement pour mettre à jour l'éditeur
            $this->dispatch('update-sql-editor', query: $result['improved_query']);

            Notification::make()
                ->title('Requête améliorée')
                ->body('La requête a été optimisée avec succès')
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Erreur IA')
                ->body($result['message'])
                ->danger()
                ->send();
        }
    }



    public function openExplanationModal(): void
    {
        $this->generateExplanation();
        $this->dispatch('open-modal', id: 'explain-query');
    }

    public function generateExplanation(): void
    {
        // Réinitialiser l'explication
        $this->queryExplanation = '';

        if (empty(trim($this->sqlQuery ?? ''))) {
            $this->queryExplanation = 'ERROR: Veuillez saisir une requête à expliquer';
            return;
        }

        $openAiService = app(OpenAIService::class);

        if (!$openAiService->isEnabled()) {
            $this->queryExplanation = 'ERROR: Veuillez configurer votre clé API OpenAI dans les paramètres';
            return;
        }

        try {
            $result = $openAiService->explainSqlQuery($this->sqlQuery);

            if ($result['success']) {
                $this->queryExplanation = $result['explanation'];
            } else {
                $this->queryExplanation = 'ERROR: ' . $result['message'];
            }
        } catch (\Exception $e) {
            $this->queryExplanation = 'ERROR: Exception lors de la génération: ' . $e->getMessage();
        }
    }

    public function isAiEnabled(): bool
    {
        return app(OpenAIService::class)->isEnabled();
    }

    public function getDatabaseSchema(): array
    {
        if (empty($this->selectedDatabase)) {
            return [];
        }

        try {
            $tables = $this->getDatabaseService()->getTablesForDatabase($this->selectedDatabase);
            $schema = [];

            foreach ($tables as $table) {
                $tableName = $table['name'];
                $columns = [];

                // Récupérer les colonnes de la table
                if (isset($table['columns']) && is_array($table['columns'])) {
                    foreach ($table['columns'] as $column) {
                        if (is_array($column) && isset($column['name'])) {
                            // Format pour CodeMirror: juste le nom de la colonne
                            $columns[] = $column['name'];
                        } else {
                            $columns[] = (string)$column;
                        }
                    }
                }

                // Format pour CodeMirror SQL: table_name => [column1, column2, ...]
                $schema[$tableName] = $columns;
            }

            return $schema;
        } catch (\Exception $e) {
            return [];
        }
    }

    public function exportResults(string $format = 'csv'): void
    {
        if (empty($this->originalQuery)) {
            Notification::make()
                ->title('Aucun résultat')
                ->body('Il n\'y a aucun résultat à exporter')
                ->warning()
                ->send();
            return;
        }

        try {
            if ($format === 'csv') {
                // Récupérer TOUS les résultats pour l'export (sans pagination)
                $allResults = $this->getAllResultsForExport();

                if (empty($allResults)) {
                    Notification::make()
                        ->title('Aucun résultat')
                        ->body('Il n\'y a aucun résultat à exporter')
                        ->warning()
                        ->send();
                    return;
                }

                $csv = '';

                // En-têtes
                $headers = array_keys($allResults[0]);
                $csv .= implode(',', array_map(function($header) {
                    return '"' . str_replace('"', '""', $header) . '"';
                }, $headers)) . "\n";

                // Données
                foreach ($allResults as $row) {
                    $csvRow = array_map(function($value) {
                        if (is_null($value)) {
                            return '';
                        }
                        return '"' . str_replace('"', '""', (string)$value) . '"';
                    }, array_values($row));
                    $csv .= implode(',', $csvRow) . "\n";
                }

                // Utiliser JavaScript pour télécharger le fichier
                $this->dispatch('download-csv', [
                    'content' => $csv,
                    'filename' => 'sql_results_' . date('Y-m-d_H-i-s') . '.csv'
                ]);

                Notification::make()
                    ->title('Export réussi')
                    ->body('Le fichier CSV a été généré')
                    ->success()
                    ->send();
            }

        } catch (\Exception $e) {
            Notification::make()
                ->title('Erreur d\'export')
                ->body('Impossible d\'exporter les résultats : ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        $actions = [];

        if ($this->isAiEnabled()) {
            $actions[] = Action::make('ai_generate')
                ->label('Générer avec IA')
                ->icon('heroicon-o-sparkles')
                ->color('purple')
                ->action('toggleAiPanel');


        }

        return $actions;
    }
}
