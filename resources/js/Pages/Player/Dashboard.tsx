import PlayerLayout from '@/Layouts/PlayerLayout';
import { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';

interface Props extends PageProps {
    stats: {
        balance: string;
        games_played: number;
        games_won: number;
        active_cards: number;
    };
}

export default function PlayerDashboard({ stats, tenant, auth }: Props) {
    const companySlug = tenant?.slug || 'default';

    return (
        <PlayerLayout
            header={
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-extrabold text-white tracking-tight">
                            Welcome back, {auth.user?.name}!
                        </h1>
                        <p className="text-xs text-slate-400 mt-0.5">
                            Playing at <span className="font-semibold text-amber-400">{tenant?.name}</span>
                        </p>
                    </div>

                    <div className="flex items-center space-x-3">
                        <div className="bg-slate-950/70 border border-slate-800 rounded-xl px-4 py-2 flex items-center space-x-3 shadow-inner">
                            <span className="text-xs text-slate-400">Available Wallet:</span>
                            <span className="text-base font-black text-emerald-400">
                                {stats.balance}
                            </span>
                        </div>
                    </div>
                </div>
            }
        >
            <Head title={`${tenant?.name ?? 'Bingo'} - Player Hub`} />

            {/* Quick Stats Grid */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-5 mb-8">
                <div className="bg-slate-950/60 border border-slate-800 rounded-2xl p-6 shadow-sm flex flex-col justify-between">
                    <div>
                        <div className="text-xs font-bold text-amber-400 uppercase tracking-wider mb-1">
                            Current Wallet
                        </div>
                        <div className="text-4xl font-black text-white mt-1">
                            {stats.balance}
                        </div>
                        <p className="text-xs text-slate-400 mt-2">
                            Secure double-entry ledger currency for game entries and prizes.
                        </p>
                    </div>
                </div>

                <div className="bg-slate-950/60 border border-slate-800 rounded-2xl p-6 shadow-sm flex flex-col justify-between">
                    <div>
                        <div className="text-xs font-bold text-indigo-400 uppercase tracking-wider mb-1">
                            Game History
                        </div>
                        <div className="text-4xl font-black text-white mt-1">
                            {stats.games_played}
                        </div>
                        <p className="text-xs text-slate-400 mt-2">
                            Total multiplayer sessions entered with fixed cards.
                        </p>
                    </div>
                </div>

                <div className="bg-slate-950/60 border border-slate-800 rounded-2xl p-6 shadow-sm flex flex-col justify-between">
                    <div>
                        <div className="text-xs font-bold text-emerald-400 uppercase tracking-wider mb-1">
                            Bingo Victories
                        </div>
                        <div className="text-4xl font-black text-emerald-400 mt-1">
                            {stats.games_won}
                        </div>
                        <p className="text-xs text-slate-400 mt-2">
                            Verified server-side winning pattern claims.
                        </p>
                    </div>
                </div>
            </div>

            {/* Live Lobby Banner */}
            <div className="bg-gradient-to-r from-indigo-900/40 via-purple-900/30 to-slate-950 border border-indigo-500/20 rounded-2xl p-8 shadow-lg relative overflow-hidden mb-8">
                <div className="relative z-10 max-w-2xl">
                    <span className="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-amber-400/10 text-amber-300 border border-amber-400/20 mb-3">
                        75-BALL FIXED-CARD BINGO
                    </span>
                    <h2 className="text-2xl font-black text-white tracking-tight">
                        Live Game Rooms & Scheduled Matches
                    </h2>
                    <p className="text-sm text-slate-300 mt-2 leading-relaxed">
                        Join multiplayer rooms with fixed-card allocation. Numbers are drawn in real-time,
                        automatically tracked on your persistent card, and verified on the server the instant
                        you claim Bingo.
                    </p>

                    <div className="mt-6 flex items-center space-x-4">
                        <Link
                            href={`/c/${companySlug}/lobby`}
                            className="bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-sm px-6 py-3 rounded-xl shadow-lg shadow-indigo-600/25 flex items-center space-x-2 transition"
                        >
                            <span>Enter Game Lobby</span>
                            <span>&rarr;</span>
                        </Link>
                        <span className="text-xs text-slate-400">
                            Live rooms available now
                        </span>
                    </div>
                </div>
            </div>
        </PlayerLayout>
    );
}
