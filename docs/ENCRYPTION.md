# Système de Chiffrement des Clés API

## Vue d'ensemble

AdminForge implémente un système de chiffrement automatique pour toutes les clés API sensibles stockées en base de données. Ce système garantit que les clés API (notamment OpenAI) sont chiffrées de manière transparente et ne sont JAMAIS stockées en clair dans les fichiers de configuration.

## ⚠️ IMPORTANT - Sécurité

**Les clés API ne sont plus stockées dans les fichiers de configuration** pour des raisons de sécurité :
- ❌ **Avant** : Clés en clair dans `config/adminforge.php`
- ✅ **Maintenant** : Clés chiffrées uniquement en base de données
- 🔒 **Avantage** : Aucun risque de fuite via les fichiers de config ou Git

## Fonctionnalités

### 🔐 Chiffrement Automatique

- **Chiffrement transparent** : Les clés API sont automatiquement chiffrées lors de la sauvegarde
- **Déchiffrement automatique** : Les clés sont déchiffrées lors de la récupération
- **Validation** : Vérification du format des clés OpenAI avant sauvegarde
- **Masquage** : Affichage masqué des clés dans l'interface (ex: `sk-****...****1234`)

### 🛡️ Clés Sensibles Protégées

Le système chiffre automatiquement les clés contenant :
- `openai_api_key`
- `openai_token`
- `api_key`
- `token`
- `secret`

## Architecture

### Service de Chiffrement (`EncryptionService`)

```php
// Chiffrer une valeur
$encrypted = EncryptionService::encrypt($apiKey);

// Déchiffrer une valeur
$decrypted = EncryptionService::decrypt($encrypted);

// Vérifier si une clé doit être chiffrée
$shouldEncrypt = EncryptionService::shouldEncrypt('openai_api_key');

// Masquer une clé pour l'affichage
$masked = EncryptionService::maskApiKey($apiKey); // sk-****...****1234

// Valider une clé OpenAI
$isValid = EncryptionService::validateOpenAIKey($apiKey);
```

### Modèle Setting Modifié

Le modèle `Setting` a été modifié pour :
- Chiffrer automatiquement les valeurs sensibles lors de la sauvegarde
- Déchiffrer automatiquement lors de la récupération
- Utiliser les méthodes statiques `get()` et `set()`

```php
// Sauvegarde automatiquement chiffrée
Setting::set('openai_api_key', 'sk-1234567890abcdef');

// Récupération automatiquement déchiffrée
$apiKey = Setting::get('openai_api_key');
```

## Utilisation

### Configuration dans l'Interface

1. Accédez aux **Paramètres** via le menu utilisateur
2. Activez les **fonctionnalités IA**
3. Saisissez votre **clé API OpenAI**
4. La clé sera automatiquement chiffrée lors de la sauvegarde

### Commandes Artisan

#### Chiffrer les clés existantes
```bash
# Chiffrer toutes les clés API existantes
php artisan adminforge:encrypt-api-keys

# Forcer le chiffrement sans confirmation
php artisan adminforge:encrypt-api-keys --force
```

#### Migration automatique
```bash
# Exécuter la migration pour chiffrer les clés existantes
php artisan migrate
```

## Sécurité

### Méthode de Chiffrement

- **Algorithme** : AES-256-CBC (via Laravel Crypt)
- **Clé de chiffrement** : Utilise `APP_KEY` de Laravel
- **Sécurité** : Chiffrement symétrique avec clé dérivée

### Bonnes Pratiques

1. **APP_KEY sécurisée** : Assurez-vous que votre `APP_KEY` est forte et sécurisée
2. **Sauvegarde** : Sauvegardez votre `APP_KEY` - sans elle, les données chiffrées sont irrécupérables
3. **Rotation** : Changez régulièrement vos clés API
4. **Environnement** : Ne stockez jamais de clés en clair dans le code source

### Détection Automatique

Le système détecte automatiquement :
- Si une valeur est déjà chiffrée (évite le double chiffrement)
- Le format des clés OpenAI (validation `sk-...`)
- Les clés sensibles par leur nom

## Dépannage

### Erreurs Communes

#### "Erreur lors du déchiffrement"
- Vérifiez que `APP_KEY` n'a pas changé
- La clé peut être corrompue en base de données

#### "Format de clé API OpenAI invalide"
- Les clés OpenAI doivent commencer par `sk-`
- Vérifiez que la clé est complète et valide

#### "Clé déjà chiffrée"
- Le système évite automatiquement le double chiffrement
- Aucune action requise

### Vérification du Statut

```php
// Vérifier si une valeur est chiffrée
$isEncrypted = EncryptionService::isEncrypted($value);

// Tester la connexion OpenAI
// Utilisez le bouton "Tester OpenAI" dans les paramètres
```

## Migration depuis l'Ancien Système

Si vous migrez depuis une version sans chiffrement :

1. **Sauvegardez** votre base de données
2. **Exécutez** la migration : `php artisan migrate`
3. **Vérifiez** que les clés fonctionnent toujours
4. **Testez** la connexion OpenAI dans les paramètres

## Développement

### Ajouter de Nouvelles Clés Sensibles

Pour protéger de nouvelles clés, modifiez `EncryptionService::SENSITIVE_KEYS` :

```php
private const SENSITIVE_KEYS = [
    'openai_api_key',
    'openai_token',
    'api_key',
    'new_sensitive_key', // Ajouter ici
];
```

### Tests

```php
// Tester le chiffrement
$original = 'sk-1234567890abcdef';
$encrypted = EncryptionService::encrypt($original);
$decrypted = EncryptionService::decrypt($encrypted);
assert($original === $decrypted);
```

## Support

Pour toute question ou problème lié au chiffrement :
1. Vérifiez les logs Laravel (`storage/logs/laravel.log`)
2. Testez la connexion OpenAI via l'interface
3. Consultez cette documentation
