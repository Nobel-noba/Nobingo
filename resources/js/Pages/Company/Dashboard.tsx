import CompanyAdminLayout from '@/Layouts/CompanyAdminLayout';
import { PageProps } from '@/types';
import { Head } from '@inertiajs/react';

interface Props extends PageProps {
    stats: {
        total_cards: number;
        total_templates: number;
        active_games: number;
        total_players: number;
    };
}

export default function CompanyDashboard({ stats }: Props) {
    return (
        <CompanyAdminLayout
            header={
                <div>
                    <h1 className="text-xl font-bold text-white tracking-tight">Company Administration Hub</h1>
                    <p className="text-xs text-neutral-400 mt-0.5">Manage fixed cards, game patterns, scheduled rooms, and live games</p>
                </div>
            }
        >
            <Head title="Company Admin Dashboard" />

            {/* Stats Overview */}
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                <div className="bg-neutral-900 border border-neutral-800 rounded-xl p-5 shadow-sm">
                    <div className="text-xs font-medium text-neutral-400 uppercase tracking-wider">Fixed Card Inventory</div>
                    <div className="text-3xl font-extrabold text-white mt-2">{stats.total_cards}</div>
                    <div className="text-xs text-indigo-400 mt-1 font-medium">Permanent Assets</div>
                </div>

                <div className="bg-neutral-900 border border-neutral-800 rounded-xl p-5 shadow-sm">
                    <div className="text-xs font-medium text-neutral-400 uppercase tracking-wider">Winning Templates</div>
                    <div className="text-3xl font-extrabold text-white mt-2">{stats.total_templates}</div>
                    <div className="text-xs text-neutral-400 mt-1">Configured patterns</div>
                </div>

                <div className="bg-neutral-900 border border-neutral-800 rounded-xl p-5 shadow-sm">
                    <div className="text-xs font-medium text-neutral-400 uppercase tracking-wider">Active Rooms</div>
                    <div className="text-3xl font-extrabold text-amber-400 mt-2">{stats.active_games}</div>
                    <div className="text-xs text-neutral-400 mt-1">Live calling sessions</div>
                </div>

                <div className="bg-neutral-900 border border-neutral-800 rounded-xl p-5 shadow-sm">
                    <div className="text-xs font-medium text-neutral-400 uppercase tracking-wider">Company Players</div>
                    <div className="text-3xl font-extrabold text-emerald-400 mt-2">{stats.total_players}</div>
                    <div className="text-xs text-neutral-400 mt-1">Registered accounts</div>
                </div>
            </div>

            {/* Architecture Invariants Card */}
            <div className="bg-neutral-900 border border-neutral-800 rounded-xl p-6 shadow-sm mb-6">
                <h2 className="text-base font-bold text-white mb-2 flex items-center space-x-2">
                    <span className="h-2.5 w-2.5 rounded-full bg-emerald-500 inline-block animate-pulse"></span>
                    <span>Server-Authoritative Bingo Invariants</span>
                </h2>
                <p className="text-xs text-neutral-300 leading-relaxed max-w-3xl">
                    Every bingo game under this company uses persistent fixed cards from your inventory.
                    The browser never generates card numbers or determines winners. All number calls, pattern
                    evaluations, and claims are cryptographically verified by the central server engine.
                </p>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
                    <div className="border border-neutral-800 rounded-lg p-4 bg-neutral-950/40">
                        <div className="text-xs font-bold text-indigo-400 uppercase mb-1">Phase 2: Fixed Cards</div>
                        <p className="text-xs text-neutral-400">
                            Pre-generated 75-ball cards with deterministic hash uniqueness and permanent version tracking.
                        </p>
                    </div>

                    <div className="border border-neutral-800 rounded-lg p-4 bg-neutral-950/40">
                        <div className="text-xs font-bold text-indigo-400 uppercase mb-1">Phase 3: Patterns</div>
                        <p className="text-xs text-neutral-400">
                            5x5 coordinate geometry for single lines, multi-lines, corners, diagonals, X, and blackout.
                        </p>
                    </div>

                    <div className="border border-neutral-800 rounded-lg p-4 bg-neutral-950/40">
                        <div className="text-xs font-bold text-indigo-400 uppercase mb-1">Phase 4-7: Live Engine</div>
                        <p className="text-xs text-neutral-400">
                            Cryptographic number generator, Reverb real-time ball calls, and row-locked winner claims.
                        </p>
                    </div>
                </div>
            </div>
        </CompanyAdminLayout>
    );
}
