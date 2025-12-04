// resources/js/Components/LiveWebSocketChart.tsx
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

export type LiveWebSocketTick = {
    time: UTCTimestamp;
    value: number;
};

export type WebSocketSourceAuth = {
    token?: string;   // e.g. AllTick token
    apiKey?: string;  // e.g. FreeCryptoAPI key
    apiSecret?: string; // e.g. Bybit secret (when you add WS for it)
    [key: string]: unknown;
};

export type WebSocketSourceConfig = {
    /** Stable id, used in UI / state */
    id: 'binance' | 'alltick' | 'freecryptoapi' | (string & {});
    label: string;
    description?: string;

    /** Build the WebSocket URL for a symbol */
    buildUrl: (symbol: string, auth?: WebSocketSourceAuth) => string;

    /** Optional: send subscribe/auth messages when socket opens */
    onOpen?: (socket: WebSocket, symbol: string, auth?: WebSocketSourceAuth) => void;

    /** Map raw WebSocket message → chart tick (or null to ignore) */
    parseMessage: (event: MessageEvent<any>) => LiveWebSocketTick | null;

    /** Optional: send heartbeat periodically to keep connection alive */
    heartbeatIntervalMs?: number;
    buildHeartbeatMessage?: () => unknown;
};

type LiveWebSocketChartProps = {
    symbol: string;
    height?: number;
    source: WebSocketSourceConfig;
    auth?: WebSocketSourceAuth;
};

