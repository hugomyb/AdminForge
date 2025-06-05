<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\OpenAIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenAIServiceDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_openai_service_uses_database_settings()
    {
        // Configurer les paramètres en base de données
        Setting::set('ai_enabled', true, 'boolean');
        Setting::set('openai_api_key', 'sk-test1234567890abcdef', 'string');
        Setting::set('openai_model', 'gpt-4', 'string');

        // Créer une instance du service
        $service = new OpenAIService();

        // Vérifier que le service utilise les paramètres de la base de données
        $this->assertTrue($service->isEnabled());
        
        // Utiliser la réflexion pour accéder aux propriétés privées
        $reflection = new \ReflectionClass($service);
        
        $apiKeyProperty = $reflection->getProperty('apiKey');
        $apiKeyProperty->setAccessible(true);
        $this->assertEquals('sk-test1234567890abcdef', $apiKeyProperty->getValue($service));
        
        $modelProperty = $reflection->getProperty('model');
        $modelProperty->setAccessible(true);
        $this->assertEquals('gpt-4', $modelProperty->getValue($service));
    }

    public function test_openai_service_disabled_when_no_api_key()
    {
        // Configurer seulement l'activation sans clé API
        Setting::set('ai_enabled', true, 'boolean');
        Setting::set('openai_model', 'gpt-3.5-turbo', 'string');
        // Pas de clé API

        $service = new OpenAIService();

        // Le service doit être désactivé sans clé API
        $this->assertFalse($service->isEnabled());
    }

    public function test_openai_service_disabled_when_ai_disabled()
    {
        // Configurer une clé API mais désactiver l'IA
        Setting::set('ai_enabled', false, 'boolean');
        Setting::set('openai_api_key', 'sk-test1234567890abcdef', 'string');
        Setting::set('openai_model', 'gpt-3.5-turbo', 'string');

        $service = new OpenAIService();

        // Le service doit être désactivé quand l'IA est désactivée
        $this->assertFalse($service->isEnabled());
    }

    public function test_openai_service_with_encrypted_key()
    {
        // Sauvegarder une clé qui sera automatiquement chiffrée
        $originalKey = 'sk-test1234567890abcdef1234567890abcdef';
        Setting::set('ai_enabled', true, 'boolean');
        Setting::set('openai_api_key', $originalKey, 'string');

        // Vérifier que la clé est chiffrée en base
        $rawValue = \DB::table('settings')
            ->where('key', 'openai_api_key')
            ->value('value');
        
        $this->assertNotEquals($originalKey, $rawValue);

        // Créer le service et vérifier qu'il déchiffre correctement
        $service = new OpenAIService();
        $this->assertTrue($service->isEnabled());

        // Vérifier que le service a la clé déchiffrée
        $reflection = new \ReflectionClass($service);
        $apiKeyProperty = $reflection->getProperty('apiKey');
        $apiKeyProperty->setAccessible(true);
        $this->assertEquals($originalKey, $apiKeyProperty->getValue($service));
    }

    public function test_openai_service_fallback_values()
    {
        // Aucun paramètre en base de données
        $service = new OpenAIService();

        // Vérifier les valeurs par défaut
        $this->assertFalse($service->isEnabled());

        $reflection = new \ReflectionClass($service);
        
        $modelProperty = $reflection->getProperty('model');
        $modelProperty->setAccessible(true);
        $this->assertEquals('gpt-3.5-turbo', $modelProperty->getValue($service));
        
        $apiKeyProperty = $reflection->getProperty('apiKey');
        $apiKeyProperty->setAccessible(true);
        $this->assertEquals('', $apiKeyProperty->getValue($service));
    }
}
