// resources/js/Pages/Dashboard.tsx
import { Head, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useState, useEffect, useMemo } from 'react';
import axios from 'axios';
import { ChevronDownIcon, ChevronUpIcon } from '@heroicons/react/24/outline';
import { flashError } from '@/Components/FlashMessages';

// Icon components
import CopyIcon from '@/Components/Icons/Copy';
import TwitterIcon from '@/Components/Icons/Twitter';
import TelegramIcon from '@/Components/Icons/Telegram';
import DocsIcon from '@/Components/Icons/Docs';
import WebsiteIcon from '@/Components/Icons/Website';

type LinkItem = {
    label?: string;
    type?: string;
    url: string;
};

type TokenProfile = {
    url: string;
    chainId: string;
    tokenAddress: string;
    icon: string;
    header: string;
    openGraph: string;
    description: string;
    links: LinkItem[];
};

type ProfilesByChain = Record<string, TokenProfile[]>;

type TrackedAddress = {
    network_slug: string;
    address: string;
};

type Props = {
    supportedNetworkMap: Record<string, number>;
    trackedAddresses: TrackedAddress[];
};

// Define menu items and corresponding API routes
const menuItems: { label: string; routeName: string }[] = [
    { label: 'Latest Tokens', routeName: 'dex.latestTokenProfiles' },
    { label: 'Latest Boosted Tokens', routeName: 'dex.getLatestBoostedTokens' },
    { label: 'Most Boosted Tokens', routeName: 'dex.getMostBoostedTokens' },
];

function trackingKey(slug: string, address: string) {
    return `${slug}:${address.toLowerCase()}`;
}

