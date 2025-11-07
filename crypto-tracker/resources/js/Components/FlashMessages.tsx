import { useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';
import {
    CheckCircleIcon,
    ExclamationTriangleIcon,
    InformationCircleIcon,
    XMarkIcon,
} from '@heroicons/react/24/outline';
import { Transition } from '@headlessui/react';

type Flash = {
    success?: string;
    error?: string;
    info?: string;
    status?: string;
};

export function FlashMessages() {
    const { flash } = usePage().props as { flash?: Flash };
    const [message, setMessage] = useState<Flash | null>(flash ?? null);

    useEffect(() => {
        setMessage(flash ?? null);

        if (flash?.success || flash?.error || flash?.info || flash?.status) {
            const timeout = setTimeout(() => setMessage(null), 4000);
            return () => clearTimeout(timeout);
        }
    }, [flash]);

    if (!message) return null;

    const text =
        message.success ??
        message.error ??
        message.info ??
        message.status;

    if (!text) return null;

    const type =
        message.error ? 'error' :
            message.success ? 'success' :
                message.info ? 'info' :
                    'info';

    const iconMap = {
        success: <CheckCircleIcon className="h-5 w-5 text-emerald-300" />,
        error: <ExclamationTriangleIcon className="h-5 w-5 text-red-300" />,
        info: <InformationCircleIcon className="h-5 w-5 text-sky-300" />,
    };

    const bgClass =
        type === 'error'
            ? 'bg-red-600/90 text-white'
            : type === 'success'
                ? 'bg-emerald-600/90 text-white'
                : 'bg-slate-800/90 text-white';

    return (
        <Transition
            appear
            show={!!message}
            enter="transform transition duration-300"
            enterFrom="translate-y-[-10px] opacity-0"
            enterTo="translate-y-0 opacity-100"
            leave="transform duration-300 transition ease-in-out"
            leaveFrom="translate-y-0 opacity-100"
            leaveTo="translate-y-[-10px] opacity-0"
        >
            <div className="fixed top-4 right-4 z-50 w-80">
                <div
                    className={`flex items-start justify-between rounded-xl px-4 py-3 shadow-lg ring-1 ring-black/10 ${bgClass}`}
                >
                    <div className="flex items-center gap-3">
                        {iconMap[type as keyof typeof iconMap]}
                        <p className="text-sm font-medium leading-tight">
                            {text}
                        </p>
                    </div>

                    <button
                        onClick={() => setMessage(null)}
                        className="ml-3 rounded-md p-1 hover:bg-white/10 transition"
                        aria-label="Close notification"
                    >
                        <XMarkIcon className="h-5 w-5" />
                    </button>
                </div>
            </div>
        </Transition>
    );
}
