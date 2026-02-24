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
php artisan db:seed --class=AdminUserSeeder  # Seed specific class
```

## Architecture

### Inertia.js Monolith
Laravel handles routing and controllers, rendering React pages via Inertia.js. No separate API + SPA — controllers return `Inertia::render()` responses that hydrate React components with server-side props. Ziggy provides Laravel named routes to the frontend.

### Key Directories
- `crypto-tracker/app/Http/Controllers/` — Route handlers: `WhaleController`, `DexScreenerController`, `ChartController`, `IntegrationController`, `WatchlistController`, `TransactionController`, `CryptoWebhookController`, `OpenAIController`, `ProfileController`, plus `Auth/` controllers (Breeze)
- `crypto-tracker/app/Services/` — Business logic: `ApiService` (HTTP client), `IntegrationHealthService` (credential validation for 11 providers), `OpenAIService` (LLM proxy), `TelegramService` (outbound messaging), `CoinGeckoService` (price fetching with 5-min cache), `CryptoProviderService` (two-tier API key resolution: user integration → config fallback), `WebhookAddressService` (Moralis Streams + Alchemy Notify address registration)
- `crypto-tracker/app/Services/WalletHistory/` — Blockchain transaction history providers implementing `WalletHistoryProvider` interface, plus `WalletHistoryProviderRegistry` (service locator resolving providers by network with priority fallback)
- `crypto-tracker/app/Services/Webhooks/` — Provider-specific webhook handlers (`MoralisWebhookHandler`, `AlchemyWebhookHandler`) implementing `CryptoWebhookHandler` interface
- `crypto-tracker/app/Jobs/` — Queued jobs: `ProcessCryptoWebhook`, `SyncWalletHistory`, `FetchWalletTransactions`, `UpdateWebhookAddress` (all with 3 tries + backoff)
- `crypto-tracker/app/Notifications/` — `WalletThresholdAlert` (mail + Telegram), `VerifyEmailCustom`, `CustomResetPassword`
- `crypto-tracker/app/Contracts/` — Interfaces: `WalletHistoryProvider`, `CryptoWebhookHandler`
- `crypto-tracker/app/DTOs/` — Value objects: `ParsedTransaction`
- `crypto-tracker/app/Providers/` — `AppServiceProvider` (singletons + NotificationSent listener), `CryptoServiceProvider` (CoinGeckoService, CryptoProviderService, WalletHistoryProviderRegistry, WebhookAddressService as singletons)
- `crypto-tracker/app/Http/Middleware/` — `HandleInertiaRequests` (shares auth.user + flash messages), `ForceHttps`, `TrustProxies` (trusts all proxies)
- `crypto-tracker/app/Telegram/Middleware/` — `LogIncomingUpdates` (Nutgram global middleware)
- `crypto-tracker/app/Support/IntegrationRegistry.php` — Static accessor for `config/integrations.php` provider definitions
- `crypto-tracker/app/Models/` — `User`, `Wallet`, `Transaction`, `Network`, `UserIntegration`, `TelegramChat`, `TelegramMessage`, `WebhookLog`
- `crypto-tracker/config/integrations.php` — Defines all external API providers (AllTick, FreeCryptoAPI, Bybit, OpenAI, CoinGecko, Blockchair, Helius, TronGrid, Etherscan, Alchemy, Moralis) with their fields, health checks, and WebSocket source IDs
- `crypto-tracker/resources/js/Pages/` — React page components (mapped 1:1 to Inertia routes)
- `crypto-tracker/resources/js/Layouts/` — `AppLayout` (root: flash messages + dark bg), `AuthenticatedLayout` (nav + theme toggle + user dropdown), `PublicLayout` (public nav + bg image), `GuestLayout` (auth forms)
- `crypto-tracker/resources/js/Components/` — Reusable components: `DataTable`, `LiveWebSocketChart`, `DateRangeSelect` (MUI date pickers), `FlashMessages` (toast system), `Dropdown`, `Modal`, form primitives
- `crypto-tracker/resources/js/Components/Charts/` — Generic reusable Recharts chart components (`BarChart.tsx`; future: `LineChart.tsx`, `AreaChart.tsx`)
- `crypto-tracker/resources/js/Components/Icons/` — SVG icon components: `Moon`, `Sun`, `Copy`, `Twitter`, `Telegram`, `Docs`, `Website`
- `crypto-tracker/resources/js/lib/theme-provider.tsx` — `ThemeProvider` context + `useTheme()` hook + `initializeTheme()` for dark/light/system mode (persisted in localStorage)
- `crypto-tracker/resources/js/types/index.d.ts` — TypeScript interfaces: `User`, `Network`, `WatchlistWallet`, `WhaleWallet`, `Transaction`, `PageProps`

### Models & Database

**Core models and relationships:**
- `User` → hasMany `UserIntegration`, belongsToMany `Wallet` (pivot: `user_wallet`), hasOne `TelegramChat`. Implements `MustVerifyEmail`. Custom `routeNotificationForTelegram()` method for Telegram notifications.
- `Wallet` → belongsTo `Network`, hasMany `Transaction`, belongsToMany `User`. Casts: `metadata` (array), `balance_usd` (decimal:18), `is_whale` (boolean). Unique constraint: `[network_id, address]`.
- `Transaction` → belongsTo `Wallet`. Casts: `amount`/`fee` (decimal:18). Unique: `hash`.
- `Network` → hasMany `Wallet`. Fields: `name`, `slug` (unique), `chain_id`, `currency_symbol`, `explorer_url`, `is_active`. 23 networks seeded (EVM + Solana, Bitcoin, Tron).
- `UserIntegration` → belongsTo `User`. Casts: `api_key` (encrypted), `settings` (encrypted:array). Unique: `[user_id, provider]`.
- `TelegramChat` → belongsTo `User`, hasMany `TelegramMessage`. Unique: `chat_id`.
- `TelegramMessage` → belongsTo `TelegramChat`. Fields: `direction` (in/out), `text`, `raw_payload` (jsonb).
- `WebhookLog` → standalone. Fields: `source`, `payload` (jsonb), `processed_at`.

**Pivot table `user_wallet`**: `custom_label`, `is_notified`, `notify_threshold_usd`, `notify_via` (email/telegram/both), `notify_direction` (all/incoming/outgoing), `notify_cooldown_minutes`, `last_notified_at`, `notes`.

**All monetary columns use `decimal(36,18)` for 18-decimal crypto precision.**

**Seeders**: `DatabaseSeeder` calls `NetworkSeeder` → `WhaleWalletSeeder` → `AdminUserSeeder` (local only, email: admin@admin, password: admin).

### Wallet History Provider System
Blockchain-specific providers implementing `WalletHistoryProvider` interface (`syncTransactions`, `fetchTransactions`, `syncBalance`). `WalletHistoryProviderRegistry` resolves providers by network slug with priority ordering:

| Provider | Networks | API Key | Notes |
|---|---|---|---|
| `MoralisHistoryProvider` | ethereum, polygon, bsc, arbitrum, base | Required | Wei→ether conversion, CoinGecko for USD |
| `BlockchairHistoryProvider` | bitcoin | Optional | Satoshi→BTC, UTXO model handling |
| `MempoolSpaceHistoryProvider` | bitcoin | None | Free fallback for Bitcoin |
| `HeliusHistoryProvider` | solana | Required | Lamports→SOL, RPC-based |
| `TronGridHistoryProvider` | tron | Optional | Sun→TRX, TransferContract only |

Jobs `SyncWalletHistory` and `FetchWalletTransactions` use the registry, trying providers in order until one succeeds.

### Integration System
Users manage per-provider API credentials via the Integrations page. Credentials are encrypted in the `user_integrations` table (one row per user+provider). The `IntegrationHealthService` validates credentials, and `ws_source_id` in the config links providers to WebSocket data sources for live charts. `CryptoProviderService` resolves API keys with a two-tier strategy: user's stored integration first, then `config('services.{provider}.key')` fallback.

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

**Webhook**: `POST /api/webhooks/telegram/webhook` (defined in `routes/api.php`) — receives Telegram updates and passes them to Nutgram's `$bot->run()`. Register with `php artisan nutgram:hook:set <url>`. In dev, `docker-entrypoint.sh` auto-registers via ngrok tunnel URL.

**Config**: `config/nutgram.php` (token + bot_username), `config/services.php` (`telegram-bot-api` block for laravel-notification-channels).

### Watchlist & Whale Tracking
Users track wallets via the Watchlist page (`/watchlist`). Free tier limit: 4 wallets (`WatchlistController::FREE_WALLET_LIMIT`). The `user_wallet` pivot table stores per-user settings:
- `custom_label`, `is_notified`, `notify_threshold_usd` — basic alert config
- `notify_direction` (`all`/`incoming`/`outgoing`) — filter alerts by tx direction
- `notify_cooldown_minutes` — minimum minutes between alerts per wallet
- `last_notified_at` — managed by `ProcessCryptoWebhook` job after sending an alert
- `notify_via` — channel selection (`email`/`telegram`/`both`)
- `notes` — personal notes

The public Whales page (`/whales`) shows whale wallets. Authenticated users see a "Track" button on each card that POSTs to `watchlist.store`, adding the whale to their watchlist. Already-tracked whales show a "Tracking" badge. The controller passes `trackedWhaleIds` (array of wallet IDs the user tracks) to the frontend; empty array for guests.

`ProcessCryptoWebhook` respects direction filter and cooldown before sending `WalletThresholdAlert` notifications, and updates `last_notified_at` on the pivot after each send.

**Frontend address validation** (network-aware regex): EVM `0x[0-9a-fA-F]{40}`, Solana base58 32-44 chars, Bitcoin `1.../3.../bc1...`, TRON `T...33 chars`.

### DexScreener Dashboard
The Dashboard page (`/dashboard`) fetches token data from the DexScreener API via `DexScreenerController`:
- `getLatestTokenProfiles` — latest token profiles, optionally grouped by chainId
- `getLatestBoostedTokens` — latest boosted tokens
- `getMostBoostedTokens` — tokens with most active boosts

Frontend shows a card grid with social links (Twitter, Telegram, Docs, Website), copy/track wallet functionality, and expandable token lists.

### DataTable Visualization System
`DataTable` supports an optional chart visualization rendered above the table via a render prop pattern:
- `renderVisualization?: (sortedData: T[], chartType: string) => ReactNode` — callback receives post-filter/post-sort data
- `chartTypes?: ChartTypeOption[]` — available chart type options (shows select when >1)
- A "Visualize" toggle button appears in the top bar when `renderVisualization` is provided
- Chart reflects current filter/sort state (pre-pagination)
- Supports sortable columns, multi-column filtering, cell-level filtering, pagination, and mobile card render mode

**Generic chart components** in `Components/Charts/`:
- `BarChart.tsx` — Generic Recharts bar chart with theme-aware colors (`useTheme()` → `isDark`), supports stacked bars, custom tooltips, configurable series/axes. Domain-agnostic — knows nothing about transactions.

**Transaction chart wiring** (in `Transactions.tsx` and `WhaleTransactions.tsx`):
- Adaptive time bucketing: ≤6h→minute, ≤3d→hour, ≤60d→day, ≤365d→week, >365d→month
- In (green, up) / Out (red, down) / Self (gray) diverging stacked bars via `stackOffset="sign"`
- Custom tooltip showing per-direction totals + up to 3 individual transactions per bucket
- X-axis labels rotate at -45 degrees when >15 buckets

### Crypto Webhook System
Provider-specific webhook endpoints with signature verification, following the same pattern as `WalletHistoryProvider` + registry.

**Architecture:**
- `CryptoWebhookHandler` interface (`app/Contracts/`) — `verifySignature(Request)` + `parseTransactions(array): ParsedTransaction[]`
- `MoralisWebhookHandler` — Keccak-256 signature verification (`x-signature` header), parses Moralis Streams payloads (confirmed only), converts wei→ether
- `AlchemyWebhookHandler` — HMAC-SHA256 signature verification (`x-alchemy-signature` header), parses Alchemy Notify payloads (external transfers only)
- `ParsedTransaction` DTO (`app/DTOs/`) — value object with `networkSlug`, `txHash`, `fromAddress`, `toAddress`, `amount`, `blockNumber`, `minedAt`
- `CryptoWebhookController` — two endpoints calling shared `process()` flow: verify signature → log → dispatch job. Returns 202 on success, 401 on invalid signature.
- `ProcessCryptoWebhook` job — resolves handler by `webhook_logs.source`, calls `parseTransactions()`, matches wallets, creates transactions, sends notifications. Includes CoinGecko coin mapping for USD price calculation (25+ networks).

**Endpoints** (in `routes/api.php`, no auth, CSRF exempted via `bootstrap/app.php`):
- `POST /api/webhooks/moralis` — Moralis Streams webhook
- `POST /api/webhooks/alchemy` — Alchemy Notify webhook

**Signature secrets** (in `config/services.php`):
- Moralis: `config('services.moralis.api_key')`
- Alchemy: per-network signing keys (`config('services.alchemy.webhook_{network}_signing_key')`)

**Network mappings:**
- Moralis chainId (hex): `0x1→ethereum`, `0x89→polygon`, `0x38→bsc`, `0xa4b1→arbitrum`, `0x2105→base` + 15 more EVM chains
- Alchemy network string: `ETH_MAINNET→ethereum`, `ARB_MAINNET→arbitrum`, `MATIC_MAINNET→polygon`, `BASE_MAINNET→base`, `SOL_MAINNET→solana`

### Queued Jobs
All jobs implement `ShouldQueue` with Redis queue backend (worker container: `php artisan queue:work redis --sleep=3 --tries=3 --timeout=90`).

| Job | Tries | Backoff | Purpose |
|---|---|---|---|
| `ProcessCryptoWebhook` | 3 | 15s | Parse webhook payload → match wallets → create transactions → send alerts |
| `SyncWalletHistory` | 3 | 30s | Sync recent transactions + balance via provider registry |
| `FetchWalletTransactions` | 3 | 30s | Fetch transactions within date range (triggered from Transactions page) |
| `UpdateWebhookAddress` | 3 | 30s | Add/remove wallet address from Moralis Streams or Alchemy Notify |

### Route Structure
**Public routes:**
- `GET /` → `WhaleController@index` (name: `whales`) — whale wallet listing
- `GET /whales/{wallet}/transactions` → `WhaleController@transactions` — whale transaction history
- `GET /dashboard` → `DexScreenerController@index` — DexScreener token feeds
- `GET /latest-token-profiles/{group?}` — DexScreener API proxy
- `GET /latest-boosted-tokens/{group?}` — DexScreener API proxy
- `GET /most-boosted-tokens/{group?}` — DexScreener API proxy
- `GET /chart` → `ChartController@show` — live WebSocket chart viewer

**Auth + verified routes:**
- `POST /chart/check-source` → `ChartController@checkSource` — validate WebSocket source
- `GET|POST|PATCH|DELETE /integrations` → `IntegrationController` (CRUD) + `POST /integrations/check` (health check)
- `POST /openai/respond` → `OpenAIController@respond` — OpenAI proxy
- `GET|POST|PATCH|DELETE /watchlist` → `WatchlistController` (CRUD)
- `GET /transactions` → `TransactionController@index` — user transaction history
- `POST /transactions/fetch` → `TransactionController@fetch` — async fetch for date range (dispatches job)

**Auth routes (profile):**
- `GET|PATCH|DELETE /profile` → `ProfileController` (edit/update/destroy)
- `POST /profile/telegram-link` — generate Telegram linking token
- `POST /profile/telegram-unlink` — unlink Telegram account

**Auth routes (Breeze):** `/login`, `/register`, `/forgot-password`, `/reset-password/{token}`, `/verify-email`, `/confirm-password`, `/logout`

**API webhooks (no auth, CSRF exempted):**
- `POST /api/webhooks/telegram/webhook` — Telegram bot (Nutgram)
- `POST /api/webhooks/moralis` — Moralis Streams (signature-verified)
- `POST /api/webhooks/alchemy` — Alchemy Notify (signature-verified)

**Health:** `GET /up` (Laravel health check)

### Middleware Stack
Configured in `bootstrap/app.php`:
- **Web group appends**: `TrustProxies` (trusts `*`), `ForceHttps`, `HandleInertiaRequests` (shares `auth.user` + flash messages), `AddLinkHeadersForPreloadedAssets`
- **CSRF exempted**: `api/webhooks/*`, `webhooks/*`
- `HandleInertiaRequests` shares: `auth.user`, `flash.success`, `flash.error`, `flash.info`, `flash.status` (lazy-loaded from session)

### Frontend Architecture
**Entry point** (`app.tsx`): Initializes theme → creates Inertia app → wraps in `ThemeProvider` → default layout `AppLayout`.

**Layouts:**
- `AppLayout` — Root wrapper: dark background (`bg-slate-950`) + `FlashMessages` toast system
- `AuthenticatedLayout` — Nav bar with theme toggle (Moon/Sun), logo, main links (Meme coins, Chart, Whales, Watchlist), user dropdown (Profile, Integrations, Logout), responsive mobile menu
- `PublicLayout` — Background image (`app-main-theme.png`), public nav links, login/register buttons
- `GuestLayout` — Minimal layout for auth forms

**Theme system** (`lib/theme-provider.tsx`): React context providing `appearance` (light/dark/system) + `updateAppearance()`. Persisted in localStorage, syncs Tailwind `dark` class on `<html>`. `initializeTheme()` runs before React hydration to prevent flash.

**Flash messages** (`FlashMessages.tsx`): Client-side toast system. Exported functions `flashError()`, `flashSuccess()`, `flashInfo()` for imperative use. Also reads Inertia server-side flash. Fixed top-right, auto-dismiss 4s, max 5 toasts.

**LiveWebSocketChart**: TradingView `lightweight-charts` integration supporting multiple WebSocket sources (Binance native WS, AllTick auth WS, FreeCryptoAPI REST polling). Auto-reconnect, heartbeat support, dark theme.

**DateRangeSelect**: MUI DatePicker with dayjs, theme-aware (dark/light), enforces max 6-month range.

### Docker Services
- **app**: PHP 8.3-FPM (port 9000), XDebug on 9003, volumes: `./crypto-tracker:/var/www`
- **nginx**: HTTPS on 8443, HTTP on 8080 (redirects to HTTPS), self-signed certs in `./certs/`
- **db**: PostgreSQL 15 on port 5433
- **redis**: Redis 7 on port 6379
- **worker**: Queue worker (`php artisan queue:work redis --sleep=3 --tries=3 --timeout=90`)
- **tunnel**: ngrok container for webhook dev tunneling (port 4041 dashboard)

**Production** (`docker-compose.prod.yml`): Multi-stage Dockerfile (4 stages: node-build → php-build → runtime → site). Read-only filesystem, capability dropping, no XDebug, Redis password, localhost-only DB port.

**Entrypoint** (`docker-entrypoint.sh`): Waits for DB → runs migrations → seeds → local: clears caches + registers Telegram webhook via ngrok; prod: caches config/routes/views + registers webhook via APP_URL.

### CI/CD
**GitHub Actions workflows:**
- `tests.yml` — Runs on push to main/*.x and nightly. Matrix: PHP 8.2/8.3/8.4. Steps: checkout → PHP setup → composer install → Node setup → npm install → `npm run build` → `php artisan test --env=testing`.
- `deploy.yml` — Runs on push to main. Builds Docker images (runtime + site) → Trivy security scan → pushes to DockerHub (`pasubi/crypto-tracker-app`, `pasubi/crypto-tracker-nginx`) → SSH deploys: `docker compose pull && up -d --force-recreate && migrate --force`.

## Tech Stack Specifics

- **PHP 8.2+**, Laravel 12, Pest PHP for testing
- **React 18**, TypeScript 5, Tailwind CSS 3, Vite 6
- **Charts**: `lightweight-charts` for TradingView-style WebSocket charts, `recharts` for data visualization (bar charts on Transactions/WhaleTransactions pages)
- **Date pickers**: MUI `@mui/x-date-pickers` + `dayjs` + `@emotion/react` (CSS-in-JS for MUI)
- **Telegram**: Nutgram (bot framework) + `laravel-notification-channels/telegram` (notification channel)
- **Auth**: Laravel Breeze (scaffolding) + Laravel Sanctum (API tokens)
- **Crypto**: `kornrunner/keccak` for Ethereum Keccak-256 signature verification
- **UI**: Headless UI + Heroicons
- **Path alias**: `@/*` maps to `resources/js/*` in TypeScript
- **Dark mode**: Tailwind `class` strategy, ThemeProvider context with localStorage persistence
- **Prettier**: 100 char width, single quotes, semicolons, with Tailwind + import organizer plugins
- **Testing DB**: SQLite in-memory (configured in `phpunit.xml`), array cache/session/queue/mail
- **Dev tools**: Laravel Pail (log viewer), ngrok (webhook tunneling), XDebug
- **Dev runs on WSL2** with Vite file polling enabled for HMR
