# 🟠 Configuration Render pour BSERP Backend

## 🎯 Objectif
Configurer Render pour:
1. Récupérer l'image Docker depuis Docker Hub
2. Gérer la base de données PostgreSQL
3. Exécuter les migrations
4. Déployer l'application en production

---

## 📋 Checklist avant le déploiement

### ✅ Étape 1: Vérifier Docker Hub Credentials dans Render

**URL**: https://dashboard.render.com

1. Cliquez sur votre **email** (en haut à droite)
2. Allez à **"Account Settings"**
3. Cliquez sur **"Container Registry Credentials"**
4. Cherchez **"docker.io"** dans la liste

**Si docker.io n'existe pas:**
1. Cliquez **"Add New Credential"**
2. Remplissez:
   ```
   Registry:  docker.io
   Username:  ousmane-ndao (votre Docker Hub username)
   Password:  token PAT (généré depuis Docker Hub)
   ```
3. Cliquez **"Save"**

> **⚠️ IMPORTANT**: Sans ces credentials, Render ne peut pas pull votre image Docker!

---

### ✅ Étape 2: Mettre à jour render.yaml

**Fichier**: `render.yaml` à la racine du projet

Remplacez `ousmane-ndao` par votre Docker Hub username:

```yaml
services:
  - type: web
    name: bserp-api
    runtime: docker
    image:
      fromRegistry:
        url: docker.io/votre-username/bserp-backend:latest  ← CHANGEZ ICI!
        auth: private
```

**Exemple**:
```yaml
url: docker.io/ousmane-ndao/bserp-backend:latest
```

---

### ✅ Étape 3: Variables d'environnement Render

**Ces variables sont AUTOMATIQUES** (linkées à la base de données):

| Variable | Source | Exemple |
|----------|--------|---------|
| `DB_HOST` | DB: bserp-db | `postgres-1234.render.com` |
| `DB_USERNAME` | DB: bserp-db | `bserp_user` |
| `DB_PASSWORD` | DB: bserp-db | `auto-generated` |
| `DB_PORT` | DB: bserp-db | `5432` |
| `DB_DATABASE` | DB: bserp-db | `bserp` |

**Vous n'avez RIEN à configurer** - Render les injecte automatiquement!

---

## 🚀 Procédure de déploiement

### Première fois: Créer le service sur Render

**URL**: https://dashboard.render.com

#### Créer un nouveau service Web
1. Cliquez **"New"** → **"Web Service"**
2. Cherchez votre repo: `bserp-backend`
3. Sélectionnez la branche: `master`
4. Cliquez **"Next"**

#### Configurer le service
```
Name:                 bserp-api
Environment:          Docker
Build Command:        (laisser vide)
Start Command:        /usr/local/bin/entrypoint.sh

Region:               Pick a region proche de vous
Scaling:              Min: 1, Max: 3
Plan:                 Standard ou Pro
```

5. Cliquez **"Create Web Service"**

#### Créer la base de données PostgreSQL
1. Cliquez **"New"** → **"PostgreSQL"**
2. Configurez:
```
Name:                 bserp-db
Database:             bserp
User:                 (Render générera)
Password:             (Render générera)
Region:               Même région que le service
Version:              16
```
3. Cliquez **"Create Database"**

#### Lier la base au service web
1. Allez au service `bserp-api`
2. **Environment** → Ajouter variable:
   ```
   DATABASE_URL=postgresql://...
   ```
   (Render vous donne cette URL quand vous linkez la DB)

---

## 🔄 Cycle de déploiement

### Option 1: Déploiement Auto (recommandé)
```
git push origin master
    ↓
GitHub Actions: Tests ✅
    ↓
Docker Hub: Image push-ée ✅
    ↓
Render: Détecte l'image (5 min auto)
    ↓
Render: Pull → Migrate → Déploie ✅
```

### Option 2: Déploiement Manuel
1. Allez sur Render Dashboard
2. Sélectionnez `bserp-api`
3. Cliquez **"Manual Deploy"**
4. Render pull la dernière image

---

## 📊 Structure du déploiement Render

