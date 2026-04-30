#!/bin/bash

# 1. Copy .env if it doesn't exist
if [ ! -f .env ]; then
    echo "Creating .env file..."
    cp .env.example .env
fi

# 2. Start the containers
echo "Starting Docker containers..."
docker compose up -d

# 3. Fix Git ownership (Prevents the "dubious ownership" warning)
docker exec banking_app git config --global --add safe.directory /var/www

# 4. Install dependencies
echo "Installing Composer dependencies..."
docker exec banking_app composer install

# 5. Generate App Key
echo "Generating Laravel app key..."
docker exec banking_app php artisan key:generate

# 6. FIX PERMISSIONS
echo "Setting folder permissions..."
docker exec -u root banking_app bash -c "
    mkdir -p storage/api-docs &&
    chown -R www-data:www-data storage bootstrap/cache &&
    chmod -R 775 storage bootstrap/cache
"

# 7. Run Migrations
echo "Waiting for database to stabilize..."
sleep 5
echo "Running database migrations..."
docker exec banking_app php artisan migrate


echo "----------------------------------------------------"
echo "Setup complete! API is running at http://localhost:8000"
echo "Check Swagger at http://localhost:8000/api/documentation"
