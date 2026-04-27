# 🚀 Déployer BSERP sur Render avec l'image Docker Hub

Ce guide explique comment déployer directement depuis l'image Docker Hub vers Render en utilisant `render.yaml`.

---

## 📋 Prérequis

✅ Image Docker pushée sur Docker Hub: `docker.io/votre-username/bserp-backend:latest`

Vérifiez ici: https://hub.docker.com/repositories

---

## 🎯 Processus simplifié en 4 étapes

### **Étape 1: Créer la Base de Données PostgreSQL**

C'est le **seul** élément à créer manuellement sur le dashboard Render.

1. Allez à https://dashboard.render.com
2. Cliquez **"New"** → **"PostgreSQL"**
3. Remplissez:
   ```
   Name:      bserp-db
   Database:  bserp
   User:      postgres
   Version:   16
   Region:    Votre région
   ```
4. Cliquez **"Create Database"**
5. ⏳ Attendez le status **"Available"** (3-5 minutes)

**💾 Sauvegardez**: L'ID de la base (ex: `postgres-xxxxx`) pour l'étape 2

---

### **Étape 2: Mettre à jour render.yaml avec votre username**

Ouvrez `render.yaml` et remplacez `ousmane-ndao` par votre Docker Hub username:

```yaml
services:
  - type: web
    name: bserp-api
    runtime: docker
    image:
      fromRegistry:
        url: docker.io/VOTRE-USERNAME/bserp-backend:latest  ← ☝️ CHANGEZ ICI!
        auth: private
    startCommand: /usr/local/bin/entrypoint.sh
    
    envVars:
      - key: DB_HOST
        fromDatabase:
          name: bserp-db  ← Doit correspondre au nom créé à l'étape 1
          property: host
      - key: DB_USERNAME
        fromDatabase:
          name: bserp-db
          property: username
      - key: DB_PASSWORD
        fromDatabase:
          name: bserp-db
          property: password
```

Commitez et pushez:
```bash
git add render.yaml
git commit -m "chore: update render.yaml with Docker Hub username"
git push origin master
```

---

### **Étape 3: Configurer les Credentials Docker Hub sur Render (Si image privée)**

Si votre image Docker Hub est **PRIVÉE**:

1. Allez à https://dashboard.render.com/account/container-registry
2. Cliquez **"Add New Credential"**
3. Remplissez:
   ```
   Registry:   docker.io
   Username:   votre-docker-hub-username
   Password:   votre-PAT-token-docker-hub
   ```
4. Cliquez **"Save"**

