<x-filament-panels::page>
    <div class="space-y-6">
        <!-- En-tête -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                        Gestion : {{ $database }}.{{ $table }}
                    </h1>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        {{ number_format($totalRows) }} lignes au total
                    </p>
                </div>
                <div class="flex space-x-2">
                    <x-filament::button
                        wire:click="showAddRowForm"
                        icon="heroicon-o-plus"
                        color="primary"
                    >
                        Ajouter une ligne
                    </x-filament::button>
                    <x-filament::button
                        wire:click="refreshData"
                        icon="heroicon-o-arrow-path"
                        color="gray"
                    >
                        Actualiser
                    </x-filament::button>
                    <x-filament::button
                        href="{{ route('filament.admin.pages.database-detail') }}?database={{ urlencode($database) }}"
                        icon="heroicon-o-arrow-left"
                        color="gray"
                    >
                        Retour
                    </x-filament::button>
                </div>
            </div>
        </div>

        <!-- Formulaire d'ajout/édition -->
        @if($showAddForm || $editingRow)
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                    {{ $editingRow ? 'Modifier la ligne' : 'Ajouter une nouvelle ligne' }}
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($columns as $column)
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                {{ $column['name'] }}
                                <span class="text-xs text-gray-500">({{ $column['type'] }})</span>
                                @if($column['key'] === 'PRI')
                                    <span class="text-xs text-blue-600">PK</span>
                                @endif
                                @if(!$column['null'])
                                    <span class="text-xs text-red-600">*</span>
                                @endif
                            </label>
                            
                            @if(str_contains($column['type'], 'text') || str_contains($column['type'], 'longtext'))
                                <textarea
                                    wire:model="formData.{{ $column['name'] }}"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    rows="3"
                                    @if(str_contains($column['extra'], 'auto_increment') && !$editingRow) disabled @endif
                                ></textarea>
                            @else
                                <input
                                    type="text"
                                    wire:model="formData.{{ $column['name'] }}"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    @if(str_contains($column['extra'], 'auto_increment') && !$editingRow) disabled @endif
                                />
                            @endif
                        </div>
                    @endforeach
                </div>
                
                <div class="flex justify-end space-x-2 mt-6">
                    <x-filament::button
                        wire:click="cancelEdit"
                        color="gray"
                    >
                        Annuler
                    </x-filament::button>
                    <x-filament::button
                        wire:click="saveRow"
                        color="primary"
                    >
                        {{ $editingRow ? 'Mettre à jour' : 'Ajouter' }}
                    </x-filament::button>
                </div>
            </div>
        @endif

        <!-- Table des données -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                        Données ({{ $this->getStartRecord() }}-{{ $this->getEndRecord() }} sur {{ number_format($totalRows) }})
                    </h3>
                    
                    <!-- Pagination -->
                    <div class="flex items-center space-x-2">
                        <x-filament::button
                            wire:click="previousPage"
                            :disabled="$currentPage <= 1"
                            size="sm"
                            color="gray"
                        >
                            Précédent
                        </x-filament::button>
                        
                        <span class="text-sm text-gray-600 dark:text-gray-400">
                            Page {{ $currentPage }} sur {{ $this->getTotalPages() }}
                        </span>
                        
                        <x-filament::button
                            wire:click="nextPage"
                            :disabled="$currentPage >= $this->getTotalPages()"
                            size="sm"
                            color="gray"
                        >
                            Suivant
                        </x-filament::button>
                    </div>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                @if(count($tableData) > 0)
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                @foreach(array_keys($tableData[0]) as $columnName)
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        {{ $columnName }}
                                    </th>
                                @endforeach
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($tableData as $row)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    @foreach($row as $value)
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                            @if(is_null($value))
                                                <span class="text-gray-400 italic">NULL</span>
                                            @elseif(is_bool($value))
                                                <span class="px-2 py-1 text-xs rounded {{ $value ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                                    {{ $value ? 'TRUE' : 'FALSE' }}
                                                </span>
                                            @elseif(strlen($value) > 30)
                                                <span title="{{ $value }}">{{ substr($value, 0, 30) }}...</span>
                                            @else
                                                {{ $value }}
                                            @endif
                                        </td>
                                    @endforeach
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex justify-end space-x-2">
                                            <x-filament::button
                                                wire:click="editRow({{ json_encode($row) }})"
                                                size="sm"
                                                color="primary"
                                            >
                                                Modifier
                                            </x-filament::button>
                                            <x-filament::button
                                                wire:click="deleteRow({{ json_encode($row) }})"
                                                wire:confirm="Êtes-vous sûr de vouloir supprimer cette ligne ?"
                                                size="sm"
                                                color="danger"
                                            >
                                                Supprimer
                                            </x-filament::button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="text-center py-12">
                        <x-heroicon-o-table-cells class="mx-auto h-12 w-12 text-gray-400" />
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">Aucune donnée</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Cette table ne contient aucune donnée.
                        </p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
