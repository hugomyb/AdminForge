<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\SavedQuery;
use App\Services\DatabaseExplorerService;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Auth;

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

    // Propriétés pour les requêtes sauvegardées
    public array $savedQueries = [];

    // Propriétés temporaires pour les modals
    public string $tempQueryForSave = '';
    public string $tempDatabaseForSave = '';

    public function mount(): void
    {
        $this->loadQueryHistory();
        $this->loadSavedQueries();

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

        // Recharger les requêtes sauvegardées pour la nouvelle base de données
        $this->loadSavedQueries();

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

    // ===== ACTIONS FILAMENT =====

    public function saveQueryAction(): Action
    {
        return Action::make('saveQuery')
            ->label('Sauvegarder la requête')
            ->icon('heroicon-o-bookmark')
            ->slideOver()
            ->modalWidth('md')
            ->modalSubmitActionLabel('Sauvegarder')
            ->form([
                Grid::make(1)->schema([
                    Placeholder::make('query_preview')
                        ->label('Requête à sauvegarder')
                        ->content(fn () => new \Illuminate\Support\HtmlString(
                            '<div class="bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4 max-h-40 overflow-y-auto">' .
                            '<div class="flex items-center gap-2 mb-2">' .
                            '<svg class="w-4 h-4 text-gray-500" fill="currentColor" viewBox="0 0 24 24"><path fill-rule="evenodd" d="M14.447 3.026a.75.75 0 0 1 .527.921l-4.5 16.5a.75.75 0 0 1-1.448-.394l4.5-16.5a.75.75 0 0 1 .921-.527ZM16.72 6.22a.75.75 0 0 1 1.06 0l5.25 5.25a.75.75 0 0 1 0 1.06l-5.25 5.25a.75.75 0 1 1-1.06-1.06L21.44 12l-4.72-4.72a.75.75 0 0 1 0-1.06Zm-9.44 0a.75.75 0 0 1 0 1.06L2.56 12l4.72 4.72a.75.75 0 0 1-1.06 1.06L.97 12.53a.75.75 0 0 1 0-1.06l5.25-5.25a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd"/></svg>' .
                            '<span class="text-xs font-medium text-gray-600 dark:text-gray-400 uppercase tracking-wide">Requête SQL</span>' .
                            '</div>' .
                            '<pre class="text-sm font-mono text-gray-900 dark:text-gray-100 whitespace-pre-wrap leading-relaxed">' . e($this->tempQueryForSave) . '</pre>' .
                            '</div>'
                        )),

                    Placeholder::make('database_info')
                        ->label('Base de données')
                        ->content(fn () => $this->tempDatabaseForSave),

                    TextInput::make('name')
                        ->label('Nom de la requête')
                        ->placeholder('Saisissez un nom pour cette requête...')
                        ->required()
                        ->maxLength(255)
                        ->suffixAction(
                            $this->isAiEnabled()
                                ? \Filament\Forms\Components\Actions\Action::make('generateName')
                                    ->icon('heroicon-o-sparkles')
                                    ->color('purple')
                                    ->tooltip('Générer un nom avec l\'IA')
                                    ->action(function ($set) {
                                        $openAiService = app(OpenAIService::class);
                                        $result = $openAiService->generateQueryName($this->tempQueryForSave);

                                        if ($result['success']) {
                                            $set('name', $result['name']);
                                            Notification::make()
                                                ->title('Nom généré')
                                                ->body('Le nom de la requête a été généré avec succès')
                                                ->success()
                                                ->send();
                                        } else {
                                            Notification::make()
                                                ->title('Erreur IA')
                                                ->body($result['message'])
                                                ->danger()
                                                ->send();
                                        }
                                    })
                                : null
                        ),
                ])
            ])
            ->action(function (array $data): void {
                try {
                    SavedQuery::create([
                        'name' => trim($data['name']),
                        'query' => $this->tempQueryForSave,
                        'database_name' => $this->tempDatabaseForSave,
                        'user_id' => Auth::id(),
                    ]);

                    $this->loadSavedQueries();

                    // Nettoyer les propriétés temporaires
                    $this->tempQueryForSave = '';
                    $this->tempDatabaseForSave = '';

                    Notification::make()
                        ->title('Requête sauvegardée')
                        ->body('La requête a été sauvegardée avec succès')
                        ->success()
                        ->send();

                } catch (\Exception $e) {
                    Notification::make()
                        ->title('Erreur de sauvegarde')
                        ->body('Impossible de sauvegarder la requête : ' . $e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    // ===== MÉTHODES POUR LES REQUÊTES SAUVEGARDÉES =====

    public function loadSavedQueries(): void
    {
        $this->savedQueries = SavedQuery::where('user_id', Auth::id())
            ->where('database_name', $this->selectedDatabase ?: '')
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }

    public function openSaveModal(): void
    {
        if (empty(trim($this->sqlQuery))) {
            Notification::make()
                ->title('Requête requise')
                ->body('Veuillez saisir une requête SQL avant de la sauvegarder')
                ->warning()
                ->send();
            return;
        }

        if (empty($this->selectedDatabase)) {
            Notification::make()
                ->title('Base de données requise')
                ->body('Veuillez sélectionner une base de données')
                ->warning()
                ->send();
            return;
        }

        // Stocker temporairement les données pour le modal
        $this->tempQueryForSave = $this->sqlQuery;
        $this->tempDatabaseForSave = $this->selectedDatabase;

        $this->mountAction('saveQuery');
    }



    public function loadSavedQuery(int $queryId): void
    {
        try {
            $savedQuery = SavedQuery::where('id', $queryId)
                ->where('user_id', Auth::id())
                ->first();

            if (!$savedQuery) {
                Notification::make()
                    ->title('Requête introuvable')
                    ->body('La requête demandée n\'existe pas ou ne vous appartient pas')
                    ->warning()
                    ->send();
                return;
            }

            // Changer de base de données si nécessaire
            if ($savedQuery->database_name !== $this->selectedDatabase) {
                $this->selectedDatabase = $savedQuery->database_name;
                $this->clearResults();
                $this->dispatch('database-changed');
            }

            $this->sqlQuery = $savedQuery->query;

            // Émettre un événement pour mettre à jour l'éditeur
            $this->dispatch('update-sql-editor', query: $savedQuery->query);

            Notification::make()
                ->title('Requête chargée pour modification')
                ->body("La requête \"{$savedQuery->name}\" a été chargée dans l'éditeur")
                ->success()
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->title('Erreur de chargement')
                ->body('Impossible de charger la requête : ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function executeFromSaved(int $queryId): void
    {
        try {
            $savedQuery = SavedQuery::where('id', $queryId)
                ->where('user_id', Auth::id())
                ->first();

            if (!$savedQuery) {
                Notification::make()
                    ->title('Requête introuvable')
                    ->body('La requête demandée n\'existe pas ou ne vous appartient pas')
                    ->warning()
                    ->send();
                return;
            }

            // Changer de base de données si nécessaire
            if ($savedQuery->database_name !== $this->selectedDatabase) {
                $this->selectedDatabase = $savedQuery->database_name;
                $this->clearResults();
                $this->dispatch('database-changed');
            }

            // Charger la requête
            $this->sqlQuery = $savedQuery->query;
            $this->dispatch('update-sql-editor', query: $savedQuery->query);

            // Exécuter immédiatement
            $this->executeQuery();

            Notification::make()
                ->title('Requête exécutée')
                ->body("La requête \"{$savedQuery->name}\" a été exécutée")
                ->success()
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->title('Erreur d\'exécution')
                ->body('Impossible d\'exécuter la requête : ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function deleteSavedQuery(int $queryId): void
    {
        try {
            $savedQuery = SavedQuery::where('id', $queryId)
                ->where('user_id', Auth::id())
                ->first();

            if (!$savedQuery) {
                Notification::make()
                    ->title('Requête introuvable')
                    ->body('La requête demandée n\'existe pas ou ne vous appartient pas')
                    ->warning()
                    ->send();
                return;
            }

            $queryName = $savedQuery->name;
            $savedQuery->delete();
            $this->loadSavedQueries();

            Notification::make()
                ->title('Requête supprimée')
                ->body("La requête \"{$queryName}\" a été supprimée")
                ->success()
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->title('Erreur de suppression')
                ->body('Impossible de supprimer la requête : ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function saveFromHistory(string $query, string $database): void
    {
        // Stocker temporairement les données pour le modal
        $this->tempQueryForSave = $query;
        $this->tempDatabaseForSave = $database;

        $this->mountAction('saveFromHistoryAction');
    }

    public function saveFromHistoryAction(): Action
    {
        return Action::make('saveFromHistory')
            ->label('Sauvegarder la requête')
            ->icon('heroicon-o-bookmark')
            ->slideOver()
            ->modalWidth('md')
            ->form([
                Grid::make(1)->schema([
                    Placeholder::make('query_preview')
                        ->label('Requête à sauvegarder')
                        ->content(fn () => new \Illuminate\Support\HtmlString(
                            '<div class="bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4 max-h-40 overflow-y-auto">' .
                            '<div class="flex items-center gap-2 mb-2">' .
                            '<svg class="w-4 h-4 text-gray-500" fill="currentColor" viewBox="0 0 24 24"><path fill-rule="evenodd" d="M14.447 3.026a.75.75 0 0 1 .527.921l-4.5 16.5a.75.75 0 0 1-1.448-.394l4.5-16.5a.75.75 0 0 1 .921-.527ZM16.72 6.22a.75.75 0 0 1 1.06 0l5.25 5.25a.75.75 0 0 1 0 1.06l-5.25 5.25a.75.75 0 1 1-1.06-1.06L21.44 12l-4.72-4.72a.75.75 0 0 1 0-1.06Zm-9.44 0a.75.75 0 0 1 0 1.06L2.56 12l4.72 4.72a.75.75 0 0 1-1.06 1.06L.97 12.53a.75.75 0 0 1 0-1.06l5.25-5.25a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd"/></svg>' .
                            '<span class="text-xs font-medium text-gray-600 dark:text-gray-400 uppercase tracking-wide">Requête SQL</span>' .
                            '</div>' .
                            '<pre class="text-sm font-mono text-gray-900 dark:text-gray-100 whitespace-pre-wrap leading-relaxed">' . e($this->tempQueryForSave) . '</pre>' .
                            '</div>'
                        )),

                    Placeholder::make('database_info')
                        ->label('Base de données')
                        ->content(fn () => $this->tempDatabaseForSave),

                    TextInput::make('name')
                        ->label('Nom de la requête')
                        ->placeholder('Saisissez un nom pour cette requête...')
                        ->required()
                        ->maxLength(255)
                        ->suffixAction(
                            $this->isAiEnabled()
                                ? \Filament\Forms\Components\Actions\Action::make('generateName')
                                    ->icon('heroicon-o-sparkles')
                                    ->color('purple')
                                    ->tooltip('Générer un nom avec l\'IA')
                                    ->action(function ($set) {
                                        $openAiService = app(OpenAIService::class);
                                        $result = $openAiService->generateQueryName($this->tempQueryForSave);

                                        if ($result['success']) {
                                            $set('name', $result['name']);
                                            Notification::make()
                                                ->title('Nom généré')
                                                ->body('Le nom de la requête a été généré avec succès')
                                                ->success()
                                                ->send();
                                        } else {
                                            Notification::make()
                                                ->title('Erreur IA')
                                                ->body($result['message'])
                                                ->danger()
                                                ->send();
                                        }
                                    })
                                : null
                        ),
                ])
            ])
            ->action(function (array $data): void {
                try {
                    SavedQuery::create([
                        'name' => trim($data['name']),
                        'query' => $this->tempQueryForSave,
                        'database_name' => $this->tempDatabaseForSave,
                        'user_id' => Auth::id(),
                    ]);

                    $this->loadSavedQueries();

                    // Nettoyer les propriétés temporaires
                    $this->tempQueryForSave = '';
                    $this->tempDatabaseForSave = '';

                    Notification::make()
                        ->title('Requête sauvegardée')
                        ->body('La requête de l\'historique a été sauvegardée avec succès')
                        ->success()
                        ->send();

                } catch (\Exception $e) {
                    Notification::make()
                        ->title('Erreur de sauvegarde')
                        ->body('Impossible de sauvegarder la requête : ' . $e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

}
