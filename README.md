# Crypto-Tracker
Project for tracking crypto investors' accounts, their transactions, deals, purchases, sales. Both with regular crypto coins and meme coins.

A Laravel 12 + Breeze application, containerized with Docker (PHP-FPM, Nginx, PostgreSQL, Redis) and powered by Vite + React.

---

## 📝 Prerequisites

- PHP 8.4.10-NTS, Composer (Latest: v2.8.9), Laravel 12
- Docker & Docker Compose installed
- OpenSSL (for self-signed SSL certificate)
- Windows 11 / WSL2 recommended for best filesystem performance

---

## ⚙️ Setup on WSL

1. Install Composer on WSL
   ```bash
   sudo apt update
   sudo apt install php-cli php-curl php-xml php-mbstring zip unzip
   curl -sS https://getcomposer.org/installer | php
   sudo mv composer.phar /usr/local/bin/composer
   ```

2. Run composer install inside laravel project root
   ```bash
   cd crypto-tracker/
   composer install
   ```

3. **Copy & configure your environment file**
   ```bash
   cd ../
   cp .env.dev .env
   cp .env crypto-tracker/.env
   ```
   Then open .env and enter your generated **APP_KEY**: 
   ```bash
   php artisan key:generate --show
   ```
   And fill in any missing values (DB credentials, mail, etc.)


4. **Generate a self-signed SSL certificate**
    - Windows:
        ```bash
        openssl req -x509 -nodes -days 365 -newkey rsa:2048 -keyout certs\localhost.key -out certs\localhost.crt -subj "/CN=localhost"
        ```
    - Linux:
        ```bash
        mkdir -p certs
        openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
        -keyout certs/localhost.key \
        -out certs/localhost.crt \
        -subj "/CN=localhost"
        ```
5. **Build & Start all services**
    ```bash
   docker compose up -d --build
   ```
6. Give permissions
   ```bash
   sudo chmod -R 777 crypto-tracker/storage crypto-tracker/bootstrap/cache
   ```
7. **Run Vite in Laravel app root**
    ```bash
    cd ./crypto-tracker
   ```
   ```bash
   npm install
   ```
    ```bash
   npm run dev
   ```
---

## ✅ Quick Health Checks
- Artisan CLI & version
    ```bash
  docker-compose exec app php artisan --version
- Environment
    ```bash
  docker-compose exec app php artisan env
- PostgreSQL & migrations status
    ```bash
  docker-compose exec app php artisan migrate:status

---

## 🚀 Initialize Database
Run all outstanding migrations:
```bash
docker-compose exec app php artisan migrate
```

Run all seeds:
```bash
docker-compose exec app php artisan db:seed --force
```

Seed only a specific class:
```bash
docker-compose exec app php artisan db:seed --class=LocalAdminSeeder --force
```

---

## 🔄 Check Redis Connection
Use Tinker to verify caching via Redis:
```bash
docker-compose exec app php artisan tinker
>>> cache()->put('test_key', 'hello', 60);
>>> cache()->get('test_key'); // Should return "hello"
```

---

## 🔧 Frontend (Vite + React)

- Dev server runs on http://localhost:5173
- HMR over WSS enabled via self-signed certs 
- Assets are injected automatically by the Laravel Breeze plugin

Open your browser at:
- *App:* https://localhost
- *Vite:* http://localhost:5173

---

## 📚 Useful Commands
```bash
# Stop & remove all containers
docker-compose down

# Stop & remove all containers with database tome
docker-compose down -v

# Rebuild PHP service only
docker-compose up -d --build app

# View logs (follow)
docker-compose logs -f app
docker-compose logs -f nginx
docker-compose logs -f worker
```