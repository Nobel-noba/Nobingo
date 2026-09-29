import CompanyAdminLayout from '@/Layouts/CompanyAdminLayout';
import { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

interface Props extends PageProps {
    company: {
        id: number;
        name: string;
        slug: string;
    };
    player: {
        id: number;
        name: string;
        email: string;
        status: string;
        balance: number;
        formatted_balance: string;
        created_at: string;
    };
    recent_games: Array<{
        game_id: number;
        game_number: number;
        name: string;
        status: string;
        entry_fee_paid: number;
        joined_at: string;
    }>;
    wins: Array<{
        id: number;
        game_number: number;
        game_name: string;
        pattern_name: string;
        payout_amount: number;
        formatted_payout: string;
        payout_status: string;
        claimed_at: string;
    }>;
    transactions: Array<{
        id: number;
        type: string;
        amount: number;
        formatted_amount: string;
        balance_after: number;
        formatted_balance_after: string;
        reference_code: string | null;
        description: string | null;
        created_at: string;
    }>;
}

export default function PlayerShow({ auth, company, player, recent_games, wins, transactions }: Props) {
    const [activeTab, setActiveTab] = useState<'games' | 'wins' | 'transactions'>('games');

    return (
        <CompanyAdminLayout>
            <Head title={`Player Profile: ${player.name} - ${company.name}`} />

            <div className="space-y-6">
                {/* Back Link & Header */}
                <div className="flex items-center justify-between">
                    <Link
                        href={`/c/${company.slug}/admin/players`}
                        className="text-xs text-neutral-400 hover:text-white flex items-center transition"
                    >
                        ← Back to Player Directory
                    </Link>
                </div>

                {/* Player Profile Banner */}
                <div className="bg-neutral-900 border border-neutral-800 rounded-2xl p-6 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div>
                        <div className="flex items-center space-x-3">
                            <h1 className="text-2xl font-bold text-white tracking-tight">{player.name}</h1>
                            <span
                                className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border ${
                                    player.status === 'active'
                                        ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20'
                                        : 'bg-rose-500/10 text-rose-400 border-rose-500/20'
                                }`}
                            >
                                {player.status.toUpperCase()}
                            </span>
                        </div>
                        <p className="text-xs text-neutral-400 mt-1">{player.email} • Registered {player.created_at}</p>
                    </div>

                    <div className="flex items-center space-x-4 bg-neutral-950 border border-neutral-800 rounded-xl px-5 py-3">
                        <div>
                            <div className="text-[10px] text-neutral-400 uppercase font-semibold">Wallet Balance</div>
                            <div className="text-2xl font-black font-mono text-emerald-400">{player.formatted_balance}</div>
                        </div>
                    </div>
                </div>

                {/* Dossier Tabs */}
                <div className="border-b border-neutral-800 flex space-x-4">
                    <button
                        onClick={() => setActiveTab('games')}
                        className={`pb-3 text-sm font-semibold transition border-b-2 ${
                            activeTab === 'games'
                                ? 'text-indigo-400 border-indigo-500'
                                : 'text-neutral-400 border-transparent hover:text-white'
                        }`}
                    >
                        Games History ({recent_games.length})
                    </button>
                    <button
                        onClick={() => setActiveTab('wins')}
                        className={`pb-3 text-sm font-semibold transition border-b-2 ${
                            activeTab === 'wins'
                                ? 'text-amber-400 border-amber-500'
                                : 'text-neutral-400 border-transparent hover:text-white'
                        }`}
                    >
                        Wins & Claims ({wins.length})
                    </button>
                    <button
                        onClick={() => setActiveTab('transactions')}
                        className={`pb-3 text-sm font-semibold transition border-b-2 ${
                            activeTab === 'transactions'
                                ? 'text-emerald-400 border-emerald-500'
                                : 'text-neutral-400 border-transparent hover:text-white'
                        }`}
                    >
                        Financial Ledger ({transactions.length})
                    </button>
                </div>

                {/* Tab Content */}
                {activeTab === 'games' && (
                    <div className="bg-neutral-900 border border-neutral-800 rounded-xl overflow-hidden">
                        <table className="w-full text-left text-sm text-neutral-300">
                            <thead className="bg-neutral-950 text-xs uppercase text-neutral-400 font-medium border-b border-neutral-800">
                                <tr>
                                    <th className="px-4 py-3">Game #</th>
                                    <th className="px-4 py-3">Room Name</th>
                                    <th className="px-4 py-3">Status</th>
                                    <th className="px-4 py-3">Entry Fee Paid</th>
                                    <th className="px-4 py-3">Joined At</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-neutral-800">
                                {recent_games.length === 0 ? (
                                    <tr>
                                        <td colSpan={5} className="px-4 py-8 text-center text-neutral-500">
                                            No games played yet.
                                        </td>
                                    </tr>
                                ) : (
                                    recent_games.map((g, idx) => (
                                        <tr key={idx} className="hover:bg-neutral-800/40 transition">
                                            <td className="px-4 py-3 font-mono text-xs text-neutral-400">#{g.game_number}</td>
                                            <td className="px-4 py-3 font-medium text-neutral-200">{g.name}</td>
                                            <td className="px-4 py-3 text-xs text-neutral-400 uppercase">{g.status}</td>
                                            <td className="px-4 py-3 text-xs font-mono font-semibold text-neutral-300">
                                                ${(g.entry_fee_paid / 100).toFixed(2)}
                                            </td>
                                            <td className="px-4 py-3 text-xs text-neutral-400">{g.joined_at}</td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                )}

                {activeTab === 'wins' && (
                    <div className="bg-neutral-900 border border-neutral-800 rounded-xl overflow-hidden">
                        <table className="w-full text-left text-sm text-neutral-300">
                            <thead className="bg-neutral-950 text-xs uppercase text-neutral-400 font-medium border-b border-neutral-800">
                                <tr>
                                    <th className="px-4 py-3">Game #</th>
                                    <th className="px-4 py-3">Game Name</th>
                                    <th className="px-4 py-3">Pattern</th>
                                    <th className="px-4 py-3">Prize Amount</th>
                                    <th className="px-4 py-3">Payout Status</th>
                                    <th className="px-4 py-3">Claimed At</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-neutral-800">
                                {wins.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="px-4 py-8 text-center text-neutral-500">
                                            No Bingo wins recorded yet.
                                        </td>
                                    </tr>
                                ) : (
                                    wins.map((w) => (
                                        <tr key={w.id} className="hover:bg-neutral-800/40 transition">
                                            <td className="px-4 py-3 font-mono text-xs text-neutral-400">#{w.game_number}</td>
                                            <td className="px-4 py-3 font-medium text-neutral-200">{w.game_name}</td>
                                            <td className="px-4 py-3 text-xs text-amber-300">{w.pattern_name}</td>
                                            <td className="px-4 py-3 font-mono font-bold text-amber-400">{w.formatted_payout}</td>
                                            <td className="px-4 py-3 text-xs text-emerald-400 uppercase font-semibold">{w.payout_status}</td>
                                            <td className="px-4 py-3 text-xs text-neutral-400">{w.claimed_at}</td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                )}

                {activeTab === 'transactions' && (
                    <div className="bg-neutral-900 border border-neutral-800 rounded-xl overflow-hidden">
                        <table className="w-full text-left text-sm text-neutral-300">
                            <thead className="bg-neutral-950 text-xs uppercase text-neutral-400 font-medium border-b border-neutral-800">
                                <tr>
                                    <th className="px-4 py-3">Ref Code</th>
                                    <th className="px-4 py-3">Type</th>
                                    <th className="px-4 py-3">Amount</th>
                                    <th className="px-4 py-3">Balance After</th>
                                    <th className="px-4 py-3">Description</th>
                                    <th className="px-4 py-3">Date</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-neutral-800">
                                {transactions.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="px-4 py-8 text-center text-neutral-500">
                                            No wallet transactions on record.
                                        </td>
                                    </tr>
                                ) : (
                                    transactions.map((tx) => (
                                        <tr key={tx.id} className="hover:bg-neutral-800/40 transition">
                                            <td className="px-4 py-3 font-mono text-xs text-neutral-400">{tx.reference_code ?? `#${tx.id}`}</td>
                                            <td className="px-4 py-3 text-xs font-semibold text-neutral-300">{tx.type}</td>
                                            <td className="px-4 py-3 font-mono font-bold text-neutral-200">{tx.formatted_amount}</td>
                                            <td className="px-4 py-3 font-mono text-xs text-neutral-400">{tx.formatted_balance_after}</td>
                                            <td className="px-4 py-3 text-xs text-neutral-300">{tx.description ?? '-'}</td>
                                            <td className="px-4 py-3 text-xs text-neutral-400">{tx.created_at}</td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </CompanyAdminLayout>
    );
}
