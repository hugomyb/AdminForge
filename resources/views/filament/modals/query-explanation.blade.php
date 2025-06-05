<div class="space-y-6">
    <!-- Requête SQL -->
    <div>
        <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
            <x-heroicon-o-code-bracket class="w-5 h-5 text-primary-600"/>
            Requête SQL
        </h3>
        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
            <pre class="text-sm font-mono text-gray-800 whitespace-pre-wrap">{{ $query }}</pre>
        </div>
    </div>

    <!-- Explication -->
    <div>
        <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
            <x-heroicon-o-light-bulb class="w-5 h-5 text-yellow-600"/>
            Explication détaillée
        </h3>

        @if(str_starts_with($explanation, 'ERROR:'))
            <!-- Affichage d'erreur -->
            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                <div class="flex items-start gap-3">
                    <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-red-600 flex-shrink-0 mt-0.5"/>
                    <div>
                        <h4 class="font-semibold text-red-800 mb-1">Impossible de générer l'explication</h4>
                        <p class="text-red-700">{{ str_replace('ERROR: ', '', $explanation) }}</p>
                    </div>
                </div>
            </div>
        @elseif(!empty($explanation))
            <!-- Affichage de l'explication -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="prose prose-sm max-w-none text-gray-700">
                    {!! nl2br(e($explanation)) !!}
                </div>
            </div>
        @else
            <!-- État de chargement -->
            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                <div class="flex items-center justify-center py-8">
                    <div class="flex items-center gap-3">
                        <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div>
                        <span class="text-gray-700 font-medium">Génération de l'explication en cours...</span>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <!-- Note -->
    <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
        <div class="flex items-start gap-3">
            <x-heroicon-o-information-circle class="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5"/>
            <div class="text-sm text-amber-800">
                <p class="font-medium mb-1">Note importante</p>
                <p>Cette explication est générée par l'IA et peut contenir des approximations. Vérifiez toujours la logique de vos requêtes avant de les exécuter en production.</p>
            </div>
        </div>
    </div>
</div>
