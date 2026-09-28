import { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { PropsWithChildren, ReactNode, useState } from 'react';

export default function PlatformOwnerLayout({
    header,
    children,
}: PropsWithChildren<{ header?: ReactNode }>) {
    const { auth } = usePage<PageProps>().props;
    const [showingNavigationDropdown, setShowingNavigationDropdown] = useState(false);

    return (
        <div className="min-h-screen bg-slate-950 text-slate-100 flex flex-col">
            {/* Top Navigation Bar */}
            <nav className="border-b border-slate-800 bg-slate-900/90 backdrop-blur sticky top-0 z-50">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="flex h-16 justify-between items-center">
                        <div className="flex items-center space-x-6">
                            <Link href="/platform/dashboard" className="flex items-center space-x-2">
                                <span className="text-xl font-black tracking-wider bg-gradient-to-r from-amber-400 via-rose-500 to-indigo-500 bg-clip-text text-transparent">
                                    NOBINGO
                                </span>
                                <span className="rounded bg-rose-500/20 px-2 py-0.5 text-xs font-semibold text-rose-400 border border-rose-500/30">
                                    PLATFORM OWNER
                                </span>
                            </Link>

                            <div className="hidden sm:flex space-x-4">
                                <Link
                                    href="/platform/dashboard"
                                    className="px-3 py-2 rounded-md text-sm font-medium hover:bg-slate-800 hover:text-white transition"
                                >
                                    Platform Overview
                                </Link>
                                <Link
                                    href="/platform/companies"
                                    className="px-3 py-2 rounded-md text-sm font-medium hover:bg-slate-800 hover:text-white transition"
                                >
                                    Rented Companies
                                </Link>
                            </div>
                        </div>

                        {/* Right User Bar */}
                        <div className="hidden sm:flex items-center space-x-4">
                            <span className="text-xs text-slate-400">Logged in as:</span>
                            <span className="text-sm font-semibold text-amber-300">{auth.user?.name}</span>
                            <Link
                                method="post"
                                href={route('logout')}
                                as="button"
                                className="text-xs bg-slate-800 hover:bg-rose-900/50 hover:text-rose-300 text-slate-300 px-3 py-1.5 rounded border border-slate-700 transition"
                            >
                                Log Out
                            </Link>
                        </div>

                        {/* Mobile Hamburger */}
                        <div className="flex sm:hidden">
                            <button
                                onClick={() => setShowingNavigationDropdown(!showingNavigationDropdown)}
                                className="p-2 rounded-md text-slate-400 hover:text-white hover:bg-slate-800 focus:outline-none"
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

                {/* Mobile Navigation Dropdown */}
                {showingNavigationDropdown && (
                    <div className="sm:hidden border-b border-slate-800 bg-slate-900 px-4 pt-2 pb-3 space-y-1">
                        <Link
                            href="/platform/dashboard"
                            className="block px-3 py-2 rounded-md text-base font-medium text-white hover:bg-slate-800"
                        >
                            Platform Overview
                        </Link>
                        <Link
                            href="/platform/companies"
                            className="block px-3 py-2 rounded-md text-base font-medium text-white hover:bg-slate-800"
                        >
                            Rented Companies
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

            {/* Optional Header */}
            {header && (
                <header className="bg-slate-900 border-b border-slate-800 py-4 shadow-sm">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        {header}
                    </div>
                </header>
            )}

            {/* Main Content */}
            <main className="flex-1 mx-auto max-w-7xl w-full px-4 sm:px-6 lg:px-8 py-8">
                {children}
            </main>

            {/* Footer */}
            <footer className="border-t border-slate-800 py-4 text-center text-xs text-slate-500">
                Nobingo Multi-Tenant Platform &bull; Server Source of Truth Engine
            </footer>
        </div>
    );
}
