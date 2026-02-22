import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { flashError } from '@/Components/FlashMessages';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Network, WatchlistWallet } from '@/types';
import { FormEventHandler, useEffect, useRef, useState } from 'react';

interface Props {
    wallets: WatchlistWallet[];
    networks: Network[];
    hasTelegramLinked: boolean;
    nonEvmSlugs: string[];
    isAtFreeLimit: boolean;
}

function truncateAddress(address: string): string {
    return address.slice(0, 6) + '...' + address.slice(-4);
}

function copyToClipboard(text: string) {
    navigator.clipboard.writeText(text);
}

function isValidAddress(address: string, networkSlug: string): boolean {
    const trimmed = address.trim();
    if (!trimmed) return false;
    switch (networkSlug) {
        case 'ethereum':
        case 'polygon':
        case 'bsc':
        case 'arbitrum':
        case 'base':
        case 'optimism':
        case 'avalanche':
        case 'fantom':
        case 'cronos':
        case 'gnosis':
        case 'linea':
        case 'flow':
        case 'chiliz':
        case 'pulsechain':
        case 'sei':
        case 'ronin':
        case 'lisk':
        case 'monad':
        case 'hyperevm':
        case 'palm':
            return /^0x[0-9a-fA-F]{40}$/.test(trimmed);
        case 'solana':
            return /^[1-9A-HJ-NP-Za-km-z]{32,44}$/.test(trimmed);
        case 'bitcoin':
            return /^(1[a-km-zA-HJ-NP-Z1-9]{25,34}|3[a-km-zA-HJ-NP-Z1-9]{25,34}|bc1[a-z0-9]{39,59})$/.test(
                trimmed,
            );
        case 'tron':
            return /^T[1-9A-HJ-NP-Za-km-z]{33}$/.test(trimmed);
        default:
            return trimmed.length > 0;
    }
}

