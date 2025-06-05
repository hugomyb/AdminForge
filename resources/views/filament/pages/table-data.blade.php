<x-filament-panels::page>
    <div class="space-y-6">
        <!-- En-tête -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ $database }}.{{ $table }}
                    </h1>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        {{ number_format($totalRows) }} lignes au total
                    </p>
                </div>
                <div class="flex space-x-2">
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

        <!-- Informations sur la structure -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Structure de la table</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Colonne
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Type
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Null
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Clé
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Défaut
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($columns as $column)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                    {{ $column['name'] }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                    {{ $column['type'] }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                    {{ $column['null'] ? 'Oui' : 'Non' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                    {{ $column['key'] ?: '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                    {{ $column['default'] ?: '-' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Données de la table -->
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
                    @php $formattedData = $this->getFormattedTableData(); @endphp
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                @foreach(array_keys($tableData[0]) as $columnName)
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        {{ $columnName }}
                                        @php $columnType = $this->getColumnType($columnName); @endphp
                                        @if($columnType)
                                            <div class="text-xs text-gray-400 font-normal normal-case">{{ $columnType }}</div>
                                        @endif
                                    </th>
                                @endforeach
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($formattedData as $index => $row)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    @foreach($row as $columnName => $formattedValue)
                                        <td class="px-6 py-4 text-sm text-gray-900 dark:text-white max-w-xs">
                                            <div class="flex items-center space-x-2">
                                                <div class="flex-1 min-w-0">
                                                    {!! $formattedValue['display'] !!}
                                                </div>

                                                <div class="flex items-center space-x-1">
                                                    @if(isset($formattedValue['full']) && $formattedValue['full'] !== $formattedValue['display'])
                                                        <button
                                                            onclick="showFullContent('{{ addslashes($formattedValue['full']) }}', '{{ $columnName }}')"
                                                            class="text-blue-500 hover:text-blue-700 text-xs"
                                                            title="Voir le contenu complet"
                                                        >
                                                            <x-heroicon-o-eye class="w-4 h-4" />
                                                        </button>
                                                    @endif

                                                    @if(isset($formattedValue['foreign_key_link']))
                                                        <a
                                                            href="{{ $formattedValue['foreign_key_link'] }}"
                                                            class="text-indigo-500 hover:text-indigo-700 text-xs"
                                                            title="Voir l'enregistrement lié dans {{ $formattedValue['foreign_key_info']['referenced_table'] }}"
                                                        >
                                                            <x-heroicon-o-arrow-top-right-on-square class="w-4 h-4" />
                                                        </a>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                    @endforeach
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="{{ route('filament.admin.pages.table-manager') }}?database={{ urlencode($database) }}&table={{ urlencode($table) }}&edit={{ $index }}"
                                           class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">
                                            Modifier
                                        </a>
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

    <!-- Modal pour afficher le contenu complet -->
    <div id="fullContentModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white" id="modalTitle">Contenu complet</h3>
                    <button onclick="closeFullContentModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                        <x-heroicon-o-x-mark class="w-6 h-6" />
                    </button>
                </div>
                <div class="max-h-96 overflow-auto">
                    <pre id="modalContent" class="text-sm bg-gray-100 dark:bg-gray-700 p-4 rounded whitespace-pre-wrap"></pre>
                </div>
            </div>
        </div>
    </div>

    <script>
    function showFullContent(content, columnName) {
        document.getElementById('modalTitle').textContent = 'Contenu complet - ' + columnName;
        document.getElementById('modalContent').textContent = content;
        document.getElementById('fullContentModal').classList.remove('hidden');
    }

    function closeFullContentModal() {
        document.getElementById('fullContentModal').classList.add('hidden');
    }

    // Fermer le modal en cliquant à l'extérieur
    document.getElementById('fullContentModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeFullContentModal();
        }
    });
    </script>
</x-filament-panels::page>
