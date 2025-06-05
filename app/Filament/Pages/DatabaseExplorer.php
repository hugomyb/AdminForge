<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Services\DatabaseExplorerService;

class DatabaseExplorer extends Page
{

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
        $this->searchTerm = '';
        $this->selectedDatabase = null;
        $this->cachedDatabases = null; // Vider le cache pour forcer le rechargement
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
}
