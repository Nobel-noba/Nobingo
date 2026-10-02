import CompanyAdminLayout from '@/Layouts/CompanyAdminLayout';
import { PageProps } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { useState, useEffect, useRef } from 'react';

interface GamePlayerItem {
    id: number;
    user_id?: number | null;
    guest_identifier?: string | null;
    joined_at: string;
    user?: {
        id: number;
        name: string;
        email: string;
    } | null;
}

interface GameCardItem {
    id: number;
    user_id?: number | null;
    guest_identifier?: string | null;
    assigned_at: string;
    user?: {
        id?: number;
        name: string;
        email?: string;
    } | null;
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
    winners?: Array<{
        id: number;
        user_id?: number | null;
        game_card_id: number;
        winning_call_sequence: number;
        winning_ball_number: number;
        claim_type: string;
        payout_amount: number;
        split_ratio: number;
        payout_status?: string;
        claimed_at: string;
        user?: {
            name: string;
            email: string;
        } | null;
        card?: {
            id?: number;
            guest_identifier?: string | null;
            card?: {
                card_number: string | number;
            };
            version?: {
                grid?: number[][];
            };
        } | null;
        pattern?: {
            name: string;
        };
        winning_patterns_snapshot?: Array<{ name: string }>;
    }>;
    created_at: string;
}

interface CardVerificationData {
    is_valid: boolean;
    completed_count: number;
    required_count: number;
    completed_patterns?: Array<{ id: number; name: string; slug: string; coordinates?: number[][] }>;
    completed_slugs?: string[];
    grid?: number[][];
    marked_grid?: boolean[][];
    reason?: string | null;
    card_number?: number;
    game_card_id?: number;
    player_name?: string;
    is_walkin?: boolean;
    estimated_payout?: number;
    winner_id?: number;
}

interface Props extends PageProps {
    game: GameDetail;
    available_cards_count: number;
    available_card_numbers?: number[];
    company_players?: Array<{ id: number; name: string; email: string }>;
    master_board: Record<string, BoardCell[]>;
    remaining_count: number;
}

