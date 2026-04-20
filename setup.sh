#!/bin/bash
# Quick setup script for BSERP Backend
# This script initializes everything needed for deployment

set -e

echo "🚀 BSERP Backend - Setup Script"
echo "================================"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Check prerequisites
check_prerequisites() {
    echo -e "${BLUE}📋 Checking prerequisites...${NC}"
    
    # Check Docker
    if command -v docker &> /dev/null; then
        echo -e "${GREEN}✅ Docker installed${NC}"
    else
        echo -e "${RED}❌ Docker not found. Please install Docker first.${NC}"
        exit 1
    fi
    
    # Check Composer
    if command -v composer &> /dev/null; then
        echo -e "${GREEN}✅ Composer installed${NC}"
    else
        echo -e "${RED}❌ Composer not found. Please install Composer first.${NC}"
        exit 1
    fi
    
    # Check PHP
    if command -v php &> /dev/null; then
        echo -e "${GREEN}✅ PHP installed ($(php -v | head -n 1))${NC}"
    else
        echo -e "${RED}❌ PHP not found. Please install PHP first.${NC}"
        exit 1
    fi
}

# Setup environment
setup_env() {
    echo -e "\n${BLUE}⚙️  Setting up environment...${NC}"
    
    if [ ! -f .env ]; then
        if [ -f .env.example ]; then
            cp .env.example .env
            echo -e "${GREEN}✅ Created .env from .env.example${NC}"
            echo -e "${YELLOW}⚠️  Please update .env with your configuration${NC}"
        fi
    else
        echo -e "${GREEN}✅ .env already exists${NC}"
    fi
}

# Install dependencies
install_deps() {
    echo -e "\n${BLUE}📦 Installing PHP dependencies...${NC}"
    
    if [ ! -d vendor ]; then
        composer install --no-interaction
        echo -e "${GREEN}✅ Dependencies installed${NC}"
    else
        echo -e "${GREEN}✅ Dependencies already installed${NC}"
    fi
}

# Generate app key
generate_key() {
    echo -e "\n${BLUE}🔑 Generating application key...${NC}"
    
    if grep -q "APP_KEY=$" .env || [ -z "$(grep 'APP_KEY' .env | cut -d= -f2)" ]; then
        php artisan key:generate
        echo -e "${GREEN}✅ Application key generated${NC}"
    else
        echo -e "${GREEN}✅ Application key already set${NC}"
    fi
}

# Build Docker image
build_docker() {
    echo -e "\n${BLUE}🐳 Building Docker image...${NC}"
    
    docker build -t bserp-backend:latest .
    echo -e "${GREEN}✅ Docker image built${NC}"
}

# Start containers
start_containers() {
    echo -e "\n${BLUE}▶️  Starting Docker containers...${NC}"
    
    docker-compose up -d
    echo -e "${GREEN}✅ Containers started${NC}"
    
    # Wait for containers to be ready
    echo -e "\n${BLUE}⏳ Waiting for containers to be ready...${NC}"
    sleep 5
}

# Run migrations
run_migrations() {
    echo -e "\n${BLUE}🗄️  Running database migrations...${NC}"
    
    docker-compose exec -T app php artisan migrate --force
    echo -e "${GREEN}✅ Migrations completed${NC}"
}

# Generate Swagger docs
generate_swagger() {
    echo -e "\n${BLUE}📚 Generating Swagger documentation...${NC}"
    
    docker-compose exec -T app php artisan l5-swagger:generate
    echo -e "${GREEN}✅ Swagger documentation generated${NC}"
}

# Health check
health_check() {
    echo -e "\n${BLUE}🏥 Running health check...${NC}"
    
    HEALTH=$(curl -s http://localhost:8000/api/health | jq -r '.status' 2>/dev/null || echo "error")
    
    if [ "$HEALTH" = "ok" ]; then
        echo -e "${GREEN}✅ Health check passed${NC}"
    else
        echo -e "${YELLOW}⚠️  Health check pending (container may still be starting)${NC}"
    fi
}

# Print summary
print_summary() {
    echo -e "\n${BLUE}═══════════════════════════════════════════════${NC}"
    echo -e "${GREEN}✅ Setup Complete!${NC}"
    echo -e "${BLUE}═══════════════════════════════════════════════${NC}"
    
    echo -e "\n${BLUE}📝 Important URLs:${NC}"
    echo -e "  • API Base:      ${GREEN}http://localhost:8000${NC}"
    echo -e "  • API Health:    ${GREEN}http://localhost:8000/api/health${NC}"
    echo -e "  • Swagger Docs:  ${GREEN}http://localhost:8000/api/documentation${NC}"
    
    echo -e "\n${BLUE}📚 Documentation:${NC}"
    echo -e "  • Start Here:     ${GREEN}00_START_HERE.md${NC}"
    echo -e "  • Setup Guide:    ${GREEN}SETUP_GUIDE.md${NC}"
    echo -e "  • Docker Guide:   ${GREEN}DOCKER_DEPLOYMENT.md${NC}"
    echo -e "  • Swagger Guide:  ${GREEN}SWAGGER_DOCUMENTATION.md${NC}"
    
    echo -e "\n${BLUE}🚀 Next Steps:${NC}"
    echo -e "  1. Update .env with your configuration"
    echo -e "  2. Create GitHub repository: git init && git remote add origin <your-repo>"
    echo -e "  3. Configure GitHub Actions secrets (RENDER_SERVICE_ID, RENDER_API_KEY)"
    echo -e "  4. Push to GitHub: git push -u origin master"
    echo -e "  5. Set up Render service and database"
    
    echo -e "\n${BLUE}🔧 Useful Commands:${NC}"
    echo -e "  • View logs:      ${GREEN}docker-compose logs -f app${NC}"
    echo -e "  • Run tests:      ${GREEN}docker-compose exec app php artisan test${NC}"
    echo -e "  • Database shell: ${GREEN}docker-compose exec postgres psql -U postgres -d bserp${NC}"
    echo -e "  • Stop containers:${GREEN}docker-compose down${NC}"
    
    echo -e "\n${BLUE}═══════════════════════════════════════════════${NC}"
}

# Main execution
main() {
    check_prerequisites
    setup_env
    install_deps
    generate_key
    build_docker
    start_containers
    run_migrations
    generate_swagger
    sleep 3
    health_check
    print_summary
}

# Run main function
main
