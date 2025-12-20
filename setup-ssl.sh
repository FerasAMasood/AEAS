#!/bin/bash

# SSL Setup Script for DigitalOcean VPS
# This script automates the SSL certificate setup process

set -e

DOMAIN="aeas.work.gd"
EMAIL=""
PROJECT_DIR=$(pwd)

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}SSL Setup Script for ${DOMAIN}${NC}"
echo "=================================="

# Check if running as root
if [ "$EUID" -eq 0 ]; then 
   echo -e "${RED}Please do not run as root${NC}"
   exit 1
fi

# Get email
if [ -z "$EMAIL" ]; then
    read -p "Enter your email address for Let's Encrypt: " EMAIL
fi

# Check if domain resolves
echo -e "${YELLOW}Checking DNS...${NC}"
DOMAIN_IP=$(dig +short $DOMAIN | tail -n1)
if [ -z "$DOMAIN_IP" ]; then
    echo -e "${RED}Error: Domain ${DOMAIN} does not resolve. Please configure DNS first.${NC}"
    exit 1
fi

SERVER_IP=$(curl -s ifconfig.me)
if [ "$DOMAIN_IP" != "$SERVER_IP" ]; then
    echo -e "${YELLOW}Warning: Domain IP (${DOMAIN_IP}) does not match server IP (${SERVER_IP})${NC}"
    read -p "Continue anyway? (y/n) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
fi

echo -e "${GREEN}DNS check passed${NC}"

# Check if docker-compose is available
if ! command -v docker-compose &> /dev/null; then
    echo -e "${RED}Error: docker-compose not found. Please install Docker Compose.${NC}"
    exit 1
fi

# Check if services are running
echo -e "${YELLOW}Checking Docker services...${NC}"
if ! docker-compose ps | grep -q "Up"; then
    echo -e "${YELLOW}Starting Docker services...${NC}"
    docker-compose up -d
    sleep 5
fi

# Stop nginx for certificate request
echo -e "${YELLOW}Stopping nginx for certificate request...${NC}"
docker-compose stop nginx

# Request certificate
echo -e "${YELLOW}Requesting SSL certificate from Let's Encrypt...${NC}"
docker run -it --rm \
    -v "$PROJECT_DIR:/var/www/html" \
    -v certbot-etc:/etc/letsencrypt \
    -v certbot-var:/var/lib/letsencrypt \
    certbot/certbot certonly \
    --webroot \
    --webroot-path=/var/www/html/public \
    --email "$EMAIL" \
    --agree-tos \
    --no-eff-email \
    -d "$DOMAIN" \
    -d "www.$DOMAIN" || {
    echo -e "${YELLOW}Trying standalone mode...${NC}"
    docker run -it --rm \
        -p 80:80 \
        -v certbot-etc:/etc/letsencrypt \
        -v certbot-var:/var/lib/letsencrypt \
        certbot/certbot certonly \
        --standalone \
        --email "$EMAIL" \
        --agree-tos \
        --no-eff-email \
        -d "$DOMAIN" \
        -d "www.$DOMAIN"
}

# Check if certificate was created
if [ ! -d "/var/lib/docker/volumes/certbot-etc/_data/live/$DOMAIN" ]; then
    echo -e "${RED}Error: Certificate was not created. Please check the errors above.${NC}"
    exit 1
fi

echo -e "${GREEN}Certificate created successfully!${NC}"

# Backup original files
echo -e "${YELLOW}Backing up original configuration...${NC}"
if [ ! -f "docker-compose.yml.backup" ]; then
    cp docker-compose.yml docker-compose.yml.backup
fi
if [ ! -f "docker/nginx/default.conf.backup" ]; then
    cp docker/nginx/default.conf docker/nginx/default.conf.backup
fi

# Update to SSL configuration
echo -e "${YELLOW}Updating to SSL configuration...${NC}"

# Update docker-compose.yml
if [ -f "docker-compose.ssl.yml" ]; then
    cp docker-compose.ssl.yml docker-compose.yml
    echo -e "${GREEN}Updated docker-compose.yml${NC}"
else
    echo -e "${YELLOW}Warning: docker-compose.ssl.yml not found. Please update manually.${NC}"
fi

# Update nginx config
if [ -f "docker/nginx/default-ssl.conf" ]; then
    cp docker/nginx/default-ssl.conf docker/nginx/default.conf
    # Update domain in config
    sed -i "s/aeas.work.gd/$DOMAIN/g" docker/nginx/default.conf
    sed -i "s/www.aeas.work.gd/www.$DOMAIN/g" docker/nginx/default.conf
    echo -e "${GREEN}Updated nginx configuration${NC}"
else
    echo -e "${YELLOW}Warning: docker/nginx/default-ssl.conf not found. Please update manually.${NC}"
fi

# Restart services
echo -e "${YELLOW}Restarting services with SSL...${NC}"
docker-compose down
docker-compose up -d

# Wait for services to start
sleep 5

# Test SSL
echo -e "${YELLOW}Testing SSL...${NC}"
if curl -s -o /dev/null -w "%{http_code}" https://$DOMAIN | grep -q "200\|301\|302"; then
    echo -e "${GREEN}SSL is working! Visit https://${DOMAIN}${NC}"
else
    echo -e "${YELLOW}SSL test inconclusive. Please check manually.${NC}"
fi

# Update .env
echo -e "${YELLOW}Updating .env file...${NC}"
if [ -f ".env" ]; then
    sed -i "s|APP_URL=.*|APP_URL=https://$DOMAIN|g" .env
    echo -e "${GREEN}Updated APP_URL in .env${NC}"
else
    echo -e "${YELLOW}Warning: .env file not found${NC}"
fi

# Set up auto-renewal
echo -e "${YELLOW}Setting up auto-renewal...${NC}"
if [ -f "docker/certbot/renewal.sh" ]; then
    chmod +x docker/certbot/renewal.sh
    echo -e "${GREEN}Renewal script is ready${NC}"
    echo -e "${YELLOW}Add this to crontab (crontab -e):${NC}"
    echo "0 3,15 * * * cd $PROJECT_DIR && ./docker/certbot/renewal.sh >> /var/log/certbot-renewal.log 2>&1"
else
    echo -e "${YELLOW}Warning: Renewal script not found${NC}"
fi

echo ""
echo -e "${GREEN}==================================${NC}"
echo -e "${GREEN}SSL Setup Complete!${NC}"
echo -e "${GREEN}==================================${NC}"
echo ""
echo "Next steps:"
echo "1. Visit https://$DOMAIN to verify SSL"
echo "2. Test SSL rating: https://www.ssllabs.com/ssltest/analyze.html?d=$DOMAIN"
echo "3. Set up auto-renewal cron job (see above)"
echo "4. Update CORS config in config/cors.php for production"
echo ""

