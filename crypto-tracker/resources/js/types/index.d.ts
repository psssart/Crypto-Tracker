export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    telegram_username?: string;
}

export interface Network {
    id: number;
    name: string;
    slug: string;
    currency_symbol: string;
    explorer_url: string | null;
}

export interface WatchlistWallet {
    id: number;
    address: string;
    balance_usd: string;
    last_synced_at: string | null;
    is_whale: boolean;
    network: Network;
    pivot: {
        custom_label: string | null;
        is_notified: boolean;
        notify_threshold_usd: string | null;
        notify_via: 'email' | 'telegram' | 'both';
        notify_direction: 'all' | 'incoming' | 'outgoing';
        notify_cooldown_minutes: number | null;
        last_notified_at: string | null;
        notes: string | null;
    };
}

export interface WhaleWallet {
    id: number;
    address: string;
    balance_usd: string;
    last_synced_at: string | null;
    metadata: { label?: string } | null;
    network: Network;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User | null;
    };
};
