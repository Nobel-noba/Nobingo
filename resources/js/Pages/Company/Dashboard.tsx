import CompanyAdminLayout from '@/Layouts/CompanyAdminLayout';
import { PageProps } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';

interface RecentGame {
    id: number;
    game_number: string;
    name: string;
    status: string;
    entry_fee: number;
    created_at: string;
}

interface RecentWinner {
    id: number;
    prize_amount: number;
    is_walkin?: boolean;
    user?: {
        id: number;
        name: string;
        email: string;
    } | null;
    game?: {
        id: number;
        game_number: string;
        name: string;
    } | null;
}

interface Props extends PageProps {
    stats: {
        total_cards: number;
        total_templates: number;
        active_games: number;
        total_players: number;
        total_games: number;
        credit_balance: number;
        formatted_credit_balance: string;
        net_revenue: number;
        formatted_net_revenue: string;
        total_pots: number;
        formatted_total_pots: string;
        total_winner_payouts: number;
        formatted_winner_payouts: string;
        total_gross_house: number;
        formatted_gross_house: string;
        total_platform_fee: number;
        formatted_platform_fee: string;
        total_net_house: number;
        formatted_net_house: string;
        pending_player_deposits_count: number;
    };
    recent_games?: RecentGame[];
    recent_winners?: RecentWinner[];
}

