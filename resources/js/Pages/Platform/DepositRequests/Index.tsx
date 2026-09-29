import PlatformOwnerLayout from '@/Layouts/PlatformOwnerLayout';
import { PageProps } from '@/types';
import { Head, router } from '@inertiajs/react';
import React, { useState } from 'react';

interface CompanyItem {
    id: number;
    name: string;
    slug: string;
    credit_balance?: number;
    formatted_credit_balance?: string;
}

interface PaymentAccountItem {
    id: number;
    provider_name: string;
    account_name?: string;
    account_number?: string;
}

interface ReviewerItem {
    id: number;
    name: string;
}

interface DepositRequestItem {
    id: number;
    amount: number;
    formatted_amount: string;
    status: string;
    reference_number: string | null;
    receipt_url: string | null;
    notes: string | null;
    reviewer_notes?: string | null;
    company: CompanyItem | null;
    payment_account: PaymentAccountItem | null;
    reviewer?: ReviewerItem | null;
    created_at: string;
    reviewed_at?: string | null;
}

interface Props extends PageProps {
    pending_requests: DepositRequestItem[];
    history_requests: {
        data: DepositRequestItem[];
        current_page: number;
        last_page: number;
        total: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
}

export default function PlatformDepositRequestsIndex({
    pending_requests,
    history_requests,
}: Props) {
    const [previewReceipt, setPreviewReceipt] = useState<string | null>(null);
    const [actionRequest, setActionRequest] = useState<DepositRequestItem | null>(null);
    const [actionType, setActionType] = useState<'approve' | 'reject' | null>(null);
    const [actionNotes, setActionNotes] = useState('');
    const [actionProcessing, setActionProcessing] = useState(false);

    const openActionModal = (req: DepositRequestItem, type: 'approve' | 'reject') => {
        setActionRequest(req);
        setActionType(type);
        setActionNotes('');
    };

    const handleActionSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!actionRequest || !actionType) return;

        setActionProcessing(true);
        const endpoint = `/platform/deposit-requests/${actionRequest.id}/${actionType}`;

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

