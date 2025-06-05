<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\EncryptionService;
use Illuminate\Console\Command;

class EncryptApiKeys extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'adminforge:encrypt-api-keys {--force : Force encryption without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Encrypt all API keys stored in the database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔐 Chiffrement des clés API...');

        // Récupérer toutes les clés sensibles
        $sensitiveKeys = [
            'openai_api_key',
            'openai_token',
        ];

        $settings = Setting::whereIn('key', $sensitiveKeys)->get();

        if ($settings->isEmpty()) {
            $this->info('✅ Aucune clé API trouvée en base de données.');
            return 0;
        }

        $this->info("📋 {$settings->count()} clé(s) API trouvée(s).");

        if (!$this->option('force') && !$this->confirm('Voulez-vous chiffrer ces clés ?')) {
            $this->info('❌ Opération annulée.');
            return 0;
        }

        $encrypted = 0;
        $alreadyEncrypted = 0;
        $errors = 0;

        foreach ($settings as $setting) {
            try {
                if (empty($setting->value)) {
                    continue;
                }

                // Vérifier si déjà chiffrée
                if (EncryptionService::isEncrypted($setting->value)) {
                    $alreadyEncrypted++;
                    $this->line("⚠️  {$setting->key}: déjà chiffrée");
                    continue;
                }

                // Chiffrer la valeur
                $encryptedValue = EncryptionService::encrypt($setting->value);

                // Mettre à jour en base
                \DB::table('settings')
                    ->where('id', $setting->id)
                    ->update([
                        'value' => json_encode($encryptedValue),
                        'updated_at' => now()
                    ]);

                $encrypted++;
                $this->line("✅ {$setting->key}: chiffrée avec succès");

            } catch (\Exception $e) {
                $errors++;
                $this->error("❌ Erreur pour {$setting->key}: " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info("📊 Résumé:");
        $this->info("   • Clés chiffrées: {$encrypted}");
        $this->info("   • Déjà chiffrées: {$alreadyEncrypted}");
        $this->info("   • Erreurs: {$errors}");

        if ($errors > 0) {
            $this->error("⚠️  Des erreurs sont survenues lors du chiffrement.");
            return 1;
        }

        $this->info("🎉 Chiffrement terminé avec succès!");
        return 0;
    }
}
