<div class="space-y-6">
    <!-- Message d'erreur -->
    <div class="bg-red-50 border border-red-200 rounded-lg p-6">
        <div class="flex items-start gap-3">
            <x-heroicon-o-exclamation-triangle class="w-6 h-6 text-red-600 flex-shrink-0 mt-0.5"/>
            <div>
                <h3 class="text-lg font-semibold text-red-800 mb-2">
                    Impossible de générer l'explication
                </h3>
                <p class="text-red-700">
                    {{ $error }}
                </p>
            </div>
        </div>
    </div>

    <!-- Conseils -->
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div class="flex items-start gap-3">
            <x-heroicon-o-information-circle class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5"/>
            <div class="text-sm text-blue-800">
                <p class="font-medium mb-1">Conseils :</p>
                <ul class="list-disc list-inside space-y-1">
                    <li>Vérifiez que votre clé API OpenAI est configurée dans les paramètres</li>
                    <li>Assurez-vous d'avoir saisi une requête SQL valide</li>
                    <li>Réessayez dans quelques instants si le service est temporairement indisponible</li>
                </ul>
            </div>
        </div>
    </div>
</div>
