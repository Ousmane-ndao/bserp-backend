# 🚀 Guide de Configuration du Pipeline CI/CD

## 📋 Vue d'ensemble

Le pipeline automatisé fonctionne de cette façon:

```
1️⃣ Développeur: git push origin master
                        ↓
2️⃣ GitHub Actions: Test + Build Docker
                        ↓
3️⃣ Docker Hub: Image push-ée
                        ↓
4️⃣ Render: Pull image + Migrations + Déploiement
```

---

## ⚡ Avant de commencer: Checklist des secrets GitHub

### Accès aux secrets GitHub
1. Allez sur: https://github.com/Ousmane-ndao/bserp-backend/settings/secrets/actions
2. Cliquez sur **"New repository secret"**

### ✅ 2 secrets OBLIGATOIRES

#### 1️⃣ `DOCKER_HUB_USERNAME`
- **Valeur**: Votre nom d'utilisateur Docker Hub
- **Exemple**: `ousmane-ndao`
- **Où trouver**: https://hub.docker.com/settings/account

#### 2️⃣ `DOCKER_HUB_PASSWORD`
- **Valeur**: Token PAT Docker Hub (PAS votre password!)
- **Comment générer**:
  1. Allez sur https://hub.docker.com/settings/security
  2. Cliquez sur **"New Access Token"**
  3. Donnez un nom: `github-actions`
  4. Sélectionnez permissions: **Read, Write**
  5. Copiez le token immédiatement (vous ne pourrez pas le relire!)

### ✅ 1 secret OPTIONNEL (déploiement auto)

#### 3️⃣ `RENDER_DEPLOY_HOOK` (recommandé mais pas obligatoire)
- **Valeur**: L'URL du webhook Render
- **Comment générer**:
  1. Allez sur Render Dashboard: https://dashboard.render.com
  2. Sélectionnez votre service **`bserp-api`**
  3. Allez à **Settings → Deploy Hooks**
  4. Cliquez **"Create Deploy Hook"**
  5. Copiez l'URL générée
- **Sans ce secret**: Render déploiera automatiquement dans 5 minutes

> **⚠️ IMPORTANT**: Sans les 2 secrets obligatoires, le pipeline échouera!

---

## 🔌 Configuration Render (Credentials Docker Hub)

Render doit pouvoir accéder à votre image Docker Hub privée.

