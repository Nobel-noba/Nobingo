import CompanyAdminLayout from '@/Layouts/CompanyAdminLayout';
import { PageProps } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

interface ManagerBreakdownItem {
    id: number;
    name: string;
    email: string;
    walkin_sales: number;
    formatted_walkin_sales: string;
    walkin_sales_count: number;
    deposits: number;
    formatted_deposits: string;
    deposits_count: number;
    walkin_prizes: number;
    formatted_walkin_prizes: string;
    walkin_prizes_count: number;
    withdrawals: number;
    formatted_withdrawals: string;
    withdrawals_count: number;
    period_inflow: number;
    formatted_period_inflow: string;
    period_outflow: number;
    formatted_period_outflow: string;
    period_net: number;
    formatted_period_net: string;
    total_cash_on_hand: number;
    formatted_total_cash_on_hand: string;
}

interface Props extends PageProps {
    company: {
        id: number;
        name: string;
        slug: string;
    };
    managers?: Array<{
        id: number;
        name: string;
        email: string;
    }>;
    report: {
        is_game_manager: boolean;
        selected_manager_id: number | null;
        filters: {
            start_date: string;
            end_date: string;
            manager_id: string;
        };
        games: {
            total: number;
            completed: number;
            cancelled: number;
            active: number;
            completion_rate: number;
            avg_calls_to_win: number;
        };
        financial: {
            entry_fees: number;
            formatted_entry_fees: string;
            prizes_paid: number;
            formatted_prizes_paid: string;
            refunds_issued: number;
            formatted_refunds_issued: string;
            deposits: number;
            formatted_deposits: string;
            withdrawals: number;
            formatted_withdrawals: string;
            net_house_earnings: number;
            formatted_net_house_earnings: string;
            house_margin: number;
        };
        walkin: {
            sales_amount: number;
            formatted_sales_amount: string;
            sales_count: number;
            prizes_amount: number;
            formatted_prizes_amount: string;
            prizes_count: number;
            net_cash: number;
            formatted_net_cash: string;
        };
        cash_on_hand: {
            period_inflow: number;
            formatted_period_inflow: string;
            period_outflow: number;
            formatted_period_outflow: string;
            period_net_flow: number;
            formatted_period_net_flow: string;
            total_cash_on_hand: number;
            formatted_total_cash_on_hand: string;
        };
        managers_breakdown: ManagerBreakdownItem[];
        players: {
            total: number;
            active: number;
            suspended: number;
            top_winners: Array<{
                user_id: number;
                name: string;
                email: string;
                total_won: number;
                formatted_total_won: string;
                win_count: number;
            }>;
        };
    };
}

