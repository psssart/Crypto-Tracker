import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import DataTable, { Column, MobileRenderHelpers } from '@/Components/DataTable';
import DateRangeSelect from '@/Components/DateRangeSelect';
import { flashError, flashInfo } from '@/Components/FlashMessages';
import { Head, Link, router } from '@inertiajs/react';
import { Network, Transaction } from '@/types';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

interface WalletOption {
    id: number;
    address: string;
    custom_label: string | null;
    network: Network;
}

interface Props {
    wallets: WalletOption[];
    activeWalletId: number | null;
    transactions: Transaction[];
    dateFrom: string;
    dateTo: string;
    lastSyncedAt: string | null;
}

function truncateAddress(address: string): string {
    return address.slice(0, 6) + '...' + address.slice(-4);
}

function truncateHash(hash: string): string {
    return hash.slice(0, 10) + '...' + hash.slice(-6);
}

function formatAmount(amount: string): string {
    const num = parseFloat(amount);
    if (num === 0) return '0';
    if (Math.abs(num) < 0.000001) return num.toExponential(4);
    return num.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 8 });
}

type Direction = 'In' | 'Out' | 'Self';

function getDirection(tx: Transaction, walletAddress: string): Direction {
    const from = tx.from_address?.toLowerCase();
    const to = tx.to_address?.toLowerCase();
    const addr = walletAddress.toLowerCase();
    if (from === addr && to === addr) return 'Self';
    if (to === addr) return 'In';
    return 'Out';
}

const dirBadgeStyles: Record<Direction, string> = {
    In: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    Out: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    Self: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
};

function DirectionBadge({ direction }: { direction: Direction }) {
    return (
        <span
            className={`inline-block rounded px-2 py-0.5 text-xs font-medium ${dirBadgeStyles[direction]}`}
        >
            {direction}
        </span>
    );
}

const COOLDOWN_SECONDS = 60;

