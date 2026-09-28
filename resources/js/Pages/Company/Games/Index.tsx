import CompanyAdminLayout from '@/Layouts/CompanyAdminLayout';
import { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';

interface GameItem {
    id: number;
    game_number: number;
    name: string;
    status: string;
    entry_fee: number;
    players_count: number;
    max_players: number;
    template?: {
        name: string;
    };
    created_at: string;
}

interface Props extends PageProps {
    games: {
        data: GameItem[];
        current_page: number;
        last_page: number;
        total: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    stats: {
        total: number;
        open: number;
        active: number;
        completed: number;
    };
}

export default function GameIndex({ games, stats, tenant }: Props) {
    const companySlug = tenant?.slug || 'default';

    return (
        <CompanyAdminLayout
            header={
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-bold text-white tracking-tight">Bingo Games & Rooms</h1>
                        <p className="text-xs text-neutral-400 mt-0.5">
                            Manage scheduled, open, and active multiplayer bingo sessions
                        </p>
                    </div>

                    <Link
                        href={`/c/${companySlug}/admin/games/create`}
                        className="bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold px-4 py-2 rounded-lg shadow-sm transition"
                    >
                        + Create / Schedule Game
                    </Link>
                </div>
            }
        >
            <Head title="Bingo Games" />

            {/* Quick Stats */}
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
                <div className="bg-neutral-900 border border-neutral-800 rounded-xl p-4">
                    <div className="text-[10px] text-neutral-400 uppercase font-semibold">Total Games</div>
                    <div className="text-2xl font-black text-white mt-1">{stats.total}</div>
                </div>
                <div className="bg-neutral-900 border border-neutral-800 rounded-xl p-4">
                    <div className="text-[10px] text-emerald-400 uppercase font-semibold">Open for Joining</div>
                    <div className="text-2xl font-black text-emerald-400 mt-1">{stats.open}</div>
                </div>
                <div className="bg-neutral-900 border border-neutral-800 rounded-xl p-4">
                    <div className="text-[10px] text-amber-400 uppercase font-semibold">Live / Active</div>
                    <div className="text-2xl font-black text-amber-400 mt-1">{stats.active}</div>
                </div>
                <div className="bg-neutral-900 border border-neutral-800 rounded-xl p-4">
                    <div className="text-[10px] text-neutral-400 uppercase font-semibold">Completed</div>
                    <div className="text-2xl font-black text-neutral-300 mt-1">{stats.completed}</div>
                </div>
            </div>

            {/* Games Table */}
            <div className="bg-neutral-900 border border-neutral-800 rounded-xl overflow-hidden shadow-sm">
                <table className="min-w-full divide-y divide-neutral-800 text-sm">
                    <thead className="bg-neutral-950/60 text-neutral-400 text-xs uppercase tracking-wider text-left">
                        <tr>
                            <th className="px-6 py-3 font-semibold">Game #</th>
                            <th className="px-6 py-3 font-semibold">Game Name</th>
                            <th className="px-6 py-3 font-semibold">Template</th>
                            <th className="px-6 py-3 font-semibold">Players</th>
                            <th className="px-6 py-3 font-semibold">Status</th>
                            <th className="px-6 py-3 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-neutral-800/80 text-neutral-200">
                        {games.data.length === 0 ? (
                            <tr>
                                <td colSpan={6} className="px-6 py-12 text-center text-neutral-500 text-xs">
                                    No games created yet. Click "+ Create / Schedule Game" to set up your first room.
                                </td>
                            </tr>
                        ) : (
                            games.data.map((game) => (
                                <tr key={game.id} className="hover:bg-neutral-800/40 transition">
                                    <td className="px-6 py-4 font-mono font-bold text-white">
                                        #{game.game_number}
                                    </td>
                                    <td className="px-6 py-4 font-semibold text-white">
                                        {game.name}
                                    </td>
                                    <td className="px-6 py-4 text-xs text-neutral-400">
                                        {game.template?.name ?? 'Custom Match'}
                                    </td>
                                    <td className="px-6 py-4 text-xs font-semibold text-neutral-300">
                                        {game.players_count} / {game.max_players}
                                    </td>
                                    <td className="px-6 py-4">
                                        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium capitalize ${
                                            game.status === 'open'
                                                ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20'
                                                : game.status === 'active'
                                                ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20'
                                                : game.status === 'starting'
                                                ? 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/20'
                                                : 'bg-neutral-500/10 text-neutral-400 border border-neutral-500/20'
                                        }`}>
                                            {game.status}
                                        </span>
                                    </td>
                                    <td className="px-6 py-4 text-right">
                                        <Link
                                            href={`/c/${companySlug}/admin/games/${game.id}`}
                                            className="text-xs text-indigo-400 hover:text-indigo-300 font-semibold hover:underline"
                                        >
                                            Operator Console &rarr;
                                        </Link>
                                    </td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>
        </CompanyAdminLayout>
    );
}
