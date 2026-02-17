import { PageProps, WhaleWallet } from '@/types';
import { Head, Link } from '@inertiajs/react';

interface Props extends PageProps {
    whales: WhaleWallet[];
}

export default function Whales({ auth, whales }: Props) {
    return (
        <>
            <Head title="Whale Tracking" />

            <div
                className="relative flex min-h-screen flex-col items-center justify-between bg-cover bg-center text-white"
                style={{
                    backgroundImage: `url(${window.location.origin}/images/app-main-theme.png)`,
                }}
            >
                {/* Navigation */}
                <div className="absolute right-6 top-6 flex gap-4">
                    {auth.user ? (
                        <>
                            <Link
                                href={route('dashboard')}
                                className="rounded-lg bg-white/80 px-4 py-2 font-semibold text-black transition hover:bg-white"
                            >
                                Dashboard
                            </Link>
                            <Link
                                href={route('chart')}
                                className="rounded-lg bg-white/80 px-4 py-2 font-semibold text-black transition hover:bg-white"
                            >
                                Chart
                            </Link>
                            <Link
                                href={route('watchlist.index')}
                                className="rounded-lg bg-white/80 px-4 py-2 font-semibold text-black transition hover:bg-white"
                            >
                                Watchlist
                            </Link>
                        </>
                    ) : (
                        <>
                            <Link
                                href={route('login')}
                                className="rounded-lg bg-white/80 px-4 py-2 font-semibold text-black transition hover:bg-white"
                            >
                                Log in
                            </Link>
                            <Link
                                href={route('register')}
                                className="rounded-lg bg-white/80 px-4 py-2 font-semibold text-black transition hover:bg-white"
                            >
                                Register
                            </Link>
                        </>
                    )}
                </div>

                {/* Title */}
                <div className="mt-24 text-center">
                    <h1 className="text-5xl font-extrabold drop-shadow-md">Whale Tracking</h1>
                </div>

                {/* Placeholder content */}
                <div className="mb-12 max-w-2xl px-4 text-center text-lg font-medium text-white/90">
                    <p className="mb-4">
                        Whale tracking is coming soon. Monitor large wallet movements, track
                        significant transfers, and get alerts when whales make moves.
                    </p>
                    <p className="text-sm text-white/70">
                        This feature is under active development. Stay tuned for updates.
                    </p>
                </div>
            </div>
        </>
    );
}
