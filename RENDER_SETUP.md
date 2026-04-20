# Render Setup Instructions

## 🔐 Configuration des credentials Docker Hub dans Render

Render doit avoir accès à Docker Hub pour pull votre image privée.

### Étapes:

1. **Allez sur [Render Dashboard](https://dashboard.render.com)**

2. **Paramètres du compte** → **Container Registry Credentials**

3. **Ajouter des credentials**:
   - **Registry**: `docker.io`
   - **Username**: Votre username Docker Hub
   - **Password**: Votre token PAT Docker Hub (pas votre mot de passe)

4. **Mettre à jour render.yaml** avec votre username:
   ```yaml
   image:
     fromRegistry:
       url: docker.io/[VOTRE_USERNAME]/bserp-backend:latest
       auth: private
   ```
   Remplacez `[VOTRE_USERNAME]` par votre username Docker Hub

5. **Déployer** vers Render

---

## 🚀 Workflow complet

```
1. Push to GitHub (master)
   ↓
2. GitHub Actions:
   - Tests PHP + DB
   - Build Docker image
   - Push vers Docker Hub:latest
   ↓
3. Trigger Render webhook (optionnel)
   ↓
4. Render:
   - Pull image depuis Docker Hub
   - Run migrations (entrypoint.sh)
   - Déploiement en live
```

---

## 📝 Checklist finale

- [ ] Secrets GitHub configurés:
  - [ ] `DOCKER_HUB_USERNAME`
  - [ ] `DOCKER_HUB_PASSWORD`
  - [ ] `RENDER_DEPLOY_HOOK` (optionnel)

- [ ] Credentials Docker Hub dans Render:
  - [ ] Registry: `docker.io`
  - [ ] Username & Token ajoutés

- [ ] render.yaml mis à jour:
  - [ ] Changez `ousmane-ndao` par votre username
  - [ ] Image URL correcte: `docker.io/[username]/bserp-backend:latest`

- [ ] Premiere déploiement:
  - [ ] Faites un push
  - [ ] Vérifiez GitHub Actions
  - [ ] Vérifiez Docker Hub (image push-ée)
  - [ ] Vérifiez Render (déploiement en cours)

---

## 🐛 Troubleshooting

**GitHub Actions échoue?**
- Check logs: [Actions](https://github.com/Ousmane-ndao/bserp-backend/actions)
- Erreur `composer install`? → Vérifiez le working-directory
- Tests échouent? → Check logs détaillés

**Docker Hub ne reçoit pas l'image?**
- Vérifiez `DOCKER_HUB_PASSWORD` (doit être un token PAT, pas le password)
- Check GitHub Actions logs

**Render ne déploie pas?**
- Credentials Docker Hub configurés dans Render?
- Image URL correcte dans render.yaml?
- Check Render logs

**Migrations échouent au démarrage?**
- Check Render logs
- Vérifiez DB credentials dans render.yaml
- SSH dans Render et testez manuellement
