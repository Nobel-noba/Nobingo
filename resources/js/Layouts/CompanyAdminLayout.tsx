import { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { PropsWithChildren, ReactNode, useState } from 'react';

export default function CompanyAdminLayout({
    header,
    children,
}: PropsWithChildren<{ header?: ReactNode }>) {
    const { auth, tenant } = usePage<PageProps>().props;
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const companySlug = tenant?.slug || 'default';
    const currentPath = window.location.pathname;

    const navSections = [
        {
            title: 'Live Operations',
            items: [
                {
                    name: 'Dashboard',
                    href: `/c/${companySlug}/admin`,
                    active: currentPath === `/c/${companySlug}/admin`,
                    icon: (
                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                    ),
                },
                {
                    name: 'Games & Rooms',
                    href: `/c/${companySlug}/admin/games`,
                    active: currentPath.startsWith(`/c/${companySlug}/admin/games`),
                    icon: (
                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    ),
                },
                {
                    name: 'Game Templates',
                    href: `/c/${companySlug}/admin/templates`,
                    active: currentPath.startsWith(`/c/${companySlug}/admin/templates`),
                    icon: (
                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z" />
                        </svg>
                    ),
                },
            ],
        },
        {
            title: 'Assets & Patterns',
            items: [
                {
                    name: 'Fixed Card Assets',
                    href: `/c/${companySlug}/admin/cards`,
                    active: currentPath.startsWith(`/c/${companySlug}/admin/cards`),
                    icon: (
                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                        </svg>
                    ),
                },
                {
                    name: 'Winning Patterns',
                    href: `/c/${companySlug}/admin/patterns`,
                    active: currentPath.startsWith(`/c/${companySlug}/admin/patterns`),
                    icon: (
                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                        </svg>
                    ),
                },
                {
                    name: 'Winners Ledger',
                    href: `/c/${companySlug}/admin/winners`,
                    active: currentPath.startsWith(`/c/${companySlug}/admin/winners`),
                    icon: (
                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7" />
                        </svg>
                    ),
                },
            ],
        },
        {
            title: 'Finance & Billing',
            items: [
                {
                    name: 'Buy Platform Credits',
                    href: `/c/${companySlug}/admin/credits`,
                    active: currentPath.startsWith(`/c/${companySlug}/admin/credits`),
                    icon: (
                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    ),
                },
                {
                    name: 'Player Deposits',
                    href: `/c/${companySlug}/admin/deposit-requests`,
                    active: currentPath.startsWith(`/c/${companySlug}/admin/deposit-requests`),
                    icon: (
                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    ),
                },
                {
                    name: 'Payment Accounts',
                    href: `/c/${companySlug}/admin/payment-accounts`,
                    active: currentPath.startsWith(`/c/${companySlug}/admin/payment-accounts`),
                    icon: (
                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                        </svg>
                    ),
                },
                {
                    name: 'Treasury & Ledger',
                    href: `/c/${companySlug}/admin/ledger`,
                    active: currentPath.startsWith(`/c/${companySlug}/admin/ledger`),
                    icon: (
                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                        </svg>
                    ),
                },
            ],
        },
        {
            title: 'Administration',
            items: [
                {
                    name: 'Player Accounts',
                    href: `/c/${companySlug}/admin/players`,
                    active: currentPath.startsWith(`/c/${companySlug}/admin/players`),
                    icon: (
                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    ),
                },
                {
                    name: 'Section 49 Audit',
                    href: `/c/${companySlug}/admin/audit-logs`,
                    active: currentPath.startsWith(`/c/${companySlug}/admin/audit-logs`),
                    icon: (
                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                        </svg>
                    ),
                },
                {
                    name: 'Business Reports',
                    href: `/c/${companySlug}/admin/reports`,
                    active: currentPath.startsWith(`/c/${companySlug}/admin/reports`),
                    icon: (
                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    ),
                },
            ],
        },
    ];

    return (
        <div className="min-h-screen bg-neutral-950 text-neutral-100 flex flex-col md:flex-row font-sans selection:bg-indigo-500 selection:text-white">
            {/* Mobile Header Bar */}
            <header className="md:hidden flex items-center justify-between px-4 py-3 bg-neutral-900 border-b border-neutral-800 sticky top-0 z-40">
                <Link href={`/c/${companySlug}/admin`} className="flex items-center space-x-2.5">
                    <div
                        className="h-8 w-8 rounded-lg flex items-center justify-center font-bold text-white shadow-md text-sm"
                        style={{ backgroundColor: tenant?.settings?.brand_color || '#4f46e5' }}
                    >
                        {tenant?.name?.charAt(0) || 'B'}
                    </div>
                    <div>
                        <div className="text-sm font-bold text-white leading-tight">
                            {tenant?.name ?? 'Company Console'}
                        </div>
                        <div className="text-[10px] text-indigo-400 font-semibold uppercase">
                            Admin Console
                        </div>
                    </div>
                </Link>
                <button
                    onClick={() => setSidebarOpen(!sidebarOpen)}
                    className="p-2 rounded-xl text-neutral-400 hover:text-white hover:bg-neutral-800 transition focus:outline-none"
                    aria-label="Toggle Navigation Menu"
                >
                    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d={sidebarOpen ? 'M6 18L18 6M6 6l12 12' : 'M4 6h16M4 12h16M4 18h16'} />
                    </svg>
                </button>
            </header>

            {/* Mobile Slide-over Drawer Backdrop */}
            {sidebarOpen && (
                <div
                    className="fixed inset-0 z-40 bg-neutral-950/80 backdrop-blur-sm md:hidden transition-opacity"
                    onClick={() => setSidebarOpen(false)}
                />
            )}

            {/* Vertical Sidebar */}
            <aside
                className={`fixed md:sticky top-0 z-50 md:z-30 h-screen w-64 bg-neutral-950 border-r border-neutral-800 flex flex-col justify-between transition-transform duration-200 ease-in-out md:translate-x-0 ${
                    sidebarOpen ? 'translate-x-0' : '-translate-x-full'
                }`}
            >
                {/* Brand Header & Navigation Sections */}
                <div className="overflow-y-auto flex-1">
                    <div className="p-5 border-b border-neutral-800/80 flex items-center justify-between">
                        <Link href={`/c/${companySlug}/admin`} className="flex items-center space-x-3">
                            <div
                                className="h-9 w-9 rounded-xl flex items-center justify-center font-black text-white shadow-md text-base"
                                style={{ backgroundColor: tenant?.settings?.brand_color || '#4f46e5' }}
                            >
                                {tenant?.name?.charAt(0) || 'B'}
                            </div>
                            <div className="min-w-0">
                                <div className="text-sm font-bold text-white leading-tight truncate">
                                    {tenant?.name ?? 'Bingo Console'}
                                </div>
                                <div className="text-[10px] text-indigo-400 font-semibold uppercase tracking-wider">
                                    Company Admin
                                </div>
                            </div>
                        </Link>
                        <button
                            onClick={() => setSidebarOpen(false)}
                            className="md:hidden text-neutral-400 hover:text-white p-1"
                        >
                            &times;
                        </button>
                    </div>

                    {/* Quick Link to Player Lobby */}
                    <div className="px-4 pt-3 pb-1">
                        <Link
                            href={`/c/${companySlug}/lobby`}
                            className="flex items-center justify-between px-3 py-1.5 rounded-lg bg-indigo-500/10 hover:bg-indigo-500/20 text-indigo-300 border border-indigo-500/20 text-xs font-semibold transition"
                        >
                            <span>Open Player Lobby</span>
                            <span>&rarr;</span>
                        </Link>
                    </div>

                    {/* Platform Credit Balance Card */}
                    <div className="px-4 py-2">
                        <div className="p-2.5 rounded-xl bg-neutral-900/90 border border-neutral-800 flex items-center justify-between shadow-inner">
                            <div>
                                <div className="text-[10px] uppercase font-bold text-neutral-400 tracking-wider">Company Credits</div>
                                <div className="text-sm font-black text-emerald-400">
                                    {(tenant as any)?.formatted_credit_balance || '$0.00'}
                                </div>
                            </div>
                            <Link
                                href={`/c/${companySlug}/admin/credits`}
                                className="px-2.5 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-[10px] font-bold text-white transition shadow-sm"
                            >
                                + Buy
                            </Link>
                        </div>
                    </div>

                    {/* Grouped Navigation */}
                    <nav className="p-3 space-y-4">
                        {navSections.map((section) => (
                            <div key={section.title}>
                                <div className="text-[10px] uppercase font-bold tracking-wider text-neutral-500 px-3 mb-1">
                                    {section.title}
                                </div>
                                <div className="space-y-0.5">
                                    {section.items.map((item) => (
                                        <Link
                                            key={item.name}
                                            href={item.href}
                                            onClick={() => setSidebarOpen(false)}
                                            className={`flex items-center space-x-2.5 px-3 py-2 rounded-lg text-xs font-medium transition ${
                                                item.active
                                                    ? 'bg-indigo-600/15 text-indigo-300 border border-indigo-500/30 font-semibold'
                                                    : 'text-neutral-400 hover:bg-neutral-900 hover:text-neutral-200 border border-transparent'
                                            }`}
                                        >
                                            <span className={item.active ? 'text-indigo-400' : 'text-neutral-500'}>
                                                {item.icon}
                                            </span>
                                            <span className="truncate">{item.name}</span>
                                        </Link>
                                    ))}
                                </div>
                            </div>
                        ))}
                    </nav>
                </div>

                {/* Footer User Profile & Actions */}
                <div className="p-3.5 border-t border-neutral-800 bg-neutral-900/40">
                    <div className="flex items-center space-x-2.5 mb-2.5 px-1.5">
                        <div className="w-8 h-8 rounded-lg bg-neutral-800 border border-neutral-700 flex items-center justify-center font-bold text-white text-xs">
                            {auth.user?.name?.charAt(0) || 'A'}
                        </div>
                        <div className="flex-1 min-w-0">
                            <p className="text-xs font-bold text-white truncate">{auth.user?.name}</p>
                            <p className="text-[10px] text-neutral-400 truncate">{auth.user?.email}</p>
                        </div>
                    </div>
                    <Link
                        method="post"
                        href={route('logout')}
                        as="button"
                        className="w-full flex items-center justify-center space-x-1.5 text-xs font-medium bg-neutral-800/80 hover:bg-rose-950/50 hover:text-rose-300 text-neutral-300 py-1.5 rounded-lg border border-neutral-700/60 transition"
                    >
                        <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        <span>Sign Out</span>
                    </Link>
                </div>
            </aside>

            {/* Main Content Area */}
            <div className="flex-1 min-w-0 flex flex-col min-h-screen">
                {header && (
                    <div className="border-b border-neutral-800 bg-neutral-900/40 px-6 py-4 backdrop-blur sticky top-0 z-20">
                        {header}
                    </div>
                )}

                <main className="flex-1 p-6 lg:p-8">
                    {children}
                </main>
            </div>
        </div>
    );
}
