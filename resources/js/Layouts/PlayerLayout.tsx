import { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { PropsWithChildren, ReactNode, useState } from 'react';

export default function PlayerLayout({
    header,
    children,
}: PropsWithChildren<{ header?: ReactNode }>) {
    const { auth, tenant } = usePage<PageProps>().props;
    const [showingNavigationDropdown, setShowingNavigationDropdown] = useState(false);

    const companySlug = tenant?.slug || 'default';

    return (
        <div className="min-h-screen bg-slate-900 text-slate-100 flex flex-col font-sans">
            {/* Player Navbar */}
            <nav className="border-b border-slate-800 bg-slate-950/80 backdrop-blur sticky top-0 z-50">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="flex h-16 justify-between items-center">
                        {/* Company Logo & Name */}
                        <div className="flex items-center space-x-4">
                            <Link href={`/c/${companySlug}/dashboard`} className="flex items-center space-x-3">
                                <div className="h-9 w-9 rounded-xl bg-gradient-to-tr from-amber-500 to-rose-500 flex items-center justify-center font-black text-white text-lg shadow-lg shadow-rose-500/20">
                                    75
                                </div>
                                <div>
                                    <div className="text-base font-extrabold tracking-tight text-white">
                                        {tenant?.name ?? 'Bingo Room'}
                                    </div>
                                    <div className="text-[10px] uppercase font-bold text-amber-400 tracking-wider">
                                        Multiplayer 75-Ball
                                    </div>
                                </div>
                            </Link>

                            <div className="hidden md:flex space-x-1 pl-4">
                                <Link
                                    href={`/c/${companySlug}/dashboard`}
                                    className="px-3 py-2 rounded-lg text-sm font-semibold hover:bg-slate-800 text-slate-200 hover:text-white transition"
                                >
                                    Player Hub
                                </Link>
                                <Link
                                    href={`/c/${companySlug}/lobby`}
                                    className="px-3 py-2 rounded-lg text-sm font-semibold hover:bg-slate-800 text-slate-200 hover:text-white transition"
                                >
                                    Bingo Lobby
                                </Link>
                                <Link
                                    href={`/c/${companySlug}/wallet`}
                                    className="px-3 py-2 rounded-lg text-sm font-semibold hover:bg-slate-800 text-slate-200 hover:text-white transition"
                                >
                                    Wallet & Ledger
                                </Link>
                            </div>
                        </div>

                        {/* Balance and User Profile */}
                        <div className="hidden sm:flex items-center space-x-4">
                            {/* Wallet Balance Pill */}
                            <Link
                                href={`/c/${companySlug}/wallet`}
                                className="flex items-center bg-slate-900/90 hover:bg-slate-800 border border-slate-700/80 hover:border-emerald-500/50 rounded-full px-3.5 py-1.5 shadow-inner transition cursor-pointer group"
                                title="Manage Wallet & View Transactions"
                            >
                                <span className="text-xs font-semibold text-slate-400 mr-2 group-hover:text-slate-300">Wallet:</span>
                                <span className="text-sm font-black text-emerald-400 tracking-tight">
                                    {auth.user?.formatted_balance ?? '$0.00'}
                                </span>
                            </Link>

                            <div className="text-right">
                                <div className="text-sm font-bold text-slate-200">{auth.user?.name}</div>
                                <div className="text-[10px] text-amber-400/90 font-medium">Player</div>
                            </div>

                            <Link
                                method="post"
                                href={route('logout')}
                                as="button"
                                className="text-xs bg-slate-800 hover:bg-rose-950/60 hover:text-rose-300 text-slate-300 px-3 py-1.5 rounded-lg border border-slate-700 transition"
                            >
                                Log Out
                            </Link>
                        </div>

                        {/* Mobile Menu Button */}
                        <div className="flex sm:hidden">
                            <button
                                onClick={() => setShowingNavigationDropdown(!showingNavigationDropdown)}
                                className="p-2 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800"
                            >
                                <svg className="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                    <path
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth="2"
                                        d={showingNavigationDropdown ? 'M6 18L18 6M6 6l12 12' : 'M4 6h16M4 12h16M4 18h16'}
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                {/* Mobile Dropdown */}
                {showingNavigationDropdown && (
                    <div className="sm:hidden border-b border-slate-800 bg-slate-950 px-4 pt-2 pb-3 space-y-2">
                        <div className="flex justify-between items-center py-2 px-3 bg-slate-900 rounded-lg">
                            <span className="text-xs text-slate-400">Balance</span>
                            <span className="text-sm font-bold text-emerald-400">{auth.user?.formatted_balance ?? '$0.00'}</span>
                        </div>
                        <Link
                            href={`/c/${companySlug}/dashboard`}
                            className="block px-3 py-2 rounded-lg text-base font-semibold text-white hover:bg-slate-800"
                        >
                            Player Hub
                        </Link>
                        <Link
                            href={`/c/${companySlug}/lobby`}
                            className="block px-3 py-2 rounded-lg text-base font-semibold text-slate-300 hover:text-white hover:bg-slate-800"
                        >
                            Bingo Lobby
                        </Link>
                        <Link
                            href={`/c/${companySlug}/wallet`}
                            className="block px-3 py-2 rounded-lg text-base font-semibold text-slate-300 hover:text-white hover:bg-slate-800"
                        >
                            Wallet & Ledger
                        </Link>
                        <div className="pt-2 border-t border-slate-800 flex justify-between items-center">
                            <span className="text-sm text-slate-400">{auth.user?.name}</span>
                            <Link
                                method="post"
                                href={route('logout')}
                                as="button"
                                className="text-xs text-rose-400 hover:underline"
                            >
                                Log Out
                            </Link>
                        </div>
                    </div>
                )}
            </nav>

            {/* Header */}
            {header && (
                <header className="bg-slate-950/40 border-b border-slate-800/80 py-4">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        {header}
                    </div>
                </header>
            )}

            {/* Main Area */}
            <main className="flex-1 mx-auto max-w-7xl w-full px-4 sm:px-6 lg:px-8 py-8">
                {children}
            </main>

            {/* Footer */}
            <footer className="border-t border-slate-800/80 py-4 text-center text-xs text-slate-500">
                {tenant?.name} &bull; 75-Ball Fixed-Card Realtime Bingo
            </footer>
        </div>
    );
}
