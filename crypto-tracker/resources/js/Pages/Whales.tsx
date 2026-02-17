import PublicLayout from '@/Layouts/PublicLayout';
import { Network, PageProps, WhaleWallet } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

interface Props extends PageProps {
    whales: WhaleWallet[];
    networks: Network[];
    activeNetwork: string | null;
    trackedWhaleIds: number[];
}

function truncateAddress(address: string): string {
    if (address.length <= 14) return address;
    return address.slice(0, 6) + '...' + address.slice(-4);
}

function formatUsd(value: string): string {
    const num = parseFloat(value);
    if (num >= 1_000_000_000) return '$' + (num / 1_000_000_000).toFixed(1) + 'B';
    if (num >= 1_000_000) return '$' + (num / 1_000_000).toFixed(1) + 'M';
    return '$' + num.toLocaleString(undefined, { maximumFractionDigits: 0 });
}

function formatUsdFull(value: string): string {
    return '$' + parseFloat(value).toLocaleString(undefined, { maximumFractionDigits: 0 });
}

function copyToClipboard(text: string, setCopied: (addr: string) => void) {
    navigator.clipboard.writeText(text);
    setCopied(text);
    setTimeout(() => setCopied(''), 1500);
}

function explorerAddressUrl(network: Network, address: string): string | null {
    if (!network.explorer_url) return null;
    if (network.slug === 'bitcoin') return `${network.explorer_url}/address/${address}`;
    if (network.slug === 'solana') return `${network.explorer_url}/address/${address}`;
    return `${network.explorer_url}/address/${address}`;
}

const networkColors: Record<string, string> = {
    ETH: 'bg-blue-500/20 text-blue-300 border-blue-500/30',
    BNB: 'bg-yellow-500/20 text-yellow-300 border-yellow-500/30',
    MATIC: 'bg-purple-500/20 text-purple-300 border-purple-500/30',
    BTC: 'bg-orange-500/20 text-orange-300 border-orange-500/30',
    SOL: 'bg-green-500/20 text-green-300 border-green-500/30',
};

function NetworkBadge({ network }: { network: Network }) {
    const colors = networkColors[network.currency_symbol] || 'bg-gray-500/20 text-gray-300';
    return (
        <span className={`rounded border px-2 py-0.5 text-xs font-medium ${colors}`}>
            {network.currency_symbol}
        </span>
    );
}

function WhaleCard({
    whale,
    copiedAddr,
    onCopy,
    isTracked,
    isAuthenticated,
    onTrack,
}: {
    whale: WhaleWallet;
    copiedAddr: string;
    onCopy: (addr: string) => void;
    isTracked: boolean;
    isAuthenticated: boolean;
    onTrack: (whale: WhaleWallet) => void;
}) {
    const explorer = explorerAddressUrl(whale.network, whale.address);
    const label = whale.metadata?.label;

    return (
        <div className="rounded-xl border border-white/10 bg-black/40 p-5 backdrop-blur transition hover:border-white/20 hover:bg-black/50">
            <div className="flex items-start justify-between">
                <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-2">
                        <NetworkBadge network={whale.network} />
                        {label && (
                            <span className="truncate text-sm font-semibold text-white">
                                {label}
                            </span>
                        )}
                    </div>

                    <div className="mt-2 flex items-center gap-2">
                        <code className="text-sm text-white/60">
                            {truncateAddress(whale.address)}
                        </code>
                        <button
                            onClick={() => onCopy(whale.address)}
                            className="text-white/40 transition hover:text-white/80"
                            title="Copy address"
                        >
                            {copiedAddr === whale.address ? (
                                <svg
                                    className="h-4 w-4 text-green-400"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth={2}
                                        d="M5 13l4 4L19 7"
                                    />
                                </svg>
                            ) : (
                                <svg
                                    className="h-4 w-4"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth={2}
                                        d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"
                                    />
                                </svg>
                            )}
                        </button>
                        {explorer && (
                            <a
                                href={explorer}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="text-white/40 transition hover:text-white/80"
                                title="View on explorer"
                            >
                                <svg
                                    className="h-4 w-4"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth={2}
                                        d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"
                                    />
                                </svg>
                            </a>
                        )}
                    </div>
                </div>
            </div>

            <div className="mt-3 flex items-center justify-between">
                <span className="text-xl font-bold text-white">
                    {formatUsd(whale.balance_usd)}
                </span>
                {isAuthenticated &&
                    (isTracked ? (
                        <span className="rounded-full bg-green-500/20 px-3 py-1 text-xs font-medium text-green-300">
                            Tracking
                        </span>
                    ) : (
                        <button
                            onClick={() => onTrack(whale)}
                            className="rounded-full border border-white/20 px-3 py-1 text-xs font-medium text-white/70 transition hover:border-white/40 hover:text-white"
                        >
                            Track
                        </button>
                    ))}
            </div>
        </div>
    );
}

