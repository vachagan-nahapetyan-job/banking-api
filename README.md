# 🏦 Banking ATM REST API
 
A Laravel-based REST API simulating ATM operations — withdraw, balance check, and transaction history — with Laravel Sanctum authentication and Swagger/OpenAPI documentation.
 
---
 
## 🛠 Tech Stack
 
- **PHP** 8.2
- **Laravel** 10
- **MySQL** 8.0
- **Docker** + Nginx
- **Laravel Sanctum** — token-based auth
- **L5-Swagger** — OpenAPI documentation
---
 
## 📁 Project Structure
 
```
banking-api/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/
│   │   │       ├── AuthController.php   # Register & Login
│   │   │       └── AtmController.php    # Withdraw, Balance, Transactions
│   │   └── Requests/
│   │       ├── RegisterRequest.php      # Validation for register
│   │       └── WithdrawRequest.php      # Validation for withdraw
│   └── Models/
│       ├── User.php                     # User with balance, pin, currency
│       └── Transaction.php              # Withdrawal records
├── database/
│   └── migrations/                      # DB schema
├── routes/
│   └── api.php                          # All API routes
├── docker/
│   ├── nginx/
│   │   └── default.conf                 # Nginx config
│   └── php/
│       └── Dockerfile                   # PHP 8.3-fpm image
└── docker-compose.yml                   # Docker services
```
 
---
 
## ⚙️ Installation & Setup
 
### Requirements
- Docker Desktop installed and running
- Composer (optional — only needed outside Docker)
### Step 1 — Clone the project
```bash
git clone https://github.com/vachagan-nahapetyan-job/banking-api.git
cd banking-api
```
 
### Step 2 — Copy environment file
```bash
cp .env.example .env
```
 
### Step 3 — Build and start Docker containers
```bash
docker-compose up -d --build
```
 
This starts 3 containers:
| Container       | Role            | Port |
|----------------|-----------------|------|
| `banking_app`  | PHP 8.3-fpm     | —    |
| `banking_nginx`| Nginx web server| 8000 |
| `banking_db`   | MySQL 8.0       | 3307 |
 
### Step 4 — Run composer install & migrations
```bash
docker-compose exec app composer install
```

```bash
docker exec banking_app php artisan migrate
```

 
### Step 5 — Generate Swagger docs
```bash
docker exec banking_app php artisan l5-swagger:generate
```
 
### Step 6 — Verify everything works
```bash
curl http://localhost:8000
```
 
---
 
## 📡 API Endpoints
 
### Authentication
 
| Method | Endpoint              | Description         | Auth required |
|--------|-----------------------|---------------------|---------------|
| POST   | `/api/auth/register`  | Register new user   | No            |
| POST   | `/api/auth/login`     | Login, get token    | No            |
 
### ATM
 
| Method | Endpoint                  | Description              | Auth required |
|--------|---------------------------|--------------------------|---------------|
| POST   | `/api/atm/withdraw`       | Withdraw funds (1% fee)  | Yes           |
| GET    | `/api/atm/balance`        | Get current balance      | Yes           |
| GET    | `/api/atm/transactions`   | List transactions        | Yes           |
 
---
 
## 🔐 Authentication Flow
 
1. **Register** → receive `token`
2. **Use token** in all protected requests as:
```
Authorization: Bearer your_token_here
```
 
---
 
## 💸 Withdraw Rules
 
- Fee: **1%** of withdrawal amount (deducted on top)
- Max per transaction: **300,000 USD**
- Requires valid **PIN** (4–6 digits set at registration)
- Example: withdraw 10,000 USD → fee 100 USD → total deducted 10,100 USD
---
 
## 📬 Postman Usage
 
### Required Headers for every request:
```
Accept:        application/json
Content-Type:  application/json
```
> ⚠️ Without `Accept: application/json` Laravel returns HTML redirects instead of JSON errors.
 
### Register example:
```json
POST /api/auth/register
{
    "email": "user@example.com",
    "password": "secret123",
    "pin": "1234"
}
```
 
### Withdraw example:
```json
POST /api/atm/withdraw
Authorization: Bearer your_token_here
 
{
    "amount": 5000,
    "pin": "1234"
}
```
 
### Transactions with filters:
```
GET /api/atm/transactions?page=1&date_from=2025-01-01&date_to=2025-12-31
Authorization: Bearer your_token_here
```
 
---
 
## 📖 Swagger UI
 
After running `php artisan l5-swagger:generate`, open:
```
http://localhost:8000/api/documentation
```
 
Click **Authorize 🔓** → enter `Bearer your_token_here` → all protected endpoints work automatically.
 
---
 
## 🗄 Database Schema
 
### `users`
| Column     | Type           | Notes                     |
|------------|----------------|---------------------------|
| id         | bigint         | Primary key               |
| email      | string         | Unique                    |
| password   | string         | Hashed (bcrypt)           |
| pin        | string         | Hashed (bcrypt)           |
| balance    | decimal(15,2)  | Default: 999,999.00 USD   |
| currency   | string         | Default: USD              |
 
### `transactions`
| Column        | Type           | Notes                  |
|---------------|----------------|------------------------|
| id            | bigint         | Primary key            |
| user_id       | bigint         | Foreign key → users    |
| type          | enum           | Only: withdraw         |
| amount        | decimal(15,2)  | Requested amount       |
| fee_amount    | decimal(15,2)  | 1% of amount           |
| balance_after | decimal(15,2)  | Balance after withdraw |
| created_at    | timestamp      |                        |
 
---
 
## 🧹 Useful Commands
 
```bash
# Clear all caches
docker exec banking_app php artisan config:clear
docker exec banking_app php artisan cache:clear
docker exec banking_app php artisan route:clear
 
# Re-run migrations fresh (⚠️ deletes all data)
docker exec banking_app php artisan migrate:fresh
 
# Stop containers
docker-compose down
 
# Stop and delete volumes (⚠️ deletes DB data)
docker-compose down -v
```
 
---
