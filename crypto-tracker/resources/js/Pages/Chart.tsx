// Chart.tsx
import React, { useState } from "react";
import { BinanceLiveChart } from "@/Components/BinanceLiveChart";

type CoinOption = {
    label: string;
    symbol: string; // Binance symbol
};

const COIN_OPTIONS: CoinOption[] = [
    { label: "Bitcoin (BTC)", symbol: "BTCUSDT" },
    { label: "Ethereum (ETH)", symbol: "ETHUSDT" },
    { label: "Solana (SOL)", symbol: "SOLUSDT" },
    { label: "BNB (BNB)", symbol: "BNBUSDT" },
    { label: "XRP (XRP)", symbol: "XRPUSDT" },
];

export const Chart: React.FC = () => {
    const [selectedSymbol, setSelectedSymbol] = useState<string>(
        COIN_OPTIONS[0].symbol
    );

    const handleChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
        setSelectedSymbol(e.target.value);
    };

    const currentCoin = COIN_OPTIONS.find(
        (coin) => coin.symbol === selectedSymbol
    );

    return (
        <div
            className="w-full min-h-screen flex flex-col items-center gap-4 p-4"
            style={{ backgroundColor: "#020617", color: "#e5e7eb" }}
        >
            <header className="w-full max-w-4xl flex flex-col gap-2">
                <h1 className="text-2xl font-bold">
                    Live Crypto Chart
                </h1>
                <p className="text-sm text-slate-400">
                    Data: Binance trade stream • Pair: {selectedSymbol}
                </p>

                <div className="mt-2 inline-flex items-center gap-2">
                    <label htmlFor="coin-select" className="text-sm">
                        Select coin:
                    </label>
                    <select
                        id="coin-select"
                        value={selectedSymbol}
                        onChange={handleChange}
                        className="bg-slate-900 border border-slate-700 rounded-md px-3 py-1 text-sm focus:outline-none focus:ring focus:ring-slate-500"
                    >
                        {COIN_OPTIONS.map((coin) => (
                            <option key={coin.symbol} value={coin.symbol}>
                                {coin.label} · {coin.symbol}
                            </option>
                        ))}
                    </select>
                </div>
            </header>

            <main className="w-full max-w-4xl">
                <div className="rounded-lg border border-slate-800 overflow-hidden bg-slate-900">
                    <div className="px-4 py-2 border-b border-slate-800 flex justify-between items-center text-sm">
            <span>
              {currentCoin?.label ?? selectedSymbol}
            </span>
                        <span className="text-xs text-slate-400">
              Live trades, no caching
            </span>
                    </div>
                    <BinanceLiveChart symbol={selectedSymbol} height={420} />
                </div>
            </main>
        </div>
    );
};
