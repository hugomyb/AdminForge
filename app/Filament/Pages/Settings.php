<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use App\Models\Setting;
use App\Services\EncryptionService;
use Livewire\Attributes\Reactive;

class Settings extends Page implements HasForms, HasActions
{
    use InteractsWithForms;
    use InteractsWithActions;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static string $view = 'filament.pages.settings';
    protected static ?string $title = 'Paramètres';
    protected static ?string $navigationLabel = 'Paramètres';
    protected static bool $shouldRegisterNavigation = false;

    public ?array $data = [];

    // Propriétés réactives pour les paramètres
    public bool $ai_enabled = false;
    public string $openai_api_key = '';
    public string $openai_model = 'gpt-3.5-turbo';
    public int $openai_max_tokens = 1000;
    public bool $enable_query_history = true;
    public int $max_history_items = 50;

    public function mount(): void
    {
        $this->loadSettings();
        $this->form->fill($this->data);
    }

    protected function loadSettings(): void
    {
        $this->ai_enabled = Setting::get('ai_enabled', false);
        $rawApiKey = Setting::get('openai_api_key', '');
        // Masquer la clé API pour l'affichage (sauf si elle est vide)
        $this->openai_api_key = $rawApiKey ? EncryptionService::maskApiKey($rawApiKey) : '';
        $this->openai_model = Setting::get('openai_model', 'gpt-3.5-turbo');
        $this->openai_max_tokens = Setting::get('openai_max_tokens', 1000);
        $this->enable_query_history = Setting::get('enable_query_history', true);
        $this->max_history_items = Setting::get('max_history_items', 50);

        $this->data = [
            'ai_enabled' => $this->ai_enabled,
            'openai_api_key' => $this->openai_api_key,
            'openai_model' => $this->openai_model,
            'openai_max_tokens' => $this->openai_max_tokens,
            'enable_query_history' => $this->enable_query_history,
            'max_history_items' => $this->max_history_items,
        ];
    }

