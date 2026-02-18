import { Link, usePage } from '@inertiajs/react';
import { PropsWithChildren } from 'react';
import { PageProps } from '@/types';

export default function PublicLayout({ children }: PropsWithChildren) {
    const { auth } = usePage<PageProps>().props;

    return (
        <div
            className="relative flex min-h-screen flex-col bg-cover bg-center text-white"
            style={{
                backgroundImage: `url(${window.location.origin}/images/app-main-theme.png)`,
            }}
        >
            {/* Navigation */}
            <nav className="flex flex-wrap gap-3 items-center justify-between px-6 py-4">
                <div className="flex gap-4">
                    <Link
                        href={route('dashboard')}
                        className="rounded-lg bg-white/80 px-4 py-2 font-semibold text-black transition hover:bg-white"
                    >
                        Meme coins
                    </Link>
                    <Link
                        href={route('chart')}
                        className="rounded-lg bg-white/80 px-4 py-2 font-semibold text-black transition hover:bg-white"
                    >
                        Chart
                    </Link>
                    <Link
                        href={route('whales')}
                        className="rounded-lg bg-white/80 px-4 py-2 font-semibold text-black transition hover:bg-white"
                    >
                        Whales
                    </Link>
                </div>

                <div className="flex gap-4">
                    {auth.user ? (
                        <>
                            <Link
                                href={route('watchlist.index')}
                                className="rounded-lg bg-white/80 px-4 py-2 font-semibold text-black transition hover:bg-white"
                            >
                                Watchlist
                            </Link>
                            <Link
                                href={route('profile.edit')}
                                className="rounded-lg bg-white/80 px-4 py-2 font-semibold text-black transition hover:bg-white"
                            >
                                {auth.user.name}
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
            </nav>

            {children}
        </div>
    );
}
