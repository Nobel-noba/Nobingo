import PlayerLayout from '@/Layouts/PlayerLayout';
import { PageProps } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import React, { useState, useEffect, useRef } from 'react';

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
        winner_policy?: string;
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

interface WinnerAnnouncement {
    game_id: number;
    winner_id: number;
    winner_name: string;
    card_number: string;
    patterns: string[];
    winning_ball: string;
    winning_call_sequence: number;
    payout_amount: number;
    formatted_payout: string;
    claim_type: string;
    is_game_completed: boolean;
    total_winners: number;
}

interface Props extends PageProps {
    game: GameInfo;
    player_card: PlayerCardInfo | null;
    master_board?: Record<string, Array<{ number: number; is_called: boolean }>>;
}

// Web Audio API Synthesizer for Zero-Asset Chimes & Fanfare
class SoundSynthesizer {
    private ctx: AudioContext | null = null;

    private getContext(): AudioContext | null {
        if (typeof window === 'undefined') return null;
        if (!this.ctx) {
            const AudioCtx = window.AudioContext || (window as any).webkitAudioContext;
            if (AudioCtx) {
                this.ctx = new AudioCtx();
            }
        }
        if (this.ctx && this.ctx.state === 'suspended') {
            this.ctx.resume();
        }
        return this.ctx;
    }

    playBallChime() {
        try {
            const ctx = this.getContext();
            if (!ctx) return;

            const now = ctx.currentTime;
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();

            osc.type = 'sine';
            // Pleasant two-tone glissando (C5 to G5)
            osc.frequency.setValueAtTime(523.25, now);
            osc.frequency.exponentialRampToValueAtTime(783.99, now + 0.12);

            gain.gain.setValueAtTime(0.15, now);
            gain.gain.exponentialRampToValueAtTime(0.001, now + 0.35);

            osc.connect(gain);
            gain.connect(ctx.destination);

            osc.start(now);
            osc.stop(now + 0.35);
        } catch {
            // Audio context blocked or unsupported
        }
    }

    playVictoryFanfare() {
        try {
            const ctx = this.getContext();
            if (!ctx) return;

            const now = ctx.currentTime;
            const chords = [523.25, 659.25, 783.99, 1046.50]; // C Major arpeggio

            chords.forEach((freq, idx) => {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();

                osc.type = 'triangle';
                osc.frequency.setValueAtTime(freq, now + idx * 0.1);

                gain.gain.setValueAtTime(0.2, now + idx * 0.1);
                gain.gain.exponentialRampToValueAtTime(0.001, now + idx * 0.1 + 0.6);

                osc.connect(gain);
                gain.connect(ctx.destination);

                osc.start(now + idx * 0.1);
                osc.stop(now + idx * 0.1 + 0.6);
            });
        } catch {
            // Audio context blocked
        }
    }
}

const synth = new SoundSynthesizer();