    public function updatedData($value, $key): void
    {
        // Mise à jour réactive des propriétés
        if (isset($this->data[$key])) {
            $this->{$key} = $this->data[$key];
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Configuration OpenAI')
                    ->description('Configurez l\'intégration avec OpenAI pour les fonctionnalités IA')
                    ->schema([
                        Toggle::make('ai_enabled')
                            ->label('Activer les fonctionnalités IA')
                            ->helperText('Active ou désactive les fonctionnalités basées sur l\'IA')
                            ->live()
                            ->afterStateUpdated(function ($state) {
                                $this->ai_enabled = $state;
                            }),

                        TextInput::make('openai_api_key')
                            ->label('Clé API OpenAI')
                            ->password()
                            ->placeholder('sk-... (saisissez une nouvelle clé pour la modifier)')
                            ->helperText('Votre clé API OpenAI pour utiliser les fonctionnalités IA. Les clés sont chiffrées en base de données.')
                            ->visible(fn ($get) => $get('ai_enabled'))
                            ->live()
                            ->afterStateUpdated(function ($state) {
                                $this->openai_api_key = $state;
                            }),

                        TextInput::make('openai_model')
                            ->label('Modèle OpenAI')
                            ->default('gpt-3.5-turbo')
                            ->helperText('Le modèle OpenAI à utiliser (gpt-3.5-turbo, gpt-4, etc.)')
                            ->visible(fn ($get) => $get('ai_enabled'))
                            ->live()
                            ->afterStateUpdated(function ($state) {
                                $this->openai_model = $state;
                            }),

                        TextInput::make('openai_max_tokens')
                            ->label('Nombre maximum de tokens')
                            ->numeric()
                            ->default(1000)
                            ->helperText('Nombre maximum de tokens pour les réponses IA')
                            ->visible(fn ($get) => $get('ai_enabled'))
                            ->live()
                            ->afterStateUpdated(function ($state) {
                                $this->openai_max_tokens = $state;
                            }),
                    ]),

                Section::make('Configuration SQL')
                    ->description('Paramètres pour l\'éditeur SQL et l\'historique')
                    ->schema([
                        Toggle::make('enable_query_history')
                            ->label('Activer l\'historique des requêtes')
                            ->default(true)
                            ->helperText('Enregistre l\'historique des requêtes exécutées')
                            ->live()
                            ->afterStateUpdated(function ($state) {
                                $this->enable_query_history = $state;
                            }),

                        TextInput::make('max_history_items')
                            ->label('Nombre maximum d\'éléments dans l\'historique')
                            ->numeric()
                            ->default(50)
                            ->visible(fn ($get) => $get('enable_query_history'))
                            ->helperText('Nombre maximum de requêtes à conserver dans l\'historique')
                            ->live()
                            ->afterStateUpdated(function ($state) {
                                $this->max_history_items = $state;
                            }),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        try {
            $data = $this->form->getState();

            // Sauvegarder en base de données
            Setting::set('ai_enabled', $data['ai_enabled'], 'boolean', 'Active ou désactive les fonctionnalités IA');

            // Ne sauvegarder la clé API que si elle n'est pas masquée (nouvelle clé saisie)
            if (!empty($data['openai_api_key']) && !str_contains($data['openai_api_key'], '*')) {
                // Valider le format de la clé OpenAI
                if (!EncryptionService::validateOpenAIKey($data['openai_api_key'])) {
                    throw new \Exception('Format de clé API OpenAI invalide. La clé doit commencer par "sk-".');
                }
                Setting::set('openai_api_key', $data['openai_api_key'], 'string', 'Clé API OpenAI (chiffrée)');
            }

            Setting::set('openai_model', $data['openai_model'], 'string', 'Modèle OpenAI à utiliser');
            Setting::set('openai_max_tokens', $data['openai_max_tokens'], 'integer', 'Nombre maximum de tokens');
            Setting::set('enable_query_history', $data['enable_query_history'], 'boolean', 'Active l\'historique des requêtes');
            Setting::set('max_history_items', $data['max_history_items'], 'integer', 'Nombre maximum d\'éléments dans l\'historique');

            // Recharger les paramètres
            $this->loadSettings();

            Notification::make()
                ->title('Paramètres sauvegardés')
                ->body('Les paramètres ont été sauvegardés avec succès. Les clés API sont chiffrées en base de données.')
                ->success()
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->title('Erreur')
                ->body('Erreur lors de la sauvegarde: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function testOpenAiConnection(): void
    {
        try {
            // Utiliser la clé stockée en base de données (déjà déchiffrée)
            $apiKey = Setting::get('openai_api_key', '');

            if (empty($apiKey)) {
                throw new \Exception('Aucune clé API OpenAI configurée');
            }

            $data = $this->form->getState();

            // Test simple de connexion à OpenAI
            $response = \Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(10)->post('https://api.openai.com/v1/chat/completions', [
                'model' => $data['openai_model'],
                'messages' => [
                    ['role' => 'user', 'content' => 'Test connection']
                ],
                'max_tokens' => 5
            ]);

            if ($response->successful()) {
                Notification::make()
                    ->title('Connexion réussie')
                    ->body('La connexion à OpenAI fonctionne correctement')
                    ->success()
                    ->send();
            } else {
                throw new \Exception('Erreur de connexion: ' . $response->body());
            }

        } catch (\Exception $e) {
            Notification::make()
                ->title('Erreur de connexion')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Sauvegarder')
                ->icon('heroicon-o-check')
                ->color('primary')
                ->action('save'),
            Action::make('test_openai')
                ->label('Tester OpenAI')
                ->icon('heroicon-o-signal')
                ->color('gray')
                ->action('testOpenAiConnection')
                ->visible(fn () => $this->ai_enabled && !empty($this->openai_api_key)),
        ];
    }
}
