import { useTheme } from '@/lib/theme-provider';
import { ReactNode, useMemo } from 'react';
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

    return (
        <ResponsiveContainer width="100%" height={height}>
            <RechartsBarChart
                data={data}
                stackOffset={stackOffset === 'sign' ? 'sign' : undefined}
                margin={{
                    top: 5,
                    right: 20,
                    left: 10,
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
