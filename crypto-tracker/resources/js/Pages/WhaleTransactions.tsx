import DataTable, { Column, MobileRenderHelpers } from '@/Components/DataTable';
import PublicLayout from '@/Layouts/PublicLayout';
import { Network, Transaction } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';

interface WhaleWallet {
    id: number;
    address: string;
    balance_usd: string;
    metadata: { label?: string } | null;
    network: Network;
}

interface Props {
    wallet: WhaleWallet;
    transactions: Transaction[];
}

function truncateAddress(address: string): string {
    return address.slice(0, 6) + '...' + address.slice(-4);
}

function copyToClipboard(text: string, setCopied: (addr: string) => void) {
    navigator.clipboard.writeText(text);
    setCopied(text);
    setTimeout(() => setCopied(''), 1500);
}

function explorerAddressUrl(network: Network, address: string): string | null {
    if (!network.explorer_url) return null;
    if (network.slug === 'tron') return `${network.explorer_url}/#/address/${address}`;
    return `${network.explorer_url}/address/${address}`;
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

export default function WhaleTransactions({ wallet, transactions }: Props) {
    const addr = wallet.address;
    const explorerBase = wallet.network.explorer_url;
    const symbol = wallet.network.currency_symbol;
    const label = wallet.metadata?.label;
    const explorer = explorerAddressUrl(wallet.network, addr);
    const [copiedAddr, setCopiedAddr] = useState('');

    const columns = useMemo<Column<Transaction>[]>(
        () => [
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
                            className={`font-mono ${isOwn ? 'font-bold text-black dark:text-white' : 'text-gray-400 dark:text-gray-400'}`}
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
        ],
        [addr, explorerBase, symbol],
    );

    const renderMobileCard = useMemo(() => {
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
    }, [addr, explorerBase, symbol]);

    return (
        <PublicLayout>
            <Head title={`Transactions - ${label || truncateAddress(addr)}`} />

            <div className="mx-auto mt-6 w-full max-w-7xl px-4 pb-12">
                {/* Header */}
                <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <Link
                            href={route('whales')}
                            className="inline-flex items-center gap-1.5 text-sm text-gray-500 transition hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
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
                                    d="M15 19l-7-7 7-7"
                                />
                            </svg>
                            Back to Whales
                        </Link>
                        <h1 className="mt-1 text-2xl font-bold text-white">
                            {label || truncateAddress(addr)}
                        </h1>
                        <div className="mt-1 flex items-center gap-2">
                            <span className="rounded border border-gray-200 bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                                {symbol}
                            </span>
                            <code className="text-sm text-gray-500 dark:text-gray-400">
                                {truncateAddress(addr)}
                            </code>
                            <button
                                onClick={() => copyToClipboard(addr, setCopiedAddr)}
                                className="text-gray-400 transition hover:text-gray-600 dark:hover:text-gray-200"
                                title="Copy address"
                            >
                                {copiedAddr === addr ? (
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
                                    className="text-gray-400 transition hover:text-gray-600 dark:hover:text-gray-200"
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
                    <div className="text-right text-sm text-gray-500 dark:text-gray-400">
                        Last {transactions.length} transactions
                    </div>
                </div>

                {transactions.length === 0 ? (
                    <div className="py-20 text-center text-gray-500 dark:text-gray-400">
                        No transactions found for this wallet.
                    </div>
                ) : (
                    <DataTable
                        columns={columns}
                        data={transactions}
                        rowKey={(tx) => tx.id}
                        mobileRender={renderMobileCard}
                    />
                )}
            </div>
        </PublicLayout>
    );
}