export const LiveWebSocketChart: React.FC<LiveWebSocketChartProps> = ({
                                                                          symbol,
                                                                          height = 400,
                                                                          source,
                                                                          auth,
                                                                      }) => {
    const containerRef = useRef<HTMLDivElement | null>(null);
    const chartRef = useRef<IChartApi | null>(null);
    const seriesRef = useRef<ISeriesApi<'Line'> | null>(null);
    const wsRef = useRef<WebSocket | null>(null);
    const heartbeatTimerRef = useRef<number | null>(null);

    // 1) Init chart once
    useEffect(() => {
        const container = containerRef.current;
        if (!container) return;

        const chart = createChart(container, {
            autoSize: true,
            layout: {
                background: {
                    type: ColorType.Solid,
                    color: '#020617', // dark bg
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
            if (heartbeatTimerRef.current != null) {
                window.clearInterval(heartbeatTimerRef.current);
            }
            wsRef.current?.close(1000, 'component unmount');
            chart.remove();
            chartRef.current = null;
            seriesRef.current = null;
        };
    }, []);

    // 2) WebSocket lifecycle: re-connect when symbol, source, or auth change
    useEffect(() => {
        const series = seriesRef.current;
        if (!series) return;

        let url: string;
        try {
            url = source.buildUrl(symbol, auth);
        } catch (err) {
            // eslint-disable-next-line no-console
            console.error('Failed to build WebSocket URL', err);
            return;
        }

        if (!url) {
            // eslint-disable-next-line no-console
            console.warn(
                `No WebSocket URL for source "${source.id}" (maybe missing auth?)`,
            );
            return;
        }

        const ws = new WebSocket(url);
        wsRef.current = ws;

        ws.onopen = () => {
            try {
                if (source.onOpen) {
                    source.onOpen(ws, symbol, auth);
                }

                if (source.heartbeatIntervalMs && source.buildHeartbeatMessage) {
                    if (heartbeatTimerRef.current != null) {
                        window.clearInterval(heartbeatTimerRef.current);
                    }

                    heartbeatTimerRef.current = window.setInterval(() => {
                        try {
                            const payload = source.buildHeartbeatMessage!();
                            ws.send(
                                typeof payload === 'string'
                                    ? payload
                                    : JSON.stringify(payload),
                            );
                        } catch (err) {
                            // eslint-disable-next-line no-console
                            console.error('Failed to send heartbeat', err);
                        }
                    }, source.heartbeatIntervalMs);
                }
            } catch (err) {
                // eslint-disable-next-line no-console
                console.error('Error in WebSocket onopen handler', err);
            }
        };

        ws.onmessage = (event: MessageEvent<any>) => {
            try {
                const tick = source.parseMessage(event);
                if (!tick) return;

                const point: LineData<Time> = {
                    time: tick.time,
                    value: tick.value,
                };

                series.update(point);
            } catch (err) {
                // eslint-disable-next-line no-console
                console.error('Failed to parse WebSocket message', err);
            }
        };

        ws.onerror = (event) => {
            // eslint-disable-next-line no-console
            console.error('WebSocket error', event);
        };

        return () => {
            if (heartbeatTimerRef.current != null) {
                window.clearInterval(heartbeatTimerRef.current);
                heartbeatTimerRef.current = null;
            }
            ws.close(1000, 'symbol/source/auth changed / cleanup');
            wsRef.current = null;
        };
    }, [symbol, source, auth]);

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

/* ------------------------------------------------------------------ */
/*  Provider configs: Binance / AllTick / FreeCryptoAPI               */
/* ------------------------------------------------------------------ */

interface BinanceTradeEvent {
    e: 'trade';
    E: number;
    s: string;
    t: number;
    p: string;
    q: string;
    T: number;
    m: boolean;
    M: boolean;
}

export const BINANCE_SOURCE: WebSocketSourceConfig = {
    id: 'binance',
    label: 'Binance (live trades)',
    description: 'Public trade WebSocket from Binance, no auth / caching',
    buildUrl: (symbol: string) =>
        `wss://stream.binance.com:9443/ws/${symbol.toLowerCase()}@trade`,
    parseMessage: (event) => {
        try {
            if (typeof event.data !== 'string') return null;
            const data: BinanceTradeEvent = JSON.parse(event.data);

            const price = parseFloat(data.p);
            if (!Number.isFinite(price)) return null;

            const ts = Math.floor(data.T / 1000) as UTCTimestamp;

            return { time: ts, value: price };
        } catch {
            return null;
        }
    },
};

/* ----------------------------- AllTick ----------------------------- */

interface AllTickPushMessage {
    cmd_id: number;
    data?: {
        code: string;
        tick_time: string;
        price: string;
        volume?: string;
        turnover?: string;
        trade_direction?: number;
    };
}

export const ALLTICK_SOURCE: WebSocketSourceConfig = {
    id: 'alltick',
    label: 'AllTick',
    description: 'AllTick real-time tick stream for crypto / forex / metals',
    buildUrl: (_symbol: string, auth?: WebSocketSourceAuth) => {
        const token = auth?.token;
        if (!token) {
            // eslint-disable-next-line no-console
            console.warn(
                'AllTick source selected but no token provided in auth. Check sourceAuth from backend.',
            );
            return '';
        }

        // For crypto/forex/commodities WS:
        // wss://quote.alltick.co/quote-b-ws-api?token=your_token
        return `wss://quote.alltick.co/quote-b-ws-api?token=${encodeURIComponent(
            String(token),
        )}`;
    },
    onOpen: (socket, symbol) => {
        const payload = {
            cmd_id: 22004,
            seq_id: 1,
            trace: `chart-${symbol}-${Date.now()}`,
            data: {
                symbol_list: [{ code: symbol }],
            },
        };

        socket.send(JSON.stringify(payload));
    },
    heartbeatIntervalMs: 10_000,
    buildHeartbeatMessage: () => ({
        cmd_id: 22000,
        seq_id: 1,
        trace: `heartbeat-${Date.now()}`,
        data: {},
    }),
    parseMessage: (event) => {
        try {
            if (typeof event.data !== 'string') return null;

            const parsed: AllTickPushMessage = JSON.parse(event.data);

            if (parsed.cmd_id !== 22998 || !parsed.data) return null;

            const price = parseFloat(parsed.data.price);
            if (!Number.isFinite(price)) return null;

            const raw = Number(parsed.data.tick_time);
            if (!Number.isFinite(raw)) return null;

            const seconds = raw > 1e12 ? Math.floor(raw / 1000) : raw;

            return {
                time: seconds as UTCTimestamp,
                value: price,
            };
        } catch {
            return null;
        }
    },
};

/* ------------------------- FreeCryptoAPI --------------------------- */
/*
   WebSocket endpoint & payload shape depend on your FreeCryptoAPI plan.
   This config uses auth.apiKey and assumes token in query, you can adapt
   URL and parser to the real docs they give you.
*/

export const FREECRYPTOAPI_SOURCE: WebSocketSourceConfig = {
    id: 'freecryptoapi',
    label: 'FreeCryptoAPI',
    description: 'Streaming via FreeCryptoAPI (configure WS endpoint & parser)',
    buildUrl: (symbol: string, auth?: WebSocketSourceAuth) => {
        const apiKey = auth?.apiKey;
        if (!apiKey) {
            // eslint-disable-next-line no-console
            console.warn(
                'FreeCryptoAPI source selected but no apiKey provided in auth. Check sourceAuth from backend.',
            );
            return '';
        }

        // TODO: Replace with the real WebSocket endpoint you get from FreeCryptoAPI docs.
        return `wss://YOUR-FREECRYPTOAPI-WS-ENDPOINT?symbol=${encodeURIComponent(
            symbol,
        )}&api_key=${encodeURIComponent(String(apiKey))}`;
    },
    onOpen: (socket, symbol, auth) => {
        // TODO: Replace with real subscribe message if required.
        // Example (pseudo):
        // const msg = { action: 'subscribe', symbol, apiKey: auth?.apiKey };
        // socket.send(JSON.stringify(msg));
    },
    parseMessage: (event) => {
        try {
            if (typeof event.data !== 'string') return null;
            const data: any = JSON.parse(event.data);

            if (!data || typeof data.price !== 'number') return null;

            const raw =
                data.timestamp != null
                    ? Number(data.timestamp)
                    : Number(data.t ?? Date.now());
            const seconds = raw > 1e12 ? Math.floor(raw / 1000) : raw;

            return {
                time: seconds as UTCTimestamp,
                value: data.price,
            };
        } catch {
            return null;
        }
    },
};

/* ---------------------- Aggregated source list --------------------- */

export const ALL_WEB_SOCKET_SOURCES: WebSocketSourceConfig[] = [
    BINANCE_SOURCE,
    ALLTICK_SOURCE,
    FREECRYPTOAPI_SOURCE,
];
