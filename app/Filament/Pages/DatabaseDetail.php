<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Services\DatabaseExplorerService;
use Illuminate\Contracts\View\View;
use Filament\Notifications\Notification;

class DatabaseDetail extends Page
{

    protected static ?string $navigationIcon = 'heroicon-o-table-cells';
    protected static string $view = 'filament.pages.database-detail';
    protected static ?string $title = 'Détails de la Base de Données';
    protected static bool $shouldRegisterNavigation = false;

    public string $database;
    public array $databaseInfo = [];
    public string $activeTab = 'tables';

    protected $listeners = ['refresh' => 'refreshData'];

    public function mount(): void
    {
        // Récupérer le paramètre database depuis la requête
        $this->database = request()->route('database') ?? request()->get('database') ?? '';

        if (empty($this->database)) {
            abort(404, 'Base de données non spécifiée');
        }

        $this->loadDatabaseInfo();
    }

    protected function getDatabaseService(): DatabaseExplorerService
    {
        return app(DatabaseExplorerService::class);
    }

    public function getTitle(): string
    {
        return "Base de données : {$this->database}";
    }

    public function setActiveTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function viewTableData(string $tableName): void
    {
        // Pour l'instant, on affiche juste un message
        session()->flash('message', "Affichage des données de la table: {$tableName}");
    }

    public function getTablesWithInfo(): array
    {
        return $this->databaseInfo['tables'] ?? [];
    }

    public function loadDatabaseInfo(): void
    {
        try {
            $this->databaseInfo = $this->getDatabaseService()->getDatabaseInfo($this->database);
        } catch (\Exception $e) {
            $this->databaseInfo = [];
            Notification::make()
                ->title('Erreur')
                ->body('Impossible de charger les informations de la base de données: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function refreshData(): void
    {
        $this->loadDatabaseInfo();

        Notification::make()
            ->title('Actualisé')
            ->body('Les informations de la base de données ont été actualisées')
            ->success()
            ->send();
    }
}
