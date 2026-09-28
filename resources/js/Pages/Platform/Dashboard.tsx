import PlatformOwnerLayout from '@/Layouts/PlatformOwnerLayout';
import { PageProps, Tenant } from '@/types';
import { Head, Link } from '@inertiajs/react';

interface Props extends PageProps {
    stats: {
        total_companies: number;
        active_companies: number;
        total_users: number;
        total_players: number;
    };
    companies: Array<Tenant & { users_count: number }>;
}

export default function PlatformDashboard({ stats, companies }: Props) {
    return (
        <PlatformOwnerLayout
            header={
                <div className="flex justify-between items-center">
                    <div>
                        <h1 className="text-xl font-bold text-white tracking-tight">Platform Master Control</h1>
                        <p className="text-xs text-slate-400 mt-0.5">Global multi-tenant infrastructure and rental oversight</p>
                    </div>
                    <Link
                        href="/platform/companies"
                        className="bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold px-4 py-2 rounded-lg shadow-sm transition"
                    >
                        + Rent New Company
                    </Link>
                </div>
            }
        >
            <Head title="Platform Control" />

            {/* Platform Stats Grid */}
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                <div className="bg-slate-900 border border-slate-800 rounded-xl p-5 shadow-sm">
                    <div className="text-xs font-medium text-slate-400 uppercase tracking-wider">Rented Companies</div>
                    <div className="text-3xl font-extrabold text-white mt-2">{stats.total_companies}</div>
                    <div className="text-xs text-emerald-400 mt-1 font-medium">{stats.active_companies} active</div>
                </div>

                <div className="bg-slate-900 border border-slate-800 rounded-xl p-5 shadow-sm">
                    <div className="text-xs font-medium text-slate-400 uppercase tracking-wider">Total System Users</div>
                    <div className="text-3xl font-extrabold text-white mt-2">{stats.total_users}</div>
                    <div className="text-xs text-slate-400 mt-1">Across all tenants</div>
                </div>

                <div className="bg-slate-900 border border-slate-800 rounded-xl p-5 shadow-sm">
                    <div className="text-xs font-medium text-slate-400 uppercase tracking-wider">Total Active Players</div>
                    <div className="text-3xl font-extrabold text-amber-400 mt-2">{stats.total_players}</div>
                    <div className="text-xs text-slate-400 mt-1">Multiplayer pool</div>
                </div>

                <div className="bg-slate-900 border border-slate-800 rounded-xl p-5 shadow-sm">
                    <div className="text-xs font-medium text-slate-400 uppercase tracking-wider">Server Authority Engine</div>
                    <div className="text-3xl font-extrabold text-indigo-400 mt-2">Active</div>
                    <div className="text-xs text-slate-400 mt-1">Deterministic Verification</div>
                </div>
            </div>

            {/* Rented Companies List */}
            <div className="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-sm">
                <div className="p-5 border-b border-slate-800 flex justify-between items-center">
                    <h2 className="text-base font-bold text-white">Active Rented Companies</h2>
                    <span className="text-xs text-slate-400">Isolated Tenant Scopes</span>
                </div>

                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-slate-800 text-sm">
                        <thead className="bg-slate-950/60 text-slate-400 text-xs uppercase tracking-wider text-left">
                            <tr>
                                <th className="px-6 py-3 font-semibold">Company Name</th>
                                <th className="px-6 py-3 font-semibold">Tenant Slug</th>
                                <th className="px-6 py-3 font-semibold">Status</th>
                                <th className="px-6 py-3 font-semibold">Users</th>
                                <th className="px-6 py-3 font-semibold text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-800/80 text-slate-200">
                            {companies.map((company) => (
                                <tr key={company.id} className="hover:bg-slate-800/40 transition">
                                    <td className="px-6 py-4 font-semibold text-white">
                                        <div className="flex items-center space-x-2">
                                            <span>{company.name}</span>
                                        </div>
                                    </td>
                                    <td className="px-6 py-4 font-mono text-xs text-slate-400">
                                        {company.slug}
                                    </td>
                                    <td className="px-6 py-4">
                                        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                            company.status === 'active'
                                                ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20'
                                                : 'bg-rose-500/10 text-rose-400 border border-rose-500/20'
                                        }`}>
                                            {company.status}
                                        </span>
                                    </td>
                                    <td className="px-6 py-4 text-slate-300">
                                        {company.users_count ?? 0}
                                    </td>
                                    <td className="px-6 py-4 text-right space-x-3">
                                        <Link
                                            href={`/c/${company.slug}/admin`}
                                            className="text-xs text-indigo-400 hover:text-indigo-300 font-medium hover:underline"
                                        >
                                            Enter Admin &rarr;
                                        </Link>
                                        <Link
                                            href={`/c/${company.slug}/dashboard`}
                                            className="text-xs text-amber-400 hover:text-amber-300 font-medium hover:underline"
                                        >
                                            View Player Hub &rarr;
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </PlatformOwnerLayout>
    );
}
