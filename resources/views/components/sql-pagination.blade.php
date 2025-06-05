@props([
    'paginationInfo',
    'totalPages',
    'currentPage',
    'hasPreviousPage',
    'hasNextPage',
    'position' => 'bottom' // 'top' ou 'bottom'
])

@php
    $bgClass = $position === 'top' ? 'bg-white' : 'bg-white';
    $borderClass = $position === 'top' ? 'border-b border-gray-200' : 'border-t border-gray-200';
@endphp

<div class="flex items-center justify-between px-4 py-3 {{ $bgClass }} {{ $borderClass }}">
    <div class="flex items-center space-x-2">
        <span class="text-sm text-gray-700">
            Affichage de {{ $paginationInfo['start'] }} à {{ $paginationInfo['end'] }} sur {{ $paginationInfo['total'] }} résultats
        </span>
    </div>

    <div class="flex items-center gap-2">
        <!-- Bouton page précédente -->
        <x-filament::button
            wire:click="previousPage"
            :disabled="!$hasPreviousPage"
            size="sm"
            color="gray"
            icon="heroicon-o-chevron-left"
        >
            Précédent
        </x-filament::button>

        <!-- Numéros de pages -->
        <div class="flex items-center gap-1">
            @php
                $startPage = max(1, $currentPage - 2);
                $endPage = min($totalPages, $currentPage + 2);
            @endphp

            @if($startPage > 1)
                <x-filament::button
                    wire:click="goToPage(1)"
                    size="sm"
                    color="gray"
                    class="w-8 h-8 p-0"
                >
                    1
                </x-filament::button>
                @if($startPage > 2)
                    <span class="text-gray-500">...</span>
                @endif
            @endif

            @for($page = $startPage; $page <= $endPage; $page++)
                <x-filament::button
                    wire:click="goToPage({{ $page }})"
                    size="sm"
                    :color="$page === $currentPage ? 'primary' : 'gray'"
                    class="w-8 h-8 p-0"
                >
                    {{ $page }}
                </x-filament::button>
            @endfor

            @if($endPage < $totalPages)
                @if($endPage < $totalPages - 1)
                    <span class="text-gray-500">...</span>
                @endif
                <x-filament::button
                    wire:click="goToPage({{ $totalPages }})"
                    size="sm"
                    color="gray"
                    class="w-8 h-8 p-0"
                >
                    {{ $totalPages }}
                </x-filament::button>
            @endif
        </div>

        <!-- Bouton page suivante -->
        <x-filament::button
            wire:click="nextPage"
            :disabled="!$hasNextPage"
            size="sm"
            color="gray"
            icon="heroicon-o-chevron-right"
        >
            Suivant
        </x-filament::button>
    </div>
</div>
