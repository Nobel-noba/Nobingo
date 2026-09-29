import CompanyAdminLayout from '@/Layouts/CompanyAdminLayout';
import { PageProps } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import React, { useState } from 'react';

interface PlatformAccount {
    id: number;
    provider_name: string;
    account_name: string;
    account_number: string;
    instructions: string | null;
}

interface CreditRequestItem {
    id: number;
    amount: number;
    formatted_amount: string;
    status: string;
    reference_number: string | null;
    receipt_url: string | null;
    notes: string | null;
    reviewer_notes: string | null;
    payment_account: {
        id: number;
        provider_name: string;
        account_name: string;
        account_number: string;
    } | null;
    reviewer: {
        id: number;
        name: string;
    } | null;
    created_at: string;
    reviewed_at: string | null;
}

interface Props extends PageProps {
    company: {
        id: number;
        name: string;
        slug: string;
        credit_balance: number;
        formatted_credit_balance: string;
    };
    platform_accounts: PlatformAccount[];
    credit_requests: {
        data: CreditRequestItem[];
        current_page: number;
        last_page: number;
        total: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
}

export default function CompanyCreditsIndex({ company, platform_accounts, credit_requests }: Props) {
    const [modalOpen, setModalOpen] = useState(false);

    const form = useForm<{
        payment_account_id: number | '';
        amount: string;
        reference_number: string;
        receipt: File | null;
        notes: string;
    }>({
        payment_account_id: platform_accounts[0]?.id || '',
        amount: '100',
        reference_number: '',
        receipt: null,
        notes: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(`/c/${company.slug}/admin/credits/buy`, {
            preserveScroll: true,
            onSuccess: () => {
                setModalOpen(false);
                form.reset('receipt', 'reference_number', 'notes');
            },
        });
    };

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'approved':
                return (
                    <span className="px-2 py-0.5 rounded text-[11px] font-bold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                        Approved & Credited
                    </span>
                );
            case 'rejected':
                return (
                    <span className="px-2 py-0.5 rounded text-[11px] font-bold bg-rose-500/20 text-rose-400 border border-rose-500/30">
                        Rejected
                    </span>
                );
            default:
                return (
                    <span className="px-2 py-0.5 rounded text-[11px] font-bold bg-amber-500/20 text-amber-400 border border-amber-500/30">
                        Pending Verification
                    </span>
                );
        }
    };

