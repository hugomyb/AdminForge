<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Services\DatabaseExplorerService;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;

class DatabaseExplorer extends Page implements HasForms, HasActions
{
    use InteractsWithForms;
    use InteractsWithActions;

    protected static ?string $navigationIcon = 'heroicon-o-circle-stack';
    protected static string $view = 'filament.pages.database-explorer';
    protected static ?string $title = 'Explorateur de Bases de Données';
    protected static ?string $navigationLabel = 'Bases de Données';
    protected static ?int $navigationSort = 1;

    public $selectedDatabase = null;
    public $searchTerm = '';
    protected $cachedDatabases = null;

    public function mount(): void
    {
        // Initialisation dans mount si nécessaire
    }

    protected function getDatabaseService(): DatabaseExplorerService
    {
        return app(DatabaseExplorerService::class);
    }

    public function getDatabases(): array
    {
        // Cache des bases de données pour éviter les requêtes répétées
        if ($this->cachedDatabases === null) {
            $this->cachedDatabases = $this->getDatabaseService()->getAllDatabases();
        }

        $databases = $this->cachedDatabases;

        if ($this->searchTerm) {
            $searchTerm = strtolower(trim($this->searchTerm));
            $databases = array_filter($databases, function ($database) use ($searchTerm) {
                return str_contains(strtolower($database), $searchTerm);
            });
        }

        return array_values($databases);
    }

    public function selectDatabase(string $database)
    {
        $this->selectedDatabase = $database;
        // Rediriger vers la page de détails de la base
        return redirect()->route('filament.admin.pages.database-detail', ['database' => $database]);
    }

    public function refreshDatabases(): void
    {
        try {
            $this->searchTerm = '';
            $this->selectedDatabase = null;
            $this->cachedDatabases = null; // Vider le cache pour forcer le rechargement

            Notification::make()
                ->title('Actualisé')
                ->body('La liste des bases de données a été actualisée')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Erreur')
                ->body('Impossible d\'actualiser les bases de données: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function getDatabasesWithInfo(): array
    {
        $databases = $this->getDatabases();
        $result = [];

        // Optimisation : affichage rapide sans statistiques détaillées
        foreach ($databases as $database) {
            $result[] = [
                'name' => $database,
                'total_tables' => '?', // Affiché comme "?" pour indiquer un chargement à la demande
                'total_rows' => '?',
                'size_mb' => '?'
            ];
        }

        return $result;
    }

    public function loadDatabaseInfo(string $database): array
    {
        // Méthode pour charger les infos à la demande (AJAX)
        try {
            return $this->getDatabaseService()->getDatabaseInfo($database);
        } catch (\Exception $e) {
            return [
                'name' => $database,
                'total_tables' => 0,
                'total_rows' => 0,
                'size_mb' => 0
            ];
        }
    }

    /**
     * Action pour créer une nouvelle base de données
     */
    public function createDatabaseAction(): Action
    {
        return Action::make('createDatabase')
            ->label('Créer une nouvelle base de données')
            ->icon('heroicon-o-plus-circle')
            ->color('primary')
            ->modal()
            ->modalHeading('Créer une nouvelle base de données')
            ->modalDescription('Entrez le nom de la nouvelle base de données à créer.')
            ->modalSubmitActionLabel('Créer')
            ->form([
                TextInput::make('database_name')
                    ->label('Nom de la base de données')
                    ->required()
                    ->maxLength(64)
                    ->regex('/^[a-zA-Z_][a-zA-Z0-9_]*$/')
                    ->validationMessages([
                        'regex' => 'Le nom doit commencer par une lettre ou un underscore et ne contenir que des lettres, chiffres et underscores.'
                    ])
                    ->placeholder('ex: ma_nouvelle_base')
                    ->helperText('Le nom ne peut contenir que des lettres, chiffres et underscores, et ne peut pas commencer par un chiffre.')
            ])
            ->action(function (array $data): void {
                $result = $this->getDatabaseService()->createDatabase($data['database_name']);

                if ($result['success']) {
                    // Vider le cache des bases de données pour forcer le rechargement
                    $this->cachedDatabases = null;

                    Notification::make()
                        ->title('Base de données créée')
                        ->body($result['message'])
                        ->success()
                        ->send();

                    // Rafraîchir la page pour afficher la nouvelle base
                    $this->redirect(request()->header('Referer'));
                } else {
                    Notification::make()
                        ->title('Erreur')
                        ->body($result['message'])
                        ->danger()
                        ->send();
                }
            });
    }

    /**
     * Obtenir les actions de la page
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->createDatabaseAction(),
        ];
    }
}
