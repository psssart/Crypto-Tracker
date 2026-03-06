<p align="center">
  <img src="crypto-tracker/public/icons/crypto-tracker.svg" width="80" alt="Crypto Tracker Logo">
</p>
<h1 align="center">Crypto Tracker</h1>

<p align="center">
  <strong>Real-time cryptocurrency wallet monitoring, whale tracking, and market intelligence — all in one platform.</strong>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-12-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel 12">
  <img src="https://img.shields.io/badge/React-18-61DAFB?style=for-the-badge&logo=react&logoColor=black" alt="React 18">
  <img src="https://img.shields.io/badge/TypeScript-5-3178C6?style=for-the-badge&logo=typescript&logoColor=white" alt="TypeScript 5">
  <img src="https://img.shields.io/badge/PostgreSQL-15-4169E1?style=for-the-badge&logo=postgresql&logoColor=white" alt="PostgreSQL 15">
  <img src="https://img.shields.io/badge/Docker-Ready-2496ED?style=for-the-badge&logo=docker&logoColor=white" alt="Docker">
  <img src="https://img.shields.io/badge/License-MIT-green?style=for-the-badge" alt="MIT License">
</p>

---

## What is Crypto Tracker?

Crypto Tracker is a full-stack web platform for monitoring cryptocurrency wallets, tracking whale movements, analyzing transactions, and staying on top of the meme coin market — with real-time alerts delivered to your inbox or Telegram.

Whether you're a DeFi researcher, a whale watcher, or just keeping tabs on your own wallets, Crypto Tracker gives you the tools to stay informed without the noise.

---

## Key Features

### Whale Tracking
Monitor the biggest players in crypto. Browse known whale wallets across 23+ networks, see their transaction history, and start tracking any wallet with a single click.

### Personal Watchlist
Build your own watchlist of wallets to follow. Set custom labels, configure USD threshold alerts, choose notification channels (email, Telegram, or both), and filter by transaction direction — incoming, outgoing, or all.

### Real-Time Webhook Alerts
Get notified the moment a tracked wallet moves funds. Powered by Moralis Streams and Alchemy Notify with cryptographic signature verification, so you never miss a transaction.

### Live Market Charts
TradingView-style candlestick charts with live WebSocket data from multiple providers (Binance, AllTick, FreeCryptoAPI). Auto-reconnecting, dark-theme ready.

### DexScreener Dashboard
Discover trending tokens with the integrated DexScreener feed. Browse latest token profiles, boosted tokens, and top movers — complete with social links and one-click wallet tracking.

### Transaction History & Visualization
Dive into detailed transaction history for any tracked wallet. Interactive bar charts with adaptive time bucketing, directional color coding (in/out/self), and custom tooltips.

### Multi-Chain Support
Track wallets across **23 networks** out of the box — Ethereum, Polygon, BSC, Arbitrum, Base, Solana, Bitcoin, Tron, and more. Provider-agnostic architecture makes adding new chains straightforward.

### Telegram Bot Integration
Link your Telegram account in seconds. Receive threshold alerts directly in Telegram with rich formatting, and all messages are logged for your records.

### Smart Notification System
Fine-grained control over how and when you're alerted:
- **Threshold-based** — only notify above a USD amount you set
- **Direction filtering** — incoming, outgoing, or all transactions
- **Cooldown periods** — prevent alert fatigue with configurable cooldowns
- **Multi-channel** — email, Telegram, or both

### Bring Your Own API Keys
Connect your own API credentials for 11+ providers (Moralis, Alchemy, Helius, Etherscan, CoinGecko, OpenAI, and more) via the Integrations page. Your keys are encrypted at rest and validated on save.

---

## Tech Stack

| Layer | Technology |
|---|---|
| **Backend** | PHP 8.2+ / Laravel 12 / Pest PHP |
| **Frontend** | React 18 / TypeScript 5 / Tailwind CSS 3 / Vite 6 |
| **Database** | PostgreSQL 15 / Redis 7 |
| **Charts** | TradingView Lightweight Charts / Recharts |
| **Auth** | Laravel Breeze + Sanctum |
| **Bot** | Nutgram (Telegram) |
| **Infra** | Docker Compose / GitHub Actions CI/CD |
| **Bridge** | Inertia.js (no separate API — server-rendered SPA) |

---

## Quick Start

### Prerequisites

- Docker & Docker Compose
- Node.js 18+ & npm
- PHP 8.2+ & Composer (for local development)
- OpenSSL (for self-signed certificates)

### 1. Clone & configure

```bash
git clone https://github.com/your-username/Crypto-Tracker.git
cd Crypto-Tracker

cp .env.dev .env
cp .env crypto-tracker/.env
```

Edit `.env` and fill in your credentials (database, mail, API keys).

### 2. Generate SSL certificates

```bash
mkdir -p certs
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout certs/localhost.key \
  -out certs/localhost.crt \
  -subj "/CN=localhost"
```

### 3. Install dependencies & start

```bash
cd crypto-tracker
composer install
cd ..

docker compose up -d --build
sudo chmod -R 777 crypto-tracker/storage crypto-tracker/bootstrap/cache
```

### 4. Start the frontend dev server

```bash
cd crypto-tracker
npm install
npm run dev
```

### 5. Open the app

- **App:** https://localhost:8443
- **Vite HMR:** http://localhost:5173

Default admin login (local only): `admin@admin` / `admin`

---

## Docker Services

| Service | Description | Port |
|---|---|---|
| **app** | PHP 8.3-FPM with XDebug | 9000 |
| **nginx** | HTTPS reverse proxy | 8443 (HTTPS), 8080 (HTTP) |
| **db** | PostgreSQL 15 | 5433 |
| **redis** | Redis 7 cache & queue backend | 6379 |
| **worker** | Queue worker (3 retries, 90s timeout) | — |
| **tunnel** | ngrok for webhook dev tunneling | 4041 |

---

## Project Structure

```
Crypto-Tracker/
├── crypto-tracker/          # Laravel 12 application
│   ├── app/
│   │   ├── Contracts/       # Interfaces (WalletHistoryProvider, CryptoWebhookHandler)
│   │   ├── DTOs/            # Value objects (ParsedTransaction)
│   │   ├── Http/Controllers/# Route handlers
│   │   ├── Jobs/            # Queued jobs (webhook processing, wallet sync)
│   │   ├── Models/          # Eloquent models
│   │   ├── Notifications/   # Mail + Telegram notifications
│   │   ├── Services/        # Business logic & API integrations
│   │   └── Providers/       # Service providers
│   ├── resources/js/
│   │   ├── Pages/           # React page components (Inertia)
│   │   ├── Components/      # Reusable UI components
│   │   ├── Layouts/         # App, Auth, Public, Guest layouts
│   │   └── lib/             # Theme provider, utilities
│   └── config/              # App & integration configuration
├── docker-compose.yml       # Development services
├── docker-compose.prod.yml  # Production services
├── nginx/                   # Nginx configuration
├── certs/                   # SSL certificates (gitignored)
└── CLAUDE.md                # AI assistant context
```

---

## CI/CD

- **Tests** run on every push to `main` across PHP 8.2, 8.3, and 8.4
- **Deploy** pipeline builds Docker images, runs Trivy security scans, pushes to DockerHub, and deploys via SSH with zero-downtime container recreation

---

## License

MIT License - see [LICENSE.txt](LICENSE.txt) for details.

Copyright 2026 Pavel Shubin
