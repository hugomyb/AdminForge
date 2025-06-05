<x-filament-panels::page>
    <div class="space-y-6">
        <!-- En-tête avec informations de la base -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ $database }}
                    </h1>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        Base de données MySQL
                    </p>
                </div>
                <x-filament::button
                    wire:click="$dispatch('refresh')"
                    icon="heroicon-o-arrow-path"
                    color="gray"
                >
                    Actualiser
                </x-filament::button>
            </div>
        </div>

        <!-- Statistiques de la base -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-blue-100 dark:bg-blue-900 rounded-lg">
                        <x-heroicon-o-table-cells class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Tables</p>
                        <p class="text-2xl font-semibold text-gray-900 dark:text-white">
                            {{ $databaseInfo['total_tables'] ?? 0 }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-green-100 dark:bg-green-900 rounded-lg">
                        <x-heroicon-o-document-text class="w-6 h-6 text-green-600 dark:text-green-400" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Lignes totales</p>
                        <p class="text-2xl font-semibold text-gray-900 dark:text-white">
                            {{ number_format($databaseInfo['total_rows'] ?? 0) }}
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
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Taille</p>
                        <p class="text-2xl font-semibold text-gray-900 dark:text-white">
                            {{ $databaseInfo['size_mb'] ?? 0 }} MB
                        </p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-orange-100 dark:bg-orange-900 rounded-lg">
                        <x-heroicon-o-clock class="w-6 h-6 text-orange-600 dark:text-orange-400" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Dernière MAJ</p>
                        <p class="text-lg font-semibold text-gray-900 dark:text-white">
                            {{ now()->format('H:i') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Onglets -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
            <div class="border-b border-gray-200 dark:border-gray-700">
                <nav class="flex space-x-8 px-6" aria-label="Tabs">
                    <button
                        wire:click="setActiveTab('tables')"
                        class="py-4 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'tables' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300' }}"
                    >
                        Tables
                    </button>
                    <button
                        wire:click="setActiveTab('queries')"
                        class="py-4 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'queries' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300' }}"
                    >
                        Requêtes SQL
                    </button>
                    <button
                        wire:click="setActiveTab('info')"
                        class="py-4 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'info' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300' }}"
                    >
                        Infos générales
                    </button>
                </nav>
            </div>

            <div class="p-6">
                @if($activeTab === 'tables')
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($this->getTablesWithInfo() as $table)
                            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:bg-gray-50 dark:hover:bg-gray-700">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="text-lg font-medium text-gray-900 dark:text-white">
                                        {{ $table['name'] }}
                                    </h4>
                                    <x-heroicon-o-table-cells class="w-5 h-5 text-gray-400" />
                                </div>
                                <div class="space-y-1 mb-4">
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        {{ number_format($table['row_count']) }} lignes
                                    </p>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        {{ count($table['columns']) }} colonnes
                                    </p>
                                </div>
                                <div class="flex space-x-2">
                                    <x-filament::button
                                        href="{{ route('filament.admin.pages.table-data') }}?database={{ urlencode($database) }}&table={{ urlencode($table['name']) }}"
                                        size="sm"
                                        color="primary"
                                    >
                                        Voir données
                                    </x-filament::button>
                                    <x-filament::button
                                        href="{{ route('filament.admin.pages.table-manager') }}?database={{ urlencode($database) }}&table={{ urlencode($table['name']) }}"
                                        size="sm"
                                        color="success"
                                    >
                                        Gérer
                                    </x-filament::button>
                                    <x-filament::button
                                        size="sm"
                                        color="gray"
                                    >
                                        Structure
                                    </x-filament::button>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if(empty($this->getTablesWithInfo()))
                        <div class="text-center py-12">
                            <x-heroicon-o-table-cells class="mx-auto h-12 w-12 text-gray-400" />
                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">Aucune table</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Cette base de données ne contient aucune table.
                            </p>
                        </div>
                    @endif
                @elseif($activeTab === 'queries')
                    <div class="text-center py-12">
                        <x-heroicon-o-code-bracket class="mx-auto h-8 w-8 text-gray-400" />
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">Requêtes SQL</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Cette fonctionnalité sera disponible dans la Phase 3
                        </p>
                    </div>
                @elseif($activeTab === 'info')
                    <div class="space-y-6">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Informations générales</h3>
                            <dl class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Nom de la base</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $database }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Nombre de tables</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $databaseInfo['total_tables'] ?? 0 }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Nombre total de lignes</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ number_format($databaseInfo['total_rows'] ?? 0) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Taille totale</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $databaseInfo['size_mb'] ?? 0 }} MB</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