```
┌─────────────────────────────────────┐
│ Service Web: bserp-api              │
├─────────────────────────────────────┤
│ Runtime: Docker                     │
│ Image:   docker.io/.../bserp-...:latest
│ Port:    80                         │
│ Start:   /usr/local/bin/entrypoint.sh
└─────────────────────────────────────┘
              ↓ (connects to)
┌─────────────────────────────────────┐
│ Database: bserp-db (PostgreSQL 16)  │
├─────────────────────────────────────┤
│ Host:     postgres-xxxx.render.com  │
│ Port:     5432                      │
│ Database: bserp                     │
│ User:     bserp_user                │
│ Password: (generated & secure)      │
└─────────────────────────────────────┘
```

---

## 🔍 Monitoring

### Logs en direct
```
Dashboard → bserp-api → Logs
```

Vous verrez:
```
[INFO] Starting entrypoint.sh...
[INFO] Waiting for database...
[OK] Database is ready!
[INFO] Running migrations...
[OK] Migrations completed
[INFO] Starting application services...
```

### Vérifier le statut
```
Dashboard → bserp-api → Status
```

Cherchez: `Live` (vert) = tout fonctionne ✅

### Health Checks
```
Dashboard → bserp-api → Health
```

La route `/health` est appelée toutes les 30 secondes.

---

## ❌ Troubleshooting

### ❌ Erreur: "Failed to pull image"
**Symptôme**: `deployment error: image pull backoff`
**Cause**: Credentials Docker Hub manquantes
**Solution**:
1. Allez à Account Settings → Container Registry Credentials
2. Vérifiez que docker.io est configuré
3. Testez les credentials
4. Cliquez **"Manual Deploy"** pour réessayer

### ❌ Erreur: "connection refused on database"
**Symptôme**: `SQLSTATE[HY000]: General error: 2006 MySQL server has gone away`
**Cause**: DATABASE_URL incorrecte ou DB pas linkée
**Solution**:
1. Allez au service `bserp-api`
2. Vérifiez que `bserp-db` est linkée dans Environment
3. Copiez l'URL de la DB depuis le service `bserp-db`
4. Testez la connexion: `psql connection-string`

### ❌ Erreur: "Migrations failed"
**Symptôme**: Logs affichent SQL error
**Solution**:
1. Allez aux logs détaillés
2. Cherchez le nom de la migration qui échoue
3. SSH dans le service:
   ```
   render exec bserp-api bash
   php artisan migrate:status
   php artisan migrate --force
   ```

### ❌ Erreur: "Health check failed"
**Symptôme**: Service se redémarre en boucle
**Cause**: L'app ne répond pas à `/health`
**Solution**:
1. Vérifiez que le service démarre: `render logs bserp-api`
2. Vérifiez le healthCheckPath dans render.yaml
3. Augmentez le timeout: `healthCheckStartupFailureThreshold: 60`

---

## 💾 Backup et Restauration

### Backup PostgreSQL
```
Render Dashboard → bserp-db → Backups
```

Les backups sont **automatiques** (daily).

### Restaurer une sauvegarde
```
Render Dashboard → bserp-db → Backups → Restore
```

---

## 🔐 Sécurité

### Secrets de production
```
render.yaml: APP_ENV = production
render.yaml: APP_DEBUG = false
```

Cela désactive les debug logs et les stack traces.

### Variables d'environnement
Les variables sont **cryptées en transit** et **au repos**.

### Accès SSH
```
render exec bserp-api bash
```

Pour déboguer directement dans le container.

---

## 📞 Support et Liens utiles

- **Render Dashboard**: https://dashboard.render.com
- **Render Docs**: https://render.com/docs
- **Docker Hub**: https://hub.docker.com
- **PostgreSQL Docs**: https://www.postgresql.org/docs/16/

---

## ✅ Checklist final

- [ ] Secrets GitHub configurés (DOCKER_HUB_USERNAME, DOCKER_HUB_PASSWORD)
- [ ] Credentials Docker Hub dans Render (Account Settings)
- [ ] render.yaml mis à jour avec votre username
- [ ] Service web créé sur Render (bserp-api)
- [ ] Base de données créée sur Render (bserp-db)
- [ ] Variables d'environnement linkées
- [ ] Webhooks GitHub configurés (optionnel)
- [ ] Premier déploiement testé (git push)
- [ ] Logs vérifiés (tout fonctionne ✅)

**Prêt pour la production!** 🚀

**Migrations échouent au démarrage?**
- Check Render logs
- Vérifiez DB credentials dans render.yaml
- SSH dans Render et testez manuellement
