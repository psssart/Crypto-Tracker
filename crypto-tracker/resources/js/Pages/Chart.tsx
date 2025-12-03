// resources/js/Pages/Chart.tsx
import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import React, { useMemo, useState } from 'react';
import {
    LiveWebSocketChart,
    ALL_WEB_SOCKET_SOURCES,
    WebSocketSourceConfig,
} from '@/Components/LiveWebSocketChart';

type CoinOption = {
    label: string;
    symbol: string; // e.g. BTCUSDT
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

export default function Chart() {
    const [selectedSymbol, setSelectedSymbol] = useState<string>(
        COIN_OPTIONS[0].symbol,
    );
    const [selectedSourceId, setSelectedSourceId] =
        useState<DataSourceId>(DEFAULT_SOURCE_ID);

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

    // Header with Dashboard-like data source tabs + coin selector
    const header = (
        <div className="space-y-4">
            {/* Data source selector (tabs) */}
            <nav className="flex space-x-4 border-b border-gray-200 dark:border-gray-700">
                {ALL_WEB_SOCKET_SOURCES.map((item) => (
                    <button
                        key={item.id}
                        type="button"
                        onClick={() => setSelectedSourceId(item.id as DataSourceId)}
                        className={
                            `px-4 py-2 -mb-px font-medium focus:outline-none ` +
                            (selectedSourceId === item.id
                                ? 'text-gray-900 dark:text-gray-100 border-b-2 border-blue-500'
                                : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200')
                        }
                    >
                        {item.label}
                    </button>
                ))}
            </nav>

            {/* Title + info + coin selector */}
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
        </div>
    );

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
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
