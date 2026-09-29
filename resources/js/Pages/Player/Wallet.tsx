import PlayerLayout from '@/Layouts/PlayerLayout';
import { PageProps } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import React, { useState } from 'react';

interface TransactionItem {
    id: number;
    type: string;
    amount: number;
    formatted_amount: string;
    status: string;
    balance_before: number;
    formatted_balance_before: string;
    balance_after: number;
    formatted_balance_after: string;
    reference_code: string | null;
    description: string | null;
    is_credit: boolean;
    is_debit: boolean;
    created_at: string;
}

interface PaymentAccountItem {
    id: number;
    provider_name: string;
    account_name: string;
    account_number: string;
    instructions: string | null;
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
    payment_account: {
        id: number;
        provider_name: string;
        account_name: string;
        account_number: string;
    } | null;
    created_at: string;
    reviewed_at: string | null;
}

interface Props extends PageProps {
    company: {
        id: number;
        name: string;
        slug: string;
    };
    balance: number;
    formatted_balance: string;
    transactions: {
        data: TransactionItem[];
        current_page: number;
        last_page: number;
        total: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    company_payment_accounts?: PaymentAccountItem[];
    deposit_requests?: {
        data: DepositRequestItem[];
        current_page: number;
        last_page: number;
        total: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    statistics: {
        total_deposited: number;
        formatted_total_deposited: string;
        total_withdrawn: number;
        formatted_total_withdrawn: string;
        total_won: number;
        formatted_total_won: string;
        total_entry_fees: number;
        formatted_total_entry_fees: string;
    };
    errors: Record<string, string>;
    flash?: {
        success?: string;
        error?: string;
    };
}

export default function PlayerWallet({
    company,
    balance,
    formatted_balance,
    transactions,
    company_payment_accounts = [],
    deposit_requests,
    statistics,
    flash,
}: Props) {
    const [activeTab, setActiveTab] = useState<'history' | 'deposit' | 'requests' | 'withdraw'>('history');
    const [previewReceipt, setPreviewReceipt] = useState<string | null>(null);

    // Deposit Receipt Form
    const depositForm = useForm<{
        payment_account_id: number | '';
        amount: string;
        reference_number: string;
        receipt: File | null;
        notes: string;
    }>({
        payment_account_id: company_payment_accounts[0]?.id || '',
        amount: '25',
        reference_number: '',
        receipt: null,
        notes: '',
    });

    // Withdraw Form
    const withdrawForm = useForm({
        amount: Math.min(25, Math.floor(balance / 100)),
    });

    const handleDepositRequest = (e: React.FormEvent) => {
        e.preventDefault();
        depositForm.post(`/c/${company.slug}/wallet/deposit-request`, {
            preserveScroll: true,
            onSuccess: () => {
                depositForm.reset('receipt', 'reference_number', 'notes');
                setActiveTab('requests');
            },
        });
    };

    const handleWithdraw = (e: React.FormEvent) => {
        e.preventDefault();
        withdrawForm.post(`/c/${company.slug}/wallet/withdraw`, {
            preserveScroll: true,
            onSuccess: () => setActiveTab('history'),
        });
    };

    const getTypeColor = (type: string) => {
        switch (type) {
            case 'DEPOSIT':
                return 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30';
            case 'PRIZE':
                return 'bg-amber-500/20 text-amber-300 border-amber-500/30';
            case 'REFUND':
                return 'bg-purple-500/20 text-purple-300 border-purple-500/30';
            case 'ENTRY_FEE':
                return 'bg-blue-500/20 text-blue-300 border-blue-500/30';
            case 'WITHDRAWAL':
                return 'bg-rose-500/20 text-rose-300 border-rose-500/30';
            default:
                return 'bg-slate-700 text-slate-300 border-slate-600';
        }
    };

    const getRequestStatusBadge = (status: string) => {
        switch (status) {
            case 'approved':
                return <span className="px-2 py-0.5 rounded text-[11px] font-bold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">Approved</span>;
            case 'rejected':
                return <span className="px-2 py-0.5 rounded text-[11px] font-bold bg-rose-500/20 text-rose-400 border border-rose-500/30">Rejected</span>;
            default:
                return <span className="px-2 py-0.5 rounded text-[11px] font-bold bg-amber-500/20 text-amber-400 border border-amber-500/30">Pending Review</span>;
        }
    };

    return (
        <PlayerLayout>
            <Head title="Player Wallet & Treasury" />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
                {/* Header Banner */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-800 pb-5">
                    <div>
                        <h1 className="text-2xl font-black text-white tracking-tight flex items-center gap-2">
                            <span>💳</span> Personal Wallet
                        </h1>
                        <p className="text-xs text-slate-400 mt-1">
                            Deposit funds via payment receipts, review ledger transactions, and withdraw game winnings.
                        </p>
                    </div>
                </div>

                {/* Flash Messages */}
                {flash?.success && (
                    <div className="p-4 rounded-xl bg-emerald-950/70 border border-emerald-500/40 text-emerald-300 text-xs font-semibold flex items-center gap-2">
                        <span>✅</span> {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div className="p-4 rounded-xl bg-rose-950/70 border border-rose-500/40 text-rose-300 text-xs font-semibold flex items-center gap-2">
                        <span>❌</span> {flash.error}
                    </div>
                )}

                {/* Wallet Balance Hero Card */}
                <div className="bg-gradient-to-br from-slate-900 via-slate-900 to-indigo-950/50 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-2xl relative overflow-hidden">
                    <div className="flex flex-col md:flex-row md:items-center justify-between gap-6 relative z-10">
                        <div>
                            <span className="text-xs font-bold uppercase tracking-wider text-indigo-400">Available Balance</span>
                            <div className="text-4xl sm:text-5xl font-black text-white font-mono mt-1 tracking-tight">
                                {formatted_balance}
                            </div>
                            <p className="text-xs text-slate-400 mt-2">
                                Funds can be used immediately for game room buy-ins.
                            </p>
                        </div>

                        {/* Action Buttons */}
                        <div className="flex flex-wrap items-center gap-3">
                            <button
                                onClick={() => setActiveTab(activeTab === 'deposit' ? 'history' : 'deposit')}
                                className={`px-5 py-3 rounded-2xl text-xs font-bold transition flex items-center gap-2 shadow-lg ${
                                    activeTab === 'deposit'
                                        ? 'bg-emerald-500 text-slate-950 ring-2 ring-emerald-400'
                                        : 'bg-emerald-600 hover:bg-emerald-500 text-white'
                                }`}
                            >
                                <span>📥</span> Deposit Funds
                            </button>

                            <button
                                onClick={() => setActiveTab(activeTab === 'requests' ? 'history' : 'requests')}
                                className={`px-4 py-3 rounded-2xl text-xs font-bold transition flex items-center gap-2 border ${
                                    activeTab === 'requests'
                                        ? 'bg-amber-500 text-slate-950 border-amber-400'
                                        : 'bg-slate-800 hover:bg-slate-700 text-slate-200 border-slate-700'
                                }`}
                            >
                                <span>📋</span> My Receipts ({deposit_requests?.data?.length || 0})
                            </button>

                            <button
                                onClick={() => setActiveTab(activeTab === 'withdraw' ? 'history' : 'withdraw')}
                                disabled={balance < 100}
                                className={`px-4 py-3 rounded-2xl text-xs font-bold transition flex items-center gap-2 border ${
                                    balance < 100
                                        ? 'bg-slate-800 text-slate-500 border-slate-700 cursor-not-allowed'
                                        : activeTab === 'withdraw'
                                        ? 'bg-indigo-600 text-white border-indigo-500'
                                        : 'bg-slate-800 hover:bg-slate-700 text-slate-200 border-slate-700'
                                }`}
                            >
                                <span>📤</span> Withdraw
                            </button>
                        </div>
                    </div>

                    {/* Deposit Section (Receipt Upload) */}
                    {activeTab === 'deposit' && (
                        <div className="mt-8 pt-6 border-t border-slate-800 space-y-6 animate-fade-in">
                            <div>
                                <h3 className="text-base font-bold text-white flex items-center gap-2">
                                    <span>🏦</span> Step 1: Transfer Funds to Company Account
                                </h3>
                                <p className="text-xs text-slate-400 mt-1">
                                    Choose one of the official company payment accounts below, send your deposit, and keep your transfer receipt / transaction screenshot.
                                </p>
                            </div>

                            {/* Company Payment Accounts Cards */}
                            {company_payment_accounts.length === 0 ? (
                                <div className="p-4 rounded-2xl bg-amber-500/10 border border-amber-500/30 text-amber-300 text-xs">
                                    No payment accounts are configured by the company right now. Please contact venue administration to deposit cash in person.
                                </div>
                            ) : (
                                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                    {company_payment_accounts.map((acc) => (
                                        <div
                                            key={acc.id}
                                            onClick={() => depositForm.setData('payment_account_id', acc.id)}
                                            className={`p-4 rounded-2xl border transition cursor-pointer ${
                                                depositForm.data.payment_account_id === acc.id
                                                    ? 'bg-indigo-950/60 border-indigo-500 shadow-md ring-1 ring-indigo-500'
                                                    : 'bg-slate-950/60 border-slate-800 hover:border-slate-700'
                                            }`}
                                        >
                                            <div className="flex items-center justify-between">
                                                <span className="text-xs font-black uppercase text-indigo-400">
                                                    {acc.provider_name}
                                                </span>
                                                {depositForm.data.payment_account_id === acc.id && (
                                                    <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-500 text-white">
                                                        Selected
                                                    </span>
                                                )}
                                            </div>
                                            <div className="mt-2 text-sm font-bold text-white">{acc.account_name}</div>
                                            <div className="text-xs font-mono text-emerald-400 font-bold mt-0.5">
                                                {acc.account_number}
                                            </div>
                                            {acc.instructions && (
                                                <p className="text-[11px] text-slate-400 mt-2 border-t border-slate-800/80 pt-2">
                                                    {acc.instructions}
                                                </p>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            )}

                            {/* Submit Receipt Form */}
                            <div className="p-6 rounded-2xl bg-slate-950/80 border border-slate-800">
                                <h3 className="text-base font-bold text-white mb-1 flex items-center gap-2">
                                    <span>🧾</span> Step 2: Upload Payment Proof & Receipt
                                </h3>
                                <p className="text-xs text-slate-400 mb-4">
                                    Once sent, submit your receipt here. An admin will review and verify your deposit.
                                </p>

                                <form onSubmit={handleDepositRequest} className="space-y-4">
                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <label className="block text-xs font-semibold text-slate-300 mb-1">
                                                Deposit Amount ($ USD) <span className="text-rose-400">*</span>
                                            </label>
                                            <div className="relative">
                                                <span className="absolute left-3.5 top-2.5 text-slate-500 font-bold">$</span>
                                                <input
                                                    type="number"
                                                    min="1"
                                                    max="50000"
                                                    required
                                                    value={depositForm.data.amount}
                                                    onChange={(e) => depositForm.setData('amount', e.target.value)}
                                                    className="w-full bg-slate-900 border border-slate-800 rounded-xl pl-8 pr-4 py-2.5 text-xs text-white focus:border-indigo-500 focus:outline-none"
                                                />
                                            </div>
                                            {depositForm.errors.amount && (
                                                <p className="text-[11px] text-rose-400 mt-1">{depositForm.errors.amount}</p>
                                            )}
                                        </div>

                                        <div>
                                            <label className="block text-xs font-semibold text-slate-300 mb-1">
                                                Transaction / Reference # <span className="text-slate-500">(Optional)</span>
                                            </label>
                                            <input
                                                type="text"
                                                value={depositForm.data.reference_number}
                                                onChange={(e) => depositForm.setData('reference_number', e.target.value)}
                                                placeholder="e.g. TXN-9843212 or Ref ID"
                                                className="w-full bg-slate-900 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-white focus:border-indigo-500 focus:outline-none"
                                            />
                                        </div>
                                    </div>

                                    <div>
                                        <label className="block text-xs font-semibold text-slate-300 mb-1">
                                            Payment Receipt / Screenshot <span className="text-rose-400">*</span>
                                        </label>
                                        <input
                                            type="file"
                                            required
                                            accept="image/*,.pdf"
                                            onChange={(e) => depositForm.setData('receipt', e.target.files ? e.target.files[0] : null)}
                                            className="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-300 file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-600 file:text-white hover:file:bg-indigo-500"
                                        />
                                        <p className="text-[10px] text-slate-500 mt-1">Upload JPG, PNG, WEBP, or PDF (Max 5MB).</p>
                                        {depositForm.errors.receipt && (
                                            <p className="text-[11px] text-rose-400 mt-1">{depositForm.errors.receipt}</p>
                                        )}
                                    </div>

                                    <div>
                                        <label className="block text-xs font-semibold text-slate-300 mb-1">
                                            Notes / Sender Information <span className="text-slate-500">(Optional)</span>
                                        </label>
                                        <input
                                            type="text"
                                            value={depositForm.data.notes}
                                            onChange={(e) => depositForm.setData('notes', e.target.value)}
                                            placeholder="e.g. Sent via CBE Birr from phone ending in 1234"
                                            className="w-full bg-slate-900 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-white focus:border-indigo-500 focus:outline-none"
                                        />
                                    </div>

                                    <div className="flex justify-end gap-3 pt-2">
                                        <button
                                            type="button"
                                            onClick={() => setActiveTab('history')}
                                            className="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold"
                                        >
                                            Cancel
                                        </button>
                                        <button
                                            type="submit"
                                            disabled={depositForm.processing || !depositForm.data.receipt}
                                            className="px-6 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs disabled:opacity-50 transition shadow-sm"
                                        >
                                            {depositForm.processing ? 'Submitting Receipt...' : 'Submit Deposit Request'}
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    )}

                    {/* Deposit Requests Section */}
                    {activeTab === 'requests' && (
                        <div className="mt-8 pt-6 border-t border-slate-800 space-y-4 animate-fade-in">
                            <div className="flex items-center justify-between">
                                <div>
                                    <h3 className="text-base font-bold text-white flex items-center gap-2">
                                        <span>📋</span> Submitted Deposit Receipts
                                    </h3>
                                    <p className="text-xs text-slate-400">
                                        Track the verification status of your deposit receipts.
                                    </p>
                                </div>
                                <button
                                    onClick={() => setActiveTab('deposit')}
                                    className="px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition"
                                >
                                    + New Deposit
                                </button>
                            </div>

                            <div className="overflow-x-auto rounded-2xl border border-slate-800">
                                <table className="w-full text-left text-xs text-slate-300">
                                    <thead className="bg-slate-950 text-slate-400 uppercase font-semibold border-b border-slate-800">
                                        <tr>
                                            <th className="px-4 py-3">ID / Date</th>
                                            <th className="px-4 py-3">Amount</th>
                                            <th className="px-4 py-3">Account / Ref</th>
                                            <th className="px-4 py-3">Receipt</th>
                                            <th className="px-4 py-3">Status</th>
                                            <th className="px-4 py-3">Admin Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-800/80 bg-slate-950/40">
                                        {!deposit_requests || deposit_requests.data.length === 0 ? (
                                            <tr>
                                                <td colSpan={6} className="px-4 py-8 text-center text-slate-500">
                                                    No deposit requests submitted yet.
                                                </td>
                                            </tr>
                                        ) : (
                                            deposit_requests.data.map((req) => (
                                                <tr key={req.id} className="hover:bg-slate-800/40">
                                                    <td className="px-4 py-3">
                                                        <div className="font-mono text-white font-bold">#{req.id}</div>
                                                        <div className="text-[10px] text-slate-500">{req.created_at}</div>
                                                    </td>
                                                    <td className="px-4 py-3 font-mono font-bold text-emerald-400 text-sm">
                                                        {req.formatted_amount}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <div className="font-semibold text-white">
                                                            {req.payment_account?.provider_name || 'Standard Account'}
                                                        </div>
                                                        <div className="text-[10px] text-slate-500 font-mono">
                                                            {req.reference_number || '-'}
                                                        </div>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        {req.receipt_url ? (
                                                            <a
                                                                href={req.receipt_url}
                                                                target="_blank"
                                                                rel="noreferrer"
                                                                className="px-2.5 py-1 rounded bg-slate-800 hover:bg-slate-700 text-indigo-400 hover:text-indigo-300 font-semibold text-[11px] inline-flex items-center gap-1 border border-slate-700"
                                                            >
                                                                <span>👁️</span> View Proof
                                                            </a>
                                                        ) : (
                                                            <span className="text-slate-500 italic">No receipt</span>
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        {getRequestStatusBadge(req.status)}
                                                    </td>
                                                    <td className="px-4 py-3 text-slate-400 max-w-xs truncate">
                                                        {req.reviewer_notes || req.notes || '-'}
                                                    </td>
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}

                    {/* Withdraw Drawer */}
                    {activeTab === 'withdraw' && (
                        <div className="mt-8 pt-6 border-t border-slate-800 animate-fade-in">
                            <h3 className="text-base font-bold text-white mb-3">Withdraw Funds</h3>
                            <form onSubmit={handleWithdraw} className="space-y-4">
                                <div className="flex items-center space-x-3 max-w-sm">
                                    <div className="relative w-full">
                                        <span className="absolute left-3 top-2.5 text-slate-400 font-bold">$</span>
                                        <input
                                            type="number"
                                            min="1"
                                            max={Math.floor(balance / 100)}
                                            value={withdrawForm.data.amount}
                                            onChange={(e) => withdrawForm.setData('amount', parseInt(e.target.value) || 1)}
                                            className="w-full bg-slate-950 border border-slate-700 rounded-lg pl-8 pr-4 py-2 text-white font-mono font-bold focus:ring-indigo-500 focus:border-indigo-500"
                                        />
                                    </div>
                                    <button
                                        type="submit"
                                        disabled={withdrawForm.processing}
                                        className="bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-sm px-6 py-2 rounded-lg transition whitespace-nowrap"
                                    >
                                        {withdrawForm.processing ? 'Processing...' : 'Confirm Withdrawal'}
                                    </button>
                                </div>
                                {withdrawForm.errors.amount && (
                                    <p className="text-xs text-rose-400">{withdrawForm.errors.amount}</p>
                                )}
                            </form>
                        </div>
                    )}
                </div>

                {/* Player Financial Summary Stats */}
                <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div className="bg-slate-900/80 border border-slate-800 rounded-xl p-4 shadow-sm">
                        <div className="text-xs font-semibold text-slate-400 uppercase">Total Deposited</div>
                        <div className="text-xl font-bold text-emerald-400 font-mono mt-1">{statistics.formatted_total_deposited}</div>
                        <div className="text-xs text-slate-500 mt-1">Lifetime wallet additions</div>
                    </div>

                    <div className="bg-slate-900/80 border border-slate-800 rounded-xl p-4 shadow-sm">
                        <div className="text-xs font-semibold text-slate-400 uppercase">Total Won</div>
                        <div className="text-xl font-bold text-amber-400 font-mono mt-1">{statistics.formatted_total_won}</div>
                        <div className="text-xs text-slate-500 mt-1">Bingo game prize payouts</div>
                    </div>

                    <div className="bg-slate-900/80 border border-slate-800 rounded-xl p-4 shadow-sm">
                        <div className="text-xs font-semibold text-slate-400 uppercase">Total Entry Fees</div>
                        <div className="text-xl font-bold text-blue-400 font-mono mt-1">{statistics.formatted_total_entry_fees}</div>
                        <div className="text-xs text-slate-500 mt-1">Room buy-ins</div>
                    </div>

                    <div className="bg-slate-900/80 border border-slate-800 rounded-xl p-4 shadow-sm">
                        <div className="text-xs font-semibold text-slate-400 uppercase">Total Withdrawn</div>
                        <div className="text-xl font-bold text-rose-400 font-mono mt-1">{statistics.formatted_total_withdrawn}</div>
                        <div className="text-xs text-slate-500 mt-1">Processed cashouts</div>
                    </div>
                </div>

                {/* Transaction History Section */}
                <div className="bg-slate-900/80 border border-slate-800 rounded-xl overflow-hidden shadow-sm">
                    <div className="px-6 py-4 border-b border-slate-800 flex items-center justify-between">
                        <div>
                            <h2 className="text-lg font-bold text-white">Wallet Transaction History</h2>
                            <p className="text-xs text-slate-400 mt-0.5">Immutable double-entry log of all wallet activity.</p>
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm text-slate-300">
                            <thead className="bg-slate-950 text-xs uppercase text-slate-400 font-medium border-b border-slate-800">
                                <tr>
                                    <th className="px-5 py-3">Reference</th>
                                    <th className="px-5 py-3">Date & Time</th>
                                    <th className="px-5 py-3">Type</th>
                                    <th className="px-5 py-3">Amount</th>
                                    <th className="px-5 py-3">Wallet Balance</th>
                                    <th className="px-5 py-3">Description</th>
                                    <th className="px-5 py-3">Status</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-800">
                                {transactions.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={7} className="px-5 py-8 text-center text-slate-500">
                                            No transactions found yet. Join a bingo game or deposit funds to start!
                                        </td>
                                    </tr>
                                ) : (
                                    transactions.data.map((tx) => (
                                        <tr key={tx.id} className="hover:bg-slate-800/40 transition">
                                            <td className="px-5 py-3 font-mono text-xs text-slate-400">
                                                {tx.reference_code ?? `#${tx.id}`}
                                            </td>
                                            <td className="px-5 py-3 text-xs text-slate-400 whitespace-nowrap">
                                                {tx.created_at}
                                            </td>
                                            <td className="px-5 py-3 whitespace-nowrap">
                                                <span className={`inline-flex px-2 py-0.5 rounded text-[11px] font-semibold border ${getTypeColor(tx.type)}`}>
                                                    {tx.type}
                                                </span>
                                            </td>
                                            <td className="px-5 py-3 whitespace-nowrap">
                                                <span className={`font-bold font-mono text-sm ${tx.is_credit ? 'text-emerald-400' : 'text-rose-400'}`}>
                                                    {tx.formatted_amount}
                                                </span>
                                            </td>
                                            <td className="px-5 py-3 whitespace-nowrap font-mono text-xs text-slate-300">
                                                {tx.formatted_balance_after}
                                            </td>
                                            <td className="px-5 py-3 text-xs text-slate-300 max-w-sm truncate">
                                                {tx.description ?? '-'}
                                            </td>
                                            <td className="px-5 py-3 whitespace-nowrap">
                                                <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                                    {tx.status}
                                                </span>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </PlayerLayout>
    );
}
