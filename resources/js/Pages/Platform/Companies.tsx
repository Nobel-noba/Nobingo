import PlatformOwnerLayout from '@/Layouts/PlatformOwnerLayout';
import { PageProps, Tenant } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

interface CompanyItem extends Tenant {
    users_count: number;
    cards_count?: number;
    admin_user?: {
        id: number;
        name: string;
        email: string;
        email_verified_at: string | null;
        status: string;
    } | null;
}

interface Props extends PageProps {
    companies: CompanyItem[];
    filters?: {
        search?: string;
        status?: string;
    };
}

export default function PlatformCompanies({ companies, filters }: Props) {
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [searchTerm, setSearchTerm] = useState(filters?.search || '');
    const [selectedStatus, setSelectedStatus] = useState(filters?.status || '');

    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        slug: '',
        tagline: '',
        brand_color: '#4f46e5',
        currency: 'USD',
        admin_name: '',
        admin_email: '',
        admin_password: '',
        admin_password_confirmation: '',
    });

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/platform/companies', {
            search: searchTerm,
            status: selectedStatus,
        }, { preserveState: true });
    };

    const handleNameChange = (val: string) => {
        setData((prev) => ({
            ...prev,
            name: val,
            slug: prev.slug === '' || prev.slug === prev.name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '')
                ? val.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '')
                : prev.slug,
        }));
    };

    const submitCreate: FormEventHandler = (e) => {
        e.preventDefault();
        post('/platform/companies', {
            onSuccess: () => {
                reset();
                setShowCreateModal(false);
            },
        });
    };

    const toggleStatus = (company: CompanyItem) => {
        const action = company.status === 'active' ? 'REVOKE access from and SUSPEND' : 'RESTORE access to';
        if (confirm(`Are you sure you want to ${action} ${company.name}?`)) {
            router.patch(`/platform/companies/${company.id}/status`);
        }
    };

    return (
        <PlatformOwnerLayout
            header={
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-bold text-white tracking-tight">Rented Company Directory</h1>
                        <p className="text-xs text-slate-400 mt-0.5">Provision, supervise, and control multi-tenant bingo installations</p>
                    </div>
                    <button
                        onClick={() => setShowCreateModal(true)}
                        className="bg-rose-600 hover:bg-rose-500 text-white text-xs font-bold px-4 py-2.5 rounded-xl shadow-md shadow-rose-600/20 transition flex items-center gap-2 self-start sm:self-auto"
                    >
                        <span>+ Provision New Company</span>
                    </button>
                </div>
            }
        >
            <Head title="Rented Companies" />

            {/* Filter & Search Bar */}
            <div className="mb-6 flex flex-col sm:flex-row items-center justify-between gap-3 bg-slate-900/60 p-3 rounded-2xl border border-slate-800">
                <form onSubmit={handleSearch} className="flex-1 flex gap-2 w-full">
                    <input
                        type="text"
                        placeholder="Search company by name or slug..."
                        value={searchTerm}
                        onChange={(e) => setSearchTerm(e.target.value)}
                        className="flex-1 bg-slate-950 border border-slate-700/80 rounded-xl px-3.5 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-rose-500"
                    />
                    <select
                        value={selectedStatus}
                        onChange={(e) => {
                            setSelectedStatus(e.target.value);
                            router.get('/platform/companies', {
                                search: searchTerm,
                                status: e.target.value,
                            }, { preserveState: true });
                        }}
                        className="bg-slate-950 border border-slate-700/80 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-rose-500"
                    >
                        <option value="">All Statuses</option>
                        <option value="active">Active</option>
                        <option value="suspended">Suspended</option>
                    </select>
                    <button
                        type="submit"
                        className="bg-slate-800 hover:bg-slate-700 text-slate-200 px-4 py-2 rounded-xl text-xs font-semibold transition"
                    >
                        Filter
                    </button>
                </form>

                <div className="text-xs text-slate-400 self-end sm:self-auto">
                    Showing <span className="font-bold text-white">{companies.length}</span> installations
                </div>
            </div>

            {/* Companies Grid */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                {companies.map((company) => (
                    <div
                        key={company.id}
                        className={`bg-slate-900/80 border rounded-2xl p-6 flex flex-col justify-between shadow-sm transition hover:shadow-md ${
                            company.status === 'active'
                                ? 'border-slate-800 hover:border-slate-700'
                                : 'border-rose-900/50 bg-rose-950/10'
                        }`}
                    >
                        <div>
                            {/* Card Header */}
                            <div className="flex justify-between items-start mb-4">
                                <div
                                    className="h-12 w-12 rounded-xl flex items-center justify-center font-black text-white text-lg shadow-md"
                                    style={{ backgroundColor: company.settings?.brand_color || '#4f46e5' }}
                                >
                                    {company.name.charAt(0)}
                                </div>
                                <div className="flex flex-col items-end gap-1">
                                    <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider ${
                                        company.status === 'active'
                                            ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20'
                                            : 'bg-rose-500/20 text-rose-300 border border-rose-500/40'
                                    }`}>
                                        {company.status}
                                    </span>
                                </div>
                            </div>

                            <Link href={`/platform/companies/${company.id}`} className="group">
                                <h3 className="text-lg font-bold text-white group-hover:text-rose-400 transition flex items-center gap-1.5">
                                    {company.name}
                                    <span className="text-xs text-slate-500 group-hover:translate-x-0.5 transition">&rarr;</span>
                                </h3>
                            </Link>
                            <p className="text-xs font-mono text-slate-400 mb-2">/c/{company.slug}</p>
                            <p className="text-xs text-slate-300 line-clamp-2 mb-4">
                                {company.settings?.tagline || 'Licensed 75-Ball Online Bingo Room'}
                            </p>

                            {/* Admin Credentials & Status Info */}
                            <div className="bg-slate-950/70 border border-slate-800 rounded-xl p-3 mb-4 space-y-1.5 text-xs">
                                <div className="flex justify-between text-slate-400">
                                    <span>Primary Admin:</span>
                                    <span className="font-semibold text-white truncate max-w-[140px]">
                                        {company.admin_user?.name || 'Unassigned'}
                                    </span>
                                </div>
                                <div className="flex justify-between text-slate-400">
                                    <span>Admin Email:</span>
                                    <span className="font-mono text-slate-300 truncate max-w-[140px]">
                                        {company.admin_user?.email || 'N/A'}
                                    </span>
                                </div>
                                <div className="flex justify-between items-center text-slate-400 pt-1 border-t border-slate-800/80">
                                    <span>Email Verification:</span>
                                    {company.admin_user?.email_verified_at ? (
                                        <span className="text-emerald-400 font-semibold flex items-center gap-1">
                                            <span>✓ Verified</span>
                                        </span>
                                    ) : (
                                        <span className="text-amber-400 font-semibold flex items-center gap-1">
                                            <span>◌ Pending</span>
                                        </span>
                                    )}
                                </div>
                            </div>

                            {/* Tenant Counts */}
                            <div className="pt-3 border-t border-slate-800 grid grid-cols-2 gap-2 text-xs text-slate-400">
                                <div>
                                    <span className="block text-[10px] uppercase font-bold text-slate-500">Players</span>
                                    <span className="text-sm font-bold text-white">{company.users_count ?? 0}</span>
                                </div>
                                <div>
                                    <span className="block text-[10px] uppercase font-bold text-slate-500">Cards</span>
                                    <span className="text-sm font-bold text-white">{company.cards_count ?? 0}</span>
                                </div>
                            </div>
                        </div>

                        {/* Card Action Buttons */}
                        <div className="mt-5 space-y-2">
                            <div className="flex gap-2">
                                <Link
                                    href={`/platform/companies/${company.id}`}
                                    className="flex-1 text-center bg-rose-600/15 hover:bg-rose-600/25 text-rose-300 border border-rose-500/30 text-xs font-bold py-2 rounded-xl transition"
                                >
                                    Manage Installation
                                </Link>
                                <button
                                    onClick={() => toggleStatus(company)}
                                    className={`px-3 py-2 rounded-xl text-xs font-semibold border transition ${
                                        company.status === 'active'
                                            ? 'bg-slate-800/60 hover:bg-rose-950/60 text-rose-300 border-slate-700/80 hover:border-rose-700'
                                            : 'bg-emerald-950/40 hover:bg-emerald-900/60 text-emerald-300 border-emerald-700/60'
                                    }`}
                                >
                                    {company.status === 'active' ? 'Suspend' : 'Restore'}
                                </button>
                            </div>

                            <div className="flex gap-2">
                                <a
                                    href={`/c/${company.slug}/admin`}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="flex-1 text-center bg-slate-800/80 hover:bg-slate-700 text-slate-300 text-xs font-medium py-1.5 rounded-lg transition"
                                >
                                    Admin View &nearr;
                                </a>
                                <a
                                    href={`/c/${company.slug}/lobby`}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="flex-1 text-center bg-slate-800/80 hover:bg-slate-700 text-slate-300 text-xs font-medium py-1.5 rounded-lg transition"
                                >
                                    Player Lobby &nearr;
                                </a>
                            </div>
                        </div>
                    </div>
                ))}
            </div>

            {/* Provision New Company Modal */}
            {showCreateModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm overflow-y-auto">
                    <div className="bg-slate-900 border border-slate-800 rounded-2xl max-w-lg w-full p-6 shadow-2xl my-8">
                        <div className="flex justify-between items-center mb-5">
                            <div>
                                <h3 className="text-lg font-bold text-white">Provision New Rented Company</h3>
                                <p className="text-xs text-slate-400">Deploy a dedicated bingo installation with its administrator account</p>
                            </div>
                            <button
                                onClick={() => setShowCreateModal(false)}
                                className="text-slate-400 hover:text-white text-lg p-1"
                            >
                                &times;
                            </button>
                        </div>

                        <form onSubmit={submitCreate} className="space-y-4">
                            {/* Section 1: Company Profile */}
                            <div className="border-b border-slate-800 pb-3">
                                <h4 className="text-xs font-bold uppercase tracking-wider text-rose-400 mb-3">1. Company Profile</h4>
                                <div className="space-y-3">
                                    <div>
                                        <label className="block text-xs font-semibold text-slate-300 mb-1">Company Name *</label>
                                        <input
                                            type="text"
                                            required
                                            value={data.name}
                                            onChange={(e) => handleNameChange(e.target.value)}
                                            placeholder="e.g. Royal Vegas Bingo"
                                            className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2 text-xs text-white focus:outline-none focus:border-rose-500"
                                        />
                                        {errors.name && <span className="text-rose-400 text-[11px]">{errors.name}</span>}
                                    </div>

                                    <div className="grid grid-cols-2 gap-3">
                                        <div>
                                            <label className="block text-xs font-semibold text-slate-300 mb-1">Tenant Slug *</label>
                                            <input
                                                type="text"
                                                required
                                                value={data.slug}
                                                onChange={(e) => setData('slug', e.target.value)}
                                                placeholder="royal-vegas"
                                                className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2 text-xs font-mono text-white focus:outline-none focus:border-rose-500"
                                            />
                                            {errors.slug && <span className="text-rose-400 text-[11px]">{errors.slug}</span>}
                                        </div>
                                        <div>
                                            <label className="block text-xs font-semibold text-slate-300 mb-1">Brand Accent Color</label>
                                            <div className="flex items-center space-x-2">
                                                <input
                                                    type="color"
                                                    value={data.brand_color}
                                                    onChange={(e) => setData('brand_color', e.target.value)}
                                                    className="h-8 w-10 bg-transparent cursor-pointer rounded border border-slate-700"
                                                />
                                                <input
                                                    type="text"
                                                    value={data.brand_color}
                                                    onChange={(e) => setData('brand_color', e.target.value)}
                                                    className="w-full bg-slate-950 border border-slate-700 rounded-xl px-2.5 py-2 text-xs font-mono text-white focus:outline-none focus:border-rose-500"
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <label className="block text-xs font-semibold text-slate-300 mb-1">Tagline</label>
                                        <input
                                            type="text"
                                            value={data.tagline}
                                            onChange={(e) => setData('tagline', e.target.value)}
                                            placeholder="The Premier Real-Time 75-Ball Bingo Hall"
                                            className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2 text-xs text-white focus:outline-none focus:border-rose-500"
                                        />
                                    </div>
                                </div>
                            </div>

                            {/* Section 2: Initial Company Admin Credentials */}
                            <div>
                                <h4 className="text-xs font-bold uppercase tracking-wider text-rose-400 mb-3">2. Initial Company Administrator</h4>
                                <div className="space-y-3">
                                    <div className="grid grid-cols-2 gap-3">
                                        <div>
                                            <label className="block text-xs font-semibold text-slate-300 mb-1">Admin Full Name *</label>
                                            <input
                                                type="text"
                                                required
                                                value={data.admin_name}
                                                onChange={(e) => setData('admin_name', e.target.value)}
                                                placeholder="e.g. John Doe"
                                                className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2 text-xs text-white focus:outline-none focus:border-rose-500"
                                            />
                                            {errors.admin_name && <span className="text-rose-400 text-[11px]">{errors.admin_name}</span>}
                                        </div>
                                        <div>
                                            <label className="block text-xs font-semibold text-slate-300 mb-1">Admin Email Address *</label>
                                            <input
                                                type="email"
                                                required
                                                value={data.admin_email}
                                                onChange={(e) => setData('admin_email', e.target.value)}
                                                placeholder="admin@company.com"
                                                className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2 text-xs text-white focus:outline-none focus:border-rose-500"
                                            />
                                            {errors.admin_email && <span className="text-rose-400 text-[11px]">{errors.admin_email}</span>}
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-2 gap-3">
                                        <div>
                                            <label className="block text-xs font-semibold text-slate-300 mb-1">Initial Password (min 8) *</label>
                                            <input
                                                type="password"
                                                required
                                                value={data.admin_password}
                                                onChange={(e) => setData('admin_password', e.target.value)}
                                                placeholder="••••••••"
                                                className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2 text-xs text-white focus:outline-none focus:border-rose-500"
                                            />
                                            {errors.admin_password && <span className="text-rose-400 text-[11px]">{errors.admin_password}</span>}
                                        </div>
                                        <div>
                                            <label className="block text-xs font-semibold text-slate-300 mb-1">Confirm Password *</label>
                                            <input
                                                type="password"
                                                required
                                                value={data.admin_password_confirmation}
                                                onChange={(e) => setData('admin_password_confirmation', e.target.value)}
                                                placeholder="••••••••"
                                                className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2 text-xs text-white focus:outline-none focus:border-rose-500"
                                            />
                                        </div>
                                    </div>

                                    <p className="text-[11px] text-slate-400 bg-slate-950/80 p-2.5 rounded-xl border border-slate-800">
                                        An email verification message with a secure 6-digit OTP code and account activation link will be automatically sent to the administrator.
                                    </p>
                                </div>
                            </div>

                            <div className="flex justify-end space-x-3 pt-3 border-t border-slate-800">
                                <button
                                    type="button"
                                    onClick={() => setShowCreateModal(false)}
                                    className="px-4 py-2 text-xs font-medium text-slate-400 hover:text-white"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="bg-rose-600 hover:bg-rose-500 text-white text-xs font-bold px-5 py-2.5 rounded-xl shadow-md transition disabled:opacity-50"
                                >
                                    {processing ? 'Provisioning & Dispatching Email...' : 'Provision Company & Admin'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </PlatformOwnerLayout>
    );
}
