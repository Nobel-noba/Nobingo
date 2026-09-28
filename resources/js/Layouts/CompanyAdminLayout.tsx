import { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { PropsWithChildren, ReactNode, useState } from 'react';

export default function CompanyAdminLayout({
    header,
    children,
}: PropsWithChildren<{ header?: ReactNode }>) {
    const { auth, tenant } = usePage<PageProps>().props;
    const [showingNavigationDropdown, setShowingNavigationDropdown] = useState(false);

    const companySlug = tenant?.slug || 'default';

    return (
        <div className="min-h-screen bg-neutral-950 text-neutral-100 flex flex-col">
            {/* Top Navigation Bar */}
            <nav className="border-b border-neutral-800 bg-neutral-900/95 backdrop-blur sticky top-0 z-50">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="flex h-16 justify-between items-center">
                        <div className="flex items-center space-x-6">
                            <Link href={`/c/${companySlug}/admin`} className="flex items-center space-x-3">
                                <div className="h-8 w-8 rounded-lg bg-indigo-600 flex items-center justify-center font-bold text-white shadow-md shadow-indigo-500/20">
                                    B
                                </div>
                                <div>
                                    <div className="text-base font-bold text-white leading-tight">
                                        {tenant?.name ?? 'Company Console'}
                                    </div>
                                    <div className="text-[10px] tracking-wide uppercase text-indigo-400 font-semibold">
                                        Company Admin Console
                                    </div>
                                </div>
                            </Link>

                            <div className="hidden md:flex space-x-1 pl-4">
                                <Link
                                    href={`/c/${companySlug}/admin`}
                                    className="px-3 py-2 rounded-md text-sm font-medium hover:bg-neutral-800 text-neutral-200 hover:text-white transition"
                                >
                                    Dashboard
                                </Link>
                                <Link
                                    href={`/c/${companySlug}/admin/cards`}
                                    className="px-3 py-2 rounded-md text-sm font-medium hover:bg-neutral-800 text-neutral-200 hover:text-white transition"
                                >
                                    Cards Inventory
                                </Link>
                                <Link
                                    href={`/c/${companySlug}/admin/patterns`}
                                    className="px-3 py-2 rounded-md text-sm font-medium hover:bg-neutral-800 text-neutral-200 hover:text-white transition"
                                >
                                    Winning Patterns
                                </Link>
                                <Link
                                    href={`/c/${companySlug}/admin/games`}
                                    className="px-3 py-2 rounded-md text-sm font-medium hover:bg-neutral-800 text-neutral-200 hover:text-white transition"
                                >
                                    Games & Rooms
                                </Link>
                                <span className="px-3 py-2 text-sm font-medium text-neutral-500 cursor-not-allowed">
                                    Ledger (Phase 8)
                                </span>
                            </div>
                        </div>

                        {/* Right User Bar */}
                        <div className="hidden sm:flex items-center space-x-4">
                            <div className="text-right">
                                <div className="text-sm font-medium text-neutral-200">{auth.user?.name}</div>
                                <div className="text-xs text-indigo-400 capitalize">{auth.user?.roles.join(', ').toLowerCase()}</div>
                            </div>
                            <Link
                                method="post"
                                href={route('logout')}
                                as="button"
                                className="text-xs bg-neutral-800 hover:bg-red-950/60 hover:text-red-300 text-neutral-300 px-3 py-1.5 rounded border border-neutral-700 transition"
                            >
                                Log Out
                            </Link>
                        </div>

                        {/* Mobile Hamburger */}
                        <div className="flex md:hidden">
                            <button
                                onClick={() => setShowingNavigationDropdown(!showingNavigationDropdown)}
                                className="p-2 rounded-md text-neutral-400 hover:text-white hover:bg-neutral-800"
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
                    <div className="md:hidden border-b border-neutral-800 bg-neutral-900 px-4 pt-2 pb-3 space-y-1">
                        <Link
                            href={`/c/${companySlug}/admin`}
                            className="block px-3 py-2 rounded-md text-base font-medium text-white hover:bg-neutral-800"
                        >
                            Dashboard
                        </Link>
                        <div className="pt-2 border-t border-neutral-800 flex justify-between items-center">
                            <span className="text-sm text-neutral-400">{auth.user?.name}</span>
                            <Link
                                method="post"
                                href={route('logout')}
                                as="button"
                                className="text-xs text-red-400 hover:underline"
                            >
                                Log Out
                            </Link>
                        </div>
                    </div>
                )}
            </nav>

            {/* Optional Header */}
            {header && (
                <header className="bg-neutral-900 border-b border-neutral-800 py-4 shadow-sm">
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
            <footer className="border-t border-neutral-800 py-4 text-center text-xs text-neutral-500">
                {tenant?.name} &bull; Powered by Nobingo Multi-Tenant Engine
            </footer>
        </div>
    );
}
