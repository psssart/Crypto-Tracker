# Crypto Tracker — Technical Reference

Laravel 12 + React 18 + TypeScript monolith connected via Inertia.js. This document covers architecture, commands, and conventions for developers working on the codebase.

---

## Development Commands

### Docker (from repo root `/`)

```bash
docker-compose up -d --build          # Start all services
docker-compose exec app <command>     # Run command in PHP container
docker-compose down                   # Stop containers
docker-compose down -v                # Stop + remove volumes (destroys DB)
```

### Local Development (from `crypto-tracker/`)

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

- **Test DB:** SQLite in-memory (configured in `phpunit.xml`)
- **Test drivers:** array cache, session, queue, and mail

### Linting & Formatting

```bash
npm run lint                          # ESLint with auto-fix on resources/js
```

- **Prettier:** 100 char width, single quotes, semicolons, Tailwind + import organizer plugins

### Database

```bash
php artisan migrate                   # Run migrations
php artisan migrate:fresh --seed      # Reset DB + migrate + seed
php artisan db:seed --class=AdminUserSeeder  # Seed specific class
```

---

## Architecture

### Inertia.js Monolith

Laravel handles routing and controllers, rendering React pages via Inertia.js. No separate API + SPA — controllers return `Inertia::render()` responses that hydrate React components with server-side props. Ziggy provides Laravel named routes to the frontend via the `route()` helper.

### Key Directories

| Directory | Purpose |
|---|---|
| `app/Http/Controllers/` | Route handlers — `WhaleController`, `DexScreenerController`, `ChartController`, `IntegrationController`, `WatchlistController`, `TransactionController`, `CryptoWebhookController`, `OpenAIController`, `ProfileController`, plus `Auth/` (Breeze) |
| `app/Services/` | Business logic — `ApiService`, `IntegrationHealthService`, `OpenAIService`, `TelegramService`, `CoinGeckoService`, `CryptoProviderService`, `WebhookAddressService` |
| `app/Services/WalletHistory/` | Blockchain tx history providers implementing `WalletHistoryProvider` + `WalletHistoryProviderRegistry` |
| `app/Services/Webhooks/` | `MoralisWebhookHandler`, `AlchemyWebhookHandler` implementing `CryptoWebhookHandler` |
| `app/Jobs/` | Queued jobs — `ProcessCryptoWebhook`, `SyncWalletHistory`, `FetchWalletTransactions`, `UpdateWebhookAddress` |
| `app/Contracts/` | Interfaces — `WalletHistoryProvider`, `CryptoWebhookHandler` |
| `app/DTOs/` | Value objects — `ParsedTransaction` |
| `app/Notifications/` | `WalletThresholdAlert` (mail + Telegram), `VerifyEmailCustom`, `CustomResetPassword` |
| `app/Models/` | `User`, `Wallet`, `Transaction`, `Network`, `UserIntegration`, `TelegramChat`, `TelegramMessage`, `WebhookLog` |
| `app/Providers/` | `AppServiceProvider`, `CryptoServiceProvider` (registers singletons) |
| `app/Support/` | `IntegrationRegistry` — static accessor for `config/integrations.php` |
| `config/integrations.php` | All external API provider definitions (11 providers) with fields, health checks, WS source IDs |
| `resources/js/Pages/` | React page components mapped 1:1 to Inertia routes |
| `resources/js/Components/` | Reusable components — `DataTable`, `LiveWebSocketChart`, `DateRangeSelect`, `FlashMessages`, charts, icons |
| `resources/js/Layouts/` | `AppLayout`, `AuthenticatedLayout`, `PublicLayout`, `GuestLayout` |
| `resources/js/lib/` | `theme-provider.tsx` — context + hook + FOUC prevention |

---

## Models & Database

### Core Models

