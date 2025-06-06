@php
    use App\Services\DatabaseExplorerService;
    $databaseService = app(DatabaseExplorerService::class);
    $databases = $databaseService->getAllDatabases();
@endphp

<!-- Meta tag pour le token CSRF -->
<meta name="csrf-token" content="{{ csrf_token() }}">

<style>
/* Forcer la sidebar à ne pas avoir de scroll */
.fi-sidebar {
    overflow-y: hidden !important;
}
.fi-sidebar-nav {
    overflow-y: hidden !important;
}
</style>

<!-- Section Bases de données - placée après la navigation principale -->
<div class="fi-sidebar-nav-groups">
    <div class="fi-sidebar-nav-group" id="database-section">
        <!-- En-tête de section optimisé -->
        <div class="flex items-center gap-x-2 py-3 border-b border-gray-200 dark:border-gray-700">
            <div class="flex h-7 w-7 items-center justify-center rounded-md bg-primary-50 dark:bg-primary-500/10">
                <x-heroicon-o-circle-stack class="h-4 w-4 text-primary-600 dark:text-primary-400" />
            </div>
            <div class="flex-1 min-w-0">
                <h3 class="text-sm font-semibold text-gray-900">
                    Bases de données
                </h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                    {{ count($databases) }} base{{ count($databases) > 1 ? 's' : '' }}
                </p>
            </div>
        </div>

        <!-- Barre de recherche améliorée -->
        <div class="py-2">
            <div class="relative">
                <div class="absolute inset-y-0 left-0 flex items-center px-3 pointer-events-none" style="z-index: 10;">
                    <x-heroicon-o-magnifying-glass class="h-4 w-4 text-gray-400" />
                </div>
                <input
                    type="text"
                    id="database-search"
                    placeholder="Filtrer les bases... (Ctrl+K)"
                    style="padding-left: 35px"
                    class="database-search-input block w-full rounded-lg border-0 bg-gray-50 py-2 pl-10 pr-4 text-sm text-gray-900 ring-0 ring-inset ring-gray-200 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-primary-500 focus:bg-white dark:bg-gray-800 dark:text-white dark:ring-gray-700 dark:placeholder:text-gray-500 dark:focus:bg-gray-700 dark:focus:ring-primary-400 duration-200"
                    onkeyup="filterDatabases(this.value)"
                />
            </div>
        </div>

        <!-- Liste des bases optimisée -->
        <div class="pb-4">
            <div class="overflow-y-auto scrollbar-thin scrollbar-thumb-gray-300 dark:scrollbar-thumb-gray-600" id="databases-list">
                @if(count($databases) > 0)
                    <div class="space-y-0.5">
                        @foreach($databases as $database)
                            <a
                                href="{{ route('filament.admin.pages.database-detail') }}?database={{ urlencode($database) }}"
                                class="database-item database-tooltip group flex items-center gap-x-3 rounded-md px-3 py-2.5 text-sm font-medium text-gray-700 outline-none transition-all duration-150 hover:bg-gray-100 hover:text-gray-900 focus:bg-gray-100 focus:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white dark:focus:bg-gray-700 dark:focus:text-white"
                                data-database="{{ strtolower($database) }}"
                                title="{{ $database }}"
                            >
                                <div class="flex h-5 w-5 items-center justify-center">
                                    <x-heroicon-o-table-cells class="icon-transition h-4 w-4 text-gray-400 transition-colors group-hover:text-gray-600 dark:group-hover:text-gray-300" />
                                </div>
                                <span class="flex-1 truncate font-mono text-xs">{{ $database }}</span>
                                <x-heroicon-o-chevron-right class="h-3.5 w-3.5 text-gray-300 opacity-0 transition-all group-hover:opacity-100 group-hover:text-gray-500 dark:text-gray-600 dark:group-hover:text-gray-400" />
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            @if(count($databases) === 0)
                <div class="px-2 py-8 text-center">
                    <x-heroicon-o-exclamation-triangle class="mx-auto h-8 w-8 text-gray-300 dark:text-gray-600" />
                    <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                        Aucune base trouvée
                    </p>
                </div>
            @endif

            <!-- Bouton d'actualisation compact -->
            <div class="mt-3 px-1">
                <x-filament::button
                    wire:click="refreshDatabases"
                    wire:loading.attr="disabled"
                    wire:target="refreshDatabases"
                    size="sm"
                    color="gray"
                    class="w-full"
                    icon="heroicon-o-arrow-path"
                >
                    <span wire:loading.remove wire:target="refreshDatabases">Actualiser</span>
                    <span wire:loading wire:target="refreshDatabases">Actualisation...</span>
                </x-filament::button>
            </div>
        </div>
    </div>
</div>

