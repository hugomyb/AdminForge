<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Services\DatabaseExplorerService;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;

class TableManager extends Page implements HasForms, HasActions
{
    use InteractsWithForms;
    use InteractsWithActions;

    protected static ?string $navigationIcon = 'heroicon-o-pencil-square';
    protected static string $view = 'filament.pages.table-manager';
    protected static ?string $title = 'Gestionnaire de Table';
    protected static bool $shouldRegisterNavigation = false;

    public string $database;
    public string $table;
    public array $tableData = [];
    public array $columns = [];
    public int $currentPage = 1;
    public int $perPage = 50;
    public int $totalRows = 0;
    public ?array $editingRow = null;
    public bool $showAddForm = false;
    public array $formData = [];

    public function mount(): void
    {
        $this->database = request()->get('database', '');
        $this->table = request()->get('table', '');

        if (empty($this->database) || empty($this->table)) {
            abort(404, 'Base de données ou table non spécifiée');
        }

        $this->loadTableData();
        $this->loadTableColumns();
        $this->initializeFormData();
    }

    protected function getDatabaseService(): DatabaseExplorerService
    {
        return app(DatabaseExplorerService::class);
    }

    public function getTitle(): string
    {
        return "Gestion : {$this->database}.{$this->table}";
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

    public function initializeFormData(): void
    {
        $this->formData = [];
        foreach ($this->columns as $column) {
            $this->formData[$column['name']] = '';
        }
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

    public function refreshData(): void
    {
        $this->loadTableData();
        $this->editingRow = null;
        $this->showAddForm = false;
    }

    public function showAddRowForm(): void
    {
        $this->showAddForm = true;
        $this->editingRow = null;
        $this->initializeFormData();
    }

    public function editRow(array $row): void
    {
        $this->editingRow = $row;
        $this->showAddForm = false;
        $this->formData = $row;
    }

    public function cancelEdit(): void
    {
        $this->editingRow = null;
        $this->showAddForm = false;
        $this->initializeFormData();
    }

    public function saveRow(): void
    {
        try {
            if ($this->editingRow) {
                // Mise à jour
                $this->updateRow();
            } else {
                // Insertion
                $this->insertRow();
            }
            
            $this->refreshData();
            
            Notification::make()
                ->title('Succès')
                ->body('Ligne sauvegardée avec succès')
                ->success()
                ->send();
                
        } catch (\Exception $e) {
            Notification::make()
                ->title('Erreur')
                ->body('Erreur lors de la sauvegarde: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function insertRow(): void
    {
        $columns = array_keys($this->formData);
        $values = array_values($this->formData);
        
        // Filtrer les valeurs vides pour les colonnes auto-increment
        $filteredData = [];
        foreach ($this->formData as $key => $value) {
            if ($value !== '' || !$this->isAutoIncrementColumn($key)) {
                $filteredData[$key] = $value;
            }
        }
        
        if (!empty($filteredData)) {
            $columns = array_keys($filteredData);
            $placeholders = str_repeat('?,', count($columns) - 1) . '?';
            
            $sql = "INSERT INTO `{$this->database}`.`{$this->table}` (`" . implode('`, `', $columns) . "`) VALUES ({$placeholders})";
            
            \DB::statement($sql, array_values($filteredData));
        }
    }

    protected function updateRow(): void
    {
        // Trouver la clé primaire
        $primaryKey = $this->getPrimaryKeyColumn();
        
        if (!$primaryKey) {
            throw new \Exception('Aucune clé primaire trouvée pour cette table');
        }
        
        $setParts = [];
        $values = [];
        
        foreach ($this->formData as $column => $value) {
            if ($column !== $primaryKey) {
                $setParts[] = "`{$column}` = ?";
                $values[] = $value;
            }
        }
        
        $values[] = $this->editingRow[$primaryKey];
        
        $sql = "UPDATE `{$this->database}`.`{$this->table}` SET " . implode(', ', $setParts) . " WHERE `{$primaryKey}` = ?";
        
        \DB::statement($sql, $values);
    }

    public function deleteRow(array $row): void
    {
        try {
            $primaryKey = $this->getPrimaryKeyColumn();
            
            if (!$primaryKey) {
                throw new \Exception('Aucune clé primaire trouvée pour cette table');
            }
            
            $sql = "DELETE FROM `{$this->database}`.`{$this->table}` WHERE `{$primaryKey}` = ?";
            \DB::statement($sql, [$row[$primaryKey]]);
            
            $this->refreshData();
            
            Notification::make()
                ->title('Succès')
                ->body('Ligne supprimée avec succès')
                ->success()
                ->send();
                
        } catch (\Exception $e) {
            Notification::make()
                ->title('Erreur')
                ->body('Erreur lors de la suppression: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getPrimaryKeyColumn(): ?string
    {
        foreach ($this->columns as $column) {
            if ($column['key'] === 'PRI') {
                return $column['name'];
            }
        }
        return null;
    }

    protected function isAutoIncrementColumn(string $columnName): bool
    {
        foreach ($this->columns as $column) {
            if ($column['name'] === $columnName && str_contains($column['extra'], 'auto_increment')) {
                return true;
            }
        }
        return false;
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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('add_row')
                ->label('Ajouter une ligne')
                ->icon('heroicon-o-plus')
                ->action('showAddRowForm'),
            Action::make('refresh')
                ->label('Actualiser')
                ->icon('heroicon-o-arrow-path')
                ->action('refreshData'),
        ];
    }
}
