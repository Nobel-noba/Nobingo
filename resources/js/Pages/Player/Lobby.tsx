import PlayerLayout from '@/Layouts/PlayerLayout';
import { PageProps } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect } from 'react';

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
        router.post(`/c/${companySlug}/games/${gameId}/join`);
    };

    return (
        <PlayerLayout
            header={
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-extrabold text-white tracking-tight">
                            Multiplayer Bingo Lobby
                        </h1>
                        <p className="text-xs text-slate-400 mt-0.5">
                            Join open game rooms with fixed-card allocation
                        </p>
                    </div>

                    <Link
                        href={`/c/${companySlug}/dashboard`}
                        className="text-xs text-slate-400 hover:text-white"
                    >
                        &larr; Back to Player Hub
                    </Link>
                </div>
            }
        >
            <Head title="Bingo Lobby" />

            {/* Games Grid */}
            <div className="space-y-4">
                {games.length === 0 ? (
                    <div className="bg-slate-950/60 border border-slate-800 rounded-3xl p-12 text-center">
                        <div className="h-12 w-12 rounded-2xl bg-indigo-500/10 text-indigo-400 flex items-center justify-center font-bold text-xl mx-auto mb-3">
                            75
                        </div>
                        <h2 className="text-base font-bold text-white">No Open Rooms at the Moment</h2>
                        <p className="text-xs text-slate-400 mt-1 max-w-md mx-auto">
                            The game manager will schedule new bingo rooms shortly. Check back in a few minutes or contact support.
                        </p>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        {games.map((game) => (
                            <div
                                key={game.id}
                                className="bg-slate-950/80 border border-slate-800/90 rounded-3xl p-6 flex flex-col justify-between shadow-xl hover:border-slate-700 transition"
                            >
                                <div>
                                    <div className="flex justify-between items-start mb-3">
                                        <span className="text-[10px] font-mono text-slate-400 uppercase font-semibold">
                                            Game #{game.game_number}
                                        </span>
                                        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider ${
                                            game.status === 'open'
                                                ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20'
                                                : game.status === 'active'
                                                ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20 animate-pulse'
                                                : 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/20'
                                        }`}>
                                            {game.status}
                                        </span>
                                    </div>

                                    <h3 className="text-lg font-black text-white">{game.name}</h3>
                                    <p className="text-xs text-slate-400 mt-1 line-clamp-2">
                                        {game.description || 'Live 75-ball multiplayer match'}
                                    </p>

                                    <div className="mt-5 space-y-2 pt-4 border-t border-slate-800/80 text-xs">
                                        <div className="flex justify-between">
                                            <span className="text-slate-400">Winning Rule:</span>
                                            <span className="font-bold text-amber-400">
                                                {game.required_pattern_count} {game.required_pattern_count === 1 ? 'Line' : 'Lines'} Required
                                            </span>
                                        </div>

                                        <div className="flex justify-between">
                                            <span className="text-slate-400">Entry Fee:</span>
                                            <span className="font-bold text-white">
                                                {game.formatted_entry_fee}
                                            </span>
                                        </div>

                                        <div className="flex justify-between">
                                            <span className="text-slate-400">Players in Room:</span>
                                            <span className="font-bold text-indigo-400">
                                                {game.players_count} / {game.max_players}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div className="mt-6">
                                    {game.has_joined ? (
                                        <Link
                                            href={`/c/${companySlug}/game/${game.id}`}
                                            className="w-full block text-center bg-emerald-600 hover:bg-emerald-500 text-white font-extrabold text-xs py-3 rounded-2xl shadow-lg shadow-emerald-600/20 transition"
                                        >
                                            Enter Live Room &rarr;
                                        </Link>
                                    ) : (
                                        <button
                                            onClick={() => handleJoin(game.id)}
                                            className="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-extrabold text-xs py-3 rounded-2xl shadow-lg shadow-indigo-600/20 transition flex items-center justify-center space-x-2"
                                        >
                                            <span>Join Game</span>
                                            <span className="opacity-75">({game.formatted_entry_fee})</span>
                                        </button>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </PlayerLayout>
    );
}