export default function GameShow({
    game,
    available_cards_count,
    available_card_numbers = [],
    company_players = [],
    master_board,
    remaining_count,
    tenant,
}: Props) {
    const companySlug = tenant?.slug || 'default';
    const [calling, setCalling] = useState(false);
    const [autoCallActive, setAutoCallActive] = useState(false);
    const [liveGame, setLiveGame] = useState(game);
    const [liveBoard, setLiveBoard] = useState(master_board);
    const [liveRemaining, setLiveRemaining] = useState(remaining_count);

    // Fullscreen Console Mode
    const [isFullscreen, setIsFullscreen] = useState(false);
    const gameConsoleRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const handleFullscreenChange = () => {
            setIsFullscreen(Boolean(document.fullscreenElement));
        };
        document.addEventListener('fullscreenchange', handleFullscreenChange);
        return () => {
            document.removeEventListener('fullscreenchange', handleFullscreenChange);
        };
    }, []);

    const toggleFullscreen = () => {
        if (!document.fullscreenElement) {
            if (gameConsoleRef.current?.requestFullscreen) {
                gameConsoleRef.current.requestFullscreen();
            } else if (document.documentElement.requestFullscreen) {
                document.documentElement.requestFullscreen();
            }
        } else {
            if (document.exitFullscreen) {
                document.exitFullscreen();
            }
        }
    };

    // Card Assignment Modal State
    const [assignModalOpen, setAssignModalOpen] = useState(false);
    const [isWalkIn, setIsWalkIn] = useState(false);
    const [selectedUserId, setSelectedUserId] = useState<number | ''>('');
    const [guestIdentifier, setGuestIdentifier] = useState<string>('');
    const [targetCardNumber, setTargetCardNumber] = useState<string>('');
    const [assigningCard, setAssigningCard] = useState(false);
    const [assignError, setAssignError] = useState<string | null>(null);

    const openAssignModal = (targetUser?: { id?: number | null; name?: string; currentCard?: number; isWalkIn?: boolean; guestIdentifier?: string }) => {
        setAssignError(null);
        if (targetUser && targetUser.isWalkIn) {
            setIsWalkIn(true);
            setSelectedUserId('');
            setGuestIdentifier(targetUser.guestIdentifier || targetUser.name || '');
            setTargetCardNumber(targetUser.currentCard ? String(targetUser.currentCard) : '');
        } else if (targetUser && targetUser.id) {
            setIsWalkIn(false);
            setSelectedUserId(targetUser.id);
            setGuestIdentifier('');
            setTargetCardNumber(targetUser.currentCard ? String(targetUser.currentCard) : '');
        } else {
            setIsWalkIn(false);
            const firstId = liveGame.players.find(p => p.user)?.user?.id || company_players[0]?.id || '';
            setSelectedUserId(firstId);
            setGuestIdentifier('');
            setTargetCardNumber('');
        }
        setAssignModalOpen(true);
    };

    const handleAssignCard = (e: React.FormEvent) => {
        e.preventDefault();
        if (!isWalkIn && !selectedUserId) return;
        if (!targetCardNumber) return;

        setAssigningCard(true);
        setAssignError(null);

        router.post(`/c/${companySlug}/admin/games/${game.id}/assign-card`, {
            is_walkin: isWalkIn,
            user_id: isWalkIn ? null : selectedUserId,
            card_number: parseInt(targetCardNumber, 10),
            guest_identifier: isWalkIn ? (guestIdentifier || `Walk-in Cash Player (#${targetCardNumber})`) : null,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setAssignModalOpen(false);
                setTargetCardNumber('');
                setIsWalkIn(false);
                setGuestIdentifier('');
                setAssigningCard(false);
            },
            onError: (errs) => {
                setAssignError(Object.values(errs).join(', ') || 'Failed to assign card.');
                setAssigningCard(false);
            },
            onFinish: () => {
                setAssigningCard(false);
            },
        });
    };

    // Verification & Claim Resolution Modal State
    const [verificationModalOpen, setVerificationModalOpen] = useState(false);
    const [verifyingCard, setVerifyingCard] = useState(false);
    const [verificationData, setVerificationData] = useState<CardVerificationData | null>(null);
    const [resolvingClaim, setResolvingClaim] = useState(false);
    const [resolveError, setResolveError] = useState<string | null>(null);

    // Quick Card Lookup Modal State
    const [lookupModalOpen, setLookupModalOpen] = useState(false);
    const [lookupCardNumber, setLookupCardNumber] = useState('');

    const openVerifyModalForWinner = async (winner: any) => {
        setResolveError(null);
        setVerifyingCard(true);
        setVerificationData(null);
        setVerificationModalOpen(true);
        try {
            const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content;
            const res = await fetch(`/c/${companySlug}/admin/games/${game.id}/verify-card`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken || '',
                },
                body: JSON.stringify({ game_card_id: winner.game_card_id }),
            });
            const data = await res.json();
            if (res.ok) {
                setVerificationData({
                    ...data,
                    winner_id: winner.id,
                    player_name: winner.user?.name || winner.card?.guest_identifier || data.player_name || 'Player',
                    estimated_payout: winner.payout_amount || data.estimated_payout,
                });
            } else {
                setResolveError(data.error || 'Failed to inspect card.');
            }
        } catch {
            setResolveError('Network error while inspecting card.');
        } finally {
            setVerifyingCard(false);
        }
    };

    const openVerifyModalForCard = async (target: { cardNumber?: number; gameCardId?: number; playerName?: string }) => {
        setResolveError(null);
        setVerifyingCard(true);
        setVerificationData(null);
        setVerificationModalOpen(true);
        try {
            const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content;
            const res = await fetch(`/c/${companySlug}/admin/games/${game.id}/verify-card`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken || '',
                },
                body: JSON.stringify({
                    card_number: target.cardNumber,
                    game_card_id: target.gameCardId,
                }),
            });
            const data = await res.json();
            if (res.ok) {
                setVerificationData({
                    ...data,
                    player_name: target.playerName || data.player_name,
                });
            } else {
                setResolveError(data.error || 'Failed to inspect card.');
            }
        } catch {
            setResolveError('Network error while inspecting card.');
        } finally {
            setVerifyingCard(false);
        }
    };

    const handleConfirmClaim = () => {
        if (!verificationData?.winner_id) return;
        setResolvingClaim(true);
        setResolveError(null);

        router.post(`/c/${companySlug}/admin/games/${game.id}/claims/${verificationData.winner_id}/confirm`, {}, {
            preserveScroll: true,
            onSuccess: () => {
                setVerificationModalOpen(false);
                setResolvingClaim(false);
            },
            onError: (errs) => {
                setResolveError(Object.values(errs).join(', ') || 'Failed to confirm claim.');
                setResolvingClaim(false);
            },
            onFinish: () => setResolvingClaim(false),
        });
    };

    const handleRejectClaim = () => {
        if (!verificationData?.winner_id) return;
        if (!confirm('Are you sure you want to reject this claim? The game will remain paused so you can resume calling balls.')) return;
        setResolvingClaim(true);
        setResolveError(null);

        router.post(`/c/${companySlug}/admin/games/${game.id}/claims/${verificationData.winner_id}/reject`, {}, {
            preserveScroll: true,
            onSuccess: () => {
                setVerificationModalOpen(false);
                setResolvingClaim(false);
            },
            onError: (errs) => {
                setResolveError(Object.values(errs).join(', ') || 'Failed to reject claim.');
                setResolvingClaim(false);
            },
            onFinish: () => setResolvingClaim(false),
        });
    };

    const handleDeclareWalkInWinner = () => {
        if (!verificationData?.game_card_id && !verificationData?.card_number) return;
        setResolvingClaim(true);
        setResolveError(null);

        router.post(`/c/${companySlug}/admin/games/${game.id}/declare-walkin-winner`, {
            game_card_id: verificationData.game_card_id,
            card_number: verificationData.card_number,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setVerificationModalOpen(false);
                setResolvingClaim(false);
            },
            onError: (errs) => {
                setResolveError(Object.values(errs).join(', ') || 'Failed to declare walk-in winner.');
                setResolvingClaim(false);
            },
            onFinish: () => setResolvingClaim(false),
        });
    };

    const pendingWinners = liveGame.winners?.filter(w => w.payout_status === 'pending') || [];

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

        channel.listen('.game.won', () => {
            router.reload({ only: ['game'] });
        });

        channel.listen('.bingo.claim.submitted', () => {
            router.reload({ only: ['game'] });
        });

        channel.listen('.bingo.claim.rejected', () => {
            router.reload({ only: ['game'] });
        });

        return () => {
            channel.stopListening('.number.called');
            channel.stopListening('.game.state.changed');
            channel.stopListening('.player.joined');
            channel.stopListening('.game.won');
            channel.stopListening('.bingo.claim.submitted');
            channel.stopListening('.bingo.claim.rejected');
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
                            liveGame.status === 'open'
                                ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20'
                                : liveGame.status === 'active'
                                ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20 animate-pulse'
                                : liveGame.status === 'starting'
                                ? 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/20'
                                : 'bg-neutral-500/10 text-neutral-400 border border-neutral-500/20'
                        }`}>
                            {liveGame.status}
                        </span>
                    </div>

                    {/* Operator Control Actions */}
                    <div className="flex items-center space-x-2">
                        {liveGame.status === 'draft' && (
                            <button
                                onClick={() => handleTransition('open')}
                                className="bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold px-4 py-2 rounded-xl shadow-md transition"
                            >
                                Open Room for Players
                            </button>
                        )}

                        {liveGame.status === 'open' && (
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

                        {liveGame.status === 'active' && (
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
                        {liveGame.status === 'paused' && (
                            <button
                                onClick={() => handleTransition('active')}
                                className="bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold px-4 py-2 rounded-xl transition"
                            >
                                Resume Session
                            </button>
                        )}

                        <button
                            type="button"
                            onClick={() => setLookupModalOpen(true)}
                            className="bg-neutral-800 hover:bg-neutral-700 text-neutral-200 border border-neutral-700 text-xs font-bold px-3 py-2 rounded-xl transition flex items-center gap-1.5 shadow-sm"
                        >
                            <span>🔍</span> Check Card
                        </button>

                        <Link
                            href={`/c/${companySlug}/admin/games/${game.id}/audit`}
                            className="bg-indigo-950/70 hover:bg-indigo-900 text-indigo-300 border border-indigo-700/60 text-xs font-bold px-3.5 py-2 rounded-xl transition flex items-center shadow-sm"
                        >
                            Audit & Replay
                        </Link>

                        <button
                            type="button"
                            onClick={toggleFullscreen}
                            className="bg-neutral-800 hover:bg-neutral-700 text-neutral-200 border border-neutral-700 text-xs font-bold px-3 py-2 rounded-xl transition flex items-center gap-1.5 shadow-sm cursor-pointer"
                            title={isFullscreen ? 'Exit Fullscreen' : 'Enter Fullscreen Live Console'}
                        >
                            <span>{isFullscreen ? '✕' : '⛶'}</span>
                            <span>{isFullscreen ? 'Exit Fullscreen' : 'Fullscreen'}</span>
                        </button>
                    </div>
                </div>
            }
        >
            <Head title={`Game #${game.game_number}`} />

            <div
                ref={gameConsoleRef}
                className={`transition-all ${
                    isFullscreen
                        ? 'bg-neutral-950 p-4 sm:p-6 min-h-screen text-white flex flex-col justify-start space-y-4'
                        : 'space-y-5'
                }`}
            >
                {/* Fullscreen Dedicated Operator Bar */}
                {isFullscreen && (
                    <div className="bg-neutral-900/95 border border-neutral-800 rounded-2xl p-4 flex flex-wrap items-center justify-between gap-4 shadow-2xl sticky top-0 z-30 backdrop-blur-md">
                        <div className="flex items-center space-x-3">
                            <span className="font-mono text-xs text-amber-400 font-black bg-amber-400/10 border border-amber-400/20 px-3 py-1.5 rounded-xl">
                                Game #{game.game_number}
                            </span>
                            <span className="font-extrabold text-white text-base tracking-tight">{game.name}</span>
                            <span className="px-2.5 py-1 rounded-full text-[10px] font-black uppercase bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                                {liveGame.status}
                            </span>
                            <span className="text-xs text-neutral-400 font-mono">
                                Pot: <strong className="text-emerald-400 font-bold">${((liveGame.entry_fee * (liveGame.players?.length || liveGame.cards?.length || 0)) / 100).toFixed(2)}</strong>
                            </span>
                        </div>

                        <div className="flex items-center space-x-2">
                            <button
                                type="button"
                                onClick={() => setLookupModalOpen(true)}
                                className="bg-neutral-800 hover:bg-neutral-700 text-neutral-200 text-xs font-bold px-3.5 py-1.5 rounded-xl transition flex items-center gap-1.5 border border-neutral-700"
                            >
                                <span>🔍</span> Check Card
                            </button>
                            <button
                                type="button"
                                onClick={toggleFullscreen}
                                className="bg-rose-600/20 hover:bg-rose-600/30 text-rose-300 border border-rose-500/40 text-xs font-bold px-3.5 py-1.5 rounded-xl transition flex items-center gap-1.5 cursor-pointer"
                            >
                                <span>✕</span> Exit Fullscreen
                            </button>
                        </div>
                    </div>
                )}
                {/* Pending Bingo Claims Alert Banner */}
                {pendingWinners.length > 0 && (
                    <div className="p-4 rounded-2xl bg-gradient-to-r from-amber-500/20 via-yellow-500/20 to-amber-500/20 border-2 border-amber-500 text-amber-200 flex flex-col md:flex-row md:items-center justify-between gap-4 shadow-xl">
                        <div className="flex items-center gap-3">
                            <span className="text-3xl animate-bounce">🚨</span>
                            <div>
                                <div className="font-black text-sm text-amber-100 uppercase tracking-wider flex items-center gap-2">
                                    <span>BINGO CLAIM PENDING VERIFICATION ({pendingWinners.length})</span>
                                    <span className="px-2 py-0.5 rounded-full bg-amber-400 text-neutral-950 text-[10px] font-black uppercase">
                                        Action Required
                                    </span>
                                </div>
                                <div className="text-xs text-amber-200/90 mt-0.5">
                                    {pendingWinners[0].user?.name || pendingWinners[0].card?.guest_identifier || 'Player'} has called BINGO on Card #{pendingWinners[0].card?.card?.card_number || pendingWinners[0].game_card_id}! Game is PAUSED. Inspect the card to approve win & release payout, or reject false claim.
                                </div>
                            </div>
                        </div>
                        <div className="flex items-center gap-2">
                            <button
                                type="button"
                                onClick={() => openVerifyModalForWinner(pendingWinners[0])}
                                className="px-5 py-2.5 rounded-xl bg-amber-400 hover:bg-amber-300 text-neutral-950 font-black text-xs transition shadow-lg whitespace-nowrap flex items-center gap-1.5 cursor-pointer"
                            >
                                <span>🔍</span>
                                <span>Inspect & Verify Card</span>
                            </button>
                        </div>
                    </div>
                )}
                {/* Company Credit Warning Banner */}
                {!isFullscreen && ((tenant as any)?.credit_balance ?? 0) <= 0 && ['draft', 'open'].includes(liveGame.status) && (
                    <div className="p-4 rounded-2xl bg-amber-500/10 border border-amber-500/30 text-amber-300 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 shadow-md">
                        <div className="flex items-center gap-3">
                            <span className="text-2xl">⚠️</span>
                            <div>
                                <div className="font-bold text-sm text-amber-200">Company Credit Balance is $0.00</div>
                                <div className="text-xs text-amber-300/80">
                                    You must purchase platform credits before activating or starting this game.
                                </div>
                            </div>
                        </div>
                        <Link
                            href={`/c/${companySlug}/admin/credits`}
                            className="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-neutral-950 font-bold text-xs text-center transition shadow-sm whitespace-nowrap"
                        >
                            Buy Platform Credits &rarr;
                        </Link>
                    </div>
                )}

                {/* Caller Console: Fullscreen Arena (20% Current Draw + 80% Master Caller Board) or Standard Single-View Layout */}
                {isFullscreen ? (
                    <div className="flex-1 flex flex-col justify-between bg-neutral-900 border border-neutral-800 rounded-3xl p-4 sm:p-6 shadow-2xl">
                        {/* 2-Column Split: Current Draw (20%) and Master Caller Board (80%) */}
                        <div className="flex flex-col lg:flex-row gap-5 lg:gap-6 items-stretch flex-1">
                            {/* Current Draw (Left ~20%) */}
                            <div className="w-full lg:w-[20%] xl:w-[20%] shrink-0 flex flex-col justify-between bg-neutral-950/70 border border-neutral-800/80 rounded-2xl p-4 sm:p-5">
                                <div className="flex flex-col items-center text-center space-y-4 my-auto">
                                    <div className="text-xs font-bold text-neutral-400 uppercase tracking-widest">
                                        Current Draw
                                    </div>

                                    {/* Jumbo Ball */}
                                    <div className="relative">
                                        {latestCall ? (
                                            <div className={`w-28 h-28 sm:w-36 sm:h-36 lg:w-40 lg:h-40 xl:w-48 xl:h-48 rounded-full bg-gradient-to-tr ${getLetterColor(latestCall.letter)} flex flex-col items-center justify-center shadow-2xl border-4 ring-8 ring-white/10 animate-pulse`}>
                                                <span className="text-sm sm:text-base font-black tracking-widest uppercase opacity-90">{latestCall.letter}</span>
                                                <span className="text-3xl sm:text-4xl lg:text-5xl xl:text-6xl font-black leading-none">{latestCall.ball_number}</span>
                                            </div>
                                        ) : (
                                            <div className="w-28 h-28 sm:w-36 sm:h-36 lg:w-40 lg:h-40 xl:w-48 xl:h-48 rounded-full bg-neutral-950 border-2 border-dashed border-neutral-700 flex flex-col items-center justify-center text-neutral-500 text-sm font-bold">
                                                <span>NO BALL</span>
                                                <span>CALLED</span>
                                            </div>
                                        )}
                                    </div>

                                    {/* Draw Status */}
                                    <div>
                                        <div className="text-xl sm:text-2xl xl:text-3xl font-black text-white">
                                            {latestCall ? `${latestCall.letter}-${latestCall.ball_number}` : 'Awaiting Call'}
                                        </div>
                                        <div className="text-xs sm:text-sm text-neutral-400 mt-2 flex flex-col items-center gap-1 font-mono">
                                            <span>Drawn: <strong className="text-white font-mono">#{liveGame.calls?.length || 0} / 75</strong></span>
                                            <span>Remaining: <strong className="text-amber-400 font-mono">{liveRemaining}</strong></span>
                                        </div>
                                    </div>
                                </div>

                                {/* Caller Controls */}
                                {liveGame.status === 'active' && (
                                    <div className="flex flex-col gap-2 pt-4 border-t border-neutral-800/80 mt-4">
                                        <button
                                            onClick={handleCallNext}
                                            disabled={calling || liveRemaining === 0}
                                            className="w-full bg-amber-500 hover:bg-amber-400 disabled:opacity-50 text-neutral-950 font-black px-4 py-3 rounded-xl shadow-lg shadow-amber-500/20 text-sm flex items-center justify-center space-x-1.5 transition cursor-pointer"
                                        >
                                            <span>{calling ? 'Drawing...' : 'Call Next Ball'}</span>
                                            <span className="text-[10px] bg-neutral-950/20 px-1.5 py-0.5 rounded font-mono">1..75</span>
                                        </button>

                                        <button
                                            onClick={() => setAutoCallActive(!autoCallActive)}
                                            className={`w-full px-3 py-2.5 rounded-xl text-xs font-bold transition flex items-center justify-center space-x-2 border whitespace-nowrap cursor-pointer ${
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

                            {/* Master Caller Board (Right ~80%) */}
                            <div className="w-full lg:w-[80%] xl:w-[80%] flex-1 flex flex-col justify-between bg-neutral-950/80 border border-neutral-800/80 rounded-2xl p-4 sm:p-5 shadow-inner">
                                <div className="flex justify-between items-center mb-3 sm:mb-4 px-1">
                                    <span className="text-xs sm:text-sm font-bold text-neutral-400 uppercase tracking-wider">
                                        Master Caller Board (1–75)
                                    </span>
                                    <span className="text-xs text-amber-400 font-mono font-bold bg-amber-500/10 px-3 py-1 rounded-lg border border-amber-500/20">
                                        {liveGame.calls?.length || 0} / 75 Drawn
                                    </span>
                                </div>

                                <div className="space-y-2 sm:space-y-3 flex-1 flex flex-col justify-around">
                                    {['B', 'I', 'N', 'G', 'O'].map((letter) => {
                                        const rowNumbers = liveBoard?.[letter] || [];
                                        return (
                                            <div key={letter} className="flex items-center gap-2 sm:gap-3 w-full">
                                                {/* Letter Badge */}
                                                <div className={`w-10 h-10 sm:w-12 sm:h-12 lg:w-14 lg:h-14 xl:w-16 xl:h-16 rounded-xl flex items-center justify-center font-black text-base sm:text-xl lg:text-2xl shrink-0 border ${
                                                    letter === 'B' ? 'bg-rose-500/20 text-rose-400 border-rose-500/30' :
                                                    letter === 'I' ? 'bg-amber-500/20 text-amber-400 border-amber-500/30' :
                                                    letter === 'N' ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30' :
                                                    letter === 'G' ? 'bg-sky-500/20 text-sky-400 border-sky-500/30' :
                                                    'bg-purple-500/20 text-purple-400 border-purple-500/30'
                                                }`}>
                                                    {letter}
                                                </div>

                                                {/* 15 Compact Square Number Cells side by side */}
                                                <div className="grid grid-cols-15 gap-1 sm:gap-1.5 lg:gap-2 flex-1 w-full">
                                                    {rowNumbers.map((cell) => (
                                                        <div
                                                            key={cell.number}
                                                            title={cell.is_called ? `Ball #${cell.number} (Call #${cell.sequence_index})` : `Ball #${cell.number} (Uncalled)`}
                                                            className={`h-10 sm:h-12 lg:h-14 xl:h-16 rounded-xl flex items-center justify-center text-sm sm:text-base lg:text-xl xl:text-2xl font-black select-none transition ${
                                                                cell.is_called
                                                                    ? 'bg-amber-400 text-neutral-950 font-black shadow-lg ring-2 ring-amber-300 scale-105 z-10'
                                                                    : 'bg-neutral-900/90 text-neutral-300 border border-neutral-800/90 hover:border-neutral-700'
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

                                {/* Fullscreen Recent Calls Ticker */}
                                {liveGame.calls && liveGame.calls.length > 0 && (
                                    <div className="mt-4 pt-3 border-t border-neutral-800 flex items-center gap-2 overflow-x-auto py-1">
                                        <span className="text-xs font-bold text-neutral-400 uppercase tracking-wider shrink-0">
                                            Recent Calls:
                                        </span>
                                        <div className="flex items-center gap-2 flex-nowrap">
                                            {liveGame.calls.slice(-18).reverse().map((call) => (
                                                <div
                                                    key={call.id}
                                                    className="bg-neutral-900 border border-neutral-800 px-2.5 py-1 rounded-xl text-xs flex items-center space-x-1.5 shrink-0"
                                                >
                                                    <span className="text-neutral-500 font-mono text-[10px]">#{call.sequence_index}</span>
                                                    <span className="font-extrabold text-white">{call.letter}-{call.ball_number}</span>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                ) : (
                    /* Standard Compact Master Ball Calling Console (Single-View Layout) */
                    <div className="bg-neutral-900 border border-neutral-800 rounded-3xl p-4 sm:p-5 shadow-xl">
                        <div className="grid grid-cols-1 xl:grid-cols-12 gap-5 items-center">
                            {/* Call Station / Left Deck (xl:col-span-4) */}
                            <div className="xl:col-span-4 flex flex-col justify-between space-y-4">
                                <div className="flex items-center space-x-4">
                                    {/* Jumbo Ball */}
                                    <div className="relative shrink-0">
                                        {latestCall ? (
                                            <div className={`w-20 h-20 sm:w-22 sm:h-22 rounded-full bg-gradient-to-tr ${getLetterColor(latestCall.letter)} flex flex-col items-center justify-center shadow-2xl border-4 ring-4 ring-white/10 animate-bounce`}>
                                                <span className="text-xs font-black tracking-widest uppercase opacity-90">{latestCall.letter}</span>
                                                <span className="text-2xl sm:text-3xl font-extrabold leading-none">{latestCall.ball_number}</span>
                                            </div>
                                        ) : (
                                            <div className="w-20 h-20 sm:w-22 sm:h-22 rounded-full bg-neutral-950 border-2 border-dashed border-neutral-700 flex flex-col items-center justify-center text-neutral-500 text-xs font-bold">
                                                <span>NO BALL</span>
                                                <span>CALLED</span>
                                            </div>
                                        )}
                                    </div>

                                    {/* Draw Status */}
                                    <div>
                                        <div className="text-[10px] font-bold text-neutral-400 uppercase tracking-wider">
                                            Current Draw
                                        </div>
                                        <div className="text-xl sm:text-2xl font-black text-white mt-0.5">
                                            {latestCall ? `${latestCall.letter}-${latestCall.ball_number}` : 'Awaiting Call'}
                                        </div>
                                        <div className="text-xs text-neutral-400 mt-1 flex flex-wrap items-center gap-2">
                                            <span>Drawn: <strong className="text-white font-mono">#{liveGame.calls?.length || 0}/75</strong></span>
                                            <span className="text-neutral-600">&bull;</span>
                                            <span>Remaining: <strong className="text-amber-400 font-mono">{liveRemaining}</strong></span>
                                        </div>
                                    </div>
                                </div>

                                {/* Caller Controls */}
                                {liveGame.status === 'active' && (
                                    <div className="flex items-center gap-2 pt-1">
                                        <button
                                            onClick={handleCallNext}
                                            disabled={calling || liveRemaining === 0}
                                            className="flex-1 bg-amber-500 hover:bg-amber-400 disabled:opacity-50 text-neutral-950 font-black px-4 py-2.5 rounded-xl shadow-lg shadow-amber-500/20 text-xs sm:text-sm flex items-center justify-center space-x-1.5 transition cursor-pointer"
                                        >
                                            <span>{calling ? 'Drawing...' : 'Call Next Ball'}</span>
                                            <span className="text-[10px] bg-neutral-950/20 px-1.5 py-0.5 rounded font-mono">1..75</span>
                                        </button>

                                        <button
                                            onClick={() => setAutoCallActive(!autoCallActive)}
                                            className={`px-3 py-2.5 rounded-xl text-xs font-bold transition flex items-center space-x-1.5 border whitespace-nowrap cursor-pointer ${
                                                autoCallActive
                                                    ? 'bg-rose-500/20 text-rose-300 border-rose-500/40 hover:bg-rose-500/30'
                                                    : 'bg-neutral-800 text-neutral-300 border-neutral-700 hover:bg-neutral-700'
                                            }`}
                                        >
                                            <span className={`w-2 h-2 rounded-full ${autoCallActive ? 'bg-rose-400 animate-ping' : 'bg-neutral-500'}`} />
                                            <span>{autoCallActive ? 'Stop Auto' : `Auto (${liveGame.call_interval}s)`}</span>
                                        </button>
                                    </div>
                                )}
                            </div>

                            {/* Compact 75-Ball Matrix Board (xl:col-span-8) */}
                            <div className="xl:col-span-8 flex justify-center xl:justify-start overflow-x-auto py-1">
                                <div className="w-fit bg-neutral-950/80 border border-neutral-800/80 rounded-2xl p-3 sm:p-3.5 shadow-inner">
                                    <div className="flex justify-between items-center mb-2 px-1 gap-4">
                                        <span className="text-[11px] font-bold text-neutral-400 uppercase tracking-wider">
                                            Master Caller Board (1–75)
                                        </span>
                                        <span className="text-[10px] text-amber-400/90 font-mono font-bold bg-amber-500/10 px-2 py-0.5 rounded border border-amber-500/20">
                                            {liveGame.calls?.length || 0} / 75 Drawn
                                        </span>
                                    </div>

                                    <div className="space-y-1.5 w-fit">
                                        {['B', 'I', 'N', 'G', 'O'].map((letter) => {
                                            const rowNumbers = liveBoard?.[letter] || [];
                                            return (
                                                <div key={letter} className="flex items-center space-x-1 sm:space-x-1.5 w-fit">
                                                    {/* Letter Badge */}
                                                    <div className={`w-7 h-7 sm:w-8 sm:h-8 rounded-lg flex items-center justify-center font-black text-xs sm:text-sm shrink-0 border ${
                                                        letter === 'B' ? 'bg-rose-500/20 text-rose-400 border-rose-500/30' :
                                                        letter === 'I' ? 'bg-amber-500/20 text-amber-400 border-amber-500/30' :
                                                        letter === 'N' ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30' :
                                                        letter === 'G' ? 'bg-sky-500/20 text-sky-400 border-sky-500/30' :
                                                        'bg-purple-500/20 text-purple-400 border-purple-500/30'
                                                    }`}>
                                                        {letter}
                                                    </div>

                                                    {/* 15 Compact Square Number Cells side by side */}
                                                    <div className="flex items-center space-x-1 sm:space-x-1.5 w-fit">
                                                        {rowNumbers.map((cell) => (
                                                            <div
                                                                key={cell.number}
                                                                title={cell.is_called ? `Ball #${cell.number} (Call #${cell.sequence_index})` : `Ball #${cell.number} (Uncalled)`}
                                                                className={`w-7 h-7 sm:w-8 sm:h-8 rounded-lg flex items-center justify-center text-xs sm:text-sm font-black select-none transition shrink-0 ${
                                                                    cell.is_called
                                                                        ? 'bg-amber-400 text-neutral-950 font-black shadow-md ring-2 ring-amber-300 scale-105 z-10'
                                                                        : 'bg-neutral-900/90 text-neutral-300 border border-neutral-800/90 hover:border-neutral-700'
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
                            </div>
                        </div>

                        {/* Compact Recent Calls Ticker */}
                        {liveGame.calls && liveGame.calls.length > 0 && (
                            <div className="mt-3 pt-3 border-t border-neutral-800 flex items-center gap-2 overflow-x-auto py-0.5">
                                <span className="text-[10px] font-bold text-neutral-400 uppercase tracking-wider shrink-0">
                                    Recent Calls:
                                </span>
                                <div className="flex items-center gap-1.5 flex-nowrap">
                                    {liveGame.calls.slice(-14).reverse().map((call) => (
                                        <div
                                            key={call.id}
                                            className="bg-neutral-950 border border-neutral-800 px-2 py-0.5 rounded-lg text-[11px] flex items-center space-x-1 shrink-0"
                                        >
                                            <span className="text-neutral-500 font-mono text-[9px]">#{call.sequence_index}</span>
                                            <span className="font-extrabold text-white">{call.letter}-{call.ball_number}</span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                )}

                {/* Verified Winners Banner */}
                {!isFullscreen && liveGame.winners && liveGame.winners.length > 0 && (
                    <div className="bg-gradient-to-r from-amber-950/40 via-neutral-900 to-amber-950/40 border-2 border-amber-500/50 rounded-3xl p-6 shadow-xl shadow-amber-500/10 mb-6">
                        <div className="flex items-center space-x-3 mb-4">
                            <span className="text-3xl">🏆</span>
                            <div>
                                <h2 className="text-lg font-black text-amber-300">
                                    Verified Game Winner{liveGame.winners.length > 1 ? 's' : ''} ({liveGame.winners.length})
                                </h2>
                                <p className="text-xs text-neutral-400">
                                    Server-authoritatively verified claims and pattern evaluations
                                </p>
                            </div>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            {liveGame.winners.map((winner) => {
                                const patterns = winner.winning_patterns_snapshot || [];
                                const patternNames = patterns.map((p) => p.name).join(', ') || winner.pattern?.name || 'Winning Pattern';

                                return (
                                    <div
                                        key={winner.id}
                                        className="bg-neutral-950/80 border border-amber-500/30 rounded-2xl p-4 space-y-2 text-xs"
                                    >
                                        <div className="flex justify-between items-center">
                                            <span className="font-bold text-white text-sm">{winner.user?.name ?? 'Player'}</span>
                                            <span className="font-mono text-indigo-400 font-bold">
                                                {winner.card?.card?.card_number ?? `#${winner.id}`}
                                            </span>
                                        </div>
                                        <div className="text-[10px] text-neutral-400">{winner.user?.email}</div>
                                        <div className="flex justify-between py-1 border-t border-neutral-800">
                                            <span className="text-neutral-400">Winning Ball:</span>
                                            <span className="font-bold text-amber-300">
                                                Ball {winner.winning_ball_number} (Call #{winner.winning_call_sequence})
                                            </span>
                                        </div>
                                        <div className="flex justify-between py-1 border-t border-neutral-800">
                                            <span className="text-neutral-400">Pattern:</span>
                                            <span className="font-semibold text-white">{patternNames}</span>
                                        </div>
                                        <div className="flex justify-between items-center pt-1 border-t border-neutral-800">
                                            <span className="text-[10px] uppercase font-bold text-neutral-500">{winner.claim_type} claim</span>
                                            <span className="font-black text-emerald-400 text-sm">
                                                ${(winner.payout_amount / 100).toFixed(2)}
                                            </span>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                )}

                {!isFullscreen && (
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
                            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                                <div>
                                    <h2 className="text-base font-bold text-white">
                                        Registered Players & Assigned Cards ({liveGame.cards.length})
                                    </h2>
                                    <p className="text-xs text-neutral-400">
                                        Assign specific fixed cards from inventory or reassign player cards
                                    </p>
                                </div>
                                <div className="flex items-center gap-3">
                                    <span className="text-xs font-mono text-indigo-400 font-semibold">
                                        {liveGame.players.length} joined
                                    </span>
                                    <button
                                        type="button"
                                        onClick={() => openAssignModal()}
                                        className="px-3.5 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs flex items-center gap-1.5 transition shadow-sm"
                                    >
                                        <span>➕</span> Assign Card
                                    </button>
                                </div>
                            </div>

                            <div className="overflow-x-auto max-h-[380px] overflow-y-auto rounded-2xl border border-neutral-800/80">
                                <table className="min-w-full divide-y divide-neutral-800 text-sm">
                                    <thead className="bg-neutral-950 sticky top-0 z-10 text-neutral-400 text-xs uppercase tracking-wider text-left border-b border-neutral-800">
                                        <tr>
                                            <th className="px-4 py-3 font-semibold">Player</th>
                                            <th className="px-4 py-3 font-semibold">Assigned Fixed Card</th>
                                            <th className="px-4 py-3 font-semibold">Version</th>
                                            <th className="px-4 py-3 font-semibold">Joined At</th>
                                            <th className="px-4 py-3 font-semibold text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-neutral-800/80 text-neutral-200 text-xs">
                                        {liveGame.players.length === 0 && liveGame.cards.length === 0 ? (
                                            <tr>
                                                <td colSpan={5} className="px-4 py-8 text-center text-neutral-500">
                                                    No players have joined this room yet.
                                                </td>
                                            </tr>
                                        ) : (
                                            // Map through liveGame.players or fallback to liveGame.cards
                                            (liveGame.players.length > 0 ? liveGame.players : liveGame.cards.map(c => ({
                                                id: c.id,
                                                joined_at: c.assigned_at,
                                                guest_identifier: c.guest_identifier,
                                                user: c.user || null,
                                            }))).map((playerItem) => {
                                                const isWalkInPlayer = !playerItem.user || Boolean(playerItem.guest_identifier);
                                                const displayName = playerItem.user?.name || playerItem.guest_identifier || 'Walk-in Cash Player';

                                                const assignedCard = liveGame.cards.find(
                                                    (c) => (c.user_id && playerItem.user?.id && c.user_id === playerItem.user.id) ||
                                                           (c.guest_identifier && c.guest_identifier === playerItem.guest_identifier) ||
                                                           (c.id === playerItem.id)
                                                );

                                                return (
                                                    <tr key={playerItem.id} className="hover:bg-neutral-800/40">
                                                        <td className="px-4 py-3">
                                                            {isWalkInPlayer ? (
                                                                <div>
                                                                    <div className="font-bold text-amber-300 flex items-center gap-1.5">
                                                                        <span>💵</span>
                                                                        <span>{displayName}</span>
                                                                        <span className="px-1.5 py-0.5 rounded text-[9px] bg-amber-500/20 text-amber-300 border border-amber-500/30 uppercase font-black tracking-wide">
                                                                            Walk-in Cash
                                                                        </span>
                                                                    </div>
                                                                    <div className="text-[10px] text-neutral-500 font-mono">Paid entry fee at venue</div>
                                                                </div>
                                                            ) : (
                                                                <div>
                                                                    <div className="font-bold text-white">{playerItem.user?.name}</div>
                                                                    <div className="text-[10px] text-neutral-500 font-mono">{playerItem.user?.email}</div>
                                                                </div>
                                                            )}
                                                        </td>
                                                        <td className="px-4 py-3 font-mono font-bold">
                                                            {assignedCard ? (
                                                                <span className="text-amber-400">
                                                                    #{String(assignedCard.card.card_number).padStart(6, '0')}
                                                                </span>
                                                            ) : (
                                                                <span className="text-neutral-500 italic">No card assigned</span>
                                                            )}
                                                        </td>
                                                        <td className="px-4 py-3 text-indigo-400 font-semibold">
                                                            {assignedCard ? `v${assignedCard.version.version_number}` : '-'}
                                                        </td>
                                                        <td className="px-4 py-3 text-neutral-400">
                                                            {new Date(assignedCard?.assigned_at || playerItem.joined_at).toLocaleTimeString()}
                                                        </td>
                                                        <td className="px-4 py-3 text-right">
                                                            <div className="flex items-center justify-end gap-1.5">
                                                                {assignedCard && (
                                                                    <button
                                                                        type="button"
                                                                        onClick={() => openVerifyModalForCard({
                                                                            cardNumber: assignedCard.card.card_number,
                                                                            gameCardId: assignedCard.id,
                                                                            playerName: displayName,
                                                                        })}
                                                                        className="px-2.5 py-1 rounded-lg bg-indigo-950 hover:bg-indigo-900 text-indigo-300 font-bold text-xs transition border border-indigo-700/60"
                                                                    >
                                                                        Check BINGO
                                                                    </button>
                                                                )}
                                                                <button
                                                                    type="button"
                                                                    onClick={() => openAssignModal({
                                                                        id: playerItem.user?.id || null,
                                                                        name: displayName,
                                                                        isWalkIn: isWalkInPlayer,
                                                                        guestIdentifier: playerItem.guest_identifier || '',
                                                                        currentCard: assignedCard?.card?.card_number,
                                                                    })}
                                                                    className="px-2.5 py-1 rounded-lg bg-neutral-800 hover:bg-neutral-700 text-amber-400 hover:text-amber-300 font-bold text-xs transition border border-neutral-700"
                                                                >
                                                                    {assignedCard ? 'Change Card' : 'Assign Card'}
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                );
                                            })
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                )}

                {/* Specific Card Assignment Modal */}
                {assignModalOpen && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
                        <div className="bg-neutral-900 border border-neutral-800 rounded-3xl max-w-lg w-full p-6 shadow-2xl space-y-6">
                            <div className="flex justify-between items-center pb-4 border-b border-neutral-800">
                                <div>
                                    <h3 className="text-base font-bold text-white">Assign Fixed Card</h3>
                                    <p className="text-xs text-neutral-400 mt-0.5">
                                        Allocate an inventory card to an online player or walk-in cash customer
                                    </p>
                                </div>
                                <button
                                    onClick={() => setAssignModalOpen(false)}
                                    className="text-neutral-400 hover:text-white text-lg p-1"
                                >
                                    &times;
                                </button>
                            </div>

                            {assignError && (
                                <div className="p-3.5 rounded-2xl bg-rose-950/60 border border-rose-500/40 text-rose-300 text-xs">
                                    {assignError}
                                </div>
                            )}

                            <form onSubmit={handleAssignCard} className="space-y-4">
                                <div>
                                    <label className="block text-xs font-semibold text-neutral-300 mb-2">
                                        Player Type
                                    </label>
                                    <div className="grid grid-cols-2 gap-2 p-1 bg-neutral-950 border border-neutral-800 rounded-xl">
                                        <button
                                            type="button"
                                            onClick={() => setIsWalkIn(false)}
                                            className={`py-2 px-3 rounded-lg text-xs font-bold transition flex items-center justify-center gap-1.5 ${
                                                !isWalkIn
                                                    ? 'bg-indigo-600 text-white shadow-sm'
                                                    : 'text-neutral-400 hover:text-white'
                                            }`}
                                        >
                                            <span>📱</span> Registered Online
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => setIsWalkIn(true)}
                                            className={`py-2 px-3 rounded-lg text-xs font-bold transition flex items-center justify-center gap-1.5 ${
                                                isWalkIn
                                                    ? 'bg-amber-500 text-neutral-950 shadow-sm'
                                                    : 'text-neutral-400 hover:text-white'
                                            }`}
                                        >
                                            <span>💵</span> Walk-in Cash
                                        </button>
                                    </div>
                                </div>

                                {isWalkIn ? (
                                    <div>
                                        <label className="block text-xs font-semibold text-neutral-300 mb-1">
                                            Guest Identifier / Table Note <span className="text-neutral-500">(Optional)</span>
                                        </label>
                                        <input
                                            type="text"
                                            value={guestIdentifier}
                                            onChange={(e) => setGuestIdentifier(e.target.value)}
                                            placeholder="e.g. Table 4 - Alex, Cash Guest #1"
                                            className="w-full bg-neutral-950 border border-neutral-800 rounded-xl px-3.5 py-2.5 text-xs text-white placeholder-neutral-600 focus:border-amber-500 focus:outline-none"
                                        />
                                        <p className="text-[11px] text-amber-400/80 mt-1">
                                            Walk-in player pays cash over counter. Identified by their card number.
                                        </p>
                                    </div>
                                ) : (
                                    <div>
                                        <label className="block text-xs font-semibold text-neutral-300 mb-1">
                                            Online Player <span className="text-rose-400">*</span>
                                        </label>
                                        <select
                                            value={selectedUserId}
                                            onChange={(e) => setSelectedUserId(Number(e.target.value))}
                                            className="w-full bg-neutral-950 border border-neutral-800 rounded-xl px-3.5 py-2.5 text-xs text-white focus:border-indigo-500 focus:outline-none"
                                        >
                                            <option value="">-- Choose Player --</option>
                                            {company_players && company_players.length > 0 ? (
                                                company_players.map((p) => (
                                                    <option key={p.id} value={p.id}>
                                                        {p.name} ({p.email})
                                                    </option>
                                                ))
                                            ) : (
                                                liveGame.players.filter(p => p.user).map((p) => (
                                                    <option key={p.user!.id} value={p.user!.id}>
                                                        {p.user!.name} ({p.user!.email})
                                                    </option>
                                                ))
                                            )}
                                        </select>
                                    </div>
                                )}

                                <div>
                                    <label className="block text-xs font-semibold text-neutral-300 mb-1">
                                        Target Card Number <span className="text-rose-400">*</span>
                                    </label>
                                    <input
                                        type="number"
                                        min="1"
                                        required
                                        value={targetCardNumber}
                                        onChange={(e) => setTargetCardNumber(e.target.value)}
                                        placeholder="e.g. 42 or 100"
                                        className="w-full bg-neutral-950 border border-neutral-800 rounded-xl px-3.5 py-2.5 text-xs text-white placeholder-neutral-600 focus:border-indigo-500 focus:outline-none"
                                    />
                                    <p className="text-[11px] text-neutral-500 mt-1">
                                        Input the exact card number from inventory (e.g. 1 to {available_cards_count || 1000})
                                    </p>
                                </div>

                                {/* Quick pick chips */}
                                {available_card_numbers && available_card_numbers.length > 0 && (
                                    <div>
                                        <span className="text-[10px] font-bold text-neutral-400 uppercase tracking-wider block mb-1.5">
                                            Quick Pick Available Cards
                                        </span>
                                        <div className="flex flex-wrap gap-1.5 max-h-24 overflow-y-auto pr-1">
                                            {available_card_numbers.slice(0, 20).map((num) => (
                                                <button
                                                    key={num}
                                                    type="button"
                                                    onClick={() => setTargetCardNumber(String(num))}
                                                    className={`px-2 py-1 rounded-lg text-xs font-mono font-bold transition border ${
                                                        targetCardNumber === String(num)
                                                            ? 'bg-amber-400 text-neutral-950 border-amber-300'
                                                            : 'bg-neutral-950 border-neutral-800 text-neutral-300 hover:border-neutral-700'
                                                    }`}
                                                >
                                                    #{String(num).padStart(4, '0')}
                                                </button>
                                            ))}
                                        </div>
                                    </div>
                                )}

                                <div className="pt-4 border-t border-neutral-800 flex justify-end gap-3">
                                    <button
                                        type="button"
                                        onClick={() => setAssignModalOpen(false)}
                                        className="px-4 py-2 rounded-xl bg-neutral-800 hover:bg-neutral-700 text-neutral-300 text-xs font-semibold"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={assigningCard || (!isWalkIn && !selectedUserId) || !targetCardNumber}
                                        className="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs disabled:opacity-50 transition shadow-sm"
                                    >
                                        {assigningCard ? 'Assigning...' : 'Assign Card'}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}

                {/* Card Verification & Claim Resolution Modal */}
                {verificationModalOpen && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/85 backdrop-blur-sm">
                        <div className="bg-neutral-900 border border-neutral-800 rounded-3xl max-w-2xl w-full p-6 shadow-2xl space-y-6 max-h-[90vh] overflow-y-auto">
                            {/* Modal Header */}
                            <div className="flex justify-between items-center pb-4 border-b border-neutral-800">
                                <div>
                                    <h3 className="text-base font-bold text-white flex items-center gap-2">
                                        <span>Bingo Card Verification</span>
                                        {verificationData?.is_walkin && (
                                            <span className="px-2 py-0.5 rounded text-[10px] bg-amber-500/20 text-amber-300 border border-amber-500/30 uppercase font-black">
                                                Walk-in Cash
                                            </span>
                                        )}
                                    </h3>
                                    <p className="text-xs text-neutral-400 mt-0.5">
                                        Card #{verificationData?.card_number ? String(verificationData.card_number).padStart(6, '0') : '-'} &bull; {verificationData?.player_name || 'Player'}
                                    </p>
                                </div>
                                <button
                                    onClick={() => setVerificationModalOpen(false)}
                                    className="text-neutral-400 hover:text-white text-xl p-1"
                                >
                                    &times;
                                </button>
                            </div>

                            {resolveError && (
                                <div className="p-3.5 rounded-2xl bg-rose-950/60 border border-rose-500/40 text-rose-300 text-xs">
                                    {resolveError}
                                </div>
                            )}

                            {verifyingCard ? (
                                <div className="py-12 text-center text-neutral-400 text-sm animate-pulse">
                                    Evaluating card patterns against called numbers...
                                </div>
                            ) : verificationData ? (
                                <div className="space-y-6">
                                    {/* Status Banner */}
                                    <div className={`p-4 rounded-2xl border ${
                                        verificationData.is_valid
                                            ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-300'
                                            : 'bg-rose-500/10 border-rose-500/30 text-rose-300'
                                    }`}>
                                        <div className="flex items-center gap-3">
                                            <span className="text-2xl">{verificationData.is_valid ? '🎉' : '❌'}</span>
                                            <div>
                                                <div className="font-black text-sm">
                                                    {verificationData.is_valid ? 'VALID WINNING BINGO!' : 'NOT A WINNING CARD'}
                                                </div>
                                                <div className="text-xs mt-0.5 opacity-90">
                                                    {verificationData.is_valid ? (
                                                        <span>
                                                            Completed {verificationData.completed_count} pattern(s):{' '}
                                                            <strong>{verificationData.completed_patterns?.map(p => p.name).join(', ') || 'Winning Line'}</strong>
                                                        </span>
                                                    ) : (
                                                        <span>{verificationData.reason || 'Pattern requirements not satisfied yet.'}</span>
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {/* 5x5 Grid Visualization */}
                                    {verificationData.grid && (
                                        <div>
                                            <div className="text-xs font-bold text-neutral-400 uppercase tracking-wider mb-2 text-center">
                                                Card Grid (Called numbers highlighted, winning pattern glowing)
                                            </div>
                                            <div className="max-w-xs mx-auto bg-neutral-950 p-3 rounded-2xl border border-neutral-800 shadow-inner">
                                                <div className="grid grid-cols-5 gap-1.5 text-center mb-1.5">
                                                    {['B', 'I', 'N', 'G', 'O'].map((letter, idx) => (
                                                        <div key={letter} className={`text-xs font-black py-1 rounded-lg ${
                                                            ['bg-rose-500/20 text-rose-400', 'bg-amber-500/20 text-amber-400', 'bg-emerald-500/20 text-emerald-400', 'bg-sky-500/20 text-sky-400', 'bg-purple-500/20 text-purple-400'][idx]
                                                        }`}>
                                                            {letter}
                                                        </div>
                                                    ))}
                                                </div>
                                                <div className="grid grid-cols-5 gap-1.5">
                                                    {verificationData.grid.map((row, rIdx) =>
                                                        row.map((cell, cIdx) => {
                                                            const isCenter = rIdx === 2 && cIdx === 2;
                                                            const isMarked = isCenter || (verificationData.marked_grid && verificationData.marked_grid[rIdx]?.[cIdx]);
                                                            
                                                            // Check if this cell is part of any completed winning pattern
                                                            const isWinningCell = verificationData.completed_patterns?.some(p => 
                                                                p.coordinates?.some((coord: any) => coord[0] === rIdx && coord[1] === cIdx)
                                                            );

                                                            return (
                                                                <div
                                                                    key={`${rIdx}-${cIdx}`}
                                                                    className={`aspect-square rounded-xl flex flex-col items-center justify-center font-bold text-xs transition border select-none ${
                                                                        isWinningCell
                                                                            ? 'bg-amber-400 text-neutral-950 border-amber-300 ring-2 ring-amber-400/50 shadow-md font-black'
                                                                            : isMarked
                                                                            ? 'bg-emerald-950/80 border-emerald-500/50 text-emerald-300 font-extrabold'
                                                                            : 'bg-neutral-900 border-neutral-800 text-neutral-400'
                                                                    }`}
                                                                >
                                                                    <span>{isCenter ? '★ FREE' : cell}</span>
                                                                    {isWinningCell && !isCenter && (
                                                                        <span className="text-[7px] leading-none uppercase tracking-tighter opacity-80 font-black">WIN</span>
                                                                    )}
                                                                </div>
                                                            );
                                                        })
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                    )}

                                    {/* Prize Info */}
                                    <div className="bg-neutral-950 p-4 rounded-2xl border border-neutral-800 flex items-center justify-between text-xs">
                                        <span className="text-neutral-400">Total Prize Payout:</span>
                                        <span className="text-base font-black text-emerald-400">
                                            ${((verificationData.estimated_payout || 0) / 100).toFixed(2)}
                                        </span>
                                    </div>

                                    {/* Action Buttons */}
                                    <div className="flex flex-col sm:flex-row justify-end gap-2.5 pt-4 border-t border-neutral-800">
                                        <button
                                            type="button"
                                            onClick={() => setVerificationModalOpen(false)}
                                            className="px-4 py-2.5 rounded-xl bg-neutral-800 hover:bg-neutral-700 text-neutral-300 text-xs font-semibold"
                                        >
                                            Close
                                        </button>

                                        {/* If verifying a pending winner claim */}
                                        {verificationData.winner_id ? (
                                            <>
                                                <button
                                                    type="button"
                                                    onClick={handleRejectClaim}
                                                    disabled={resolvingClaim}
                                                    className="px-4 py-2.5 rounded-xl bg-rose-950 hover:bg-rose-900 text-rose-300 border border-rose-700 font-bold text-xs transition disabled:opacity-50"
                                                >
                                                    {resolvingClaim ? 'Rejecting...' : 'Reject False Claim & Allow Resume'}
                                                </button>
                                                {verificationData.is_valid && (
                                                    <button
                                                        type="button"
                                                        onClick={handleConfirmClaim}
                                                        disabled={resolvingClaim}
                                                        className="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-black text-xs transition shadow-lg disabled:opacity-50 flex items-center gap-1.5"
                                                    >
                                                        <span>✓</span>
                                                        <span>{resolvingClaim ? 'Finalizing...' : 'Confirm Valid Win & Finalize Game'}</span>
                                                    </button>
                                                )}
                                            </>
                                        ) : (
                                            /* If inspecting a walk-in card */
                                            verificationData.is_valid && (
                                                <button
                                                    type="button"
                                                    onClick={handleDeclareWalkInWinner}
                                                    disabled={resolvingClaim}
                                                    className="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-black text-xs transition shadow-lg disabled:opacity-50 flex items-center gap-1.5"
                                                >
                                                    <span>✓</span>
                                                    <span>{resolvingClaim ? 'Finalizing...' : 'Declare Walk-in Winner & Finalize Game'}</span>
                                                </button>
                                            )
                                        )}
                                    </div>
                                </div>
                            ) : null}
                        </div>
                    </div>
                )}

                {/* Quick Card Lookup Modal */}
                {lookupModalOpen && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
                        <div className="bg-neutral-900 border border-neutral-800 rounded-3xl max-w-sm w-full p-6 shadow-2xl space-y-4">
                            <div className="flex justify-between items-center pb-3 border-b border-neutral-800">
                                <h3 className="text-sm font-bold text-white">Check Card for BINGO</h3>
                                <button onClick={() => setLookupModalOpen(false)} className="text-neutral-400 hover:text-white text-lg">
                                    &times;
                                </button>
                            </div>
                            <div>
                                <label className="block text-xs font-semibold text-neutral-300 mb-1.5">
                                    Card Number
                                </label>
                                <input
                                    type="number"
                                    min="1"
                                    value={lookupCardNumber}
                                    onChange={(e) => setLookupCardNumber(e.target.value)}
                                    placeholder="e.g. 5 or 42"
                                    className="w-full bg-neutral-950 border border-neutral-800 rounded-xl px-3.5 py-2.5 text-xs text-white focus:border-indigo-500 focus:outline-none"
                                />
                            </div>
                            <div className="pt-2 flex justify-end gap-2">
                                <button
                                    type="button"
                                    onClick={() => setLookupModalOpen(false)}
                                    className="px-3.5 py-2 rounded-xl bg-neutral-800 text-neutral-300 text-xs font-semibold"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="button"
                                    onClick={() => {
                                        const num = parseInt(lookupCardNumber, 10);
                                        if (!num) return;
                                        setLookupModalOpen(false);
                                        openVerifyModalForCard({ cardNumber: num });
                                    }}
                                    disabled={!lookupCardNumber}
                                    className="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs disabled:opacity-50"
                                >
                                    Verify Card
                                </button>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </CompanyAdminLayout>
    );
}
