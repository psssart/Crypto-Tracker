import { useState } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

type Integration = {
    id: number;
    provider: string;
    masked_key: string | null;
    settings: Record<string, any> | null;
    last_used_at: string | null;
    revoked_at: string | null;
};

type Props = {
    integrations: Integration[];
    status?: string;
};

export default function Integrations({ integrations, status }: Props) {
    const [editingId, setEditingId] = useState<number | null>(null);

    const createForm = useForm({
        provider: '',
        api_key: '',
        settings: {} as Record<string, any>,
    });

    const editForm = useForm({
        provider: '',
        api_key: '',
        settings: {} as Record<string, any>,
    });

    const startEdit = (integration: Integration) => {
        setEditingId(integration.id);
        editForm.setData({
            provider: integration.provider,
            api_key: '',
            settings: integration.settings || {},
        });
    };

    const submitCreate = (e: React.FormEvent) => {
        e.preventDefault();
        createForm.post(route('integrations.store'), {
            onSuccess: () => createForm.reset(),
        });
    };

    const submitEdit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editingId) return;

        editForm.patch(route('integrations.update', editingId), {
            onSuccess: () => setEditingId(null),
        });
    };

    const deleteIntegration = (id: number) => {
        if (!confirm('Delete this integration?')) return;
        router.delete(route('integrations.destroy', id));
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

                    {/* LIST */}
                    <div className="bg-white dark:bg-gray-800 p-6 shadow sm:rounded-lg">
                        <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                            Your integrations
                        </h3>

                        {integrations.length === 0 && (
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                No integrations yet.
                            </p>
                        )}

                        <div className="space-y-4">
                            {integrations.map((integration) => (
                                <div
                                    key={integration.id}
                                    className="flex justify-between items-center border border-gray-200 dark:border-gray-700 rounded p-4"
                                >
                                    <div>
                                        <div className="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                            {integration.provider}
                                        </div>
                                        <div className="text-xs text-gray-500 dark:text-gray-400">
                                            {integration.masked_key ?? 'No key'}
                                        </div>
                                    </div>

                                    <div className="flex items-center gap-3">
                                        <button
                                            onClick={() => startEdit(integration)}
                                            className="text-indigo-600 dark:text-indigo-400 text-sm hover:underline"
                                        >
                                            Edit
                                        </button>
                                        <button
                                            onClick={() => deleteIntegration(integration.id)}
                                            className="text-red-600 dark:text-red-400 text-sm hover:underline"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* CREATE */}
                    <div className="bg-white dark:bg-gray-800 p-6 shadow sm:rounded-lg">
                        <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                            Add new integration
                        </h3>

                        <form onSubmit={submitCreate} className="space-y-4 max-w-xl">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Provider
                                </label>
                                <input
                                    type="text"
                                    value={createForm.data.provider}
                                    onChange={e => createForm.setData('provider', e.target.value)}
                                    className="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                                    required
                                />
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    API key
                                </label>
                                <input
                                    type="password"
                                    value={createForm.data.api_key}
                                    onChange={e => createForm.setData('api_key', e.target.value)}
                                    className="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                                    required
                                />
                            </div>

                            <button
                                type="submit"
                                className="px-4 py-2 bg-indigo-600 text-white text-sm rounded shadow hover:bg-indigo-700"
                                disabled={createForm.processing}
                            >
                                Save
                            </button>
                        </form>
                    </div>

                    {/* EDIT FORM */}
                    {editingId && (
                        <div className="bg-white dark:bg-gray-800 p-6 shadow sm:rounded-lg">
                            <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                                Edit integration
                            </h3>

                            <form onSubmit={submitEdit} className="space-y-4 max-w-xl">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Provider
                                    </label>
                                    <input
                                        type="text"
                                        value={editForm.data.provider}
                                        onChange={e => editForm.setData('provider', e.target.value)}
                                        className="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                                        required
                                    />
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        New API key (leave empty to keep old one)
                                    </label>
                                    <input
                                        type="password"
                                        value={editForm.data.api_key}
                                        onChange={e => editForm.setData('api_key', e.target.value)}
                                        className="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                                    />
                                </div>

                                <div className="flex gap-3">
                                    <button
                                        type="submit"
                                        className="px-4 py-2 bg-indigo-600 text-white text-sm rounded shadow hover:bg-indigo-700"
                                        disabled={editForm.processing}
                                    >
                                        Update
                                    </button>

                                    <button
                                        type="button"
                                        onClick={() => setEditingId(null)}
                                        className="px-4 py-2 bg-gray-500 text-white text-sm rounded shadow hover:bg-gray-600"
                                    >
                                        Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    )}

                </div>
            </div>
        </AuthenticatedLayout>
    );
}
