import { type ReactNode, useCallback, useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';
import {
    CheckCircleIcon,
    ExclamationTriangleIcon,
    InformationCircleIcon,
    XMarkIcon,
} from '@heroicons/react/24/outline';
import { Transition } from '@headlessui/react';

type FlashType = 'success' | 'error' | 'info';

type FlashItem = { id: number; type: FlashType; content: ReactNode };

type InertiaFlash = {
    success?: string;
    error?: string;
    info?: string;
    status?: string;
};

const FLASH_DURATION_MS = 4_000;
let idCounter = 0;

/* ---- Public API: call from anywhere ---- */

export function flashError(content: ReactNode) {
    window.dispatchEvent(
        new CustomEvent('flash', { detail: { type: 'error', content } }),
    );
}

export function flashSuccess(content: ReactNode) {
    window.dispatchEvent(
        new CustomEvent('flash', { detail: { type: 'success', content } }),
    );
}

export function flashInfo(content: ReactNode) {
    window.dispatchEvent(
        new CustomEvent('flash', { detail: { type: 'info', content } }),
    );
}

/* ---- Component ---- */

export function FlashMessages() {
    const { flash } = usePage().props as { flash?: InertiaFlash };
    const [items, setItems] = useState<FlashItem[]>([]);

    const push = useCallback((type: FlashType, content: ReactNode) => {
        const id = ++idCounter;
        setItems((prev) => [...prev.slice(-4), { id, type, content }]);
        setTimeout(() => {
            setItems((prev) => prev.filter((i) => i.id !== id));
        }, FLASH_DURATION_MS);
    }, []);

    const dismiss = useCallback((id: number) => {
        setItems((prev) => prev.filter((i) => i.id !== id));
    }, []);

    // Inertia server-side flash
    useEffect(() => {
        if (flash?.error) push('error', flash.error);
        else if (flash?.success) push('success', flash.success);
        else if (flash?.info) push('info', flash.info);
        else if (flash?.status) push('info', flash.status);
    }, [flash, push]);

    // Client-side custom events
    useEffect(() => {
        const handler = (e: Event) => {
            const { type, content } = (e as CustomEvent).detail;
            push(type, content);
        };
        window.addEventListener('flash', handler);
        return () => window.removeEventListener('flash', handler);
    }, [push]);

    const iconMap = {
        success: <CheckCircleIcon className="h-5 w-5 text-emerald-300" />,
        error: <ExclamationTriangleIcon className="h-5 w-5 text-red-300" />,
        info: <InformationCircleIcon className="h-5 w-5 text-sky-300" />,
    };

    const bgMap = {
        error: 'bg-red-600/90 text-white',
        success: 'bg-emerald-600/90 text-white',
        info: 'bg-slate-800/90 text-white',
    };

    return (
        <div className="fixed top-4 right-4 z-50 flex w-80 flex-col gap-2">
            {items.map((item) => (
                <Transition
                    key={item.id}
                    appear
                    show
                    enter="transform transition duration-300"
                    enterFrom="translate-y-[-10px] opacity-0"
                    enterTo="translate-y-0 opacity-100"
                >
                    <div
                        className={`flex items-start justify-between rounded-xl px-4 py-3 shadow-lg ring-1 ring-black/10 ${bgMap[item.type]}`}
                    >
                        <div className="flex items-center gap-3">
                            {iconMap[item.type]}
                            <p className="text-sm font-medium leading-tight">
                                {item.content}
                            </p>
                        </div>

                        <button
                            onClick={() => dismiss(item.id)}
                            className="ml-3 rounded-md p-1 transition hover:bg-white/10"
                            aria-label="Close notification"
                        >
                            <XMarkIcon className="h-5 w-5" />
                        </button>
                    </div>
                </Transition>
            ))}
        </div>
    );
}