- **User** → hasMany `UserIntegration`, belongsToMany `Wallet` (pivot: `user_wallet`), hasOne `TelegramChat`. Implements `MustVerifyEmail`.
- **Wallet** → belongsTo `Network`, hasMany `Transaction`, belongsToMany `User`. Unique: `[network_id, address]`. Casts: `metadata` (array), `balance_usd` (decimal:18), `is_whale` (boolean).
- **Transaction** → belongsTo `Wallet`. Casts: `amount`/`fee` (decimal:18). Unique: `hash`.
- **Network** → hasMany `Wallet`. 23 networks seeded (EVM + Solana, Bitcoin, Tron). Unique: `slug`.
- **UserIntegration** → belongsTo `User`. Casts: `api_key` (encrypted), `settings` (encrypted:array). Unique: `[user_id, provider]`.
- **TelegramChat** → belongsTo `User`, hasMany `TelegramMessage`. Unique: `chat_id`.
- **TelegramMessage** → belongsTo `TelegramChat`. Fields: `direction` (in/out), `text`, `raw_payload` (jsonb).
- **WebhookLog** → standalone. Fields: `source`, `payload` (jsonb), `processed_at`.

### Pivot Table: `user_wallet`

| Column | Type | Purpose |
|---|---|---|
| `custom_label` | string | User-defined wallet label |
| `is_notified` | boolean | Alerts enabled |
| `notify_threshold_usd` | decimal | Min USD amount to trigger alert |
| `notify_via` | enum | `email` / `telegram` / `both` |
| `notify_direction` | enum | `all` / `incoming` / `outgoing` |
| `notify_cooldown_minutes` | integer | Min minutes between alerts |
| `last_notified_at` | timestamp | Managed by `ProcessCryptoWebhook` |
| `notes` | text | Personal notes |

**All monetary columns use `decimal(36,18)` for 18-decimal crypto precision.**

### Seeders

`DatabaseSeeder` → `NetworkSeeder` → `WhaleWalletSeeder` → `AdminUserSeeder` (local only: `admin@admin` / `admin`).

---

## Wallet History Provider System

Providers implement the `WalletHistoryProvider` interface (`syncTransactions`, `fetchTransactions`, `syncBalance`). The `WalletHistoryProviderRegistry` resolves providers by network slug with priority-ordered fallback.

| Provider | Networks | API Key | Notes |
|---|---|---|---|
| `MoralisHistoryProvider` | ethereum, polygon, bsc, arbitrum, base | Required | Wei → ether, CoinGecko USD pricing |
| `BlockchairHistoryProvider` | bitcoin | Optional | Satoshi → BTC, UTXO model |
| `MempoolSpaceHistoryProvider` | bitcoin | None | Free Bitcoin fallback |
| `HeliusHistoryProvider` | solana | Required | Lamports → SOL, RPC-based |
| `TronGridHistoryProvider` | tron | Optional | Sun → TRX, TransferContract only |

Jobs `SyncWalletHistory` and `FetchWalletTransactions` use the registry, trying providers in priority order until one succeeds.

---

## Crypto Webhook System

### Architecture

- **`CryptoWebhookHandler`** interface — `verifySignature(Request)` + `parseTransactions(array): ParsedTransaction[]`
- **`MoralisWebhookHandler`** — Keccak-256 signature verification (`x-signature` header), parses Moralis Streams payloads (confirmed only), converts wei → ether
- **`AlchemyWebhookHandler`** — HMAC-SHA256 verification (`x-alchemy-signature` header), parses Alchemy Notify payloads (external transfers only)
- **`CryptoWebhookController`** — verify signature → log to `webhook_logs` → dispatch `ProcessCryptoWebhook` job. Returns 202/401.
- **`ProcessCryptoWebhook`** job — resolves handler, parses transactions, matches wallets, creates records, applies direction filter + cooldown, sends `WalletThresholdAlert`

### Network Mappings

- **Moralis** chainId (hex): `0x1→ethereum`, `0x89→polygon`, `0x38→bsc`, `0xa4b1→arbitrum`, `0x2105→base` + 15 more
- **Alchemy** network string: `ETH_MAINNET→ethereum`, `ARB_MAINNET→arbitrum`, `MATIC_MAINNET→polygon`, `BASE_MAINNET→base`, `SOL_MAINNET→solana`

---

## Integration System

Users manage per-provider API credentials via the Integrations page. Credentials are encrypted in `user_integrations` (one row per user + provider).

- **`IntegrationHealthService`** validates credentials against each provider's API
- **`CryptoProviderService`** resolves API keys with two-tier strategy: user's stored key first, then `config('services.{provider}.key')` fallback
- **`config/integrations.php`** defines all 11 providers: AllTick, FreeCryptoAPI, Bybit, OpenAI, CoinGecko, Blockchair, Helius, TronGrid, Etherscan, Alchemy, Moralis

