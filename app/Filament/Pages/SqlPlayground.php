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
    public bool $hasUserPagination = false;
    public array $userPaginationInfo = [];

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

        // Nettoyer toutes les propriétés au montage
        $this->cleanAllPublicProperties();
    }



    /**
     * Hook Livewire appelé avant chaque mise à jour
     */
    public function updating($name, $value)
    {
        // Nettoyer les valeurs string avant qu'elles soient assignées
        if (is_string($value)) {
            return $this->sanitizeString($value);
        }

        return $value;
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
            // NETTOYER TOUTES LES PROPRIÉTÉS PUBLIQUES AVANT TRAITEMENT
            $this->cleanAllPublicProperties();

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
                // Nettoyer les données avant de les assigner aux propriétés publiques
                $this->queryResults = $this->sanitizeArray($result['data']);
                $this->paginatedResults = $this->sanitizeArray($result['data']); // Les données sont déjà paginées
                $this->hasError = false;
                $this->errorMessage = '';
                $this->affectedRows = $result['total_count'];
                $this->queryExecuted = true;

                // Initialiser la pagination avec les données du service
                $this->totalResults = $result['total_count'];
                $this->currentPage = $result['current_page'];

                // Stocker les informations de pagination utilisateur
                $this->hasUserPagination = $result['has_user_pagination'] ?? false;
                $this->userPaginationInfo = $result['user_pagination_info'] ?? [];

                // Détecter les colonnes de clés étrangères avec nettoyage
                try {
                    $rawForeignKeyColumns = $this->getDatabaseService()->detectForeignKeyColumns(
                        $this->queryResults,
                        $this->selectedDatabase
                    );
                    $this->foreignKeyColumns = $this->sanitizeArray($rawForeignKeyColumns);
                } catch (\Exception $e) {
                    // En cas d'erreur, désactiver les clés étrangères pour cette requête
                    $this->foreignKeyColumns = [];
                    \Log::warning('Erreur lors de la détection des clés étrangères: ' . $e->getMessage());
                }

                // Ajouter à l'historique
                $this->addToHistory($this->sqlQuery, true);

                Notification::make()
                    ->title('Succès')
                    ->body("Requête exécutée en " . round($this->executionTime * 1000, 2) . "ms")
                    ->success()
                    ->send();
            } else {
                $this->hasError = true;
                $this->errorMessage = $this->sanitizeString($result['message']);
                $this->queryResults = [];

                // Ajouter à l'historique même en cas d'erreur
                $this->addToHistory($this->sqlQuery, false);

                Notification::make()
                    ->title('Erreur SQL')
                    ->body('Une erreur SQL s\'est produite. Consultez les détails ci-dessous.')
                    ->danger()
                    ->send();
            }

        } catch (\Exception $e) {
            $this->hasError = true;
            $this->errorMessage = $this->sanitizeString($e->getMessage());
            $this->queryResults = [];

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

        // Réinitialiser les informations de pagination utilisateur
        $this->hasUserPagination = false;
        $this->userPaginationInfo = [];
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
                $this->paginatedResults = $this->sanitizeArray($result['data']);
                $this->queryResults = $this->sanitizeArray($result['data']); // Synchroniser pour les autres fonctionnalités

                // Mettre à jour les colonnes de clés étrangères pour la nouvelle page avec nettoyage
                try {
                    $rawForeignKeyColumns = $this->getDatabaseService()->detectForeignKeyColumns(
                        $this->paginatedResults,
                        $this->selectedDatabase
                    );
                    $this->foreignKeyColumns = $this->sanitizeArray($rawForeignKeyColumns);
                } catch (\Exception $e) {
                    // En cas d'erreur, désactiver les clés étrangères pour cette page
                    $this->foreignKeyColumns = [];
                    \Log::warning('Erreur lors de la détection des clés étrangères (pagination): ' . $e->getMessage());
                }
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
        $this->dispatch('update-sql-editor', query: $this->sanitizeQueryForDispatch(''));
    }

    public function loadQueryFromHistory(string $encodedQuery): void
    {
        // Décoder la requête base64
        $query = base64_decode($encodedQuery);

        $this->sqlQuery = $query;

        // Émettre un événement pour mettre à jour l'éditeur
        $this->dispatch('update-sql-editor', query: $this->sanitizeQueryForDispatch($query));
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
            'query' => $this->sanitizeString($query),
            'success' => $success,
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'database' => $this->sanitizeString($this->selectedDatabase),
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
        $rawHistory = session('sql_query_history', []);
        $this->queryHistory = $this->sanitizeArray($rawHistory);
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
        $this->dispatch('update-sql-editor', query: $this->sanitizeQueryForDispatch($query));
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
            $this->dispatch('update-sql-editor', query: $this->sanitizeQueryForDispatch($result['query']));

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
            $this->dispatch('update-sql-editor', query: $this->sanitizeQueryForDispatch($result['improved_query']));

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

    /**
     * Nettoyer et sécuriser une requête SQL pour l'envoi via dispatch
     */
    private function sanitizeQueryForDispatch(string $query): string
    {
        // Simplement retourner la requête sans modification excessive
        // pour éviter les problèmes d'encodage
        return trim($query);
    }

    /**
     * Nettoyer une chaîne de caractères pour éviter les problèmes avec Livewire
     */
    private function sanitizeString(string $input): string
    {
        // Nettoyer la chaîne de caractères problématiques de manière plus simple
        $cleanString = trim($input);

        // Supprimer seulement les caractères de contrôle vraiment problématiques
        $cleanString = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $cleanString);

        return $cleanString;
    }

    /**
     * Nettoyer un tableau de données pour éviter les problèmes avec Livewire
     */
    private function sanitizeArray(array $data): array
    {
        $cleanData = [];

        foreach ($data as $key => $value) {
            $cleanKey = $this->sanitizeString((string) $key);

            if (is_array($value)) {
                $cleanData[$cleanKey] = $this->sanitizeArray($value);
            } elseif (is_string($value)) {
                $cleanData[$cleanKey] = $this->sanitizeString($value);
            } elseif (is_null($value)) {
                $cleanData[$cleanKey] = null;
            } else {
                // Pour les autres types (int, float, bool), on les garde tels quels
                $cleanData[$cleanKey] = $value;
            }
        }

        return $cleanData;
    }

    /**
     * Nettoyer toutes les propriétés publiques pour éviter les problèmes Livewire
     */
    private function cleanAllPublicProperties(): void
    {
        // Nettoyer les propriétés string
        $this->sqlQuery = $this->sanitizeString($this->sqlQuery ?? '');
        $this->selectedDatabase = $this->sanitizeString($this->selectedDatabase ?? '');
        $this->errorMessage = $this->sanitizeString($this->errorMessage ?? '');
        $this->aiPrompt = $this->sanitizeString($this->aiPrompt ?? '');
        $this->queryExplanation = $this->sanitizeString($this->queryExplanation ?? '');
        $this->originalQuery = $this->sanitizeString($this->originalQuery ?? '');
        $this->tempQueryForSave = $this->sanitizeString($this->tempQueryForSave ?? '');
        $this->tempDatabaseForSave = $this->sanitizeString($this->tempDatabaseForSave ?? '');

        // Nettoyer les propriétés array
        $this->queryResults = $this->sanitizeArray($this->queryResults ?? []);
        $this->paginatedResults = $this->sanitizeArray($this->paginatedResults ?? []);
        $this->queryHistory = $this->sanitizeArray($this->queryHistory ?? []);
        $this->savedQueries = $this->sanitizeArray($this->savedQueries ?? []);
        $this->foreignKeyColumns = $this->sanitizeArray($this->foreignKeyColumns ?? []);
        $this->userPaginationInfo = $this->sanitizeArray($this->userPaginationInfo ?? []);
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

    /**
     * Définir les actions disponibles pour cette page
     */
    protected function getActions(): array
    {
        return [
            $this->saveQueryAction(),
            $this->saveFromHistoryAction(),
            $this->deleteSavedQueryAction(),
        ];
    }

    // ===== ACTIONS FILAMENT =====

    public function saveQueryAction(): Action
    {
        return Action::make('saveQueryAction')
            ->label('Sauvegarder la requête')
            ->icon('heroicon-o-bookmark')
            ->slideOver()
            ->modalWidth('md')
            ->modalSubmitActionLabel('Sauvegarder')
            ->form([
                Grid::make(1)->schema([
                    Textarea::make('query_display')
                        ->label('Requête à sauvegarder')
                        ->default(fn () => $this->tempQueryForSave)
                        ->disabled()
                        ->rows(4),

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
        $rawQueries = SavedQuery::where('user_id', Auth::id())
            ->where('database_name', $this->selectedDatabase ?: '')
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();

        $this->savedQueries = $this->sanitizeArray($rawQueries);
    }

    public function openSaveModal(): void
    {
        \Log::info('openSaveModal() appelée');
        \Log::info('sqlQuery: ' . $this->sqlQuery);
        \Log::info('selectedDatabase: ' . $this->selectedDatabase);

        if (empty(trim($this->sqlQuery))) {
            \Log::warning('Requête vide');
            Notification::make()
                ->title('Requête requise')
                ->body('Veuillez saisir une requête SQL avant de la sauvegarder')
                ->warning()
                ->send();
            return;
        }

        if (empty($this->selectedDatabase)) {
            \Log::warning('Base de données non sélectionnée');
            Notification::make()
                ->title('Base de données requise')
                ->body('Veuillez sélectionner une base de données')
                ->warning()
                ->send();
            return;
        }

        // Stocker temporairement les données pour le modal avec nettoyage
        $this->tempQueryForSave = $this->sanitizeString(trim($this->sqlQuery));
        $this->tempDatabaseForSave = $this->sanitizeString(trim($this->selectedDatabase));

        \Log::info('Données temporaires stockées');
        \Log::info('tempQueryForSave: ' . $this->tempQueryForSave);
        \Log::info('tempDatabaseForSave: ' . $this->tempDatabaseForSave);

        // Essayer d'ouvrir la modal avec une approche plus simple
        try {
            \Log::info('Tentative d\'ouverture de la modal');
            $this->mountAction('saveQueryAction');
            \Log::info('Modal ouverte avec succès');
        } catch (\Exception $e) {
            \Log::error('Erreur lors de l\'ouverture de la modal: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            // Si ça échoue, utiliser une notification pour informer l'utilisateur
            Notification::make()
                ->title('Erreur technique')
                ->body('Impossible d\'ouvrir la modal. Erreur: ' . $e->getMessage())
                ->danger()
                ->send();
        }
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
            $this->dispatch('update-sql-editor', query: $this->sanitizeQueryForDispatch($savedQuery->query));

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
            $this->dispatch('update-sql-editor', query: $this->sanitizeQueryForDispatch($savedQuery->query));

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

    public function deleteSavedQueryAction(): Action
    {
        return Action::make('deleteSavedQueryAction')
            ->label('Supprimer')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Supprimer la requête')
            ->modalDescription('Êtes-vous sûr de vouloir supprimer cette requête ? Cette action est irréversible.')
            ->modalSubmitActionLabel('Supprimer')
            ->action(function (array $arguments): void {
                try {
                    $queryId = $arguments['queryId'] ?? null;

                    if (!$queryId) {
                        Notification::make()
                            ->title('Erreur')
                            ->body('ID de requête manquant')
                            ->danger()
                            ->send();
                        return;
                    }

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
            });
    }

    public function deleteSavedQuery(int $queryId): void
    {
        $this->mountAction('deleteSavedQueryAction', ['queryId' => $queryId]);
    }

    public function saveFromHistory(string $encodedQuery, string $database): void
    {
        // Décoder la requête base64
        $query = base64_decode($encodedQuery);

        // Stocker temporairement les données pour le modal avec nettoyage
        $this->tempQueryForSave = $this->sanitizeString(trim($query));
        $this->tempDatabaseForSave = $this->sanitizeString(trim($database));

        try {
            $this->mountAction('saveFromHistoryAction');
        } catch (\Exception $e) {
            Notification::make()
                ->title('Erreur technique')
                ->body('Impossible d\'ouvrir la modal. Veuillez rafraîchir la page et réessayer.')
                ->danger()
                ->send();
        }
    }

    public function saveFromHistoryAction(): Action
    {
        return Action::make('saveFromHistoryAction')
            ->label('Sauvegarder la requête')
            ->icon('heroicon-o-bookmark')
            ->slideOver()
            ->modalWidth('md')
            ->form([
                Grid::make(1)->schema([
                    Textarea::make('query_display')
                        ->label('Requête à sauvegarder')
                        ->default(fn () => $this->tempQueryForSave)
                        ->disabled()
                        ->rows(4),

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
