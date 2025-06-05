<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Header Section -->
        <x-filament::section id="header-sql">
            <x-slot name="heading">
                <div class="flex items-center gap-3">
                    <x-heroicon-o-code-bracket class="w-6 h-6 text-primary-600"/>
                    <span>SQL Playground</span>
                </div>
            </x-slot>

            <x-slot name="description">
                Exécutez et testez vos requêtes SQL en temps réel
            </x-slot>

            <x-slot name="headerEnd">
                <div class="flex items-center gap-4">
                    <!-- Sélection de base de données -->
                    <div class="flex gap-2">
                        <x-filament::input.wrapper>
                            <x-filament::input.select
                                wire:model.live="selectedDatabase"
                                class="{{ empty($selectedDatabase) ? 'border-warning-300 focus:border-warning-500 focus:ring-warning-500' : '' }}"
                            >
                                <option value="">⚠️ Sélectionner une base de données...</option>
                                @foreach($this->getDatabases() as $database)
                                    <option value="{{ $database }}">{{ $database }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>

                        @if($selectedDatabase)
                            <x-filament::badge color="success" size="sm">
                                <div class="flex items-center justify-center gap-1">
                                    <x-heroicon-s-check-circle class="w-3 h-3 mr-1"/>
                                    Connecté à {{ $selectedDatabase }}
                                </div>
                            </x-filament::badge>
                        @else
                            <x-filament::badge color="warning" size="sm">
                                <div class="flex items-center justify-center gap-1">
                                    <x-heroicon-s-exclamation-triangle class="w-3 h-3 mr-1"/>
                                    Base de données requise
                                </div>
                            </x-filament::badge>
                        @endif
                    </div>
                </div>
            </x-slot>
        </x-filament::section>

        <!-- Layout principal avec grille -->
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            <!-- Colonne principale - Éditeur et résultats -->
            <div class="lg:col-span-3 space-y-6">

                <!-- Panel IA intégré -->
                @if($showAiPanel)
                    <x-filament::section
                        icon="heroicon-o-sparkles"
                        icon-color="purple"
                    >
                        <x-slot name="heading">
                            Assistant IA
                        </x-slot>

                        <x-slot name="description">
                            Décrivez ce que vous voulez faire et l'IA générera la requête SQL correspondante
                        </x-slot>

                        <x-slot name="headerEnd">
                            <x-filament::button
                                wire:click="toggleAiPanel"
                                icon="heroicon-o-x-mark"
                                color="gray"
                                size="sm"
                            >
                                Fermer
                            </x-filament::button>
                        </x-slot>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Décrivez votre requête en langage naturel
                                </label>
                                <textarea
                                    wire:model="aiPrompt"
                                    rows="3"
                                    class="w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                                    placeholder="Ex: Afficher tous les utilisateurs créés cette semaine avec leur nombre de commandes"
                                ></textarea>
                            </div>

                            <div class="flex justify-end">
                                <x-filament::button
                                    wire:click="generateQueryFromAi"
                                    icon="heroicon-o-sparkles"
                                    color="purple"
                                >
                                    Générer la requête
                                </x-filament::button>
                            </div>
                        </div>
                    </x-filament::section>
                @endif

                <!-- Éditeur SQL -->
                <x-filament::section>
                    <x-slot name="heading">
                        <div class="flex items-center gap-3">
                            <span>Éditeur SQL</span>
                            @if($selectedDatabase)
                                <x-filament::badge color="primary" size="sm">
                                    <div class="flex items-center justify-center gap-1">
                                        <x-heroicon-s-circle-stack class="w-3 h-3 mr-1"/>
                                        {{ $selectedDatabase }}
                                    </div>
                                </x-filament::badge>
                            @endif
                        </div>
                    </x-slot>

                    <x-slot name="description">
                        @if(empty($selectedDatabase))
                            ⚠️ Sélectionnez d'abord une base de données ci-dessus pour pouvoir exécuter vos requêtes SQL
                        @else
                            Saisissez votre requête SQL et exécutez-la avec Ctrl+Enter
                        @endif
                    </x-slot>

                    <x-slot name="headerEnd">
                        <div class="flex items-center gap-2">
                            <!-- Actions IA -->
                            @if($this->isAiEnabled() && !empty(trim($sqlQuery)))
                                <x-filament::button
                                    wire:click="openExplanationModal"
                                    icon="heroicon-o-question-mark-circle"
                                    color="gray"
                                    size="sm"
                                >
                                    Expliquer
                                </x-filament::button>
                            @endif

                            <!-- Actions principales -->
                            <x-filament::button
                                wire:click="executeQuery"
                                icon="heroicon-o-play"
                                :color="empty($selectedDatabase) ? 'gray' : 'primary'"
                                size="sm"
                                :disabled="empty($selectedDatabase)"
                                :tooltip="empty($selectedDatabase) ? 'Sélectionnez une base de données pour exécuter la requête' : null"
                            >
                                Exécuter
                            </x-filament::button>
                            <x-filament::button
                                wire:click="clearQuery"
                                icon="heroicon-o-trash"
                                color="gray"
                                size="sm"
                            >
                                Effacer
                            </x-filament::button>
                        </div>
                    </x-slot>

                    <div class="space-y-4">
                        <div wire:ignore>
                            <!-- Textarea caché pour Livewire -->
                            <textarea
                                id="sql-query-textarea"
                                wire:model="sqlQuery"
                                style="display: none;"
                            ></textarea>

                            <!-- Container pour CodeMirror -->
                            <div
                                id="sql-editor-container"
                                class="sql-editor-wrapper"
                                data-readonly="{{ empty($selectedDatabase) ? 'true' : 'false' }}"
                                data-placeholder="{{ empty($selectedDatabase) ? '-- Sélectionnez d\'abord une base de données pour commencer...' : '-- Saisissez votre requête SQL ici...' }}"
                                data-current-query="{{ $sqlQuery }}"
                            ></div>
                        </div>

                        <div class="flex justify-between items-center text-xs text-gray-500">
                            <div class="flex items-center space-x-4">
                                <span>{{ strlen($sqlQuery) }} caractères</span>
                                <div class="flex items-center space-x-1">
                                    <x-filament::badge color="gray" size="xs">Ctrl</x-filament::badge>
                                    <span>+</span>
                                    <x-filament::badge color="gray" size="xs">Enter</x-filament::badge>
                                    <span>pour exécuter</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </x-filament::section>

                <!-- Résultats -->
                @if($hasError || !empty($queryResults) || $queryExecuted)
                    <x-filament::section
                        id="sql-results-section"
                        :icon="$hasError ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle'"
                        :icon-color="$hasError ? 'danger' : 'success'"
                    >
                        <x-slot name="heading">
                            @if($hasError)
                                Erreur SQL
                            @else
                                Résultats de la requête
                            @endif
                        </x-slot>

                        <x-slot name="description">
                            @if(!$hasError && !empty($queryResults))
                                {{ count($queryResults) }} ligne{{ count($queryResults) > 1 ? 's' : '' }} •
                                {{ round($executionTime * 1000, 2) }}ms
                            @elseif(!$hasError)
                                Requête exécutée avec succès • {{ round($executionTime * 1000, 2) }}ms
                            @endif
                        </x-slot>

                        @if(!$hasError && !empty($queryResults))
                            <x-slot name="headerEnd">
                                <div class="flex items-center gap-2">
                                    <x-filament::button
                                        wire:click="exportResults('csv')"
                                        icon="heroicon-o-arrow-down-tray"
                                        color="gray"
                                        size="sm"
                                    >
                                        Export CSV
                                    </x-filament::button>
                                    <x-filament::button
                                        wire:click="clearResults"
                                        icon="heroicon-o-x-mark"
                                        color="gray"
                                        size="sm"
                                    >
                                        Fermer
                                    </x-filament::button>
                                </div>
                            </x-slot>
                        @endif

                        @if($hasError)
                            <!-- Affichage d'erreur amélioré -->
                            <div id="sql-error-container" class="bg-red-50 border-2 border-red-300">
                                <div class="flex items-start space-x-3">
                                    <div class="flex-shrink-0">
                                        <x-heroicon-s-exclamation-triangle class="w-6 h-6 text-red-600" />
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <h3 class="text-lg font-semibold text-red-800 mb-3">
                                            Erreur SQL
                                        </h3>
                                        <div class="bg-red-100 border border-red-300 rounded-md p-4 max-h-64 overflow-y-auto">
                                            <pre class="text-sm text-red-800 whitespace-pre-wrap font-mono leading-relaxed break-words">{{ $errorMessage }}</pre>
                                        </div>
                                        <div class="mt-3 text-xs text-red-600">
                                            💡 Vérifiez la syntaxe de votre requête SQL et assurez-vous que les tables et colonnes existent.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @else
                            <!-- Tableau des résultats -->
                            @if(count($queryResults) > 0)
                                <div>
                                    <!-- Contrôles de pagination du haut -->
                                    @if($totalResults > $perPage)
                                        <x-sql-pagination
                                            :pagination-info="$this->getPaginationInfo()"
                                            :total-pages="$this->getTotalPages()"
                                            :current-page="$this->currentPage"
                                            :has-previous-page="$this->hasPreviousPage()"
                                            :has-next-page="$this->hasNextPage()"
                                            position="top"
                                        />
                                    @endif

                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200">
                                            <thead class="bg-gray-50">
                                            <tr>
                                                @foreach(array_keys($queryResults[0]) as $column)
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        {{ $column }}
                                                    </th>
                                                @endforeach
                                            </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-200">
                                            @foreach($paginatedResults as $index => $row)
                                            <tr class="hover:bg-gray-50 {{ $index % 2 === 0 ? 'bg-white' : 'bg-gray-50' }}">
                                                @foreach($row as $column => $value)
                                                    <td class="px-6 py-4 text-sm text-gray-900">
                                                        @if(is_null($value))
                                                            <x-filament::badge color="gray" size="sm">
                                                                NULL
                                                            </x-filament::badge>
                                                        @elseif(is_bool($value))
                                                            <x-filament::badge
                                                                :color="$value ? 'success' : 'danger'"
                                                                size="sm"
                                                            >
                                                                {{ $value ? 'TRUE' : 'FALSE' }}
                                                            </x-filament::badge>
                                                        @elseif(is_numeric($value) && isset($foreignKeyColumns[$column]))
                                                            @php
                                                                $tooltipInfo = $this->getDatabaseService()->getRecordTooltipInfo(
                                                                    $selectedDatabase,
                                                                    $foreignKeyColumns[$column]['referenced_table'],
                                                                    $foreignKeyColumns[$column]['referenced_column'],
                                                                    $value
                                                                );
                                                                $tooltipParts = [];
                                                                $tooltipParts[] = $foreignKeyColumns[$column]['referenced_table'] . ' #' . $value;

                                                                if ($tooltipInfo) {
                                                                    foreach ($tooltipInfo['data'] as $key => $val) {
                                                                        if ($val !== null && $val !== '') {
                                                                            $tooltipParts[] = ucfirst($key) . ': ' . $val;
                                                                        }
                                                                    }
                                                                } else {
                                                                    $tooltipParts[] = 'Aucune information disponible';
                                                                }

                                                                $tooltipContent = implode(' | ', $tooltipParts);
                                                            @endphp
                                                            <span
                                                                class="font-mono text-primary-600 cursor-help underline decoration-dotted hover:bg-primary-50 hover:text-primary-800 px-1 py-0.5 rounded transition-all duration-200"
                                                                x-tooltip="'{{ str_replace("'", "\'", $tooltipContent) }}'"
                                                            >
                                                                {{ $value }}
                                                            </span>
                                                        @elseif(is_numeric($value))
                                                            <span class="font-mono text-primary-600">{{ $value }}</span>
                                                        @elseif(strlen($value) > 100)
                                                            <div class="max-w-xs">
                                                                <span class="block truncate"
                                                                      title="{{ $value }}">{{ $value }}</span>
                                                                <x-filament::link size="sm" class="mt-1">
                                                                    Voir plus
                                                                </x-filament::link>
                                                            </div>
                                                        @else
                                                            <span class="break-words">{{ $value }}</span>
                                                        @endif
                                                    </td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Contrôles de pagination du bas -->
                                @if($totalResults > $perPage)
                                    <x-sql-pagination
                                        :pagination-info="$this->getPaginationInfo()"
                                        :total-pages="$this->getTotalPages()"
                                        :current-page="$this->currentPage"
                                        :has-previous-page="$this->hasPreviousPage()"
                                        :has-next-page="$this->hasNextPage()"
                                        position="bottom"
                                    />
                                @endif
                            </div>
                            @else
                                <!-- État vide avec succès -->
                                <div class="text-center py-12">
                                    <div
                                        class="flex items-center justify-center w-16 h-16 bg-green-100 rounded-full mx-auto mb-4">
                                        <x-heroicon-o-check-circle class="w-8 h-8 text-green-600"/>
                                    </div>
                                    <h3 class="text-lg font-medium text-gray-900 mb-2">Requête exécutée avec succès</h3>
                                    <p class="text-sm text-gray-500 mb-2">
                                        La requête a été exécutée mais n'a retourné aucune ligne.
                                    </p>
                                    <div class="text-xs text-gray-400">
                                        💡 Cela peut être normal selon votre requête (DELETE, UPDATE, ou SELECT sans résultats correspondants)
                                    </div>
                                </div>
                            @endif
                        @endif
                    </x-filament::section>
                @endif
            </div>

            <!-- Sidebar avec onglets -->
            <div class="lg:col-span-1">
                <div class="sticky top-6 space-y-6">
                    <!-- Onglet Historique -->
                    @if(!empty($queryHistory))
                        <x-filament::section
                            icon="heroicon-o-clock"
                            icon-color="info"
                            collapsible
                            collapsed
                        >
                            <x-slot name="heading">
                                Historique des requêtes
                            </x-slot>

                            <x-slot name="headerEnd">
                                <x-filament::button
                                    wire:click="clearHistory"
                                    size="sm"
                                    color="gray"
                                    icon="heroicon-o-trash"
                                >
                                    Vider
                                </x-filament::button>
                            </x-slot>

                            <div class="space-y-3 max-h-96 overflow-y-auto">
                                @foreach(array_slice($queryHistory, 0, 10) as $item)
                                    <div
                                        class="p-3 rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors group">
                                        <div class="flex items-start space-x-2">
                                            <div class="flex-shrink-0 mt-1">
                                                @if($item['success'])
                                                    <x-heroicon-s-check-circle class="w-4 h-4 text-green-500"/>
                                                @else
                                                    <x-heroicon-s-x-circle class="w-4 h-4 text-red-500"/>
                                                @endif
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center justify-between mb-1">
                                                    <div class="flex items-center space-x-2">
                                                        <x-filament::badge color="gray" size="xs">
                                                            {{ $item['database'] }}
                                                        </x-filament::badge>
                                                        @if(isset($item['execution_time']))
                                                            <span class="text-xs text-gray-500">
                                                                {{ round($item['execution_time'] * 1000, 2) }}ms
                                                            </span>
                                                        @endif
                                                    </div>
                                                    <x-filament::link
                                                        wire:click="loadQueryFromHistory('{{ addslashes($item['query']) }}')"
                                                        size="sm"
                                                        class="opacity-0 group-hover:opacity-100 transition-opacity"
                                                    >
                                                        Charger
                                                    </x-filament::link>
                                                </div>
                                                <pre
                                                    class="text-xs font-mono text-gray-900 whitespace-pre-wrap line-clamp-3">{{ $item['query'] }}</pre>
                                                <p class="text-xs text-gray-400 mt-1">
                                                    {{ $item['timestamp'] }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </x-filament::section>
                    @else
                        <x-filament::section
                            icon="heroicon-o-clock"
                            icon-color="gray"
                        >
                            <x-slot name="heading">
                                Historique des requêtes
                            </x-slot>

                            <div class="text-center py-8">
                                <x-heroicon-o-clock class="mx-auto h-8 w-8 text-gray-400 mb-2"/>
                                <h3 class="text-sm font-medium text-gray-900 mb-1">Aucun historique</h3>
                                <p class="text-xs text-gray-500">
                                    Vos requêtes exécutées apparaîtront ici
                                </p>
                            </div>
                        </x-filament::section>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <!-- Scripts pour le téléchargement CSV, le scroll vers les erreurs et l'éditeur SQL -->
    @vite('resources/js/sql-editor-inline.js')

    <script>
        let sqlEditor = null;
        let livewireComponent = null;

        // Fonction pour télécharger un fichier CSV
        function downloadCSV(content, filename) {
            const blob = new Blob([content], {type: 'text/csv;charset=utf-8;'});
            const link = document.createElement('a');

            if (link.download !== undefined) {
                const url = URL.createObjectURL(blob);
                link.setAttribute('href', url);
                link.setAttribute('download', filename);
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(url);
            }
        }

        // Fonction pour faire défiler vers l'erreur SQL
        function scrollToSqlError() {
            const errorContainer = document.getElementById('sql-error-container');
            if (errorContainer) {
                // Attendre un court délai pour que le DOM soit mis à jour
                setTimeout(() => {
                    errorContainer.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });

                    // Ajouter un effet de pulsation pour attirer l'attention
                    errorContainer.classList.add('animate-pulse');
                    setTimeout(() => {
                        errorContainer.classList.remove('animate-pulse');
                    }, 2000);
                }, 100);
            }
        }

        // Fonction pour faire défiler vers les résultats SQL
        function scrollToSqlResults() {
            const resultsSection = document.getElementById('sql-results-section');
            if (resultsSection) {
                // Attendre un court délai pour que le DOM soit mis à jour
                setTimeout(() => {
                    resultsSection.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });

                    // Ajouter un effet de pulsation pour attirer l'attention
                    resultsSection.classList.add('animate-pulse');
                    setTimeout(() => {
                        resultsSection.classList.remove('animate-pulse');
                    }, 1500);
                }, 150);
            }
        }

        // Créer une source d'autocomplétion personnalisée
        function createCompletionSource(schema) {
            return (context) => {
                const word = context.matchBefore(/\w*/)
                if (!word) return null

                const options = []

                // Mots-clés SQL
                const sqlKeywords = [
                    'SELECT', 'FROM', 'WHERE', 'JOIN', 'INNER JOIN', 'LEFT JOIN', 'RIGHT JOIN',
                    'GROUP BY', 'ORDER BY', 'HAVING', 'LIMIT', 'OFFSET', 'INSERT', 'UPDATE',
                    'DELETE', 'CREATE', 'ALTER', 'DROP', 'INDEX', 'TABLE', 'DATABASE',
                    'AND', 'OR', 'NOT', 'IN', 'EXISTS', 'BETWEEN', 'LIKE', 'IS NULL',
                    'IS NOT NULL', 'DISTINCT', 'COUNT', 'SUM', 'AVG', 'MIN', 'MAX'
                ]

                sqlKeywords.forEach(keyword => {
                    if (keyword.toLowerCase().startsWith(word.text.toLowerCase())) {
                        options.push({
                            label: keyword,
                            type: 'keyword',
                            info: `Mot-clé SQL: ${keyword}`
                        })
                    }
                })

                // Tables et colonnes du schéma
                if (schema && typeof schema === 'object') {
                    Object.keys(schema).forEach(tableName => {
                        if (tableName.toLowerCase().startsWith(word.text.toLowerCase())) {
                            options.push({
                                label: tableName,
                                type: 'class',
                                info: `Table: ${tableName}`
                            })
                        }
                    })

                    Object.entries(schema).forEach(([tableName, columns]) => {
                        if (Array.isArray(columns)) {
                            columns.forEach(columnName => {
                                if (columnName.toLowerCase().startsWith(word.text.toLowerCase())) {
                                    options.push({
                                        label: columnName,
                                        type: 'property',
                                        info: `Colonne de ${tableName}: ${columnName}`
                                    })
                                }
                            })
                        }
                    })
                }

                return {
                    from: word.from,
                    options: options.slice(0, 20),
                    validFor: /^\w*$/
                }
            }
        }

        // Initialiser l'éditeur SQL
        function initSqlEditor() {
            console.log('Début de l\'initialisation de l\'éditeur SQL');

            // Vérifier si un éditeur existe déjà et fonctionne
            const container = document.getElementById('sql-editor-container');
            if (sqlEditor && sqlEditor.state && container && container.querySelector('.cm-editor')) {
                console.log('Un éditeur existe déjà et fonctionne, on ne le recrée pas');
                return;
            }

            // Sauvegarder le contenu actuel si l'éditeur existe
            let currentContent = '';
            if (sqlEditor && sqlEditor.state) {
                try {
                    currentContent = sqlEditor.state.doc.toString();
                    console.log('Contenu sauvegardé:', currentContent.substring(0, 50) + '...');
                } catch (e) {
                    console.log('Erreur lors de la sauvegarde du contenu:', e);
                }
            }

            // Nettoyer l'éditeur existant s'il y en a un
            if (sqlEditor && sqlEditor.destroy) {
                try {
                    sqlEditor.destroy();
                } catch (e) {
                    console.log('Erreur lors de la destruction de l\'éditeur existant:', e);
                }
                sqlEditor = null;
            }

            const textarea = document.getElementById('sql-query-textarea');

            console.log('Éléments trouvés:', {
                textarea: !!textarea,
                container: !!container,
                codeMirrorModules: !!window.CodeMirrorModules
            });

            if (!textarea || !container) {
                console.error('Éléments requis non trouvés');
                return;
            }

            if (!livewireComponent) {
                console.error('Livewire component non disponible');
                return;
            }

            if (!window.CodeMirrorModules) {
                console.error('Modules CodeMirror non disponibles');
                return;
            }

            // Récupérer le schéma de la base de données via Livewire
            livewireComponent.call('getDatabaseSchema').then(schema => {
                console.log('Schéma récupéré:', schema);

                try {
                    const { EditorView, EditorState, sql, autocompletion, completionKeymap, defaultKeymap, basicSetup, keymap } = window.CodeMirrorModules;

                    const extensions = [
                        basicSetup,
                        sql({
                            schema: schema,
                            upperCaseKeywords: true
                        }),
                        autocompletion({
                            override: [createCompletionSource(schema)]
                        }),
                        keymap.of([
                            ...defaultKeymap,
                            ...completionKeymap,
                            {
                                key: 'Ctrl-Enter',
                                run: () => {
                                    console.log('🚀 Ctrl+Enter détecté dans l\'éditeur SQL');
                                    console.log('📋 Livewire component disponible:', !!livewireComponent);
                                    console.log('📝 Contenu de l\'éditeur:', sqlEditor?.state?.doc?.toString() || 'N/A');

                                    if (livewireComponent && typeof livewireComponent.executeQuery === 'function') {
                                        console.log('✅ Exécution de la requête...');
                                        livewireComponent.executeQuery();
                                    } else {
                                        console.error('❌ Livewire component ou méthode executeQuery non disponible');
                                    }
                                    return true;
                                }
                            }
                        ]),
                        EditorView.updateListener.of((update) => {
                            if (update.docChanged) {
                                const value = update.state.doc.toString();
                                textarea.value = value;
                                livewireComponent.set('sqlQuery', value, false);
                            }
                        }),
                        EditorView.theme({
                            '&': {
                                fontSize: '14px',
                                fontFamily: 'Monaco, Menlo, "Ubuntu Mono", monospace'
                            },
                            '.cm-content': {
                                padding: '12px',
                                minHeight: '300px'
                            },
                            '.cm-focused': {
                                outline: '2px solid rgb(59 130 246)',
                                outlineOffset: '2px'
                            },
                            '.cm-editor': {
                                borderRadius: '8px',
                                border: '1px solid rgb(209 213 219)'
                            },
                            '.cm-editor.cm-focused': {
                                borderColor: 'rgb(59 130 246)',
                                boxShadow: '0 0 0 1px rgb(59 130 246)'
                            }
                        })
                    ];

                    // Utiliser le contenu sauvegardé ou la valeur du textarea
                    const initialContent = currentContent || textarea.value || '';

                    const state = EditorState.create({
                        doc: initialContent,
                        extensions
                    });

                    sqlEditor = new EditorView({
                        state,
                        parent: container
                    });

                    // Cacher le textarea original
                    textarea.style.display = 'none';

                    console.log('Éditeur SQL initialisé avec succès');
                } catch (error) {
                    console.error('Erreur lors de la création de l\'éditeur:', error);
                }
            }).catch(error => {
                console.error('Erreur lors de la récupération du schéma:', error);
            });
        }

        // Fonction pour mettre à jour le contenu de l'éditeur
        function updateEditorContent(newQuery) {
            console.log('=== updateEditorContent appelée ===');
            console.log('Nouvelle requête:', newQuery);
            console.log('Type de newQuery:', typeof newQuery);
            console.log('sqlEditor disponible:', !!sqlEditor);

            if (!sqlEditor) {
                console.error('❌ Éditeur non disponible pour la mise à jour du contenu');
                return;
            }

            if (!sqlEditor.state) {
                console.error('❌ État de l\'éditeur non disponible');
                return;
            }

            try {
                console.log('📝 Tentative de mise à jour du contenu...');
                console.log('Contenu actuel:', sqlEditor.state.doc.toString());
                console.log('Longueur actuelle:', sqlEditor.state.doc.length);

                // Mettre à jour le contenu de l'éditeur CodeMirror
                sqlEditor.dispatch({
                    changes: {
                        from: 0,
                        to: sqlEditor.state.doc.length,
                        insert: newQuery || ''
                    }
                });

                // Synchroniser avec le textarea caché
                const textarea = document.getElementById('sql-query-textarea');
                if (textarea) {
                    textarea.value = newQuery || '';
                    console.log('✅ Textarea synchronisé');
                } else {
                    console.warn('⚠️ Textarea non trouvé');
                }

                console.log('✅ Contenu de l\'éditeur mis à jour avec succès');
                console.log('Nouveau contenu:', sqlEditor.state.doc.toString());
            } catch (error) {
                console.error('❌ Erreur lors de la mise à jour du contenu:', error);
                console.error('Stack trace:', error.stack);
            }
        }

        // Exposer la fonction globalement pour Alpine.js
        window.updateEditorContent = updateEditorContent;

        // Mettre à jour le schéma de l'éditeur quand la base de données change
        function updateSqlEditor() {
            console.log('Mise à jour du schéma de l\'éditeur SQL');

            if (!sqlEditor || !livewireComponent) {
                console.log('Éditeur ou Livewire non disponible pour la mise à jour');
                return;
            }

            // Récupérer le nouveau schéma
            livewireComponent.call('getDatabaseSchema').then(schema => {
                console.log('Nouveau schéma récupéré pour mise à jour:', schema);

                // Pour l'instant, on recrée l'éditeur avec le nouveau schéma
                // Dans une version plus avancée, on pourrait mettre à jour juste le schéma
                const currentContent = sqlEditor.state.doc.toString();

                // Détruire et recréer avec le nouveau schéma
                if (sqlEditor.destroy) {
                    sqlEditor.destroy();
                }

                // Réinitialiser avec le contenu préservé
                setTimeout(() => {
                    const textarea = document.getElementById('sql-query-textarea');
                    if (textarea) {
                        textarea.value = currentContent;
                    }
                    initSqlEditor();
                }, 100);

            }).catch(error => {
                console.error('Erreur lors de la mise à jour du schéma:', error);
            });
        }

        // Fonction pour attendre que les modules CodeMirror soient disponibles
        function waitForCodeMirror(callback, maxAttempts = 20) {
            let attempts = 0;
            const checkInterval = setInterval(() => {
                attempts++;
                if (window.CodeMirrorModules) {
                    clearInterval(checkInterval);
                    callback();
                } else if (attempts >= maxAttempts) {
                    clearInterval(checkInterval);
                    console.error('Modules CodeMirror non disponibles après', maxAttempts, 'tentatives');
                }
            }, 100);
        }

        // Fonction pour attendre que Livewire soit prêt
        function waitForLivewire(callback, maxAttempts = 20) {
            let attempts = 0;
            const checkInterval = setInterval(() => {
                attempts++;
                if (livewireComponent) {
                    clearInterval(checkInterval);
                    callback();
                } else if (attempts >= maxAttempts) {
                    clearInterval(checkInterval);
                    console.error('Livewire component non disponible après', maxAttempts, 'tentatives');
                }
            }, 100);
        }

        // Écouter les événements Livewire
        document.addEventListener('livewire:init', function () {
            console.log('Livewire init event déclenché');

            // Stocker la référence du composant Livewire
            livewireComponent = @this;

            console.log('Livewire component stocké:', !!livewireComponent);

            // Configurer les événements Livewire immédiatement
            console.log('Configuration des événements Livewire...');

            // Téléchargement CSV
            Livewire.on('download-csv', (data) => {
                downloadCSV(data[0].content, data[0].filename);
            });

            // Scroll vers l'erreur quand déclenché par le serveur
            Livewire.on('scroll-to-error', () => {
                scrollToSqlError();
            });

            // Scroll vers les résultats quand déclenché par le serveur
            Livewire.on('scroll-to-results', () => {
                scrollToSqlResults();
            });

            // Écouter les changements de base de données
            Livewire.on('database-changed', () => {
                console.log('🔄 Base de données changée - recréation de l\'éditeur avec nouveau schéma');
                if (sqlEditor && livewireComponent) {
                    // Sauvegarder le contenu actuel
                    const currentContent = sqlEditor.state.doc.toString();
                    console.log('💾 Contenu sauvegardé:', currentContent);

                    // Récupérer le nouveau schéma et recréer l'éditeur
                    livewireComponent.call('getDatabaseSchema').then(schema => {
                        console.log('📋 Nouveau schéma récupéré:', schema);
                        console.log('📊 Nombre de tables dans le schéma:', Object.keys(schema).length);

                        // Détruire l'éditeur actuel
                        if (sqlEditor.destroy) {
                            sqlEditor.destroy();
                        }
                        sqlEditor = null;

                        // Mettre à jour le textarea avec le contenu sauvegardé
                        const textarea = document.getElementById('sql-query-textarea');
                        if (textarea) {
                            textarea.value = currentContent;
                        }

                        // Recréer l'éditeur avec le nouveau schéma
                        setTimeout(() => {
                            initSqlEditor();
                            console.log('✅ Éditeur recréé avec le nouveau schéma');
                        }, 100);

                    }).catch(error => {
                        console.error('❌ Erreur lors de la récupération du nouveau schéma:', error);
                    });
                } else {
                    console.warn('⚠️ Éditeur ou Livewire non disponible pour la mise à jour');
                }
            });

            // Écouter les mises à jour du contenu de l'éditeur
            Livewire.on('update-sql-editor', (data) => {
                console.log('🎯 Événement update-sql-editor reçu:', data);

                // Gérer différents formats de données
                let queryValue = '';
                if (typeof data === 'string') {
                    queryValue = data;
                } else if (Array.isArray(data) && data.length > 0) {
                    if (typeof data[0] === 'string') {
                        queryValue = data[0];
                    } else if (data[0] && typeof data[0].query !== 'undefined') {
                        queryValue = data[0].query;
                    }
                } else if (data && typeof data.query !== 'undefined') {
                    queryValue = data.query;
                }

                console.log('🎯 Valeur de requête extraite:', queryValue);
                updateEditorContent(queryValue);
            });

            console.log('✅ Événements Livewire configurés avec succès');

            // Observer les changements dans le DOM pour détecter les erreurs (fallback)
            const errorObserver = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    if (mutation.type === 'childList') {
                        const errorContainer = document.getElementById('sql-error-container');
                        if (errorContainer && !errorContainer.hasAttribute('data-scrolled')) {
                            errorContainer.setAttribute('data-scrolled', 'true');
                            scrollToSqlError();
                        }
                    }
                });
            });

            // Observer le conteneur principal
            const mainContainer = document.querySelector('.sql-playground-container') || document.body;
            errorObserver.observe(mainContainer, {
                childList: true,
                subtree: true
            });
        });

        // Observer pour détecter si l'éditeur disparaît
        function setupEditorObserver() {
            const editorContainer = document.getElementById('sql-editor-container');
            if (!editorContainer) return;

            const editorObserver = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    if (mutation.type === 'childList') {
                        // Vérifier si l'éditeur CodeMirror a disparu
                        const cmEditor = editorContainer.querySelector('.cm-editor');
                        if (!cmEditor && sqlEditor) {
                            console.log('Éditeur CodeMirror détecté comme disparu, recréation...');
                            sqlEditor = null; // Reset la référence
                            setTimeout(() => {
                                initSqlEditor();
                            }, 100);
                        }
                    }
                });
            });

            editorObserver.observe(editorContainer, {
                childList: true,
                subtree: true
            });

            console.log('Observateur de l\'éditeur configuré');
        }

        // Raccourci clavier global pour Ctrl+Enter (fallback)
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'Enter') {
                // Vérifier si le focus est dans l'éditeur SQL
                const sqlEditorContainer = document.getElementById('sql-editor-container');
                const activeElement = document.activeElement;

                if (sqlEditorContainer && (
                    sqlEditorContainer.contains(activeElement) ||
                    activeElement.closest('.cm-editor') ||
                    activeElement.id === 'sql-query-textarea'
                )) {
                    console.log('🚀 Ctrl+Enter détecté globalement dans l\'éditeur SQL');
                    e.preventDefault();

                    if (livewireComponent && typeof livewireComponent.executeQuery === 'function') {
                        console.log('✅ Exécution de la requête via raccourci global...');
                        livewireComponent.executeQuery();
                    } else {
                        console.error('❌ Livewire component non disponible pour le raccourci global');
                    }
                }
            }
        });

        // Initialiser une seule fois au chargement de la page
        document.addEventListener('DOMContentLoaded', function () {
            console.log('DOM Content Loaded - initialisation unique de l\'éditeur');

            // Attendre que les modules CodeMirror soient disponibles
            waitForCodeMirror(() => {
                setTimeout(() => {
                    // Stocker la référence du composant Livewire
                    if (typeof @this !== 'undefined') {
                        livewireComponent = @this;
                        console.log('Livewire component stocké via DOMContentLoaded:', !!livewireComponent);
                        initSqlEditor();
                        setupEditorObserver(); // Configurer l'observateur
                    } else {
                        console.log('Livewire pas encore disponible, on attend...');
                        // Réessayer après un délai
                        setTimeout(() => {
                            if (typeof @this !== 'undefined') {
                                livewireComponent = @this;
                                console.log('Livewire component stocké (2ème tentative):', !!livewireComponent);
                                initSqlEditor();
                                setupEditorObserver(); // Configurer l'observateur
                            }
                        }, 1000);
                    }
                }, 500);
            });
        });
    </script>


    @endpush

    <!-- Modal d'explication -->
    <x-filament::modal
        id="explain-query"
        width="2xl"
        slide-over
    >
        <x-slot name="heading">
            Explication de la requête SQL
        </x-slot>

        <x-slot name="description">
            Voici une explication détaillée de votre requête SQL générée par l'IA
        </x-slot>

        <div>
            @include('filament.modals.query-explanation', [
                'explanation' => $queryExplanation,
                'query' => $sqlQuery
            ])
        </div>

        <x-slot name="footerActions">
            <x-filament::button
                x-on:click="$dispatch('close-modal', { id: 'explain-query' })"
                color="gray"
            >
                Fermer
            </x-filament::button>
        </x-slot>
    </x-filament::modal>

    <style>
        #header-sql .fi-section-content-ctn {
            display: none !important;
        }

        #sql-results-section .fi-section-content {
            padding: 0 !important;
        }
    </style>
</x-filament-panels::page>
