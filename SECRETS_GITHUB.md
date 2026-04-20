# 🔐 GitHub Secrets - Configuration obligatoire

## 📍 Accès aux Secrets

**URL**: https://github.com/Ousmane-ndao/bserp-backend/settings/secrets/actions

## 📋 2 Secrets OBLIGATOIRES

Ces 2 secrets sont **ESSENTIELS** pour que le pipeline fonctionne.

### 1️⃣ `DOCKER_HUB_USERNAME`

**Qu'est-ce que c'est?** Votre identifiant Docker Hub

**Comment le récupérer?**
1. Allez sur: https://hub.docker.com/settings/account
2. Regardez le champ **"Username"**
3. Copiez votre username

**Exemple**:
```
DOCKER_HUB_USERNAME = ousmane-ndao
```

**Ne pas confondre avec:**
- ❌ Email Docker Hub
- ❌ Nom complet
- ✅ Username (affiché sur votre profil)

---

### 2️⃣ `DOCKER_HUB_PASSWORD`

**Qu'est-ce que c'est?** Un token d'accès personnel (PAT) Docker Hub

**⚠️ ATTENTION**: Ce n'est PAS votre mot de passe Docker Hub!

**Comment générer le token?**
1. Allez sur: https://hub.docker.com/settings/security
2. Cliquez **"New Access Token"**
3. Donnez un nom: `github-actions`
4. Dans "Permissions", sélectionnez:
   - ✅ **Read**
   - ✅ **Write**
5. Cliquez **"Generate"**
6. **COPIEZ IMMÉDIATEMENT** le token (vous ne pourrez pas le relire!)
7. Collez dans GitHub Secrets

**Exemple**:
```
DOCKER_HUB_PASSWORD = dckr_pat_abc123xyz789...
```

**Ne pas confondre avec:**
- ❌ Votre password Docker Hub
- ✅ Token PAT (commence par `dckr_pat_`)

---

## 🎁 1 Secret OPTIONNEL (mais recommandé)

### 3️⃣ `RENDER_DEPLOY_HOOK`

**Qu'est-ce que c'est?** URL du webhook Render (déploiement auto)

**Est-ce obligatoire?** Non. Render déploiera automatiquement dans 5 minutes sans ce secret.

**Comment le récupérer?**
1. Allez sur: https://dashboard.render.com
2. Sélectionnez votre service **`bserp-api`**
3. Allez à **Settings** → **Deploy Hooks**
4. Cliquez **"Create Deploy Hook"**
5. Vous recevrez une URL de la forme:
   ```
   https://api.render.com/deploy/srv-...?key=...
   ```
6. Copiez l'URL entière dans GitHub Secrets

**Exemple**:
```
RENDER_DEPLOY_HOOK = https://api.render.com/deploy/srv-abc123/...
```

---

## 📝 Comment ajouter les secrets

### Étape 1: Aller aux Secrets GitHub
```
GitHub → Settings → Secrets and variables → Actions
```

### Étape 2: Ajouter un nouveau secret
1. Cliquez **"New repository secret"**
2. Remplissez:
   - **Name**: Le nom du secret (ex: `DOCKER_HUB_USERNAME`)
   - **Secret**: La valeur (ex: `ousmane-ndao`)
3. Cliquez **"Add secret"**

### Étape 3: Répéter pour tous les secrets
```
DOCKER_HUB_USERNAME = ousmane-ndao
DOCKER_HUB_PASSWORD = dckr_pat_abc123...
RENDER_DEPLOY_HOOK = https://api.render.com/deploy/srv-...  (optionnel)
```

---

## ✅ Vérifier que les secrets sont ajoutés

1. Allez sur: https://github.com/Ousmane-ndao/bserp-backend/settings/secrets/actions
2. Vous devez voir:
   ```
   ✓ DOCKER_HUB_USERNAME
   ✓ DOCKER_HUB_PASSWORD
   ✓ RENDER_DEPLOY_HOOK (si configuré)
   ```

---

## 🚀 Une fois les secrets configurés

1. **Faites un push**:
   ```bash
   git push origin master
   ```

2. **Regardez les [GitHub Actions](https://github.com/Ousmane-ndao/bserp-backend/actions)**:
   - Devrait voir un workflow `Build, Push to Docker Hub & Deploy to Render`
   - S'il échoue → Les secrets sont mauvais

---

## 🐛 Si les secrets sont mauvais

### Symptôme: ❌ "authentication failed" ou "invalid credentials"

**Solutions**:
1. Vérifiez que vous avez copié:
   - `DOCKER_HUB_USERNAME` → username (pas email)
   - `DOCKER_HUB_PASSWORD` → token PAT (commence par `dckr_pat_`)
2. Régénérez le token si douté
3. Mettez à jour les secrets GitHub
4. Faites un nouveau push: `git push origin master`

---

## 🔒 Sécurité des secrets

- ✅ Les secrets sont **chiffrés** dans GitHub
- ✅ Ils ne s'affichent jamais en clair dans les logs
- ✅ Ils ne sont accessibles que par le workflow
- ✅ Vous pouvez les révoquer à tout moment

---

## 📋 Checklist avant de commencer

- [ ] J'ai mon username Docker Hub (`DOCKER_HUB_USERNAME`)
- [ ] J'ai généré un token PAT Docker Hub (`DOCKER_HUB_PASSWORD`)
- [ ] J'ai configuré les 2 secrets GitHub
- [ ] J'ai optionnellement récupéré l'URL du webhook Render
- [ ] Tous les secrets sont listés sur https://github.com/Ousmane-ndao/bserp-backend/settings/secrets/actions

**Prêt?** Faites un `git push` et regardez le magic! ✨
