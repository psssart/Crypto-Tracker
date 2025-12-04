import React, { useMemo, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {CheckCircleIcon, XCircleIcon, ArrowPathIcon, KeyIcon, LinkIcon, TrashIcon} from '@heroicons/react/24/outline';

type ProviderSecretField = {
    key: string;
    label: string;
    help?: string;
};

type ProviderConfig = {
    name: string;
    description?: string;
    docs_url?: string;
    secret_field: ProviderSecretField;
    extra_secret_field?: ProviderSecretField;
    default_settings?: Record<string, any>;
    health_check?: {
        enabled?: boolean;
    };
};

type Integration = {
    id: number;
    provider: string;
    provider_name: string;
    masked_key: string | null;
    has_api_key: boolean;
    last_used_at: string | null;
    revoked_at: string | null;
};

type Props = {
    providers: Record<string, ProviderConfig>;
    integrations: Integration[];
    status?: string;
};

type CheckState = 'idle' | 'checking' | 'success' | 'error';

export default function Integrations({ providers, integrations, status }: Props) {
    const integrationByProvider = useMemo(() => {
        const map: Record<string, Integration> = {};
        integrations.forEach((i) => {
            map[i.provider] = i;
        });
        return map;
    }, [integrations]);

    const [apiKeys, setApiKeys] = useState<Record<string, string>>(() => {
        const initial: Record<string, string> = {};
        Object.keys(providers).forEach((key) => {
            initial[key] = '';
        });
        return initial;
    });

    const [extraSecrets, setExtraSecrets] = useState<Record<string, string>>(() => {
        const initial: Record<string, string> = {};
        Object.keys(providers).forEach((key) => {
            initial[key] = '';
        });
        return initial;
    });

    const [checkStatus, setCheckStatus] = useState<Record<string, CheckState>>({});
    const [checkError, setCheckError] = useState<Record<string, string | null>>({});
    const [savingProvider, setSavingProvider] = useState<string | null>(null);
    const [deletingProvider, setDeletingProvider] = useState<string | null>(null);

    const csrfToken =
        (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';

    const updateApiKey = (providerKey: string, value: string) => {
        setApiKeys((prev) => ({ ...prev, [providerKey]: value }));
        setCheckStatus((prev) => ({ ...prev, [providerKey]: 'idle' }));
        setCheckError((prev) => ({ ...prev, [providerKey]: null }));
    };

    const updateExtraSecret = (providerKey: string, value: string) => {
        setExtraSecrets((prev) => ({ ...prev, [providerKey]: value }));
        setCheckStatus((prev) => ({ ...prev, [providerKey]: 'idle' }));
        setCheckError((prev) => ({ ...prev, [providerKey]: null }));
    };

    const currentStatus = (providerKey: string): CheckState =>
        checkStatus[providerKey] ?? 'idle';

    const currentError = (providerKey: string): string | null =>
        checkError[providerKey] ?? null;

    const handleTestConnection = async (providerKey: string) => {
        const apiKey = apiKeys[providerKey];
        const apiSecret = extraSecrets[providerKey];
        const config = providers[providerKey];
        const requiresExtraSecret = !!config.extra_secret_field;

        if (!apiKey || (requiresExtraSecret && !apiSecret)) {
            setCheckError((prev) => ({
                ...prev,
                [providerKey]: 'Fill all required credentials first.',
            }));
            setCheckStatus((prev) => ({ ...prev, [providerKey]: 'error' }));
            return;
        }

        setCheckStatus((prev) => ({ ...prev, [providerKey]: 'checking' }));
        setCheckError((prev) => ({ ...prev, [providerKey]: null }));

        try {
            const response = await fetch(route('integrations.check'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    provider: providerKey,
                    api_key: apiKey,
                    api_secret: requiresExtraSecret ? apiSecret : undefined,
                }),
            });

            const data = await response.json();

            if (response.ok && data.ok) {
                setCheckStatus((prev) => ({ ...prev, [providerKey]: 'success' }));
                setCheckError((prev) => ({ ...prev, [providerKey]: null }));
            } else {
                setCheckStatus((prev) => ({ ...prev, [providerKey]: 'error' }));
                setCheckError((prev) => ({
                    ...prev,
                    [providerKey]: data.message || 'Health check failed.',
                }));
            }
        } catch {
            setCheckStatus((prev) => ({ ...prev, [providerKey]: 'error' }));
            setCheckError((prev) => ({
                ...prev,
                [providerKey]: 'Network error during health check.',
            }));
        }
    };

    const handleSave = (providerKey: string) => {
        const apiKey = apiKeys[providerKey];
        const apiSecret = extraSecrets[providerKey];
        const config = providers[providerKey];
        const requiresExtraSecret = !!config.extra_secret_field;

        if (!apiKey || (requiresExtraSecret && !apiSecret) || currentStatus(providerKey) !== 'success') {
            return;
        }

        setSavingProvider(providerKey);

        router.post(
            route('integrations.store'),
            {
                provider: providerKey,
                api_key: apiKey,
                api_secret: requiresExtraSecret ? apiSecret : undefined,
            },
            {
                preserveScroll: true,
                onFinish: () => setSavingProvider(null),
            }
        );
    };

    const handleDelete = (providerKey: string) => {
        const integration = integrationByProvider[providerKey];
        if (!integration) return;

        if (!confirm(`Remove ${integration.provider_name} integration?`)) {
            return;
        }

        setDeletingProvider(providerKey);

        router.delete(route('integrations.destroy', integration.id), {
            preserveScroll: true,
            onFinish: () => setDeletingProvider(null),
        });
    };

    const renderStatusIcon = (providerKey: string) => {
        const state = currentStatus(providerKey);

        if (state === 'checking') {
            return (
                <ArrowPathIcon className="h-5 w-5 animate-spin text-blue-500" />
            );
        }

        if (state === 'success') {
            return (
                <CheckCircleIcon className="h-5 w-5 text-green-500" />
            );
        }

        if (state === 'error') {
            return (
                <XCircleIcon className="h-5 w-5 text-red-500" />
            );
        }

        return null;
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold text-gray-800 dark:text-gray-200">
                    Integrations
                </h2>
            }
        >
            <Head title="Integrations" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    {status && (
                        <div className="rounded-md bg-green-50 p-4 text-sm text-green-800 dark:bg-green-900/30 dark:text-green-200">
                            {status}
                        </div>
                    )}

                    <div className="bg-white p-6 shadow sm:rounded-lg dark:bg-gray-800">
                        <p className="mb-4 text-sm text-gray-600 dark:text-gray-400">
                            Connect your account to available integrations. API keys are
                            stored securely and never shown in full.
                        </p>

                        <div className="grid gap-6 md:grid-cols-2">
                            {Object.entries(providers).map(([key, config]) => {
                                const integration = integrationByProvider[key];
                                const hasIntegration = !!integration;
                                const healthEnabled =
                                    config.health_check?.enabled ?? false;
                                const state = currentStatus(key);
                                const error = currentError(key);
                                const requiresExtraSecret = !!config.extra_secret_field;

                                return (
                                    <div
                                        key={key}
                                        className="flex flex-col justify-between rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900"
                                    >
                                        <div className="space-y-3">
                                            <div className="flex items-center justify-between">
                                                <div className="flex items-center gap-2">
                                                    <KeyIcon className="h-5 w-5 text-gray-400" />
                                                    <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                                        {config.name}
                                                    </h3>
                                                </div>

                                                {hasIntegration && (
                                                    <span className="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900/40 dark:text-green-200">
                                                        Connected
                                                    </span>
                                                )}
                                            </div>

                                            {config.description && (
                                                <p className="text-xs text-gray-600 dark:text-gray-400">
                                                    {config.description}
                                                </p>
                                            )}

                                            <div className="flex flex-wrap items-center gap-3 text-xs">
                                                {config.docs_url && (
                                                    <a
                                                        href={config.docs_url}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        className="inline-flex items-center gap-1 text-indigo-600 hover:underline dark:text-indigo-400"
                                                    >
                                                        <LinkIcon className="h-4 w-4" />
                                                        Docs
                                                    </a>
                                                )}

                                                {integration?.masked_key && (
                                                    <span className="text-gray-500 dark:text-gray-400">
                                                        Current key: {integration.masked_key}
                                                    </span>
                                                )}
                                            </div>

                                            <div className="space-y-2">
                                                <label className="block text-xs font-medium text-gray-700 dark:text-gray-300">
                                                    {config.secret_field.label}
                                                </label>
                                                <input
                                                    type="password"
                                                    value={apiKeys[key] ?? ''}
                                                    onChange={(e) =>
                                                        updateApiKey(key, e.target.value)
                                                    }
                                                    placeholder={
                                                        hasIntegration
                                                            ? 'Enter new key to replace existing'
                                                            : 'Enter API key'
                                                    }
                                                    className="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100"
                                                />
                                                {config.secret_field.help && (
                                                    <p className="text-[11px] text-gray-500 dark:text-gray-400">
                                                        {config.secret_field.help}
                                                    </p>
                                                )}
                                            </div>

                                            {config.extra_secret_field && (
                                                <div className="space-y-2 mt-3">
                                                    <label className="block text-xs font-medium text-gray-700 dark:text-gray-300">
                                                        {config.extra_secret_field.label}
                                                    </label>
                                                    <input
                                                        type="password"
                                                        value={extraSecrets[key] ?? ''}
                                                        onChange={(e) => updateExtraSecret(key, e.target.value)}
                                                        placeholder="Enter secret key"
                                                        className="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100"
                                                    />
                                                    {config.extra_secret_field.help && (
                                                        <p className="text-[11px] text-gray-500 dark:text-gray-400">
                                                            {config.extra_secret_field.help}
                                                        </p>
                                                    )}
                                                </div>
                                            )}

                                            {error && (
                                                <p className="text-xs text-red-500">
                                                    {error}
                                                </p>
                                            )}
                                        </div>

                                        <div className="mt-4 flex items-center justify-between">
                                            <div className="flex items-center gap-2">
                                                {healthEnabled && (
                                                    <>
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                handleTestConnection(key)
                                                            }
                                                            disabled={
                                                                !apiKeys[key] ||
                                                                (requiresExtraSecret && !extraSecrets[key]) ||
                                                                state === 'checking'
                                                            }
                                                            className="inline-flex items-center gap-1 rounded-md border border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-100 disabled:opacity-60 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
                                                        >
                                                            {state === 'checking' ? (
                                                                'Testing...'
                                                            ) : (
                                                                'Test connection'
                                                            )}
                                                        </button>
                                                        {renderStatusIcon(key)}
                                                    </>
                                                )}
                                            </div>

                                            <div className="flex items-center gap-2">
                                                {hasIntegration && (
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            handleDelete(key)
                                                        }
                                                        disabled={
                                                            deletingProvider === key
                                                        }
                                                        className="inline-flex items-center gap-1 rounded-md border border-transparent px-2.5 py-1 text-xs font-medium text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/40"
                                                    >
                                                        <TrashIcon className="h-4 w-4" />
                                                        {deletingProvider === key
                                                            ? 'Removing...'
                                                            : 'Remove'}
                                                    </button>
                                                )}

                                                <button
                                                    type="button"
                                                    onClick={() => handleSave(key)}
                                                    disabled={
                                                        !apiKeys[key] ||
                                                        (requiresExtraSecret && !extraSecrets[key]) ||
                                                        state !== 'success' ||
                                                        savingProvider === key
                                                    }
                                                    className="inline-flex items-center rounded-md border border-transparent bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-indigo-700 disabled:opacity-60 dark:bg-indigo-500 dark:hover:bg-indigo-600"
                                                >
                                                    {savingProvider === key
                                                        ? 'Saving...'
                                                        : hasIntegration
                                                            ? 'Update'
                                                            : 'Connect'}
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
