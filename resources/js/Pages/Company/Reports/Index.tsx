import CompanyAdminLayout from '@/Layouts/CompanyAdminLayout';
import { PageProps } from '@/types';
import { Head } from '@inertiajs/react';

interface Props extends PageProps {
    company: {
        id: number;
        name: string;
        slug: string;
    };
    report: {
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

export default function ReportsIndex({ auth, company, report }: Props) {
    return (
        <CompanyAdminLayout>
            <Head title={`Reports & Analytics - ${company.name}`} />

            <div className="space-y-6">
                {/* Header */}
                <div>
                    <h1 className="text-2xl font-bold text-white tracking-tight">Executive Analytics & Reports</h1>
                    <p className="text-sm text-neutral-400 mt-1">
                        High-level operational performance, house profitability, game call metrics, and player retention.
                    </p>
                </div>

                {/* Top KPI Metric Cards */}
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
                        <div className="text-xs uppercase font-semibold text-neutral-400">Total Buy-In Volume</div>
                        <div className="text-2xl font-black font-mono text-blue-400 mt-1">
                            {report.financial.formatted_entry_fees}
                        </div>
                        <div className="text-xs text-neutral-500 mt-1">Across all completed rooms</div>
                    </div>

                    <div className="bg-neutral-900 border border-neutral-800 rounded-2xl p-5 shadow-sm">
                        <div className="text-xs uppercase font-semibold text-neutral-400">Total Prizes Paid</div>
                        <div className="text-2xl font-black font-mono text-amber-400 mt-1">
                            {report.financial.formatted_prizes_paid}
                        </div>
                        <div className="text-xs text-neutral-500 mt-1">Distributed to winners</div>
                    </div>

                    <div className="bg-neutral-900 border border-neutral-800 rounded-2xl p-5 shadow-sm">
                        <div className="text-xs uppercase font-semibold text-neutral-400">Game Completion Rate</div>
                        <div className="text-2xl font-black font-mono text-indigo-400 mt-1">
                            {report.games.completion_rate}%
                        </div>
                        <div className="text-xs text-neutral-500 mt-1">
                            {report.games.completed} of {report.games.total} games
                        </div>
                    </div>
                </div>

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
                                            No winners recorded yet.
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
