import PlayerLayout from '@/Layouts/PlayerLayout';
import { PageProps } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import React, { useState, useEffect } from 'react';

interface LobbyGameItem {
    id: number;
    game_number: number;
    name: string;
    description: string | null;
    status: string;
    entry_fee: number;
    formatted_entry_fee: string;
    players_count: number;
    max_players: number;
    template_name: string;
    required_pattern_count: number;
    has_joined: boolean;
}

interface Props extends PageProps {
    games: LobbyGameItem[];
}

export default function PlayerLobby({ games, tenant }: Props) {
    const companySlug = tenant?.slug || 'default';
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState<'all' | 'open' | 'active'>('all');
    const [joiningId, setJoiningId] = useState<number | null>(null);

    useEffect(() => {
        if (!window.Echo || !tenant?.id) return;

        const channelName = `company.${tenant.id}.lobby`;
        const channel = window.Echo.private(channelName);

        channel.listen('.game.state.changed', () => {
            router.reload({ only: ['games'] });
        });

        channel.listen('.player.joined', () => {
            router.reload({ only: ['games'] });
        });

        return () => {
            channel.stopListening('.game.state.changed');
            channel.stopListening('.player.joined');
            window.Echo.leave(channelName);
        };
    }, [tenant?.id]);

    const handleJoin = (gameId: number) => {
        if (joiningId !== null) return;
        setJoiningId(gameId);
        router.post(
            `/c/${companySlug}/games/${gameId}/join`,
            {},
            {
                onFinish: () => setJoiningId(null),
            }
        );
    };

    // Filter games by search keyword and status filter
    const filteredGames = games.filter((game) => {
        const matchesSearch =
            game.name.toLowerCase().includes(search.toLowerCase()) ||
            game.game_number.toString().includes(search) ||
            game.template_name.toLowerCase().includes(search.toLowerCase());

        const matchesStatus =
            statusFilter === 'all' ||
            (statusFilter === 'open' && game.status === 'open') ||
            (statusFilter === 'active' && game.status === 'active');

        return matchesSearch && matchesStatus;
    });

    const openCount = games.filter((g) => g.status === 'open').length;
    const activeCount = games.filter((g) => g.status === 'active').length;

    return (
        <PlayerLayout
            header={
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-extrabold text-white tracking-tight">
                            Multiplayer Bingo Lobby
                        </h1>
                        <p className="text-xs text-slate-400 mt-0.5">
                            Join live 75-ball game rooms with permanent fixed cards
                        </p>
                    </div>

                    <Link
                        href={`/c/${companySlug}/dashboard`}
                        className="text-xs text-slate-400 hover:text-white transition flex items-center gap-1"
                    >
                        <span>&larr;</span>
                        <span>Back to Dashboard</span>
                    </Link>
                </div>
            }
        >
            <Head title="Bingo Lobby — Available Rooms" />

            <div className="space-y-6">
                {/* Search & Status Filters Bar */}
                <div className="bg-slate-950 border border-slate-800 rounded-2xl p-4 flex flex-col md:flex-row md:items-center justify-between gap-4 shadow-sm">
                    {/* Status Filter Tabs */}
                    <div className="flex items-center space-x-2">
                        <button
                            type="button"
                            onClick={() => setStatusFilter('all')}
                            className={`px-3.5 py-1.5 rounded-xl text-xs font-bold transition ${
                                statusFilter === 'all'
                                    ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20'
                                    : 'bg-slate-900 text-slate-400 hover:text-white'
                            }`}
                        >
                            All Rooms ({games.length})
                        </button>
                        <button
                            type="button"
                            onClick={() => setStatusFilter('open')}
                            className={`px-3.5 py-1.5 rounded-xl text-xs font-bold transition flex items-center space-x-1.5 ${
                                statusFilter === 'open'
                                    ? 'bg-emerald-600 text-white shadow-md shadow-emerald-600/20'
                                    : 'bg-slate-900 text-slate-400 hover:text-white'
                            }`}
                        >
                            <span className="h-1.5 w-1.5 rounded-full bg-emerald-400" />
                            <span>Open to Join ({openCount})</span>
                        </button>
                        <button
                            type="button"
                            onClick={() => setStatusFilter('active')}
                            className={`px-3.5 py-1.5 rounded-xl text-xs font-bold transition flex items-center space-x-1.5 ${
                                statusFilter === 'active'
                                    ? 'bg-amber-600 text-white shadow-md shadow-amber-600/20'
                                    : 'bg-slate-900 text-slate-400 hover:text-white'
                            }`}
                        >
                            <span className="h-1.5 w-1.5 rounded-full bg-amber-400 animate-pulse" />
                            <span>In-Progress ({activeCount})</span>
                        </button>
                    </div>

                    {/* Search Input */}
                    <div className="w-full md:w-72">
                        <input
                            type="text"
                            placeholder="Search rooms or match #..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-3.5 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition"
                        />
                    </div>
                </div>

                {/* Rooms Grid */}
                {filteredGames.length === 0 ? (
                    <div className="bg-slate-950/60 border border-slate-800 rounded-3xl p-12 text-center">
                        <div className="h-12 w-12 rounded-2xl bg-indigo-500/10 text-indigo-400 flex items-center justify-center font-black text-xl mx-auto mb-3">
                            75
                        </div>
                        <h2 className="text-base font-bold text-white">No Matching Rooms Found</h2>
                        <p className="text-xs text-slate-400 mt-1 max-w-md mx-auto">
                            {search
                                ? 'No bingo rooms matched your query. Try clearing your search filters.'
                                : 'There are no active rooms in this category right now. New rooms will appear as scheduled.'}
                        </p>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        {filteredGames.map((game) => {
                            const capacityPercent = Math.min(100, Math.round((game.players_count / (game.max_players || 1)) * 100));

                            return (
                                <div
                                    key={game.id}
                                    className="bg-slate-950/90 border border-slate-800/90 rounded-3xl p-6 flex flex-col justify-between shadow-xl hover:border-slate-700 transition"
                                >
                                    <div>
                                        <div className="flex justify-between items-start mb-3">
                                            <span className="text-[10px] font-mono text-indigo-400 uppercase font-bold tracking-wider">
                                                Game #{game.game_number}
                                            </span>
                                            <span
                                                className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider ${
                                                    game.status === 'open'
                                                        ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20'
                                                        : game.status === 'active'
                                                        ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20 animate-pulse'
                                                        : 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/20'
                                                }`}
                                            >
                                                {game.status}
                                            </span>
                                        </div>

                                        <h3 className="text-lg font-black text-white">{game.name}</h3>
                                        <p className="text-xs text-slate-400 mt-1 line-clamp-2">
                                            {game.description || `${game.template_name} &bull; 75-Ball Fixed Card Match`}
                                        </p>

                                        {/* Room Invariants & Specs */}
                                        <div className="mt-5 space-y-2.5 pt-4 border-t border-slate-800/80 text-xs">
                                            <div className="flex justify-between items-center">
                                                <span className="text-slate-400">Winning Rule:</span>
                                                <span className="font-bold text-amber-400">
                                                    {game.required_pattern_count} {game.required_pattern_count === 1 ? 'Line' : 'Lines'}
                                                </span>
                                            </div>

                                            <div className="flex justify-between items-center">
                                                <span className="text-slate-400">Entry Ticket:</span>
                                                <span className="font-black text-white">
                                                    {game.formatted_entry_fee}
                                                </span>
                                            </div>

                                            {/* Capacity Progress Meter */}
                                            <div className="space-y-1 pt-1">
                                                <div className="flex justify-between items-center text-[11px]">
                                                    <span className="text-slate-400">Enrolled Players:</span>
                                                    <span className="font-bold text-indigo-300">
                                                        {game.players_count} / {game.max_players}
                                                    </span>
                                                </div>
                                                <div className="w-full bg-slate-900 rounded-full h-1.5 overflow-hidden">
                                                    <div
                                                        className={`h-full rounded-full transition-all duration-300 ${
                                                            capacityPercent >= 90
                                                                ? 'bg-rose-500'
                                                                : capacityPercent >= 60
                                                                ? 'bg-amber-500'
                                                                : 'bg-indigo-500'
                                                        }`}
                                                        style={{ width: `${capacityPercent}%` }}
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Action Button */}
                                    <div className="mt-6">
                                        {game.has_joined ? (
                                            <Link
                                                href={`/c/${companySlug}/game/${game.id}`}
                                                className="w-full block text-center bg-emerald-600 hover:bg-emerald-500 text-white font-black text-xs py-3.5 rounded-2xl shadow-lg shadow-emerald-600/20 transition active:scale-95"
                                            >
                                                Enter Game Room &rarr;
                                            </Link>
                                        ) : game.status === 'open' ? (
                                            <button
                                                type="button"
                                                onClick={() => handleJoin(game.id)}
                                                disabled={joiningId === game.id || game.players_count >= game.max_players}
                                                className={`w-full font-black text-xs py-3.5 rounded-2xl shadow-lg transition flex items-center justify-center space-x-2 active:scale-95 ${
                                                    game.players_count >= game.max_players
                                                        ? 'bg-slate-800 text-slate-500 cursor-not-allowed shadow-none'
                                                        : 'bg-indigo-600 hover:bg-indigo-500 text-white shadow-indigo-600/20 cursor-pointer'
                                                }`}
                                            >
                                                {joiningId === game.id ? (
                                                    <span>Assigning Fixed Card...</span>
                                                ) : game.players_count >= game.max_players ? (
                                                    <span>Room Full</span>
                                                ) : (
                                                    <>
                                                        <span>Join Match</span>
                                                        <span className="opacity-75">({game.formatted_entry_fee})</span>
                                                    </>
                                                )}
                                            </button>
                                        ) : (
                                            <div className="w-full text-center py-3 bg-slate-900 text-slate-500 text-xs font-semibold rounded-2xl border border-slate-800">
                                                Match In Progress
                                            </div>
                                        )}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>
        </PlayerLayout>
    );
}
