import CompanyAdminLayout from '@/Layouts/CompanyAdminLayout';
import { PageProps } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';

interface GamePlayerItem {
    id: number;
    joined_at: string;
    user: {
        id: number;
        name: string;
        email: string;
    };
}

interface GameCardItem {
    id: number;
    assigned_at: string;
    user: {
        name: string;
    };
    card: {
        id: number;
        card_number: number;
    };
    version: {
        version_number: number;
    };
}

interface GameCallItem {
    id: number;
    sequence_index: number;
    ball_number: number;
    letter: string;
    called_at: string;
}

interface BoardCell {
    number: number;
    is_called: boolean;
    sequence_index: number | null;
    called_at: string | null;
}

interface GameDetail {
    id: number;
    game_number: number;
    name: string;
    description: string;
    status: string;
    entry_fee: number;
    currency: string;
    min_players: number;
    max_players: number;
    call_interval: number;
    winner_policy: string;
    configuration_snapshot: {
        template_name?: string;
        required_pattern_count?: number;
        winner_policy?: string;
        call_interval?: number;
    };
    players: GamePlayerItem[];
    cards: GameCardItem[];
    calls: GameCallItem[];
    last_call?: GameCallItem | null;
    created_at: string;
}

interface Props extends PageProps {
    game: GameDetail;
    available_cards_count: number;
    master_board: Record<string, BoardCell[]>;
    remaining_count: number;
}

