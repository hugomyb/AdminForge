<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Services\EncryptionService;
use App\Models\Setting;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Chiffrer toutes les clés API existantes en base de données
        $sensitiveSettings = Setting::whereIn('key', [
            'openai_api_key',
            'openai_token',
        ])->get();

        foreach ($sensitiveSettings as $setting) {
            if (!empty($setting->value) && !EncryptionService::isEncrypted($setting->value)) {
                // Chiffrer la valeur directement en base sans passer par les mutateurs
                $encryptedValue = EncryptionService::encrypt($setting->value);

                // Mettre à jour directement en base de données
                \DB::table('settings')
                    ->where('id', $setting->id)
                    ->update([
                        'value' => json_encode($encryptedValue),
                        'description' => $setting->description . ' (chiffrée)',
                        'updated_at' => now()
                    ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Déchiffrer toutes les clés API en base de données
        $sensitiveSettings = Setting::whereIn('key', [
            'openai_api_key',
            'openai_token',
        ])->get();

        foreach ($sensitiveSettings as $setting) {
            if (!empty($setting->value) && EncryptionService::isEncrypted($setting->value)) {
                // Déchiffrer la valeur
                $decryptedValue = EncryptionService::decrypt($setting->value);

                // Mettre à jour directement en base de données
                \DB::table('settings')
                    ->where('id', $setting->id)
                    ->update([
                        'value' => json_encode($decryptedValue),
                        'description' => str_replace(' (chiffrée)', '', $setting->description),
                        'updated_at' => now()
                    ]);
            }
        }
    }
};
