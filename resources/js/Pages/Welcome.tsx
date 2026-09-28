import { PageProps, Tenant } from '@/types';
import { Head, Link } from '@inertiajs/react';

interface Props extends PageProps {
    companies: Tenant[];
}

export default function Welcome({ auth, companies }: Props) {
    return (
        <>
            <Head title="Nobingo - Multi-Tenant 75-Ball Fixed-Card Bingo" />
            <div className="min-h-screen bg-slate-950 text-slate-100 flex flex-col font-sans selection:bg-rose-500 selection:text-white">
                {/* Navbar */}
                <header className="border-b border-slate-800 bg-slate-900/60 backdrop-blur sticky top-0 z-50">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                        <div className="flex items-center space-x-3">
                            <div className="h-9 w-9 rounded-xl bg-gradient-to-tr from-amber-500 via-rose-500 to-indigo-500 flex items-center justify-center font-black text-white text-lg shadow-lg shadow-rose-500/20">
                                75
                            </div>
                            <span className="text-xl font-black tracking-wider bg-gradient-to-r from-amber-400 via-rose-400 to-indigo-400 bg-clip-text text-transparent">
                                NOBINGO
                            </span>
                        </div>

                        <div className="flex items-center space-x-3">
                            {auth.user ? (
                                <Link
                                    href="/dashboard"
                                    className="bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs px-4 py-2 rounded-lg transition shadow-sm"
                                >
                                    Go to Dashboard &rarr;
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href={route('login')}
                                        className="text-slate-300 hover:text-white text-xs font-semibold px-3 py-2 rounded-lg transition"
                                    >
                                        Log In
                                    </Link>
                                    <Link
                                        href={route('register')}
                                        className="bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold px-4 py-2 rounded-lg transition shadow-sm"
                                    >
                                        Register
                                    </Link>
                                </>
                            )}
                        </div>
                    </div>
                </header>

                {/* Hero Section */}
                <main className="flex-1 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 flex flex-col items-center text-center">
                    <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 mb-6">
                        <span className="h-2 w-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        Phase 1 Foundation & Multi-Company Tenancy Active
                    </div>

                    <h1 className="text-4xl sm:text-6xl font-black tracking-tight text-white max-w-4xl leading-tight">
                        Fixed-Card <span className="bg-gradient-to-r from-amber-400 via-rose-400 to-indigo-400 bg-clip-text text-transparent">75-Ball Bingo</span> Built for Multi-Company Rental
                    </h1>

                    <p className="mt-6 text-base sm:text-lg text-slate-400 max-w-2xl leading-relaxed">
                        A single high-performance server powers completely isolated bingo rooms for multiple rented companies.
                        Every game runs with permanent fixed-card inventory, deterministic pattern calculation, and
                        cryptographic server-side verification.
                    </p>

                    <div className="mt-8 flex flex-wrap gap-4 justify-center">
                        {auth.user ? (
                            <Link
                                href="/dashboard"
                                className="bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-sm px-6 py-3 rounded-xl transition shadow-lg shadow-indigo-600/20"
                            >
                                Enter Application Hub
                            </Link>
                        ) : (
                            <Link
                                href={route('login')}
                                className="bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-sm px-6 py-3 rounded-xl transition shadow-lg shadow-indigo-600/20"
                            >
                                Sign In with Demo Account
                            </Link>
                        )}
                    </div>

                    {/* Multi-Tenant Demo Accounts Section */}
                    <div className="mt-16 w-full max-w-4xl bg-slate-900 border border-slate-800 rounded-2xl p-6 sm:p-8 text-left shadow-xl">
                        <div className="flex items-center justify-between mb-4 border-b border-slate-800 pb-3">
                            <h2 className="text-base font-bold text-white flex items-center space-x-2">
                                <span className="h-3 w-3 rounded bg-amber-400 inline-block"></span>
                                <span>Multi-Tenant Roles & Demo Credentials</span>
                            </h2>
                            <span className="text-xs text-slate-400">Default Password: <code className="text-amber-300 font-mono">Password123!</code></span>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
                            {/* Platform Owner */}
                            <div className="bg-slate-950 p-4 rounded-xl border border-slate-800">
                                <div className="font-bold text-rose-400 uppercase text-[11px] mb-1">Platform Owner (You)</div>
                                <div className="text-slate-300 font-semibold mb-1">Global Super Admin</div>
                                <div className="text-slate-400 font-mono">owner@nobingo.test</div>
                                <div className="text-slate-500 text-[10px] mt-2">
                                    Full control across all rented companies, global quotas, tenant provisioning.
                                </div>
                            </div>

                            {/* Acme Company */}
                            <div className="bg-slate-950 p-4 rounded-xl border border-slate-800">
                                <div className="font-bold text-indigo-400 uppercase text-[11px] mb-1">Company: Acme Bingo Club</div>
                                <div className="text-slate-300 font-semibold mb-1">Admin & Players</div>
                                <div className="text-slate-400 font-mono">admin@acme.test</div>
                                <div className="text-slate-400 font-mono mt-0.5">player1@acme.test</div>
                                <div className="text-slate-500 text-[10px] mt-2">
                                    Isolated Acme inventory, games, player balance, and caller session.
                                </div>
                            </div>

                            {/* Lucky Star Company */}
                            <div className="bg-slate-950 p-4 rounded-xl border border-slate-800">
                                <div className="font-bold text-emerald-400 uppercase text-[11px] mb-1">Company: Lucky Star</div>
                                <div className="text-slate-300 font-semibold mb-1">Admin & Players</div>
                                <div className="text-slate-400 font-mono">admin@luckystar.test</div>
                                <div className="text-slate-400 font-mono mt-0.5">player2@luckystar.test</div>
                                <div className="text-slate-500 text-[10px] mt-2">
                                    Separate company with zero cross-tenant visibility into Acme data.
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Active Rented Companies Grid */}
                    <div className="mt-12 w-full max-w-4xl text-left">
                        <h3 className="text-sm font-bold text-slate-300 uppercase tracking-wider mb-4">
                            Active Rented Companies ({companies.length})
                        </h3>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            {companies.map((c) => (
                                <div
                                    key={c.id}
                                    className="bg-slate-900/60 border border-slate-800 rounded-xl p-5 flex items-center justify-between"
                                >
                                    <div>
                                        <div className="text-base font-bold text-white">{c.name}</div>
                                        <div className="text-xs font-mono text-slate-400">/c/{c.slug}</div>
                                    </div>
                                    <span className="px-2.5 py-1 text-[11px] font-semibold rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                        Live
                                    </span>
                                </div>
                            ))}
                        </div>
                    </div>
                </main>

                <footer className="border-t border-slate-800 py-6 text-center text-xs text-slate-500">
                    Nobingo Architecture &bull; 75-Ball Fixed-Card Multi-Tenant Engine
                </footer>
            </div>
        </>
    );
}
