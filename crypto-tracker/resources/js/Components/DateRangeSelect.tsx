import { useTheme } from '@/lib/theme-provider';
import { ThemeProvider, createTheme } from '@mui/material/styles';
import { DatePicker, LocalizationProvider } from '@mui/x-date-pickers';
import { AdapterDayjs } from '@mui/x-date-pickers/AdapterDayjs';
import dayjs, { Dayjs } from 'dayjs';
import { useMemo } from 'react';

interface DateRangeSelectProps {
    from: string;
    to: string;
    onChange: (from: string, to: string) => void;
    maxRangeMonths?: number;
}

export default function DateRangeSelect({
    from,
    to,
    onChange,
    maxRangeMonths = 6,
}: DateRangeSelectProps) {
    const { appearance } = useTheme();

    const isDark = useMemo(() => {
        if (appearance === 'dark') return true;
        if (appearance === 'light') return false;
        return (
            typeof window !== 'undefined' &&
            window.matchMedia('(prefers-color-scheme: dark)').matches
        );
    }, [appearance]);

    const muiTheme = useMemo(
        () =>
            createTheme({
                palette: { mode: isDark ? 'dark' : 'light' },
                components: {
                    MuiOutlinedInput: {
                        styleOverrides: {
                            root: { fontSize: '0.875rem' },
                            input: { padding: '8px 12px' },
                        },
                    },
                    MuiInputLabel: {
                        styleOverrides: {
                            root: { fontSize: '0.875rem' },
                        },
                    },
                },
            }),
        [isDark],
    );

    const fromDate = dayjs(from);
    const toDate = dayjs(to);

    const handleFromChange = (val: Dayjs | null) => {
        if (!val?.isValid()) return;
        let newFrom = val.format('YYYY-MM-DD');
        let newTo = to;
        // If from > to, move to forward
        if (val.isAfter(toDate)) {
            newTo = newFrom;
        }
        // Enforce max range
        if (dayjs(newTo).diff(dayjs(newFrom), 'month', true) > maxRangeMonths) {
            newTo = dayjs(newFrom).add(maxRangeMonths, 'month').format('YYYY-MM-DD');
        }
        onChange(newFrom, newTo);
    };

    const handleToChange = (val: Dayjs | null) => {
        if (!val?.isValid()) return;
        let newTo = val.format('YYYY-MM-DD');
        let newFrom = from;
        // If to < from, move from backward
        if (val.isBefore(fromDate)) {
            newFrom = newTo;
        }
        // Enforce max range
        if (dayjs(newTo).diff(dayjs(newFrom), 'month', true) > maxRangeMonths) {
            newFrom = dayjs(newTo).subtract(maxRangeMonths, 'month').format('YYYY-MM-DD');
        }
        onChange(newFrom, newTo);
    };

    return (
        <ThemeProvider theme={muiTheme}>
            <LocalizationProvider dateAdapter={AdapterDayjs}>
                <div className="flex flex-wrap items-center gap-3">
                    <DatePicker
                        label="From"
                        value={fromDate}
                        onChange={handleFromChange}
                        maxDate={dayjs()}
                        slotProps={{
                            textField: { size: 'small' },
                        }}
                    />
                    <span className="text-gray-400">&ndash;</span>
                    <DatePicker
                        label="To"
                        value={toDate}
                        onChange={handleToChange}
                        maxDate={dayjs()}
                        minDate={fromDate}
                        slotProps={{
                            textField: { size: 'small' },
                        }}
                    />
                </div>
            </LocalizationProvider>
        </ThemeProvider>
    );
}
