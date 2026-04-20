# CI/CD Pipeline Setup Guide

## 📋 Overview

Le pipeline CI/CD fonctionne comme suit:

```
Push to master
    ↓
GitHub Actions Tests (PHP + DB)
    ↓
Build & Push Docker Image to Docker Hub
    ↓
Trigger Render Deployment Webhook
    ↓
Render pulls latest image from Docker Hub
    ↓
Run Migrations & Start Application
```

---

## 🔧 Configuration requise

### 1. **Docker Hub Credentials**

Vous avez besoin des identifiants Docker Hub pour push les images.

#### Étapes:
1. Créer un compte sur [hub.docker.com](https://hub.docker.com) si vous n'en avez pas
2. Créer un **Personal Access Token** (PAT):
   - Allez sur [Account Settings → Security](https://hub.docker.com/settings/security)
   - Cliquez sur "New Access Token"
   - Donnez-lui un nom (ex: `github-actions`)
   - Copiez le token généré
3. Ajouter les secrets GitHub:
   - Allez sur [GitHub Settings → Secrets and variables → Actions](https://github.com/Ousmane-ndao/bserp-backend/settings/secrets/actions)
   - Cliquez sur "New repository secret"
   - Ajoutez:
     - **Nom**: `DOCKER_HUB_USERNAME`
       **Valeur**: Votre nom d'utilisateur Docker Hub
     - **Nom**: `DOCKER_HUB_PASSWORD`
       **Valeur**: Le token PAT généré ci-dessus

### 2. **Render Deployment Webhook** (Optionnel mais recommandé)

Pour que Render se déclenche automatiquement après le push Docker Hub.

#### Étapes:
1. Allez sur votre dashboard Render
2. Sélectionnez votre service `bserp-api`
3. Allez dans **Settings → Deploy Hooks**
4. Créez un nouveau deploy hook (il vous donnera une URL)
5. Copiez cette URL
6. Ajouter le secret GitHub:
   - Allez sur [GitHub Settings → Secrets](https://github.com/Ousmane-ndao/bserp-backend/settings/secrets/actions)
   - **Nom**: `RENDER_DEPLOY_HOOK`
   - **Valeur**: L'URL du webhook copied ci-dessus

> **Note**: Si vous ne configurez pas le webhook, Render détectera automatiquement la nouvelle image Docker Hub et se déploiera dans les prochaines minutes.

---

## 🚀 Comment ça fonctionne

### Commit & Push
```bash
git add .
git commit -m "feat: new feature"
git push origin master
```

### GitHub Actions Workflow
1. **Tests** (build-and-test job):
   - ✅ Checkout code
   - ✅ Setup PHP 8.3
   - ✅ Install Composer dependencies
   - ✅ Setup database (PostgreSQL)
   - ✅ Run migrations
   - ✅ Run unit tests

2. **Docker Build & Push** (push-to-docker-hub job):
   - ✅ Build Docker image
   - ✅ Push vers `docker.io/[username]/bserp-backend:latest`
   - ✅ Push vers `docker.io/[username]/bserp-backend:[commit-sha]`

3. **Trigger Render** (trigger-render-deploy job):
   - ✅ Appelle le webhook Render
   - ✅ Render déploie l'image Docker Hub

### Render Deployment
1. Pull l'image depuis Docker Hub
2. Exécute `/usr/local/bin/entrypoint.sh`:
   - Attend que PostgreSQL soit prêt
   - Lance les migrations
   - Cache la configuration (production)
   - Démarre Supervisor + Nginx

---

## 📊 Avantages de cette architecture

| Aspect | Avantage |
|--------|----------|
| **Build separation** | Tests avant le build Docker → erreurs attrapées tôt |
| **Image reuse** | Même image en production qu'en staging |
| **Fast deployments** | Pas de rebuild à chaque déploiement |
| **Automatic updates** | Render détecte l'image Docker nouvelle |
| **No secrets exposure** | Pas de clés Render/DB en GitHub secrets |
| **Audit trail** | Chaque image tagged avec commit SHA |

---

## 🔍 Monitoring

### GitHub Actions
Allez sur [Actions](https://github.com/Ousmane-ndao/bserp-backend/actions) pour voir:
- ✅ Build status
- ✅ Test results
- ✅ Docker push status
- ✅ Render webhook trigger

### Docker Hub
Allez sur [Docker Hub](https://hub.docker.com/repositories) pour voir:
- ✅ Images publiées
- ✅ Tags (latest, commit-sha)
- ✅ Image size et layers

### Render Dashboard
Allez sur [Render Dashboard](https://dashboard.render.com) pour voir:
- ✅ Deployment status
- ✅ Logs
- ✅ Running instance
- ✅ Health checks

---

## 🐛 Troubleshooting

### Tests échouent
- Check: [Actions → Latest Run](https://github.com/Ousmane-ndao/bserp-backend/actions)
- Logs: Allez voir les logs de la job `build-and-test`

### Docker push échoue
- Vérifiez: `DOCKER_HUB_USERNAME` et `DOCKER_HUB_PASSWORD` sont corrects
- Token expiré? Régénérez sur Docker Hub

### Render ne se déploie pas
- Webhook URL correcte?
- Essayez de déclencher manuellement via Render Dashboard
- Ou attendez 5 min, Render pullera l'image automatiquement

### Migration échoue au démarrage
- Check: Render logs → Application logs
- Vérifiez les variables d'environnement DB
- SSH dans Render et run `php artisan migrate --force` manuellement

---

## 📝 Workflow résumé

```yaml
trigger: push to master
  ↓
jobs:
  - build-and-test (2-3 min)
    - Tests pass? → Continue
    - Tests fail? → Stop, no deploy
  ↓
  - push-to-docker-hub (1-2 min)
    - Build & push image
  ↓
  - trigger-render-deploy (instant)
    - Call webhook
  ↓
Render (2-5 min)
  - Pull image
  - Run migrations
  - Start app
```

**Total time**: ~5-10 minutes from push to live 🚀
