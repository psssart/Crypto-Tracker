import React, { useEffect, useRef } from 'react';
import {
    createChart,
    ColorType,
    IChartApi,
    ISeriesApi,
    LineData,
    Time,
    UTCTimestamp,
    LineSeries,
} from 'lightweight-charts';

type Props = {
    /** Binance symbol, can be 'BTCUSDT', 'ethusdt', etc. */
    symbol: string;
    /** Container height in px */
    height?: number;
};

interface BinanceTradeEvent {
    e: 'trade';
    E: number;    // event time (ms)
    s: string;    // symbol
    t: number;    // trade id
    p: string;    // price
    q: string;    // qty
    T: number;    // trade time (ms)
    m: boolean;
    M: boolean;
}

export const BinanceLiveChart: React.FC<Props> = ({
                                                      symbol,
                                                      height = 400,
                                                  }) => {
    const containerRef = useRef<HTMLDivElement | null>(null);
    const chartRef = useRef<IChartApi | null>(null);
    const seriesRef = useRef<ISeriesApi<'Line'> | null>(null);
    const wsRef = useRef<WebSocket | null>(null);

    // 1) Init chart once
    useEffect(() => {
        const container = containerRef.current;
        if (!container) return;

        const chart = createChart(container, {
            autoSize: true, // let LC + ResizeObserver handle width/height
            layout: {
                background: {
                    type: ColorType.Solid,
                    color: '#020617', // dark background
                },
                textColor: '#e5e7eb',
            },
            rightPriceScale: {
                borderVisible: false,
            },
            timeScale: {
                timeVisible: true,
                secondsVisible: true,
                borderVisible: false,
            },
            grid: {
                vertLines: { visible: false },
                horzLines: { visible: false },
            },
            crosshair: {
                // 1 = Magnet to series (CrosshairMode.Normal)
                mode: 1,
            },
        });

        const lineSeries = chart.addSeries(LineSeries, {
            lineWidth: 2,
            priceLineVisible: true,
            lastValueVisible: true,
        });

        chart.timeScale().fitContent();

        chartRef.current = chart;
        seriesRef.current = lineSeries;

        return () => {
            // cleanup on unmount
            wsRef.current?.close(1000, 'component unmount');
            chart.remove();
            chartRef.current = null;
            seriesRef.current = null;
        };
    }, []);

    // 2) WebSocket: reconnect whenever `symbol` changes
    useEffect(() => {
        const series = seriesRef.current;
        if (!series) return;

        const lowerSymbol = symbol.toLowerCase(); // Binance wants lowercase
        const wsUrl = `wss://stream.binance.com:9443/ws/${lowerSymbol}@trade`;

        const ws = new WebSocket(wsUrl);
        wsRef.current = ws;

        ws.onmessage = (event: MessageEvent<string>) => {
            try {
                const data: BinanceTradeEvent = JSON.parse(event.data);

                const price = parseFloat(data.p);
                if (!Number.isFinite(price)) return;

                // Binance sends ms → LC expects seconds (UTCTimestamp)
                const time = Math.floor(data.T / 1000) as UTCTimestamp;

                const point: LineData<Time> = {
                    time,
                    value: price,
                };

                series.update(point);
            } catch (err) {
                // eslint-disable-next-line no-console
                console.error('Failed to parse Binance trade event', err);
            }
        };

        ws.onerror = (event) => {
            // eslint-disable-next-line no-console
            console.error('Binance WebSocket error', event);
        };

        return () => {
            ws.close(1000, 'symbol changed / cleanup');
            wsRef.current = null;
        };
    }, [symbol]);

    return (
        <div
            ref={containerRef}
            style={{
                width: '100%',
                height,
            }}
        />
    );
};
