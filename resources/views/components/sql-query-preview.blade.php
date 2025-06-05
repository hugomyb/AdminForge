@props(['query'])

<div class="bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4 max-h-40 overflow-y-auto">
    <div class="flex items-center gap-2 mb-2">
        <x-heroicon-s-code-bracket class="w-4 h-4 text-gray-500"/>
        <span class="text-xs font-medium text-gray-600 dark:text-gray-400 uppercase tracking-wide">Requête SQL</span>
    </div>
    <pre class="text-sm font-mono text-gray-900 dark:text-gray-100 whitespace-pre-wrap leading-relaxed">{{ $query }}</pre>
    @if(strlen($query) > 500)
        <div class="mt-2 text-xs text-gray-500 italic">
            Requête tronquée pour l'affichage - {{ strlen($query) }} caractères au total
        </div>
    @endif
</div>
