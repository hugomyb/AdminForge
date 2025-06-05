<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class EncryptionService
{
    /**
     * Liste des clés sensibles qui doivent être chiffrées
     */
    private const SENSITIVE_KEYS = [
        'openai_api_key',
        'openai_token',
        'api_key',
    ];

    /**
     * Vérifie si une clé doit être chiffrée
     */
    public static function shouldEncrypt(string $key): bool
    {
        return in_array($key, self::SENSITIVE_KEYS) || 
               str_contains($key, 'api_key') || 
               str_contains($key, 'token') ||
               str_contains($key, 'secret');
    }

    /**
     * Chiffre une valeur de manière sécurisée
     */
    public static function encrypt($value): ?string
    {
        if (empty($value) || is_null($value)) {
            return null;
        }

        try {
            // Vérifier si la valeur est déjà chiffrée
            if (self::isEncrypted($value)) {
                return $value;
            }

            return Crypt::encryptString($value);
        } catch (\Exception $e) {
            Log::error('Erreur lors du chiffrement', [
                'error' => $e->getMessage(),
                'value_length' => strlen($value)
            ]);
            return $value; // Retourner la valeur non chiffrée en cas d'erreur
        }
    }

    /**
     * Déchiffre une valeur
     */
    public static function decrypt($value): ?string
    {
        if (empty($value) || is_null($value)) {
            return null;
        }

        try {
            // Si la valeur n'est pas chiffrée, la retourner telle quelle
            if (!self::isEncrypted($value)) {
                return $value;
            }

            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            Log::error('Erreur lors du déchiffrement', [
                'error' => $e->getMessage(),
                'value_length' => strlen($value)
            ]);
            return $value; // Retourner la valeur telle quelle en cas d'erreur
        }
    }

    /**
     * Vérifie si une valeur est déjà chiffrée
     */
    public static function isEncrypted($value): bool
    {
        if (empty($value) || !is_string($value)) {
            return false;
        }

        try {
            // Tenter de déchiffrer pour vérifier si c'est chiffré
            Crypt::decryptString($value);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Masque une clé API pour l'affichage (garde seulement les premiers et derniers caractères)
     */
    public static function maskApiKey(?string $apiKey): string
    {
        if (empty($apiKey)) {
            return '';
        }

        $length = strlen($apiKey);
        
        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        return substr($apiKey, 0, 4) . str_repeat('*', $length - 8) . substr($apiKey, -4);
    }

    /**
     * Valide le format d'une clé API OpenAI
     */
    public static function validateOpenAIKey(?string $apiKey): bool
    {
        if (empty($apiKey)) {
            return false;
        }

        // Déchiffrer si nécessaire
        $decryptedKey = self::decrypt($apiKey);
        
        // Vérifier le format de la clé OpenAI (commence par sk- et fait au moins 20 caractères)
        return preg_match('/^sk-[a-zA-Z0-9]{20,}$/', $decryptedKey);
    }

    /**
     * Chiffre toutes les valeurs sensibles dans un tableau
     */
    public static function encryptSensitiveData(array $data): array
    {
        $encrypted = [];
        
        foreach ($data as $key => $value) {
            if (self::shouldEncrypt($key) && !empty($value)) {
                $encrypted[$key] = self::encrypt($value);
            } else {
                $encrypted[$key] = $value;
            }
        }
        
        return $encrypted;
    }

    /**
     * Déchiffre toutes les valeurs sensibles dans un tableau
     */
    public static function decryptSensitiveData(array $data): array
    {
        $decrypted = [];
        
        foreach ($data as $key => $value) {
            if (self::shouldEncrypt($key) && !empty($value)) {
                $decrypted[$key] = self::decrypt($value);
            } else {
                $decrypted[$key] = $value;
            }
        }
        
        return $decrypted;
    }
}
