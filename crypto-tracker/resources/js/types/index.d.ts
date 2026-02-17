export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
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
    };
}

export interface WhaleWallet {
    id: number;
    address: string;
    balance_usd: string;
    network: Network;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
};
