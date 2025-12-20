#!/bin/bash

# Certbot renewal script for Let's Encrypt certificates
# This script should be run via cron job twice daily

echo "Starting certificate renewal check at $(date)"

# Renew certificates
docker-compose -f docker-compose.ssl.yml run --rm certbot renew

# Reload nginx to use new certificates
docker-compose -f docker-compose.ssl.yml exec nginx nginx -s reload

echo "Certificate renewal check completed at $(date)"

