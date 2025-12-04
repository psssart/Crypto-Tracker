// resources/js/Pages/Chart.tsx
import React, { useMemo, useState } from 'react';
import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {
    LiveWebSocketChart,
    ALL_WEB_SOCKET_SOURCES,
    WebSocketSourceConfig,
    WebSocketSourceAuth,
} from '@/Components/LiveWebSocketChart';

type CoinOption = {
    label: string;
    symbol: string;
};

const COIN_OPTIONS: CoinOption[] = [
    { label: 'Bitcoin (BTC)',  symbol: 'BTCUSDT' },
    { label: 'Ethereum (ETH)', symbol: 'ETHUSDT' },
    { label: 'Solana (SOL)',   symbol: 'SOLUSDT' },
    { label: 'BNB (BNB)',      symbol: 'BNBUSDT' },
    { label: 'XRP (XRP)',      symbol: 'XRPUSDT' },
];

type DataSourceId = WebSocketSourceConfig['id'];

const DEFAULT_SOURCE_ID: DataSourceId = 'binance';

type SourceHealthStatus = 'unknown' | 'checking' | 'ok' | 'error';

type SourceHealth = {
    status: SourceHealthStatus;
    message?: string;
};

type ChartProps = {
    integrationBackedSourceIds: string[]; // e.g. ['alltick', 'freecryptoapi', 'bybit']
    integrationsUrl: string;
    sourceAuth?: Record<string, WebSocketSourceAuth>; // keyed by WS source id
};

export default function Chart({
                                  integrationBackedSourceIds,
                                  integrationsUrl,
                                  sourceAuth = {},
                              }: ChartProps) {
    const [selectedSymbol, setSelectedSymbol] = useState<string>(
        COIN_OPTIONS[0].symbol,
    );
    const [selectedSourceId, setSelectedSourceId] =
        useState<DataSourceId>(DEFAULT_SOURCE_ID);

    const [health, setHealth] = useState<Record<string, SourceHealth>>({});

    const csrfToken =
        (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)
            ?.content ?? '';

    const isIntegrationBacked = (id: string): boolean =>
        integrationBackedSourceIds.includes(id);

    const getHealth = (id: string): SourceHealth =>
        health[id] ?? { status: isIntegrationBacked(id) ? 'unknown' : 'ok' };

    const handleSymbolChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
        setSelectedSymbol(e.target.value);
    };

    const currentCoin = COIN_OPTIONS.find(
        (coin) => coin.symbol === selectedSymbol,
    );

    const activeSource = useMemo(
        () =>
            ALL_WEB_SOCKET_SOURCES.find(
                (src) => src.id === selectedSourceId,
            ) ?? ALL_WEB_SOCKET_SOURCES[0],
        [selectedSourceId],
    );

    const selectedHealth = getHealth(selectedSourceId);
    const selectedIsBacked = isIntegrationBacked(selectedSourceId);

    const handleSourceClick = async (id: DataSourceId) => {
        // Public (non-integration) sources can switch immediately
        if (!isIntegrationBacked(id)) {
            setSelectedSourceId(id);
            return;
        }

        const current = getHealth(id);

        if (current.status === 'ok') {
            setSelectedSourceId(id);
            return;
        }

        setHealth((prev) => ({
            ...prev,
            [id]: { status: 'checking' },
        }));

        try {
            const response = await fetch(route('chart.checkSource'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ ws_source_id: id }),
            });

            const data = await response.json();

            if (response.ok && data.ok) {
                setHealth((prev) => ({
                    ...prev,
                    [id]: { status: 'ok', message: data.message },
                }));
                setSelectedSourceId(id);
            } else {
                setHealth((prev) => ({
                    ...prev,
                    [id]: {
                        status: 'error',
                        message: data.message || 'Data source is not available.',
                    },
                }));
            }
        } catch {
            setHealth((prev) => ({
                ...prev,
                [id]: {
                    status: 'error',
                    message: 'Failed to contact server for health check.',
                },
            }));
        }
    };

    const header = (
        <div className="space-y-4">
            <nav className="flex space-x-4 border-b border-gray-200 dark:border-gray-700">
                {ALL_WEB_SOCKET_SOURCES.map((item) => {
                    const h = getHealth(item.id);
                    const isActive = selectedSourceId === item.id;
                    const isBacked = isIntegrationBacked(item.id);

                    return (
                        <button
                            key={item.id}
                            type="button"
                            onClick={() => handleSourceClick(item.id as DataSourceId)}
                            className={
                                'px-4 py-2 -mb-px font-medium focus:outline-none ' +
                                (isActive
                                    ? 'border-b-2 border-blue-500 text-gray-900 dark:text-gray-100'
                                    : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200')
                            }
                        >
                            {item.label}
                            {isBacked && h.status === 'ok' && (
                                <span className="ml-2 text-xs text-green-500">●</span>
                            )}
                            {isBacked && h.status === 'error' && (
                                <span className="ml-2 text-xs text-red-500">●</span>
                            )}
                            {isBacked && h.status === 'checking' && (
                                <span className="ml-2 text-xs text-blue-500 animate-pulse">
                                    ●
                                </span>
                            )}
                        </button>
                    );
                })}
            </nav>

            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div className="space-y-1">
                    <h2 className="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                        Live Crypto Chart
                    </h2>
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        Data source: {activeSource.label} • Pair: {selectedSymbol}
                    </p>
                </div>

                <div className="inline-flex items-center gap-2">
                    <label
                        htmlFor="coin-select"
                        className="text-sm text-gray-600 dark:text-gray-300"
                    >
                        Select coin:
                    </label>
                    <select
                        id="coin-select"
                        value={selectedSymbol}
                        onChange={handleSymbolChange}
                        className="bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-md px-3 py-1 text-sm focus:outline-none focus:ring focus:ring-blue-500"
                    >
                        {COIN_OPTIONS.map((coin) => (
                            <option key={coin.symbol} value={coin.symbol}>
                                {coin.label} · {coin.symbol}
                            </option>
                        ))}
                    </select>
                </div>
            </div>

            {selectedIsBacked && selectedHealth.status === 'error' && (
                <div className="rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:bg-amber-900/40 dark:text-amber-100">
                    <p>{selectedHealth.message ?? 'This data source is not available.'}</p>
                    <p className="mt-1">
                        Fix the API key on the{' '}
                        <a
                            href={integrationsUrl}
                            className="font-medium underline"
                        >
                            Integrations page
                        </a>
                        .
                    </p>
                </div>
            )}
        </div>
    );

    const activeAuth: WebSocketSourceAuth =
        sourceAuth[activeSource.id] ?? {};

    return (
        <AuthenticatedLayout header={header}>
            <Head title="Live Chart" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden">
                        <div className="border-b border-gray-200 dark:border-gray-700 px-4 py-3 flex justify-between items-center text-sm">
                            <span className="font-medium text-gray-800 dark:text-gray-200">
                                {currentCoin?.label ?? selectedSymbol}
                            </span>
                            <span className="text-xs text-gray-500 dark:text-gray-400">
                                {activeSource.description}
                            </span>
                        </div>

                        <div className="px-2 pb-2 pt-1">
                            <div className="w-full" style={{ height: 420 }}>
                                <LiveWebSocketChart
                                    symbol={selectedSymbol}
                                    height={420}
                                    source={activeSource}
                                    auth={activeAuth}
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
