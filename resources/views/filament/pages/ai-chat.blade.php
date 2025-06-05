<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Configuration du contexte -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                Contexte de la base de données
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Base de données
                    </label>
                    <select wire:model.live="selectedDatabase" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        <option value="">Sélectionner une base</option>
                        @foreach($this->getDatabases() as $database)
                            <option value="{{ $database }}">{{ $database }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Table (optionnel)
                    </label>
                    <select wire:model.live="selectedTable" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        <option value="">Toutes les tables</option>
                        @foreach($this->getTables() as $table)
                            <option value="{{ $table }}">{{ $table }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            @if($selectedDatabase)
                <div class="mt-4 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                    <p class="text-sm text-blue-700 dark:text-blue-300">
                        <x-heroicon-o-information-circle class="w-4 h-4 inline mr-1" />
                        L'IA a accès au schéma complet de <strong>{{ $selectedDatabase }}</strong>
                        @if($selectedTable)
                            avec un focus sur la table <strong>{{ $selectedTable }}</strong>
                        @endif
                    </p>
                </div>
            @endif
        </div>

        <!-- Zone de chat -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
            <!-- En-tête du chat -->
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-medium text-gray-900 dark:text-white">
                        Chat IA Contextuel
                    </h2>
                    <div class="flex items-center space-x-2">
                        @if(config('services.openai.api_key'))
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                <x-heroicon-o-check-circle class="w-3 h-3 mr-1" />
                                IA activée
                            </span>
                        @else
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                <x-heroicon-o-x-circle class="w-3 h-3 mr-1" />
                                IA désactivée
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Historique du chat -->
            <div class="h-96 overflow-y-auto p-6 space-y-4" id="chat-container">
                @if(empty($chatHistory))
                    <div class="text-center py-8 h-full flex justify-center items-center flex-col">
                        <x-heroicon-o-chat-bubble-left-right class="mx-auto h-10 w-10 text-gray-400" />
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">Commencez une conversation</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Posez des questions sur votre base de données ou demandez de l'aide pour écrire des requêtes SQL.
                        </p>
                    </div>
                @else
                    @foreach($chatHistory as $entry)
                        <div class="flex {{ $entry['type'] === 'user' ? 'justify-end' : 'justify-start' }}">
                            <div class="max-w-xs lg:max-w-md px-4 py-2 rounded-lg {{
                                $entry['type'] === 'user'
                                    ? 'bg-blue-500 text-white'
                                    : ($entry['type'] === 'error'
                                        ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                        : 'bg-gray-100 text-gray-900 dark:bg-gray-700 dark:text-white')
                            }}">
                                <div class="text-sm">
                                    {!! nl2br(e($entry['message'])) !!}
                                </div>

                                @if(isset($entry['sql']) && $entry['sql'])
                                    <div class="mt-2 p-2 bg-gray-800 rounded text-green-400 text-xs font-mono">
                                        {{ $entry['sql'] }}
                                    </div>
                                    <button
                                        wire:click="executeSQL('{{ addslashes($entry['sql']) }}')"
                                        class="mt-2 text-xs text-blue-200 hover:text-blue-100 underline"
                                    >
                                        Exécuter dans SQL Playground
                                    </button>
                                @endif

                                <div class="text-xs opacity-75 mt-1">
                                    {{ $entry['timestamp'] }}
                                </div>
                            </div>
                        </div>
                    @endforeach
                @endif

                @if($isLoading)
                    <div class="flex justify-start">
                        <div class="max-w-xs lg:max-w-md px-4 py-2 rounded-lg bg-gray-100 dark:bg-gray-700">
                            <div class="flex items-center space-x-2">
                                <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-500"></div>
                                <span class="text-sm text-gray-600 dark:text-gray-300">L'IA réfléchit...</span>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Zone de saisie -->
            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                @if(config('services.openai.api_key'))
                    <div class="flex space-x-4">
                        <div class="flex-1">
                            <textarea
                                wire:model="userMessage"
                                wire:keydown.ctrl.enter="sendMessage"
                                placeholder="Posez votre question sur la base de données... (Ctrl+Entrée pour envoyer)"
                                rows="2"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white resize-none"
                            ></textarea>
                        </div>
                        <div class="flex flex-col space-y-2">
                            <x-filament::button
                                wire:click="sendMessage"
                                :disabled="$isLoading || empty(trim($userMessage))"
                                icon="heroicon-o-paper-airplane"
                                size="sm"
                            >
                                Envoyer
                            </x-filament::button>
                        </div>
                    </div>

                    <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        Exemples: "Montre-moi les utilisateurs actifs", "Comment joindre les tables users et orders?", "Crée une requête pour..."
                    </div>
                @else
                    <div class="text-center py-4">
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">
                            L'IA n'est pas configurée. Ajoutez votre clé API OpenAI dans les paramètres.
                        </p>
                        <x-filament::button
                            href="{{ route('filament.admin.pages.settings') }}"
                            icon="heroicon-o-cog-6-tooth"
                            size="sm"
                        >
                            Configurer l'IA
                        </x-filament::button>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <script>
        // Auto-scroll vers le bas du chat
        function scrollChatToBottom() {
            const container = document.getElementById('chat-container');
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        }

        // Scroll automatique quand le chat se met à jour
        document.addEventListener('livewire:updated', function () {
            setTimeout(scrollChatToBottom, 100);
        });

        // Scroll initial
        document.addEventListener('DOMContentLoaded', function () {
            scrollChatToBottom();
        });

        // Raccourci clavier pour envoyer
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'Enter') {
                const textarea = document.querySelector('textarea[wire\\:model="userMessage"]');
                if (textarea && document.activeElement === textarea) {
                    @this.sendMessage();
                }
            }
        });
    </script>
</x-filament-panels::page>