export default function GameRoom({ game: initialGame, player_card: initialCard, tenant }: Props) {
    const companySlug = tenant?.slug || 'default';
    const [game, setGame] = useState(initialGame);
    const [card, setCard] = useState<PlayerCardInfo | null>(initialCard);
    const [markedPositions, setMarkedPositions] = useState<[number, number][]>(initialCard?.marked_positions || [[2, 2]]);
    const [daubError, setDaubError] = useState<string | null>(null);
    const [claiming, setClaiming] = useState(false);
    const [claimPending, setClaimPending] = useState(false);
    const [claimMessage, setClaimMessage] = useState<string | null>(null);
    const [claimError, setClaimError] = useState<string | null>(null);
    const [winnerAnnouncement, setWinnerAnnouncement] = useState<WinnerAnnouncement | null>(null);
    const [isMuted, setIsMuted] = useState(false);
    const [connectionStatus, setConnectionStatus] = useState<'ws_live' | 'polling' | 'connecting'>('connecting');
    const [activeTab, setActiveTab] = useState<'board' | 'history'>('board');

    // Load persisted sound preference
    useEffect(() => {
        const savedMute = localStorage.getItem('nobingo_sound_muted');
        if (savedMute === 'true') {
            setIsMuted(true);
        }
    }, []);

    const toggleMute = () => {
        setIsMuted((prev) => {
            const next = !prev;
            localStorage.setItem('nobingo_sound_muted', String(next));
            return next;
        });
    };

    // Synchronize props if changed via Inertia visits
    useEffect(() => {
        setGame(initialGame);
        setCard(initialCard);
        setMarkedPositions(initialCard?.marked_positions || [[2, 2]]);
    }, [initialGame, initialCard]);

    // Live WebSockets subscription via Laravel Reverb & Echo
    useEffect(() => {
        if (!window.Echo || !tenant?.id) {
            setConnectionStatus('polling');
            return;
        }

        const channelName = `company.${tenant.id}.game.${game.id}`;
        const channel = window.Echo.private(channelName);
        setConnectionStatus('ws_live');

        channel.listen('.number.called', (event: any) => {
            setConnectionStatus('ws_live');
            if (!isMuted) {
                synth.playBallChime();
            }

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
                calls: [
                    ...(prev.calls || []),
                    {
                        sequence_index: event.sequence_index,
                        ball_number: event.ball_number,
                        letter: event.letter,
                        code: event.code,
                        called_at: event.called_at,
                    },
                ],
            }));

            // Auto-mark card if matching number exists
            if (card) {
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
            }
        });

        channel.listen('.game.state.changed', (event: any) => {
            setGame((prev) => ({
                ...prev,
                status: event.status,
            }));
            if (event.status === 'active') {
                setClaimPending(false);
            }
            router.reload({ only: ['player_card', 'game'] });
        });

        channel.listen('.player.joined', (event: any) => {
            setGame((prev) => ({
                ...prev,
                players_count: event.players_count,
            }));
            router.reload({ only: ['player_card', 'game'] });
        });

        channel.listen('.bingo.claim.rejected', (event: any) => {
            setClaimPending(false);
            setClaimMessage(null);
            setClaimError(event.reason || 'Your Bingo claim was rejected by the host.');
        });

        channel.listen('.game.won', (event: WinnerAnnouncement) => {
            setClaimPending(false);
            setWinnerAnnouncement(event);
            if (!isMuted) {
                synth.playVictoryFanfare();
            }
            if (event.is_game_completed) {
                setGame((prev) => ({
                    ...prev,
                    status: 'completed',
                }));
            }
        });

        return () => {
            channel.stopListening('.number.called');
            channel.stopListening('.game.state.changed');
            channel.stopListening('.player.joined');
            channel.stopListening('.bingo.claim.rejected');
            channel.stopListening('.game.won');
            window.Echo.leave(channelName);
        };
    }, [game.id, tenant?.id, card?.grid, isMuted]);

    const handleClaimBingo = async () => {
        if (!card || game.status !== 'active' || claiming || claimPending) return;

        setClaiming(true);
        setClaimError(null);
        setClaimMessage(null);

        try {
            const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content;
            const response = await fetch(`/c/${companySlug}/game/${game.id}/cards/${card.id}/claim-bingo`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken || '',
                },
            });

            const data = await response.json();

            if (response.ok && data.success) {
                setClaimMessage(data.message);
                if (data.status === 'pending_verification') {
                    setClaimPending(true);
                    setGame((prev) => ({ ...prev, status: 'paused' }));
                } else {
                    if (!isMuted) {
                        synth.playVictoryFanfare();
                    }
                    setGame((prev) => ({ ...prev, status: 'completed' }));
                }
            } else {
                setClaimError(data.message || 'Invalid Bingo claim.');
            }
        } catch {
            setClaimError('Network error while claiming Bingo.');
        } finally {
            setClaiming(false);
        }
    };

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
                setConnectionStatus('polling');
            }
        }, 2500);

        return () => clearInterval(interval);
    }, [game.id, game.status, companySlug]);

    const isCellMarked = (row: number, col: number) => {
        if (row === 2 && col === 2) return true;
        return markedPositions.some(([r, c]) => r === row && c === col);
    };

    const isCellLastCalled = (row: number, col: number) => {
        if (!card || !game.last_call) return false;
        return card.grid[row]?.[col] === game.last_call.ball_number;
    };

    const handleDaub = async (row: number, col: number) => {
        if (!card || isCellMarked(row, col)) return;
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
        } catch {
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

    // Construct full 1-75 master board partitioned into columns
    const calledSet = new Set((game.calls || []).map((c) => c.ball_number));
    if (game.last_call) {
        calledSet.add(game.last_call.ball_number);
    }

    const masterColumns = [
        { letter: 'B', range: [1, 15], color: 'rose' },
        { letter: 'I', range: [16, 30], color: 'amber' },
        { letter: 'N', range: [31, 45], color: 'emerald' },
        { letter: 'G', range: [46, 60], color: 'sky' },
        { letter: 'O', range: [61, 75], color: 'purple' },
    ];

    return (
        <PlayerLayout
            header={
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div className="flex items-center space-x-3">
                        <Link
                            href={`/c/${companySlug}/lobby`}
                            className="text-xs text-slate-400 hover:text-white transition flex items-center gap-1"
                        >
                            <span>&larr;</span>
                            <span>Lobby</span>
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
                        {/* Audio Toggle Button */}
                        <button
                            type="button"
                            onClick={toggleMute}
                            className={`px-3 py-1.5 rounded-xl border text-xs font-bold transition flex items-center space-x-1.5 ${
                                isMuted
                                    ? 'bg-neutral-900 border-neutral-700 text-neutral-400 hover:text-white'
                                    : 'bg-indigo-600/20 border-indigo-500/40 text-indigo-300 hover:bg-indigo-600/30'
                            }`}
                            title={isMuted ? 'Unmute game sounds' : 'Mute game sounds'}
                            aria-label={isMuted ? 'Unmute sound effects' : 'Mute sound effects'}
                        >
                            <span>{isMuted ? '🔇' : '🔊'}</span>
                            <span className="hidden sm:inline">{isMuted ? 'Muted' : 'Sound On'}</span>
                        </button>

                        {/* Real-time Connection Status Indicator */}
                        <div
                            className={`px-2.5 py-1 rounded-xl border text-[11px] font-bold flex items-center space-x-1.5 ${
                                connectionStatus === 'ws_live'
                                    ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400'
                                    : 'bg-amber-500/10 border-amber-500/30 text-amber-400'
                            }`}
                            role="status"
                            aria-live="polite"
                        >
                            <span className={`h-1.5 w-1.5 rounded-full ${connectionStatus === 'ws_live' ? 'bg-emerald-400 animate-pulse' : 'bg-amber-400'}`} />
                            <span className="hidden sm:inline">
                                {connectionStatus === 'ws_live' ? 'Reverb Live' : 'Polling Active'}
                            </span>
                        </div>

                        <div className="flex items-center space-x-2 text-xs">
                            <span className="text-slate-400">Players:</span>
                            <span className="font-bold text-white bg-slate-950 px-2.5 py-1 rounded-lg border border-slate-800">
                                {game.players_count}
                            </span>
                        </div>
                    </div>
                </div>
            }
        >
            <Head title={`${game.name} — Live Bingo Room`} />

            <div className="max-w-6xl mx-auto space-y-6">
                {/* Live Drawn Ball Banner & Ticker */}
                <div className="bg-slate-950 border border-slate-800/90 rounded-3xl p-4 sm:p-6 shadow-2xl flex flex-col md:flex-row items-center justify-between gap-6">
                    <div className="flex items-center space-x-5 w-full md:w-auto">
                        {game.last_call ? (
                            <div className={`w-20 h-20 rounded-full bg-gradient-to-tr ${getLetterColor(game.last_call.letter)} flex flex-col items-center justify-center text-white border-4 shadow-xl shadow-indigo-500/20 animate-bounce flex-shrink-0`}>
                                <span className="text-xs font-black tracking-widest leading-none">{game.last_call.letter}</span>
                                <span className="text-3xl font-black leading-tight">{game.last_call.ball_number}</span>
                            </div>
                        ) : (
                            <div className="w-20 h-20 rounded-full bg-slate-900 border-2 border-slate-800 flex items-center justify-center text-xs font-bold text-slate-500 text-center flex-shrink-0">
                                READY
                            </div>
                        )}

                        <div>
                            <span className="text-[10px] uppercase font-bold text-indigo-400 tracking-wider">
                                {game.status === 'active' ? 'Active Ball In Play' : 'Session Status'}
                            </span>
                            <div className="text-2xl font-black text-white" aria-live="assertive">
                                {game.last_call ? game.last_call.code : (game.status === 'open' ? 'Waiting for Call Sequence...' : 'Game Concluded')}
                            </div>
                            <span className="text-xs text-slate-400 font-mono">
                                Sequence: #{game.last_call?.sequence_index ?? 0}/75 &bull; {game.remaining_count ?? 75} balls remaining
                            </span>
                        </div>
                    </div>

                    {/* Recent Call Ticker Stream */}
                    {game.calls && game.calls.length > 0 && (
                        <div className="w-full md:w-auto flex flex-col items-start md:items-end">
                            <span className="text-[10px] uppercase font-bold text-slate-500 mb-1.5">Recent Number Draws:</span>
                            <div className="flex items-center space-x-1.5 overflow-x-auto max-w-full pb-1">
                                {game.calls.slice(-8).reverse().map((call) => (
                                    <span
                                        key={call.sequence_index}
                                        className="bg-slate-900 border border-slate-800 px-2.5 py-1 rounded-xl text-xs font-bold text-slate-200 shadow-sm flex-shrink-0"
                                    >
                                        {call.code}
                                    </span>
                                ))}
                            </div>
                        </div>
                    )}
                </div>

                {daubError && (
                    <div className="bg-rose-500/10 border border-rose-500/30 text-rose-300 px-4 py-2.5 rounded-2xl text-xs font-semibold text-center animate-shake shadow-sm" role="alert">
                        ⚠️ {daubError}
                    </div>
                )}

                <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                    {/* Left Column: Fixed 5x5 Bingo Card or Awaiting Card State */}
                    <div className="lg:col-span-6 flex flex-col items-center">
                        {!card ? (
                            <div className="w-full max-w-md bg-slate-950 border border-slate-800 rounded-3xl p-8 shadow-2xl text-center space-y-5">
                                <div className="w-20 h-20 rounded-3xl bg-amber-500/10 border border-amber-500/30 text-amber-400 flex items-center justify-center text-4xl mx-auto shadow-inner animate-pulse">
                                    📇
                                </div>
                                <div className="space-y-2">
                                    <h3 className="text-xl font-black text-white">Awaiting Card Assignment</h3>
                                    <p className="text-xs text-slate-400 leading-relaxed max-w-xs mx-auto">
                                        You have successfully joined this game! The host manager will allocate a bingo card to your account before starting the game.
                                    </p>
                                </div>
                                <div className="p-3 rounded-2xl bg-slate-900 border border-slate-800 flex items-center justify-center gap-2 text-xs text-amber-300 font-bold">
                                    <span className="w-2 h-2 rounded-full bg-amber-400 animate-ping"></span>
                                    <span>Game status: <span className="uppercase">{game.status}</span></span>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => router.reload({ only: ['player_card', 'game'] })}
                                    className="px-5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold transition shadow cursor-pointer"
                                >
                                    ↻ Check for Assigned Card
                                </button>
                            </div>
                        ) : (
                            <div className="w-full max-w-md bg-slate-950 border border-slate-800 rounded-3xl p-4 sm:p-6 shadow-2xl">
                                <div className="flex justify-between items-center mb-4">
                                    <div>
                                        <span className="text-[10px] uppercase font-bold text-indigo-400 tracking-wider">
                                            Persistent Fixed Card
                                        </span>
                                        <h3 className="text-xl font-black text-white">{card.card_number}</h3>
                                    </div>
                                    <div className="flex items-center space-x-2">
                                        <span className="text-xs font-mono text-emerald-400 font-bold px-2.5 py-1 rounded-lg bg-emerald-500/10 border border-emerald-500/20">
                                            {markedPositions.length} Marked
                                        </span>
                                        <span className="text-xs font-mono text-slate-400 font-semibold px-2 py-1 rounded-lg bg-slate-900 border border-slate-800">
                                            v{card.version}
                                        </span>
                                    </div>
                                </div>

                                {/* 5x5 Card Matrix with Touch Daubing */}
                                <div className="grid grid-cols-5 gap-2 sm:gap-2.5" role="grid" aria-label="Bingo Card Matrix">
                                    {columnHeaders.map((col, idx) => (
                                        <div
                                            key={col}
                                            className={`h-10 sm:h-12 rounded-xl flex items-center justify-center font-black text-base sm:text-lg border shadow-sm select-none ${columnColors[idx]}`}
                                        >
                                            {col}
                                        </div>
                                    ))}

                                    {card.grid.map((row, rIdx) =>
                                        row.map((cell, cIdx) => {
                                            const isCenter = rIdx === 2 && cIdx === 2;
                                            const marked = isCellMarked(rIdx, cIdx);
                                            const isLastCalledMatch = isCellLastCalled(rIdx, cIdx);

                                            return (
                                                <button
                                                    key={`${rIdx}-${cIdx}`}
                                                    type="button"
                                                    onClick={() => handleDaub(rIdx, cIdx)}
                                                    disabled={marked}
                                                    aria-label={isCenter ? 'Free Space' : `Number ${cell}${marked ? ', Daubed' : ''}`}
                                                    className={`aspect-square rounded-2xl flex flex-col items-center justify-center font-bold text-sm sm:text-base border transition relative overflow-hidden select-none active:scale-95 ${
                                                        marked
                                                            ? 'bg-gradient-to-tr from-amber-500 to-amber-600 text-neutral-950 font-black border-amber-300 shadow-lg shadow-amber-500/20 ring-2 ring-amber-400/40'
                                                            : isLastCalledMatch
                                                            ? 'bg-indigo-900/60 border-indigo-400 text-white animate-pulse ring-2 ring-indigo-400'
                                                            : 'bg-slate-900/90 hover:bg-slate-800/90 border-slate-800 text-white'
                                                    }`}
                                                >
                                                    {/* Daub Stamp Ink Blot Effect */}
                                                    {marked && (
                                                        <span className="absolute inset-0 bg-amber-400/20 rounded-full blur-sm pointer-events-none" />
                                                    )}

                                                    <span className="relative z-10 text-base sm:text-lg">
                                                        {isCenter ? '★ FREE' : cell}
                                                    </span>

                                                    {marked && !isCenter && (
                                                        <span className="text-[8px] uppercase tracking-tighter opacity-80 font-black relative z-10">
                                                            DAUBED
                                                        </span>
                                                    )}
                                                </button>
                                            );
                                        })
                                    )}
                                </div>

                                <div className="mt-3.5 text-center text-[10px] text-slate-500">
                                    Tap matching cells to daub &bull; Automatic server daubing active
                                </div>

                                {/* BINGO Claim Button */}
                                <div className="mt-5 space-y-2">
                                    <button
                                        type="button"
                                        onClick={handleClaimBingo}
                                        disabled={game.status !== 'active' || claiming || claimPending}
                                        className={`w-full py-4 rounded-2xl font-black text-xl tracking-widest uppercase transition transform active:scale-95 shadow-xl flex items-center justify-center space-x-2 ${
                                            game.status === 'active' && !claiming && !claimPending
                                                ? 'bg-gradient-to-r from-amber-500 via-yellow-400 to-amber-500 text-slate-950 shadow-amber-500/30 hover:shadow-amber-500/50 hover:brightness-110 animate-pulse cursor-pointer'
                                                : 'bg-slate-800 text-slate-500 cursor-not-allowed shadow-none'
                                        }`}
                                    >
                                        {claiming ? (
                                            <span>VERIFYING CLAIM...</span>
                                        ) : claimPending ? (
                                            <span>VERIFICATION IN PROGRESS...</span>
                                        ) : (
                                            <span>BINGO! CLAIM WIN</span>
                                        )}
                                    </button>

                                {claimPending && (
                                    <div className="p-4 bg-amber-500/10 border-2 border-amber-500/40 rounded-2xl text-center text-xs text-amber-300 font-bold animate-pulse" role="status">
                                        ⏳ BINGO CLAIM SUBMITTED! Your card is being verified by the host. The game is paused. Please wait...
                                    </div>
                                )}

                                {claimError && (
                                    <div className="p-3 bg-red-950/60 border border-red-500/40 rounded-xl text-center text-xs text-red-300 font-semibold" role="alert">
                                        ❌ {claimError}
                                    </div>
                                )}

                                {claimMessage && !claimPending && (
                                    <div className="p-3 bg-emerald-950/60 border border-emerald-500/40 rounded-xl text-center text-xs text-emerald-300 font-bold" role="status">
                                        🎉 {claimMessage}
                                    </div>
                                )}
                            </div>
                        </div>
                    )}
                </div>

                    {/* Right Column: 1-75 Master Board & Match Rules */}
                    <div className="lg:col-span-6 space-y-6">
                        {/* 1-75 Master Board Widget */}
                        <div className="bg-slate-950 border border-slate-800 rounded-3xl p-5 shadow-xl">
                            <div className="flex justify-between items-center mb-3">
                                <div>
                                    <h3 className="text-sm font-black text-white uppercase tracking-wider">
                                        75-Ball Master Caller Board
                                    </h3>
                                    <span className="text-[10px] text-slate-400">
                                        Illuminated cells indicate numbers drawn by server
                                    </span>
                                </div>
                                <span className="text-xs font-mono font-bold text-amber-400 bg-amber-400/10 px-2.5 py-0.5 rounded-lg border border-amber-400/20">
                                    {calledSet.size} / 75 Drawn
                                </span>
                            </div>

                            {/* 75 Number Board (5 Rows: B, I, N, G, O) */}
                            <div className="space-y-2 mt-4">
                                {masterColumns.map((col) => {
                                    const numbers = Array.from(
                                        { length: col.range[1] - col.range[0] + 1 },
                                        (_, idx) => col.range[0] + idx
                                    );

                                    return (
                                        <div key={col.letter} className="flex items-center gap-1 sm:gap-1.5">
                                            {/* Column Header Label */}
                                            <div className="w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-slate-900 border border-slate-800 flex items-center justify-center text-xs font-black text-white flex-shrink-0">
                                                {col.letter}
                                            </div>

                                            {/* Numbers 1-15 */}
                                            <div className="grid grid-cols-15 gap-1 flex-1">
                                                {numbers.map((n) => {
                                                    const isCalled = calledSet.has(n);
                                                    const isCurrent = game.last_call?.ball_number === n;

                                                    return (
                                                        <div
                                                            key={n}
                                                            className={`aspect-square rounded-md flex items-center justify-center text-[10px] font-bold transition select-none ${
                                                                isCurrent
                                                                    ? 'bg-amber-400 text-slate-950 font-black shadow-lg shadow-amber-400/50 scale-110 z-10'
                                                                    : isCalled
                                                                    ? 'bg-indigo-600 text-white font-bold'
                                                                    : 'bg-slate-900/60 text-slate-600'
                                                            }`}
                                                            title={`${col.letter}-${n} ${isCalled ? '(Called)' : ''}`}
                                                        >
                                                            {n}
                                                        </div>
                                                    );
                                                })}
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>

                        {/* Match Invariants Card */}
                        <div className="bg-slate-950/70 border border-slate-800 rounded-3xl p-5 shadow-sm space-y-3">
                            <h3 className="text-xs font-bold text-slate-400 uppercase tracking-wider">
                                Game Rules & Specifications
                            </h3>
                            <div className="space-y-2 text-xs divide-y divide-slate-800/80">
                                <div className="flex justify-between pt-2">
                                    <span className="text-slate-400">Winning Requirement:</span>
                                    <span className="font-bold text-amber-400">
                                        {game.configuration.required_pattern_count ?? 1} Line(s) Required
                                    </span>
                                </div>
                                <div className="flex justify-between pt-2">
                                    <span className="text-slate-400">Call Interval:</span>
                                    <span className="font-bold text-white">
                                        Every {game.call_interval} seconds
                                    </span>
                                </div>
                                <div className="flex justify-between pt-2">
                                    <span className="text-slate-400">Winner Policy:</span>
                                    <span className="font-bold text-indigo-400 capitalize">
                                        {(game.configuration.winner_policy ?? 'first_valid').replace('_', ' ')}
                                    </span>
                                </div>
                                <div className="flex justify-between pt-2">
                                    <span className="text-slate-400">Ticket Entry Fee:</span>
                                    <span className="font-bold text-emerald-400">
                                        {game.entry_fee}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Real-time Winner Announcement Celebration Modal */}
            {winnerAnnouncement && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/85 backdrop-blur-md p-4 animate-in fade-in duration-300">
                    <div className="bg-gradient-to-b from-slate-900 to-slate-950 border-2 border-amber-400/80 rounded-3xl p-6 sm:p-8 max-w-md w-full shadow-2xl shadow-amber-500/20 text-center relative overflow-hidden">
                        <div className="text-5xl mb-3 animate-bounce">🏆</div>
                        <span className="text-[10px] uppercase font-black tracking-widest text-amber-400 bg-amber-500/10 px-3 py-1 rounded-full border border-amber-500/30">
                            BINGO CONFIRMED!
                        </span>

                        <h2 className="text-2xl sm:text-3xl font-black text-white mt-3">
                            {winnerAnnouncement.winner_name} WON!
                        </h2>

                        <div className="my-5 p-4 bg-slate-950/90 border border-slate-800 rounded-2xl space-y-2 text-xs text-left">
                            <div className="flex justify-between py-1 border-b border-slate-800">
                                <span className="text-slate-400">Card Number</span>
                                <span className="font-mono font-bold text-indigo-400">{winnerAnnouncement.card_number}</span>
                            </div>
                            <div className="flex justify-between py-1 border-b border-slate-800">
                                <span className="text-slate-400">Winning Ball</span>
                                <span className="font-bold text-amber-300">
                                    Ball {winnerAnnouncement.winning_ball} (Call #{winnerAnnouncement.winning_call_sequence})
                                </span>
                            </div>
                            <div className="flex justify-between py-1 border-b border-slate-800">
                                <span className="text-slate-400">Winning Pattern</span>
                                <span className="font-bold text-white">
                                    {winnerAnnouncement.patterns.join(', ') || 'Winning Line'}
                                </span>
                            </div>
                            <div className="flex justify-between py-1">
                                <span className="text-slate-400">Prize Payout</span>
                                <span className="font-black text-emerald-400 text-sm">
                                    {winnerAnnouncement.formatted_payout}
                                </span>
                            </div>
                        </div>

                        <div className="flex flex-col gap-2">
                            <Link
                                href={`/c/${companySlug}/lobby`}
                                className="w-full py-3 bg-indigo-600 hover:bg-indigo-500 text-white font-bold rounded-xl text-sm transition shadow-lg shadow-indigo-600/30"
                            >
                                Back to Room Lobby
                            </Link>
                            <button
                                type="button"
                                onClick={() => setWinnerAnnouncement(null)}
                                className="text-xs text-slate-400 hover:text-white py-1"
                            >
                                Dismiss Announcement
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </PlayerLayout>
    );
}
