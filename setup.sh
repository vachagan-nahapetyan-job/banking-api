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
# Ensure the specific directory exists first
docker exec banking_app mkdir -p storage/api-docs
# Grant ownership of everything in storage to the web server
docker exec banking_app chown -R www-data:www-data storage bootstrap/cache
# Ensure it is writable
docker exec banking_app chmod -R 775 storage bootstrap/cache

# 7. Run Migrations
echo "Running database migrations..."
docker exec banking_app php artisan migrate --force

# 8. Generate Swagger Documentation
echo "Generating API documentation..."
docker exec banking_app php artisan l5-swagger:generate

echo "----------------------------------------------------"
echo "Setup complete! API is running at http://localhost:8000"
echo "Check Swagger at http://localhost:8000/api/documentation"