export default function Dashboard({ supportedNetworkMap, trackedAddresses }: Props) {
    const [profiles, setProfiles] = useState<ProfilesByChain>({});
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [selectedRoute, setSelectedRoute] = useState(menuItems[0].routeName);
    const [expandedChains, setExpandedChains] = useState<Record<string, boolean>>({});

    const initialTrackedSet = useMemo(
        () => new Set(trackedAddresses.map((t) => trackingKey(t.network_slug, t.address))),
        [trackedAddresses],
    );
    const [trackedSet, setTrackedSet] = useState(initialTrackedSet);
    const [trackingInProgress, setTrackingInProgress] = useState<Set<string>>(new Set());

    useEffect(() => {
        setLoading(true);
        setError(null);

        axios
            .get<ProfilesByChain>(route(selectedRoute, { group: 1 }))
            .then((res) => setProfiles(res.data))
            .catch((err) => {
                console.error(err);
                setError(err.response?.data?.message || 'Failed to load token profiles');
            })
            .finally(() => setLoading(false));
    }, [selectedRoute]);

    const handleCopy = (text: string) => {
        navigator.clipboard.writeText(text);
    };

    const toggleExpand = (chainId: string) => {
        setExpandedChains((prev) => ({ ...prev, [chainId]: !prev[chainId] }));
    };

    const handleTrack = (chainId: string, tokenAddress: string) => {
        const networkId = supportedNetworkMap[chainId];
        if (!networkId) return;

        const key = trackingKey(chainId, tokenAddress);
        setTrackingInProgress((prev) => new Set(prev).add(key));

        router.post(
            route('watchlist.store'),
            { network_id: networkId, address: tokenAddress },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setTrackedSet((prev) => new Set(prev).add(key));
                },
                onError: (errors) => {
                    if (errors.limit) {
                        flashError(errors.limit);
                    } else if (errors.address) {
                        flashError(errors.address);
                    }
                },
                onFinish: () => {
                    setTrackingInProgress((prev) => {
                        const next = new Set(prev);
                        next.delete(key);
                        return next;
                    });
                },
            },
        );
    };

    const headerMenu = (
        <nav className="flex space-x-4 border-b border-gray-200 dark:border-gray-700">
            {menuItems.map((item) => (
                <button
                    key={item.routeName}
                    onClick={() => setSelectedRoute(item.routeName)}
                    className={
                        `px-4 py-2 -mb-px font-medium focus:outline-none ` +
                        (selectedRoute === item.routeName
                            ? 'text-gray-900 dark:text-gray-100 border-b-2 border-blue-500'
                            : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200')
                    }
                >
                    {item.label}
                </button>
            ))}
        </nav>
    );

    return (
        <AuthenticatedLayout header={headerMenu}>
            <Head title="Tokens" />

            <div className="mx-7 py-12 space-y-8">
                {loading && (
                    <p className="text-center text-icon-primary">Loading token profiles…</p>
                )}
                {error && <p className="text-center text-red-500">{error}</p>}

                {!loading && !error && Object.keys(profiles).length === 0 && (
                    <p className="text-center">No token profiles found.</p>
                )}

                {!loading && !error && (
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                        {Object.entries(profiles).map(([chainId, items]) => {
                            const isExpanded = expandedChains[chainId];
                            const visibleItems = isExpanded ? items : items.slice(0, 3);
                            const remainingCount = items.length - 3;

                            return (
                                <div key={chainId} className="col-span-1">
                                    <h3 className="mb-4 text-lg font-bold text-gray-700 dark:text-gray-300">
                                        {chainId.toUpperCase()}
                                    </h3>
                                    <div className="space-y-6">
                                        {visibleItems.map((item) => {
                                            const key = trackingKey(chainId, item.tokenAddress);
                                            const isTracked = trackedSet.has(key);
                                            const isSupported = chainId in supportedNetworkMap;
                                            const isPosting = trackingInProgress.has(key);

                                            return (
                                                <div
                                                    key={item.tokenAddress}
                                                    className="flex flex-col bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden"
                                                >
                                                    {/* Image with tooltip + link */}
                                                    <a
                                                        href={item.url}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        title={item.description}
                                                        className="block"
                                                    >
                                                        <img
                                                            src={item.openGraph}
                                                            alt={item.description}
                                                            onError={(e) => {
                                                                const img = e.currentTarget;
                                                                img.onerror = null;
                                                                img.src = item.header;
                                                            }}
                                                            className="w-full h-100 object-cover [clip-path:inset(0px_0px_15px_0px)]"
                                                        />
                                                    </a>

                                                    {/* Footer */}
                                                    <div className="flex items-center justify-between p-3 pt-0">
                                                        <div className="flex items-center space-x-1">
                                                            {/* Copy button */}
                                                            <button
                                                                onClick={() =>
                                                                    handleCopy(item.tokenAddress)
                                                                }
                                                                className="p-2 rounded text-icon-primary hover:text-icon-secondary"
                                                                aria-label="Copy token address"
                                                                title="Copy token address"
                                                            >
                                                                <CopyIcon className="w-5 h-5" />
                                                            </button>

                                                            {/* Track button */}
                                                            {isSupported &&
                                                                (isTracked ? (
                                                                    <span className="rounded-full bg-green-500/20 px-2.5 py-0.5 text-xs font-medium text-green-600 dark:text-green-400">
                                                                        Tracking
                                                                    </span>
                                                                ) : (
                                                                    <button
                                                                        onClick={() =>
                                                                            handleTrack(
                                                                                chainId,
                                                                                item.tokenAddress,
                                                                            )
                                                                        }
                                                                        disabled={isPosting}
                                                                        className="rounded-full border border-gray-300 dark:border-gray-600 px-2.5 py-0.5 text-xs font-medium text-gray-500 dark:text-gray-400 transition hover:border-blue-400 hover:text-blue-500 disabled:opacity-50"
                                                                    >
                                                                        {isPosting
                                                                            ? 'Tracking…'
                                                                            : 'Track'}
                                                                    </button>
                                                                ))}
                                                        </div>

                                                        {/* Links */}
                                                        <div className="flex space-x-3">
                                                            {item.links?.map((link, i) => {
                                                                let IconComponent;
                                                                if (link.type === 'twitter')
                                                                    IconComponent = TwitterIcon;
                                                                else if (link.type === 'telegram')
                                                                    IconComponent = TelegramIcon;
                                                                else if (link.label === 'Docs')
                                                                    IconComponent = DocsIcon;
                                                                else if (link.label === 'Website')
                                                                    IconComponent = WebsiteIcon;
                                                                else return null;

                                                                return (
                                                                    <a
                                                                        key={i}
                                                                        href={link.url}
                                                                        target="_blank"
                                                                        rel="noopener noreferrer"
                                                                        className="p-1 rounded text-icon-primary hover:text-icon-secondary"
                                                                        aria-label={
                                                                            link.label || link.type
                                                                        }
                                                                        title={
                                                                            link.label || link.type
                                                                        }
                                                                    >
                                                                        <IconComponent className="w-5 h-5" />
                                                                    </a>
                                                                );
                                                            })}
                                                        </div>
                                                    </div>
                                                </div>
                                            );
                                        })}

                                        {/* Button «Show more / Show less» */}
                                        {items.length > 3 && (
                                            <button
                                                onClick={() => toggleExpand(chainId)}
                                                className="flex items-center justify-center w-full mt-2 text-blue-500 hover:underline focus:outline-none"
                                            >
                                                {isExpanded ? (
                                                    <>
                                                        <ChevronUpIcon className="w-5 h-5 mr-1" />
                                                        Show less
                                                    </>
                                                ) : (
                                                    <>
                                                        <ChevronDownIcon className="w-5 h-5 mr-1" />
                                                        Show {remainingCount} more
                                                    </>
                                                )}
                                            </button>
                                        )}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
