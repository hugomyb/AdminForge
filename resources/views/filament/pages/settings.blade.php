<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Formulaire de configuration -->
        <div class="bg-transparent rounded-lg shadow">
            {{ $this->form }}

            <div class="px-6 py-4">
                <div class="flex justify-between items-center">
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        Les paramètres sont sauvegardés dans <code>config/adminforge.php</code>
                    </div>
                    <div class="flex space-x-2">
                        @if(($data['openai_enabled'] ?? false) && !empty($data['openai_api_key'] ?? ''))
                            <x-filament::button
                                wire:click="testOpenAiConnection"
                                icon="heroicon-o-signal"
                                color="gray"
                            >
                                Tester OpenAI
                            </x-filament::button>
                        @endif
                        <x-filament::button
                            wire:click="save"
                            icon="heroicon-o-check"
                            color="primary"
                        >
                            Sauvegarder
                        </x-filament::button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Informations sur les fonctionnalités IA -->
        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-6">
            <div class="flex items-start">
                <x-heroicon-o-information-circle class="w-6 h-6 text-blue-600 dark:text-blue-400 mr-3 mt-0.5" />
                <div>
                    <h3 class="text-lg font-medium text-blue-800 dark:text-blue-200 mb-2">
                        Fonctionnalités IA disponibles
                    </h3>
                    <ul class="text-sm text-blue-700 dark:text-blue-300 space-y-1">
                        <li>• Génération automatique de requêtes SQL à partir de descriptions en langage naturel</li>
                        <li>• Amélioration et optimisation de requêtes existantes</li>
                        <li>• Explication détaillée des requêtes complexes</li>
                        <li>• Suggestions d'index et d'optimisations de performance</li>
                        <li>• Chat contextuel avec la base de données sélectionnée</li>
                    </ul>
                    <p class="mt-3 text-sm text-blue-600 dark:text-blue-400">
                        <strong>Note :</strong> Une clé API OpenAI valide est requise pour utiliser ces fonctionnalités.
                        Vous pouvez obtenir une clé sur <a href="https://platform.openai.com/api-keys" target="_blank" class="underline">platform.openai.com</a>.
                    </p>
                </div>
            </div>
        </div>

        <!-- État actuel -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">État des fonctionnalités</h3>
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Fonctionnalités IA</span>
                        @if(($data['openai_enabled'] ?? false) && !empty($data['openai_api_key'] ?? ''))
                            <span class="px-2 py-1 text-xs bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 rounded">
                                Activées
                            </span>
                        @else
                            <span class="px-2 py-1 text-xs bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200 rounded">
                                Désactivées
                            </span>
                        @endif
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Historique des requêtes</span>
                        @if($data['enable_query_history'] ?? true)
                            <span class="px-2 py-1 text-xs bg-green-100 text-success rounded">
                                Activé
                            </span>
                        @else
                            <span class="px-2 py-1 text-xs bg-gray-100 text-danger rounded">
                                Désactivé
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Informations système</h3>
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Version PHP</span>
                        <span class="text-sm font-medium text-gray-900 dark:text-white">{{ PHP_VERSION }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Version Laravel</span>
                        <span class="text-sm font-medium text-gray-900 dark:text-white">{{ app()->version() }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Base de données</span>
                        <span class="text-sm font-medium text-gray-900 dark:text-white">
                            {{ DB::connection()->getDriverName() }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
