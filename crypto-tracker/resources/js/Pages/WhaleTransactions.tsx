import BarChart from '@/Components/Charts/BarChart';
import DataTable, { Column, MobileRenderHelpers } from '@/Components/DataTable';
import PublicLayout from '@/Layouts/PublicLayout';
import { Network, Transaction } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ReactNode, useMemo, useState } from 'react';

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

type BucketSize = 'minute' | 'hour' | 'day' | 'week' | 'month';

function chooseBucketSize(transactions: Transaction[]): BucketSize {
    const timestamps = transactions
        .filter((tx) => tx.mined_at)
        .map((tx) => new Date(tx.mined_at!).getTime());
    if (timestamps.length < 2) return 'day';
    const min = Math.min(...timestamps);
    const max = Math.max(...timestamps);
    const spanHours = (max - min) / (1000 * 60 * 60);
    if (spanHours <= 6) return 'minute';
    if (spanHours <= 72) return 'hour';
    if (spanHours <= 60 * 24) return 'day';
    if (spanHours <= 365 * 24) return 'week';
    return 'month';
}

function toBucketKey(date: Date, size: BucketSize): string {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    const h = String(date.getHours()).padStart(2, '0');
    const min = String(date.getMinutes()).padStart(2, '0');
    switch (size) {
        case 'minute':
            return `${y}-${m}-${d}T${h}:${min}`;
        case 'hour':
            return `${y}-${m}-${d}T${h}`;
        case 'day':
            return `${y}-${m}-${d}`;
        case 'week': {
            const day = date.getDay();
            const weekStart = new Date(date);
            weekStart.setDate(date.getDate() - day);
            const wy = weekStart.getFullYear();
            const wm = String(weekStart.getMonth() + 1).padStart(2, '0');
            const wd = String(weekStart.getDate()).padStart(2, '0');
            return `${wy}-${wm}-${wd}`;
        }
        case 'month':
            return `${y}-${m}`;
    }
}

const shortMonth = [
    'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
    'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec',
];

function formatBucketLabel(key: string, size: BucketSize): string {
    switch (size) {
        case 'minute':
            return key.slice(11);
        case 'hour': {
            const dt = new Date(key + ':00:00');
            return `${shortMonth[dt.getMonth()]} ${dt.getDate()} ${String(dt.getHours()).padStart(2, '0')}:00`;
        }
        case 'day':
        case 'week': {
            const dt = new Date(key + 'T00:00:00');
            return `${shortMonth[dt.getMonth()]} ${dt.getDate()}`;
        }
        case 'month': {
            const [y, mo] = key.split('-');
            return `${shortMonth[parseInt(mo, 10) - 1]} ${y}`;
        }
    }
}

interface BucketTxDetail {
    from: string;
    to: string;
    amount: string;
    fee: string | null;
    direction: Direction;
}

interface ChartBucket {
    label: string;
    inAmount: number;
    outAmount: number;
    selfAmount: number;
    details: BucketTxDetail[];
}

function bucketTransactions(transactions: Transaction[], walletAddress: string): ChartBucket[] {
    const timestamped = transactions.filter((tx) => tx.mined_at);
    if (timestamped.length === 0) return [];

    const size = chooseBucketSize(timestamped);
    const bucketMap = new Map<
        string,
        { in: number; out: number; self: number; details: BucketTxDetail[] }
    >();

    for (const tx of timestamped) {
        const date = new Date(tx.mined_at!);
        const key = toBucketKey(date, size);
        let bucket = bucketMap.get(key);
        if (!bucket) {
            bucket = { in: 0, out: 0, self: 0, details: [] };
            bucketMap.set(key, bucket);
        }
        const dir = getDirection(tx, walletAddress);
        const amount = parseFloat(tx.amount);
        if (dir === 'In') bucket.in += amount;
        else if (dir === 'Out') bucket.out += amount;
        else bucket.self += amount;
        bucket.details.push({
            from: tx.from_address,
            to: tx.to_address,
            amount: tx.amount,
            fee: tx.fee,
            direction: dir,
        });
    }

    const sortedKeys = Array.from(bucketMap.keys()).sort();
    return sortedKeys.map((key) => {
        const b = bucketMap.get(key)!;
        return {
            label: formatBucketLabel(key, size),
            inAmount: b.in,
            outAmount: b.out > 0 ? -b.out : 0,
            selfAmount: b.self,
            details: b.details,
        };
    });
}

