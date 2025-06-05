<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Barre de recherche -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex items-center space-x-4">
                <div class="flex-1">
                    <x-filament::input.wrapper>
                        <x-filament::input
                            type="text"
                            wire:model.live="searchTerm"
                            placeholder="Rechercher une base de données..."
                            class="w-full"
                        />
                    </x-filament::input.wrapper>
                </div>
                <x-filament::button
                    wire:click="refreshDatabases"
                    icon="heroicon-o-arrow-path"
                    color="gray"
                >
                    Actualiser
                </x-filament::button>
            </div>
        </div>

        <!-- Statistiques rapides -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-blue-100 dark:bg-blue-900 rounded-lg">
                        <x-heroicon-o-circle-stack class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Bases de données</p>
                        <p class="text-2xl font-semibold text-gray-900 dark:text-white">
                            {{ count($this->getDatabases()) }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-green-100 dark:bg-green-900 rounded-lg">
                        <x-heroicon-o-table-cells class="w-6 h-6 text-green-600 dark:text-green-400" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Tables totales</p>
                        <p class="text-2xl font-semibold text-gray-900 dark:text-white">
                            <span class="text-gray-400">Calculé à la demande</span>
                        </p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-purple-100 dark:bg-purple-900 rounded-lg">
                        <x-heroicon-o-server class="w-6 h-6 text-purple-600 dark:text-purple-400" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Serveur</p>
                        <p class="text-lg font-semibold text-gray-900 dark:text-white">
                            MySQL {{ DB::select('SELECT VERSION() as version')[0]->version ?? 'N/A' }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Liste des bases de données -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Bases de données disponibles</h3>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($this->getDatabasesWithInfo() as $database)
                        <a href="{{ route('filament.admin.pages.database-detail') }}?database={{ urlencode($database['name']) }}"
                           class="block border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:bg-gray-50 dark:hover:bg-gray-700 hover:border-blue-300 dark:hover:border-blue-600 transition-colors">
                            <div class="flex items-center justify-between">
                                <h4 class="text-lg font-medium text-gray-900 dark:text-white">
                                    {{ $database['name'] }}
                                </h4>
                                <x-heroicon-o-chevron-right class="w-5 h-5 text-gray-400" />
                            </div>
                            <div class="mt-2 space-y-1">
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    @if($database['total_tables'] === '?')
                                        <span class="text-gray-400">Cliquez pour voir les détails</span>
                                    @else
                                        {{ $database['total_tables'] }} tables
                                    @endif
                                </p>
                                @if($database['total_tables'] !== '?')
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        {{ is_numeric($database['total_rows']) ? number_format($database['total_rows']) : $database['total_rows'] }} lignes
                                    </p>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        {{ $database['size_mb'] }} MB
                                    </p>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>

                @if(empty($this->getDatabasesWithInfo()))
                    <div class="text-center py-12">
                        <x-heroicon-o-circle-stack class="mx-auto h-12 w-12 text-gray-400" />
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">Aucune base de données</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Aucune base de données trouvée ou erreur de connexion.
                        </p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
