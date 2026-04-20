# 🚀 Démarrage rapide - 5 étapes

Ce guide vous permettra de déployer BSERP sur Render en 5 étapes simples.

## ⏱️ Temps estimé: 15 minutes

---

## 1️⃣ Récupérer votre Docker Hub Username

**URL**: https://hub.docker.com/settings/account

Copiez votre **Username** (pas votre email)

**Exemple**: `ousmane-ndao`

---

## 2️⃣ Générer un token PAT Docker Hub

**URL**: https://hub.docker.com/settings/security

1. Cliquez **"New Access Token"**
2. Nom: `github-actions`
3. Permissions: ✅ Read, ✅ Write
4. Cliquez **"Generate"**
5. **COPIEZ LE TOKEN** immédiatement

**Format**: `dckr_pat_abc123xyz...`

---

## 3️⃣ Configurer GitHub Secrets

**URL**: https://github.com/Ousmane-ndao/bserp-backend/settings/secrets/actions

Ajoutez 2 secrets:

| Secret | Valeur |
|--------|--------|
| `DOCKER_HUB_USERNAME` | Votre Docker Hub username |
| `DOCKER_HUB_PASSWORD` | Token PAT Docker Hub |

**Processus**:
1. Cliquez **"New repository secret"**
2. Nom: `DOCKER_HUB_USERNAME`
3. Valeur: `ousmane-ndao`
4. Cliquez **"Add secret"**
5. Répétez pour `DOCKER_HUB_PASSWORD`

---

## 4️⃣ Configurer Render

### 4a. Ajouter les credentials Docker Hub

**URL**: https://dashboard.render.com

1. Cliquez votre **email** (haut droit)
2. **"Account Settings"** → **"Container Registry Credentials"**
3. Cliquez **"Add New Credential"**
4. Remplissez:
   ```
   Registry:   docker.io
   Username:   ousmane-ndao
   Password:   token PAT
   ```
5. Cliquez **"Save"**

### 4b. Créer le service web

1. Cliquez **"New"** → **"Web Service"**
2. Sélectionnez: `bserp-backend` (repo)
3. Branch: `master`
4. Cliquez **"Next"**
5. Configurez:
   ```
   Name:            bserp-api
   Environment:     Docker
   Start Command:   /usr/local/bin/entrypoint.sh
   Plan:            Standard (ou Pro)
   ```
6. Cliquez **"Create Web Service"**

### 4c. Créer la base de données

1. Cliquez **"New"** → **"PostgreSQL"**
2. Configurez:
   ```
   Name:     bserp-db
   Database: bserp
   Version:  16
   Region:   Même que le service web
   ```
3. Cliquez **"Create Database"**

---

## 5️⃣ Mettre à jour render.yaml

**Fichier**: Racine du projet → `render.yaml`

Remplacez `ousmane-ndao` par votre Docker Hub username:

```yaml
services:
  - type: web
    name: bserp-api
    runtime: docker
    image:
      fromRegistry:
        url: docker.io/ousmane-ndao/bserp-backend:latest  ← CHANGEZ ICI!
```

---

## 🎉 C'est prêt!

Faites un push:
```bash
git push origin master
```

Et regardez:
1. [GitHub Actions](https://github.com/Ousmane-ndao/bserp-backend/actions) - Build en cours ✅
2. [Docker Hub](https://hub.docker.com/repositories) - Image push-ée ✅
3. [Render Dashboard](https://dashboard.render.com) - Déploiement en cours ✅

**Total**: 5-10 minutes jusqu'à la prod! 🚀

---

## 🔗 Liens importants

- **GitHub Secrets**: https://github.com/Ousmane-ndao/bserp-backend/settings/secrets/actions
- **Docker Hub**: https://hub.docker.com/settings/account
- **Render Dashboard**: https://dashboard.render.com
- **GitHub Actions**: https://github.com/Ousmane-ndao/bserp-backend/actions

---

## ❓ Besoin d'aide?

Consultez les guides détaillés:
- [SECRETS_GITHUB.md](SECRETS_GITHUB.md) - Configuration des secrets (détaillé)
- [CI_CD_SETUP.md](CI_CD_SETUP.md) - Pipeline CI/CD complet
- [RENDER_SETUP.md](RENDER_SETUP.md) - Configuration Render (détaillé)
