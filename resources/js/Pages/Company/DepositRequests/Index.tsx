import CompanyAdminLayout from '@/Layouts/CompanyAdminLayout';
import { PageProps } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import React, { useState } from 'react';

interface PlayerItem {
    id: number;
    name: string;
    email: string;
    balance: number;
    formatted_balance: string;
}

interface DepositRequestItem {
    id: number;
    amount: number;
    formatted_amount: string;
    status: string;
    reference_number: string | null;
    receipt_url: string | null;
    notes: string | null;
    reviewer_notes: string | null;
    user: PlayerItem | null;
    payment_account: {
        id: number;
        provider_name: string;
        account_name?: string;
        account_number?: string;
    } | null;
    reviewer?: {
        id: number;
        name: string;
    } | null;
    created_at: string;
    reviewed_at?: string | null;
}

interface Props extends PageProps {
    company: {
        id: number;
        name: string;
        slug: string;
    };
    pending_requests: DepositRequestItem[];
    history_requests: {
        data: DepositRequestItem[];
        current_page: number;
        last_page: number;
        total: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    company_players: PlayerItem[];
}

export default function CompanyDepositRequestsIndex({
    company,
    pending_requests,
    history_requests,
    company_players,
}: Props) {
    const [previewReceipt, setPreviewReceipt] = useState<string | null>(null);
    const [actionRequest, setActionRequest] = useState<DepositRequestItem | null>(null);
    const [actionType, setActionType] = useState<'approve' | 'reject' | null>(null);
    const [actionNotes, setActionNotes] = useState('');
    const [actionProcessing, setActionProcessing] = useState(false);

    // Manual Direct Top-up Modal State
    const [manualModalOpen, setManualModalOpen] = useState(false);
    const manualForm = useForm({
        user_id: company_players[0]?.id || '',
        amount: '25',
        notes: '',
    });

    const openActionModal = (req: DepositRequestItem, type: 'approve' | 'reject') => {
        setActionRequest(req);
        setActionType(type);
        setActionNotes('');
    };

    const handleActionSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!actionRequest || !actionType) return;

        setActionProcessing(true);
        const endpoint = `/c/${company.slug}/admin/deposit-requests/${actionRequest.id}/${actionType}`;