    return (
        <PlatformOwnerLayout
            header={
                <div>
                    <h1 className="text-xl font-bold text-white tracking-tight">Company Platform Credit Approvals</h1>
                    <p className="text-xs text-slate-400 mt-0.5">
                        Verify incoming bank transfers / receipts from tenant companies and credit their game activation balances.
                    </p>
                </div>
            }
        >
            <Head title="Credit Approvals - Platform Owner" />

            <div className="space-y-6">
                {/* Pending Requests Section */}
                <div className="p-6 rounded-2xl bg-slate-900 border border-slate-800 shadow-sm space-y-4">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <span className="h-2.5 w-2.5 rounded-full bg-amber-400 animate-pulse" />
                            <h2 className="text-sm font-bold text-white uppercase tracking-wider">
                                Pending Credit Purchase Requests ({pending_requests.length})
                            </h2>
                        </div>
                        <span className="text-xs text-slate-400">
                            Companies need positive credit to activate games and start multiplayer rounds.
                        </span>
                    </div>

                    <div className="overflow-x-auto rounded-xl border border-slate-800">
                        <table className="w-full text-left text-xs text-slate-300">
                            <thead className="bg-slate-950 text-slate-400 uppercase font-semibold border-b border-slate-800">
                                <tr>
                                    <th className="px-4 py-3">Company</th>
                                    <th className="px-4 py-3">Requested Credit</th>
                                    <th className="px-4 py-3">Paid To Account / Ref</th>
                                    <th className="px-4 py-3">Proof Receipt</th>
                                    <th className="px-4 py-3">Submitted At</th>
                                    <th className="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-800/80 bg-slate-950/40">
                                {pending_requests.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="px-4 py-8 text-center text-slate-500">
                                            No pending credit purchase requests. All company orders are fulfilled!
                                        </td>
                                    </tr>
                                ) : (
                                    pending_requests.map((req) => (
                                        <tr key={req.id} className="hover:bg-slate-800/40 transition">
                                            <td className="px-4 py-3">
                                                <div className="font-bold text-white text-sm">
                                                    {req.company?.name || 'Unknown Company'}
                                                </div>
                                                <div className="text-[11px] font-mono text-slate-400">
                                                    slug: {req.company?.slug}
                                                </div>
                                                <div className="text-[11px] text-amber-400 font-mono mt-0.5">
                                                    Current Credit: {req.company?.formatted_credit_balance}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 font-mono font-bold text-emerald-400 text-sm">
                                                {req.formatted_amount}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="font-semibold text-white">
                                                    {req.payment_account?.provider_name || 'Direct Deposit'}
                                                </div>
                                                <div className="text-[10px] text-slate-400 font-mono">
                                                    Account: {req.payment_account?.account_number || '-'}
                                                </div>
                                                <div className="text-[10px] text-indigo-400 font-mono">
                                                    Ref: {req.reference_number || '-'}
                                                </div>
                                                {req.notes && (
                                                    <div className="text-[10px] text-slate-400 italic mt-0.5 max-w-xs truncate">
                                                        "{req.notes}"
                                                    </div>
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                {req.receipt_url ? (
                                                    <button
                                                        type="button"
                                                        onClick={() => setPreviewReceipt(req.receipt_url)}
                                                        className="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-indigo-400 hover:text-indigo-300 font-semibold text-[11px] inline-flex items-center gap-1 border border-slate-700"
                                                    >
                                                        <span>👁️</span> Inspect Receipt
                                                    </button>
                                                ) : (
                                                    <span className="text-slate-500 italic">No proof file</span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-slate-400">{req.created_at}</td>
                                            <td className="px-4 py-3 text-right">
                                                <div className="flex items-center justify-end gap-2">
                                                    <button
                                                        type="button"
                                                        onClick={() => openActionModal(req, 'approve')}
                                                        className="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs transition shadow-sm"
                                                    >
                                                        Approve & Credit
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
                <div className="p-6 rounded-2xl bg-slate-900 border border-slate-800 shadow-sm space-y-4">
                    <h2 className="text-sm font-bold text-white uppercase tracking-wider flex items-center gap-2">
                        <span>📜</span> Historical Credit Approvals & Rejections
                    </h2>

                    <div className="overflow-x-auto rounded-xl border border-slate-800">
                        <table className="w-full text-left text-xs text-slate-300">
                            <thead className="bg-slate-950 text-slate-400 uppercase font-semibold border-b border-slate-800">
                                <tr>
                                    <th className="px-4 py-3">Company</th>
                                    <th className="px-4 py-3">Amount</th>
                                    <th className="px-4 py-3">Account / Ref</th>
                                    <th className="px-4 py-3">Receipt</th>
                                    <th className="px-4 py-3">Status</th>
                                    <th className="px-4 py-3">Reviewed By / Notes</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-800/80 bg-slate-950/40">
                                {history_requests.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="px-4 py-8 text-center text-slate-500">
                                            No past credit transactions found.
                                        </td>
                                    </tr>
                                ) : (
                                    history_requests.data.map((req) => (
                                        <tr key={req.id} className="hover:bg-slate-800/40 transition">
                                            <td className="px-4 py-3 font-semibold text-white">
                                                {req.company?.name || 'Company'}
                                                <div className="text-[10px] text-slate-500 font-mono">{req.company?.slug}</div>
                                            </td>
                                            <td className="px-4 py-3 font-mono font-bold text-emerald-400">
                                                {req.formatted_amount}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="text-white font-medium">{req.payment_account?.provider_name || '-'}</div>
                                                <div className="text-[10px] text-slate-400 font-mono">{req.reference_number || '-'}</div>
                                            </td>
                                            <td className="px-4 py-3">
                                                {req.receipt_url ? (
                                                    <a
                                                        href={req.receipt_url}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        className="text-indigo-400 hover:text-indigo-300 font-semibold underline"
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
                                            <td className="px-4 py-3 text-slate-400 max-w-xs">
                                                <div>
                                                    {req.reviewer?.name ? `Reviewed by ${req.reviewer.name}` : '-'}
                                                    {req.reviewed_at && <span className="text-[10px] text-slate-500 ml-1">({req.reviewed_at})</span>}
                                                </div>
                                                {req.reviewer_notes && (
                                                    <div className="text-[10px] text-slate-300 italic mt-0.5">
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

                {/* Approve / Reject Modal */}
                {actionRequest && actionType && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
                        <div className="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-5">
                            <div className="flex justify-between items-center pb-3 border-b border-slate-800">
                                <h3 className="text-base font-bold text-white">
                                    {actionType === 'approve' ? 'Approve Credit Purchase' : 'Reject Credit Purchase'}
                                </h3>
                                <button
                                    onClick={() => setActionRequest(null)}
                                    className="text-slate-400 hover:text-white text-lg p-1"
                                >
                                    &times;
                                </button>
                            </div>

                            <div className="p-3.5 rounded-xl bg-slate-950 border border-slate-800 space-y-1">
                                <div className="text-xs text-slate-400">
                                    Company: <span className="font-bold text-white">{actionRequest.company?.name}</span>
                                </div>
                                <div className="text-xs text-slate-400">
                                    Credit Amount: <span className="font-bold text-emerald-400 font-mono">{actionRequest.formatted_amount}</span>
                                </div>
                                {actionRequest.receipt_url && (
                                    <div className="pt-1">
                                        <a
                                            href={actionRequest.receipt_url}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="text-xs text-indigo-400 hover:underline font-semibold"
                                        >
                                            View Transfer Receipt Proof &rarr;
                                        </a>
                                    </div>
                                )}
                            </div>

                            <form onSubmit={handleActionSubmit} className="space-y-4">
                                <div>
                                    <label className="block text-xs font-semibold text-slate-300 mb-1">
                                        {actionType === 'approve' ? 'Approval Notes (Optional)' : 'Rejection Reason (Required)'}
                                    </label>
                                    <textarea
                                        rows={3}
                                        required={actionType === 'reject'}
                                        value={actionNotes}
                                        onChange={(e) => setActionNotes(e.target.value)}
                                        placeholder={actionType === 'approve' ? 'e.g. Bank wire confirmed in platform bank account' : 'e.g. Funds not received in account / invalid transaction ID'}
                                        className="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-white focus:border-indigo-500 focus:outline-none"
                                    />
                                </div>

                                <div className="flex justify-end gap-3 pt-2 border-t border-slate-800">
                                    <button
                                        type="button"
                                        onClick={() => setActionRequest(null)}
                                        className="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold"
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
                                        {actionProcessing ? 'Processing...' : actionType === 'approve' ? 'Confirm & Credit Balance' : 'Confirm Rejection'}
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
                        <div className="relative max-w-3xl max-h-[90vh] p-2 bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl">
                            <button
                                onClick={() => setPreviewReceipt(null)}
                                className="absolute top-4 right-4 bg-black/70 hover:bg-black text-white p-2 rounded-full text-xs font-bold"
                            >
                                Close &times;
                            </button>
                            <img
                                src={previewReceipt}
                                alt="Platform Credit Receipt Proof"
                                className="max-h-[80vh] w-auto rounded-xl object-contain mx-auto"
                            />
                        </div>
                    </div>
                )}
            </div>
        </PlatformOwnerLayout>
    );
}
