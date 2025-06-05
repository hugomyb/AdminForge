<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CleanConfigApiKeys extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'adminforge:clean-config-keys {--force : Force cleaning without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove API keys from configuration files for security';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🧹 Nettoyage des clés API dans les fichiers de configuration...');

        $configFiles = [
            'config/adminforge.php',
            'config/services.php',
            'config/openai.php',
        ];

        $cleaned = 0;
        $warnings = [];

        foreach ($configFiles as $configFile) {
            if (!file_exists($configFile)) {
                continue;
            }

            $this->info("📁 Vérification de {$configFile}...");

            $content = file_get_contents($configFile);
            $originalContent = $content;

            // Rechercher les clés API potentielles
            $patterns = [
                '/[\'"]api_key[\'"][\s]*=>[\s]*[\'"][sk-][a-zA-Z0-9_-]+[\'"]/',
                '/[\'"]openai_api_key[\'"][\s]*=>[\s]*[\'"][sk-][a-zA-Z0-9_-]+[\'"]/',
                '/[\'"]token[\'"][\s]*=>[\s]*[\'"][a-zA-Z0-9_-]{20,}[\'"]/',
                '/[\'"]secret[\'"][\s]*=>[\s]*[\'"][a-zA-Z0-9_-]{20,}[\'"]/',
            ];

            $foundKeys = false;
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $content)) {
                    $foundKeys = true;
                    $warnings[] = "⚠️  Clé API détectée dans {$configFile}";
                    break;
                }
            }

            if ($foundKeys) {
                if (!$this->option('force') && !$this->confirm("Nettoyer les clés dans {$configFile} ?")) {
                    $this->line("⏭️  Ignoré: {$configFile}");
                    continue;
                }

                // Remplacer les clés par null
                foreach ($patterns as $pattern) {
                    $content = preg_replace($pattern, "'api_key' => null", $content);
                }

                // Sauvegarder le fichier nettoyé
                file_put_contents($configFile, $content);
                $cleaned++;
                $this->line("✅ Nettoyé: {$configFile}");
            } else {
                $this->line("✅ Propre: {$configFile}");
            }
        }

        $this->newLine();

        if (!empty($warnings)) {
            $this->warn('⚠️  AVERTISSEMENTS DE SÉCURITÉ:');
            foreach ($warnings as $warning) {
                $this->warn($warning);
            }
            $this->newLine();
        }

        $this->info("📊 Résumé:");
        $this->info("   • Fichiers nettoyés: {$cleaned}");
        $this->info("   • Avertissements: " . count($warnings));

        if ($cleaned > 0) {
            $this->newLine();
            $this->info("🔒 IMPORTANT:");
            $this->info("   • Les clés API ont été supprimées des fichiers de configuration");
            $this->info("   • Utilisez les paramètres de l'application pour configurer les clés");
            $this->info("   • Les clés sont maintenant stockées de manière chiffrée en base de données");
        }

        $this->newLine();
        $this->info("🎉 Nettoyage terminé!");
        return 0;
    }
}
