<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SecurityAudit extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'adminforge:security-audit {--fix : Automatically fix some issues}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Audit the repository for security issues and sensitive data leaks';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Audit de sécurité AdminForge...');
        $this->newLine();

        $issues = 0;
        $warnings = 0;
        $fixed = 0;

        // 1. Vérifier les fichiers .gitignore
        $issues += $this->checkGitignoreFiles();

        // 2. Rechercher des clés API dans les fichiers
        $issues += $this->scanForApiKeys();

        // 3. Vérifier les permissions des fichiers
        $warnings += $this->checkFilePermissions();

        // 4. Vérifier la configuration
        $issues += $this->checkConfiguration();

        // 5. Vérifier les variables d'environnement
        $warnings += $this->checkEnvironmentVariables();

        $this->newLine();
        $this->info('📊 Résumé de l\'audit:');
        $this->info("   • Problèmes critiques: {$issues}");
        $this->info("   • Avertissements: {$warnings}");

        if ($this->option('fix')) {
            $this->info("   • Corrections automatiques: {$fixed}");
        }

        if ($issues > 0) {
            $this->error('❌ Des problèmes de sécurité ont été détectés!');
            return 1;
        } elseif ($warnings > 0) {
            $this->warn('⚠️  Des avertissements ont été émis.');
            return 0;
        } else {
            $this->info('✅ Aucun problème de sécurité détecté!');
            return 0;
        }
    }

    private function checkGitignoreFiles(): int
    {
        $this->info('🔍 Vérification des fichiers .gitignore...');
        $issues = 0;

        $requiredGitignores = [
            '.gitignore',
            'storage/.gitignore',
            'storage/app/.gitignore',
            'storage/logs/.gitignore',
            'config/.gitignore',
            'database/.gitignore',
        ];

        foreach ($requiredGitignores as $gitignore) {
            if (!File::exists($gitignore)) {
                $this->error("❌ Fichier manquant: {$gitignore}");
                $issues++;
            } else {
                $this->line("✅ Présent: {$gitignore}");
            }
        }

        return $issues;
    }

    private function scanForApiKeys(): int
    {
        $this->info('🔍 Recherche de clés API dans les fichiers...');
        $issues = 0;

        $sensitivePatterns = [
            'sk-[a-zA-Z0-9]{20,}' => 'Clé OpenAI',
            'api_key.*=.*[\'"][^\'"\s]{20,}[\'"]' => 'Clé API générique',
            'secret.*=.*[\'"][^\'"\s]{20,}[\'"]' => 'Secret',
            'token.*=.*[\'"][^\'"\s]{20,}[\'"]' => 'Token',
        ];

        $excludeDirs = ['vendor', 'node_modules', '.git', 'storage/logs'];
        $includeExtensions = ['php', 'js', 'env', 'json', 'yaml', 'yml'];

        foreach ($sensitivePatterns as $pattern => $description) {
            $command = "grep -r -E '{$pattern}' . " .
                      "--include='*.{" . implode(',', $includeExtensions) . "}' " .
                      implode(' ', array_map(fn($dir) => "--exclude-dir={$dir}", $excludeDirs)) .
                      " 2>/dev/null || true";

            $output = shell_exec($command);

            if (!empty(trim($output))) {
                $this->error("❌ {$description} détectée:");
                $lines = explode("\n", trim($output));
                foreach (array_slice($lines, 0, 5) as $line) {
                    $this->line("   {$line}");
                }
                if (count($lines) > 5) {
                    $this->line("   ... et " . (count($lines) - 5) . " autres occurrences");
                }
                $issues++;
            }
        }

        if ($issues === 0) {
            $this->line('✅ Aucune clé API détectée dans les fichiers');
        }

        return $issues;
    }

    private function checkFilePermissions(): int
    {
        $this->info('🔍 Vérification des permissions des fichiers...');
        $warnings = 0;

        $sensitiveFiles = [
            '.env' => '600',
            'storage' => '755',
            'bootstrap/cache' => '755',
        ];

        foreach ($sensitiveFiles as $file => $expectedPerm) {
            if (File::exists($file)) {
                $actualPerm = substr(sprintf('%o', fileperms($file)), -3);
                if ($actualPerm !== $expectedPerm) {
                    $this->warn("⚠️  {$file}: permissions {$actualPerm} (recommandé: {$expectedPerm})");
                    $warnings++;
                } else {
                    $this->line("✅ {$file}: permissions correctes ({$actualPerm})");
                }
            }
        }

        return $warnings;
    }

    private function checkConfiguration(): int
    {
        $this->info('🔍 Vérification de la configuration...');
        $issues = 0;

        // Vérifier que les clés ne sont pas dans les fichiers de config
        $configFiles = ['config/adminforge.php', 'config/services.php'];

        foreach ($configFiles as $configFile) {
            if (File::exists($configFile)) {
                $content = File::get($configFile);
                if (preg_match('/[\'"]api_key[\'"][\s]*=>[\s]*[\'"]sk-[a-zA-Z0-9]+[\'"]/', $content)) {
                    $this->error("❌ Clé API détectée dans {$configFile}");
                    $issues++;
                } else {
                    $this->line("✅ {$configFile}: pas de clé API en dur");
                }
            }
        }

        return $issues;
    }

    private function checkEnvironmentVariables(): int
    {
        $this->info('🔍 Vérification des variables d\'environnement...');
        $warnings = 0;

        $requiredEnvVars = ['APP_KEY', 'DB_PASSWORD'];
        $sensitiveEnvVars = ['OPENAI_API_KEY', 'API_KEY'];

        foreach ($requiredEnvVars as $var) {
            if (empty(env($var))) {
                $this->warn("⚠️  Variable d'environnement manquante: {$var}");
                $warnings++;
            }
        }

        foreach ($sensitiveEnvVars as $var) {
            if (!empty(env($var))) {
                $this->warn("⚠️  Variable d'environnement sensible détectée: {$var} (utilisez plutôt la base de données)");
                $warnings++;
            }
        }

        return $warnings;
    }
}
