# BSERP Backend - Docker & Deployment Guide

## 📋 Table of Contents
- [Prerequisites](#prerequisites)
- [Local Development](#local-development)
- [Docker Setup](#docker-setup)
- [API Documentation](#api-documentation)
- [GitHub Actions CI/CD](#github-actions-cicd)
- [Render Deployment](#render-deployment)

## 📋 Prerequisites

- Docker & Docker Compose (for local development)
- PHP 8.3+ (for local development without Docker)
- PostgreSQL 16+ 
- Composer
- Node.js (optional, for frontend building)

## 🚀 Local Development

### Without Docker

```bash
# Install dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate

# Start development server
php artisan serve
```

### With Docker Compose

```bash
# Build and start containers
docker-compose up -d

# Run migrations
docker-compose exec app php artisan migrate

# Generate Swagger docs
docker-compose exec app php artisan l5-swagger:generate

# Seed database (optional)
docker-compose exec app php artisan db:seed
```

Access the application at `http://localhost:8000`

## 🐳 Docker Setup

### Build Docker Image

```bash
docker build -t bserp-backend:latest .
```

### Run Container

```bash
docker run -d \
  --name bserp-api \
  -p 8000:80 \
  -e DB_HOST=postgres \
  -e DB_DATABASE=bserp \
  -e DB_USERNAME=postgres \
  -e DB_PASSWORD=your_password \
  bserp-backend:latest
```

### Environment Variables

Create a `.env` file with:

```env
APP_NAME=BSERP
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=pgsql
DB_HOST=postgres.render.com
DB_PORT=5432
DB_DATABASE=bserp
DB_USERNAME=postgres
DB_PASSWORD=your_secure_password

SANCTUM_STATEFUL_DOMAINS=your-domain.com
SESSION_DOMAIN=.your-domain.com

SWAGGER_GENERATE_ALWAYS=false
```

## 📚 API Documentation

### Swagger/OpenAPI

Once the application is running, access Swagger UI at:
```
http://localhost:8000/api/documentation
```

Or download the OpenAPI JSON:
```
http://localhost:8000/storage/api-docs.json
```

### Generate Swagger Documentation

```bash
# Inside container
docker-compose exec app php artisan l5-swagger:generate

# Or locally
php artisan l5-swagger:generate
```

### Document Your Endpoints

Add OpenAPI annotations to your controllers:

```php
/**
 * @OA\Get(
 *     path="/api/clients",
 *     summary="Get all clients",
 *     description="Returns a list of all clients",
 *     tags={"Clients"},
 *     security={{"sanctum":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Successful operation"
 *     ),
 *     @OA\Response(
 *         response=401,
 *         description="Unauthenticated"
 *     )
 * )
 */
public function index()
{
    // ...
}
```

## 🔄 GitHub Actions CI/CD

### Setup Secrets

Go to GitHub Repository → Settings → Secrets and add:

```
RENDER_SERVICE_ID=srv_xxxxxxxxxxxxx
RENDER_API_KEY=rnd_xxxxxxxxxxxxx
```

### Workflow Details

The workflow in `.github/workflows/deploy.yml`:

1. **Build & Test** - Runs on every push to `master`/`main`
   - Lints PHP code
   - Runs migrations on test database
   - Executes test suite
   - Builds Docker image

2. **Push to Registry** - Pushes image to GitHub Container Registry
   - Image tagged with commit SHA and `latest`
   - Implements layer caching for faster builds

3. **Deploy to Render** - Deploys to Render service
   - Triggers Render deployment via API
   - Clears full cache before deployment
   - Comments on commits with deployment status

### View Workflow Status

Go to GitHub → Actions → "Build and Deploy to Render"

## 🌐 Render Deployment

### Initial Setup

1. Create a Render account at https://render.com
2. Create a new Web Service
3. Connect your GitHub repository
4. Use the `backend/Dockerfile` as the Dockerfile

### Using render.yaml

The `render.yaml` file contains infrastructure as code for:
- Web service configuration
- PostgreSQL database
- Environment variables
- Health checks
- Auto-scaling settings

### Deploy

The application automatically deploys when you push to `master`/`main` branch via GitHub Actions.

To manually deploy:
1. Go to Render Dashboard
2. Select your service
3. Click "Manual Deploy"

### Monitor Logs

```bash
# View live logs
curl -s "https://api.render.com/v1/services/{service-id}/logs" \
  -H "authorization: Bearer {your-api-key}"
```

Or view in Render Dashboard → Logs

### Health Check

The application exposes a health check endpoint:

```bash
curl https://your-bserp-api.onrender.com/api/health
```

Response:
```json
{
  "status": "ok",
  "timestamp": "2024-04-18T12:00:00Z",
  "uptime": "Linux..."
}
```

## 🔧 Troubleshooting

### Docker issues

```bash
# Check logs
docker-compose logs app

# Rebuild without cache
docker-compose build --no-cache

# Clean up
docker-compose down -v
```

### Database migration issues

```bash
# Rollback migrations
php artisan migrate:rollback

# Refresh database
php artisan migrate:refresh --seed
```

### Render deployment fails

1. Check GitHub Actions logs for build errors
2. Verify secrets are set correctly
3. Check Render service logs
4. Verify database connection string is correct

## 📝 Notes

- The application runs on port 80 inside the container, mapped to 8000 locally
- PostgreSQL is used for production (SQLite not recommended for production)
- Supervisor manages PHP-FPM, Nginx, and Laravel workers
- Gzip compression is enabled for responses
- Security headers are configured in Nginx

## 🤝 Contributing

1. Create a feature branch
2. Make your changes
3. Push to GitHub
4. GitHub Actions will run tests and build Docker image
5. If tests pass, Render will deploy automatically

## 📞 Support

For issues or questions:
- Check logs: `docker-compose logs`
- Review Render documentation: https://render.com/docs
- Check L5-Swagger docs: https://github.com/DarkaOnline/L5-Swagger
