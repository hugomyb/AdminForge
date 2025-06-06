<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Services\DatabaseExplorerService;
use App\Services\DataPreviewService;
use Filament\Notifications\Notification;

class TableData extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-table-cells';
    protected static string $view = 'filament.pages.table-data';
    protected static ?string $title = 'Données de la Table';
    protected static bool $shouldRegisterNavigation = false;

    public string $database;
    public string $table;
    public array $tableData = [];
    public array $columns = [];
    public array $foreignKeys = [];
    public int $currentPage = 1;
    public int $perPage = 100;
    public int $totalRows = 0;

    public function mount(): void
    {
        $this->database = request()->get('database', '');
        $this->table = request()->get('table', '');

        if (empty($this->database) || empty($this->table)) {
            abort(404, 'Base de données ou table non spécifiée');
        }

        $this->loadTableData();
        $this->loadTableColumns();
        $this->loadForeignKeys();
    }

    protected function getDatabaseService(): DatabaseExplorerService
    {
        return app(DatabaseExplorerService::class);
    }

    protected function getDataPreviewService(): DataPreviewService
    {
        return app(DataPreviewService::class);
    }

    public function getTitle(): string
    {
        return "Table : {$this->database}.{$this->table}";
    }

    public function loadTableData(): void
    {
        $offset = ($this->currentPage - 1) * $this->perPage;
        $result = $this->getDatabaseService()->getTableData($this->database, $this->table, $this->perPage, $offset);

        $this->tableData = $result['data'];
        $this->totalRows = $result['total'];
    }

    public function loadTableColumns(): void
    {
        $this->columns = $this->getDatabaseService()->getTableColumns($this->database, $this->table);
    }

    public function loadForeignKeys(): void
    {
        $this->foreignKeys = $this->getDatabaseService()->getTableForeignKeys($this->database, $this->table);
    }

    public function nextPage(): void
    {
        if ($this->currentPage * $this->perPage < $this->totalRows) {
            $this->currentPage++;
            $this->loadTableData();
        }
    }

    public function previousPage(): void
    {
        if ($this->currentPage > 1) {
            $this->currentPage--;
            $this->loadTableData();
        }
    }

    public function goToPage(int $page): void
    {
        $maxPage = ceil($this->totalRows / $this->perPage);
        if ($page >= 1 && $page <= $maxPage) {
            $this->currentPage = $page;
            $this->loadTableData();
        }
    }

    public function refreshData(): void
    {
        try {
            $this->loadTableData();
            $this->loadTableColumns();
            $this->loadForeignKeys();

            Notification::make()
                ->title('Actualisé')
                ->body('Les données de la table ont été actualisées')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Erreur')
                ->body('Impossible d\'actualiser les données: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function getTotalPages(): int
    {
        return ceil($this->totalRows / $this->perPage);
    }

    public function getStartRecord(): int
    {
        return ($this->currentPage - 1) * $this->perPage + 1;
    }

    public function getEndRecord(): int
    {
        return min($this->currentPage * $this->perPage, $this->totalRows);
    }

    public function getFormattedTableData(): array
    {
        $previewService = $this->getDataPreviewService();
        $formattedData = [];

        foreach ($this->tableData as $row) {
            $formattedRow = [];
            foreach ($row as $columnName => $value) {
                // Trouver le type de la colonne
                $columnType = $this->getColumnType($columnName);
                $formatted = $previewService->formatValue($value, $columnType);

                // Vérifier si c'est une clé étrangère
                $foreignKey = $this->getForeignKeyForColumn($columnName);
                if ($foreignKey && $value !== null) {
                    $link = $previewService->generateForeignKeyLink(
                        $value,
                        $foreignKey['referenced_table'],
                        $foreignKey['referenced_column'],
                        $this->database
                    );
                    $formatted['foreign_key_link'] = $link;
                    $formatted['foreign_key_info'] = $foreignKey;
                }

                $formattedRow[$columnName] = $formatted;
            }
            $formattedData[] = $formattedRow;
        }

        return $formattedData;
    }

    protected function getColumnType(string $columnName): string
    {
        foreach ($this->columns as $column) {
            if ($column['name'] === $columnName) {
                return $column['type'];
            }
        }
        return 'varchar';
    }

    protected function getForeignKeyForColumn(string $columnName): ?array
    {
        foreach ($this->foreignKeys as $foreignKey) {
            if ($foreignKey['column'] === $columnName) {
                return $foreignKey;
            }
        }
        return null;
    }
}
