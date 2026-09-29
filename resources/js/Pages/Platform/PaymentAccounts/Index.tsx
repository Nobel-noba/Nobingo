import PlatformOwnerLayout from '@/Layouts/PlatformOwnerLayout';
import { PageProps } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import React, { useState } from 'react';

interface PaymentAccount {
    id: number;
    provider_name: string;
    account_name: string;
    account_number: string;
    instructions: string | null;
    is_active: boolean;
    created_at: string;
}

interface Props extends PageProps {
    accounts: PaymentAccount[];
}

export default function PlatformPaymentAccountsIndex({ accounts }: Props) {
    const [modalOpen, setModalOpen] = useState(false);
    const [editingAccount, setEditingAccount] = useState<PaymentAccount | null>(null);

    const form = useForm<{
        provider_name: string;
        account_name: string;
        account_number: string;
        instructions: string;
        is_active: boolean;
    }>({
        provider_name: '',
        account_name: '',
        account_number: '',
        instructions: '',
        is_active: true,
    });

    const openCreateModal = () => {
        setEditingAccount(null);
        form.reset();
        form.setData({
            provider_name: '',
            account_name: '',
            account_number: '',
            instructions: '',
            is_active: true,
        });
        setModalOpen(true);
    };

    const openEditModal = (acc: PaymentAccount) => {
        setEditingAccount(acc);
        form.setData({
            provider_name: acc.provider_name,
            account_name: acc.account_name,
            account_number: acc.account_number,
            instructions: acc.instructions || '',
            is_active: acc.is_active,
        });
        setModalOpen(true);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingAccount) {
            form.put(`/platform/payment-accounts/${editingAccount.id}`, {
                preserveScroll: true,
                onSuccess: () => setModalOpen(false),
            });
        } else {
            form.post('/platform/payment-accounts', {
                preserveScroll: true,
                onSuccess: () => setModalOpen(false),
            });
        }
    };

    const handleDelete = (acc: PaymentAccount) => {
        if (confirm(`Are you sure you want to delete platform payment account "${acc.provider_name} - ${acc.account_name}"?`)) {
            router.delete(`/platform/payment-accounts/${acc.id}`, {
                preserveScroll: true,
            });
        }
    };

    return (
        <PlatformOwnerLayout
            header={
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-bold text-white tracking-tight">Platform Payment Accounts</h1>
                        <p className="text-xs text-slate-400 mt-0.5">
                            Bank accounts, mobile money, and crypto/wire addresses where companies send funds to buy platform credits.
                        </p>
                    </div>
                    <button
                        onClick={openCreateModal}
                        className="px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs flex items-center gap-1.5 transition shadow-sm"
                    >
                        <span>➕</span> Add Platform Account
                    </button>
                </div>
            }
        >
            <Head title="Platform Payment Accounts" />

            <div className="space-y-6">
                <div className="p-6 rounded-2xl bg-slate-900 border border-slate-800 shadow-sm space-y-4">
                    <div className="flex items-center justify-between">
                        <h2 className="text-sm font-bold text-white uppercase tracking-wider flex items-center gap-2">
                            <span>💳</span> Active Accounts ({accounts.length})
                        </h2>
                        <span className="text-xs text-slate-400">
                            Visible to all tenant companies when requesting platform credits.
                        </span>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        {accounts.length === 0 ? (
                            <div className="col-span-full p-8 text-center bg-slate-950/60 rounded-xl border border-slate-800 text-slate-500 text-xs">
                                No platform payment accounts created yet. Add an account so companies know where to wire money for platform credit!
                            </div>
                        ) : (
                            accounts.map((acc) => (
                                <div key={acc.id} className="p-5 rounded-xl bg-slate-950 border border-slate-800 flex flex-col justify-between space-y-4 shadow-sm">
                                    <div className="space-y-2">
                                        <div className="flex items-center justify-between">
                                            <span className="text-xs font-black uppercase text-rose-400 tracking-wider">
                                                {acc.provider_name}
                                            </span>
                                            <span className={`px-2 py-0.5 rounded text-[10px] font-bold ${
                                                acc.is_active
                                                    ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30'
                                                    : 'bg-slate-800 text-slate-400'
                                            }`}>
                                                {acc.is_active ? 'Active' : 'Disabled'}
                                            </span>
                                        </div>
                                        <div className="text-sm font-bold text-white">{acc.account_name}</div>
                                        <div className="text-xs font-mono font-bold text-emerald-400">{acc.account_number}</div>
                                        {acc.instructions && (
                                            <p className="text-[11px] text-slate-400 border-t border-slate-800 pt-2">
                                                {acc.instructions}
                                            </p>
                                        )}
                                    </div>

                                    <div className="flex justify-end gap-2 pt-2 border-t border-slate-800">
                                        <button
                                            onClick={() => openEditModal(acc)}
                                            className="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold"
                                        >
                                            Edit
                                        </button>
                                        <button
                                            onClick={() => handleDelete(acc)}
                                            className="px-3 py-1.5 rounded-lg bg-rose-950/40 hover:bg-rose-900/60 text-rose-400 border border-rose-800/40 text-xs font-semibold"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </div>
                            ))
                        )}
                    </div>
                </div>

                {/* Add/Edit Modal */}
                {modalOpen && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
                        <div className="bg-slate-900 border border-slate-800 rounded-2xl max-w-lg w-full p-6 shadow-2xl space-y-6">
                            <div className="flex justify-between items-center pb-4 border-b border-slate-800">
                                <h3 className="text-base font-bold text-white">
                                    {editingAccount ? 'Edit Platform Payment Account' : 'Add Platform Payment Account'}
                                </h3>
                                <button
                                    onClick={() => setModalOpen(false)}
                                    className="text-slate-400 hover:text-white text-lg p-1"
                                >
                                    &times;
                                </button>
                            </div>

                            <form onSubmit={handleSubmit} className="space-y-4">
                                <div>
                                    <label className="block text-xs font-semibold text-slate-300 mb-1">
                                        Provider / Bank Name <span className="text-rose-400">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        required
                                        value={form.data.provider_name}
                                        onChange={(e) => form.setData('provider_name', e.target.value)}
                                        placeholder="e.g. Commercial Bank of Ethiopia, Chase, Swift Wire, USDT"
                                        className="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-white focus:border-rose-500 focus:outline-none"
                                    />
                                    {form.errors.provider_name && (
                                        <p className="text-[11px] text-rose-400 mt-1">{form.errors.provider_name}</p>
                                    )}
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold text-slate-300 mb-1">
                                        Account Holder / Recipient Name <span className="text-rose-400">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        required
                                        value={form.data.account_name}
                                        onChange={(e) => form.setData('account_name', e.target.value)}
                                        placeholder="e.g. Nobingo Platform HQ Ltd."
                                        className="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-white focus:border-rose-500 focus:outline-none"
                                    />
                                    {form.errors.account_name && (
                                        <p className="text-[11px] text-rose-400 mt-1">{form.errors.account_name}</p>
                                    )}
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold text-slate-300 mb-1">
                                        Account / Phone / IBAN / Wallet Address <span className="text-rose-400">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        required
                                        value={form.data.account_number}
                                        onChange={(e) => form.setData('account_number', e.target.value)}
                                        placeholder="e.g. 100098765432 or 0x71C... / +251..."
                                        className="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-white font-mono focus:border-rose-500 focus:outline-none"
                                    />
                                    {form.errors.account_number && (
                                        <p className="text-[11px] text-rose-400 mt-1">{form.errors.account_number}</p>
                                    )}
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold text-slate-300 mb-1">
                                        Instructions / Transfer Memo for Companies
                                    </label>
                                    <textarea
                                        rows={3}
                                        value={form.data.instructions}
                                        onChange={(e) => form.setData('instructions', e.target.value)}
                                        placeholder="e.g. Specify your company slug in transfer remarks. Credits will be approved within 15 minutes of receipt upload."
                                        className="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-white focus:border-rose-500 focus:outline-none"
                                    />
                                </div>

                                <div className="flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        id="is_active"
                                        checked={form.data.is_active}
                                        onChange={(e) => form.setData('is_active', e.target.checked)}
                                        className="rounded bg-slate-950 border-slate-800 text-rose-600 focus:ring-0"
                                    />
                                    <label htmlFor="is_active" className="text-xs font-semibold text-slate-300">
                                        Active & Visible to Companies
                                    </label>
                                </div>

                                <div className="pt-4 border-t border-slate-800 flex justify-end gap-3">
                                    <button
                                        type="button"
                                        onClick={() => setModalOpen(false)}
                                        className="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={form.processing}
                                        className="px-5 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs disabled:opacity-50 transition shadow-sm"
                                    >
                                        {form.processing ? 'Saving...' : editingAccount ? 'Update Account' : 'Create Account'}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}
            </div>
        </PlatformOwnerLayout>
    );
}
