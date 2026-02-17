import PublicLayout from '@/Layouts/PublicLayout';
import { Head } from '@inertiajs/react';

export default function Welcome() {
    return (
        <PublicLayout>
            <Head title="Welcome" />

            {/* App name */}
            <div className="mt-24 flex-1 text-center">
                <h1 className="text-5xl font-extrabold drop-shadow-md">Crypto Tracker</h1>
            </div>

            {/* Description */}
            <div className="mb-12 px-4 text-center max-w-2xl mx-auto text-lg font-medium text-white/90">
                Track crypto transactions, wallet links, and real-time analytics — with alerts,
                charts, and action mirroring all in one place.
            </div>
        </PublicLayout>
    );
}
