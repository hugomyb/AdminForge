<x-filament-panels::page>
    @vite(['resources/css/app.css'])

    <div class="space-y-6">

        <!-- Statistiques rapides -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-circle-stack class="w-5 h-5 text-primary-600"/>
                        Bases de données
                    </div>
                </x-slot>

                <x-slot name="headerEnd">
                    <div class="text-sm text-gray-500 mt-1">
                        <x-filament::badge color="success">
                            Connexion active
                        </x-filament::badge>
                    </div>
                </x-slot>

                <div class="text-3xl font-bold text-gray-900">
                    {{ count($this->getDatabases()) }}
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-server class="w-5 h-5 text-primary-600"/>
                        Serveur
                    </div>
                </x-slot>

                <x-slot name="headerEnd">
                    <div class="text-sm text-gray-500 mt-1">
                        <x-filament::badge color="success">
                            En ligne
                        </x-filament::badge>
                    </div>
                </x-slot>

                <div class="text-lg font-bold text-gray-900">
                    MySQL {{ DB::select('SELECT VERSION() as version')[0]->version ?? 'N/A' }}
                </div>
            </x-filament::section>
        </div>

        <!-- En-tête avec barre de recherche -->
        <x-filament::section>
            <x-slot name="heading">
                Explorateur de bases de données
            </x-slot>

            <x-slot name="headerEnd">
                <x-filament::badge color="primary">
                    {{ count($this->getDatabasesWithInfo()) }}
                    base{{ count($this->getDatabasesWithInfo()) > 1 ? 's' : '' }}
                </x-filament::badge>
            </x-slot>

            <x-slot name="description">
                Gérez et explorez vos bases de données MySQL en toute simplicité
            </x-slot>

            <div class="flex items-center space-x-4">
                <div class="flex-1">
                    <x-filament::input.wrapper>
                        <x-filament::input
                            type="text"
                            wire:model.live="searchTerm"
                            placeholder="Rechercher une base de données..."
                        />
                    </x-filament::input.wrapper>
                </div>
            </div>

            @if(!empty($this->getDatabasesWithInfo()))
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mt-6">
                    @foreach($this->getDatabasesWithInfo() as $database)
                        <a href="{{ route('filament.admin.pages.database-detail') }}?database={{ urlencode($database['name']) }}"
                           class="block bg-white border border-gray-200 rounded-xl p-6 shadow-sm hover:shadow-md hover:border-primary-300 transition-all duration-200 group cursor-pointer">
                            <!-- En-tête de la carte -->
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="p-2 bg-primary-50 rounded-lg group-hover:bg-primary-100 transition-colors">
                                        <x-heroicon-o-circle-stack class="w-6 h-6 text-primary-600" />
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-gray-900 text-lg">
                                            {{ $database['name'] }}
                                        </h4>
                                        <p class="text-xs text-gray-500 uppercase tracking-wide font-medium">
                                            Base de données
                                        </p>
                                    </div>
                                </div>
                                <x-heroicon-o-chevron-right class="w-5 h-5 text-gray-400 group-hover:text-primary-500 transition-colors" />
                            </div>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="text-center py-12 mt-6">
                    <x-heroicon-o-circle-stack class="mx-auto h-12 w-12 text-gray-400 mb-4"/>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Aucune base de données trouvée</h3>
                    <p class="text-gray-500 mb-6">
                        Aucune base de données n'a été trouvée ou il y a eu une erreur de connexion.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-3 justify-center">
                        <x-filament::button
                            wire:click="mountAction('createDatabase')"
                            icon="heroicon-o-plus-circle"
                            color="primary"
                        >
                            Créer une nouvelle base de données
                        </x-filament::button>
                        <x-filament::button
                            wire:click="refreshDatabases"
                            wire:loading.attr="disabled"
                            wire:target="refreshDatabases"
                            icon="heroicon-o-arrow-path"
                            color="gray"
                        >
                            <span wire:loading.remove wire:target="refreshDatabases">Réessayer</span>
                            <span wire:loading wire:target="refreshDatabases">Actualisation...</span>
                        </x-filament::button>
                    </div>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
