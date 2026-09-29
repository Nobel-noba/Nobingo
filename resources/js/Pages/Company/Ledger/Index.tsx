import CompanyAdminLayout from '@/Layouts/CompanyAdminLayout';
import { PageProps } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

interface TransactionItem {
    id: number;
    type: string;
    amount: number;
    formatted_amount: string;
    currency: string;
    status: string;
    balance_before: number;
    formatted_balance_before: string;
    balance_after: number;
    formatted_balance_after: string;
    reference_type: string | null;
    reference_id: number | null;
    reference_code: string | null;
    description: string | null;
    is_credit: boolean;
    is_debit: boolean;
    user: {
        id: number;
        name: string;
        email: string;
    } | null;
    created_at: string;
}

interface Props extends PageProps {
    company: {
        id: number;
        name: string;
        slug: string;
    };
    transactions: {
        data: TransactionItem[];
        current_page: number;
        last_page: number;
        total: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    statistics: {
        entry_fees: number;
        formatted_entry_fees: string;
        prizes: number;
        formatted_prizes: string;
        refunds: number;
        formatted_refunds: string;
        deposits: number;
        formatted_deposits: string;
        withdrawals: number;
        formatted_withdrawals: string;
        pending_payouts: number;
        formatted_pending_payouts: string;
        net_house_earnings: number;
        formatted_net_house_earnings: string;
    };
    filters: {
        type: string;
        search: string;
    };
}

export default function LedgerIndex({ auth, company, transactions, statistics, filters }: Props) {
    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [selectedType, setSelectedType] = useState(filters.type || 'ALL');

    const handleFilterChange = (type: string, search: string) => {
        router.get(
            `/c/${company.slug}/admin/ledger`,
            {
                type: type !== 'ALL' ? type : undefined,
                search: search || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
            }
        );
    };

    const getTypeBadge = (type: string) => {
        switch (type) {
            case 'ENTRY_FEE':
                return 'bg-blue-500/20 text-blue-300 border-blue-500/30';
            case 'PRIZE':
                return 'bg-amber-500/20 text-amber-300 border-amber-500/30';
            case 'REFUND':
                return 'bg-purple-500/20 text-purple-300 border-purple-500/30';
            case 'DEPOSIT':
                return 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30';
            case 'WITHDRAWAL':
                return 'bg-rose-500/20 text-rose-300 border-rose-500/30';
            case 'ADJUSTMENT':
                return 'bg-cyan-500/20 text-cyan-300 border-cyan-500/30';
            default:
                return 'bg-slate-700 text-slate-300 border-slate-600';
        }
    };

    return (
        <CompanyAdminLayout>
            <Head title={`Treasury & Ledger - ${company.name}`} />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-white tracking-tight">Treasury & Financial Ledger</h1>
                        <p className="text-sm text-neutral-400 mt-1">
                            Double-entry audit log of all balance transactions, entry fees, and prize distributions.
                        </p>
                    </div>
                    <div className="flex items-center space-x-2">
                        <span className="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                            Immutable Ledger Active
                        </span>
                    </div>
                </div>

                {/* Treasury Statistics Grid */}
                <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div className="bg-neutral-900 border border-neutral-800 rounded-xl p-4 shadow-sm">
                        <div className="text-xs font-medium text-neutral-400 uppercase tracking-wider">Entry Fees Inflow</div>
                        <div className="text-xl font-bold text-blue-400 mt-1">{statistics.formatted_entry_fees}</div>
                        <div className="text-xs text-neutral-500 mt-1">Collected from room joins</div>
                    </div>

                    <div className="bg-neutral-900 border border-neutral-800 rounded-xl p-4 shadow-sm">
                        <div className="text-xs font-medium text-neutral-400 uppercase tracking-wider">Prizes Distributed</div>
                        <div className="text-xl font-bold text-amber-400 mt-1">{statistics.formatted_prizes}</div>
                        <div className="text-xs text-neutral-500 mt-1">Credited to verified winners</div>
                    </div>

                    <div className="bg-neutral-900 border border-neutral-800 rounded-xl p-4 shadow-sm">
                        <div className="text-xs font-medium text-neutral-400 uppercase tracking-wider">Refunds Issued</div>
                        <div className="text-xl font-bold text-purple-400 mt-1">{statistics.formatted_refunds}</div>
                        <div className="text-xs text-neutral-500 mt-1">Cancelled rooms & returns</div>
                    </div>

                    <div className="bg-neutral-900 border border-neutral-800 rounded-xl p-4 shadow-sm">
                        <div className="text-xs font-medium text-neutral-400 uppercase tracking-wider">Net House Revenue</div>
                        <div className={`text-xl font-bold mt-1 ${statistics.net_house_earnings >= 0 ? 'text-emerald-400' : 'text-rose-400'}`}>
                            {statistics.formatted_net_house_earnings}
                        </div>
                        <div className="text-xs text-neutral-500 mt-1">Fees minus prizes & refunds</div>
                    </div>
                </div>

                {/* Filter and Search Bar */}
                <div className="bg-neutral-900 border border-neutral-800 rounded-xl p-4 flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div className="flex flex-wrap items-center gap-3">
                        <span className="text-xs font-semibold text-neutral-400 uppercase">Type:</span>
                        <select
                            value={selectedType}
                            onChange={(e) => {
                                setSelectedType(e.target.value);
                                handleFilterChange(e.target.value, searchTerm);
                            }}
                            className="bg-neutral-950 border border-neutral-700 text-neutral-200 text-xs rounded-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"
                        >
                            <option value="ALL">All Types</option>
                            <option value="ENTRY_FEE">Entry Fees</option>
                            <option value="PRIZE">Prizes</option>
                            <option value="REFUND">Refunds</option>
                            <option value="DEPOSIT">Deposits</option>
                            <option value="WITHDRAWAL">Withdrawals</option>
                            <option value="ADJUSTMENT">Adjustments</option>
                        </select>
                    </div>

                    <div className="flex items-center space-x-2">
                        <input
                            type="text"
                            placeholder="Search user, ref code, or description..."
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') {
                                    handleFilterChange(selectedType, searchTerm);
                                }
                            }}
                            className="bg-neutral-950 border border-neutral-700 text-neutral-200 text-xs rounded-lg px-3 py-2 w-72 focus:ring-indigo-500 focus:border-indigo-500"
                        />
                        <button
                            onClick={() => handleFilterChange(selectedType, searchTerm)}
                            className="bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold px-4 py-2 rounded-lg transition"
                        >
                            Filter
                        </button>
                    </div>
                </div>

                {/* Transactions Table */}
                <div className="bg-neutral-900 border border-neutral-800 rounded-xl overflow-hidden shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm text-neutral-300">
                            <thead className="bg-neutral-950 text-xs uppercase text-neutral-400 font-medium border-b border-neutral-800">
                                <tr>
                                    <th className="px-4 py-3">Tx Ref</th>
                                    <th className="px-4 py-3">Timestamp</th>
                                    <th className="px-4 py-3">Player / Account</th>
                                    <th className="px-4 py-3">Type</th>
                                    <th className="px-4 py-3">Amount</th>
                                    <th className="px-4 py-3">Wallet Delta</th>
                                    <th className="px-4 py-3">Description</th>
                                    <th className="px-4 py-3">Status</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-neutral-800">
                                {transactions.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={8} className="px-4 py-8 text-center text-neutral-500">
                                            No transactions match the selected criteria.
                                        </td>
                                    </tr>
                                ) : (
                                    transactions.data.map((tx) => (
                                        <tr key={tx.id} className="hover:bg-neutral-800/50 transition">
                                            <td className="px-4 py-3 font-mono text-xs text-neutral-400">
                                                {tx.reference_code ?? `#${tx.id}`}
                                            </td>
                                            <td className="px-4 py-3 text-xs text-neutral-400 whitespace-nowrap">
                                                {tx.created_at}
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap">
                                                {tx.user ? (
                                                    <div>
                                                        <div className="font-medium text-neutral-200">{tx.user.name}</div>
                                                        <div className="text-xs text-neutral-500">{tx.user.email}</div>
                                                    </div>
                                                ) : (
                                                    <span className="text-neutral-500 italic">System</span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap">
                                                <span className={`inline-flex px-2 py-0.5 rounded text-[11px] font-semibold border ${getTypeBadge(tx.type)}`}>
                                                    {tx.type}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap">
                                                <span className={`font-bold font-mono ${tx.is_credit ? 'text-emerald-400' : 'text-rose-400'}`}>
                                                    {tx.formatted_amount}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap font-mono text-xs text-neutral-400">
                                                <span className="text-neutral-500">{tx.formatted_balance_before}</span>
                                                <span className="mx-1 text-neutral-600">→</span>
                                                <span className="text-neutral-200 font-semibold">{tx.formatted_balance_after}</span>
                                            </td>
                                            <td className="px-4 py-3 text-xs text-neutral-300 max-w-xs truncate">
                                                {tx.description ?? '-'}
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap">
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

                    {/* Pagination */}
                    {transactions.links.length > 3 && (
                        <div className="px-4 py-3 border-t border-neutral-800 flex items-center justify-between">
                            <div className="text-xs text-neutral-500">
                                Showing page {transactions.current_page} of {transactions.last_page} ({transactions.total} records)
                            </div>
                            <div className="flex space-x-1">
                                {transactions.links.map((link, idx) => (
                                    <button
                                        key={idx}
                                        disabled={!link.url}
                                        onClick={() => link.url && router.visit(link.url)}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                        className={`px-3 py-1 text-xs rounded border transition ${
                                            link.active
                                                ? 'bg-indigo-600 text-white border-indigo-600'
                                                : link.url
                                                ? 'bg-neutral-950 text-neutral-300 border-neutral-800 hover:bg-neutral-800'
                                                : 'bg-neutral-950 text-neutral-600 border-neutral-900 cursor-not-allowed'
                                        }`}
                                    />
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </CompanyAdminLayout>
    );
}
