<x-filament-panels::page>
    @vite(['resources/css/app.css'])

    <div class="space-y-6">
        <!-- En-tête avec informations de la base -->
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-3">
                    <x-heroicon-o-circle-stack class="w-6 h-6 text-primary-600" />
                    {{ $database }}
                </div>
            </x-slot>

            <x-slot name="description">
                <div class="flex items-center gap-4">
                    <span>Base de données MySQL</span>
                    <x-filament::badge color="success" size="sm">
                        Connectée
                    </x-filament::badge>
                </div>
            </x-slot>

            <x-slot name="headerEnd">
                <div class="flex gap-2">
                    <x-filament::button
                        href="{{ route('filament.admin.pages.database-explorer') }}"
                        icon="heroicon-o-arrow-left"
                        color="gray"
                        size="sm"
                    >
                        Retour
                    </x-filament::button>
                    <x-filament::button
                        wire:click="$dispatch('refresh')"
                        icon="heroicon-o-arrow-path"
                        color="primary"
                        size="sm"
                    >
                        Actualiser
                    </x-filament::button>
                </div>
            </x-slot>
        </x-filament::section>

        <!-- Statistiques de la base -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-table-cells class="w-5 h-5 text-primary-600" />
                        Tables
                    </div>
                </x-slot>

                <div class="text-center">
                    <div class="text-3xl font-bold text-gray-900">
                        {{ $databaseInfo['total_tables'] ?? 0 }}
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-document-text class="w-5 h-5 text-primary-600" />
                        Lignes
                    </div>
                </x-slot>

                <div class="text-center">
                    <div class="text-3xl font-bold text-gray-900">
                        {{ number_format($databaseInfo['total_rows'] ?? 0) }}
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-server class="w-5 h-5 text-primary-600" />
                        Taille
                    </div>
                </x-slot>

                <div class="text-center">
                    <div class="text-3xl font-bold text-gray-900">
                        {{ $databaseInfo['size_mb'] ?? 0 }} MB
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-clock class="w-5 h-5 text-primary-600" />
                        Dernière MAJ
                    </div>
                </x-slot>

                <div class="text-center">
                    <div class="text-2xl font-bold text-gray-900">
                        {{ now()->format('H:i') }}
                    </div>
                </div>
            </x-filament::section>
        </div>

        <!-- Onglets -->
        <x-filament::tabs>
            <x-filament::tabs.item
                :active="$activeTab === 'tables'"
                wire:click="setActiveTab('tables')"
                icon="heroicon-o-table-cells"
            >
                Tables
            </x-filament::tabs.item>

            <x-filament::tabs.item
                :active="$activeTab === 'queries'"
                wire:click="setActiveTab('queries')"
                icon="heroicon-o-code-bracket"
            >
                Requêtes SQL
            </x-filament::tabs.item>

            <x-filament::tabs.item
                :active="$activeTab === 'info'"
                wire:click="setActiveTab('info')"
                icon="heroicon-o-information-circle"
            >
                Infos générales
            </x-filament::tabs.item>
        </x-filament::tabs>

        <x-filament::section>

            @if($activeTab === 'tables')
                @if(!empty($this->getTablesWithInfo()))
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($this->getTablesWithInfo() as $table)
                            <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors">
                                <div class="flex items-center gap-3 mb-3">
                                    <x-heroicon-o-table-cells class="w-5 h-5 text-primary-600" />
                                    <h4 class="font-semibold text-gray-900">
                                        {{ $table['name'] }}
                                    </h4>
                                </div>

                                <div class="space-y-2 mb-4">
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-500">Lignes:</span>
                                        <span class="font-medium">{{ number_format($table['row_count']) }}</span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-500">Colonnes:</span>
                                        <span class="font-medium">{{ count($table['columns']) }}</span>
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <x-filament::button
                                        href="{{ route('filament.admin.pages.table-data') }}?database={{ urlencode($database) }}&table={{ urlencode($table['name']) }}"
                                        size="sm"
                                        class="w-full"
                                        icon="heroicon-o-eye"
                                    >
                                        Voir données
                                    </x-filament::button>
                                    <div class="grid grid-cols-2 gap-2">
                                        <x-filament::button
                                            href="{{ route('filament.admin.pages.table-manager') }}?database={{ urlencode($database) }}&table={{ urlencode($table['name']) }}"
                                            size="sm"
                                            color="success"
                                            icon="heroicon-o-pencil"
                                        >
                                            Gérer
                                        </x-filament::button>
                                        <x-filament::button
                                            size="sm"
                                            color="gray"
                                            icon="heroicon-o-cog-6-tooth"
                                        >
                                            Structure
                                        </x-filament::button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else

                    <div class="text-center py-12">
                        <x-heroicon-o-table-cells class="mx-auto h-12 w-12 text-gray-400 mb-4" />
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Aucune table trouvée</h3>
                        <p class="text-gray-500">
                            Cette base de données ne contient aucune table.
                        </p>
                    </div>
                @endif
            @elseif($activeTab === 'queries')
                <div class="text-center py-12">
                    <x-heroicon-o-code-bracket class="mx-auto h-12 w-12 text-gray-400 mb-4" />
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Requêtes SQL</h3>
                    <p class="text-gray-500 mb-4">
                        Cette fonctionnalité sera disponible dans la Phase 3.
                    </p>
                    <x-filament::button
                        href="{{ route('filament.admin.pages.sql-playground') }}"
                        icon="heroicon-o-play"
                    >
                        Utiliser SQL Playground
                    </x-filament::button>
                </div>
            @elseif($activeTab === 'info')
                <div class="space-y-6">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <dt class="text-sm font-medium text-gray-500">Nom de la base</dt>
                            <dd class="text-lg font-semibold text-gray-900">{{ $database }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-sm font-medium text-gray-500">Nombre de tables</dt>
                            <dd class="text-lg font-semibold text-gray-900">{{ $databaseInfo['total_tables'] ?? 0 }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-sm font-medium text-gray-500">Nombre total de lignes</dt>
                            <dd class="text-lg font-semibold text-gray-900">{{ number_format($databaseInfo['total_rows'] ?? 0) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-sm font-medium text-gray-500">Taille totale</dt>
                            <dd class="text-lg font-semibold text-gray-900">{{ $databaseInfo['size_mb'] ?? 0 }} MB</dd>
                        </div>
                    </div>

                    <div class="border-t pt-6">
                        <h4 class="text-lg font-medium text-gray-900 mb-4">Actions rapides</h4>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <x-filament::button
                                href="{{ route('filament.admin.pages.sql-playground') }}"
                                icon="heroicon-o-code-bracket"
                                class="justify-center"
                            >
                                SQL Playground
                            </x-filament::button>
                            <x-filament::button
                                wire:click="$dispatch('refresh')"
                                icon="heroicon-o-arrow-path"
                                color="gray"
                                class="justify-center"
                            >
                                Actualiser
                            </x-filament::button>
                            <x-filament::button
                                href="{{ route('filament.admin.pages.database-explorer') }}"
                                icon="heroicon-o-arrow-left"
                                color="gray"
                                class="justify-center"
                            >
                                Retour
                            </x-filament::button>
                        </div>
                    </div>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