<script>
function filterDatabases(searchTerm) {
    const items = document.querySelectorAll('.database-item');
    const term = searchTerm.toLowerCase().trim();
    let visibleCount = 0;

    items.forEach(item => {
        const databaseName = item.getAttribute('data-database');
        const isVisible = !term || databaseName.includes(term);

        if (isVisible) {
            item.style.display = 'flex';
            visibleCount++;
        } else {
            item.style.display = 'none';
        }
    });

    // Optionnel : afficher un message si aucun résultat
    const container = document.getElementById('databases-list');
    let noResultsMsg = container.querySelector('.no-results-message');

    if (visibleCount === 0 && term) {
        if (!noResultsMsg) {
            noResultsMsg = document.createElement('div');
            noResultsMsg.className = 'no-results-message px-3 py-4 text-center text-sm text-gray-500 dark:text-gray-400';
            noResultsMsg.innerHTML = '<p>Aucune base trouvée pour "' + term + '"</p>';
            container.appendChild(noResultsMsg);
        }
    } else if (noResultsMsg) {
        noResultsMsg.remove();
    }
}



// Fonction pour ajuster la hauteur de la liste des bases de données
function adjustDatabaseListHeight() {
    const databaseSection = document.getElementById('database-section');
    const databasesList = document.getElementById('databases-list');

    if (!databaseSection || !databasesList) return;

    // Trouver la sidebar principale
    const sidebar = document.querySelector('.fi-sidebar') || document.querySelector('[data-sidebar]');
    if (!sidebar) return;

    // Calculer la position du début de la liste
    const listRect = databasesList.getBoundingClientRect();
    const listTop = listRect.top;

    // Calculer la position du bas de la sidebar
    const sidebarRect = sidebar.getBoundingClientRect();
    const sidebarBottom = sidebarRect.bottom;

    // Espace pour le bouton d'actualisation et padding (environ 100px pour être sûr)
    const bottomSpace = 100;

    // Calculer la hauteur disponible pour la liste
    const availableHeight = sidebarBottom - listTop - bottomSpace;

    // Appliquer la hauteur (minimum 150px pour éviter une liste trop petite)
    const finalHeight = Math.max(150, availableHeight);
    databasesList.style.maxHeight = finalHeight + 'px';

    // S'assurer que la sidebar elle-même n'a pas de scroll
    if (sidebar) {
        sidebar.style.overflowY = 'hidden';
    }
}

// Ajuster la hauteur au chargement et lors du redimensionnement
document.addEventListener('DOMContentLoaded', function() {
    // Petit délai pour s'assurer que Filament a fini de charger
    setTimeout(adjustDatabaseListHeight, 100);
});
window.addEventListener('resize', adjustDatabaseListHeight);

// Ajuster aussi après le chargement complet de la page
window.addEventListener('load', function() {
    setTimeout(adjustDatabaseListHeight, 200);
});

// Observer les changements dans la sidebar pour réajuster si nécessaire
if (window.ResizeObserver) {
    const resizeObserver = new ResizeObserver(adjustDatabaseListHeight);
    const sidebar = document.querySelector('.fi-sidebar');
    if (sidebar) {
        resizeObserver.observe(sidebar);
    }
}

// Raccourci clavier pour la recherche
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        const searchInput = document.getElementById('database-search');
        if (searchInput) {
            searchInput.focus();
            searchInput.select();
        }
    }
});

// Fonction pour créer une base de données depuis la sidebar
async function createDatabaseFromSidebar(event) {
    event.preventDefault();

    const form = event.target;
    const formData = new FormData(form);
    const databaseName = formData.get('database_name');

    if (!databaseName) {
        alert('Veuillez entrer un nom de base de données');
        return;
    }

    // Validation côté client
    const namePattern = /^[a-zA-Z_][a-zA-Z0-9_]*$/;
    if (!namePattern.test(databaseName)) {
        alert('Le nom doit commencer par une lettre ou un underscore et ne contenir que des lettres, chiffres et underscores.');
        return;
    }

    try {
        // Désactiver le bouton pendant la création
        const submitButton = form.querySelector('button[type="submit"]');
        const originalText = submitButton.textContent;
        submitButton.disabled = true;
        submitButton.textContent = 'Création...';

        // Envoyer la requête
        const response = await fetch('/admin/create-database', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                database_name: databaseName
            })
        });

        const result = await response.json();

        if (result.success) {
            // Fermer le dropdown
            const dropdownContainer = form.closest('[x-data]');
            if (dropdownContainer && dropdownContainer.__x) {
                dropdownContainer.__x.$data.open = false;
            }

            // Vider le formulaire
            form.reset();

            // Succès - recharger la page pour afficher la nouvelle base
            setTimeout(() => {
                window.location.reload();
            }, 500);
        } else {
            // Erreur
            alert('Erreur : ' + result.message);
        }
    } catch (error) {
        alert('Erreur lors de la création de la base de données : ' + error.message);
    } finally {
        // Réactiver le bouton
        const submitButton = form.querySelector('button[type="submit"]');
        submitButton.disabled = false;
        submitButton.textContent = originalText;
    }
}
</script>
