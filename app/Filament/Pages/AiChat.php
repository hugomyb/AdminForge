<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Services\DatabaseExplorerService;
use App\Services\OpenAIService;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;

class AiChat extends Page implements HasForms, HasActions
{
    use InteractsWithForms;
    use InteractsWithActions;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static string $view = 'filament.pages.ai-chat';
    protected static ?string $title = 'Chat IA Contextuel';
    protected static ?string $navigationLabel = 'Chat IA';
    protected static bool $shouldRegisterNavigation = false;

    public string $selectedDatabase = '';
    public string $selectedTable = '';
    public string $userMessage = '';
    public array $chatHistory = [];
    public bool $isLoading = false;

    public function mount(): void
    {
        $this->loadChatHistory();

        // Sélectionner la première base de données disponible par défaut
        $databases = $this->getDatabaseService()->getAllDatabases();
        if (!empty($databases)) {
            $this->selectedDatabase = $databases[0];
        }
    }

    protected function getDatabaseService(): DatabaseExplorerService
    {
        return app(DatabaseExplorerService::class);
    }

    protected function getOpenAIService(): OpenAIService
    {
        return app(OpenAIService::class);
    }

    public function getDatabases(): array
    {
        return $this->getDatabaseService()->getAllDatabases();
    }

    public function getTables(): array
    {
        if (empty($this->selectedDatabase)) {
            return [];
        }

        $tables = $this->getDatabaseService()->getTablesForDatabase($this->selectedDatabase);
        return collect($tables)->pluck('name')->toArray();
    }

    public function sendMessage(): void
    {
        if (empty(trim($this->userMessage))) {
            Notification::make()
                ->title('Erreur')
                ->body('Veuillez saisir un message')
                ->danger()
                ->send();
            return;
        }

        $this->isLoading = true;

        try {
            // Construire le contexte de la base de données
            $context = $this->buildDatabaseContext();

            // Ajouter le message de l'utilisateur à l'historique
            $this->chatHistory[] = [
                'type' => 'user',
                'message' => $this->userMessage,
                'timestamp' => now()->format('H:i:s')
            ];

            // Préparer le prompt avec contexte
            $prompt = $this->buildContextualPrompt($this->userMessage, $context);

            // Appeler l'IA
            $response = $this->getOpenAIService()->generateSQLQuery($prompt);

            if ($response['success']) {
                $this->chatHistory[] = [
                    'type' => 'assistant',
                    'message' => $response['response'],
                    'timestamp' => now()->format('H:i:s'),
                    'sql' => $response['sql'] ?? null
                ];
            } else {
                $this->chatHistory[] = [
                    'type' => 'error',
                    'message' => 'Erreur: ' . $response['error'],
                    'timestamp' => now()->format('H:i:s')
                ];
            }

            $this->saveChatHistory();
            $this->userMessage = '';

        } catch (\Exception $e) {
            $this->chatHistory[] = [
                'type' => 'error',
                'message' => 'Erreur lors de la communication avec l\'IA: ' . $e->getMessage(),
                'timestamp' => now()->format('H:i:s')
            ];
        }

        $this->isLoading = false;
    }

    protected function buildDatabaseContext(): array
    {
        $context = [
            'database' => $this->selectedDatabase,
            'tables' => []
        ];

        if (!empty($this->selectedDatabase)) {
            $tables = $this->getDatabaseService()->getTablesForDatabase($this->selectedDatabase);

            foreach ($tables as $table) {
                $tableInfo = [
                    'name' => $table['name'],
                    'columns' => $table['columns'],
                    'row_count' => $table['row_count']
                ];

                // Si une table spécifique est sélectionnée, ajouter plus de détails
                if ($this->selectedTable === $table['name']) {
                    $tableInfo['foreign_keys'] = $this->getDatabaseService()->getTableForeignKeys($this->selectedDatabase, $table['name']);
                    $tableInfo['indexes'] = $this->getDatabaseService()->getTableIndexes($this->selectedDatabase, $table['name']);
                }

                $context['tables'][] = $tableInfo;
            }
        }

        return $context;
    }

    protected function buildContextualPrompt(string $userMessage, array $context): string
    {
        $prompt = "Tu es un assistant IA spécialisé en SQL et bases de données MySQL.\n\n";

        $prompt .= "CONTEXTE DE LA BASE DE DONNÉES:\n";
        $prompt .= "Base de données: {$context['database']}\n\n";

        if (!empty($context['tables'])) {
            $prompt .= "TABLES DISPONIBLES:\n";
            foreach ($context['tables'] as $table) {
                $prompt .= "- {$table['name']} ({$table['row_count']} lignes)\n";
                $prompt .= "  Colonnes: " . collect($table['columns'])->pluck('name')->implode(', ') . "\n";

                if (!empty($table['foreign_keys'])) {
                    $prompt .= "  Clés étrangères: ";
                    foreach ($table['foreign_keys'] as $fk) {
                        $prompt .= "{$fk['column']} -> {$fk['referenced_table']}.{$fk['referenced_column']}, ";
                    }
                    $prompt = rtrim($prompt, ', ') . "\n";
                }
                $prompt .= "\n";
            }
        }

        if (!empty($this->selectedTable)) {
            $prompt .= "TABLE FOCUS: {$this->selectedTable}\n\n";
        }

        $prompt .= "HISTORIQUE DE LA CONVERSATION:\n";
        $recentHistory = array_slice($this->chatHistory, -6); // Derniers 6 messages
        foreach ($recentHistory as $entry) {
            if ($entry['type'] === 'user') {
                $prompt .= "Utilisateur: {$entry['message']}\n";
            } elseif ($entry['type'] === 'assistant') {
                $prompt .= "Assistant: {$entry['message']}\n";
            }
        }

        $prompt .= "\nNOUVELLE QUESTION DE L'UTILISATEUR:\n{$userMessage}\n\n";
        $prompt .= "Réponds de manière claire et précise. Si tu génères du SQL, assure-toi qu'il soit compatible MySQL et utilise les noms de tables/colonnes exacts du contexte.";

        return $prompt;
    }

    public function clearChat(): void
    {
        $this->chatHistory = [];
        $this->saveChatHistory();

        Notification::make()
            ->title('Chat effacé')
            ->body('L\'historique de conversation a été effacé')
            ->success()
            ->send();
    }

    public function executeSQL(string $sql): void
    {
        // Rediriger vers SQL Playground avec la requête pré-remplie
        $url = route('filament.admin.pages.sql-playground') . '?query=' . urlencode($sql) . '&database=' . urlencode($this->selectedDatabase);
        $this->redirect($url);
    }

    protected function loadChatHistory(): void
    {
        $this->chatHistory = session('ai_chat_history', []);
    }

    protected function saveChatHistory(): void
    {
        session(['ai_chat_history' => $this->chatHistory]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('clear_chat')
                ->label('Effacer le chat')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->action('clearChat'),
        ];
    }
}
