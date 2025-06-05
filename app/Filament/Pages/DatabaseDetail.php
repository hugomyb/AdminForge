<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Services\DatabaseExplorerService;
use Illuminate\Contracts\View\View;

class DatabaseDetail extends Page
{

    protected static ?string $navigationIcon = 'heroicon-o-table-cells';
    protected static string $view = 'filament.pages.database-detail';
    protected static ?string $title = 'Détails de la Base de Données';
    protected static bool $shouldRegisterNavigation = false;

    public string $database;
    public array $databaseInfo = [];
    public string $activeTab = 'tables';

    public function mount(): void
    {
        // Récupérer le paramètre database depuis la requête
        $this->database = request()->route('database') ?? request()->get('database') ?? '';

        if (empty($this->database)) {
            abort(404, 'Base de données non spécifiée');
        }

        $this->databaseInfo = $this->getDatabaseService()->getDatabaseInfo($this->database);
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
}