export default function CompanyDashboard({ stats, recent_games = [], recent_winners = [] }: Props) {
    const { tenant } = usePage<PageProps>().props;
    const companySlug = tenant?.slug || '';

    const hasLowCredit = stats.credit_balance <= 0;

    return (
        <CompanyAdminLayout
            header={
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-bold text-white tracking-tight">Company Administration Hub</h1>
                        <p className="text-xs text-neutral-400 mt-0.5">
                            Manage fixed card inventory, live game rooms, player deposits, and revenue splits
                        </p>
                    </div>
                    <div className="flex items-center gap-3">
                        <Link
                            href={`/c/${companySlug}/admin/credits`}
                            className="px-3.5 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-neutral-950 font-bold text-xs flex items-center gap-1.5 transition shadow-sm"
                        >
                            <span>💳</span> Platform Credits: {stats.formatted_credit_balance}
                        </Link>
                        <Link
                            href={`/c/${companySlug}/admin/games`}
                            className="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs transition shadow-sm"
                        >
                            + Launch Game Room
                        </Link>
                    </div>
                </div>
            }
        >
            <Head title="Company Admin Dashboard" />

            <div className="space-y-6">
                {/* Zero Credit Warning Alert */}
                {hasLowCredit && (
                    <div className="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/30 flex items-center justify-between shadow-sm">
                        <div className="flex items-center gap-3">
                            <span className="text-2xl">⚠️</span>
                            <div>
                                <h3 className="text-sm font-bold text-rose-300">
                                    Insufficient Platform Credits ({stats.formatted_credit_balance})
                                </h3>
                                <p className="text-xs text-rose-400/80">
                                    Your company must have positive platform credit before activating or starting any game rooms.
                                </p>
                            </div>
                        </div>
                        <Link
                            href={`/c/${companySlug}/admin/credits`}
                            className="px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs transition"
                        >
                            Buy Platform Credits &rarr;
                        </Link>
                    </div>
                )}

                {/* Pending Player Deposits Alert */}
                {stats.pending_player_deposits_count > 0 && (
                    <div className="p-4 rounded-2xl bg-amber-500/10 border border-amber-500/30 flex items-center justify-between shadow-sm">
                        <div className="flex items-center gap-3">
                            <span className="text-2xl">💵</span>
                            <div>
                                <h3 className="text-sm font-bold text-amber-300">
                                    {stats.pending_player_deposits_count} Pending Player Deposit {stats.pending_player_deposits_count === 1 ? 'Receipt' : 'Receipts'}
                                </h3>
                                <p className="text-xs text-amber-400/80">
                                    Players transferred money and submitted receipt proof. Review and approve to credit their player wallets.
                                </p>
                            </div>
                        </div>
                        <Link
                            href={`/c/${companySlug}/admin/deposit-requests`}
                            className="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-neutral-950 font-bold text-xs transition"
                        >
                            Review Player Receipts &rarr;
                        </Link>
                    </div>
                )}

                {/* Financial Treasury & Pot Metrics */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div className="bg-neutral-900 border border-neutral-800 rounded-2xl p-5 shadow-sm">
                        <div className="text-xs font-semibold text-amber-400 uppercase tracking-wider flex items-center justify-between">
                            <span>Platform Credit Balance</span>
                            <span className="text-base">💳</span>
                        </div>
                        <div className="text-3xl font-black text-amber-400 font-mono mt-2">{stats.formatted_credit_balance}</div>
                        <div className="text-xs text-neutral-400 mt-1">Pre-paid for platform fees</div>
                    </div>

                    <div className="bg-neutral-900 border border-neutral-800 rounded-2xl p-5 shadow-sm">
                        <div className="text-xs font-semibold text-emerald-400 uppercase tracking-wider flex items-center justify-between">
                            <span>Net House Earnings</span>
                            <span className="text-base">📈</span>
                        </div>
                        <div className="text-3xl font-black text-emerald-400 font-mono mt-2">{stats.formatted_net_house}</div>
                        <div className="text-xs text-neutral-400 mt-1">Gross house minus platform fee</div>
                    </div>

                    <div className="bg-neutral-900 border border-neutral-800 rounded-2xl p-5 shadow-sm">
                        <div className="text-xs font-semibold text-indigo-400 uppercase tracking-wider flex items-center justify-between">
                            <span>Total Pots Collected</span>
                            <span className="text-base">🎱</span>
                        </div>
                        <div className="text-3xl font-black text-white font-mono mt-2">{stats.formatted_total_pots}</div>
                        <div className="text-xs text-neutral-400 mt-1">Across all completed games</div>
                    </div>

                    <div className="bg-neutral-900 border border-neutral-800 rounded-2xl p-5 shadow-sm">
                        <div className="text-xs font-semibold text-rose-400 uppercase tracking-wider flex items-center justify-between">
                            <span>Platform Fees Paid</span>
                            <span className="text-base">🏷️</span>
                        </div>
                        <div className="text-3xl font-black text-rose-400 font-mono mt-2">{stats.formatted_platform_fee}</div>
                        <div className="text-xs text-neutral-400 mt-1">Debited on game completion</div>
                    </div>
                </div>

                {/* Operations & Inventory Grid */}
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div className="bg-neutral-900/60 border border-neutral-800/80 rounded-xl p-4">
                        <div className="text-[11px] font-medium text-neutral-400 uppercase">Fixed Card Inventory</div>
                        <div className="text-2xl font-black text-white mt-1">{stats.total_cards}</div>
                        <div className="text-[11px] text-indigo-400 mt-0.5">Permanent assets</div>
                    </div>

                    <div className="bg-neutral-900/60 border border-neutral-800/80 rounded-xl p-4">
                        <div className="text-[11px] font-medium text-neutral-400 uppercase">Winning Patterns</div>
                        <div className="text-2xl font-black text-white mt-1">{stats.total_templates}</div>
                        <div className="text-[11px] text-neutral-400 mt-0.5">Templates configured</div>
                    </div>

                    <div className="bg-neutral-900/60 border border-neutral-800/80 rounded-xl p-4">
                        <div className="text-[11px] font-medium text-neutral-400 uppercase">Active Games</div>
                        <div className="text-2xl font-black text-amber-400 mt-1">{stats.active_games}</div>
                        <div className="text-[11px] text-neutral-400 mt-0.5">Live calling rooms</div>
                    </div>

                    <div className="bg-neutral-900/60 border border-neutral-800/80 rounded-xl p-4">
                        <div className="text-[11px] font-medium text-neutral-400 uppercase">Registered Players</div>
                        <div className="text-2xl font-black text-emerald-400 mt-1">{stats.total_players}</div>
                        <div className="text-[11px] text-neutral-400 mt-0.5">Customer accounts</div>
                    </div>
                </div>

                {/* Quick Navigation Cards */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <Link
                        href={`/c/${companySlug}/admin/deposit-requests`}
                        className="p-5 rounded-2xl bg-neutral-900 border border-neutral-800 hover:border-indigo-500/50 transition group flex flex-col justify-between space-y-3"
                    >
                        <div>
                            <div className="text-sm font-bold text-white group-hover:text-indigo-400 transition flex items-center gap-2">
                                <span>📥</span> Player Deposits & Direct Top-ups
                            </div>
                            <p className="text-xs text-neutral-400 mt-1 leading-relaxed">
                                Review player bank slips, approve wallet deposits, or perform direct manual top-ups for counter cash players.
                            </p>
                        </div>
                        <span className="text-xs text-indigo-400 font-semibold group-hover:underline">
                            Manage Player Deposits &rarr;
                        </span>
                    </Link>

                    <Link
                        href={`/c/${companySlug}/admin/payment-accounts`}
                        className="p-5 rounded-2xl bg-neutral-900 border border-neutral-800 hover:border-emerald-500/50 transition group flex flex-col justify-between space-y-3"
                    >
                        <div>
                            <div className="text-sm font-bold text-white group-hover:text-emerald-400 transition flex items-center gap-2">
                                <span>🏦</span> Company Payment Accounts
                            </div>
                            <p className="text-xs text-neutral-400 mt-1 leading-relaxed">
                                Configure your company's bank accounts, Telebirr, CBE, or mobile payment numbers shown to players for deposits.
                            </p>
                        </div>
                        <span className="text-xs text-emerald-400 font-semibold group-hover:underline">
                            Manage Payment Methods &rarr;
                        </span>
                    </Link>

                    <Link
                        href={`/c/${companySlug}/admin/credits`}
                        className="p-5 rounded-2xl bg-neutral-900 border border-neutral-800 hover:border-amber-500/50 transition group flex flex-col justify-between space-y-3"
                    >
                        <div>
                            <div className="text-sm font-bold text-white group-hover:text-amber-400 transition flex items-center gap-2">
                                <span>💳</span> Buy Platform Credits
                            </div>
                            <p className="text-xs text-neutral-400 mt-1 leading-relaxed">
                                View platform owner bank accounts, transfer funds, and upload payment receipts to top up your company credit balance.
                            </p>
                        </div>
                        <span className="text-xs text-amber-400 font-semibold group-hover:underline">
                            Purchase Credits &rarr;
                        </span>
                    </Link>
                </div>

                {/* Architecture Invariants Banner */}
                <div className="bg-neutral-900 border border-neutral-800 rounded-2xl p-6 shadow-sm">
                    <h2 className="text-base font-bold text-white mb-2 flex items-center space-x-2">
                        <span className="h-2.5 w-2.5 rounded-full bg-emerald-500 inline-block animate-pulse"></span>
                        <span>Multi-Tenant Bingo Operational Guarantees</span>
                    </h2>
                    <p className="text-xs text-neutral-300 leading-relaxed max-w-3xl">
                        Every bingo game under your company operates with pre-generated fixed card inventories.
                        Card assignment supports both registered online players and cash walk-ins (identified strictly by card number).
                        Winning pot calculations automatically reserve the winner percentage and debit the platform fee directly from your platform credit balance.
                    </p>
                </div>
            </div>
        </CompanyAdminLayout>
    );
}