export default function Whales({ auth, whales, networks, activeNetwork, trackedWhaleIds }: Props) {
    const [copiedAddr, setCopiedAddr] = useState('');
    const [trackedIds, setTrackedIds] = useState<number[]>(trackedWhaleIds);

    const handleTrack = (whale: WhaleWallet) => {
        router.post(
            route('watchlist.store'),
            {
                network_id: whale.network.id,
                address: whale.address,
                custom_label: whale.metadata?.label || '',
            },
            {
                preserveScroll: true,
                onSuccess: () => setTrackedIds((prev) => [...prev, whale.id]),
            },
        );
    };

    const totalValue = whales.reduce((sum, w) => sum + parseFloat(w.balance_usd), 0);

    const networkBreakdown = networks
        .map((n) => ({
            ...n,
            count: whales.filter((w) => w.network.slug === n.slug).length,
        }))
        .filter((n) => n.count > 0);

    const handleFilter = (slug: string | null) => {
        router.get(
            route('whales'),
            slug ? { network: slug } : {},
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <PublicLayout>
            <Head title="Whale Tracking" />

                {/* Title */}
                <div className="mt-12 text-center">
                    <h1 className="text-5xl font-extrabold drop-shadow-md">Whale Tracking</h1>
                    <p className="mx-auto mt-3 max-w-lg text-lg text-white/70">
                        Monitor the largest wallets across major blockchain networks
                    </p>
                </div>

                {/* Stats Bar */}
                <div className="mx-auto mt-8 flex flex-wrap justify-center gap-6 px-4">
                    <div className="rounded-xl border border-white/10 bg-black/40 px-6 py-3 text-center backdrop-blur">
                        <div className="text-2xl font-bold">{whales.length}</div>
                        <div className="text-sm text-white/60">Tracked Whales</div>
                    </div>
                    <div className="rounded-xl border border-white/10 bg-black/40 px-6 py-3 text-center backdrop-blur">
                        <div className="text-2xl font-bold">{formatUsd(totalValue.toString())}</div>
                        <div className="text-sm text-white/60">Total Value</div>
                    </div>
                    {networkBreakdown.map((n) => (
                        <div
                            key={n.slug}
                            className="rounded-xl border border-white/10 bg-black/40 px-6 py-3 text-center backdrop-blur"
                        >
                            <div className="text-2xl font-bold">{n.count}</div>
                            <div className="text-sm text-white/60">{n.currency_symbol}</div>
                        </div>
                    ))}
                </div>

                {/* Network Filter Pills */}
                <div className="mx-auto mt-6 flex flex-wrap justify-center gap-2 px-4">
                    <button
                        onClick={() => handleFilter(null)}
                        className={`rounded-full border px-4 py-1.5 text-sm font-medium transition ${
                            !activeNetwork
                                ? 'border-white/40 bg-white/20 text-white'
                                : 'border-white/10 bg-black/30 text-white/60 hover:border-white/20 hover:text-white/80'
                        }`}
                    >
                        All
                    </button>
                    {networks.map((n) => (
                        <button
                            key={n.slug}
                            onClick={() => handleFilter(n.slug)}
                            className={`rounded-full border px-4 py-1.5 text-sm font-medium transition ${
                                activeNetwork === n.slug
                                    ? 'border-white/40 bg-white/20 text-white'
                                    : 'border-white/10 bg-black/30 text-white/60 hover:border-white/20 hover:text-white/80'
                            }`}
                        >
                            {n.name}
                        </button>
                    ))}
                </div>

                {/* Whale Cards Grid */}
                <div className="mx-auto mt-8 w-full max-w-7xl flex-1 px-4 pb-12">
                    {whales.length === 0 ? (
                        <div className="py-20 text-center text-white/50">
                            No whale wallets found for this network.
                        </div>
                    ) : (
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {whales.map((whale) => (
                                <WhaleCard
                                    key={whale.id}
                                    whale={whale}
                                    copiedAddr={copiedAddr}
                                    onCopy={(addr) =>
                                        copyToClipboard(addr, setCopiedAddr)
                                    }
                                    isTracked={trackedIds.includes(whale.id)}
                                    isAuthenticated={!!auth.user}
                                    onTrack={handleTrack}
                                />
                            ))}
                        </div>
                    )}
                </div>
        </PublicLayout>
    );
}
