<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Setting;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Migrer les paramètres depuis les fichiers de configuration vers la base de données
        $this->migrateConfigToDatabase();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Supprimer les paramètres migrés (optionnel)
        $configKeys = [
            'ai_enabled',
            'openai_api_key',
            'openai_model',
            'openai_max_tokens',
            'enable_query_history',
            'max_history_items',
        ];

        Setting::whereIn('key', $configKeys)->delete();
    }

    /**
     * Migrer les paramètres de configuration vers la base de données
     */
    private function migrateConfigToDatabase(): void
    {
        $migrations = [
            // OpenAI Settings
            [
                'key' => 'ai_enabled',
                'value' => config('adminforge.openai.enabled', true),
                'type' => 'boolean',
                'description' => 'Active ou désactive les fonctionnalités IA'
            ],
            [
                'key' => 'openai_model',
                'value' => config('adminforge.openai.model', 'gpt-3.5-turbo'),
                'type' => 'string',
                'description' => 'Modèle OpenAI à utiliser'
            ],
            [
                'key' => 'openai_max_tokens',
                'value' => 1000,
                'type' => 'integer',
                'description' => 'Nombre maximum de tokens pour les réponses IA'
            ],

            // SQL Settings
            [
                'key' => 'enable_query_history',
                'value' => config('adminforge.sql.enable_query_history', true),
                'type' => 'boolean',
                'description' => 'Active l\'historique des requêtes SQL'
            ],
            [
                'key' => 'max_history_items',
                'value' => config('adminforge.sql.max_history_items', 50),
                'type' => 'integer',
                'description' => 'Nombre maximum d\'éléments dans l\'historique'
            ],
        ];

        foreach ($migrations as $setting) {
            // Ne créer que si le paramètre n'existe pas déjà
            if (!Setting::where('key', $setting['key'])->exists()) {
                Setting::create($setting);
                echo "✅ Migré: {$setting['key']}\n";
            } else {
                echo "⏭️  Existe déjà: {$setting['key']}\n";
            }
        }

        // Gestion spéciale pour la clé API OpenAI (ne pas écraser si elle existe déjà)
        if (!Setting::where('key', 'openai_api_key')->exists()) {
            $configApiKey = config('adminforge.openai.api_key');
            if (!empty($configApiKey) && $configApiKey !== null) {
                Setting::create([
                    'key' => 'openai_api_key',
                    'value' => $configApiKey, // Sera automatiquement chiffrée par le modèle
                    'type' => 'string',
                    'description' => 'Clé API OpenAI (chiffrée)'
                ]);
                echo "✅ Migré et chiffré: openai_api_key\n";
            } else {
                echo "⚠️  Aucune clé API OpenAI trouvée dans la configuration\n";
            }
        } else {
            echo "⏭️  Clé API OpenAI existe déjà en base\n";
        }
    }
};
