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

export interface Transaction {
    id: number;
    wallet_id: number;
    hash: string;
    from_address: string;
    to_address: string;
    amount: string;
    fee: string | null;
    block_number: number | null;
    mined_at: string | null;
    created_at: string;
}

export interface PaginatedResponse<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User | null;
    };
};
