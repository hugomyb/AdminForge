<div class="fixed bottom-6 right-6 z-50">
    <!-- Bulle de chat -->
    @if($isOpen)
        <div class="ai-chat-bubble mb-4 w-96 bg-white dark:bg-gray-800 rounded-lg shadow-2xl border border-gray-200 dark:border-gray-700 overflow-hidden transition-all duration-300 ease-in-out {{ $isMinimized ? 'h-14' : 'h-96' }}">
            <!-- En-tête du chat -->
            <div class="flex items-center justify-between px-4 py-3 bg-primary-500 text-white">
                <div class="flex items-center space-x-2">
                    <x-heroicon-o-chat-bubble-left-right class="w-5 h-5" />
                    <h3 class="font-medium text-sm">Chat IA</h3>
                    @php
                        $aiEnabled = \App\Models\Setting::get('ai_enabled', false);
                        $apiKey = \App\Models\Setting::get('openai_api_key', '');
                    @endphp
                    @if($aiEnabled && !empty($apiKey))
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                            <x-heroicon-o-check-circle class="w-3 h-3 mr-1" />
                            Actif
                        </span>
                    @else
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                            <x-heroicon-o-x-circle class="w-3 h-3 mr-1" />
                            Inactif
                        </span>
                    @endif
                </div>
                <div class="flex items-center space-x-1">
                    <button wire:click="minimizeChat" class="text-white hover:text-gray-200 transition-colors">
                        @if($isMinimized)
                            <x-heroicon-o-chevron-up class="w-4 h-4" />
                        @else
                            <x-heroicon-o-chevron-down class="w-4 h-4" />
                        @endif
                    </button>
                    <button wire:click="closeChat" class="text-white hover:text-gray-200 transition-colors">
                        <x-heroicon-o-x-mark class="w-4 h-4" />
                    </button>
                </div>
            </div>

            @if(!$isMinimized)
                <!-- Configuration du contexte (compacte) -->
                <div class="px-4 py-2 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                    <div class="grid grid-cols-2 gap-2">
                        <select wire:model.live="selectedDatabase" class="text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            <option value="">Base...</option>
                            @foreach($this->getDatabases() as $database)
                                <option value="{{ $database }}">{{ Str::limit($database, 15) }}</option>
                            @endforeach
                        </select>
                        
                        <select wire:model.live="selectedTable" class="text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            <option value="">Table...</option>
                            @foreach($this->getTables() as $table)
                                <option value="{{ $table }}">{{ Str::limit($table, 15) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- Zone de chat -->
                <div class="chat-container h-64 overflow-y-auto p-3 space-y-2" id="chat-bubble-container">
                    @if(empty($chatHistory))
                        <div class="text-center py-4">
                            <x-heroicon-o-chat-bubble-left-right class="mx-auto h-8 w-8 text-gray-400" />
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                Posez une question sur votre base de données
                            </p>
                        </div>
                    @else
                        @foreach($chatHistory as $entry)
                            <div class="flex {{ $entry['type'] === 'user' ? 'justify-end' : 'justify-start' }}">
                                <div class="chat-message max-w-xs px-3 py-2 rounded-lg text-xs {{
                                    $entry['type'] === 'user'
                                        ? 'bg-primary-500 text-white'
                                        : ($entry['type'] === 'error'
                                            ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                            : 'bg-gray-100 text-gray-900 dark:bg-gray-700 dark:text-white')
                                }}">
                                    <div class="whitespace-pre-wrap">{{ $entry['message'] }}</div>
                                    
                                    @if(isset($entry['sql']) && $entry['sql'])
                                        <div class="mt-1 p-1 bg-gray-800 rounded text-green-400 text-xs font-mono">
                                            {{ Str::limit($entry['sql'], 50) }}
                                        </div>
                                        <button 
                                            wire:click="executeSQL('{{ addslashes($entry['sql']) }}')"
                                            class="mt-1 text-xs text-blue-200 hover:text-blue-100 underline"
                                        >
                                            Exécuter
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
                            <div class="max-w-xs px-3 py-2 rounded-lg bg-gray-100 dark:bg-gray-700">
                                <div class="flex items-center space-x-2">
                                    <div class="animate-spin rounded-full h-3 w-3 border-b-2 border-primary-500"></div>
                                    <span class="text-xs text-gray-600 dark:text-gray-300">IA réfléchit...</span>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Zone de saisie -->
                <div class="px-3 py-2 border-t border-gray-200 dark:border-gray-600">
                    @if($aiEnabled && !empty($apiKey))
                        <div class="flex space-x-2">
                            <textarea
                                wire:model="userMessage"
                                wire:keydown.ctrl.enter="sendMessage"
                                placeholder="Votre question..."
                                rows="2"
                                class="flex-1 text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white resize-none"
                            ></textarea>
                            <button
                                wire:click="sendMessage"
                                :disabled="$isLoading || empty(trim($userMessage))"
                                class="px-3 py-1 bg-primary-500 text-white rounded text-xs hover:bg-primary-600 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <x-heroicon-o-paper-airplane class="w-3 h-3" />
                            </button>
                        </div>
                        
                        <div class="flex justify-between items-center mt-1">
                            <div class="text-xs text-gray-500">Ctrl+Entrée pour envoyer</div>
                            @if(count($chatHistory) > 0)
                                <button wire:click="clearChat" class="text-xs text-red-500 hover:text-red-700">
                                    Effacer
                                </button>
                            @endif
                        </div>
                    @else
                        <div class="text-center py-2">
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">
                                IA non configurée
                            </p>
                            <a
                                href="/settings"
                                class="inline-flex items-center px-2 py-1 bg-primary-500 text-white rounded text-xs hover:bg-primary-600"
                            >
                                <x-heroicon-o-cog-6-tooth class="w-3 h-3 mr-1" />
                                Configurer
                            </a>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    @endif

    <!-- Bouton flottant -->
    <button
        wire:click="toggleChat"
        class="chat-float-button w-14 h-14 bg-primary-500 hover:bg-primary-600 text-white rounded-full shadow-lg hover:shadow-xl transition-all duration-200 flex items-center justify-center group"
    >
        @if($isOpen)
            <x-heroicon-o-x-mark class="w-6 h-6" />
        @else
            <x-heroicon-o-chat-bubble-left-right class="w-6 h-6 group-hover:scale-110 transition-transform" />
            @if(count($chatHistory) > 0)
                <span class="absolute -top-1 -right-1 w-4 h-4 bg-red-500 text-white rounded-full text-xs flex items-center justify-center">
                    {{ min(count($chatHistory), 9) }}
                </span>
            @endif
        @endif
    </button>
</div>

<script>
    // Auto-scroll vers le bas du chat
    function scrollChatBubbleToBottom() {
        const container = document.getElementById('chat-bubble-container');
        if (container) {
            container.scrollTop = container.scrollHeight;
        }
    }

    // Scroll automatique quand le chat se met à jour
    document.addEventListener('livewire:updated', function () {
        setTimeout(scrollChatBubbleToBottom, 100);
    });

    // Scroll initial
    document.addEventListener('DOMContentLoaded', function () {
        scrollChatBubbleToBottom();
    });
</script>
