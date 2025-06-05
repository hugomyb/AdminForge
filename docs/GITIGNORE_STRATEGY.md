# Stratégie GitIgnore - AdminForge

## Vue d'ensemble

AdminForge implémente une stratégie de sécurité multi-niveaux avec des fichiers `.gitignore` spécialisés pour protéger les données sensibles et maintenir un repository propre.

## 🔒 Sécurité - Priorité Absolue

### Données Sensibles Protégées

- **Clés API** : `*api_key*`, `*_key*`, `*.key`
- **Secrets** : `*secret*`, `*_secret*`, `secrets.*`
- **Tokens** : `*token*`, `*_token*`, `tokens.*`
- **Mots de passe** : `*password*`, `*credential*`
- **Certificats** : `*.pem`, `*.p12`, `*.pfx`

### Fichiers de Configuration

- **Environnement** : `.env*`, `*.local.php`
- **Sauvegardes** : `*.backup`, `*.bak`, `*.old`
- **Base de données** : `*.sql`, `*.dump`, `*.sqlite*`

## 📁 Structure des GitIgnore

### 1. **Racine du Projet** (`.gitignore`)
```
# Fichier principal avec toutes les règles Laravel + AdminForge
- Variables d'environnement
- Dépendances (vendor/, node_modules/)
- Cache et logs
- Fichiers sensibles globaux
- OS et IDE
```

### 2. **Storage** (`storage/.gitignore`)
```
# Ignore tout le contenu utilisateur
- Logs applicatifs
- Cache framework
- Sessions
- Uploads utilisateurs
- Exports SQL
- Sauvegardes base de données
```

### 3. **Configuration** (`config/.gitignore`)
```
# Protection des fichiers de config
- Sauvegardes de configuration
- Overrides locaux
- Fichiers temporaires
- Patterns de clés API
```

### 4. **Base de Données** (`database/.gitignore`)
```
# Protection des données
- Fichiers SQLite
- Dumps SQL
- Exports de données
- Sauvegardes
```

### 5. **Public** (`public/.gitignore`)
```
# Assets et uploads
- Uploads utilisateurs
- Assets générés
- Fichiers temporaires
- Symlinks storage
```

### 6. **Tests** (`tests/.gitignore`)
```
# Résultats de tests
- Coverage reports
- Cache PHPUnit
- Bases de test
- Logs de test
```

### 7. **Documentation** (`docs/.gitignore`)
```
# Documentation générée
- Builds
- PDFs générés
- Fichiers temporaires
```

## 🛡️ Patterns de Sécurité

### Patterns Universels (dans tous les .gitignore)
```gitignore
# Clés et secrets
*api_key*
*secret*
*token*
*password*
*credential*
*.key
*.pem
```

### Patterns Spécifiques par Contexte

#### Configuration
```gitignore
*.local.php
*_local.php
*.backup
production.php
```

#### Base de Données
```gitignore
*.sql
*.dump
*_production.sql
*_backup.sql
```

#### Storage
```gitignore
*.log
backup_*.sql
export_*.csv
```

## 📋 Fichiers .gitkeep

Pour maintenir la structure des dossiers vides :

- `storage/logs/.gitkeep`
- `storage/app/sql-exports/.gitkeep`
- `storage/app/database-backups/.gitkeep`
- `storage/app/local/.gitkeep`

## ✅ Bonnes Pratiques

### 1. **Vérification Avant Commit**
```bash
# Vérifier qu'aucun fichier sensible n'est stagé
git status
git diff --cached

# Rechercher des patterns sensibles
grep -r "api_key\|secret\|token" --include="*.php" .
```

### 2. **Nettoyage Périodique**
```bash
# Nettoyer les fichiers de config
php artisan adminforge:clean-config-keys

# Vérifier les .gitignore
git check-ignore -v <fichier>
```

### 3. **Audit de Sécurité**
```bash
# Rechercher des fichiers potentiellement sensibles
find . -name "*api_key*" -o -name "*secret*" -o -name "*token*"
find . -name "*.key" -o -name "*.pem" -o -name "*.p12"
```

## 🚨 Que Faire en Cas de Fuite

### Si une clé API a été commitée :

1. **Immédiatement** :
   ```bash
   # Révoquer la clé API chez le fournisseur
   # Générer une nouvelle clé
   ```

2. **Nettoyer l'historique** :
   ```bash
   # Utiliser git-filter-branch ou BFG Repo-Cleaner
   git filter-branch --force --index-filter \
   'git rm --cached --ignore-unmatch config/adminforge.php' \
   --prune-empty --tag-name-filter cat -- --all
   ```

3. **Forcer la mise à jour** :
   ```bash
   git push origin --force --all
   git push origin --force --tags
   ```

## 🔍 Vérification de la Configuration

### Commandes Utiles

```bash
# Lister tous les .gitignore
find . -name ".gitignore" -type f

# Vérifier qu'un fichier est ignoré
git check-ignore storage/logs/laravel.log

# Voir les fichiers trackés dans un dossier
git ls-files storage/

# Rechercher des patterns sensibles
grep -r "sk-" --include="*.php" . || echo "Aucune clé OpenAI trouvée"
```

### Test de Sécurité

```bash
# Créer un fichier test avec une fausse clé
echo "api_key=sk-test123" > test_secret.txt

# Vérifier qu'il est ignoré
git status | grep test_secret.txt || echo "✅ Fichier correctement ignoré"

# Nettoyer
rm test_secret.txt
```

## 📚 Ressources

- [Documentation Git Ignore](https://git-scm.com/docs/gitignore)
- [Laravel Security Best Practices](https://laravel.com/docs/security)
- [OWASP Secure Coding Practices](https://owasp.org/www-project-secure-coding-practices-quick-reference-guide/)

---

**⚠️ RAPPEL** : La sécurité est un processus continu. Révisez régulièrement vos `.gitignore` et auditez votre repository pour détecter d'éventuelles fuites de données sensibles.