function WalletCard({
    wallet,
    onEdit,
    onRemove,
    isNonEvm,
}: {
    wallet: WatchlistWallet;
    onEdit: (wallet: WatchlistWallet) => void;
    onRemove: (wallet: WatchlistWallet) => void;
    isNonEvm: boolean;
}) {
    const explorerUrl = wallet.network.explorer_url
        ? wallet.network.slug === 'tron'
            ? `${wallet.network.explorer_url}/#/address/${wallet.address}`
            : `${wallet.network.explorer_url}/address/${wallet.address}`
        : null;

    return (
        <div className="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div className="flex items-start justify-between">
                <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-2">
                        <span className="rounded bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200">
                            {wallet.network.currency_symbol}
                        </span>
                        {wallet.pivot.custom_label && (
                            <span className="truncate text-sm font-medium text-gray-900 dark:text-white">
                                {wallet.pivot.custom_label}
                            </span>
                        )}
                    </div>
                    <div className="mt-1 flex items-center gap-2">
                        <code className="text-sm text-gray-600 dark:text-gray-400">
                            {truncateAddress(wallet.address)}
                        </code>
                        <button
                            onClick={() => copyToClipboard(wallet.address)}
                            className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                            title="Copy address"
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
                                    d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"
                                />
                            </svg>
                        </button>
                        {explorerUrl && (
                            <a
                                href={explorerUrl}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="text-indigo-500 hover:text-indigo-700 dark:text-indigo-400"
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
                    <div className="mt-2 text-lg font-semibold text-gray-900 dark:text-white">
                        $
                        {parseFloat(wallet.balance_usd).toLocaleString(undefined, {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2,
                        })}
                    </div>
                    {!isNonEvm && (
                        <div className="mt-1 flex flex-wrap gap-1">
                            {wallet.pivot.notify_direction !== 'all' && (
                                <span className="rounded bg-blue-100 px-1.5 py-0.5 text-xs text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                    {wallet.pivot.notify_direction}
                                </span>
                            )}
                            {wallet.pivot.notify_cooldown_minutes != null && (
                                <span className="rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-800 dark:bg-amber-900 dark:text-amber-200">
                                    {wallet.pivot.notify_cooldown_minutes}m cooldown
                                </span>
                            )}
                        </div>
                    )}
                    {wallet.pivot.notes && (
                        <p className="mt-1 line-clamp-2 text-xs text-gray-500 dark:text-gray-400">
                            {wallet.pivot.notes}
                        </p>
                    )}
                    {wallet.last_synced_at && (
                        <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Synced: {new Date(wallet.last_synced_at).toLocaleString()}
                        </p>
                    )}
                </div>
                <div className="flex flex-col items-end gap-1.5">
                    <div className="flex gap-1">
                        <Link
                            href={route('transactions.index', { wallet: wallet.id })}
                            className="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-300"
                            title="Transactions"
                        >
                            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth={2}
                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"
                                />
                            </svg>
                        </Link>
                        <button
                            onClick={() => onEdit(wallet)}
                            className="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-300"
                            title="Edit"
                        >
                            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth={2}
                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                                />
                            </svg>
                        </button>
                        <button
                            onClick={() => onRemove(wallet)}
                            className="rounded p-1 text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20 dark:hover:text-red-400"
                            title="Remove"
                        >
                            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth={2}
                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                />
                            </svg>
                        </button>
                    </div>
                    <span className="text-xs text-gray-400 dark:text-gray-500">
                        {wallet.transactions_count} tx{wallet.transactions_count !== 1 ? 's' : ''}
                    </span>
                </div>
            </div>
        </div>
    );
}

export default function Watchlist({ wallets, networks, hasTelegramLinked, nonEvmSlugs, isAtFreeLimit }: Props) {
    const nonEvmSet = new Set(nonEvmSlugs);
    const [networkFilter, setNetworkFilter] = useState('');
    const [editingWallet, setEditingWallet] = useState<WatchlistWallet | null>(null);
    const [showAddForm, setShowAddForm] = useState(false);
    const [addressStatus, setAddressStatus] = useState<'idle' | 'validating' | 'valid' | 'invalid'>(
        'idle',
    );
    const [showAddAdvanced, setShowAddAdvanced] = useState(false);
    const [showEditAdvanced, setShowEditAdvanced] = useState(false);
    const validationTimer = useRef<ReturnType<typeof setTimeout>>();

    const addForm = useForm<{
        network_id: string;
        address: string;
        custom_label: string;
        is_notified: boolean;
        notify_threshold_usd: string;
        notify_via: string;
        notify_direction: string;
        notify_cooldown_minutes: string;
        notes: string;
    }>({
        network_id: '',
        address: '',
        custom_label: '',
        is_notified: false,
        notify_threshold_usd: '',
        notify_via: 'email',
        notify_direction: 'all',
        notify_cooldown_minutes: '',
        notes: '',
    });

    const editForm = useForm<{
        custom_label: string;
        is_notified: boolean;
        notify_threshold_usd: string;
        notify_via: string;
        notify_direction: string;
        notify_cooldown_minutes: string;
        notes: string;
    }>({
        custom_label: '',
        is_notified: false,
        notify_threshold_usd: '',
        notify_via: 'email',
        notify_direction: 'all',
        notify_cooldown_minutes: '',
        notes: '',
    });

    const selectedNetwork = networks.find((n) => n.id.toString() === addForm.data.network_id);
    const isAddNetworkNonEvm = selectedNetwork ? nonEvmSet.has(selectedNetwork.slug) : false;

    useEffect(() => {
        const address = addForm.data.address.trim();
        if (!address || !selectedNetwork) {
            setAddressStatus('idle');
            return;
        }

        setAddressStatus('validating');
        clearTimeout(validationTimer.current);
        validationTimer.current = setTimeout(() => {
            setAddressStatus(isValidAddress(address, selectedNetwork.slug) ? 'valid' : 'invalid');
        }, 300);

        return () => clearTimeout(validationTimer.current);
    }, [addForm.data.address, addForm.data.network_id]);

    const handleAdd: FormEventHandler = (e) => {
        e.preventDefault();
        if (addressStatus !== 'valid') return;
        addForm.post(route('watchlist.store'), {
            preserveScroll: true,
            onSuccess: () => {
                addForm.reset();
                setAddressStatus('idle');
                setShowAddAdvanced(false);
            },
            onError: (errors) => {
                if (errors.limit) {
                    flashError(
                        <span>
                            You have reached the free limit of tracked wallets.{' '}
                            <a href={route('integrations.index')} className="underline font-semibold">
                                Configure your API keys
                            </a>{' '}
                            to track more.
                        </span>,
                    );
                }
            },
        });
    };

    const handleEdit: FormEventHandler = (e) => {
        e.preventDefault();
        if (!editingWallet) return;
        editForm.patch(route('watchlist.update', editingWallet.id), {
            preserveScroll: true,
            onSuccess: () => setEditingWallet(null),
        });
    };

    const handleRemove = (wallet: WatchlistWallet) => {
        if (!confirm('Remove this wallet from your watchlist?')) return;
        router.delete(route('watchlist.destroy', wallet.id), {
            preserveScroll: true,
        });
    };

    const startEdit = (wallet: WatchlistWallet) => {
        setEditingWallet(wallet);
        setShowEditAdvanced(false);
        editForm.setData({
            custom_label: wallet.pivot.custom_label || '',
            is_notified: wallet.pivot.is_notified,
            notify_threshold_usd: wallet.pivot.notify_threshold_usd || '',
            notify_via: wallet.pivot.notify_via || 'email',
            notify_direction: wallet.pivot.notify_direction || 'all',
            notify_cooldown_minutes: wallet.pivot.notify_cooldown_minutes?.toString() || '',
            notes: wallet.pivot.notes || '',
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        Watchlist
                    </h2>
                    {wallets.length > 0 && (
                        <select
                            value={networkFilter}
                            onChange={(e) => setNetworkFilter(e.target.value)}
                            className="rounded-md border-gray-300 py-1.5 pl-3 pr-8 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        >
                            <option value="">All networks</option>
                            {networks
                                .filter((n) => wallets.some((w) => w.network.id === n.id))
                                .map((n) => (
                                    <option key={n.id} value={n.id}>
                                        {n.currency_symbol}
                                    </option>
                                ))}
                        </select>
                    )}
                </div>
            }
        >
            <Head title="Watchlist" />

            <div className="py-6">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    {!hasTelegramLinked &&
                        wallets.some(
                            (w) =>
                                w.pivot.is_notified &&
                                (w.pivot.notify_via === 'telegram' || w.pivot.notify_via === 'both'),
                        ) && (
                            <div className="mb-6 rounded-lg border border-amber-300 bg-amber-50 p-4 dark:border-amber-600 dark:bg-amber-900/20">
                                <div className="flex items-start gap-3">
                                    <svg
                                        className="mt-0.5 h-5 w-5 shrink-0 text-amber-500 dark:text-amber-400"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                            strokeWidth={2}
                                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"
                                        />
                                    </svg>
                                    <p className="text-sm text-amber-800 dark:text-amber-200">
                                        You have wallets with Telegram notifications enabled, but your
                                        Telegram account is not connected.{' '}
                                        <Link
                                            href={route('profile.edit')}
                                            className="font-semibold underline hover:text-amber-900 dark:hover:text-amber-100"
                                        >
                                            Configure it here
                                        </Link>
                                    </p>
                                </div>
                            </div>
                        )}

                    {isAtFreeLimit && (
                        <div className="mb-6 rounded-lg border border-amber-300 bg-amber-50 p-4 dark:border-amber-600 dark:bg-amber-900/20">
                            <div className="flex items-start gap-3">
                                <svg
                                    className="mt-0.5 h-5 w-5 shrink-0 text-amber-500 dark:text-amber-400"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth={2}
                                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"
                                    />
                                </svg>
                                <p className="text-sm text-amber-800 dark:text-amber-200">
                                    You have reached the free limit of 4 tracked wallets.{' '}
                                    <Link
                                        href={route('integrations.index')}
                                        className="font-semibold underline hover:text-amber-900 dark:hover:text-amber-100"
                                    >
                                        Configure your Moralis, Alchemy, or Etherscan API keys
                                    </Link>{' '}
                                    to track more.
                                </p>
                            </div>
                        </div>
                    )}

                    {/* Add Wallet - Collapsible */}
                    <div className="mb-6">
                        {!showAddForm ? (
                            <button
                                onClick={() => setShowAddForm(true)}
                                className="flex w-full items-center justify-center gap-2 rounded-lg border-2 border-dashed border-gray-300 px-4 py-3 text-sm font-medium text-gray-500 transition-colors hover:border-indigo-400 hover:text-indigo-600 dark:border-gray-600 dark:text-gray-400 dark:hover:border-indigo-500 dark:hover:text-indigo-400"
                            >
                                <svg
                                    className="h-5 w-5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth={2}
                                        d="M12 4v16m8-8H4"
                                    />
                                </svg>
                                Add Wallet
                            </button>
                        ) : (
                            <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
                                <div className="p-4">
                                    <form onSubmit={handleAdd} className="space-y-3">
                                        <div className="flex flex-wrap items-top gap-3">
                                            <div className="w-full sm:w-auto">
                                                <label className="block text-xs font-medium text-gray-700 dark:text-gray-300">
                                                    Network
                                                </label>
                                                <select
                                                    value={addForm.data.network_id}
                                                    onChange={(e) =>
                                                        addForm.setData(
                                                            'network_id',
                                                            e.target.value,
                                                        )
                                                    }
                                                    className="mt-1 block w-full rounded-md border-gray-300 py-1.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                                >
                                                    <option value="">Select network</option>
                                                    {networks.map((n) => (
                                                        <option key={n.id} value={n.id}>
                                                            {n.name} ({n.currency_symbol})
                                                        </option>
                                                    ))}
                                                </select>
                                                {addForm.errors.network_id && (
                                                    <p className="mt-1 text-xs text-red-600">
                                                        {addForm.errors.network_id}
                                                    </p>
                                                )}
                                            </div>
                                            <div className="min-w-40 flex-1">
                                                <label className="block text-xs font-medium text-gray-700 dark:text-gray-300">
                                                    Address
                                                </label>
                                                <div className="relative mt-1">
                                                    <input
                                                        type="text"
                                                        value={addForm.data.address}
                                                        onChange={(e) =>
                                                            addForm.setData(
                                                                'address',
                                                                e.target.value,
                                                            )
                                                        }
                                                        placeholder="0x..."
                                                        className={`block w-full rounded-md py-1.5 pr-8 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:text-white ${
                                                            addressStatus === 'valid'
                                                                ? 'border-green-400 dark:border-green-500'
                                                                : addressStatus === 'invalid'
                                                                  ? 'border-red-400 dark:border-red-500'
                                                                  : 'border-gray-300 dark:border-gray-600'
                                                        }`}
                                                    />
                                                    <div className="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2">
                                                        {addressStatus === 'validating' && (
                                                            <svg
                                                                className="h-4 w-4 animate-spin text-gray-400"
                                                                fill="none"
                                                                viewBox="0 0 24 24"
                                                            >
                                                                <circle
                                                                    className="opacity-25"
                                                                    cx="12"
                                                                    cy="12"
                                                                    r="10"
                                                                    stroke="currentColor"
                                                                    strokeWidth="4"
                                                                />
                                                                <path
                                                                    className="opacity-75"
                                                                    fill="currentColor"
                                                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
                                                                />
                                                            </svg>
                                                        )}
                                                        {addressStatus === 'valid' && (
                                                            <svg
                                                                className="h-4 w-4 text-green-500"
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
                                                        )}
                                                        {addressStatus === 'invalid' && (
                                                            <svg
                                                                className="h-4 w-4 text-red-500"
                                                                fill="none"
                                                                stroke="currentColor"
                                                                viewBox="0 0 24 24"
                                                            >
                                                                <path
                                                                    strokeLinecap="round"
                                                                    strokeLinejoin="round"
                                                                    strokeWidth={2}
                                                                    d="M6 18L18 6M6 6l12 12"
                                                                />
                                                            </svg>
                                                        )}
                                                    </div>
                                                </div>
                                                {addForm.errors.address && (
                                                    <p className="mt-1 text-xs text-red-600">
                                                        {addForm.errors.address}
                                                    </p>
                                                )}
                                            </div>
                                            <div className="w-full sm:w-36">
                                                <label className="block text-xs font-medium text-gray-700 dark:text-gray-300">
                                                    Label (optional)
                                                </label>
                                                <input
                                                    type="text"
                                                    value={addForm.data.custom_label}
                                                    onChange={(e) =>
                                                        addForm.setData(
                                                            'custom_label',
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder="My wallet"
                                                    className="mt-1 block w-full rounded-md border-gray-300 py-1.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                                />
                                            </div>
                                            {!isAddNetworkNonEvm && (
                                                <>
                                                    <div className="flex items-center gap-1.5">
                                                        <input
                                                            type="checkbox"
                                                            id="add_is_notified"
                                                            checked={addForm.data.is_notified}
                                                            onChange={(e) =>
                                                                addForm.setData(
                                                                    'is_notified',
                                                                    e.target.checked,
                                                                )
                                                            }
                                                            className="h-3.5 w-3.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600"
                                                        />
                                                        <label
                                                            htmlFor="add_is_notified"
                                                            className="flex flex-col text-xs text-gray-700 dark:text-gray-300"
                                                        >
                                                            <span>Notify about</span>
                                                            <span>transactions</span>
                                                        </label>
                                                    </div>
                                                    {addForm.data.is_notified && (
                                                        <div className="w-full sm:w-36">
                                                            <label className="block text-xs font-medium text-gray-700 dark:text-gray-300">
                                                                Notify via
                                                            </label>
                                                            <select
                                                                value={addForm.data.notify_via}
                                                                onChange={(e) =>
                                                                    addForm.setData(
                                                                        'notify_via',
                                                                        e.target.value,
                                                                    )
                                                                }
                                                                className="mt-1 block w-full rounded-md border-gray-300 py-1.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                                            >
                                                                <option value="email">Email</option>
                                                                <option value="telegram">Telegram</option>
                                                                <option value="both">Both</option>
                                                            </select>
                                                        </div>
                                                    )}
                                                </>
                                            )}
                                        </div>

                                        {/* Advanced Settings Toggle */}
                                        <button
                                            type="button"
                                            onClick={() => setShowAddAdvanced(!showAddAdvanced)}
                                            className="flex items-center gap-1 text-xs font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                                        >
                                            <svg
                                                className={`h-3 w-3 transition-transform ${showAddAdvanced ? 'rotate-90' : ''}`}
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    strokeLinecap="round"
                                                    strokeLinejoin="round"
                                                    strokeWidth={2}
                                                    d="M9 5l7 7-7 7"
                                                />
                                            </svg>
                                            Advanced Settings
                                        </button>

                                        {showAddAdvanced && (
                                            <div className="space-y-3">
                                                {!isAddNetworkNonEvm && addForm.data.is_notified && (
                                                    <div className="flex flex-wrap items-end gap-3 pb-3 border-b border-gray-100 dark:border-gray-700">
                                                        <div className="w-full">
                                                            <h6 className="text-sm">Notification settings</h6>
                                                        </div>
                                                        <div className="w-full sm:w-36">
                                                            <label
                                                                className="block text-xs font-medium text-gray-700 dark:text-gray-300">
                                                                Threshold (USD)
                                                            </label>
                                                            <input
                                                                type="number"
                                                                step="any"
                                                                value={
                                                                    addForm.data
                                                                        .notify_threshold_usd
                                                                }
                                                                onChange={(e) =>
                                                                    addForm.setData(
                                                                        'notify_threshold_usd',
                                                                        e.target.value,
                                                                    )
                                                                }
                                                                className="mt-1 block w-full rounded-md border-gray-300 py-1.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                                            />
                                                        </div>
                                                        <div className="w-full sm:w-36">
                                                            <label
                                                                className="block text-xs font-medium text-gray-700 dark:text-gray-300">
                                                                Direction
                                                            </label>
                                                            <select
                                                                value={
                                                                    addForm.data.notify_direction
                                                                }
                                                                onChange={(e) =>
                                                                    addForm.setData(
                                                                        'notify_direction',
                                                                        e.target.value,
                                                                    )
                                                                }
                                                                className="mt-1 block w-full rounded-md border-gray-300 py-1.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                                            >
                                                                <option value="all">All</option>
                                                                <option value="incoming">
                                                                    Incoming
                                                                </option>
                                                                <option value="outgoing">
                                                                    Outgoing
                                                                </option>
                                                            </select>
                                                        </div>
                                                        <div className="w-full sm:w-36">
                                                            <label
                                                                className="flex items-center gap-1 text-xs font-medium text-gray-700 dark:text-gray-300">
                                                                Cooldown
                                                                <svg
                                                                    className="h-3 w-3 text-gray-400"
                                                                    fill="none"
                                                                    stroke="currentColor"
                                                                    viewBox="0 0 24 24"
                                                                >
                                                                    <title>
                                                                        Minimum minutes between
                                                                        alerts
                                                                    </title>
                                                                    <path
                                                                        strokeLinecap="round"
                                                                        strokeLinejoin="round"
                                                                        strokeWidth={2}
                                                                        d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                                                    />
                                                                </svg>
                                                            </label>
                                                            <input
                                                                type="number"
                                                                min="0"
                                                                max="10080"
                                                                value={
                                                                    addForm.data
                                                                        .notify_cooldown_minutes
                                                                }
                                                                onChange={(e) =>
                                                                    addForm.setData(
                                                                        'notify_cooldown_minutes',
                                                                        e.target.value,
                                                                    )
                                                                }
                                                                placeholder="No limit"
                                                                className="mt-1 block w-full rounded-md border-gray-300 py-1.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                                            />
                                                        </div>
                                                    </div>
                                                )}
                                                <div>
                                                    <label
                                                        className="block text-xs font-medium text-gray-700 dark:text-gray-300">
                                                    Notes
                                                    </label>
                                                    <textarea
                                                        value={addForm.data.notes}
                                                        onChange={(e) =>
                                                            addForm.setData(
                                                                'notes',
                                                                e.target.value,
                                                            )
                                                        }
                                                        rows={2}
                                                        placeholder="Personal notes about this wallet..."
                                                        className="mt-1 block w-full rounded-md border-gray-300 py-1.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                                    />
                                                </div>
                                            </div>
                                        )}

                                        <div className="flex gap-2">
                                            <button
                                                type="submit"
                                                disabled={
                                                    addForm.processing ||
                                                    addressStatus !== 'valid'
                                                }
                                                className="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50"
                                            >
                                                Add
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => {
                                                    setShowAddForm(false);
                                                    addForm.reset();
                                                    setAddressStatus('idle');
                                                    setShowAddAdvanced(false);
                                                }}
                                                className="rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                                            >
                                                Cancel
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Edit Modal */}
                    {editingWallet && (() => {
                        const isEditNetworkNonEvm = nonEvmSet.has(editingWallet.network.slug);
                        return (
                        <div className="mb-6 overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
                            <div className="p-4">
                                <h3 className="mb-3 text-md font-medium text-gray-900 dark:text-white">
                                    Edit: {truncateAddress(editingWallet.address)}
                                </h3>
                                <form onSubmit={handleEdit} className="space-y-3">
                                    <div className="flex flex-wrap items-top gap-3">
                                        <div className="w-full sm:w-40">
                                            <label className="block text-xs font-medium text-gray-700 dark:text-gray-300">
                                                Label
                                            </label>
                                            <input
                                                type="text"
                                                value={editForm.data.custom_label}
                                                onChange={(e) =>
                                                    editForm.setData('custom_label', e.target.value)
                                                }
                                                className="mt-1 block w-full rounded-md border-gray-300 py-1.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                            />
                                        </div>
                                        {!isEditNetworkNonEvm && (
                                            <>
                                                <div className="flex items-center gap-1.5 pb-1">
                                                    <input
                                                        type="checkbox"
                                                        id="is_notified"
                                                        checked={editForm.data.is_notified}
                                                        onChange={(e) =>
                                                            editForm.setData(
                                                                'is_notified',
                                                                e.target.checked,
                                                            )
                                                        }
                                                        className="h-3.5 w-3.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600"
                                                    />
                                                    <label
                                                        htmlFor="is_notified"
                                                        className="flex flex-col text-xs text-gray-700 dark:text-gray-300"
                                                    >
                                                        <span>Notify about</span>
                                                        <span>transactions</span>
                                                    </label>
                                                </div>
                                                {editForm.data.is_notified && (
                                                    <div className="w-full sm:w-36">
                                                        <label className="block text-xs font-medium text-gray-700 dark:text-gray-300">
                                                            Notify via
                                                        </label>
                                                        <select
                                                            value={editForm.data.notify_via}
                                                            onChange={(e) =>
                                                                editForm.setData(
                                                                    'notify_via',
                                                                    e.target.value,
                                                                )
                                                            }
                                                            className="mt-1 block w-full rounded-md border-gray-300 py-1.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                                        >
                                                            <option value="email">Email</option>
                                                            <option value="telegram">Telegram</option>
                                                            <option value="both">Both</option>
                                                        </select>
                                                    </div>
                                                )}
                                            </>
                                        )}
                                    </div>

                                    {/* Advanced Settings Toggle */}
                                    <button
                                        type="button"
                                        onClick={() => setShowEditAdvanced(!showEditAdvanced)}
                                        className="flex items-center gap-1 text-xs font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                                    >
                                        <svg
                                            className={`h-3 w-3 transition-transform ${showEditAdvanced ? 'rotate-90' : ''}`}
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                strokeLinecap="round"
                                                strokeLinejoin="round"
                                                strokeWidth={2}
                                                d="M9 5l7 7-7 7"
                                            />
                                        </svg>
                                        Advanced Settings
                                    </button>

                                    {showEditAdvanced && (
                                        <div className="space-y-3">
                                            {!isEditNetworkNonEvm && editForm.data.is_notified && (
                                                <div className="flex flex-wrap items-end gap-3 pb-3 border-b border-gray-100 dark:border-gray-700">
                                                    <div className="w-full">
                                                        <h6 className="text-sm">Notification settings</h6>
                                                    </div>
                                                    <div className="w-full sm:w-36">
                                                        <label className="block text-xs font-medium text-gray-700 dark:text-gray-300">
                                                            Threshold (USD)
                                                        </label>
                                                        <input
                                                            type="number"
                                                            step="any"
                                                            value={
                                                                editForm.data.notify_threshold_usd
                                                            }
                                                            onChange={(e) =>
                                                                editForm.setData(
                                                                    'notify_threshold_usd',
                                                                    e.target.value,
                                                                )
                                                            }
                                                            className="mt-1 block w-full rounded-md border-gray-300 py-1.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                                        />
                                                    </div>
                                                    <div className="w-full sm:w-36">
                                                        <label className="block text-xs font-medium text-gray-700 dark:text-gray-300">
                                                            Direction
                                                        </label>
                                                        <select
                                                            value={
                                                                editForm.data.notify_direction
                                                            }
                                                            onChange={(e) =>
                                                                editForm.setData(
                                                                    'notify_direction',
                                                                    e.target.value,
                                                                )
                                                            }
                                                            className="mt-1 block w-full rounded-md border-gray-300 py-1.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                                        >
                                                            <option value="all">All</option>
                                                            <option value="incoming">
                                                                Incoming
                                                            </option>
                                                            <option value="outgoing">
                                                                Outgoing
                                                            </option>
                                                        </select>
                                                    </div>
                                                    <div className="w-full sm:w-36">
                                                        <label className="flex items-center gap-1 text-xs font-medium text-gray-700 dark:text-gray-300">
                                                            Cooldown
                                                            <svg
                                                                className="h-3 w-3 text-gray-400"
                                                                fill="none"
                                                                stroke="currentColor"
                                                                viewBox="0 0 24 24"
                                                            >
                                                                <title>
                                                                    Minimum minutes between alerts
                                                                </title>
                                                                <path
                                                                    strokeLinecap="round"
                                                                    strokeLinejoin="round"
                                                                    strokeWidth={2}
                                                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                                                />
                                                            </svg>
                                                        </label>
                                                        <input
                                                            type="number"
                                                            min="0"
                                                            max="10080"
                                                            value={
                                                                editForm.data
                                                                    .notify_cooldown_minutes
                                                            }
                                                            onChange={(e) =>
                                                                editForm.setData(
                                                                    'notify_cooldown_minutes',
                                                                    e.target.value,
                                                                )
                                                            }
                                                            placeholder="No limit"
                                                            className="mt-1 block w-full rounded-md border-gray-300 py-1.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                                        />
                                                    </div>
                                                </div>
                                            )}
                                            <div>
                                                <label className="block text-xs font-medium text-gray-700 dark:text-gray-300">
                                                    Notes
                                                </label>
                                                <textarea
                                                    value={editForm.data.notes}
                                                    onChange={(e) =>
                                                        editForm.setData('notes', e.target.value)
                                                    }
                                                    rows={2}
                                                    placeholder="Personal notes about this wallet..."
                                                    className="mt-1 block w-full rounded-md border-gray-300 py-1.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                                />
                                            </div>
                                        </div>
                                    )}

                                    <div className="flex gap-2">
                                        <button
                                            type="submit"
                                            disabled={editForm.processing}
                                            className="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50"
                                        >
                                            Save
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => setEditingWallet(null)}
                                            className="rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                                        >
                                            Cancel
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        );
                    })()}

                    {/* Wallet List */}
                    {wallets.length === 0 ? (
                        <div className="py-12 text-center text-gray-500 dark:text-gray-400">
                            No wallets in your watchlist yet. Add one above to get started.
                        </div>
                    ) : (
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {wallets
                                .filter(
                                    (w) =>
                                        !networkFilter ||
                                        w.network.id.toString() === networkFilter,
                                )
                                .map((wallet) => (
                                    <WalletCard
                                        key={wallet.id}
                                        wallet={wallet}
                                        onEdit={startEdit}
                                        onRemove={handleRemove}
                                        isNonEvm={nonEvmSet.has(wallet.network.slug)}
                                    />
                                ))}
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
