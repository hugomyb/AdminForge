# AdminForge

AdminForge est un outil d'administration de bases de données MySQL moderne et puissant, construit avec Laravel 12 et FilamentPHP 3. Il offre une interface intuitive pour explorer, gérer et interroger vos bases de données, avec des fonctionnalités d'IA optionnelles.

## 🚀 Fonctionnalités Implémentées

### ✅ Phase 1 : Exploration des bases de données
- **Explorateur de bases de données** : Visualisation de toutes les bases de données disponibles
- **Navigation intuitive** : Interface claire avec statistiques en temps réel
- **Détails des tables** : Structure complète, colonnes, types, index et clés étrangères
- **Visualisation des données** : Affichage paginé des données avec navigation facile

### ✅ Phase 2 : Gestion des données (CRUD)
- **Gestionnaire de tables** : Interface complète pour gérer les données
- **CRUD complet** : Ajouter, modifier, supprimer des lignes
- **Pagination avancée** : Navigation efficace dans les grandes tables
- **Validation automatique** : Respect des contraintes de base de données

### ✅ Phase 3 : Requêtes SQL + IA
- **SQL Playground** : Éditeur SQL avec exécution en temps réel
- **Historique des requêtes** : Sauvegarde automatique des requêtes exécutées
- **Intégration OpenAI** (optionnelle) :
  - Génération de requêtes SQL à partir de descriptions en langage naturel
  - Amélioration et optimisation de requêtes existantes
  - Explication détaillée des requêtes complexes
- **Page de paramètres** : Configuration facile de l'API OpenAI

## 🔑 Accès à l'application

- **URL** : https://adminforge.test (via Valet Linux)
- **Utilisateur admin** : admin@adminforge.test
- **Mot de passe** : password

## 🤖 Configuration de l'IA (Optionnelle)

1. Accédez à la page **Paramètres** dans l'interface
2. Activez les fonctionnalités IA
3. Saisissez votre clé API OpenAI (obtenue sur https://platform.openai.com/api-keys)
4. Testez la connexion avec le bouton "Tester OpenAI"

## 📖 Guide d'utilisation

### 1. Exploration des bases de données
- Accédez à **"Bases de Données"** dans le menu principal
- Visualisez toutes les bases disponibles avec leurs statistiques
- Cliquez sur une base pour explorer ses tables
- Utilisez **"Gérer"** pour accéder à l'interface CRUD d'une table

### 2. SQL Playground
- Accédez à **"SQL Playground"** dans le menu
- Sélectionnez une base de données dans la liste déroulante
- Utilisez les requêtes d'exemple ou écrivez votre propre SQL
- Exécutez avec **Ctrl+Enter** ou le bouton "Exécuter"
- Consultez l'historique des requêtes en bas de page

### 3. Fonctionnalités IA (si configurées)
- **Assistant IA** : Décrivez ce que vous voulez faire en français
- **Améliorer** : Optimise automatiquement vos requêtes existantes
- **Expliquer** : Obtient une explication détaillée d'une requête

### 4. Gestion des données
- Depuis la page d'une base, cliquez sur **"Gérer"** pour une table
- **Ajouter** : Bouton "Ajouter une ligne" en haut à droite
- **Modifier** : Bouton "Modifier" sur chaque ligne
- **Supprimer** : Bouton "Supprimer" avec confirmation

## 🏗️ Architecture technique

```
app/
├── Filament/Pages/          # Pages de l'interface admin
│   ├── DatabaseExplorer.php # Explorateur de bases
│   ├── DatabaseDetail.php   # Détails d'une base
│   ├── TableData.php        # Visualisation des données
│   ├── TableManager.php     # Gestionnaire CRUD
│   ├── SqlPlayground.php    # Éditeur SQL
│   └── Settings.php         # Configuration
├── Services/                # Services métier
│   ├── DatabaseExplorerService.php # Exploration DB
│   └── OpenAIService.php    # Intégration IA
└── ...

resources/views/filament/pages/ # Vues Blade des pages
config/adminforge.php          # Configuration de l'app
```

## 🔧 Configuration

### Base de données (`.env`)
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=adminforge
DB_USERNAME=root
DB_PASSWORD=password
```

### Paramètres AdminForge (`config/adminforge.php`)
- `openai.enabled` : Active/désactive les fonctionnalités IA
- `openai.api_key` : Clé API OpenAI
- `openai.model` : Modèle OpenAI à utiliser (gpt-3.5-turbo, gpt-4)
- `sql.max_query_length` : Longueur maximale des requêtes
- `sql.enable_query_history` : Active l'historique des requêtes
- `database.max_rows_per_page` : Pagination par défaut

## 🚧 Phases suivantes (planifiées)

### Phase 4 : Sécurité, Permissions & Logs
- Système de permissions granulaires par base/table
- Logs d'audit de toutes les actions
- Authentification multi-facteurs

### Phase 5 : Import/Export & Migrations
- Import/export de données (CSV, JSON, SQL)
- Générateur de migrations Laravel
- Sauvegarde et restauration automatiques

### Phase 6 : Monitoring & Performance
- Monitoring des performances en temps réel
- Analyse des requêtes lentes
- Suggestions d'optimisation automatiques

## 📝 Notes techniques

- **Framework** : Laravel 12 avec FilamentPHP 3
- **Base de données** : MySQL avec support des connexions multiples
- **IA** : Intégration OpenAI GPT-3.5/GPT-4
- **Interface** : Tailwind CSS avec composants Filament
- **Temps réel** : Livewire pour les interactions dynamiques
- **Sécurité** : Authentification FilamentPHP par défaut

## 🎯 Fonctionnalités clés implémentées

✅ **Exploration complète** : Toutes les bases, tables, colonnes, index
✅ **CRUD intuitif** : Ajout, modification, suppression avec validation
✅ **SQL Playground** : Éditeur avec historique et exécution
✅ **IA intégrée** : Génération, amélioration et explication de requêtes
✅ **Interface moderne** : Design responsive avec Filament/Tailwind
✅ **Configuration flexible** : Paramètres via interface admin

---

**AdminForge** - Votre assistant intelligent pour l'administration MySQL 🚀

*Développé avec Laravel 12, FilamentPHP 3 et l'IA OpenAI*
