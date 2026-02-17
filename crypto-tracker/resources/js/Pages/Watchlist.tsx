import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { Network, WatchlistWallet } from '@/types';
import { FormEventHandler, useState } from 'react';

interface Props {
    wallets: WatchlistWallet[];
    networks: Network[];
}

function truncateAddress(address: string): string {
    return address.slice(0, 6) + '...' + address.slice(-4);
}

function copyToClipboard(text: string) {
    navigator.clipboard.writeText(text);
}

function WalletCard({
    wallet,
    onEdit,
    onRemove,
}: {
    wallet: WatchlistWallet;
    onEdit: (wallet: WatchlistWallet) => void;
    onRemove: (wallet: WatchlistWallet) => void;
}) {
    const explorerUrl = wallet.network.explorer_url
        ? `${wallet.network.explorer_url}/address/${wallet.address}`
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
                        ${parseFloat(wallet.balance_usd).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                    </div>
                    {wallet.last_synced_at && (
                        <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Synced: {new Date(wallet.last_synced_at).toLocaleString()}
                        </p>
                    )}
                </div>
                <div className="flex gap-1">
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
            </div>
        </div>
    );
}

export default function Watchlist({ wallets, networks }: Props) {
    const [editingWallet, setEditingWallet] = useState<WatchlistWallet | null>(null);

    const addForm = useForm({
        network_id: '',
        address: '',
        custom_label: '',
    });

    const editForm = useForm<{
        custom_label: string;
        is_notified: boolean;
        notify_threshold_usd: string;
    }>({
        custom_label: '',
        is_notified: false,
        notify_threshold_usd: '',
    });

    const handleAdd: FormEventHandler = (e) => {
        e.preventDefault();
        addForm.post(route('watchlist.store'), {
            preserveScroll: true,
            onSuccess: () => addForm.reset(),
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
        editForm.setData({
            custom_label: wallet.pivot.custom_label || '',
            is_notified: wallet.pivot.is_notified,
            notify_threshold_usd: wallet.pivot.notify_threshold_usd || '',
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Watchlist
                </h2>
            }
        >
            <Head title="Watchlist" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    {/* Add Wallet Form */}
                    <div className="mb-6 overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
                        <div className="p-6">
                            <h3 className="mb-4 text-lg font-medium text-gray-900 dark:text-white">
                                Add Wallet
                            </h3>
                            <form onSubmit={handleAdd} className="flex flex-wrap items-end gap-4">
                                <div className="w-full sm:w-auto">
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Network
                                    </label>
                                    <select
                                        value={addForm.data.network_id}
                                        onChange={(e) => addForm.setData('network_id', e.target.value)}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    >
                                        <option value="">Select network</option>
                                        {networks.map((n) => (
                                            <option key={n.id} value={n.id}>
                                                {n.name} ({n.currency_symbol})
                                            </option>
                                        ))}
                                    </select>
                                    {addForm.errors.network_id && (
                                        <p className="mt-1 text-sm text-red-600">{addForm.errors.network_id}</p>
                                    )}
                                </div>
                                <div className="min-w-0 flex-1">
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Address
                                    </label>
                                    <input
                                        type="text"
                                        value={addForm.data.address}
                                        onChange={(e) => addForm.setData('address', e.target.value)}
                                        placeholder="0x..."
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    />
                                    {addForm.errors.address && (
                                        <p className="mt-1 text-sm text-red-600">{addForm.errors.address}</p>
                                    )}
                                </div>
                                <div className="w-full sm:w-48">
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Label (optional)
                                    </label>
                                    <input
                                        type="text"
                                        value={addForm.data.custom_label}
                                        onChange={(e) => addForm.setData('custom_label', e.target.value)}
                                        placeholder="My wallet"
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    />
                                </div>
                                <button
                                    type="submit"
                                    disabled={addForm.processing}
                                    className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50"
                                >
                                    Add
                                </button>
                            </form>
                        </div>
                    </div>

                    {/* Edit Modal */}
                    {editingWallet && (
                        <div className="mb-6 overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
                            <div className="p-6">
                                <h3 className="mb-4 text-lg font-medium text-gray-900 dark:text-white">
                                    Edit: {truncateAddress(editingWallet.address)}
                                </h3>
                                <form onSubmit={handleEdit} className="flex flex-wrap items-end gap-4">
                                    <div className="w-full sm:w-48">
                                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Label
                                        </label>
                                        <input
                                            type="text"
                                            value={editForm.data.custom_label}
                                            onChange={(e) =>
                                                editForm.setData('custom_label', e.target.value)
                                            }
                                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                        />
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <input
                                            type="checkbox"
                                            id="is_notified"
                                            checked={editForm.data.is_notified}
                                            onChange={(e) =>
                                                editForm.setData('is_notified', e.target.checked)
                                            }
                                            className="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600"
                                        />
                                        <label
                                            htmlFor="is_notified"
                                            className="text-sm text-gray-700 dark:text-gray-300"
                                        >
                                            Notifications
                                        </label>
                                    </div>
                                    <div className="w-full sm:w-48">
                                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Threshold (USD)
                                        </label>
                                        <input
                                            type="number"
                                            step="any"
                                            value={editForm.data.notify_threshold_usd}
                                            onChange={(e) =>
                                                editForm.setData('notify_threshold_usd', e.target.value)
                                            }
                                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                        />
                                    </div>
                                    <button
                                        type="submit"
                                        disabled={editForm.processing}
                                        className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50"
                                    >
                                        Save
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setEditingWallet(null)}
                                        className="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                                    >
                                        Cancel
                                    </button>
                                </form>
                            </div>
                        </div>
                    )}

                    {/* Wallet List */}
                    {wallets.length === 0 ? (
                        <div className="py-12 text-center text-gray-500 dark:text-gray-400">
                            No wallets in your watchlist yet. Add one above to get started.
                        </div>
                    ) : (
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {wallets.map((wallet) => (
                                <WalletCard
                                    key={wallet.id}
                                    wallet={wallet}
                                    onEdit={startEdit}
                                    onRemove={handleRemove}
                                />
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