        router.post(
            endpoint,
            actionType === 'approve' ? { notes: actionNotes } : { reason: actionNotes },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setActionRequest(null);
                    setActionType(null);
                    setActionNotes('');
                },
                onFinish: () => setActionProcessing(false),
            }
        );
    };

    const handleManualDeposit = (e: React.FormEvent) => {
        e.preventDefault();
        manualForm.post(`/c/${company.slug}/admin/players/manual-deposit`, {
            preserveScroll: true,
            onSuccess: () => {
                setManualModalOpen(false);
                manualForm.reset();
            },
        });
    };

    return (
        <CompanyAdminLayout
            header={
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-black text-white">Player Deposit Receipts & Approvals</h1>
                        <p className="text-xs text-neutral-400 mt-0.5">
                            Verify player bank/cash receipts and credit player wallets, or directly deposit to player accounts.
                        </p>
                    </div>
                    <button
                        onClick={() => setManualModalOpen(true)}
                        className="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs flex items-center gap-1.5 transition shadow-sm"
                    >
                        <span>💵</span> Direct Manual Top-up
                    </button>
                </div>
            }
        >
            <Head title="Player Deposit Requests" />

            <div className="space-y-6">
                {/* Pending Requests Section */}
                <div className="p-6 rounded-3xl bg-neutral-900 border border-neutral-800 shadow-xl space-y-4">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <span className="h-2.5 w-2.5 rounded-full bg-amber-400 animate-pulse" />
                            <h2 className="text-sm font-bold text-white">
                                Pending Deposit Requests ({pending_requests.length})
                            </h2>
                        </div>
                        <span className="text-xs text-neutral-400">
                            Players are waiting for receipt verification before funds appear in their wallets.
                        </span>
                    </div>

                    <div className="overflow-x-auto rounded-2xl border border-neutral-800">
                        <table className="w-full text-left text-xs text-neutral-300">
                            <thead className="bg-neutral-950 text-neutral-400 uppercase font-semibold border-b border-neutral-800">
                                <tr>
                                    <th className="px-4 py-3">Player</th>
                                    <th className="px-4 py-3">Amount</th>
                                    <th className="px-4 py-3">Payment Account / Ref</th>
                                    <th className="px-4 py-3">Receipt Proof</th>
                                    <th className="px-4 py-3">Submitted At</th>
                                    <th className="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-neutral-800/80 bg-neutral-950/40">
                                {pending_requests.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="px-4 py-8 text-center text-neutral-500">
                                            No pending deposit requests. All player receipts are up to date!
                                        </td>
                                    </tr>
                                ) : (
                                    pending_requests.map((req) => (
                                        <tr key={req.id} className="hover:bg-neutral-800/40">
                                            <td className="px-4 py-3">
                                                <div className="font-bold text-white">{req.user?.name || 'Unknown'}</div>
                                                <div className="text-[10px] text-neutral-500 font-mono">{req.user?.email}</div>
                                                <div className="text-[10px] text-emerald-400 font-mono mt-0.5">
                                                    Current Balance: {req.user?.formatted_balance}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 font-mono font-bold text-emerald-400 text-sm">
                                                {req.formatted_amount}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="font-semibold text-white">
                                                    {req.payment_account?.provider_name || 'Standard Account'}
                                                </div>
                                                <div className="text-[10px] text-neutral-500 font-mono">
                                                    Ref: {req.reference_number || '-'}
                                                </div>
                                                {req.notes && (
                                                    <div className="text-[10px] text-neutral-400 italic mt-0.5 max-w-xs truncate">
                                                        "{req.notes}"
                                                    </div>
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                {req.receipt_url ? (
                                                    <button
                                                        type="button"
                                                        onClick={() => setPreviewReceipt(req.receipt_url)}
                                                        className="px-2.5 py-1 rounded-lg bg-neutral-800 hover:bg-neutral-700 text-indigo-400 hover:text-indigo-300 font-semibold text-[11px] inline-flex items-center gap-1 border border-neutral-700"
                                                    >
                                                        <span>👁️</span> Inspect Receipt
                                                    </button>
                                                ) : (
                                                    <span className="text-neutral-500 italic">No receipt</span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-neutral-400">{req.created_at}</td>
                                            <td className="px-4 py-3 text-right">
                                                <div className="flex items-center justify-end gap-2">
                                                    <button
                                                        type="button"
                                                        onClick={() => openActionModal(req, 'approve')}
                                                        className="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs transition shadow-sm"
                                                    >
                                                        Approve
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() => openActionModal(req, 'reject')}
                                                        className="px-3 py-1.5 rounded-lg bg-rose-950/40 hover:bg-rose-900/60 text-rose-400 border border-rose-800/40 font-semibold text-xs transition"
                                                    >
                                                        Reject
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* History Section */}
                <div className="p-6 rounded-3xl bg-neutral-900 border border-neutral-800 shadow-xl space-y-4">
                    <h2 className="text-sm font-bold text-white flex items-center gap-2">
                        <span>📜</span> Deposit Verification History
                    </h2>

                    <div className="overflow-x-auto rounded-2xl border border-neutral-800">
                        <table className="w-full text-left text-xs text-neutral-300">
                            <thead className="bg-neutral-950 text-neutral-400 uppercase font-semibold border-b border-neutral-800">
                                <tr>
                                    <th className="px-4 py-3">Player</th>
                                    <th className="px-4 py-3">Amount</th>
                                    <th className="px-4 py-3">Ref / Account</th>
                                    <th className="px-4 py-3">Receipt</th>
                                    <th className="px-4 py-3">Status</th>
                                    <th className="px-4 py-3">Reviewed By / Notes</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-neutral-800/80 bg-neutral-950/40">
                                {history_requests.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="px-4 py-8 text-center text-neutral-500">
                                            No historical deposit requests found.
                                        </td>
                                    </tr>
                                ) : (
                                    history_requests.data.map((req) => (
                                        <tr key={req.id} className="hover:bg-neutral-800/40">
                                            <td className="px-4 py-3">
                                                <div className="font-bold text-white">{req.user?.name || 'Player'}</div>
                                                <div className="text-[10px] text-neutral-500 font-mono">{req.user?.email}</div>
                                            </td>
                                            <td className="px-4 py-3 font-mono font-bold text-emerald-400">
                                                {req.formatted_amount}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="text-white font-medium">{req.payment_account?.provider_name || '-'}</div>
                                                <div className="text-[10px] text-neutral-500 font-mono">{req.reference_number || '-'}</div>
                                            </td>
                                            <td className="px-4 py-3">
                                                {req.receipt_url ? (
                                                    <a
                                                        href={req.receipt_url}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        className="text-indigo-400 hover:text-indigo-300 font-semibold"
                                                    >
                                                        View Proof
                                                    </a>
                                                ) : (
                                                    '-'
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                <span className={`px-2 py-0.5 rounded text-[10px] font-bold ${
                                                    req.status === 'approved'
                                                        ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30'
                                                        : 'bg-rose-500/20 text-rose-400 border border-rose-500/30'
                                                }`}>
                                                    {req.status}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 text-neutral-400 max-w-xs">
                                                <div>{req.reviewer?.name ? `Reviewed by ${req.reviewer.name}` : '-'}</div>
                                                {req.reviewer_notes && (
                                                    <div className="text-[10px] text-neutral-300 italic mt-0.5">
                                                        "{req.reviewer_notes}"
                                                    </div>
                                                )}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Approve / Reject Confirmation Modal */}
                {actionRequest && actionType && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
                        <div className="bg-neutral-900 border border-neutral-800 rounded-3xl max-w-md w-full p-6 shadow-2xl space-y-5">
                            <div className="flex justify-between items-center pb-3 border-b border-neutral-800">
                                <h3 className="text-base font-bold text-white">
                                    {actionType === 'approve' ? 'Approve Deposit' : 'Reject Deposit'}
                                </h3>
                                <button
                                    onClick={() => setActionRequest(null)}
                                    className="text-neutral-400 hover:text-white text-lg p-1"
                                >
                                    &times;
                                </button>
                            </div>

                            <div className="p-3.5 rounded-2xl bg-neutral-950 border border-neutral-800 space-y-1">
                                <div className="text-xs text-neutral-400">Player: <span className="font-bold text-white">{actionRequest.user?.name}</span></div>
                                <div className="text-xs text-neutral-400">Amount: <span className="font-bold text-emerald-400 font-mono">{actionRequest.formatted_amount}</span></div>
                                {actionRequest.receipt_url && (
                                    <div className="pt-1">
                                        <a
                                            href={actionRequest.receipt_url}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="text-xs text-indigo-400 hover:underline font-semibold"
                                        >
                                            View Receipt Proof in New Tab &rarr;
                                        </a>
                                    </div>
                                )}
                            </div>

                            <form onSubmit={handleActionSubmit} className="space-y-4">
                                <div>
                                    <label className="block text-xs font-semibold text-neutral-300 mb-1">
                                        {actionType === 'approve' ? 'Approval Notes (Optional)' : 'Rejection Reason (Required)'}
                                    </label>
                                    <textarea
                                        rows={3}
                                        required={actionType === 'reject'}
                                        value={actionNotes}
                                        onChange={(e) => setActionNotes(e.target.value)}
                                        placeholder={actionType === 'approve' ? 'e.g. Verified with CBE transaction' : 'e.g. Receipt unreadable / transaction not found on bank statement'}
                                        className="w-full bg-neutral-950 border border-neutral-800 rounded-xl px-3.5 py-2.5 text-xs text-white focus:border-indigo-500 focus:outline-none"
                                    />
                                </div>

                                <div className="flex justify-end gap-3 pt-2 border-t border-neutral-800">
                                    <button
                                        type="button"
                                        onClick={() => setActionRequest(null)}
                                        className="px-4 py-2 rounded-xl bg-neutral-800 hover:bg-neutral-700 text-neutral-300 text-xs font-semibold"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={actionProcessing || (actionType === 'reject' && !actionNotes.trim())}
                                        className={`px-5 py-2 rounded-xl text-white font-bold text-xs disabled:opacity-50 transition shadow-sm ${
                                            actionType === 'approve'
                                                ? 'bg-emerald-600 hover:bg-emerald-500'
                                                : 'bg-rose-600 hover:bg-rose-500'
                                        }`}
                                    >
                                        {actionProcessing ? 'Processing...' : actionType === 'approve' ? 'Confirm Approval & Credit' : 'Confirm Rejection'}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}

                {/* Direct Manual Deposit Modal */}
                {manualModalOpen && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
                        <div className="bg-neutral-900 border border-neutral-800 rounded-3xl max-w-lg w-full p-6 shadow-2xl space-y-6">
                            <div className="flex justify-between items-center pb-4 border-b border-neutral-800">
                                <div>
                                    <h3 className="text-base font-bold text-white">Direct Admin Manual Top-up</h3>
                                    <p className="text-xs text-neutral-400 mt-0.5">
                                        Instantly credit a player's account (cash received over the counter)
                                    </p>
                                </div>
                                <button
                                    onClick={() => setManualModalOpen(false)}
                                    className="text-neutral-400 hover:text-white text-lg p-1"
                                >
                                    &times;
                                </button>
                            </div>

                            <form onSubmit={handleManualDeposit} className="space-y-4">
                                <div>
                                    <label className="block text-xs font-semibold text-neutral-300 mb-1">
                                        Select Player <span className="text-rose-400">*</span>
                                    </label>
                                    <select
                                        required
                                        value={manualForm.data.user_id}
                                        onChange={(e) => manualForm.setData('user_id', Number(e.target.value))}
                                        className="w-full bg-neutral-950 border border-neutral-800 rounded-xl px-3.5 py-2.5 text-xs text-white focus:border-indigo-500 focus:outline-none"
                                    >
                                        <option value="">-- Choose Registered Player --</option>
                                        {company_players.map((p) => (
                                            <option key={p.id} value={p.id}>
                                                {p.name} ({p.email}) - Current Balance: {p.formatted_balance}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold text-neutral-300 mb-1">
                                        Top-up Amount ($ USD) <span className="text-rose-400">*</span>
                                    </label>
                                    <div className="relative">
                                        <span className="absolute left-3.5 top-2.5 text-neutral-500 font-bold">$</span>
                                        <input
                                            type="number"
                                            min="1"
                                            max="50000"
                                            required
                                            value={manualForm.data.amount}
                                            onChange={(e) => manualForm.setData('amount', e.target.value)}
                                            className="w-full bg-neutral-950 border border-neutral-800 rounded-xl pl-8 pr-4 py-2.5 text-xs text-white font-mono focus:border-indigo-500 focus:outline-none"
                                        />
                                    </div>
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold text-neutral-300 mb-1">
                                        Admin Notes / Reason <span className="text-neutral-500">(Optional)</span>
                                    </label>
                                    <input
                                        type="text"
                                        value={manualForm.data.notes}
                                        onChange={(e) => manualForm.setData('notes', e.target.value)}
                                        placeholder="e.g. Cash received at register / promotion"
                                        className="w-full bg-neutral-950 border border-neutral-800 rounded-xl px-3.5 py-2.5 text-xs text-white focus:border-indigo-500 focus:outline-none"
                                    />
                                </div>

                                <div className="pt-4 border-t border-neutral-800 flex justify-end gap-3">
                                    <button
                                        type="button"
                                        onClick={() => setManualModalOpen(false)}
                                        className="px-4 py-2 rounded-xl bg-neutral-800 hover:bg-neutral-700 text-neutral-300 text-xs font-semibold"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={manualForm.processing || !manualForm.data.user_id}
                                        className="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs disabled:opacity-50 transition shadow-sm"
                                    >
                                        {manualForm.processing ? 'Crediting...' : 'Confirm Manual Top-up'}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}

                {/* Receipt Image Lightbox Preview */}
                {previewReceipt && (
                    <div
                        className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/90 backdrop-blur-md"
                        onClick={() => setPreviewReceipt(null)}
                    >
                        <div className="relative max-w-3xl max-h-[90vh] p-2 bg-neutral-900 border border-neutral-800 rounded-2xl shadow-2xl">
                            <button
                                onClick={() => setPreviewReceipt(null)}
                                className="absolute top-4 right-4 bg-black/70 hover:bg-black text-white p-2 rounded-full text-xs font-bold"
                            >
                                Close &times;
                            </button>
                            <img
                                src={previewReceipt}
                                alt="Payment Proof Receipt"
                                className="max-h-[80vh] w-auto rounded-xl object-contain mx-auto"
                            />
                        </div>
                    </div>
                )}
            </div>
        </CompanyAdminLayout>
    );
}
