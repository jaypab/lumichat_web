#!/bin/bash

set -e
export PATH=$PATH:/usr/local/bin:/usr/bin:/bin

cd ~/domains/lumichat.site/public_html/staging || exit

git fetch

LOCAL=$(git rev-parse HEAD)
REMOTE=$(git rev-parse origin/staging)

if [ "$LOCAL" != "$REMOTE" ]; then
    echo "New update detected. Deploying..."

    git pull origin staging

    npm install
    npm run build

    php artisan migrate
    php artisan optimize:clear

    echo "Deploy completed."
else
    echo "No changes."
fi