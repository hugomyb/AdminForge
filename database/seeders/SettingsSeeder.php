<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Setting;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Paramètres IA par défaut
        Setting::firstOrCreate(
            ['key' => 'ai_enabled'],
            [
                'value' => '0',
                'type' => 'boolean',
                'description' => 'Active ou désactive les fonctionnalités IA'
            ]
        );

        Setting::firstOrCreate(
            ['key' => 'openai_api_key'],
            [
                'value' => '',
                'type' => 'string',
                'description' => 'Clé API OpenAI'
            ]
        );

        Setting::firstOrCreate(
            ['key' => 'openai_model'],
            [
                'value' => 'gpt-3.5-turbo',
                'type' => 'string',
                'description' => 'Modèle OpenAI à utiliser'
            ]
        );

        Setting::firstOrCreate(
            ['key' => 'openai_max_tokens'],
            [
                'value' => '1000',
                'type' => 'integer',
                'description' => 'Nombre maximum de tokens'
            ]
        );

        // Paramètres SQL par défaut
        Setting::firstOrCreate(
            ['key' => 'enable_query_history'],
            [
                'value' => '1',
                'type' => 'boolean',
                'description' => 'Active l\'historique des requêtes'
            ]
        );

        Setting::firstOrCreate(
            ['key' => 'max_history_items'],
            [
                'value' => '50',
                'type' => 'integer',
                'description' => 'Nombre maximum d\'éléments dans l\'historique'
            ]
        );
    }
}
