<?php

namespace App\Services;

use App\Models\Setting;
use App\Services\EncryptionService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIService
{
    protected string $apiKey;
    protected string $model;
    protected bool $enabled;

    public function __construct()
    {
        // Récupérer les paramètres depuis la base de données (déjà déchiffrés)
        $this->apiKey = Setting::get('openai_api_key', '') ?? '';
        $this->model = Setting::get('openai_model', 'gpt-3.5-turbo') ?? 'gpt-3.5-turbo';
        $aiEnabled = Setting::get('ai_enabled', false);

        $this->enabled = $aiEnabled && !empty($this->apiKey);
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Générer une requête SQL à partir d'une description en langage naturel
     */
    public function generateSqlQuery(string $description, array $tableStructure = []): array
    {
        if (!$this->isEnabled()) {
            return [
                'success' => false,
                'message' => 'Les fonctionnalités IA ne sont pas activées. Veuillez configurer votre clé API OpenAI dans les paramètres.',
                'query' => ''
            ];
        }

        try {
            $systemPrompt = $this->buildSystemPrompt($tableStructure);
            $userPrompt = "Génère une requête SQL pour: {$description}";

            $response = $this->callOpenAI([
                [
                    'role' => 'system',
                    'content' => $systemPrompt
                ],
                [
                    'role' => 'user',
                    'content' => $userPrompt
                ]
            ]);

            if ($response['success']) {
                $content = $response['content'];
                
                // Extraire la requête SQL du contenu
                $sqlQuery = $this->extractSqlFromResponse($content);
                
                return [
                    'success' => true,
                    'query' => $sqlQuery,
                    'explanation' => $content,
                    'message' => 'Requête générée avec succès'
                ];
            }

            return $response;

        } catch (\Exception $e) {
            Log::error('Erreur OpenAI generateSqlQuery: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Erreur lors de la génération: ' . $e->getMessage(),
                'query' => ''
            ];
        }
    }

    /**
     * Améliorer une requête SQL existante
     */
    public function improveSqlQuery(string $query, string $goal = 'optimiser'): array
    {
        if (!$this->isEnabled()) {
            return [
                'success' => false,
                'message' => 'Les fonctionnalités IA ne sont pas activées.',
                'improved_query' => $query
            ];
        }

        try {
            $systemPrompt = "Tu es un expert en optimisation SQL. Analyse la requête fournie et propose une version améliorée.";
            $userPrompt = "Améliore cette requête SQL pour {$goal}:\n\n{$query}";

            $response = $this->callOpenAI([
                [
                    'role' => 'system',
                    'content' => $systemPrompt
                ],
                [
                    'role' => 'user',
                    'content' => $userPrompt
                ]
            ]);

            if ($response['success']) {
                $content = $response['content'];
                $improvedQuery = $this->extractSqlFromResponse($content);
                
                return [
                    'success' => true,
                    'improved_query' => $improvedQuery,
                    'explanation' => $content,
                    'message' => 'Requête améliorée avec succès'
                ];
            }

            return $response;

        } catch (\Exception $e) {
            Log::error('Erreur OpenAI improveSqlQuery: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Erreur lors de l\'amélioration: ' . $e->getMessage(),
                'improved_query' => $query
            ];
        }
    }

    /**
     * Expliquer une requête SQL
     */
    public function explainSqlQuery(string $query): array
    {
        if (!$this->isEnabled()) {
            return [
                'success' => false,
                'message' => 'Les fonctionnalités IA ne sont pas activées.',
                'explanation' => ''
            ];
        }

        try {
            $systemPrompt = "Tu es un expert SQL. Explique les requêtes SQL de manière claire et détaillée en français.";
            $userPrompt = "Explique cette requête SQL:\n\n{$query}";

            $response = $this->callOpenAI([
                [
                    'role' => 'system',
                    'content' => $systemPrompt
                ],
                [
                    'role' => 'user',
                    'content' => $userPrompt
                ]
            ]);

            if ($response['success']) {
                return [
                    'success' => true,
                    'explanation' => $response['content'],
                    'message' => 'Explication générée avec succès'
                ];
            }

            return $response;

        } catch (\Exception $e) {
            Log::error('Erreur OpenAI explainSqlQuery: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Erreur lors de l\'explication: ' . $e->getMessage(),
                'explanation' => ''
            ];
        }
    }

    /**
     * Appel générique à l'API OpenAI
     */
    protected function callOpenAI(array $messages, int $maxTokens = 1000): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->model,
                'messages' => $messages,
                'max_tokens' => $maxTokens,
                'temperature' => 0.1,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                return [
                    'success' => true,
                    'content' => $data['choices'][0]['message']['content'] ?? '',
                    'usage' => $data['usage'] ?? []
                ];
            }

            return [
                'success' => false,
                'message' => 'Erreur API OpenAI: ' . $response->body()
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur de connexion: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Construire le prompt système avec la structure des tables
     */
    protected function buildSystemPrompt(array $tableStructure): string
    {
        $prompt = "Tu es un expert en bases de données MySQL. Tu génères des requêtes SQL précises et optimisées.";
        
        if (!empty($tableStructure)) {
            $prompt .= "\n\nStructure des tables disponibles:\n";
            foreach ($tableStructure as $table => $columns) {
                $prompt .= "\nTable: {$table}\n";
                foreach ($columns as $column) {
                    $prompt .= "- {$column['name']} ({$column['type']})";
                    if ($column['key'] === 'PRI') $prompt .= " [PRIMARY KEY]";
                    if (!$column['null']) $prompt .= " [NOT NULL]";
                    $prompt .= "\n";
                }
            }
        }
        
        $prompt .= "\n\nRègles:\n";
        $prompt .= "- Génère uniquement du SQL MySQL valide\n";
        $prompt .= "- Utilise les noms de tables et colonnes exacts fournis\n";
        $prompt .= "- Ajoute des commentaires pour expliquer les parties complexes\n";
        $prompt .= "- Optimise pour la performance\n";
        $prompt .= "- Retourne la requête entre ```sql et ``` pour faciliter l'extraction\n";
        
        return $prompt;
    }

    /**
     * Extraire la requête SQL de la réponse OpenAI
     */
    protected function extractSqlFromResponse(string $content): string
    {
        // Chercher du SQL entre ```sql et ```
        if (preg_match('/```sql\s*(.*?)\s*```/s', $content, $matches)) {
            return trim($matches[1]);
        }
        
        // Chercher du SQL entre ``` et ```
        if (preg_match('/```\s*(.*?)\s*```/s', $content, $matches)) {
            $sql = trim($matches[1]);
            // Vérifier que ça ressemble à du SQL
            if (preg_match('/^\s*(SELECT|INSERT|UPDATE|DELETE|CREATE|ALTER|DROP|SHOW|DESCRIBE)/i', $sql)) {
                return $sql;
            }
        }
        
        // Si aucun bloc de code trouvé, chercher des mots-clés SQL
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            $line = trim($line);
            if (preg_match('/^\s*(SELECT|INSERT|UPDATE|DELETE|CREATE|ALTER|DROP|SHOW|DESCRIBE)/i', $line)) {
                return $line;
            }
        }
        
        // En dernier recours, retourner le contenu nettoyé
        return trim($content);
    }
}