export default function Transactions({
    wallets,
    activeWalletId,
    transactions,
    dateFrom,
    dateTo,
    lastSyncedAt,
}: Props) {
    const activeWallet = wallets.find((w) => w.id === activeWalletId) ?? null;

    const [cooldown, setCooldown] = useState(0);
    const [fetching, setFetching] = useState(false);
    const lastFetchParams = useRef<string | null>(null);
    const timerRef = useRef<ReturnType<typeof setInterval> | null>(null);

    // Initialize cooldown from lastSyncedAt
    useEffect(() => {
        if (!lastSyncedAt) return;
        const elapsed = Math.floor((Date.now() - new Date(lastSyncedAt).getTime()) / 1000);
        const remaining = COOLDOWN_SECONDS - elapsed;
        if (remaining > 0) {
            setCooldown(remaining);
            lastFetchParams.current = `${activeWalletId}:${dateFrom}:${dateTo}`;
        }
    }, [lastSyncedAt, activeWalletId, dateFrom, dateTo]);

    // Countdown timer
    useEffect(() => {
        if (cooldown <= 0) {
            if (timerRef.current) clearInterval(timerRef.current);
            return;
        }
        timerRef.current = setInterval(() => {
            setCooldown((prev) => {
                if (prev <= 1) {
                    if (timerRef.current) clearInterval(timerRef.current);
                    return 0;
                }
                return prev - 1;
            });
        }, 1000);
        return () => {
            if (timerRef.current) clearInterval(timerRef.current);
        };
    }, [cooldown > 0]);

    const handleFetch = useCallback(() => {
        if (!activeWalletId || fetching || cooldown > 0) return;

        const currentParams = `${activeWalletId}:${dateFrom}:${dateTo}`;
        if (lastFetchParams.current === currentParams) {
            flashError('Change the wallet or date range before fetching again.');
            return;
        }

        setFetching(true);
        router.post(
            route('transactions.fetch'),
            { wallet_id: activeWalletId, date_from: dateFrom, date_to: dateTo },
            {
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => {
                    lastFetchParams.current = currentParams;
                    setCooldown(COOLDOWN_SECONDS);
                },
                onError: () => {
                    flashError('Failed to start fetch. Please try again later.');
                },
                onFinish: () => setFetching(false),
            },
        );
    }, [activeWalletId, dateFrom, dateTo, fetching, cooldown]);

    const navigate = (params: Record<string, string | number>) => {
        router.get(
            route('transactions.index'),
            { wallet: activeWalletId, date_from: dateFrom, date_to: dateTo, ...params },
            { preserveState: true },
        );
    };

    const handleWalletChange = (walletId: string) => {
        navigate({ wallet: walletId });
    };

    const handleDateChange = (from: string, to: string) => {
        navigate({ date_from: from, date_to: to });
    };

    const columns = useMemo<Column<Transaction>[]>(() => {
        if (!activeWallet) return [];
        const addr = activeWallet.address;
        const explorerBase = activeWallet.network.explorer_url;
        const symbol = activeWallet.network.currency_symbol;

        return [
            {
                key: 'direction',
                header: 'Dir',
                filterable: true,
                filterValue: (tx) => getDirection(tx, addr),
                render: (tx) => <DirectionBadge direction={getDirection(tx, addr)} />,
            },
            {
                key: 'hash',
                header: 'Hash',
                render: (tx) =>
                    explorerBase ? (
                        <a
                            href={`${explorerBase}/tx/${tx.hash}`}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="font-mono text-indigo-600 hover:text-indigo-800 dark:text-indigo-400"
                        >
                            {truncateHash(tx.hash)}
                        </a>
                    ) : (
                        <span className="font-mono text-gray-700 dark:text-gray-300">
                            {truncateHash(tx.hash)}
                        </span>
                    ),
            },
            {
                key: 'from',
                header: 'From',
                filterable: true,
                cellFilterable: true,
                filterValue: (tx) => tx.from_address,
                filterLabel: truncateAddress,
                render: (tx) => {
                    const isOwn = tx.from_address?.toLowerCase() === addr.toLowerCase();
                    return (
                        <span
                            className={`font-mono ${isOwn ? 'font-bold text-black dark:text-white' : 'text-gray-400 dark:text-gray-400'}`}
                        >
                            {truncateAddress(tx.from_address)}
                        </span>
                    );
                },
            },
            {
                key: 'to',
                header: 'To',
                filterable: true,
                cellFilterable: true,
                filterValue: (tx) => tx.to_address,
                filterLabel: truncateAddress,
                render: (tx) => {
                    const isOwn = tx.to_address?.toLowerCase() === addr.toLowerCase();
                    return (
                        <span
                            className={`font-mono ${isOwn ? 'font-bold text-black dark:text-white' : 'text-gray-600 dark:text-gray-400'}`}
                        >
                            {truncateAddress(tx.to_address)}
                        </span>
                    );
                },
            },
            {
                key: 'amount',
                header: 'Amount',
                sortable: true,
                sortValue: (tx) => parseFloat(tx.amount),
                headerClassName: 'text-right',
                cellClassName: 'text-right',
                render: (tx) => (
                    <span className="text-gray-900 dark:text-white">
                        {formatAmount(tx.amount)}{' '}
                        <span className="text-gray-500">{symbol}</span>
                    </span>
                ),
            },
            {
                key: 'fee',
                header: 'Fee',
                sortable: true,
                sortValue: (tx) => (tx.fee ? parseFloat(tx.fee) : null),
                headerClassName: 'text-right',
                cellClassName: 'text-right',
                render: (tx) => (
                    <span className="text-gray-500 dark:text-gray-400">
                        {tx.fee ? formatAmount(tx.fee) : '\u2014'}
                    </span>
                ),
            },
            {
                key: 'time',
                header: 'Time',
                sortable: true,
                sortValue: (tx) => (tx.mined_at ? new Date(tx.mined_at).getTime() : null),
                headerClassName: 'text-right',
                cellClassName: 'text-right',
                render: (tx) => (
                    <span className="text-gray-500 dark:text-gray-400">
                        {tx.mined_at ? new Date(tx.mined_at).toLocaleString() : 'Pending'}
                    </span>
                ),
            },
        ];
    }, [activeWallet]);

    const renderMobileCard = useMemo(() => {
        if (!activeWallet) return undefined;
        const addr = activeWallet.address;
        const explorerBase = activeWallet.network.explorer_url;
        const symbol = activeWallet.network.currency_symbol;

        return (tx: Transaction, { cellFilter, isCellFiltered }: MobileRenderHelpers) => {
            const direction = getDirection(tx, addr);
            const fromFiltered = isCellFiltered('from', tx.from_address);
            const toFiltered = isCellFiltered('to', tx.to_address);

            return (
                <div className="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div className="flex items-center justify-between">
                        <DirectionBadge direction={direction} />
                        <span className="text-xs text-gray-500 dark:text-gray-400">
                            {tx.mined_at ? new Date(tx.mined_at).toLocaleString() : 'Pending'}
                        </span>
                    </div>
                    <div className="mt-2 font-mono text-sm">
                        {explorerBase ? (
                            <a
                                href={`${explorerBase}/tx/${tx.hash}`}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="text-indigo-600 dark:text-indigo-400"
                            >
                                {truncateHash(tx.hash)}
                            </a>
                        ) : (
                            <span className="text-gray-700 dark:text-gray-300">
                                {truncateHash(tx.hash)}
                            </span>
                        )}
                    </div>
                    <div className="mt-2 space-y-1 text-xs text-gray-500 dark:text-gray-400">
                        <div className="flex items-center gap-1">
                            From:{' '}
                            <span className="font-mono">
                                {truncateAddress(tx.from_address)}
                            </span>
                            <button
                                type="button"
                                onClick={() => cellFilter('from', tx.from_address)}
                                className={`rounded p-0.5 ${
                                    fromFiltered
                                        ? 'text-indigo-600 dark:text-indigo-400'
                                        : 'text-gray-400'
                                }`}
                            >
                                <svg
                                    className="h-3 w-3"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth={2}
                                        d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"
                                    />
                                </svg>
                            </button>
                        </div>
                        <div className="flex items-center gap-1">
                            To:{' '}
                            <span className="font-mono">{truncateAddress(tx.to_address)}</span>
                            <button
                                type="button"
                                onClick={() => cellFilter('to', tx.to_address)}
                                className={`rounded p-0.5 ${
                                    toFiltered
                                        ? 'text-indigo-600 dark:text-indigo-400'
                                        : 'text-gray-400'
                                }`}
                            >
                                <svg
                                    className="h-3 w-3"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth={2}
                                        d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div className="mt-2 flex items-center justify-between">
                        <span className="text-sm font-semibold text-gray-900 dark:text-white">
                            {formatAmount(tx.amount)} {symbol}
                        </span>
                        {tx.fee && (
                            <span className="text-xs text-gray-500 dark:text-gray-400">
                                Fee: {formatAmount(tx.fee)}
                            </span>
                        )}
                    </div>
                </div>
            );
        };
    }, [activeWallet]);

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        Transactions
                    </h2>
                    <Link
                        href={route('watchlist.index')}
                        className="inline-flex items-center gap-1.5 rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                    >
                        <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                        </svg>
                        Watchlist
                    </Link>
                </div>
            }
        >
            <Head title="Transactions" />

            <div className="py-6">
                <div className="mx-auto max-w-7xl px-2 sm:px-6 lg:px-8">
                    {wallets.length === 0 ? (
                        <div className="py-12 text-center text-gray-500 dark:text-gray-400">
                            <p>No tracked wallets yet.</p>
                            <Link
                                href={route('watchlist.index')}
                                className="mt-2 inline-block text-indigo-600 hover:text-indigo-800 dark:text-indigo-400"
                            >
                                Add wallets from your Watchlist
                            </Link>
                        </div>
                    ) : (
                        <>
                            {/* Controls: Wallet Selector + Date Range + Fetch */}
                            <div className="mb-6 flex flex-wrap items-end gap-4">
                                <div>
                                    <label className="block text-xs font-medium text-gray-700 dark:text-gray-300">
                                        Wallet
                                    </label>
                                    <select
                                        value={activeWalletId ?? ''}
                                        onChange={(e) => handleWalletChange(e.target.value)}
                                        className="mt-1 block w-full rounded-md border-gray-300 py-1.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:w-56 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    >
                                        {wallets.map((w) => (
                                            <option key={w.id} value={w.id}>
                                                {w.custom_label || truncateAddress(w.address)} (
                                                {w.network.currency_symbol})
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <DateRangeSelect
                                    from={dateFrom}
                                    to={dateTo}
                                    onChange={handleDateChange}
                                />
                                <button
                                    type="button"
                                    onClick={handleFetch}
                                    disabled={!activeWalletId || fetching || cooldown > 0}
                                    className="ml-auto inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    {fetching ? (
                                        <svg
                                            className="h-4 w-4 animate-spin"
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
                                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                                            />
                                        </svg>
                                    )}
                                    {cooldown > 0 ? `Fetch (${cooldown}s)` : 'Fetch'}
                                </button>
                            </div>

                            <DataTable
                                columns={columns}
                                data={transactions ?? []}
                                rowKey={(tx) => tx.id}
                                mobileRender={renderMobileCard}
                            />
                        </>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
