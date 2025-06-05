<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\EncryptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EncryptionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_should_encrypt_sensitive_keys()
    {
        $this->assertTrue(EncryptionService::shouldEncrypt('openai_api_key'));
        $this->assertTrue(EncryptionService::shouldEncrypt('openai_token'));
        $this->assertTrue(EncryptionService::shouldEncrypt('api_key'));
        $this->assertTrue(EncryptionService::shouldEncrypt('some_token'));
        $this->assertTrue(EncryptionService::shouldEncrypt('my_secret'));
        
        $this->assertFalse(EncryptionService::shouldEncrypt('username'));
        $this->assertFalse(EncryptionService::shouldEncrypt('email'));
        $this->assertFalse(EncryptionService::shouldEncrypt('name'));
    }

    public function test_encrypt_and_decrypt()
    {
        $originalValue = 'sk-1234567890abcdef1234567890abcdef';
        
        $encrypted = EncryptionService::encrypt($originalValue);
        $this->assertNotEquals($originalValue, $encrypted);
        $this->assertTrue(EncryptionService::isEncrypted($encrypted));
        
        $decrypted = EncryptionService::decrypt($encrypted);
        $this->assertEquals($originalValue, $decrypted);
    }

    public function test_encrypt_empty_value()
    {
        $this->assertNull(EncryptionService::encrypt(''));
        $this->assertNull(EncryptionService::encrypt(null));
    }

    public function test_decrypt_non_encrypted_value()
    {
        $plainValue = 'sk-1234567890abcdef';
        $result = EncryptionService::decrypt($plainValue);
        $this->assertEquals($plainValue, $result);
    }

    public function test_mask_api_key()
    {
        $apiKey = 'sk-1234567890abcdef1234567890abcdef';
        $masked = EncryptionService::maskApiKey($apiKey);
        
        $this->assertStringStartsWith('sk-1', $masked);
        $this->assertStringEndsWith('cdef', $masked);
        $this->assertStringContainsString('****', $masked);
        $this->assertNotEquals($apiKey, $masked);
    }

    public function test_validate_openai_key()
    {
        $this->assertTrue(EncryptionService::validateOpenAIKey('sk-1234567890abcdef1234567890abcdef'));
        $this->assertFalse(EncryptionService::validateOpenAIKey('invalid-key'));
        $this->assertFalse(EncryptionService::validateOpenAIKey(''));
        $this->assertFalse(EncryptionService::validateOpenAIKey('sk-short'));
    }

    public function test_setting_model_encryption()
    {
        $apiKey = 'sk-1234567890abcdef1234567890abcdef';

        // Sauvegarder une clé API
        Setting::set('openai_api_key', $apiKey, 'string', 'Test API key');

        // Vérifier que la valeur en base est chiffrée
        $rawValue = \DB::table('settings')
            ->where('key', 'openai_api_key')
            ->value('value');

        // La valeur stockée doit être différente de la valeur originale (chiffrée)
        $this->assertNotEquals($apiKey, $rawValue);

        // Vérifier que la récupération déchiffre automatiquement
        $retrievedKey = Setting::get('openai_api_key');
        $this->assertEquals($apiKey, $retrievedKey);
    }

    public function test_setting_model_non_sensitive_key()
    {
        $value = 'some-normal-value';

        // Sauvegarder une valeur non sensible
        Setting::set('normal_setting', $value, 'string', 'Test normal setting');

        // Vérifier que la valeur n'est pas chiffrée (stockée telle quelle)
        $rawValue = \DB::table('settings')
            ->where('key', 'normal_setting')
            ->value('value');

        $this->assertEquals($value, $rawValue);
        $this->assertFalse(EncryptionService::isEncrypted($rawValue));

        // Vérifier la récupération
        $retrievedValue = Setting::get('normal_setting');
        $this->assertEquals($value, $retrievedValue);
    }

    public function test_encrypt_sensitive_data_array()
    {
        $data = [
            'openai_api_key' => 'sk-1234567890abcdef',
            'username' => 'john_doe',
            'some_token' => 'token123',
            'email' => 'john@example.com'
        ];
        
        $encrypted = EncryptionService::encryptSensitiveData($data);
        
        // Les clés sensibles doivent être chiffrées
        $this->assertNotEquals($data['openai_api_key'], $encrypted['openai_api_key']);
        $this->assertNotEquals($data['some_token'], $encrypted['some_token']);
        
        // Les clés non sensibles doivent rester inchangées
        $this->assertEquals($data['username'], $encrypted['username']);
        $this->assertEquals($data['email'], $encrypted['email']);
    }

    public function test_decrypt_sensitive_data_array()
    {
        $originalData = [
            'openai_api_key' => 'sk-1234567890abcdef',
            'username' => 'john_doe',
            'some_token' => 'token123',
            'email' => 'john@example.com'
        ];
        
        $encrypted = EncryptionService::encryptSensitiveData($originalData);
        $decrypted = EncryptionService::decryptSensitiveData($encrypted);
        
        $this->assertEquals($originalData, $decrypted);
    }
}