    return (
        <CompanyAdminLayout
            header={
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-black text-white">Platform Credits & Billing</h1>
                        <p className="text-xs text-neutral-400 mt-0.5">
                            Buy platform credit to activate and host live bingo games.
                        </p>
                    </div>
                    <button
                        onClick={() => setModalOpen(true)}
                        className="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs flex items-center gap-1.5 transition shadow-sm"
                    >
                        <span>➕</span> Buy Platform Credits
                    </button>
                </div>
            }
        >
            <Head title="Platform Credits" />

            <div className="space-y-6">
                {/* Credit Overview Cards */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div className="p-6 rounded-3xl bg-neutral-900 border border-neutral-800 shadow-xl relative overflow-hidden">
                        <div className="text-xs uppercase font-bold text-neutral-400 tracking-wider">
                            Active Credit Balance
                        </div>
                        <div className="text-4xl font-black text-emerald-400 font-mono mt-2">
                            {company.formatted_credit_balance}
                        </div>
                        <div className="text-xs text-neutral-500 mt-2">
                            {company.credit_balance > 0 ? (
                                <span className="text-emerald-400 font-semibold">● Ready to host live games</span>
                            ) : (
                                <span className="text-rose-400 font-semibold">● Insufficient credit - buy credits to start games</span>
                            )}
                        </div>
                    </div>

                    <div className="p-6 rounded-3xl bg-neutral-900 border border-neutral-800 shadow-xl col-span-2">
                        <div className="text-xs uppercase font-bold text-indigo-400 tracking-wider">
                            How Revenue Sharing Works
                        </div>
                        <p className="text-xs text-neutral-300 mt-2 leading-relaxed">
                            When players enter a game, 100% of the pot is collected by your venue. When the game ends, the winner takes the configured win share (default <span className="font-bold text-white">75%</span>) and your company keeps the remaining gross cut (<span className="font-bold text-white">25%</span>). The platform fee (<span className="font-bold text-white">20% of your gross cut</span>) is automatically deducted from your credit balance.
                        </p>
                    </div>
                </div>

                {/* Platform Payment Accounts Notice */}
                <div className="p-6 rounded-3xl bg-neutral-900 border border-neutral-800 shadow-xl space-y-4">
                    <div>
                        <h2 className="text-sm font-bold text-white flex items-center gap-2">
                            <span>🏦</span> Official Platform Owner Payment Accounts
                        </h2>
                        <p className="text-xs text-neutral-400 mt-1">
                            Transfer your credit purchase payment to one of the accounts below, then upload the receipt proof.
                        </p>
                    </div>

                    {platform_accounts.length === 0 ? (
                        <div className="p-4 rounded-xl bg-neutral-950 border border-neutral-800 text-xs text-neutral-500">
                            No payment accounts have been published by the platform owner yet. Please reach out to platform support.
                        </div>
                    ) : (
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            {platform_accounts.map((acc) => (
                                <div key={acc.id} className="p-4 rounded-2xl bg-neutral-950 border border-neutral-800 space-y-1">
                                    <div className="text-xs font-black uppercase text-indigo-400">{acc.provider_name}</div>
                                    <div className="text-sm font-bold text-white">{acc.account_name}</div>
                                    <div className="text-xs font-mono font-bold text-emerald-400">{acc.account_number}</div>
                                    {acc.instructions && (
                                        <div className="text-[11px] text-neutral-400 mt-2 border-t border-neutral-800 pt-1.5">
                                            {acc.instructions}
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {/* Credit Requests History Table */}
                <div className="p-6 rounded-3xl bg-neutral-900 border border-neutral-800 shadow-xl space-y-4">
                    <h2 className="text-sm font-bold text-white flex items-center gap-2">
                        <span>📜</span> Credit Purchase History & Receipts
                    </h2>

                    <div className="overflow-x-auto rounded-2xl border border-neutral-800">
                        <table className="w-full text-left text-xs text-neutral-300">
                            <thead className="bg-neutral-950 text-neutral-400 uppercase font-semibold border-b border-neutral-800">
                                <tr>
                                    <th className="px-4 py-3">ID / Date</th>
                                    <th className="px-4 py-3">Credit Amount</th>
                                    <th className="px-4 py-3">Payment Account / Ref</th>
                                    <th className="px-4 py-3">Receipt</th>
                                    <th className="px-4 py-3">Status</th>
                                    <th className="px-4 py-3">Platform Review</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-neutral-800/80 bg-neutral-950/40">
                                {credit_requests.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="px-4 py-8 text-center text-neutral-500">
                                            No credit purchase requests found. Click "Buy Platform Credits" to add credits.
                                        </td>
                                    </tr>
                                ) : (
                                    credit_requests.data.map((req) => (
                                        <tr key={req.id} className="hover:bg-neutral-800/40">
                                            <td className="px-4 py-3">
                                                <div className="font-mono text-white font-bold">#{req.id}</div>
                                                <div className="text-[10px] text-neutral-500">{req.created_at}</div>
                                            </td>
                                            <td className="px-4 py-3 font-mono font-bold text-emerald-400 text-sm">
                                                {req.formatted_amount}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="font-semibold text-white">
                                                    {req.payment_account?.provider_name || 'Direct Transfer'}
                                                </div>
                                                <div className="text-[10px] text-neutral-500 font-mono">
                                                    {req.reference_number || '-'}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                {req.receipt_url ? (
                                                    <a
                                                        href={req.receipt_url}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        className="px-2.5 py-1 rounded-lg bg-neutral-800 hover:bg-neutral-700 text-indigo-400 hover:text-indigo-300 font-semibold text-[11px] inline-flex items-center gap-1 border border-neutral-700"
                                                    >
                                                        <span>👁️</span> View Receipt
                                                    </a>
                                                ) : (
                                                    <span className="text-neutral-500 italic">No receipt</span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3">{getStatusBadge(req.status)}</td>
                                            <td className="px-4 py-3 text-neutral-400 max-w-xs">
                                                {req.reviewer_notes ? (
                                                    <span className="text-neutral-300">{req.reviewer_notes}</span>
                                                ) : (
                                                    <span className="text-neutral-500 italic">
                                                        {req.status === 'pending' ? 'Awaiting verification' : '-'}
                                                    </span>
                                                )}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Buy Credits Modal */}
                {modalOpen && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
                        <div className="bg-neutral-900 border border-neutral-800 rounded-3xl max-w-lg w-full p-6 shadow-2xl space-y-6">
                            <div className="flex justify-between items-center pb-4 border-b border-neutral-800">
                                <div>
                                    <h3 className="text-base font-bold text-white">Buy Platform Credits</h3>
                                    <p className="text-xs text-neutral-400 mt-0.5">
                                        Upload payment proof for platform owner review
                                    </p>
                                </div>
                                <button
                                    onClick={() => setModalOpen(false)}
                                    className="text-neutral-400 hover:text-white text-lg p-1"
                                >
                                    &times;
                                </button>
                            </div>

                            <form onSubmit={handleSubmit} className="space-y-4">
                                <div>
                                    <label className="block text-xs font-semibold text-neutral-300 mb-1">
                                        Platform Account Paid Into
                                    </label>
                                    <select
                                        value={form.data.payment_account_id}
                                        onChange={(e) => form.setData('payment_account_id', Number(e.target.value) || '')}
                                        className="w-full bg-neutral-950 border border-neutral-800 rounded-xl px-3.5 py-2.5 text-xs text-white focus:border-indigo-500 focus:outline-none"
                                    >
                                        <option value="">-- Choose Account --</option>
                                        {platform_accounts.map((acc) => (
                                            <option key={acc.id} value={acc.id}>
                                                {acc.provider_name} - {acc.account_name} ({acc.account_number})
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold text-neutral-300 mb-1">
                                        Credit Amount ($ USD) <span className="text-rose-400">*</span>
                                    </label>
                                    <div className="relative">
                                        <span className="absolute left-3.5 top-2.5 text-neutral-500 font-bold">$</span>
                                        <input
                                            type="number"
                                            min="5"
                                            max="50000"
                                            required
                                            value={form.data.amount}
                                            onChange={(e) => form.setData('amount', e.target.value)}
                                            className="w-full bg-neutral-950 border border-neutral-800 rounded-xl pl-8 pr-4 py-2.5 text-xs text-white focus:border-indigo-500 focus:outline-none"
                                        />
                                    </div>
                                    {form.errors.amount && (
                                        <p className="text-[11px] text-rose-400 mt-1">{form.errors.amount}</p>
                                    )}
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold text-neutral-300 mb-1">
                                        Transaction / Reference # <span className="text-neutral-500">(Optional)</span>
                                    </label>
                                    <input
                                        type="text"
                                        value={form.data.reference_number}
                                        onChange={(e) => form.setData('reference_number', e.target.value)}
                                        placeholder="e.g. TXN-12345678"
                                        className="w-full bg-neutral-950 border border-neutral-800 rounded-xl px-3.5 py-2.5 text-xs text-white focus:border-indigo-500 focus:outline-none"
                                    />
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold text-neutral-300 mb-1">
                                        Payment Proof / Receipt <span className="text-rose-400">*</span>
                                    </label>
                                    <input
                                        type="file"
                                        required
                                        accept="image/*,.pdf"
                                        onChange={(e) => form.setData('receipt', e.target.files ? e.target.files[0] : null)}
                                        className="w-full bg-neutral-950 border border-neutral-800 rounded-xl px-3 py-2 text-xs text-neutral-300 file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-600 file:text-white hover:file:bg-indigo-500"
                                    />
                                    {form.errors.receipt && (
                                        <p className="text-[11px] text-rose-400 mt-1">{form.errors.receipt}</p>
                                    )}
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold text-neutral-300 mb-1">
                                        Notes <span className="text-neutral-500">(Optional)</span>
                                    </label>
                                    <input
                                        type="text"
                                        value={form.data.notes}
                                        onChange={(e) => form.setData('notes', e.target.value)}
                                        placeholder="Additional comments for the platform owner"
                                        className="w-full bg-neutral-950 border border-neutral-800 rounded-xl px-3.5 py-2.5 text-xs text-white focus:border-indigo-500 focus:outline-none"
                                    />
                                </div>

                                <div className="pt-4 border-t border-neutral-800 flex justify-end gap-3">
                                    <button
                                        type="button"
                                        onClick={() => setModalOpen(false)}
                                        className="px-4 py-2 rounded-xl bg-neutral-800 hover:bg-neutral-700 text-neutral-300 text-xs font-semibold"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={form.processing || !form.data.receipt}
                                        className="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs disabled:opacity-50 transition shadow-sm"
                                    >
                                        {form.processing ? 'Submitting...' : 'Submit Credit Request'}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}
            </div>
        </CompanyAdminLayout>
    );
}
