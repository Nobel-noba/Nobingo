import CompanyAdminLayout from '@/Layouts/CompanyAdminLayout';
import { PageProps } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

interface WinnerItem {
    id: number;
    game_id: number;
    winning_call_sequence: number;
    winning_ball_number: number;
    claim_type: string;
    payout_amount: number;
    split_ratio: number;
    payout_status: string;
    claimed_at: string;
    winning_patterns_snapshot: Array<{ id: number; name: string; slug: string }>;
    game?: {
        id: number;
        game_number: number;
        name: string;
    };
    user?: {
        id: number;
        name: string;
        email: string;
    };
    card?: {
        id: number;
        card?: {
            card_number: string;
        };
    };
    pattern?: {
        name: string;
    };
}

interface Props extends PageProps {
    winners: {
        data: WinnerItem[];
        current_page: number;
        last_page: number;
        total: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    games: Array<{
        id: number;
        game_number: number;
        name: string;
    }>;
    filters: {
        game_id?: string;
        search?: string;
    };
    stats: {
        total_winners: number;
        total_payout: number;
        manual_claims: number;
        automatic_claims: number;
    };
}

export default function WinnersIndex({ winners, games, filters, stats, tenant }: Props) {
    const companySlug = tenant?.slug || 'default';
    const [search, setSearch] = useState(filters.search || '');
    const [selectedGame, setSelectedGame] = useState(filters.game_id || '');

    const handleFilter = (gameId: string, searchVal: string) => {
        router.get(
            `/c/${companySlug}/admin/winners`,
            {
                game_id: gameId || undefined,
                search: searchVal || undefined,
            },
            { preserveState: true }
        );
    };

    return (
        <CompanyAdminLayout
            header={
                <div>
                    <h1 className="text-xl font-bold text-white tracking-tight">Verified Winners Ledger</h1>
                    <p className="text-xs text-neutral-400 mt-0.5">
                        Authoritative record of validated Bingo claims, pattern snapshots, and prize payouts
                    </p>
                </div>
            }
        >
            <Head title="Winners Ledger" />

            {/* Quick Stats */}
            <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div className="bg-neutral-900 border border-neutral-800 rounded-xl p-4">
                    <div className="text-xs text-neutral-400 uppercase font-semibold">Total Verified Wins</div>
                    <div className="text-2xl font-black text-amber-400 mt-1">{stats.total_winners}</div>
                </div>
                <div className="bg-neutral-900 border border-neutral-800 rounded-xl p-4">
                    <div className="text-xs text-neutral-400 uppercase font-semibold">Total Payouts</div>
                    <div className="text-2xl font-black text-emerald-400 mt-1">
                        ${(stats.total_payout / 100).toFixed(2)}
                    </div>
                </div>
                <div className="bg-neutral-900 border border-neutral-800 rounded-xl p-4">
                    <div className="text-xs text-neutral-400 uppercase font-semibold">Manual Claims</div>
                    <div className="text-2xl font-black text-indigo-400 mt-1">{stats.manual_claims}</div>
                </div>
                <div className="bg-neutral-900 border border-neutral-800 rounded-xl p-4">
                    <div className="text-xs text-neutral-400 uppercase font-semibold">Automatic Detections</div>
                    <div className="text-2xl font-black text-sky-400 mt-1">{stats.automatic_claims}</div>
                </div>
            </div>

            {/* Filter Bar */}
            <div className="bg-neutral-900 border border-neutral-800 rounded-xl p-4 mb-6 flex flex-col md:flex-row gap-3 items-center justify-between">
                <div className="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
                    <select
                        value={selectedGame}
                        onChange={(e) => {
                            setSelectedGame(e.target.value);
                            handleFilter(e.target.value, search);
                        }}
                        className="bg-neutral-950 border border-neutral-800 rounded-lg text-xs text-neutral-200 px-3 py-2 focus:ring-1 focus:ring-indigo-500"
                    >
                        <option value="">All Games</option>
                        {games.map((g) => (
                            <option key={g.id} value={g.id}>
                                Game #{g.game_number}: {g.name}
                            </option>
                        ))}
                    </select>

                    <input
                        type="text"
                        placeholder="Search player name or email..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter') {
                                handleFilter(selectedGame, search);
                            }
                        }}
                        className="bg-neutral-950 border border-neutral-800 rounded-lg text-xs text-neutral-200 px-3 py-2 w-full sm:w-64 focus:ring-1 focus:ring-indigo-500"
                    />
                </div>

                <div className="flex gap-2">
                    <button
                        type="button"
                        onClick={() => handleFilter(selectedGame, search)}
                        className="bg-neutral-800 hover:bg-neutral-700 text-neutral-200 text-xs px-3 py-2 rounded-lg font-medium transition"
                    >
                        Filter
                    </button>
                    {(selectedGame || search) && (
                        <button
                            type="button"
                            onClick={() => {
                                setSelectedGame('');
                                setSearch('');
                                handleFilter('', '');
                            }}
                            className="text-xs text-neutral-400 hover:text-white px-2 py-2"
                        >
                            Reset
                        </button>
                    )}
                </div>
            </div>

            {/* Winners Table */}
            <div className="bg-neutral-900 border border-neutral-800 rounded-xl overflow-hidden shadow-sm">
                <table className="w-full text-left text-sm text-neutral-300">
                    <thead className="bg-neutral-950 text-xs uppercase text-neutral-400 border-b border-neutral-800">
                        <tr>
                            <th className="py-3 px-4">Game</th>
                            <th className="py-3 px-4">Winner</th>
                            <th className="py-3 px-4">Card #</th>
                            <th className="py-3 px-4">Completed Pattern(s)</th>
                            <th className="py-3 px-4">Winning Ball</th>
                            <th className="py-3 px-4">Claim Type</th>
                            <th className="py-3 px-4">Payout</th>
                            <th className="py-3 px-4">Claimed At</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-neutral-800/60 font-sans">
                        {winners.data.length === 0 ? (
                            <tr>
                                <td colSpan={8} className="py-8 text-center text-neutral-500 text-sm">
                                    No winning claims recorded yet.
                                </td>
                            </tr>
                        ) : (
                            winners.data.map((winner) => {
                                const patterns = winner.winning_patterns_snapshot || [];
                                const patternNames = patterns.map((p) => p.name).join(', ') || winner.pattern?.name || 'Standard Line';

                                return (
                                    <tr key={winner.id} className="hover:bg-neutral-800/40 transition">
                                        <td className="py-3 px-4">
                                            <div className="font-bold text-white text-xs">
                                                Game #{winner.game?.game_number ?? winner.game_id}
                                            </div>
                                            <div className="text-[10px] text-neutral-400 truncate max-w-[140px]">
                                                {winner.game?.name}
                                            </div>
                                        </td>
                                        <td className="py-3 px-4">
                                            <div className="font-semibold text-white text-xs">
                                                {winner.user?.name ?? 'Player'}
                                            </div>
                                            <div className="text-[10px] text-neutral-400">
                                                {winner.user?.email}
                                            </div>
                                        </td>
                                        <td className="py-3 px-4">
                                            <span className="font-mono text-xs font-bold text-indigo-400">
                                                {winner.card?.card?.card_number ?? `#${winner.id}`}
                                            </span>
                                        </td>
                                        <td className="py-3 px-4">
                                            <span className="inline-block bg-amber-500/10 border border-amber-500/20 text-amber-300 text-xs px-2 py-0.5 rounded font-medium">
                                                {patternNames}
                                            </span>
                                        </td>
                                        <td className="py-3 px-4">
                                            <div className="flex items-center space-x-1.5">
                                                <span className="h-6 w-6 rounded-full bg-indigo-600 text-white font-bold text-xs flex items-center justify-center shadow-sm">
                                                    {winner.winning_ball_number}
                                                </span>
                                                <span className="text-[10px] text-neutral-400 font-mono">
                                                    call #{winner.winning_call_sequence}
                                                </span>
                                            </div>
                                        </td>
                                        <td className="py-3 px-4">
                                            <span
                                                className={`text-[10px] uppercase font-bold px-2 py-0.5 rounded ${
                                                    winner.claim_type === 'automatic'
                                                        ? 'bg-sky-500/10 text-sky-400 border border-sky-500/20'
                                                        : 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20'
                                                }`}
                                            >
                                                {winner.claim_type}
                                            </span>
                                        </td>
                                        <td className="py-3 px-4">
                                            <div className="font-black text-emerald-400 text-xs">
                                                ${(winner.payout_amount / 100).toFixed(2)}
                                            </div>
                                            {winner.split_ratio < 1 && (
                                                <div className="text-[9px] text-neutral-500">
                                                    Split {(winner.split_ratio * 100).toFixed(0)}%
                                                </div>
                                            )}
                                        </td>
                                        <td className="py-3 px-4 text-xs text-neutral-400">
                                            {new Date(winner.claimed_at).toLocaleString()}
                                        </td>
                                    </tr>
                                );
                            })
                        )}
                    </tbody>
                </table>
            </div>
        </CompanyAdminLayout>
    );
}