⚠️ **Si votre image est PUBLIQUE**, ignorez cette étape (rendez l'image publique sur Docker Hub si nécessaire)

---

### **Étape 4: Créer le Service Web via render.yaml**

#### **Option A: Via le Dashboard Render (Recommandé)**

1. Allez à https://dashboard.render.com
2. Cliquez **"New"** → **"Web Service"**
3. Sélectionnez votre repo `bserp-backend`
4. Branch: `master`
5. Cliquez **"Next"**
6. Render va detecter `render.yaml` et configurer automatiquement
7. Vérifiez les paramètres et cliquez **"Create Web Service"**
8. ⏳ Attendez le status **"Live"** (5-10 minutes)

#### **Option B: Via le CLI Render**

Installez le CLI Render:
```bash
npm install -g render-cli
```

Puis:
```bash
render login
render deploy --service-name bserp-api
```

---

## 🔄 Comment Render déploie l'image Docker Hub

```
Vous pushez du code
    ↓
GitHub Actions build & push l'image vers Docker Hub
    ↓
Render **automatiquement** pulle l'image :latest
    ↓
Render redéploie l'application (5 min plus tard)
```

### **Configuration automatique:**
- ✅ Render lit `render.yaml`
- ✅ Pulle l'image `docker.io/your-username/bserp-backend:latest`
- ✅ Crée les variables d'environnement depuis la BD PostgreSQL
- ✅ Démarre le conteneur avec `startCommand`

---

## 🔐 Sécurité: Auth privée vs publique

### **Image PRIVÉE (Plus sécurisé)**
```yaml
image:
  fromRegistry:
    url: docker.io/your-username/bserp-backend:latest
    auth: private  ← Nécessite credentials Docker Hub sur Render
```

**À faire:**
1. Rendez l'image PRIVÉE sur Docker Hub
2. Configurez credentials sur Render (Étape 3)

### **Image PUBLIQUE (Plus simple)**
```yaml
image:
  fromRegistry:
    url: docker.io/your-username/bserp-backend:latest
    auth: public  ← Pas besoin de credentials
```

**À faire:**
1. Rendez l'image PUBLIQUE sur Docker Hub
2. Changez `auth: private` → `auth: public` dans render.yaml

---

## 📊 Architecture complète

```
                    GitHub
                      ↑
                  git push
                      ↑
        ┌─────────────────────────┐
        │ GitHub Actions Workflow │
        │  (deploy.yml)           │
        │  • build-and-test ✅    │
        │  • push-to-docker-hub   │
        │  • trigger-render       │
        └─────────────────────────┘
                      ↓
              Docker Hub Registry
          (bserp-backend:latest)
                      ↓
        ┌─────────────────────────┐
        │   Render Platform       │
        │  • Pull image :latest   │
        │  • Start container      │
        │  • Run migrations       │
        │  • Live on web          │
        └─────────────────────────┘
                      ↓
        PostgreSQL (bserp-db)
```

---

## ✅ Checklist avant de déployer

- [ ] Image Docker pushée vers Docker Hub: `docker.io/votre-username/bserp-backend:latest`
- [ ] Base de données PostgreSQL créée sur Render (status: Available)
- [ ] `render.yaml` mis à jour avec votre username Docker Hub
- [ ] Credentials Docker Hub ajoutés sur Render (si image privée)
- [ ] Service Web créé via render.yaml
- [ ] Service Web en status: **"Live"**

---

## 🚀 Tester le déploiement complet

Faites un push pour déclencher le pipeline:

```bash
git push origin master
```

Regardez:

1. **[GitHub Actions](https://github.com/Ousmane-ndao/bserp-backend/actions)**
   - build-and-test ✅ (1-2 min)
   - push-to-docker-hub ✅ (1-2 min)
   - trigger-render-deploy ✅ (instant)

2. **[Docker Hub](https://hub.docker.com/repositories)**
   - Image `bserp-backend:latest` mise à jour ✅

3. **[Render Dashboard](https://dashboard.render.com)**
   - Service `bserp-api` en cours de redéploiement 🔄
   - Status devient **"Live"** après 5-10 min ✅

4. **[Votre app](https://bserp-api.onrender.com)** (remplacez par votre URL)
   - Accédez à l'application en production! 🎉

---

## 🐛 Troubleshooting

### **Problème: Render ne pulle pas la nouvelle image**

**Solution:** Allez dans le Service Web → **"Manual Deploy"** → Cliquez **"Deploy latest"**

### **Problème: "Failed to pull image"**

**Causes possibles:**
1. ❌ Credentials Docker Hub manquants sur Render
   → Allez à **Account Settings** → **Container Registry Credentials**

2. ❌ Image introuvable sur Docker Hub
   → Vérifiez que l'image existe: https://hub.docker.com/repositories

3. ❌ Username incorrect dans render.yaml
   → Vérifiez: `docker.io/your-username/bserp-backend:latest`

### **Problème: Migrations échouent au démarrage**

Vérifiez les logs Render:
1. Service → **"Logs"**
2. Cherchez les erreurs de migration
3. Le fichier `docker/entrypoint.sh` devrait afficher les erreurs

---

## 📞 Liens utiles

- **Render Dashboard**: https://dashboard.render.com
- **Docker Hub Repositories**: https://hub.docker.com/repositories
- **GitHub Actions**: https://github.com/Ousmane-ndao/bserp-backend/actions
- **render.yaml Documentation**: https://render.com/docs/infrastructure-as-code

---

## 🎉 Résumé

1. ✅ Image Docker built & pushed automatiquement via GitHub Actions
2. ✅ Render pulle l'image depuis Docker Hub automatiquement
3. ✅ Base de données PostgreSQL managée par Render
4. ✅ Application en production en 5-10 minutes après chaque push
5. ✅ Zéro configuration manuelle une fois render.yaml configuré

**C'est tout!** 🚀