export default function ReportsIndex({ auth, company, managers = [], report }: Props) {
    const [startDate, setStartDate] = useState(report.filters.start_date || '');
    const [endDate, setEndDate] = useState(report.filters.end_date || '');
    const [selectedManager, setSelectedManager] = useState(report.filters.manager_id || '');

    const handleFilter = (mgrId = selectedManager, sDate = startDate, eDate = endDate) => {
        router.get(
            `/c/${company.slug}/admin/reports`,
            {
                manager_id: mgrId || undefined,
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
        setStartDate('');
        setEndDate('');
        setSelectedManager('');
        router.get(`/c/${company.slug}/admin/reports`);
    };

    return (
        <CompanyAdminLayout>
            <Head title={`Reports & Walk-in Cash Flow - ${company.name}`} />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-2xl font-bold text-white tracking-tight">
                                {report.is_game_manager ? 'My Walk-in & Drawer Cash Flow Report' : 'Executive Analytics & Walk-in Cash Flow'}
                            </h1>
                            {report.is_game_manager && (
                                <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                                    Manager Scoped
                                </span>
                            )}
                        </div>
                        <p className="text-sm text-neutral-400 mt-1">
                            {report.is_game_manager
                                ? 'Audit your walk-in card sales, walk-in cash prizes paid, cashier deposits, and physical cash on hand.'
                                : 'Comprehensive operational performance, walk-in cash flow tracking, Game Manager cash drawer reconciliation, and player retention.'}
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        <span className="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                            Live Audited
                        </span>
                    </div>
                </div>

                {/* Filter Bar */}
                <div className="bg-neutral-900 border border-neutral-800 rounded-2xl p-4">
                    <div className="flex flex-wrap items-center gap-3">
                        {/* Manager Filter for Company Admin */}
                        {!report.is_game_manager && managers.length > 0 && (
                            <div className="flex items-center gap-1.5">
                                <span className="text-xs font-semibold text-neutral-400 uppercase">Manager:</span>
                                <select
                                    value={selectedManager}
                                    onChange={(e) => {
                                        setSelectedManager(e.target.value);
                                        handleFilter(e.target.value, startDate, endDate);
                                    }}
                                    className="bg-neutral-950 border border-neutral-700 text-neutral-200 text-xs rounded-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"
                                >
                                    <option value="">All Game Managers</option>
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

                        {/* Actions */}
                        <button
                            onClick={() => handleFilter(selectedManager, startDate, endDate)}
                            className="bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold px-4 py-2 rounded-lg transition"
                        >
                            Apply Filter
                        </button>
                        <button
                            onClick={handleReset}
                            className="bg-neutral-800 hover:bg-neutral-700 text-neutral-300 text-xs font-semibold px-3 py-2 rounded-lg transition"
                        >
                            Reset
                        </button>

                        {(startDate || endDate || selectedManager) && (
                            <span className="text-xs text-amber-400 font-medium">
                                Active Filter Applied
                            </span>
                        )}
                    </div>
                </div>

                {/* Primary Physical Cash Flow & Drawer KPI Cards */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                    {/* Walk-in Sales */}
                    <div className="bg-neutral-900 border border-neutral-800 rounded-2xl p-5 shadow-sm">
                        <div className="flex justify-between items-start">
                            <div className="text-xs uppercase font-semibold text-neutral-400">Walk-in Card Sales</div>
                            <span className="text-[10px] px-2 py-0.5 rounded bg-blue-500/10 text-blue-400 border border-blue-500/20 font-bold">
                                {report.walkin.sales_count} cards
                            </span>
                        </div>
                        <div className="text-2xl font-black font-mono text-blue-400 mt-2">
                            {report.walkin.formatted_sales_amount}
                        </div>
                        <div className="text-xs text-neutral-500 mt-1">Cash received for walk-in cards</div>
                    </div>

                    {/* Walk-in Prizes Paid */}
                    <div className="bg-neutral-900 border border-neutral-800 rounded-2xl p-5 shadow-sm">
                        <div className="flex justify-between items-start">
                            <div className="text-xs uppercase font-semibold text-neutral-400">Walk-in Prizes Paid</div>
                            <span className="text-[10px] px-2 py-0.5 rounded bg-amber-500/10 text-amber-400 border border-amber-500/20 font-bold">
                                {report.walkin.prizes_count} winners
                            </span>
                        </div>
                        <div className="text-2xl font-black font-mono text-amber-400 mt-2">
                            {report.walkin.formatted_prizes_amount}
                        </div>
                        <div className="text-xs text-neutral-500 mt-1">Cash disbursed to walk-in winners</div>
                    </div>

                    {/* Walk-in Net Cash */}
                    <div className="bg-neutral-900 border border-neutral-800 rounded-2xl p-5 shadow-sm">
                        <div className="text-xs uppercase font-semibold text-neutral-400">Walk-in Net Cash</div>
                        <div className={`text-2xl font-black font-mono mt-2 ${report.walkin.net_cash >= 0 ? 'text-emerald-400' : 'text-rose-400'}`}>
                            {report.walkin.formatted_net_cash}
                        </div>
                        <div className="text-xs text-neutral-500 mt-1">Sales minus walk-in prizes</div>
                    </div>

                    {/* Total Cash on Hand */}
                    <div className="bg-gradient-to-br from-neutral-900 to-indigo-950/40 border border-indigo-800/40 rounded-2xl p-5 shadow-sm">
                        <div className="flex justify-between items-start">
                            <div className="text-xs uppercase font-semibold text-indigo-300">Total Cash on Hand</div>
                            <span className="text-[10px] px-2 py-0.5 rounded bg-indigo-500/20 text-indigo-300 font-bold">
                                Drawer Balance
                            </span>
                        </div>
                        <div className={`text-2xl font-black font-mono mt-2 ${report.cash_on_hand.total_cash_on_hand >= 0 ? 'text-emerald-400' : 'text-rose-400'}`}>
                            {report.cash_on_hand.formatted_total_cash_on_hand}
                        </div>
                        <div className="text-xs text-neutral-400 mt-1">Physical cash currently in register</div>
                    </div>
                </div>

                {/* Secondary Financial Overview Cards */}
                <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div className="bg-neutral-900 border border-neutral-800 rounded-2xl p-5 shadow-sm">
                        <div className="text-xs uppercase font-semibold text-neutral-400">Net House Earnings</div>
                        <div className={`text-2xl font-black font-mono mt-1 ${report.financial.net_house_earnings >= 0 ? 'text-emerald-400' : 'text-rose-400'}`}>
                            {report.financial.formatted_net_house_earnings}
                        </div>
                        <div className="text-xs text-neutral-500 mt-1">
                            Margin: <span className="font-bold text-neutral-300">{report.financial.house_margin}%</span>
                        </div>
                    </div>

                    <div className="bg-neutral-900 border border-neutral-800 rounded-2xl p-5 shadow-sm">
                        <div className="text-xs uppercase font-semibold text-neutral-400">Total Entry Fees Volume</div>
                        <div className="text-2xl font-black font-mono text-blue-400 mt-1">
                            {report.financial.formatted_entry_fees}
                        </div>
                        <div className="text-xs text-neutral-500 mt-1">All online & walk-in card fees</div>
                    </div>

                    <div className="bg-neutral-900 border border-neutral-800 rounded-2xl p-5 shadow-sm">
                        <div className="text-xs uppercase font-semibold text-neutral-400">Total Prizes Paid</div>
                        <div className="text-2xl font-black font-mono text-amber-400 mt-1">
                            {report.financial.formatted_prizes_paid}
                        </div>
                        <div className="text-xs text-neutral-500 mt-1">All verified player prizes</div>
                    </div>

                    <div className="bg-neutral-900 border border-neutral-800 rounded-2xl p-5 shadow-sm">
                        <div className="text-xs uppercase font-semibold text-neutral-400">Room Completion Rate</div>
                        <div className="text-2xl font-black font-mono text-indigo-400 mt-1">
                            {report.games.completion_rate}%
                        </div>
                        <div className="text-xs text-neutral-500 mt-1">
                            {report.games.completed} of {report.games.total} rooms finished
                        </div>
                    </div>
                </div>

                {/* Game Manager Cash Reconciliation Table (Company Admin only) */}
                {!report.is_game_manager && report.managers_breakdown.length > 0 && (
                    <div className="bg-neutral-900 border border-neutral-800 rounded-2xl overflow-hidden shadow-sm">
                        <div className="px-6 py-4 border-b border-neutral-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                            <div>
                                <h2 className="text-base font-bold text-white">Game Manager Drawer Cash Reconciliation</h2>
                                <p className="text-xs text-neutral-400 mt-0.5">
                                    Audit breakdown of physical cash handled by each Game Manager (walk-ins, cash deposits, walk-in payouts, cash withdrawals, and net cash on hand).
                                </p>
                            </div>
                            <span className="text-xs text-neutral-400 font-mono">
                                {report.managers_breakdown.length} Manager{report.managers_breakdown.length !== 1 ? 's' : ''} Active
                            </span>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm text-neutral-300">
                                <thead className="bg-neutral-950 text-xs uppercase text-neutral-400 font-medium border-b border-neutral-800">
                                    <tr>
                                        <th className="px-5 py-3">Game Manager</th>
                                        <th className="px-5 py-3 text-right">Walk-in Sales</th>
                                        <th className="px-5 py-3 text-right">Cash Deposits</th>
                                        <th className="px-5 py-3 text-right">Walk-in Prizes</th>
                                        <th className="px-5 py-3 text-right">Cash Withdrawals</th>
                                        <th className="px-5 py-3 text-right">Period Net Cash</th>
                                        <th className="px-5 py-3 text-right">Total Cash on Hand</th>
                                        <th className="px-5 py-3 text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-neutral-800">
                                    {report.managers_breakdown.map((mgr) => (
                                        <tr key={mgr.id} className="hover:bg-neutral-800/40 transition">
                                            <td className="px-5 py-3 whitespace-nowrap">
                                                <div className="font-semibold text-neutral-200">{mgr.name}</div>
                                                <div className="text-xs text-neutral-500">{mgr.email}</div>
                                            </td>
                                            <td className="px-5 py-3 text-right font-mono font-semibold text-blue-400">
                                                {mgr.formatted_walkin_sales}
                                                <div className="text-[10px] text-neutral-500 font-normal">({mgr.walkin_sales_count} cards)</div>
                                            </td>
                                            <td className="px-5 py-3 text-right font-mono font-semibold text-emerald-400">
                                                {mgr.formatted_deposits}
                                                <div className="text-[10px] text-neutral-500 font-normal">({mgr.deposits_count} deposits)</div>
                                            </td>
                                            <td className="px-5 py-3 text-right font-mono font-semibold text-amber-400">
                                                {mgr.formatted_walkin_prizes}
                                                <div className="text-[10px] text-neutral-500 font-normal">({mgr.walkin_prizes_count} prizes)</div>
                                            </td>
                                            <td className="px-5 py-3 text-right font-mono font-semibold text-rose-400">
                                                {mgr.formatted_withdrawals}
                                                <div className="text-[10px] text-neutral-500 font-normal">({mgr.withdrawals_count} withdrawals)</div>
                                            </td>
                                            <td className={`px-5 py-3 text-right font-mono font-bold ${mgr.period_net >= 0 ? 'text-emerald-400' : 'text-rose-400'}`}>
                                                {mgr.formatted_period_net}
                                            </td>
                                            <td className="px-5 py-3 text-right font-mono font-black text-white text-base">
                                                {mgr.formatted_total_cash_on_hand}
                                            </td>
                                            <td className="px-5 py-3 text-center">
                                                <button
                                                    onClick={() => {
                                                        setSelectedManager(String(mgr.id));
                                                        handleFilter(String(mgr.id), startDate, endDate);
                                                    }}
                                                    className="px-2.5 py-1 text-xs rounded bg-neutral-800 hover:bg-indigo-600 text-neutral-300 hover:text-white transition font-medium"
                                                >
                                                    View Details
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {/* Two Column Layout: Game Health & Financial Details */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {/* Game Operational Health */}
                    <div className="bg-neutral-900 border border-neutral-800 rounded-2xl p-6 space-y-4 shadow-sm">
                        <h2 className="text-base font-bold text-white border-b border-neutral-800 pb-3">
                            Game Engine Efficiency
                        </h2>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="bg-neutral-950 p-4 rounded-xl border border-neutral-800">
                                <span className="text-xs text-neutral-500 uppercase font-semibold block">Avg Balls to Win</span>
                                <span className="text-2xl font-bold font-mono text-white mt-1 block">
                                    {report.games.avg_calls_to_win > 0 ? report.games.avg_calls_to_win : '-'}
                                </span>
                                <span className="text-[11px] text-neutral-500">Historical call sequence</span>
                            </div>

                            <div className="bg-neutral-950 p-4 rounded-xl border border-neutral-800">
                                <span className="text-xs text-neutral-500 uppercase font-semibold block">Active Rooms</span>
                                <span className="text-2xl font-bold font-mono text-emerald-400 mt-1 block">
                                    {report.games.active}
                                </span>
                                <span className="text-[11px] text-neutral-500">In lobby or in play</span>
                            </div>
                        </div>

                        <div className="space-y-2 pt-2 text-xs">
                            <div className="flex justify-between py-1 border-b border-neutral-800/60">
                                <span className="text-neutral-400">Total Games Created</span>
                                <span className="font-semibold text-neutral-200">{report.games.total}</span>
                            </div>
                            <div className="flex justify-between py-1 border-b border-neutral-800/60">
                                <span className="text-neutral-400">Successfully Completed</span>
                                <span className="font-semibold text-emerald-400">{report.games.completed}</span>
                            </div>
                            <div className="flex justify-between py-1">
                                <span className="text-neutral-400">Cancelled Rooms</span>
                                <span className="font-semibold text-rose-400">{report.games.cancelled}</span>
                            </div>
                        </div>
                    </div>

                    {/* Financial Cashflow Summary */}
                    <div className="bg-neutral-900 border border-neutral-800 rounded-2xl p-6 space-y-4 shadow-sm">
                        <h2 className="text-base font-bold text-white border-b border-neutral-800 pb-3">
                            Cashflow & Treasury Movement
                        </h2>

                        <div className="space-y-2.5 text-xs">
                            <div className="flex justify-between py-2 border-b border-neutral-800/80">
                                <span className="text-neutral-400">Player Wallet Deposits</span>
                                <span className="font-mono font-bold text-emerald-400">{report.financial.formatted_deposits}</span>
                            </div>
                            <div className="flex justify-between py-2 border-b border-neutral-800/80">
                                <span className="text-neutral-400">Player Withdrawals Processed</span>
                                <span className="font-mono font-bold text-rose-400">{report.financial.formatted_withdrawals}</span>
                            </div>
                            <div className="flex justify-between py-2 border-b border-neutral-800/80">
                                <span className="text-neutral-400">Gross Room Entry Fees</span>
                                <span className="font-mono font-bold text-blue-400">{report.financial.formatted_entry_fees}</span>
                            </div>
                            <div className="flex justify-between py-2 border-b border-neutral-800/80">
                                <span className="text-neutral-400">Prizes Paid to Winners</span>
                                <span className="font-mono font-bold text-amber-400">{report.financial.formatted_prizes_paid}</span>
                            </div>
                            <div className="flex justify-between py-2 border-b border-neutral-800/80">
                                <span className="text-neutral-400">Room Cancellation Refunds</span>
                                <span className="font-mono font-bold text-purple-400">{report.financial.formatted_refunds_issued}</span>
                            </div>
                            <div className="flex justify-between py-2 font-bold text-sm bg-neutral-950 p-3 rounded-lg border border-neutral-800">
                                <span className="text-neutral-300">Net House Profit</span>
                                <span className={report.financial.net_house_earnings >= 0 ? 'text-emerald-400 font-mono' : 'text-rose-400 font-mono'}>
                                    {report.financial.formatted_net_house_earnings}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Top 5 Winners Leaderboard */}
                <div className="bg-neutral-900 border border-neutral-800 rounded-2xl overflow-hidden shadow-sm">
                    <div className="px-6 py-4 border-b border-neutral-800 flex justify-between items-center">
                        <h2 className="text-base font-bold text-white">Top Winning Players Leaderboard</h2>
                        <span className="text-xs text-neutral-400">
                            {report.players.active} active players of {report.players.total} registered
                        </span>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm text-neutral-300">
                            <thead className="bg-neutral-950 text-xs uppercase text-neutral-400 font-medium border-b border-neutral-800">
                                <tr>
                                    <th className="px-5 py-3">Rank</th>
                                    <th className="px-5 py-3">Player</th>
                                    <th className="px-5 py-3">Bingo Wins</th>
                                    <th className="px-5 py-3 text-right">Total Payouts Won</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-neutral-800">
                                {report.players.top_winners.length === 0 ? (
                                    <tr>
                                        <td colSpan={4} className="px-5 py-8 text-center text-neutral-500">
                                            No winners recorded for this period.
                                        </td>
                                    </tr>
                                ) : (
                                    report.players.top_winners.map((winner, idx) => (
                                        <tr key={winner.user_id} className="hover:bg-neutral-800/40 transition">
                                            <td className="px-5 py-3 font-bold font-mono text-amber-400">
                                                #{idx + 1}
                                            </td>
                                            <td className="px-5 py-3 whitespace-nowrap">
                                                <div className="font-semibold text-neutral-200">{winner.name}</div>
                                                <div className="text-xs text-neutral-500">{winner.email}</div>
                                            </td>
                                            <td className="px-5 py-3 font-semibold text-neutral-200">
                                                {winner.win_count} Win{winner.win_count !== 1 ? 's' : ''}
                                            </td>
                                            <td className="px-5 py-3 font-bold font-mono text-amber-400 text-right">
                                                {winner.formatted_total_won}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </CompanyAdminLayout>
    );
}

