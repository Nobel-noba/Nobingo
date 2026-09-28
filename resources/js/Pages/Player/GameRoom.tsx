import PlayerLayout from '@/Layouts/PlayerLayout';
import { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { useState, useEffect } from 'react';

interface GameCallInfo {
    sequence_index: number;
    ball_number: number;
    letter: string;
    code: string;
    called_at?: string;
}

interface GameInfo {
    id: number;
    game_number: number;
    name: string;
    status: string;
    call_interval: number;
    configuration: {
        required_pattern_count?: number;
        template_name?: string;
    };
    players_count: number;
    entry_fee: string;
    last_call?: GameCallInfo | null;
    calls: GameCallInfo[];
    remaining_count: number;
}

interface PlayerCardInfo {
    id: number;
    card_id: number;
    card_number: string;
    version: number;
    grid: number[][];
    marked_positions: [number, number][];
}

interface Props extends PageProps {
    game: GameInfo;
    player_card: PlayerCardInfo;
    master_board?: Record<string, Array<{ number: number; is_called: boolean }>>;
}

export default function GameRoom({ game: initialGame, player_card: initialCard, tenant }: Props) {
    const companySlug = tenant?.slug || 'default';
    const [game, setGame] = useState(initialGame);
    const [card, setCard] = useState(initialCard);
    const [markedPositions, setMarkedPositions] = useState<[number, number][]>(initialCard.marked_positions || [[2, 2]]);
    const [daubError, setDaubError] = useState<string | null>(null);

    // Synchronize props if changed via Inertia visits
    useEffect(() => {
        setGame(initialGame);
        setCard(initialCard);
        setMarkedPositions(initialCard.marked_positions || [[2, 2]]);
    }, [initialGame, initialCard]);

    // Live WebSockets subscription via Laravel Reverb & Echo
    useEffect(() => {
        if (!window.Echo || !tenant?.id) return;

        const channelName = `company.${tenant.id}.game.${game.id}`;
        const channel = window.Echo.private(channelName);

        channel.listen('.number.called', (event: any) => {
            setGame((prev) => ({
                ...prev,
                last_call: {
                    sequence_index: event.sequence_index,
                    ball_number: event.ball_number,
                    letter: event.letter,
                    code: event.code,
                    called_at: event.called_at,
                },
                remaining_count: event.remaining_count,
                calls: [...(prev.calls || []), {
                    sequence_index: event.sequence_index,
                    ball_number: event.ball_number,
                    letter: event.letter,
                    code: event.code,
                    called_at: event.called_at,
                }],
            }));

            // Auto-mark card if matching number exists
            const currentGrid = card.grid || [];
            for (let r = 0; r < 5; r++) {
                for (let c = 0; c < 5; c++) {
                    if (currentGrid[r]?.[c] === event.ball_number) {
                        setMarkedPositions((prev) => {
                            if (prev.some(([pr, pc]) => pr === r && pc === c)) return prev;
                            return [...prev, [r, c]];
                        });
                    }
                }
            }
        });

        channel.listen('.game.state.changed', (event: any) => {
            setGame((prev) => ({
                ...prev,
                status: event.status,
            }));
        });

        channel.listen('.player.joined', (event: any) => {
            setGame((prev) => ({
                ...prev,
                players_count: event.players_count,
            }));
        });

        return () => {
            channel.stopListening('.number.called');
            channel.stopListening('.game.state.changed');
            channel.stopListening('.player.joined');
            window.Echo.leave(channelName);
        };
    }, [game.id, tenant?.id, card.grid]);

    // Live Polling for active game ball draws and auto-daubs
    useEffect(() => {
        if (game.status !== 'active') return;

        const interval = setInterval(async () => {
            try {
                const response = await fetch(`/c/${companySlug}/game/${game.id}/state`, {
                    headers: {
                        Accept: 'application/json',
                    },
                });

                if (response.ok) {
                    const data = await response.json();
                    setGame((prev) => ({
                        ...prev,
                        status: data.game_status,
                        last_call: data.last_call,
                        remaining_count: data.remaining_count,
                        calls: data.recent_calls || prev.calls,
                    }));

                    if (data.marked_positions && Array.isArray(data.marked_positions)) {
                        setMarkedPositions(data.marked_positions);
                    }
                }
            } catch (err) {
                console.error('State poll error:', err);
            }
        }, 2500);

        return () => clearInterval(interval);
    }, [game.id, game.status, companySlug]);

    const isCellMarked = (row: number, col: number) => {
        if (row === 2 && col === 2) return true;
        return markedPositions.some(([r, c]) => r === row && c === col);
    };

    const handleDaub = async (row: number, col: number) => {
        if (isCellMarked(row, col)) return;
        setDaubError(null);

        try {
            const token = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content;
            const res = await fetch(`/c/${companySlug}/game/${game.id}/cards/${card.id}/daub`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': token || '',
                },
                body: JSON.stringify({ row, col }),
            });

            const data = await res.json();
            if (res.ok && data.success) {
                setMarkedPositions(data.marked_positions);
            } else {
                setDaubError(data.error || 'Cannot daub uncalled number.');
                setTimeout(() => setDaubError(null), 3000);
            }
        } catch (e: any) {
            setDaubError('Daub verification failed.');
            setTimeout(() => setDaubError(null), 3000);
        }
    };

    const columnHeaders = ['B', 'I', 'N', 'G', 'O'];
    const columnColors = [
        'bg-rose-500/20 text-rose-400 border-rose-500/30',
        'bg-amber-500/20 text-amber-400 border-amber-500/30',
        'bg-emerald-500/20 text-emerald-400 border-emerald-500/30',
        'bg-sky-500/20 text-sky-400 border-sky-500/30',
        'bg-purple-500/20 text-purple-400 border-purple-500/30',
    ];

    const getLetterColor = (letter: string) => {
        switch (letter) {
            case 'B': return 'from-rose-500 to-rose-600 border-rose-400';
            case 'I': return 'from-amber-500 to-amber-600 border-amber-400';
            case 'N': return 'from-emerald-500 to-emerald-600 border-emerald-400';
            case 'G': return 'from-sky-500 to-sky-600 border-sky-400';
            case 'O': return 'from-purple-500 to-purple-600 border-purple-400';
            default: return 'from-indigo-500 to-indigo-600 border-indigo-400';
        }
    };

    return (
        <PlayerLayout
            header={
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div className="flex items-center space-x-3">
                        <Link
                            href={`/c/${companySlug}/lobby`}
                            className="text-xs text-slate-400 hover:text-white"
                        >
                            &larr; Lobby
                        </Link>
                        <span className="text-slate-700">/</span>
                        <h1 className="text-xl font-extrabold text-white tracking-tight">
                            {game.name}
                        </h1>
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

                    <div className="flex items-center space-x-3 text-xs">
                        <span className="text-slate-400">Players in room:</span>
                        <span className="font-bold text-white bg-slate-950 px-3 py-1 rounded-lg border border-slate-800">
                            {game.players_count}
                        </span>
                    </div>
                </div>
            }
        >
            <Head title={game.name} />

            <div className="max-w-5xl mx-auto space-y-6">
                {/* Live Drawn Ball Banner */}
                <div className="bg-slate-950 border border-slate-800 rounded-3xl p-5 shadow-2xl flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div className="flex items-center space-x-5">
                        {game.last_call ? (
                            <div className={`w-16 h-16 rounded-full bg-gradient-to-tr ${getLetterColor(game.last_call.letter)} flex flex-col items-center justify-center text-white border-2 shadow-lg animate-pulse`}>
                                <span className="text-[10px] font-black tracking-widest">{game.last_call.letter}</span>
                                <span className="text-2xl font-black leading-none">{game.last_call.ball_number}</span>
                            </div>
                        ) : (
                            <div className="w-16 h-16 rounded-full bg-slate-900 border border-slate-800 flex items-center justify-center text-[10px] font-bold text-slate-500 text-center px-1">
                                READY
                            </div>
                        )}

                        <div>
                            <span className="text-[10px] uppercase font-bold text-slate-400 tracking-wider">
                                {game.status === 'active' ? 'Current Ball Called' : 'Room Waiting'}
                            </span>
                            <div className="text-xl font-black text-white">
                                {game.last_call ? game.last_call.code : (game.status === 'open' ? 'Waiting to Start' : 'Game Paused')}
                            </div>
                            <span className="text-xs text-slate-400 font-mono">
                                Sequence: #{game.last_call?.sequence_index ?? 0}/75 &bull; {game.remaining_count ?? 75} balls remaining
                            </span>
                        </div>
                    </div>

                    {/* Recent Call Ticker */}
                    {game.calls && game.calls.length > 0 && (
                        <div className="flex items-center space-x-2 overflow-x-auto py-1">
                            <span className="text-[10px] uppercase font-bold text-slate-500 mr-1">Recent:</span>
                            {game.calls.slice(-6).reverse().map((call) => (
                                <span
                                    key={call.sequence_index}
                                    className="bg-slate-900 border border-slate-800 px-2.5 py-1 rounded-xl text-xs font-bold text-slate-200"
                                >
                                    {call.code}
                                </span>
                            ))}
                        </div>
                    )}
                </div>

                {daubError && (
                    <div className="bg-rose-500/10 border border-rose-500/30 text-rose-300 px-4 py-2 rounded-2xl text-xs font-semibold text-center animate-shake">
                        {daubError}
                    </div>
                )}

                <div className="grid grid-cols-1 md:grid-cols-12 gap-8 items-start">
                    {/* Left Column: Fixed Card Board */}
                    <div className="md:col-span-7 flex flex-col items-center">
                        <div className="w-full max-w-md bg-slate-950 border border-slate-800 rounded-3xl p-5 shadow-2xl">
                            <div className="flex justify-between items-center mb-4">
                                <div>
                                    <span className="text-[10px] uppercase font-bold text-indigo-400 tracking-wider">
                                        Fixed Assigned Card
                                    </span>
                                    <h3 className="text-lg font-black text-white">{card.card_number}</h3>
                                </div>
                                <div className="flex items-center space-x-2">
                                    <span className="text-[10px] font-mono text-emerald-400 font-bold px-2 py-0.5 rounded bg-emerald-500/10 border border-emerald-500/20">
                                        {markedPositions.length} Marked
                                    </span>
                                    <span className="text-[10px] font-mono text-slate-400 font-semibold px-2 py-0.5 rounded bg-slate-900 border border-slate-800">
                                        v{card.version}
                                    </span>
                                </div>
                            </div>

                            {/* 5x5 Card Matrix with Daubing */}
                            <div className="grid grid-cols-5 gap-2">
                                {columnHeaders.map((col, idx) => (
                                    <div
                                        key={col}
                                        className={`h-10 rounded-xl flex items-center justify-center font-black text-base border shadow-sm select-none ${columnColors[idx]}`}
                                    >
                                        {col}
                                    </div>
                                ))}

                                {card.grid.map((row, rIdx) =>
                                    row.map((cell, cIdx) => {
                                        const isCenter = rIdx === 2 && cIdx === 2;
                                        const marked = isCellMarked(rIdx, cIdx);

                                        return (
                                            <button
                                                key={`${rIdx}-${cIdx}`}
                                                type="button"
                                                onClick={() => handleDaub(rIdx, cIdx)}
                                                className={`aspect-square rounded-2xl flex flex-col items-center justify-center font-bold text-sm border transition relative overflow-hidden select-none active:scale-95 ${
                                                    marked
                                                        ? 'bg-gradient-to-tr from-amber-500 to-amber-600 text-neutral-950 font-black border-amber-300 shadow-lg shadow-amber-500/20 ring-2 ring-amber-400/40'
                                                        : 'bg-slate-900/90 hover:bg-slate-800/90 border-slate-800 text-white'
                                                }`}
                                            >
                                                {/* Daub Stamp Marker */}
                                                {marked && (
                                                    <span className="absolute inset-0 bg-radial-daub opacity-20 pointer-events-none" />
                                                )}

                                                <span className="relative z-10 text-base">
                                                    {isCenter ? 'FREE' : cell}
                                                </span>

                                                {marked && !isCenter && (
                                                    <span className="text-[8px] uppercase tracking-tighter opacity-80 font-black">
                                                        DAUBED
                                                    </span>
                                                )}
                                            </button>
                                        );
                                    })
                                )}
                            </div>

                            <div className="mt-4 text-center text-[10px] text-slate-500">
                                Click called cells to manually daub &bull; Auto-daubed server-authoritatively
                            </div>
                        </div>
                    </div>

                    {/* Right Column: Room Invariants & Info */}
                    <div className="md:col-span-5 space-y-5">
                        {/* Live Match Invariants */}
                        <div className="bg-slate-950/70 border border-slate-800 rounded-3xl p-6 shadow-sm">
                            <h3 className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">
                                Match Invariants
                            </h3>
                            <div className="space-y-3 text-xs mt-3">
                                <div className="flex justify-between py-1 border-b border-slate-800/80">
                                    <span className="text-slate-400">Winning Requirement:</span>
                                    <span className="font-bold text-amber-400">
                                        {game.configuration.required_pattern_count ?? 1} Line(s) Required
                                    </span>
                                </div>
                                <div className="flex justify-between py-1 border-b border-slate-800/80">
                                    <span className="text-slate-400">Call Interval:</span>
                                    <span className="font-bold text-white">
                                        Every {game.call_interval} seconds
                                    </span>
                                </div>
                                <div className="flex justify-between py-1">
                                    <span className="text-slate-400">Entry Fee:</span>
                                    <span className="font-bold text-emerald-400">
                                        {game.entry_fee}
                                    </span>
                                </div>
                            </div>
                        </div>

                        {/* Caller State Info Card */}
                        <div className="bg-indigo-950/20 border border-indigo-500/20 rounded-3xl p-6 shadow-sm">
                            <div className="flex items-center space-x-2 text-indigo-400 font-bold text-xs uppercase tracking-wider mb-2">
                                <span className={`h-2 w-2 rounded-full ${game.status === 'active' ? 'bg-amber-400 animate-ping' : 'bg-indigo-400'}`}></span>
                                <span>{game.status === 'open' ? 'Waiting for Game to Start' : (game.status === 'active' ? 'Live Calling In Progress' : 'Game Concluded')}</span>
                            </div>
                            <p className="text-xs text-slate-300 leading-relaxed">
                                {game.status === 'open'
                                    ? 'The game operator will initiate the ball calling sequence shortly. Once started, drawn balls will appear automatically.'
                                    : 'Balls are drawn server-authoritatively using cryptographically secure random generation. Matching numbers are permanently tracked.'}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </PlayerLayout>
    );
}