function formatCompactAmount(value: number): string {
    const abs = Math.abs(value);
    if (abs === 0) return '0';
    if (abs < 0.0001) return value.toExponential(1);
    if (abs < 1) return value.toFixed(4);
    return new Intl.NumberFormat(undefined, {
        notation: 'compact',
        maximumFractionDigits: 2,
    }).format(value);
}

function TransactionTooltip({ active, payload, label }: any) {
    if (!active || !payload?.length) return null;
    const details: BucketTxDetail[] = payload[0]?.payload?.details ?? [];

    const inTotal = payload.find((p: any) => p.dataKey === 'inAmount')?.value ?? 0;
    const outTotal = payload.find((p: any) => p.dataKey === 'outAmount')?.value ?? 0;
    const selfTotal = payload.find((p: any) => p.dataKey === 'selfAmount')?.value ?? 0;

    const dirGroups: { label: string; color: string; total: number; txs: BucketTxDetail[] }[] = [];
    if (inTotal > 0)
        dirGroups.push({
            label: 'In',
            color: '#22c55e',
            total: inTotal,
            txs: details.filter((d) => d.direction === 'In'),
        });
    if (outTotal < 0)
        dirGroups.push({
            label: 'Out',
            color: '#ef4444',
            total: Math.abs(outTotal),
            txs: details.filter((d) => d.direction === 'Out'),
        });
    if (selfTotal > 0)
        dirGroups.push({
            label: 'Self',
            color: '#6b7280',
            total: selfTotal,
            txs: details.filter((d) => d.direction === 'Self'),
        });

    return (
        <div className="rounded-lg border border-gray-200 bg-white px-3 py-2 shadow-lg dark:border-gray-700 dark:bg-gray-800">
            <p className="mb-1.5 text-xs font-semibold text-gray-700 dark:text-gray-200">
                {label}
            </p>
            {dirGroups.map((g) => (
                <div key={g.label} className="mb-1.5 last:mb-0">
                    <p className="text-xs font-medium" style={{ color: g.color }}>
                        {g.label}: {formatCompactAmount(g.total)}
                    </p>
                    {g.txs.slice(0, 3).map((tx, i) => (
                        <p key={i} className="pl-2 text-[11px] text-gray-500 dark:text-gray-400">
                            {truncateAddress(tx.from)} → {truncateAddress(tx.to)}:{' '}
                            {formatAmount(tx.amount)}
                            {tx.fee && ` (fee: ${formatAmount(tx.fee)})`}
                        </p>
                    ))}
                    {g.txs.length > 3 && (
                        <p className="pl-2 text-[11px] italic text-gray-400 dark:text-gray-500">
                            and {g.txs.length - 3} more...
                        </p>
                    )}
                </div>
            ))}
        </div>
    );
}

const CHART_TYPES = [{ value: 'bar', label: 'Bar Chart' }];

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

    const renderViz = useMemo<
        (sortedData: Transaction[], chartType: string) => ReactNode
    >(() => {
        return (sortedData: Transaction[], _chartType: string) => {
            const chartData = bucketTransactions(sortedData, addr);
            if (chartData.length === 0) {
                return (
                    <p className="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                        No timestamped transactions to chart.
                    </p>
                );
            }
            return (
                <BarChart
                    data={chartData}
                    xAxisKey="label"
                    series={[
                        { dataKey: 'inAmount', name: 'In', color: '#22c55e', stackId: 'stack' },
                        { dataKey: 'outAmount', name: 'Out', color: '#ef4444', stackId: 'stack' },
                        { dataKey: 'selfAmount', name: 'Self', color: '#6b7280', stackId: 'self' },
                    ]}
                    stackOffset="sign"
                    yAxisFormatter={formatCompactAmount}
                    tooltipContent={TransactionTooltip}
                    xAxisAngle={chartData.length > 15 ? -45 : 0}
                />
            );
        };
    }, [addr]);

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
                        renderVisualization={renderViz}
                        chartTypes={CHART_TYPES}
                    />
                )}
            </div>
        </PublicLayout>
    );
}
