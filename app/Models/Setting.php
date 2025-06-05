<?php

namespace App\Models;

use App\Services\EncryptionService;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'description'
    ];

    protected $casts = [
        'value' => 'string',
    ];

    /**
     * Récupérer une valeur de paramètre
     */
    public static function get(string $key, $default = null)
    {
        $setting = static::where('key', $key)->first();

        if (!$setting) {
            return $default;
        }

        // Si c'est une clé sensible, déchiffrer directement la valeur stockée
        if (EncryptionService::shouldEncrypt($key) && !empty($setting->value)) {
            return EncryptionService::decrypt($setting->value);
        }

        // Sinon, utiliser le casting normal
        return static::castValue($setting->value, $setting->type);
    }

    /**
     * Définir une valeur de paramètre
     */
    public static function set(string $key, $value, string $type = 'string', string $description = null): void
    {
        // Chiffrer si c'est une clé sensible
        if (EncryptionService::shouldEncrypt($key) && !empty($value)) {
            $value = EncryptionService::encrypt($value);
            // Pour les valeurs chiffrées, on stocke directement la chaîne chiffrée
            $preparedValue = $value;
        } else {
            $preparedValue = static::prepareValue($value, $type);
        }

        static::updateOrCreate(
            ['key' => $key],
            [
                'value' => $preparedValue,
                'type' => $type,
                'description' => $description
            ]
        );
    }

    /**
     * Préparer la valeur pour le stockage
     */
    protected static function prepareValue($value, string $type): string
    {
        switch ($type) {
            case 'boolean':
                return $value ? '1' : '0';
            case 'integer':
                return (string) intval($value);
            case 'json':
                return json_encode($value);
            default:
                return (string) $value;
        }
    }

    /**
     * Convertir la valeur selon son type
     */
    protected static function castValue(string $value, string $type)
    {
        switch ($type) {
            case 'boolean':
                return $value === '1';
            case 'integer':
                return intval($value);
            case 'json':
                return json_decode($value, true);
            default:
                return $value;
        }
    }
}
