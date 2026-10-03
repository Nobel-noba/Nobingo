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
    is_walkin?: boolean;
    user: {
        id: number;
        name: string;
        email: string;
    } | null;
    game_manager?: {
        id: number;
        name: string;
        email: string;
    } | null;
    created_at: string;
}

interface ManagerItem {
    id: number;
    name: string;
    email: string;
}

interface Props extends PageProps {
    company: {
        id: number;
        name: string;
        slug: string;
    };
    is_game_manager?: boolean;
    managers?: ManagerItem[];
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
        walkin_sales?: number;
        formatted_walkin_sales?: string;
        walkin_prizes?: number;
        formatted_walkin_prizes?: string;
        walkin_net_cash?: number;
        formatted_walkin_net_cash?: string;
        period_net_cash?: number;
        formatted_period_net_cash?: string;
        pending_payouts: number;
        formatted_pending_payouts: string;
        net_house_earnings: number;
        formatted_net_house_earnings: string;
    };
    filters: {
        type: string;
        search: string;
        manager_id?: string;
        start_date?: string;
        end_date?: string;
    };
}

export default function LedgerIndex({ auth, company, is_game_manager, managers = [], transactions, statistics, filters }: Props) {
    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [selectedType, setSelectedType] = useState(filters.type || 'ALL');
    const [selectedManager, setSelectedManager] = useState(filters.manager_id || '');
    const [startDate, setStartDate] = useState(filters.start_date || '');
    const [endDate, setEndDate] = useState(filters.end_date || '');

    const handleFilterChange = (
        type = selectedType,
        search = searchTerm,
        managerId = selectedManager,
        sDate = startDate,
        eDate = endDate
    ) => {
        const basePath = typeof window !== 'undefined' && window.location.pathname.includes('/transactions')
            ? `/c/${company.slug}/admin/transactions`
            : `/c/${company.slug}/admin/ledger`;

        router.get(
            basePath,
            {
                type: type !== 'ALL' ? type : undefined,
                search: search || undefined,
                manager_id: managerId || undefined,
                start_date: sDate || undefined,
                end_date: eDate || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
            }
        );
    };

    const handleReset = () => {
        setSelectedType('ALL');
        setSearchTerm('');
        setSelectedManager('');
        setStartDate('');
        setEndDate('');
        const basePath = typeof window !== 'undefined' && window.location.pathname.includes('/transactions')
            ? `/c/${company.slug}/admin/transactions`
            : `/c/${company.slug}/admin/ledger`;
        router.get(basePath);
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
            <Head title={`Treasury & Money Flows - ${company.name}`} />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-2xl font-bold text-white tracking-tight">Transactions & Money Flows Monitor</h1>
                            {is_game_manager && (
                                <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                                    Manager Scoped
                                </span>
                            )}
                        </div>
                        <p className="text-sm text-neutral-400 mt-1">
                            {is_game_manager
                                ? 'Real-time double-entry audit of all cash and digital money flows for games and counter operations handled by you.'
                                : 'Live double-entry audit of all cash and digital money flows: room entry fees, walk-in customer cash, player deposits, and prize payouts across all managers.'}
                        </p>
                    </div>
                    <div className="flex items-center space-x-2">
                        <span className="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                            Immutable Ledger Active
                        </span>
                    </div>
                </div>

                {/* Game Manager Isolation Banner */}
                {is_game_manager && (
                    <div className="bg-indigo-950/40 border border-indigo-800/60 rounded-xl p-3.5 flex items-center gap-3 text-xs text-indigo-200">
                        <span className="text-lg">🔒</span>
                        <span>
                            <strong>Personal Operations View:</strong> You are viewing transactions strictly belonging to games you started or player counter transactions you processed.
                        </span>
                    </div>
                )}

                {/* Treasury Statistics Grid */}
                <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                    <div className="bg-neutral-900 border border-neutral-800 rounded-xl p-3.5 shadow-sm">
                        <div className="text-[11px] font-semibold text-neutral-400 uppercase tracking-wider">Entry Fees</div>
                        <div className="text-lg font-bold text-blue-400 mt-0.5">{statistics.formatted_entry_fees}</div>
                        <div className="text-[10px] text-neutral-500 mt-0.5">Online & walk-ins</div>
                    </div>

                    <div className="bg-neutral-900 border border-neutral-800 rounded-xl p-3.5 shadow-sm">
                        <div className="text-[11px] font-semibold text-neutral-400 uppercase tracking-wider">Prizes Paid</div>
                        <div className="text-lg font-bold text-amber-400 mt-0.5">{statistics.formatted_prizes}</div>
                        <div className="text-[10px] text-neutral-500 mt-0.5">Distributed to winners</div>
                    </div>

                    <div className="bg-neutral-900 border border-neutral-800 rounded-xl p-3.5 shadow-sm">
                        <div className="text-[11px] font-semibold text-neutral-400 uppercase tracking-wider">Cash Deposits</div>
                        <div className="text-lg font-bold text-emerald-400 mt-0.5">{statistics.formatted_deposits}</div>
                        <div className="text-[10px] text-neutral-500 mt-0.5">Counter & online deposits</div>
                    </div>

                    <div className="bg-neutral-900 border border-neutral-800 rounded-xl p-3.5 shadow-sm">
                        <div className="text-[11px] font-semibold text-neutral-400 uppercase tracking-wider">Cash Withdrawals</div>
                        <div className="text-lg font-bold text-rose-400 mt-0.5">{statistics.formatted_withdrawals}</div>
                        <div className="text-[10px] text-neutral-500 mt-0.5">Disbursed to players</div>
                    </div>

                    <div className="bg-neutral-900 border border-neutral-800 rounded-xl p-3.5 shadow-sm">
                        <div className="text-[11px] font-semibold text-neutral-400 uppercase tracking-wider">Walk-in Net Cash</div>
                        <div className={`text-lg font-bold mt-0.5 ${(statistics.walkin_net_cash ?? 0) >= 0 ? 'text-emerald-400' : 'text-rose-400'}`}>
                            {statistics.formatted_walkin_net_cash ?? '$0.00'}
                        </div>
                        <div className="text-[10px] text-neutral-500 mt-0.5">Sales minus walk-in prizes</div>
                    </div>

                    <div className="bg-neutral-900 border border-neutral-800 rounded-xl p-3.5 shadow-sm">
                        <div className="text-[11px] font-semibold text-neutral-400 uppercase tracking-wider">Net Cash Flow</div>
                        <div className={`text-lg font-bold mt-0.5 ${(statistics.period_net_cash ?? 0) >= 0 ? 'text-emerald-400' : 'text-rose-400'}`}>
                            {statistics.formatted_period_net_cash ?? '$0.00'}
                        </div>
                        <div className="text-[10px] text-neutral-500 mt-0.5">Inflow minus outflow</div>
                    </div>
                </div>

                {/* Filter and Search Bar */}
                <div className="bg-neutral-900 border border-neutral-800 rounded-xl p-4 space-y-3">
                    <div className="flex flex-wrap items-center gap-3">
                        {/* Type Selector */}
                        <div className="flex items-center gap-1.5">
                            <span className="text-xs font-semibold text-neutral-400 uppercase">Type:</span>
                            <select
                                value={selectedType}
                                onChange={(e) => {
                                    setSelectedType(e.target.value);
                                    handleFilterChange(e.target.value, searchTerm, selectedManager, startDate, endDate);
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

                        {/* Manager Selector (Company Admin only) */}
                        {!is_game_manager && managers.length > 0 && (
                            <div className="flex items-center gap-1.5">
                                <span className="text-xs font-semibold text-neutral-400 uppercase">Manager:</span>
                                <select
                                    value={selectedManager}
                                    onChange={(e) => {
                                        setSelectedManager(e.target.value);
                                        handleFilterChange(selectedType, searchTerm, e.target.value, startDate, endDate);
                                    }}
                                    className="bg-neutral-950 border border-neutral-700 text-neutral-200 text-xs rounded-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"
                                >
                                    <option value="">All Managers</option>
                                    {managers.map((m) => (
                                        <option key={m.id} value={m.id}>
                                            {m.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        )}

                        {/* Date Range Selectors */}
                        <div className="flex items-center gap-1.5">
                            <span className="text-xs font-semibold text-neutral-400 uppercase">From:</span>
                            <input
                                type="date"
                                value={startDate}
                                onChange={(e) => setStartDate(e.target.value)}
                                className="bg-neutral-950 border border-neutral-700 text-neutral-200 text-xs rounded-lg px-2.5 py-1.5 focus:ring-indigo-500 focus:border-indigo-500"
                            />
                        </div>
                        <div className="flex items-center gap-1.5">
                            <span className="text-xs font-semibold text-neutral-400 uppercase">To:</span>
                            <input
                                type="date"
                                value={endDate}
                                onChange={(e) => setEndDate(e.target.value)}
                                className="bg-neutral-950 border border-neutral-700 text-neutral-200 text-xs rounded-lg px-2.5 py-1.5 focus:ring-indigo-500 focus:border-indigo-500"
                            />
                        </div>

                        {/* Search Input */}
                        <div className="flex-1 min-w-[200px]">
                            <input
                                type="text"
                                placeholder="Search user, ref code, or description..."
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter') {
                                        handleFilterChange(selectedType, searchTerm, selectedManager, startDate, endDate);
                                    }
                                }}
                                className="w-full bg-neutral-950 border border-neutral-700 text-neutral-200 text-xs rounded-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"
                            />
                        </div>

                        {/* Filter & Reset Buttons */}
                        <button
                            onClick={() => handleFilterChange(selectedType, searchTerm, selectedManager, startDate, endDate)}
                            className="bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold px-4 py-2 rounded-lg transition"
                        >
                            Filter
                        </button>
                        <button
                            onClick={handleReset}
                            className="bg-neutral-800 hover:bg-neutral-700 text-neutral-300 text-xs font-semibold px-3 py-2 rounded-lg transition"
                        >
                            Reset
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
                                    {!is_game_manager && <th className="px-4 py-3">Host Manager</th>}
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
                                        <td colSpan={is_game_manager ? 8 : 9} className="px-4 py-8 text-center text-neutral-500">
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
                                                ) : tx.is_walkin || tx.reference_code?.startsWith('WALKIN') || tx.reference_code?.startsWith('PRIZE-WALKIN') || tx.description?.toLowerCase().includes('walk-in') ? (
                                                    <div>
                                                        <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-amber-500/10 text-amber-300 border border-amber-500/30">
                                                            Walk-in Guest (Cash)
                                                        </span>
                                                        <div className="text-[11px] text-neutral-500 mt-0.5">Counter Purchase</div>
                                                    </div>
                                                ) : (
                                                    <span className="text-neutral-500 italic">System / Treasury</span>
                                                )}
                                            </td>
                                            {!is_game_manager && (
                                                <td className="px-4 py-3 whitespace-nowrap text-xs">
                                                    {tx.game_manager ? (
                                                        <span className="text-indigo-300 font-medium">
                                                            {tx.game_manager.name}
                                                        </span>
                                                    ) : (
                                                        <span className="text-neutral-500">Direct / Company</span>
                                                    )}
                                                </td>
                                            )}
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