---

## Telegram Integration

### Linking Flow

1. Backend generates a random token, caches as `telegram_link:{token}` → `user_id` (15 min TTL)
2. Frontend opens `t.me/{bot}?start={token}` deep link
3. Bot's `/start {token}` handler resolves cache → creates/updates `TelegramChat` record
4. Bare `/start` (no token) invites unregistered users to sign up

### Components

- **`TelegramService`** — wraps Nutgram `sendMessage()` with auto-logging to `telegram_messages`
- **`WalletThresholdAlert`** — notification supporting `mail` + `telegram` channels
- **`LogIncomingUpdates`** — Nutgram global middleware logging all incoming updates
- **Webhook endpoint:** `POST /api/webhooks/telegram/webhook` → Nutgram `$bot->run()`
- **Config:** `config/nutgram.php`, `config/services.php` (`telegram-bot-api` block)

---

## Queued Jobs

All jobs implement `ShouldQueue` with Redis backend. Worker: `php artisan queue:work redis --sleep=3 --tries=3 --timeout=90`.

| Job | Tries | Backoff | Purpose |
|---|---|---|---|
| `ProcessCryptoWebhook` | 3 | 15s | Parse webhook → match wallets → create transactions → send alerts |
| `SyncWalletHistory` | 3 | 30s | Sync recent transactions + balance via provider registry |
| `FetchWalletTransactions` | 3 | 30s | Fetch transactions within date range (Transactions page) |
| `UpdateWebhookAddress` | 3 | 30s | Add/remove address from Moralis Streams or Alchemy Notify |

---

## Route Structure

### Public

| Method | URI | Controller | Name |
|---|---|---|---|
| GET | `/` | `WhaleController@index` | `whales` |
| GET | `/whales/{wallet}/transactions` | `WhaleController@transactions` | — |
| GET | `/dashboard` | `DexScreenerController@index` | — |
| GET | `/latest-token-profiles/{group?}` | DexScreener API proxy | — |
| GET | `/latest-boosted-tokens/{group?}` | DexScreener API proxy | — |
| GET | `/most-boosted-tokens/{group?}` | DexScreener API proxy | — |
| GET | `/chart` | `ChartController@show` | — |

### Authenticated + Verified

| Method | URI | Controller | Purpose |
|---|---|---|---|
| POST | `/chart/check-source` | `ChartController@checkSource` | Validate WebSocket source |
| CRUD | `/integrations` | `IntegrationController` | Manage API credentials |
| POST | `/integrations/check` | `IntegrationController` | Health check |
| POST | `/openai/respond` | `OpenAIController@respond` | OpenAI proxy |
| CRUD | `/watchlist` | `WatchlistController` | Manage tracked wallets |
| GET | `/transactions` | `TransactionController@index` | User transaction history |
| POST | `/transactions/fetch` | `TransactionController@fetch` | Async fetch (dispatches job) |

### Profile

| Method | URI | Purpose |
|---|---|---|
| GET/PATCH/DELETE | `/profile` | View / update / delete account |
| POST | `/profile/telegram-link` | Generate Telegram linking token |
| POST | `/profile/telegram-unlink` | Unlink Telegram account |

### API Webhooks (no auth, CSRF exempted)

| Method | URI | Source |
|---|---|---|
| POST | `/api/webhooks/telegram/webhook` | Telegram bot (Nutgram) |
| POST | `/api/webhooks/moralis` | Moralis Streams |
| POST | `/api/webhooks/alchemy` | Alchemy Notify |

---

## Middleware Stack

Configured in `bootstrap/app.php`:

- **Web group:** `TrustProxies` (trusts `*`), `ForceHttps`, `HandleInertiaRequests`, `AddLinkHeadersForPreloadedAssets`
- **CSRF exempted:** `api/webhooks/*`, `webhooks/*`
- **`HandleInertiaRequests`** shares: `auth.user`, `flash.success`, `flash.error`, `flash.info`, `flash.status` (lazy-loaded)

---

## Frontend Architecture

### Entry Point (`app.tsx`)

