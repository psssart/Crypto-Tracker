import { useTheme } from '@/lib/theme-provider';
import { ReactNode, useCallback, useMemo } from 'react';
import {
    Bar,
    BarChart as RechartsBarChart,
    CartesianGrid,
    ReferenceLine,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

export interface BarChartSeries {
    dataKey: string;
    name: string;
    color: string;
    stackId?: string;
}

export interface BarChartProps {
    data: Record<string, any>[];
    series: BarChartSeries[];
    xAxisKey: string;
    height?: number;
    stackOffset?: 'sign' | 'none';
    yAxisFormatter?: (value: number) => string;
    tooltipContent?: (props: any) => ReactNode;
    xAxisAngle?: number;
}

export default function BarChart({
    data,
    series,
    xAxisKey,
    height = 300,
    stackOffset = 'none',
    yAxisFormatter,
    tooltipContent,
    xAxisAngle = 0,
}: BarChartProps) {
    const { appearance } = useTheme();

    const isDark = useMemo(() => {
        if (appearance === 'dark') return true;
        if (appearance === 'light') return false;
        return (
            typeof window !== 'undefined' &&
            window.matchMedia('(prefers-color-scheme: dark)').matches
        );
    }, [appearance]);

    const gridColor = isDark ? '#374151' : '#e5e7eb';
    const axisTickColor = isDark ? '#9ca3af' : '#6b7280';
    const tooltipBg = isDark ? '#1f2937' : '#ffffff';
    const tooltipBorder = isDark ? '#374151' : '#e5e7eb';
    const refLineColor = isDark ? '#9ca3af' : '#6b7280';

    const needsBottomMargin = xAxisAngle !== 0;

    const measureText = useCallback((text: string, fontSize: number): number => {
        if (typeof document === 'undefined') return text.length * fontSize * 0.6;
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        if (!ctx) return text.length * fontSize * 0.6;
        ctx.font = `${fontSize}px sans-serif`;
        return ctx.measureText(text).width;
    }, []);

    const yAxisWidth = useMemo(() => {
        const dataKeys = series.map((s) => s.dataKey);
        let maxVal = 0;
        let minVal = 0;
        for (const row of data) {
            for (const key of dataKeys) {
                const v = Number(row[key]) || 0;
                if (v > maxVal) maxVal = v;
                if (v < minVal) minVal = v;
            }
        }
        const candidates = [minVal, maxVal];
        const fontSize = 12;
        let maxWidth = 0;
        for (const val of candidates) {
            const label = yAxisFormatter ? yAxisFormatter(val) : String(val);
            const w = measureText(label, fontSize);
            if (w > maxWidth) maxWidth = w;
        }
        return Math.ceil(maxWidth) + 8;
    }, [data, series, yAxisFormatter, measureText]);

    return (
        <ResponsiveContainer width="100%" height={height}>
            <RechartsBarChart
                data={data}
                stackOffset={stackOffset === 'sign' ? 'sign' : undefined}
                margin={{
                    top: 5,
                    right: 10,
                    left: 5,
                    bottom: needsBottomMargin ? 50 : 5,
                }}
            >
                <CartesianGrid strokeDasharray="3 3" stroke={gridColor} />
                <XAxis
                    dataKey={xAxisKey}
                    tick={{ fill: axisTickColor, fontSize: 12 }}
                    angle={xAxisAngle}
                    textAnchor={xAxisAngle !== 0 ? 'end' : 'middle'}
                    height={needsBottomMargin ? 60 : 30}
                />
                <YAxis
                    width={yAxisWidth}
                    tick={{ fill: axisTickColor, fontSize: 12 }}
                    tickFormatter={yAxisFormatter}
                />
                {stackOffset === 'sign' && (
                    <ReferenceLine y={0} stroke={refLineColor} />
                )}
                <Tooltip
                    content={tooltipContent}
                    {...(!tooltipContent && {
                        contentStyle: {
                            backgroundColor: tooltipBg,
                            borderColor: tooltipBorder,
                            borderRadius: 8,
                        },
                        labelStyle: { color: axisTickColor },
                        itemStyle: { padding: 0 },
                    })}
                />
                {series.map((s) => (
                    <Bar
                        key={s.dataKey}
                        dataKey={s.dataKey}
                        name={s.name}
                        fill={s.color}
                        stackId={s.stackId}
                        radius={s.stackId ? undefined : [2, 2, 0, 0]}
                    />
                ))}
            </RechartsBarChart>
        </ResponsiveContainer>
    );
}
