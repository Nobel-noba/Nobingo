import PlatformOwnerLayout from '@/Layouts/PlatformOwnerLayout';
import { PageProps, Tenant } from '@/types';
import { Head, Link } from '@inertiajs/react';

interface Props extends PageProps {
    stats: {
        total_companies: number;
        active_companies: number;
        suspended_companies: number;
        total_users: number;
        total_players: number;
        total_games: number;
        active_games: number;
        total_platform_revenue: number;
        formatted_platform_revenue: string;
        total_pots_played: number;
        formatted_pots_played: string;
        total_winner_payouts: number;
        formatted_winner_payouts: string;
        total_house_gross: number;
        formatted_house_gross: string;
        total_credits_purchased: number;
        formatted_credits_purchased: string;
        pending_credit_requests: number;
    };
    companies: Array<Tenant & { users_count: number; cards_count?: number }>;
}

export default function PlatformDashboard({ stats, companies }: Props) {
    return (
        <PlatformOwnerLayout
            header={
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-bold text-white tracking-tight">Platform Master Control</h1>
                        <p className="text-xs text-slate-400 mt-0.5">Global multi-tenant infrastructure, credit treasury, and revenue oversight</p>
                    </div>
                    <div className="flex items-center gap-3">
                        <Link
                            href="/platform/settings/revenue"
                            className="bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold px-3.5 py-2 rounded-xl border border-slate-700 transition"
                        >
                            ⚙️ Revenue Splits
                        </Link>
                        <Link
                            href="/platform/companies"
                            className="bg-rose-600 hover:bg-rose-500 text-white text-xs font-semibold px-4 py-2 rounded-xl shadow-sm transition"
                        >
                            + Rent New Company
                        </Link>
                    </div>
                </div>
            }
        >
            <Head title="Platform Master Control" />

            <div className="space-y-6">
                {/* Pending Credit Requests Action Banner */}
                {stats.pending_credit_requests > 0 && (
                    <div className="p-4 rounded-2xl bg-amber-500/10 border border-amber-500/30 flex items-center justify-between shadow-sm animate-pulse">
                        <div className="flex items-center gap-3">
                            <span className="text-2xl">⚡</span>
                            <div>
                                <h3 className="text-sm font-bold text-amber-300">
                                    {stats.pending_credit_requests} Pending Company Credit Purchase {stats.pending_credit_requests === 1 ? 'Request' : 'Requests'}
                                </h3>
                                <p className="text-xs text-amber-400/80">
                                    Tenant companies uploaded payment receipts and are waiting for credit approval to activate games.
                                </p>
                            </div>
                        </div>
                        <Link
                            href="/platform/deposit-requests"
                            className="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs transition"
                        >
                            Review Receipts &rarr;
                        </Link>
                    </div>
                )}

                {/* Financial & Revenue Stats Grid */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div className="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-sm">
                        <div className="text-xs font-semibold text-rose-400 uppercase tracking-wider flex items-center justify-between">
                            <span>Platform Revenue</span>
                            <span className="text-base">💎</span>
                        </div>
                        <div className="text-3xl font-black text-white font-mono mt-2">{stats.formatted_platform_revenue}</div>
                        <div className="text-xs text-slate-400 mt-1 font-medium">Automatic house fee commissions</div>
                    </div>

                    <div className="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-sm">
                        <div className="text-xs font-semibold text-emerald-400 uppercase tracking-wider flex items-center justify-between">
                            <span>Platform Credits Sold</span>
                            <span className="text-base">💳</span>
                        </div>
                        <div className="text-3xl font-black text-emerald-400 font-mono mt-2">{stats.formatted_credits_purchased}</div>
                        <div className="text-xs text-slate-400 mt-1">Tenant credit purchase volume</div>
                    </div>

                    <div className="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-sm">
                        <div className="text-xs font-semibold text-amber-400 uppercase tracking-wider flex items-center justify-between">
                            <span>Total Pots Played</span>
                            <span className="text-base">🎱</span>
                        </div>
                        <div className="text-3xl font-black text-amber-400 font-mono mt-2">{stats.formatted_pots_played}</div>
                        <div className="text-xs text-slate-400 mt-1">Across all completed games</div>
                    </div>

                    <div className="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-sm">
                        <div className="text-xs font-semibold text-indigo-400 uppercase tracking-wider flex items-center justify-between">
                            <span>Winner Payouts</span>
                            <span className="text-base">🏆</span>
                        </div>
                        <div className="text-3xl font-black text-indigo-300 font-mono mt-2">{stats.formatted_winner_payouts}</div>
                        <div className="text-xs text-slate-400 mt-1">Directly awarded to players</div>
                    </div>
                </div>

                {/* Infrastructure Operational Stats */}
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div className="bg-slate-900/60 border border-slate-800/80 rounded-xl p-4">
                        <div className="text-[11px] font-medium text-slate-400 uppercase">Tenant Companies</div>
                        <div className="text-2xl font-black text-white mt-1">{stats.total_companies}</div>
                        <div className="text-[11px] text-emerald-400 mt-0.5">{stats.active_companies} active</div>
                    </div>

                    <div className="bg-slate-900/60 border border-slate-800/80 rounded-xl p-4">
                        <div className="text-[11px] font-medium text-slate-400 uppercase">Active Game Rooms</div>
                        <div className="text-2xl font-black text-amber-400 mt-1">{stats.active_games}</div>
                        <div className="text-[11px] text-slate-400 mt-0.5">{stats.total_games} total games hosted</div>
                    </div>

                    <div className="bg-slate-900/60 border border-slate-800/80 rounded-xl p-4">
                        <div className="text-[11px] font-medium text-slate-400 uppercase">Registered Players</div>
                        <div className="text-2xl font-black text-indigo-400 mt-1">{stats.total_players}</div>
                        <div className="text-[11px] text-slate-400 mt-0.5">{stats.total_users} total system users</div>
                    </div>

                    <div className="bg-slate-900/60 border border-slate-800/80 rounded-xl p-4">
                        <div className="text-[11px] font-medium text-slate-400 uppercase">House Gross Cuts</div>
                        <div className="text-2xl font-black text-white font-mono mt-1">{stats.formatted_house_gross}</div>
                        <div className="text-[11px] text-slate-400 mt-0.5">Company aggregate gross</div>
                    </div>
                </div>

                {/* Rented Companies Directory */}
                <div className="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
                    <div className="p-5 border-b border-slate-800 flex justify-between items-center">
                        <div>
                            <h2 className="text-base font-bold text-white">Active Rented Companies</h2>
                            <p className="text-xs text-slate-400">Multi-tenant isolation scopes</p>
                        </div>
                        <Link
                            href="/platform/companies"
                            className="text-xs text-rose-400 hover:text-rose-300 font-semibold"
                        >
                            View All Companies &rarr;
                        </Link>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-800 text-sm">
                            <thead className="bg-slate-950/60 text-slate-400 text-xs uppercase tracking-wider text-left">
                                <tr>
                                    <th className="px-6 py-3 font-semibold">Company Name</th>
                                    <th className="px-6 py-3 font-semibold">Tenant Slug</th>
                                    <th className="px-6 py-3 font-semibold">Status</th>
                                    <th className="px-6 py-3 font-semibold">Users</th>
                                    <th className="px-6 py-3 font-semibold">Card Inventory</th>
                                    <th className="px-6 py-3 font-semibold text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-800/80 text-slate-200">
                                {companies.map((company) => (
                                    <tr key={company.id} className="hover:bg-slate-800/40 transition">
                                        <td className="px-6 py-4 font-semibold text-white">
                                            <div className="flex items-center space-x-2">
                                                <span>{company.name}</span>
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 font-mono text-xs text-slate-400">
                                            {company.slug}
                                        </td>
                                        <td className="px-6 py-4">
                                            <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                                company.status === 'active'
                                                    ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20'
                                                    : 'bg-rose-500/10 text-rose-400 border border-rose-500/20'
                                            }`}>
                                                {company.status}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 text-slate-300">
                                            {company.users_count ?? 0}
                                        </td>
                                        <td className="px-6 py-4 text-slate-300 font-mono">
                                            {company.cards_count ?? 0} cards
                                        </td>
                                        <td className="px-6 py-4 text-right space-x-3">
                                            <Link
                                                href={`/c/${company.slug}/admin`}
                                                className="text-xs text-indigo-400 hover:text-indigo-300 font-medium hover:underline"
                                            >
                                                Enter Admin &rarr;
                                            </Link>
                                            <Link
                                                href={`/c/${company.slug}/dashboard`}
                                                className="text-xs text-amber-400 hover:text-amber-300 font-medium hover:underline"
                                            >
                                                Player Hub &rarr;
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </PlatformOwnerLayout>
    );
}