### Étapes:
1. Allez sur [Render Dashboard](https://dashboard.render.com)
2. Cliquez sur votre **email** (en haut à droite)
3. Allez à **Account Settings → Container Registry Credentials**
4. Cliquez **"Add New Credential"**
5. Remplissez:
   - **Registry**: `docker.io`
   - **Username**: Votre Docker Hub username (ex: `ousmane-ndao`)
   - **Password**: Le token PAT Docker Hub (pas votre password)
6. Cliquez **"Save"**

### ✏️ Mettre à jour render.yaml

**Avant le déploiement**, remplacez `ousmane-ndao` par votre username:

```yaml
services:
  - type: web
    image:
      fromRegistry:
        url: docker.io/votre-username/bserp-backend:latest
        auth: private
```

---

## 📊 Pipeline détaillé: Où va quoi?

### 🔷 Phase 1: GitHub Actions Tests (votre machine virtuelle)
```
Déclenché par: git push origin master

Job: build-and-test
├─ Base de données: PostgreSQL:16 (temporaire, nettoyée après)
├─ Actions:
│  ├─ php artisan migrate --force (base test)
│  ├─ php artisan test --no-coverage
│  └─ Si échoue → STOP ❌
└─ Durée: 2-3 minutes
```

### 🟢 Phase 2: Docker Build & Push (Docker Hub)
```
Déclenché par: Tests réussis

Job: push-to-docker-hub
├─ Builds Docker image (à partir du Dockerfile)
├─ Push vers:
│  ├─ docker.io/ousmane-ndao/bserp-backend:latest
│  └─ docker.io/ousmane-ndao/bserp-backend:commit-sha
└─ Durée: 1-2 minutes
```

### 🟠 Phase 3: Trigger Render Webhook (optional)
```
Déclenché par: Docker push réussi

Job: trigger-render-deploy
├─ Appelle le webhook Render
├─ Render démarre immédiatement
└─ Durée: instantané
```

### 🟡 Phase 4: Render Deployment (production)
```
Déclenché par: Webhook OU détection automatique (5 min)

Étapes:
1. Pull image depuis Docker Hub
   └─ Utilise credentials Docker Hub configurés
2. Démarre le container
3. Exécute entrypoint.sh:
   ├─ pg_isready: attend que PostgreSQL bserp-db soit prêt
   ├─ php artisan migrate --force (base PRODUCTION)
   ├─ php artisan config:cache
   └─ supervisord: démarre Nginx + PHP-FPM
4. Health checks toutes les 30s
└─ Durée: 2-5 minutes
```

---

## 🗄️ Bases de données utilisées

| Étape | Base de données | Driver | Persistance |
|-------|-----------------|--------|-------------|
| **Tests GitHub** | `bserp_test` | PostgreSQL:16 (temporaire) | ❌ Supprimée après tests |
| **Docker Build** | *(aucune)* | - | - |
| **Production Render** | `bserp` | PostgreSQL:16 (managée) | ✅ Persistante |

**Les variables d'environnement de Render fournissent automatiquement:**
- `DB_HOST`: Hostname du serveur PostgreSQL
- `DB_USERNAME`: Identifiant
- `DB_PASSWORD`: Mot de passe

---

## 📡 Fichiers de configuration

### `.github/workflows/deploy.yml`
Définit le pipeline GitHub Actions:
- Tests (PHP + Composer + DB)
- Docker build & push
- Trigger Render webhook

### `Dockerfile`
Build l'image Docker:
- PHP 8.3 + Extensions
- Nginx + Supervisor
- Entrypoint pour migrations

### `docker/entrypoint.sh`
Script exécuté au démarrage du container:
- Attend PostgreSQL (60s timeout)
- Lance les migrations
- Demarre les services

### `render.yaml`
Configuration du service Render:
- Image Docker Hub à utiliser
- Variables d'environnement
- Base de données PostgreSQL
- Health checks

---

## 🚀 Démarrage rapide

### 1️⃣ Configurer les secrets GitHub
```
Allez sur: https://github.com/Ousmane-ndao/bserp-backend/settings/secrets/actions

Ajoutez 2 secrets:
  DOCKER_HUB_USERNAME = ousmane-ndao
  DOCKER_HUB_PASSWORD = token PAT depuis Docker Hub
```

### 2️⃣ Configurer Render (credentials)
```
1. Render Dashboard → Account Settings
2. Container Registry Credentials → Add New
3. Registry: docker.io
4. Username: ousmane-ndao
5. Password: token PAT Docker Hub
```

### 3️⃣ Mettre à jour render.yaml
```yaml
url: docker.io/ousmane-ndao/bserp-backend:latest
```

### 4️⃣ Premier déploiement
```bash
git push origin master
```

Regardez:
1. [GitHub Actions](https://github.com/Ousmane-ndao/bserp-backend/actions) (build + tests)
2. [Docker Hub](https://hub.docker.com/repositories) (image publiée)
3. [Render Dashboard](https://dashboard.render.com) (déploiement en cours)

---

## 🔍 Monitoring et logs

### GitHub Actions
- **URL**: https://github.com/Ousmane-ndao/bserp-backend/actions
- **Voir**: Tests, build Docker, trigger webhook
- **Si échec**: Cliquez sur le run pour voir les logs détaillés

### Docker Hub
- **URL**: https://hub.docker.com/repositories
- **Voir**: Images push-ées, tags, sizes
- **Si échec**: Credentials incorrects?

### Render
- **URL**: https://dashboard.render.com
- **Voir**: Deployment status, logs en live, health checks
- **Si échec**: Cliquez sur "Logs" pour déboguer

---

## ❌ Troubleshooting

### ❌ GitHub Actions: Tests échouent
**Symptôme**: Job `build-and-test` red
**Cause courante**: Erreur dans le code
**Solution**:
1. Cliquez sur le run échoué
2. Allez à `build-and-test` job
3. Cherchez `FAIL` dans les logs
4. Fixez le code, git push à nouveau

### ❌ GitHub Actions: Docker push échoue
**Symptôme**: Job `push-to-docker-hub` red
**Cause courante**: Secrets incorrects
**Solution**:
1. Vérifiez GitHub Secrets:
   - `DOCKER_HUB_USERNAME` = votre username (pas email!)
   - `DOCKER_HUB_PASSWORD` = token PAT (pas votre password!)
2. Régénérez le token si expirédocumentation
3. Git push à nouveau

### ❌ Render: Déploiement échoue
**Symptôme**: Status "Failed" dans Render Dashboard
**Cause courante**: Credentials Docker Hub manquantes
**Solution**:
1. Allez à Render Dashboard
2. Account Settings → Container Registry Credentials
3. Ajoutez Docker Hub credentials
4. Déclenchez manuellement: Settings → Manual Deploy

### ❌ Application: Migrations échouent
**Symptôme**: Render logs montrent erreur SQL
**Solution**:
1. Vérifiez render.yaml pour les env vars DB
2. SSH dans Render: `render exec bserp-api bash`
3. Testez: `php artisan migrate --force`
4. Vérifiez les dernières migrations

---

## 📈 Temps estimé du pipeline

```
GitHub Actions Tests:      2-3 minutes
  ├─ Composer install:     ~1 min
  ├─ Migrations:           ~0.3 min
  └─ Tests:                ~0.5 min

Docker Build & Push:       1-2 minutes
  ├─ Build image:          ~1 min
  └─ Push Docker Hub:      ~0.5 min

Render Deployment:         2-5 minutes
  ├─ Pull image:           ~0.5 min
  ├─ Container startup:    ~1 min
  ├─ Migrations (prod):    ~0.5 min
  └─ Health checks:        ~2 min

─────────────────────────────────────
TOTAL:                     5-10 minutes
```

---

## 📚 Ressources utiles

- **GitHub Secrets**: https://github.com/Ousmane-ndao/bserp-backend/settings/secrets/actions
- **Docker Hub**: https://hub.docker.com
- **Render Dashboard**: https://dashboard.render.com
- **GitHub Actions Logs**: https://github.com/Ousmane-ndao/bserp-backend/actions