export default function GameShow({ game, available_cards_count, master_board, remaining_count, tenant }: Props) {
    const companySlug = tenant?.slug || 'default';
    const [calling, setCalling] = useState(false);
    const [autoCallActive, setAutoCallActive] = useState(false);
    const [liveGame, setLiveGame] = useState(game);
    const [liveBoard, setLiveBoard] = useState(master_board);
    const [liveRemaining, setLiveRemaining] = useState(remaining_count);

    useEffect(() => {
        setLiveGame(game);
        setLiveBoard(master_board);
        setLiveRemaining(remaining_count);
    }, [game, master_board, remaining_count]);

    // Live WebSockets listener via Laravel Reverb & Echo
    useEffect(() => {
        if (!window.Echo || !tenant?.id) return;

        const channelName = `company.${tenant.id}.game.${game.id}`;
        const channel = window.Echo.private(channelName);

        channel.listen('.number.called', (event: any) => {
            const newCall = {
                id: Date.now(),
                sequence_index: event.sequence_index,
                ball_number: event.ball_number,
                letter: event.letter,
                called_at: event.called_at,
            };

            setLiveGame((prev) => ({
                ...prev,
                last_call: newCall,
                calls: [...(prev.calls || []), newCall],
            }));

            setLiveRemaining(event.remaining_count);

            // Update board cell state
            setLiveBoard((prev) => {
                const next = { ...prev };
                const letterCells = next[event.letter] || [];
                next[event.letter] = letterCells.map((c) =>
                    c.number === event.ball_number
                        ? { ...c, is_called: true, sequence_index: event.sequence_index }
                        : c
                );
                return next;
            });
        });

        channel.listen('.game.state.changed', (event: any) => {
            setLiveGame((prev) => ({
                ...prev,
                status: event.status,
            }));
        });

        channel.listen('.player.joined', () => {
            router.reload({ only: ['game'] });
        });

        return () => {
            channel.stopListening('.number.called');
            channel.stopListening('.game.state.changed');
            channel.stopListening('.player.joined');
            window.Echo.leave(channelName);
        };
    }, [game.id, tenant?.id]);

    const handleTransition = (targetStatus: string) => {
        if (confirm(`Are you sure you want to transition game to ${targetStatus.toUpperCase()}?`)) {
            router.patch(`/c/${companySlug}/admin/games/${game.id}/status`, {
                status: targetStatus,
            });
        }
    };

    const handleCallNext = () => {
        setCalling(true);
        router.post(
            `/c/${companySlug}/admin/games/${game.id}/call-next`,
            {},
            {
                preserveScroll: true,
                onFinish: () => setCalling(false),
            }
        );
    };

    // Auto-calling timer loop in browser if enabled by operator
    useEffect(() => {
        let timer: NodeJS.Timeout;
        if (autoCallActive && liveGame.status === 'active' && liveRemaining > 0) {
            timer = setTimeout(() => {
                router.post(
                    `/c/${companySlug}/admin/games/${game.id}/call-next`,
                    {},
                    {
                        preserveScroll: true,
                        onError: () => setAutoCallActive(false),
                    }
                );
            }, (liveGame.call_interval || 5) * 1000);
        } else if (liveRemaining === 0 || liveGame.status !== 'active') {
            setAutoCallActive(false);
        }
        return () => clearTimeout(timer);
    }, [autoCallActive, liveGame.status, liveGame.calls.length, liveRemaining]);

    const latestCall = game.last_call || (game.calls && game.calls.length > 0 ? game.calls[game.calls.length - 1] : null);

    const getLetterColor = (letter: string) => {
        switch (letter) {
            case 'B': return 'from-rose-500 to-rose-600 border-rose-400 text-white';
            case 'I': return 'from-amber-500 to-amber-600 border-amber-400 text-white';
            case 'N': return 'from-emerald-500 to-emerald-600 border-emerald-400 text-white';
            case 'G': return 'from-sky-500 to-sky-600 border-sky-400 text-white';
            case 'O': return 'from-purple-500 to-purple-600 border-purple-400 text-white';
            default: return 'from-neutral-700 to-neutral-800 border-neutral-600 text-white';
        }
    };

    return (
        <CompanyAdminLayout
            header={
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div className="flex items-center space-x-3">
                        <Link
                            href={`/c/${companySlug}/admin/games`}
                            className="text-xs text-neutral-400 hover:text-white"
                        >
                            &larr; Back to Games
                        </Link>
                        <span className="text-neutral-600">/</span>
                        <h1 className="text-xl font-bold text-white tracking-tight">
                            Game #{game.game_number}: {game.name}
                        </h1>
                        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold capitalize ${
                            game.status === 'open'
                                ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20'
                                : game.status === 'active'
                                ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20 animate-pulse'
                                : game.status === 'starting'
                                ? 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/20'
                                : 'bg-neutral-500/10 text-neutral-400 border border-neutral-500/20'
                        }`}>
                            {game.status}
                        </span>
                    </div>

                    {/* Operator Control Actions */}
                    <div className="flex items-center space-x-2">
                        {game.status === 'draft' && (
                            <button
                                onClick={() => handleTransition('open')}
                                className="bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold px-4 py-2 rounded-xl shadow-md transition"
                            >
                                Open Room for Players
                            </button>
                        )}

                        {game.status === 'open' && (
                            <>
                                <button
                                    onClick={() => handleTransition('active')}
                                    className="bg-amber-500 hover:bg-amber-400 text-neutral-950 text-xs font-bold px-4 py-2 rounded-xl shadow-md transition"
                                >
                                    Start Live Game
                                </button>
                                <button
                                    onClick={() => handleTransition('cancelled')}
                                    className="bg-neutral-800 hover:bg-rose-900/50 hover:text-rose-300 text-neutral-300 text-xs font-semibold px-3 py-2 rounded-xl transition"
                                >
                                    Cancel Game
                                </button>
                            </>
                        )}

                        {game.status === 'active' && (
                            <>
                                <button
                                    onClick={() => handleTransition('paused')}
                                    className="bg-amber-600 hover:bg-amber-500 text-white text-xs font-bold px-4 py-2 rounded-xl transition"
                                >
                                    Pause Session
                                </button>
                                <button
                                    onClick={() => handleTransition('completed')}
                                    className="bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold px-4 py-2 rounded-xl transition"
                                >
                                    Complete Game
                                </button>
                            </>
                        )}

                        {game.status === 'paused' && (
                            <button
                                onClick={() => handleTransition('active')}
                                className="bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold px-4 py-2 rounded-xl transition"
                            >
                                Resume Session
                            </button>
                        )}
                    </div>
                </div>
            }
        >
            <Head title={`Game #${game.game_number}`} />

            <div className="space-y-6">
                {/* Master Ball Calling Console */}
                <div className="bg-neutral-900 border border-neutral-800 rounded-3xl p-6 shadow-xl">
                    <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-6 pb-6 border-b border-neutral-800">
                        {/* Jumbo Current Ball */}
                        <div className="flex items-center space-x-6">
                            <div className="relative">
                                {latestCall ? (
                                    <div className={`w-24 h-24 rounded-full bg-gradient-to-tr ${getLetterColor(latestCall.letter)} flex flex-col items-center justify-center shadow-2xl border-4 ring-4 ring-white/10 animate-bounce`}>
                                        <span className="text-sm font-black tracking-widest uppercase opacity-90">{latestCall.letter}</span>
                                        <span className="text-3xl font-extrabold leading-none">{latestCall.ball_number}</span>
                                    </div>
                                ) : (
                                    <div className="w-24 h-24 rounded-full bg-neutral-800 border-2 border-dashed border-neutral-700 flex flex-col items-center justify-center text-neutral-500 text-xs font-bold">
                                        <span>NO BALL</span>
                                        <span>CALLED</span>
                                    </div>
                                )}
                            </div>

                            <div>
                                <div className="text-xs font-bold text-neutral-400 uppercase tracking-wider">
                                    Current Draw
                                </div>
                                <div className="text-2xl font-black text-white mt-1">
                                    {latestCall ? `${latestCall.letter}-${latestCall.ball_number}` : 'Awaiting First Ball'}
                                </div>
                                <div className="text-xs text-neutral-400 mt-1 flex items-center space-x-3">
                                    <span>Sequence: <strong className="text-white">#{liveGame.calls?.length || 0} / 75</strong></span>
                                    <span>&bull;</span>
                                    <span>Remaining: <strong className="text-amber-400">{liveRemaining}</strong></span>
                                </div>
                            </div>
                        </div>

                        {/* Caller Controls */}
                        {liveGame.status === 'active' && (
                            <div className="flex flex-wrap items-center gap-3">
                                <button
                                    onClick={handleCallNext}
                                    disabled={calling || liveRemaining === 0}
                                    className="bg-amber-500 hover:bg-amber-400 disabled:opacity-50 text-neutral-950 font-black px-6 py-3 rounded-2xl shadow-lg shadow-amber-500/20 text-sm flex items-center space-x-2 transition"
                                >
                                    <span>{calling ? 'Drawing Ball...' : 'Call Next Ball'}</span>
                                    <span className="text-xs bg-neutral-950/20 px-2 py-0.5 rounded-full font-mono">1..75</span>
                                </button>

                                <button
                                    onClick={() => setAutoCallActive(!autoCallActive)}
                                    className={`px-5 py-3 rounded-2xl text-xs font-bold transition flex items-center space-x-2 border ${
                                        autoCallActive
                                            ? 'bg-rose-500/20 text-rose-300 border-rose-500/40 hover:bg-rose-500/30'
                                            : 'bg-neutral-800 text-neutral-300 border-neutral-700 hover:bg-neutral-700'
                                    }`}
                                >
                                    <span className={`w-2 h-2 rounded-full ${autoCallActive ? 'bg-rose-400 animate-ping' : 'bg-neutral-500'}`} />
                                    <span>{autoCallActive ? 'Stop Auto-Call' : `Auto-Call (${liveGame.call_interval}s)`}</span>
                                </button>
                            </div>
                        )}
                    </div>

                    {/* Master Caller Board (75 Ball Matrix) */}
                    <div className="mt-6">
                        <div className="flex justify-between items-center mb-3">
                            <h3 className="text-xs font-bold text-neutral-400 uppercase tracking-wider">
                                Master Caller Board (1–75)
                            </h3>
                            <span className="text-xs text-neutral-500 font-mono">
                                Server Authoritative CSPRNG
                            </span>
                        </div>

                        <div className="space-y-2">
                            {['B', 'I', 'N', 'G', 'O'].map((letter) => {
                                const rowNumbers = liveBoard?.[letter] || [];
                                return (
                                    <div key={letter} className="flex items-center space-x-2">
                                        <div className={`w-8 h-8 rounded-lg flex items-center justify-center font-black text-xs border ${
                                            letter === 'B' ? 'bg-rose-500/20 text-rose-400 border-rose-500/30' :
                                            letter === 'I' ? 'bg-amber-500/20 text-amber-400 border-amber-500/30' :
                                            letter === 'N' ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30' :
                                            letter === 'G' ? 'bg-sky-500/20 text-sky-400 border-sky-500/30' :
                                            'bg-purple-500/20 text-purple-400 border-purple-500/30'
                                        }`}>
                                            {letter}
                                        </div>

                                        <div className="flex-1 grid grid-cols-15 gap-1">
                                            {rowNumbers.map((cell) => (
                                                <div
                                                    key={cell.number}
                                                    title={cell.is_called ? `Called #${cell.sequence_index}` : `Uncalled #${cell.number}`}
                                                    className={`h-8 rounded-lg flex items-center justify-center font-bold text-xs transition select-none ${
                                                        cell.is_called
                                                            ? 'bg-amber-400 text-neutral-950 shadow-sm font-black scale-105 ring-1 ring-amber-300'
                                                            : 'bg-neutral-950/70 border border-neutral-800 text-neutral-500'
                                                    }`}
                                                >
                                                    {cell.number}
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    {/* Recent Calls Feed */}
                    {liveGame.calls && liveGame.calls.length > 0 && (
                        <div className="mt-6 pt-4 border-t border-neutral-800">
                            <span className="text-xs font-bold text-neutral-400 uppercase tracking-wider block mb-2">
                                Recent Calls Ticker
                            </span>
                            <div className="flex flex-wrap items-center gap-2">
                                {liveGame.calls.slice(-12).reverse().map((call) => (
                                    <div
                                        key={call.id}
                                        className="bg-neutral-950 border border-neutral-800 px-3 py-1 rounded-xl text-xs flex items-center space-x-2"
                                    >
                                        <span className="text-neutral-500 font-mono">#{call.sequence_index}</span>
                                        <span className="font-extrabold text-white">{call.letter}-{call.ball_number}</span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
                    {/* Left Column: Room Overview & Configuration Snapshot */}
                    <div className="lg:col-span-4 space-y-6">
                        {/* Game Rules Card */}
                        <div className="bg-neutral-900 border border-neutral-800 rounded-3xl p-6 shadow-sm">
                            <h2 className="text-xs font-bold text-neutral-400 uppercase tracking-wider mb-4">
                                Game Configuration Snapshot
                            </h2>

                            <div className="space-y-3 text-xs">
                                <div className="flex justify-between py-1 border-b border-neutral-800">
                                    <span className="text-neutral-400">Template</span>
                                    <span className="font-bold text-white">
                                        {game.configuration_snapshot.template_name ?? 'Custom'}
                                    </span>
                                </div>

                                <div className="flex justify-between py-1 border-b border-neutral-800">
                                    <span className="text-neutral-400">Required Patterns</span>
                                    <span className="font-bold text-amber-400">
                                        {game.configuration_snapshot.required_pattern_count ?? 1} lines
                                    </span>
                                </div>

                                <div className="flex justify-between py-1 border-b border-neutral-800">
                                    <span className="text-neutral-400">Call Interval</span>
                                    <span className="font-bold text-white">
                                        {game.call_interval} seconds
                                    </span>
                                </div>

                                <div className="flex justify-between py-1 border-b border-neutral-800">
                                    <span className="text-neutral-400">Winner Policy</span>
                                    <span className="font-bold text-indigo-400 font-mono">
                                        {game.winner_policy}
                                    </span>
                                </div>

                                <div className="flex justify-between py-1 border-b border-neutral-800">
                                    <span className="text-neutral-400">Entry Fee</span>
                                    <span className="font-bold text-emerald-400">
                                        {game.entry_fee === 0 ? 'Free Entry' : `$${(game.entry_fee / 100).toFixed(2)}`}
                                    </span>
                                </div>

                                <div className="flex justify-between py-1">
                                    <span className="text-neutral-400">Player Capacity</span>
                                    <span className="font-bold text-white">
                                        {game.players.length} / {game.max_players}
                                    </span>
                                </div>
                            </div>
                        </div>

                        {/* Inventory Health */}
                        <div className="bg-neutral-900 border border-neutral-800 rounded-3xl p-6 shadow-sm">
                            <h3 className="text-xs font-bold text-neutral-400 uppercase tracking-wider mb-2">
                                Card Inventory Buffer
                            </h3>
                            <div className="text-2xl font-black text-white mt-1">
                                {available_cards_count} Available
                            </div>
                            <p className="text-xs text-neutral-400 mt-2 leading-relaxed">
                                {available_cards_count >= (game.max_players - game.players.length)
                                    ? 'Sufficient cards in company inventory for full room assignment.'
                                    : 'Warning: Inventory low. Generate more cards before room fills up.'}
                            </p>
                        </div>
                    </div>

                    {/* Right Column: Live Players & Fixed Cards Assigned */}
                    <div className="lg:col-span-8">
                        <div className="bg-neutral-900 border border-neutral-800 rounded-3xl p-6 shadow-sm">
                            <div className="flex justify-between items-center mb-6">
                                <div>
                                    <h2 className="text-base font-bold text-white">
                                        Registered Players & Assigned Cards ({game.cards.length})
                                    </h2>
                                    <p className="text-xs text-neutral-400">
                                        Each player is atomically allocated a unique fixed card from inventory
                                    </p>
                                </div>
                                <span className="text-xs font-mono text-indigo-400 font-semibold">
                                    {game.players.length} joined
                                </span>
                            </div>

                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-neutral-800 text-sm">
                                    <thead className="bg-neutral-950/60 text-neutral-400 text-xs uppercase tracking-wider text-left">
                                        <tr>
                                            <th className="px-4 py-3 font-semibold">Player</th>
                                            <th className="px-4 py-3 font-semibold">Assigned Fixed Card</th>
                                            <th className="px-4 py-3 font-semibold">Version</th>
                                            <th className="px-4 py-3 font-semibold text-right">Joined At</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-neutral-800/80 text-neutral-200 text-xs">
                                        {game.cards.length === 0 ? (
                                            <tr>
                                                <td colSpan={4} className="px-4 py-8 text-center text-neutral-500">
                                                    No players have joined this room yet.
                                                </td>
                                            </tr>
                                        ) : (
                                            game.cards.map((item) => (
                                                <tr key={item.id} className="hover:bg-neutral-800/40">
                                                    <td className="px-4 py-3 font-bold text-white">
                                                        {item.user.name}
                                                    </td>
                                                    <td className="px-4 py-3 font-mono font-bold text-amber-400">
                                                        #{String(item.card.card_number).padStart(6, '0')}
                                                    </td>
                                                    <td className="px-4 py-3 text-indigo-400 font-semibold">
                                                        v{item.version.version_number}
                                                    </td>
                                                    <td className="px-4 py-3 text-right text-neutral-500">
                                                        {new Date(item.assigned_at).toLocaleTimeString()}
                                                    </td>
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </CompanyAdminLayout>
    );
}
