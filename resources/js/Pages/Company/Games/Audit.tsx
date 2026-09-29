import CompanyAdminLayout from '@/Layouts/CompanyAdminLayout';
import { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';
import React, { useState } from 'react';

interface CallItem {
    sequence_index: number;
    ball_number: number;
    letter: string;
    display: string;
    called_at: string;
}

interface PlayerItem {
    user_id: number;
    name: string;
    email: string;
    card_number: string;
    card_grid: number[][];
    joined_at: string;
}

interface WinnerItem {
    id: number;
    user_name: string;
    user_email: string;
    card_number: string;
    pattern_name: string;
    winning_call_sequence: number;
    winning_ball: string;
    payout_amount: number;
    formatted_payout: string;
    payout_status: string;
    claim_type: string;
    claimed_at: string;
    patterns_snapshot: Array<{ id: number; name: string; slug: string }>;
    validation_verdict: string;
}

interface AuditLogItem {
    id: number;
    action: string;
    description: string;
    created_at: string;
}

interface Props extends PageProps {
    company: {
        id: number;
        name: string;
        slug: string;
    };
    game: {
        id: number;
        game_number: number;
        name: string;
        status: string;
        entry_fee: number;
        formatted_entry_fee: string;
        winner_policy: string;
        call_interval: number;
        configuration: Record<string, any>;
        started_at: string | null;
        ended_at: string | null;
    };
    calls: CallItem[];
    players: PlayerItem[];
    winners: WinnerItem[];
    audit_logs: AuditLogItem[];
}

export default function GameAudit({ auth, company, game, calls, players, winners, audit_logs }: Props) {
    const [selectedCardPlayer, setSelectedCardPlayer] = useState<PlayerItem | null>(null);

    const calledNumbersSet = new Set(calls.map((c) => c.ball_number));

    const getBallColor = (letter: string) => {
        switch (letter) {
            case 'B':
                return 'bg-blue-600 text-white';
            case 'I':
                return 'bg-rose-600 text-white';
            case 'N':
                return 'bg-amber-500 text-slate-950 font-black';
            case 'G':
                return 'bg-emerald-600 text-white';
            case 'O':
                return 'bg-purple-600 text-white';
            default:
                return 'bg-slate-700 text-white';
        }
    };

    return (
        <CompanyAdminLayout>
            <Head title={`Game #${game.game_number} Replay & Audit - ${company.name}`} />

            <div className="space-y-6">
                {/* Back Link & Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <Link
                            href={`/c/${company.slug}/admin/games/${game.id}`}
                            className="text-xs text-neutral-400 hover:text-white flex items-center transition mb-2"
                        >
                            ← Back to Game Monitor
                        </Link>
                        <h1 className="text-2xl font-bold text-white tracking-tight">
                            Game #{game.game_number} Verification & Replay Inspector
                        </h1>
                        <p className="text-sm text-neutral-400 mt-0.5">
                            Authoritative server timeline of all calls, card mappings, pattern matches, and claim validations.
                        </p>
                    </div>

                    <div className="flex items-center space-x-2">
                        <span className="inline-flex px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-neutral-800 text-neutral-300 border border-neutral-700">
                            Status: {game.status}
                        </span>
                    </div>
                </div>

                {/* Game Configuration Snapshot */}
                <div className="bg-neutral-900 border border-neutral-800 rounded-2xl p-5 shadow-sm">
                    <h2 className="text-xs font-bold text-neutral-400 uppercase tracking-wider mb-3">Immutable Configuration Snapshot</h2>
                    <div className="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-3 text-xs">
                        <div className="bg-neutral-950 p-3 rounded-lg border border-neutral-800">
                            <span className="text-neutral-500 block text-[10px] uppercase">Room Name</span>
                            <span className="font-semibold text-white truncate block">{game.name}</span>
                        </div>
                        <div className="bg-neutral-950 p-3 rounded-lg border border-neutral-800">
                            <span className="text-neutral-500 block text-[10px] uppercase">Entry Fee</span>
                            <span className="font-semibold text-emerald-400">{game.formatted_entry_fee}</span>
                        </div>
                        <div className="bg-neutral-950 p-3 rounded-lg border border-neutral-800">
                            <span className="text-neutral-500 block text-[10px] uppercase">Winner Policy</span>
                            <span className="font-semibold text-white uppercase">{game.winner_policy.replace('_', ' ')}</span>
                        </div>
                        <div className="bg-neutral-950 p-3 rounded-lg border border-neutral-800">
                            <span className="text-neutral-500 block text-[10px] uppercase">Call Interval</span>
                            <span className="font-semibold text-white">{game.call_interval} seconds</span>
                        </div>
                        <div className="bg-neutral-950 p-3 rounded-lg border border-neutral-800">
                            <span className="text-neutral-500 block text-[10px] uppercase">Total Balls Called</span>
                            <span className="font-semibold text-blue-400 font-mono">{calls.length} / 75</span>
                        </div>
                        <div className="bg-neutral-950 p-3 rounded-lg border border-neutral-800">
                            <span className="text-neutral-500 block text-[10px] uppercase">Total Players</span>
                            <span className="font-semibold text-white font-mono">{players.length}</span>
                        </div>
                    </div>
                </div>

                {/* Section 49: Winner Validation Verdict Banner */}
                {winners.length > 0 && (
                    <div className="bg-gradient-to-r from-amber-950/40 via-neutral-900 to-amber-950/40 border border-amber-500/40 rounded-2xl p-6 shadow-lg space-y-4">
                        <div className="flex items-center space-x-2">
                            <span className="text-2xl">🏆</span>
                            <h2 className="text-lg font-black text-amber-400 uppercase tracking-wide">
                                Verified Winning Claim(s)
                            </h2>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            {winners.map((w) => (
                                <div key={w.id} className="bg-neutral-950 border border-amber-500/30 rounded-xl p-4 space-y-2">
                                    <div className="flex justify-between items-start">
                                        <div>
                                            <div className="text-base font-bold text-white">{w.user_name}</div>
                                            <div className="text-xs text-neutral-400">{w.user_email}</div>
                                        </div>
                                        <span className="px-2 py-0.5 rounded text-[11px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/40">
                                            {w.formatted_payout}
                                        </span>
                                    </div>

                                    <div className="text-xs text-neutral-300 space-y-1 pt-2 border-t border-neutral-800 font-mono">
                                        <div>Card Assigned: <span className="text-white font-bold">{w.card_number}</span></div>
                                        <div>Winning Ball: <span className="text-amber-400 font-bold">{w.winning_ball}</span> on sequence #{w.winning_call_sequence}</div>
                                        <div>Claim Type: <span className="text-neutral-400 uppercase">{w.claim_type}</span></div>
                                        <div>Payout Status: <span className="text-emerald-400 uppercase font-semibold">{w.payout_status}</span></div>
                                    </div>

                                    <div className="pt-2 text-xs">
                                        <span className="text-[10px] text-neutral-500 uppercase font-semibold block mb-1">Pattern Validation:</span>
                                        <div className="flex flex-wrap gap-1">
                                            {w.patterns_snapshot && w.patterns_snapshot.length > 0 ? (
                                                w.patterns_snapshot.map((p, idx) => (
                                                    <span key={idx} className="px-2 py-0.5 rounded text-[10px] bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 font-semibold">
                                                        ✓ {p.name}
                                                    </span>
                                                ))
                                            ) : (
                                                <span className="px-2 py-0.5 rounded text-[10px] bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 font-semibold">
                                                    ✓ {w.pattern_name}
                                                </span>
                                            )}
                                        </div>
                                    </div>

                                    <div className="text-[11px] font-semibold text-emerald-400 pt-1">
                                        ✓ {w.validation_verdict}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* Chronological Ball Calls Sequence */}
                <div className="bg-neutral-900 border border-neutral-800 rounded-2xl p-5 shadow-sm space-y-3">
                    <div className="flex justify-between items-center">
                        <h2 className="text-sm font-bold text-white uppercase tracking-wider">
                            Chronological Call Sequence ({calls.length} Balls)
                        </h2>
                    </div>

                    {calls.length === 0 ? (
                        <p className="text-xs text-neutral-500 py-4 text-center">No balls called in this game yet.</p>
                    ) : (
                        <div className="flex flex-wrap gap-2 max-h-48 overflow-y-auto p-2 bg-neutral-950 rounded-xl border border-neutral-800">
                            {calls.map((c) => (
                                <div
                                    key={c.sequence_index}
                                    className="flex items-center space-x-1.5 bg-neutral-900 border border-neutral-800 rounded-lg px-2.5 py-1"
                                    title={`Called at ${c.called_at}`}
                                >
                                    <span className="text-[10px] font-mono text-neutral-500">#{c.sequence_index}</span>
                                    <span className={`w-6 h-6 rounded-full text-[10px] font-bold flex items-center justify-center ${getBallColor(c.letter)}`}>
                                        {c.display}
                                    </span>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {/* Participating Players & Fixed Cards */}
                <div className="bg-neutral-900 border border-neutral-800 rounded-2xl overflow-hidden shadow-sm">
                    <div className="px-6 py-4 border-b border-neutral-800 flex justify-between items-center">
                        <h2 className="text-sm font-bold text-white uppercase tracking-wider">
                            Participating Players & Assigned Fixed Cards ({players.length})
                        </h2>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm text-neutral-300">
                            <thead className="bg-neutral-950 text-xs uppercase text-neutral-400 font-medium border-b border-neutral-800">
                                <tr>
                                    <th className="px-5 py-3">Player</th>
                                    <th className="px-5 py-3">Card #</th>
                                    <th className="px-5 py-3">Joined At</th>
                                    <th className="px-5 py-3 text-right">Card Inspection</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-neutral-800">
                                {players.map((p) => (
                                    <tr key={p.user_id} className="hover:bg-neutral-800/40 transition">
                                        <td className="px-5 py-3 whitespace-nowrap">
                                            <div className="font-semibold text-neutral-200">{p.name}</div>
                                            <div className="text-xs text-neutral-500">{p.email}</div>
                                        </td>
                                        <td className="px-5 py-3 font-mono font-bold text-indigo-400">
                                            {p.card_number}
                                        </td>
                                        <td className="px-5 py-3 text-xs text-neutral-400">
                                            {p.joined_at}
                                        </td>
                                        <td className="px-5 py-3 text-right">
                                            {p.card_grid.length === 5 && (
                                                <button
                                                    onClick={() => setSelectedCardPlayer(p)}
                                                    className="text-xs bg-indigo-950/60 hover:bg-indigo-900 text-indigo-300 border border-indigo-700/60 px-3 py-1 rounded-lg transition"
                                                >
                                                    Inspect Card
                                                </button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Audit Logs Specific to this Game */}
                <div className="bg-neutral-900 border border-neutral-800 rounded-2xl p-5 shadow-sm space-y-3">
                    <h2 className="text-sm font-bold text-white uppercase tracking-wider">
                        Immutable Audit Events ({audit_logs.length})
                    </h2>
                    <div className="space-y-1.5 max-h-48 overflow-y-auto">
                        {audit_logs.map((log) => (
                            <div key={log.id} className="flex items-center justify-between text-xs py-1.5 px-3 bg-neutral-950 rounded-lg border border-neutral-800">
                                <span className="font-mono text-[11px] text-neutral-500">{log.created_at}</span>
                                <span className="font-semibold text-neutral-300 px-2">{log.description}</span>
                                <span className="font-mono text-[10px] text-indigo-400">{log.action}</span>
                            </div>
                        ))}
                    </div>
                </div>
            </div>

            {/* Inspect Card Modal with Called Ball Markers */}
            {selectedCardPlayer && (
                <div className="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
                    <div className="bg-neutral-900 border border-neutral-800 rounded-2xl max-w-sm w-full p-6 shadow-2xl space-y-4">
                        <div className="flex justify-between items-center">
                            <div>
                                <h3 className="text-base font-bold text-white">Card #{selectedCardPlayer.card_number}</h3>
                                <p className="text-xs text-neutral-400">Owner: {selectedCardPlayer.name}</p>
                            </div>
                            <button onClick={() => setSelectedCardPlayer(null)} className="text-neutral-400 hover:text-white">✕</button>
                        </div>

                        {/* 5x5 Card Matrix */}
                        <div className="grid grid-cols-5 gap-1.5 text-center font-bold">
                            {['B', 'I', 'N', 'G', 'O'].map((col) => (
                                <div key={col} className={`py-1 rounded text-xs ${getBallColor(col)}`}>{col}</div>
                            ))}
                            {selectedCardPlayer.card_grid.map((row, rIdx) =>
                                row.map((cellNum, cIdx) => {
                                    const isFree = rIdx === 2 && cIdx === 2;
                                    const isCalled = isFree || calledNumbersSet.has(cellNum);

                                    return (
                                        <div
                                            key={`${rIdx}-${cIdx}`}
                                            className={`aspect-square flex items-center justify-center rounded-lg text-xs font-mono transition border ${
                                                isCalled
                                                    ? 'bg-emerald-600 text-white border-emerald-400 font-black shadow-inner'
                                                    : 'bg-neutral-950 text-neutral-400 border-neutral-800'
                                            }`}
                                        >
                                            {isFree ? 'FREE' : cellNum}
                                        </div>
                                    );
                                })
                            )}
                        </div>

                        <div className="flex items-center justify-between text-[11px] text-neutral-400 pt-2 border-t border-neutral-800">
                            <span className="flex items-center">
                                <span className="w-2.5 h-2.5 rounded bg-emerald-600 mr-1.5"></span> Called Square
                            </span>
                            <span className="flex items-center">
                                <span className="w-2.5 h-2.5 rounded bg-neutral-950 border border-neutral-800 mr-1.5"></span> Uncalled
                            </span>
                        </div>
                    </div>
                </div>
            )}
        </CompanyAdminLayout>
    );
}
