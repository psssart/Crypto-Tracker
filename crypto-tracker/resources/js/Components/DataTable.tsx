import { memo, ReactNode, useCallback, useEffect, useMemo, useRef, useState } from 'react';

type SortDirection = 'asc' | 'desc';

export interface Column<T> {
    key: string;
    header: string;
    render: (row: T) => ReactNode;
    sortable?: boolean;
    sortValue?: (row: T) => number | string | null;
    filterable?: boolean;
    cellFilterable?: boolean;
    filterValue?: (row: T) => string;
    filterLabel?: (value: string) => string;
    headerClassName?: string;
    cellClassName?: string;
}

export interface MobileRenderHelpers {
    cellFilter: (columnKey: string, value: string) => void;
    isCellFiltered: (columnKey: string, value: string) => boolean;
}

interface DataTableProps<T> {
    columns: Column<T>[];
    data: T[];
    rowKey: (row: T) => string | number;
    defaultPerPage?: number;
    perPageOptions?: number[];
    mobileRender?: (row: T, helpers: MobileRenderHelpers) => ReactNode;
}

function FilterDropdown({
    values,
    selected,
    onToggle,
    onClear,
    labelFn,
}: {
    values: string[];
    selected: Set<string>;
    onToggle: (value: string) => void;
    onClear: () => void;
    labelFn?: (value: string) => string;
}) {
    const [open, setOpen] = useState(false);
    const ref = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!open) return;
        const handler = (e: MouseEvent) => {
            if (ref.current && !ref.current.contains(e.target as Node)) {
                setOpen(false);
            }
        };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, [open]);

    const isFiltered = selected.size > 0;

    return (
        <div className="relative inline-block" ref={ref}>
            <button
                type="button"
                onClick={() => setOpen((o) => !o)}
                className={`ml-1 rounded p-0.5 ${
                    isFiltered
                        ? 'text-indigo-600 dark:text-indigo-400'
                        : 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-300'
                }`}
                title="Filter"
            >
                <svg className="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth={2}
                        d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"
                    />
                </svg>
            </button>
            {open && (
                <div className="absolute left-0 z-20 mt-1 max-h-56 w-52 overflow-y-auto rounded-md border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-600 dark:bg-gray-800">
                    <button
                        type="button"
                        onClick={() => {
                            onClear();
                            setOpen(false);
                        }}
                        className="w-full px-3 py-1.5 text-left text-xs font-medium text-indigo-600 hover:bg-gray-50 dark:text-indigo-400 dark:hover:bg-gray-700"
                    >
                        {isFiltered ? 'Clear filter' : 'All shown'}
                    </button>
                    <div className="border-t border-gray-100 dark:border-gray-700" />
                    {values.map((v) => (
                        <label
                            key={v}
                            className="flex cursor-pointer items-center gap-2 px-3 py-1.5 text-sm hover:bg-gray-50 dark:hover:bg-gray-700"
                        >
                            <input
                                type="checkbox"
                                checked={selected.has(v)}
                                onChange={() => onToggle(v)}
                                className="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600"
                            />
                            <span className="truncate font-mono text-xs text-gray-700 dark:text-gray-300">
                                {labelFn ? labelFn(v) : v}
                            </span>
                        </label>
                    ))}
                </div>
            )}
        </div>
    );
}

const FunnelIcon = memo(function FunnelIcon({ className }: { className?: string }) {
    return (
        <svg
            className={className ?? 'h-3 w-3'}
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
    );
});

