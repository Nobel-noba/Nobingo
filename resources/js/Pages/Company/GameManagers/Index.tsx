import CompanyAdminLayout from '@/Layouts/CompanyAdminLayout';
import { PageProps } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import React, { useState } from 'react';

interface GameManagerItem {
    id: number;
    name: string;
    email: string;
    status: string;
    games_count: number;
    created_at: string;
}

interface Props extends PageProps {
    company: {
        id: number;
        name: string;
        slug: string;
    };
    managers: GameManagerItem[];
}

export default function GameManagersIndex({ company, managers }: Props) {
    const [createModalOpen, setCreateModalOpen] = useState(false);
    const [editManager, setEditManager] = useState<GameManagerItem | null>(null);
    const [resetPassManager, setResetPassManager] = useState<GameManagerItem | null>(null);

    const createForm = useForm({
        name: '',
        email: '',
        password: '',
    });

    const editForm = useForm({
        name: '',
        email: '',
        status: 'active',
    });

    const resetPassForm = useForm({
        password: '',
    });

    const generateRandomPassword = () => {
        return 'Manager' + Math.floor(1000 + Math.random() * 9000) + '!';
    };

    const handleCreateSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        createForm.post(`/c/${company.slug}/admin/game-managers`, {
            preserveScroll: true,
            onSuccess: () => {
                setCreateModalOpen(false);
                createForm.reset();
            },
        });
    };

    const handleEditSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editManager) return;
        editForm.patch(`/c/${company.slug}/admin/game-managers/${editManager.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setEditManager(null);
                editForm.reset();
            },
        });
    };

    const handleResetPassSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!resetPassManager) return;
        resetPassForm.post(`/c/${company.slug}/admin/game-managers/${resetPassManager.id}/reset-password`, {
            preserveScroll: true,
            onSuccess: () => {
                setResetPassManager(null);
                resetPassForm.reset();
            },
        });
    };

    const handleDelete = (manager: GameManagerItem) => {
        if (confirm(`Are you sure you want to remove Game Manager "${manager.name}"? They will lose access immediately.`)) {
            router.delete(`/c/${company.slug}/admin/game-managers/${manager.id}`, {
                preserveScroll: true,
            });
        }
    };

    return (
        <CompanyAdminLayout>
            <Head title={`Game Managers - ${company.name}`} />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-white tracking-tight">Game Managers & Venue Operators</h1>
                        <p className="text-sm text-neutral-400 mt-1">
                            Provision operator accounts, regulate shift access, issue credentials, and supervise game callers.
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={() => {
                            createForm.setData({
                                name: '',
                                email: '',
                                password: generateRandomPassword(),
                            });
                            setCreateModalOpen(true);
                        }}
                        className="bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs px-4 py-2.5 rounded-xl shadow-md flex items-center gap-2 transition self-start sm:self-auto cursor-pointer"
                    >
                        <span>+ Add Game Manager</span>
                    </button>
                </div>

                {/* Managers Table */}
                <div className="bg-neutral-900 border border-neutral-800 rounded-xl overflow-hidden shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm text-neutral-300">
                            <thead className="bg-neutral-950 text-xs uppercase text-neutral-400 font-medium border-b border-neutral-800">
                                <tr>
                                    <th className="px-4 py-3">Operator</th>
                                    <th className="px-4 py-3">Role</th>
                                    <th className="px-4 py-3">Status</th>
                                    <th className="px-4 py-3">Games Hosted</th>
                                    <th className="px-4 py-3">Created</th>
                                    <th className="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-neutral-800">
                                {managers.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="px-4 py-8 text-center text-neutral-500">
                                            No Game Managers assigned to this company yet. Click "+ Add Game Manager" to create one.
                                        </td>
                                    </tr>
                                ) : (
                                    managers.map((m) => (
                                        <tr key={m.id} className="hover:bg-neutral-800/40 transition">
                                            <td className="px-4 py-3 whitespace-nowrap">
                                                <div className="font-semibold text-neutral-200">{m.name}</div>
                                                <div className="text-xs text-neutral-500">{m.email}</div>
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap">
                                                <span className="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                                                    Game Manager
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap">
                                                <span
                                                    className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border ${
                                                        m.status === 'active'
                                                            ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20'
                                                            : 'bg-rose-500/10 text-rose-400 border-rose-500/20'
                                                    }`}
                                                >
                                                    {m.status}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap text-xs font-bold text-amber-400">
                                                {m.games_count}
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap text-xs text-neutral-400">
                                                {m.created_at}
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap text-right space-x-2">
                                                <button
                                                    onClick={() => {
                                                        setEditManager(m);
                                                        editForm.setData({
                                                            name: m.name,
                                                            email: m.email,
                                                            status: m.status,
                                                        });
                                                    }}
                                                    className="text-xs bg-neutral-800 hover:bg-neutral-700 text-neutral-200 px-2.5 py-1 rounded transition border border-neutral-700"
                                                >
                                                    Edit
                                                </button>
                                                <button
                                                    onClick={() => {
                                                        setResetPassManager(m);
                                                        resetPassForm.setData('password', generateRandomPassword());
                                                    }}
                                                    className="text-xs bg-amber-950/60 hover:bg-amber-900 text-amber-300 border border-amber-700/60 px-2.5 py-1 rounded transition"
                                                >
                                                    Password
                                                </button>
                                                <button
                                                    onClick={() => handleDelete(m)}
                                                    className="text-xs bg-rose-950/60 hover:bg-rose-900 text-rose-300 border border-rose-700/60 px-2.5 py-1 rounded transition"
                                                >
                                                    Remove
                                                </button>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {/* Create Modal */}
            {createModalOpen && (
                <div className="fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4">
                    <div className="bg-neutral-900 border border-neutral-800 rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
                        <div className="flex justify-between items-center border-b border-neutral-800 pb-3">
                            <h3 className="font-bold text-white text-base">Add New Game Manager</h3>
                            <button onClick={() => setCreateModalOpen(false)} className="text-neutral-400 hover:text-white text-xs font-bold">
                                ✕
                            </button>
                        </div>

                        <form onSubmit={handleCreateSubmit} className="space-y-4 text-xs">
                            <div>
                                <label className="block uppercase font-bold text-neutral-400 mb-1">Full Name</label>
                                <input
                                    type="text"
                                    required
                                    placeholder="e.g. Alex Operator"
                                    value={createForm.data.name}
                                    onChange={(e) => createForm.setData('name', e.target.value)}
                                    className="w-full bg-neutral-950 border border-neutral-700 rounded-lg px-3 py-2 text-white focus:ring-indigo-500 focus:border-indigo-500"
                                />
                                {createForm.errors.name && <div className="text-rose-400 text-[11px] mt-1">{createForm.errors.name}</div>}
                            </div>

                            <div>
                                <label className="block uppercase font-bold text-neutral-400 mb-1">Email Address</label>
                                <input
                                    type="email"
                                    required
                                    placeholder="operator@company.com"
                                    value={createForm.data.email}
                                    onChange={(e) => createForm.setData('email', e.target.value)}
                                    className="w-full bg-neutral-950 border border-neutral-700 rounded-lg px-3 py-2 text-white focus:ring-indigo-500 focus:border-indigo-500"
                                />
                                {createForm.errors.email && <div className="text-rose-400 text-[11px] mt-1">{createForm.errors.email}</div>}
                            </div>

                            <div>
                                <div className="flex justify-between items-center mb-1">
                                    <label className="block uppercase font-bold text-neutral-400">Password</label>
                                    <button
                                        type="button"
                                        onClick={() => createForm.setData('password', generateRandomPassword())}
                                        className="text-[11px] text-indigo-400 hover:text-indigo-300 underline font-semibold"
                                    >
                                        Generate
                                    </button>
                                </div>
                                <input
                                    type="text"
                                    required
                                    value={createForm.data.password}
                                    onChange={(e) => createForm.setData('password', e.target.value)}
                                    className="w-full bg-neutral-950 border border-neutral-700 rounded-lg px-3 py-2 text-white font-mono focus:ring-indigo-500 focus:border-indigo-500"
                                />
                                {createForm.errors.password && <div className="text-rose-400 text-[11px] mt-1">{createForm.errors.password}</div>}
                            </div>

                            <div className="flex justify-end space-x-2 pt-2 border-t border-neutral-800">
                                <button
                                    type="button"
                                    onClick={() => setCreateModalOpen(false)}
                                    className="px-4 py-2 rounded-lg text-xs font-semibold text-neutral-400 hover:text-white bg-neutral-800"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={createForm.processing}
                                    className="px-5 py-2 rounded-lg text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-500 transition shadow"
                                >
                                    {createForm.processing ? 'Creating...' : 'Create Game Manager'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Edit Modal */}
            {editManager && (
                <div className="fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4">
                    <div className="bg-neutral-900 border border-neutral-800 rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
                        <div className="flex justify-between items-center border-b border-neutral-800 pb-3">
                            <h3 className="font-bold text-white text-base">Edit Game Manager: {editManager.name}</h3>
                            <button onClick={() => setEditManager(null)} className="text-neutral-400 hover:text-white text-xs font-bold">
                                ✕
                            </button>
                        </div>

                        <form onSubmit={handleEditSubmit} className="space-y-4 text-xs">
                            <div>
                                <label className="block uppercase font-bold text-neutral-400 mb-1">Full Name</label>
                                <input
                                    type="text"
                                    required
                                    value={editForm.data.name}
                                    onChange={(e) => editForm.setData('name', e.target.value)}
                                    className="w-full bg-neutral-950 border border-neutral-700 rounded-lg px-3 py-2 text-white focus:ring-indigo-500 focus:border-indigo-500"
                                />
                                {editForm.errors.name && <div className="text-rose-400 text-[11px] mt-1">{editForm.errors.name}</div>}
                            </div>

                            <div>
                                <label className="block uppercase font-bold text-neutral-400 mb-1">Email Address</label>
                                <input
                                    type="email"
                                    required
                                    value={editForm.data.email}
                                    onChange={(e) => editForm.setData('email', e.target.value)}
                                    className="w-full bg-neutral-950 border border-neutral-700 rounded-lg px-3 py-2 text-white focus:ring-indigo-500 focus:border-indigo-500"
                                />
                                {editForm.errors.email && <div className="text-rose-400 text-[11px] mt-1">{editForm.errors.email}</div>}
                            </div>

                            <div>
                                <label className="block uppercase font-bold text-neutral-400 mb-1">Account Status</label>
                                <select
                                    value={editForm.data.status}
                                    onChange={(e) => editForm.setData('status', e.target.value)}
                                    className="w-full bg-neutral-950 border border-neutral-700 rounded-lg px-3 py-2 text-white focus:ring-indigo-500 focus:border-indigo-500"
                                >
                                    <option value="active">Active (Access Allowed)</option>
                                    <option value="suspended">Suspended (Access Revoked)</option>
                                </select>
                            </div>

                            <div className="flex justify-end space-x-2 pt-2 border-t border-neutral-800">
                                <button
                                    type="button"
                                    onClick={() => setEditManager(null)}
                                    className="px-4 py-2 rounded-lg text-xs font-semibold text-neutral-400 hover:text-white bg-neutral-800"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={editForm.processing}
                                    className="px-5 py-2 rounded-lg text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-500 transition shadow"
                                >
                                    {editForm.processing ? 'Saving...' : 'Save Changes'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Reset Password Modal */}
            {resetPassManager && (
                <div className="fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4">
                    <div className="bg-neutral-900 border border-neutral-800 rounded-2xl max-w-sm w-full p-6 shadow-2xl space-y-4">
                        <div className="flex justify-between items-center border-b border-neutral-800 pb-3">
                            <h3 className="font-bold text-white text-base">Set Password: {resetPassManager.name}</h3>
                            <button onClick={() => setResetPassManager(null)} className="text-neutral-400 hover:text-white text-xs font-bold">
                                ✕
                            </button>
                        </div>

                        <form onSubmit={handleResetPassSubmit} className="space-y-4 text-xs">
                            <div>
                                <div className="flex justify-between items-center mb-1">
                                    <label className="block uppercase font-bold text-neutral-400">New Password</label>
                                    <button
                                        type="button"
                                        onClick={() => resetPassForm.setData('password', generateRandomPassword())}
                                        className="text-[11px] text-amber-400 hover:text-amber-300 underline font-semibold"
                                    >
                                        Generate
                                    </button>
                                </div>
                                <input
                                    type="text"
                                    required
                                    value={resetPassForm.data.password}
                                    onChange={(e) => resetPassForm.setData('password', e.target.value)}
                                    className="w-full bg-neutral-950 border border-neutral-700 rounded-lg px-3 py-2 text-white font-mono focus:ring-amber-500 focus:border-amber-500"
                                />
                                {resetPassForm.errors.password && <div className="text-rose-400 text-[11px] mt-1">{resetPassForm.errors.password}</div>}
                            </div>

                            <div className="flex justify-end space-x-2 pt-2 border-t border-neutral-800">
                                <button
                                    type="button"
                                    onClick={() => setResetPassManager(null)}
                                    className="px-4 py-2 rounded-lg text-xs font-semibold text-neutral-400 hover:text-white bg-neutral-800"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={resetPassForm.processing}
                                    className="px-5 py-2 rounded-lg text-xs font-bold text-white bg-amber-600 hover:bg-amber-500 transition shadow"
                                >
                                    {resetPassForm.processing ? 'Updating...' : 'Set Password'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </CompanyAdminLayout>
    );
}
