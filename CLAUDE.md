# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Crypto-Tracker is a web platform for tracking cryptocurrency wallets, transactions, and real-time market data. Built with Laravel 12 (PHP backend) + React 18 (TypeScript frontend) connected via Inertia.js, containerized with Docker.

## Development Commands

All commands run from `crypto-tracker/` directory unless noted.

### Docker (from repo root)
```bash
docker-compose up -d --build          # Start all services
docker-compose exec app <command>     # Run command in PHP container
docker-compose down                   # Stop containers
docker-compose down -v                # Stop + remove volumes (destroys DB)
```

### Local Development
```bash
composer dev                          # Starts artisan serve + queue + pail + vite concurrently
npm run dev                           # Vite dev server only (localhost:5173)
npm run build                         # TypeScript check + Vite production build
```

### Testing
```bash
composer test                         # Clears config cache + runs Pest tests
php artisan test --env=testing        # Run Pest tests directly
php artisan test --filter=ExampleTest # Run a single test
```

### Linting & Formatting
```bash
npm run lint                          # ESLint with auto-fix on resources/js
```

### Database
```bash
php artisan migrate                   # Run migrations
php artisan migrate:fresh --seed      # Reset DB + migrate + seed
php artisan db:seed --class=LocalAdminSeeder  # Seed specific class
```

## Architecture

### Inertia.js Monolith
Laravel handles routing and controllers, rendering React pages via Inertia.js. No separate API + SPA — controllers return `Inertia::render()` responses that hydrate React components with server-side props. Ziggy provides Laravel named routes to the frontend.

### Key Directories
- `crypto-tracker/app/Services/` — Business logic (ApiService for HTTP calls, IntegrationHealthService for credential validation, OpenAIService for LLM, TelegramService for outbound Telegram messages)
- `crypto-tracker/app/Telegram/Middleware/` — Nutgram global middleware (LogIncomingUpdates)
- `crypto-tracker/app/Support/IntegrationRegistry.php` — Static accessor for `config/integrations.php` provider definitions
- `crypto-tracker/config/integrations.php` — Defines all external API providers (AllTick, FreeCryptoAPI, Bybit, OpenAI, CoinGecko, Blockchair, Helius, TronGrid, Etherscan, Alchemy, Moralis) with their fields, health checks, and WebSocket source IDs
- `crypto-tracker/resources/js/Pages/` — React page components (mapped 1:1 to Inertia routes)
- `crypto-tracker/resources/js/Components/` — Reusable React components including `LiveWebSocketChart.tsx` for real-time charting

### Integration System
Users manage per-provider API credentials via the Integrations page. Credentials are encrypted in the `user_integrations` table (one row per user+provider). The `IntegrationHealthService` validates credentials, and `ws_source_id` in the config links providers to WebSocket data sources for live charts.

### Telegram Integration
Users link their Telegram account via the Profile page ("Connect Telegram" button). The flow:
1. Backend generates a random token, caches it as `telegram_link:{token}` → `user_id` (15 min TTL)
2. Frontend opens a `t.me/{bot}?start={token}` deep link
3. Bot's `/start {token}` handler (in `routes/telegram.php`) looks up the cache, creates/updates a `TelegramChat` record linking the Telegram chat to the user
4. Bare `/start` (no token) invites unregistered users to sign up on the site

**Tables**: `telegram_chats` (links a Telegram chat_id to a user) and `telegram_messages` (logs all in/out messages). **Models**: `TelegramChat`, `TelegramMessage`.

**Outbound messaging**:
- `TelegramService` wraps Nutgram's `sendMessage()` with automatic logging to `telegram_messages`
- `WalletThresholdAlert` notification supports both `mail` and `telegram` channels, controlled by the `notify_via` pivot field (`email`/`telegram`/`both`)
- A `NotificationSent` event listener in `AppServiceProvider` auto-logs all Telegram notification deliveries

**Inbound logging**: `LogIncomingUpdates` Nutgram global middleware logs every incoming update.

**Webhook**: `POST /api/webhooks/telegram/webhook` (defined in `routes/api.php`) — receives Telegram updates and passes them to Nutgram's `$bot->run()`. Register with `php artisan nutgram:hook:set <url>`.

**Config**: `config/nutgram.php` (token + bot_username), `config/services.php` (`telegram-bot-api` block for laravel-notification-channels).

### Watchlist & Whale Tracking
Users track wallets via the Watchlist page (`/watchlist`). The `user_wallet` pivot table stores per-user settings:
- `custom_label`, `is_notified`, `notify_threshold_usd` — basic alert config
- `notify_direction` (`all`/`incoming`/`outgoing`) — filter alerts by tx direction
- `notify_cooldown_minutes` — minimum minutes between alerts per wallet
- `last_notified_at` — managed by `ProcessCryptoWebhook` job after sending an alert
- `notes` — personal notes

The public Whales page (`/whales`) shows whale wallets. Authenticated users see a "Track" button on each card that POSTs to `watchlist.store`, adding the whale to their watchlist. Already-tracked whales show a "Tracking" badge. The controller passes `trackedWhaleIds` (array of wallet IDs the user tracks) to the frontend; empty array for guests.

`ProcessCryptoWebhook` respects direction filter and cooldown before sending `WalletThresholdAlert` notifications, and updates `last_notified_at` on the pivot after each send.

### Route Structure
- `/` — Welcome (public)
- `/whales` — Whale wallet tracking (public, track buttons for auth users)
- `/dashboard` — DexScreener token feeds (auth + verified)
- `/chart` — Live WebSocket chart viewer (auth + verified)
- `/watchlist` — User wallet watchlist with notification settings (auth + verified)
- `/integrations` — CRUD for user API integrations (auth + verified)
- `/openai/respond` — OpenAI proxy endpoint (auth + verified)
- `/profile` — Profile management (auth), includes Telegram link/unlink
- `/api/webhooks/telegram/webhook` — Telegram bot webhook (Nutgram)

### Docker Services
- **app**: PHP-FPM (port 9000), XDebug on 9003
- **nginx**: HTTPS on 8443, HTTP on 8080 (redirects to HTTPS)
- **db**: PostgreSQL 15 on port 5433
- **redis**: Redis 7 on port 6379
- **worker**: Queue worker (`php artisan queue:work redis`)

## Tech Stack Specifics

- **PHP 8.2+**, Laravel 12, Pest PHP for testing
- **React 18**, TypeScript, Tailwind CSS, Vite 6
- **Charts**: `lightweight-charts` library for TradingView-style charts
- **Telegram**: Nutgram (bot framework) + `laravel-notification-channels/telegram` (notification channel)
- **UI**: Headless UI + Heroicons
- **Path alias**: `@/*` maps to `resources/js/*` in TypeScript
- **Dark mode**: Tailwind `class` strategy
- **Prettier**: 100 char width, single quotes, semicolons, with Tailwind + import organizer plugins
- **Testing DB**: SQLite in-memory (configured in `phpunit.xml`)
- **Dev runs on WSL2** with Vite file polling enabled for HMR
