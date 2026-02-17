import { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';

export default function Welcome({ auth }: PageProps) {
    return (
        <>
            <Head title="Welcome" />

            <div
                className="relative min-h-screen bg-cover bg-center text-white flex flex-col items-center justify-between"
                style={{
                    backgroundImage: `url(${window.location.origin}/images/app-main-theme.png)`,
                }}
            >
                {/* Navigation */}
                <div className="absolute top-6 right-6 flex gap-4">
                    {auth.user ? (
                        <>
                            <Link
                                href={route('dashboard')}
                                className="bg-white/80 text-black font-semibold px-4 py-2 rounded-lg hover:bg-white transition"
                            >
                                Dashboard
                            </Link>
                            <Link
                                href={route('chart')}
                                className="bg-white/80 text-black font-semibold px-4 py-2 rounded-lg hover:bg-white transition"
                            >
                                Chart
                            </Link>
                            <Link
                                href={route('whales')}
                                className="bg-white/80 text-black font-semibold px-4 py-2 rounded-lg hover:bg-white transition"
                            >
                                Whales
                            </Link>
                        </>
                    ) : (
                        <>
                            <Link
                                href={route('login')}
                                className="bg-white/80 text-black font-semibold px-4 py-2 rounded-lg hover:bg-white transition"
                            >
                                Log in
                            </Link>
                            <Link
                                href={route('register')}
                                className="bg-white/80 text-black font-semibold px-4 py-2 rounded-lg hover:bg-white transition"
                            >
                                Register
                            </Link>
                            <Link
                                href={route('whales')}
                                className="bg-white/80 text-black font-semibold px-4 py-2 rounded-lg hover:bg-white transition"
                            >
                                Whales
                            </Link>
                        </>
                    )}
                </div>

                {/* App name */}
                <div className="text-center mt-24">
                    <h1 className="text-5xl font-extrabold drop-shadow-md">Crypto Tracker</h1>
                </div>

                {/* Description */}
                <div className="mb-12 px-4 text-center max-w-2xl text-lg font-medium text-white/90">
                    Track crypto transactions, wallet links, and real-time analytics — with alerts, charts, and action mirroring all in one place.
                </div>
            </div>
        </>
    );
}