Initialize theme → create Inertia app → wrap in `ThemeProvider` → default layout `AppLayout`.

### Layouts

| Layout | Purpose |
|---|---|
| `AppLayout` | Root wrapper: dark bg (`bg-slate-950`) + `FlashMessages` toast system |
| `AuthenticatedLayout` | Nav bar with theme toggle, logo, main links, user dropdown, mobile menu |
| `PublicLayout` | Background image, public nav links, login/register buttons |
| `GuestLayout` | Minimal layout for auth forms |

### Theme System (`lib/theme-provider.tsx`)

React context providing `appearance` (light/dark/system) + `updateAppearance()`. Persisted in localStorage, syncs Tailwind `dark` class on `<html>`. `initializeTheme()` runs before React hydration to prevent FOUC.

### Flash Messages (`FlashMessages.tsx`)

Client-side toast system. Exported functions: `flashError()`, `flashSuccess()`, `flashInfo()`. Also reads server-side Inertia flash. Fixed top-right, auto-dismiss 4s, max 5 toasts.

### Key Components

- **`DataTable`** — Sortable columns, multi-column filtering, pagination, mobile card mode, optional chart visualization via render prop
- **`LiveWebSocketChart`** — TradingView `lightweight-charts` with Binance WS, AllTick WS, FreeCryptoAPI REST polling. Auto-reconnect, heartbeat, dark theme.
- **`DateRangeSelect`** — MUI DatePicker with dayjs, theme-aware, max 6-month range enforcement
- **`Charts/BarChart.tsx`** — Generic Recharts bar chart with theme-aware colors, stacked bars, custom tooltips

### Transaction Visualization

- Adaptive time bucketing: ≤6h → minute, ≤3d → hour, ≤60d → day, ≤365d → week, >365d → month
- Directional stacked bars: In (green), Out (red), Self (gray) via `stackOffset="sign"`
- Custom tooltip with per-direction totals + up to 3 individual txs per bucket
- X-axis labels rotate -45deg when >15 buckets

---

## Docker Services

| Service | Image | Port | Notes |
|---|---|---|---|
| **app** | PHP 8.3-FPM | 9000 | XDebug on 9003 |
| **nginx** | nginx | 8443 / 8080 | HTTPS, HTTP redirect |
| **db** | PostgreSQL 15 | 5433 | — |
| **redis** | Redis 7 | 6379 | Cache + queue backend |
| **worker** | Same as app | — | `queue:work redis` |
| **tunnel** | ngrok | 4041 | Webhook dev tunneling |

### Production (`docker-compose.prod.yml`)

Multi-stage Dockerfile (node-build → php-build → runtime → site). Read-only filesystem, capability dropping, no XDebug, Redis password, localhost-only DB port.

### Entrypoint (`docker-entrypoint.sh`)

Waits for DB → migrations → seed → local: clear caches + register Telegram webhook via ngrok; prod: cache config/routes/views + register webhook via APP_URL.

---

## CI/CD

### Tests (`tests.yml`)

Runs on push to `main`/`*.x` and nightly. Matrix: PHP 8.2, 8.3, 8.4. Steps: checkout → PHP setup → Composer → Node → npm → `npm run build` → `php artisan test`.

### Deploy (`deploy.yml`)

Runs on push to `main`. Docker build → Trivy scan → push to DockerHub (`pasubi/crypto-tracker-app`, `pasubi/crypto-tracker-nginx`) → SSH deploy: `docker compose pull && up -d --force-recreate && migrate --force`.

---

## Tech Stack

| Category | Technology |
|---|---|
| Backend | PHP 8.2+, Laravel 12, Pest PHP |
| Frontend | React 18, TypeScript 5, Tailwind CSS 3, Vite 6 |
| Charts | `lightweight-charts` (TradingView), `recharts` (data viz) |
| Date pickers | MUI `@mui/x-date-pickers` + dayjs + Emotion |
| Telegram | Nutgram + `laravel-notification-channels/telegram` |
| Auth | Laravel Breeze + Sanctum |
| Crypto | `kornrunner/keccak` (Keccak-256 sig verification) |
| UI | Headless UI + Heroicons |
| Path alias | `@/*` → `resources/js/*` |
| Dark mode | Tailwind `class` strategy + ThemeProvider context |
