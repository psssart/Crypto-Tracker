import DangerButton from '@/Components/DangerButton';
import PrimaryButton from '@/Components/PrimaryButton';
import { router } from '@inertiajs/react';
import { useState } from 'react';

export default function TelegramLinkForm({
    telegramLinked,
    telegramUsername,
    className = '',
}: {
    telegramLinked: boolean;
    telegramUsername?: string;
    className?: string;
}) {
    const [loading, setLoading] = useState(false);

    const handleLink = async () => {
        setLoading(true);

        try {
            const response = await fetch(route('profile.telegram-link'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        document.querySelector<HTMLMetaElement>(
                            'meta[name="csrf-token"]',
                        )?.content ?? '',
                },
            });

            const data = await response.json();

            if (data.url) {
                window.open(data.url, '_blank');
            }
        } finally {
            setLoading(false);
        }
    };

    const handleUnlink = () => {
        router.post(route('profile.telegram-unlink'));
    };

    return (
        <section className={className}>
            <header>
                <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Telegram Notifications
                </h2>

                <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Connect your Telegram account to receive wallet alert notifications via
                    Telegram.
                </p>
            </header>

            <div className="mt-6">
                {telegramLinked ? (
                    <div className="flex items-center gap-4">
                        <div className="flex items-center gap-2">
                            <span className="inline-block h-2.5 w-2.5 rounded-full bg-green-500" />
                            <span className="text-sm text-gray-700 dark:text-gray-300">
                                Connected
                                {telegramUsername && (
                                    <span className="ml-1 font-medium">
                                        @{telegramUsername}
                                    </span>
                                )}
                            </span>
                        </div>

                        <DangerButton onClick={handleUnlink}>
                            Disconnect
                        </DangerButton>
                    </div>
                ) : (
                    <div className="flex items-center gap-4">
                        <PrimaryButton onClick={handleLink} disabled={loading}>
                            {loading ? 'Generating link...' : 'Connect Telegram'}
                        </PrimaryButton>

                        <p className="text-sm text-gray-500 dark:text-gray-400">
                            A link will open in Telegram to complete the connection.
                        </p>
                    </div>
                )}
            </div>
        </section>
    );
}
