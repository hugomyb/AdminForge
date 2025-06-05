<?php

namespace Database\Seeders;

use App\Models\SavedQuery;
use App\Models\User;
use Illuminate\Database\Seeder;

class SavedQuerySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Récupérer le premier utilisateur ou en créer un
        $user = User::first();
        
        if (!$user) {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => bcrypt('password'),
            ]);
        }

        // Créer quelques requêtes sauvegardées d'exemple
        $queries = [
            [
                'name' => 'Liste des utilisateurs',
                'query' => 'SELECT * FROM users ORDER BY created_at DESC LIMIT 10',
                'database_name' => 'adminforge',
            ],
            [
                'name' => 'Compter les utilisateurs',
                'query' => 'SELECT COUNT(*) as total_users FROM users',
                'database_name' => 'adminforge',
            ],
            [
                'name' => 'Utilisateurs récents',
                'query' => 'SELECT name, email, created_at FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)',
                'database_name' => 'adminforge',
            ],
            [
                'name' => 'Structure de la table users',
                'query' => 'DESCRIBE users',
                'database_name' => 'adminforge',
            ],
        ];

        foreach ($queries as $queryData) {
            SavedQuery::create([
                'name' => $queryData['name'],
                'query' => $queryData['query'],
                'database_name' => $queryData['database_name'],
                'user_id' => $user->id,
            ]);
        }
    }
}