export default function DataTable<T>({
    columns,
    data,
    rowKey,
    defaultPerPage = 25,
    perPageOptions = [10, 25, 50, 100],
    mobileRender,
}: DataTableProps<T>) {
    const [sortKey, setSortKey] = useState<string | null>(null);
    const [sortDir, setSortDir] = useState<SortDirection>('desc');
    const [filters, setFilters] = useState<Record<string, Set<string>>>({});
    const [perPage, setPerPage] = useState(defaultPerPage);
    const [currentPage, setCurrentPage] = useState(1);
    const [mobileSettingsOpen, setMobileSettingsOpen] = useState(false);

    // Reset page when data, filters, sort, or perPage change
    useEffect(() => setCurrentPage(1), [data, filters, sortKey, sortDir, perPage]);

    // Build column lookup once
    const columnMap = useMemo(() => {
        const map = new Map<string, Column<T>>();
        for (const col of columns) map.set(col.key, col);
        return map;
    }, [columns]);

    const sortableColumns = useMemo(() => columns.filter((c) => c.sortable), [columns]);
    const filterableColumns = useMemo(
        () => columns.filter((c) => c.filterable && c.filterValue),
        [columns],
    );

    const uniqueFilterValues = useMemo(() => {
        const result: Record<string, string[]> = {};
        for (const col of columns) {
            if ((!col.filterable && !col.cellFilterable) || !col.filterValue) continue;
            const set = new Set<string>();
            for (const row of data) {
                set.add(col.filterValue(row));
            }
            result[col.key] = Array.from(set).sort();
        }
        return result;
    }, [data, columns]);

    const filteredData = useMemo(() => {
        const activeFilters = Object.entries(filters).filter(([, s]) => s.size > 0);
        if (activeFilters.length === 0) return data;
        return data.filter((row) => {
            for (const [key, selected] of activeFilters) {
                const col = columnMap.get(key);
                if (!col?.filterValue) continue;
                if (!selected.has(col.filterValue(row))) return false;
            }
            return true;
        });
    }, [data, filters, columnMap]);

    const sortedData = useMemo(() => {
        if (!sortKey) return filteredData;
        const col = columnMap.get(sortKey);
        if (!col?.sortValue) return filteredData;
        return [...filteredData].sort((a, b) => {
            const aVal = col.sortValue!(a);
            const bVal = col.sortValue!(b);
            if (aVal == null && bVal == null) return 0;
            if (aVal == null) return 1;
            if (bVal == null) return -1;
            const cmp = aVal < bVal ? -1 : aVal > bVal ? 1 : 0;
            return sortDir === 'asc' ? cmp : -cmp;
        });
    }, [filteredData, sortKey, sortDir, columnMap]);

    const totalPages = Math.ceil(sortedData.length / perPage);
    const paginatedData = useMemo(
        () => sortedData.slice((currentPage - 1) * perPage, currentPage * perPage),
        [sortedData, currentPage, perPage],
    );

    const handleSort = useCallback((key: string) => {
        setSortKey((prev) => {
            if (prev === key) {
                setSortDir((d) => (d === 'asc' ? 'desc' : 'asc'));
                return key;
            }
            setSortDir('desc');
            return key;
        });
    }, []);

    const handleFilterToggle = useCallback((columnKey: string, value: string) => {
        setFilters((prev) => {
            const current = new Set(prev[columnKey] ?? []);
            if (current.has(value)) {
                current.delete(value);
            } else {
                current.add(value);
            }
            return { ...prev, [columnKey]: current };
        });
    }, []);

    const handleFilterClear = useCallback((columnKey: string) => {
        setFilters((prev) => {
            const next = { ...prev };
            delete next[columnKey];
            return next;
        });
    }, []);

    const handleCellFilter = useCallback((columnKey: string, value: string) => {
        setFilters((prev) => {
            const current = prev[columnKey];
            if (current?.size === 1 && current.has(value)) {
                const next = { ...prev };
                delete next[columnKey];
                return next;
            }
            return { ...prev, [columnKey]: new Set([value]) };
        });
    }, []);

    const isCellFiltered = useCallback(
        (columnKey: string, value: string) => {
            return (filters[columnKey]?.size === 1 && filters[columnKey].has(value)) ?? false;
        },
        [filters],
    );

    const mobileHelpers = useMemo<MobileRenderHelpers>(
        () => ({ cellFilter: handleCellFilter, isCellFiltered }),
        [handleCellFilter, isCellFiltered],
    );

    const handleMobileFilterChange = useCallback(
        (columnKey: string, value: string) => {
            if (value === '') {
                handleFilterClear(columnKey);
            } else {
                setFilters((prev) => ({ ...prev, [columnKey]: new Set([value]) }));
            }
        },
        [handleFilterClear],
    );

    const pageNumbers = useMemo(() => {
        const pages: (number | '...')[] = [];
        if (totalPages <= 5) {
            for (let i = 1; i <= totalPages; i++) pages.push(i);
        } else {
            const start = Math.max(1, currentPage - 1);
            const end = Math.min(totalPages, currentPage + 1);
            if (start > 1) pages.push('...');
            for (let i = start; i <= end; i++) pages.push(i);
            if (end < totalPages - 1) pages.push('...');
            if (end < totalPages) pages.push(totalPages);
        }
        return pages;
    }, [totalPages, currentPage]);

    const SortIcon = ({ columnKey }: { columnKey: string }) => {
        if (sortKey !== columnKey) {
            return (
                <svg
                    className="ml-1 inline h-3.5 w-3.5 text-gray-400"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth={2}
                        d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"
                    />
                </svg>
            );
        }
        return sortDir === 'asc' ? (
            <svg
                className="ml-1 inline h-3.5 w-3.5 text-indigo-600 dark:text-indigo-400"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M5 15l7-7 7 7"
                />
            </svg>
        ) : (
            <svg
                className="ml-1 inline h-3.5 w-3.5 text-indigo-600 dark:text-indigo-400"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M19 9l-7 7-7-7"
                />
            </svg>
        );
    };

    const renderCell = useCallback(
        (col: Column<T>, row: T) => {
            const content = col.render(row);
            if (!col.cellFilterable || !col.filterValue) return content;

            const value = col.filterValue(row);
            const isActive = filters[col.key]?.size === 1 && filters[col.key].has(value);

            return (
                <div className="group/cell flex items-center gap-1">
                    <span className="min-w-0">{content}</span>
                    <button
                        type="button"
                        onClick={() => handleCellFilter(col.key, value)}
                        className={`shrink-0 rounded p-0.5 transition-opacity ${
                            isActive
                                ? 'text-indigo-600 opacity-100 dark:text-indigo-400'
                                : 'text-gray-400 opacity-0 hover:text-indigo-600 group-hover/cell:opacity-100 dark:hover:text-indigo-400'
                        }`}
                        title={isActive ? 'Clear filter' : 'Filter by this value'}
                    >
                        <FunnelIcon />
                    </button>
                </div>
            );
        },
        [filters, handleCellFilter],
    );

    // Current single-select value for a mobile filter dropdown
    const getMobileFilterValue = (columnKey: string): string => {
        const set = filters[columnKey];
        if (!set || set.size !== 1) return '';
        return Array.from(set)[0];
    };

    const hasMobileControls = sortableColumns.length > 0 || filterableColumns.length > 0;
    const hasActiveSettings =
        sortKey !== null || Object.values(filters).some((s) => s.size > 0);

    return (
        <div>
            {/* Top bar */}
            <div className="mb-3 flex items-center justify-between">
                <div className="flex items-center gap-2">
                    <span className="text-sm text-gray-500 dark:text-gray-400">
                        {sortedData.length} result{sortedData.length !== 1 ? 's' : ''}
                        {sortedData.length !== data.length && ` (of ${data.length})`}
                    </span>
                    {/* Mobile settings toggle */}
                    {hasMobileControls && (
                        <button
                            type="button"
                            onClick={() => setMobileSettingsOpen((o) => !o)}
                            className={`relative rounded-md border p-1.5 sm:hidden ${
                                mobileSettingsOpen
                                    ? 'border-indigo-500 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400'
                                    : 'border-gray-300 text-gray-500 hover:text-gray-700 dark:border-gray-600 dark:text-gray-400 dark:hover:text-gray-300'
                            }`}
                            title="Sort & filter settings"
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
                                    d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"
                                />
                            </svg>
                            {hasActiveSettings && (
                                <span className="absolute -right-1 -top-1 h-2 w-2 rounded-full bg-indigo-500" />
                            )}
                        </button>
                    )}
                </div>
                <div className="flex items-center gap-2">
                    <label className="text-sm text-gray-500 dark:text-gray-400">Show</label>
                    <select
                        value={perPage}
                        onChange={(e) => setPerPage(Number(e.target.value))}
                        className="rounded-md border-gray-300 py-1 pl-2 pr-7 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    >
                        {perPageOptions.map((n) => (
                            <option key={n} value={n}>
                                {n}
                            </option>
                        ))}
                    </select>
                </div>
            </div>

            {/* Mobile sort & filter controls (collapsible) */}
            {hasMobileControls && mobileSettingsOpen && (
                <div className="mb-3 space-y-2 sm:hidden">
                    {/* Sort row */}
                    {sortableColumns.length > 0 && (
                        <div className="flex items-center gap-2">
                            <label className="shrink-0 text-xs font-medium text-gray-500 dark:text-gray-400">
                                Sort by
                            </label>
                            <select
                                value={sortKey ?? ''}
                                onChange={(e) => {
                                    const val = e.target.value;
                                    if (val === '') {
                                        setSortKey(null);
                                    } else {
                                        handleSort(val);
                                    }
                                }}
                                className="min-w-0 flex-1 rounded-md border-gray-300 py-1 pl-2 pr-7 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            >
                                <option value="">None</option>
                                {sortableColumns.map((col) => (
                                    <option key={col.key} value={col.key}>
                                        {col.header}
                                    </option>
                                ))}
                            </select>
                            {sortKey && (
                                <button
                                    type="button"
                                    onClick={() =>
                                        setSortDir((d) => (d === 'asc' ? 'desc' : 'asc'))
                                    }
                                    className="rounded-md border border-gray-300 p-1.5 text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                                    title={sortDir === 'asc' ? 'Ascending' : 'Descending'}
                                >
                                    {sortDir === 'asc' ? (
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
                                                d="M5 15l7-7 7 7"
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
                                                d="M19 9l-7 7-7-7"
                                            />
                                        </svg>
                                    )}
                                </button>
                            )}
                        </div>
                    )}

                    {/* Filter row(s) */}
                    {filterableColumns.length > 0 && (
                        <div className="flex flex-wrap items-center gap-2">
                            {filterableColumns.map((col) => (
                                <div key={col.key} className="flex items-center gap-1">
                                    <label className="shrink-0 text-xs font-medium text-gray-500 dark:text-gray-400">
                                        {col.header}
                                    </label>
                                    <select
                                        value={getMobileFilterValue(col.key)}
                                        onChange={(e) =>
                                            handleMobileFilterChange(col.key, e.target.value)
                                        }
                                        className="min-w-0 rounded-md border-gray-300 py-1 pl-2 pr-7 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    >
                                        <option value="">All</option>
                                        {(uniqueFilterValues[col.key] ?? []).map((v) => (
                                            <option key={v} value={v}>
                                                {col.filterLabel ? col.filterLabel(v) : v}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            )}

            {paginatedData.length === 0 ? (
                <div className="py-12 text-center text-gray-500 dark:text-gray-400">
                    {data.length === 0 ? 'No data available.' : 'No results match the filters.'}
                </div>
            ) : (
                <>
                    {/* Desktop Table */}
                    <div className="hidden overflow-x-auto rounded-lg border border-gray-200 shadow-sm sm:block dark:border-gray-700">
                        <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead className="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    {columns.map((col) => (
                                        <th
                                            key={col.key}
                                            className={`px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400 ${col.headerClassName ?? ''}`}
                                        >
                                            <div className="flex items-center justify-center">
                                                {col.sortable ? (
                                                    <button
                                                        type="button"
                                                        onClick={() => handleSort(col.key)}
                                                        className="inline-flex items-center hover:text-gray-700 dark:hover:text-gray-200"
                                                    >
                                                        {col.header}
                                                        <SortIcon columnKey={col.key} />
                                                    </button>
                                                ) : (
                                                    <span>{col.header}</span>
                                                )}
                                                {col.filterable &&
                                                    uniqueFilterValues[col.key]?.length > 0 && (
                                                        <FilterDropdown
                                                            values={uniqueFilterValues[col.key]}
                                                            selected={
                                                                filters[col.key] ?? new Set()
                                                            }
                                                            onToggle={(v) =>
                                                                handleFilterToggle(col.key, v)
                                                            }
                                                            onClear={() =>
                                                                handleFilterClear(col.key)
                                                            }
                                                            labelFn={col.filterLabel}
                                                        />
                                                    )}
                                            </div>
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                                {paginatedData.map((row) => (
                                    <tr
                                        key={rowKey(row)}
                                        className="hover:bg-gray-50 dark:hover:bg-gray-800"
                                    >
                                        {columns.map((col) => (
                                            <td
                                                key={col.key}
                                                className={`whitespace-nowrap px-4 py-3 text-sm ${col.cellClassName ?? ''}`}
                                            >
                                                {renderCell(col, row)}
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {/* Mobile Cards */}
                    {mobileRender && (
                        <div className="space-y-3 sm:hidden">
                            {paginatedData.map((row) => (
                                <div key={rowKey(row)}>
                                    {mobileRender(row, mobileHelpers)}
                                </div>
                            ))}
                        </div>
                    )}

                    {/* Mobile fallback: scrollable table if no mobileRender */}
                    {!mobileRender && (
                        <div className="overflow-x-auto rounded-lg border border-gray-200 shadow-sm sm:hidden dark:border-gray-700">
                            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead className="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        {columns.map((col) => (
                                            <th
                                                key={col.key}
                                                className={`px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400 ${col.headerClassName ?? ''}`}
                                            >
                                                {col.header}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                                    {paginatedData.map((row) => (
                                        <tr key={rowKey(row)}>
                                            {columns.map((col) => (
                                                <td
                                                    key={col.key}
                                                    className={`whitespace-nowrap px-4 py-3 text-sm ${col.cellClassName ?? ''}`}
                                                >
                                                    {col.render(row)}
                                                </td>
                                            ))}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </>
            )}

            {/* Pagination */}
            {totalPages > 1 && (
                <nav className="mt-4 flex items-center justify-center gap-1">
                    {/* First page */}
                    <button
                        disabled={currentPage === 1}
                        onClick={() => setCurrentPage(1)}
                        className="rounded p-1 text-gray-700 hover:bg-gray-100 disabled:cursor-default disabled:text-gray-400 dark:text-gray-300 dark:hover:bg-gray-700 dark:disabled:text-gray-600"
                        title="First page"
                    >
                        <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
                        </svg>
                    </button>
                    {/* Previous page */}
                    <button
                        disabled={currentPage === 1}
                        onClick={() => setCurrentPage((p) => p - 1)}
                        className="rounded p-1 text-gray-700 hover:bg-gray-100 disabled:cursor-default disabled:text-gray-400 dark:text-gray-300 dark:hover:bg-gray-700 dark:disabled:text-gray-600"
                        title="Previous page"
                    >
                        <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>
                    {pageNumbers.map((p, i) =>
                        p === '...' ? (
                            <span
                                key={`ellipsis-${i}`}
                                className="px-2 py-1 text-sm text-gray-400"
                            >
                                ...
                            </span>
                        ) : (
                            <button
                                key={p}
                                onClick={() => setCurrentPage(p)}
                                className={`rounded px-3 py-1 text-sm ${
                                    currentPage === p
                                        ? 'bg-indigo-600 text-white'
                                        : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700'
                                }`}
                            >
                                {p}
                            </button>
                        ),
                    )}
                    {/* Next page */}
                    <button
                        disabled={currentPage === totalPages}
                        onClick={() => setCurrentPage((p) => p + 1)}
                        className="rounded p-1 text-gray-700 hover:bg-gray-100 disabled:cursor-default disabled:text-gray-400 dark:text-gray-300 dark:hover:bg-gray-700 dark:disabled:text-gray-600"
                        title="Next page"
                    >
                        <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                    {/* Last page */}
                    <button
                        disabled={currentPage === totalPages}
                        onClick={() => setCurrentPage(totalPages)}
                        className="rounded p-1 text-gray-700 hover:bg-gray-100 disabled:cursor-default disabled:text-gray-400 dark:text-gray-300 dark:hover:bg-gray-700 dark:disabled:text-gray-600"
                        title="Last page"
                    >
                        <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 5l7 7-7 7M5 5l7 7-7 7" />
                        </svg>
                    </button>
                </nav>
            )}
        </div>
    );
}
