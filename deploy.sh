#!/bin/bash

# Configuration
PROJECT_PATH="~/domains/lumichat.site/public_html/staging"
BRANCH="staging"

set -e
export PATH=$PATH:/usr/local/bin:/usr/bin:/bin

cd $PROJECT_PATH || { echo "$(date): Failed to enter project path"; exit 1; }

# Fetch updates
git fetch

LOCAL=$(git rev-parse HEAD)
REMOTE=$(git rev-parse origin/$BRANCH)

if [ "$LOCAL" != "$REMOTE" ]; then
    echo "$(date): New update detected. Deploying..."

    # Reset and pull
    git pull origin $BRANCH

    # Install JS dependencies
    npm install

    # Build assets with memory limit for Three.js/esbuild
    echo "$(date): Building assets..."
    NODE_OPTIONS=--max-old-space-size=2048 npm run build

    # Laravel database and cache
    echo "$(date): Running migrations..."
    php artisan migrate --force
    php artisan optimize:clear

    echo "$(date): Deploy completed successfully."
else
    echo "$(date): No changes detected."
fi