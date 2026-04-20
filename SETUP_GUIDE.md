# BSERP Backend Deployment Setup

## 🚀 Quick Start Guide

### 1. Prerequisites

- GitHub Repository access
- Render account (https://render.com)
- Docker installed (for local testing)

### 2. GitHub Actions Setup

#### Add Secrets to GitHub

Go to **Repository Settings → Secrets and variables → Actions**

Add the following secrets:

```
RENDER_SERVICE_ID: srv_xxxxxxxxxxxxx
RENDER_API_KEY: rnd_xxxxxxxxxxxxx
```

**How to get these values:**

**RENDER_API_KEY:**
1. Go to Render Dashboard
2. Click your account in top right → API Keys
3. Create new API key
4. Copy the token

**RENDER_SERVICE_ID:**
1. Go to Render Dashboard
2. Select your Web Service
3. Copy the service ID from URL (srv_xxxxx)

### 3. Render Configuration

#### Option A: Manual Setup (if not using render.yaml)

1. Go to https://render.com/dashboard
2. Click "New +"
3. Select "Web Service"
4. Connect your GitHub repository
5. Configure:
   - **Name**: bserp-api
   - **Environment**: Docker
   - **Dockerfile Path**: backend/Dockerfile
   - **Port**: 80
   - **Auto-deploy**: Enable

6. Add Environment Variables:
   - `APP_ENV`: production
   - `APP_DEBUG`: false
   - `APP_KEY`: (will be set in GitHub Actions)
   - `SWAGGER_GENERATE_ALWAYS`: false
   - Database credentials from Render's PostgreSQL

#### Option B: Using render.yaml (Automatic)

1. The `backend/render.yaml` file contains all configuration
2. Push to GitHub
3. Render will automatically provision:
   - Web Service
   - PostgreSQL Database
   - Environment variables
   - Health checks

### 4. Database Migration

On first deployment, manually run migrations:

```bash
curl -X POST "https://api.render.com/deploy/srv-YOUR_SERVICE_ID?key=YOUR_API_KEY"
```

Or through Render Dashboard → Service → Shell

```bash
cd /app
php artisan migrate --force
php artisan l5-swagger:generate
```

### 5. Environment Configuration

Make sure these env vars are set in Render:

```env
APP_NAME=BSERP
APP_ENV=production
APP_DEBUG=false
DB_CONNECTION=pgsql
QUEUE_CONNECTION=database
CACHE_STORE=database
LOG_CHANNEL=stack
SWAGGER_GENERATE_ALWAYS=false
```

### 6. Local Testing with Docker

```bash
# Build image
docker build -t bserp-backend:latest ./backend

# Run with docker-compose
docker-compose up -d

# Access at http://localhost:8000
# Swagger docs at http://localhost:8000/api/documentation
```

### 7. API Documentation

After deployment, access Swagger UI:

```
https://your-service.onrender.com/api/documentation
```

## 🔄 CI/CD Pipeline

The GitHub Actions workflows automatically:

1. **On Push to master/main**:
   - Run tests
   - Build Docker image
   - Push to GitHub Container Registry
   - Deploy to Render

2. **Code Quality Checks**:
   - PHP syntax and style checks
   - Static analysis with PHPStan
   - Test coverage reports

3. **Security Checks** (Daily):
   - Dependency vulnerability scanning
   - Security audits

## 📊 Monitoring

### View Logs

**GitHub Actions:**
- Go to Actions tab
- Select workflow
- View build logs

**Render:**
- Service Dashboard → Logs
- Live logs stream

### Health Check

```bash
curl https://your-service.onrender.com/api/health
```

Response:
```json
{
  "status": "ok",
  "timestamp": "2024-04-18T12:00:00Z"
}
```

## 🔧 Troubleshooting

### Build Fails

1. Check GitHub Actions logs
2. Verify Docker credentials
3. Ensure Dockerfile is valid
4. Check environment variables

### Deployment Fails

1. Check Render logs
2. Verify database connection
3. Check if migrations ran successfully
4. Verify environment variables are set

### API Returns Errors

```bash
# Check application logs
curl https://api.render.com/v1/services/{service-id}/logs

# Test health endpoint
curl https://your-service.onrender.com/api/health

# Test API endpoint
curl https://your-service.onrender.com/api/login -X POST
```

## 📝 Next Steps

1. Configure your frontend `.env` with the API URL
2. Test API endpoints
3. Document your endpoints with OpenAPI annotations
4. Set up monitoring and alerting
5. Configure custom domain (Render → Settings)

## 🆘 Support

- Render Docs: https://render.com/docs
- GitHub Actions Docs: https://docs.github.com/en/actions
- Laravel Docs: https://laravel.com/docs
- L5-Swagger: https://github.com/DarkaOnline/L5-Swagger

## ✅ Verification Checklist

- [ ] GitHub secrets configured
- [ ] Render service created
- [ ] Database migrations run
- [ ] API health check passes
- [ ] Swagger docs accessible
- [ ] Frontend can connect to API
- [ ] Tests passing
- [ ] Logs monitored
