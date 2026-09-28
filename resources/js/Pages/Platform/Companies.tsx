import PlatformOwnerLayout from '@/Layouts/PlatformOwnerLayout';
import { PageProps, Tenant } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

interface Props extends PageProps {
    companies: Array<Tenant & { users_count: number }>;
}

export default function PlatformCompanies({ companies }: Props) {
    const [showCreateModal, setShowCreateModal] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        slug: '',
        tagline: '',
        brand_color: '#4f46e5',
    });

    const submitCreate: FormEventHandler = (e) => {
        e.preventDefault();
        post('/platform/companies', {
            onSuccess: () => {
                reset();
                setShowCreateModal(false);
            },
        });
    };

    return (
        <PlatformOwnerLayout
            header={
                <div className="flex justify-between items-center">
                    <div>
                        <h1 className="text-xl font-bold text-white tracking-tight">Rented Company Directory</h1>
                        <p className="text-xs text-slate-400 mt-0.5">Provision and supervise multi-tenant bingo installations</p>
                    </div>
                    <button
                        onClick={() => setShowCreateModal(true)}
                        className="bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold px-4 py-2 rounded-lg shadow-sm transition"
                    >
                        + Rent New Company
                    </button>
                </div>
            }
        >
            <Head title="Rented Companies" />

            {/* Companies Grid */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                {companies.map((company) => (
                    <div
                        key={company.id}
                        className="bg-slate-900 border border-slate-800 rounded-xl p-6 flex flex-col justify-between shadow-sm hover:border-slate-700 transition"
                    >
                        <div>
                            <div className="flex justify-between items-start mb-3">
                                <div className="h-10 w-10 rounded-lg flex items-center justify-center font-bold text-white shadow-md"
                                     style={{ backgroundColor: company.settings?.brand_color || '#4f46e5' }}>
                                    {company.name.charAt(0)}
                                </div>
                                <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold ${
                                    company.status === 'active'
                                        ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20'
                                        : 'bg-rose-500/10 text-rose-400 border border-rose-500/20'
                                }`}>
                                    {company.status}
                                </span>
                            </div>

                            <h3 className="text-lg font-bold text-white">{company.name}</h3>
                            <p className="text-xs font-mono text-slate-400 mb-2">slug: {company.slug}</p>
                            <p className="text-xs text-slate-400 line-clamp-2">
                                {company.settings?.tagline || 'Custom Bingo Installation'}
                            </p>

                            <div className="mt-4 pt-4 border-t border-slate-800 flex justify-between items-center text-xs text-slate-400">
                                <span>Registered Accounts:</span>
                                <span className="font-bold text-white">{company.users_count ?? 0}</span>
                            </div>
                        </div>

                        <div className="mt-6 flex space-x-2">
                            <a
                                href={`/c/${company.slug}/admin`}
                                className="flex-1 text-center bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-medium py-2 rounded-lg transition"
                            >
                                Admin Panel
                            </a>
                            <a
                                href={`/c/${company.slug}/dashboard`}
                                className="flex-1 text-center bg-indigo-600/20 hover:bg-indigo-600/30 text-indigo-300 border border-indigo-500/30 text-xs font-medium py-2 rounded-lg transition"
                            >
                                Player Lobby
                            </a>
                        </div>
                    </div>
                ))}
            </div>

            {/* Create Company Modal */}
            {showCreateModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm p-4">
                    <div className="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl">
                        <div className="flex justify-between items-center mb-4">
                            <h2 className="text-lg font-bold text-white">Provision New Company Tenant</h2>
                            <button
                                onClick={() => setShowCreateModal(false)}
                                className="text-slate-400 hover:text-white"
                            >
                                &times;
                            </button>
                        </div>

                        <form onSubmit={submitCreate} className="space-y-4">
                            <div>
                                <label className="block text-xs font-semibold text-slate-300 uppercase mb-1">
                                    Company Name
                                </label>
                                <input
                                    type="text"
                                    value={data.name}
                                    onChange={(e) => {
                                        setData({
                                            ...data,
                                            name: e.target.value,
                                            slug: e.target.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, ''),
                                        });
                                    }}
                                    className="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500"
                                    placeholder="e.g. Royal Palace Bingo"
                                    required
                                />
                                {errors.name && <div className="text-xs text-rose-400 mt-1">{errors.name}</div>}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-300 uppercase mb-1">
                                    Company Slug (Subpath / Subdomain)
                                </label>
                                <input
                                    type="text"
                                    value={data.slug}
                                    onChange={(e) => setData('slug', e.target.value)}
                                    className="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-sm font-mono text-white focus:outline-none focus:border-indigo-500"
                                    placeholder="royal-palace"
                                    required
                                />
                                {errors.slug && <div className="text-xs text-rose-400 mt-1">{errors.slug}</div>}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-300 uppercase mb-1">
                                    Tagline / Slogan
                                </label>
                                <input
                                    type="text"
                                    value={data.tagline}
                                    onChange={(e) => setData('tagline', e.target.value)}
                                    className="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500"
                                    placeholder="e.g. Daily Jackpots & Excitement"
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-300 uppercase mb-1">
                                    Brand Color
                                </label>
                                <div className="flex items-center space-x-3">
                                    <input
                                        type="color"
                                        value={data.brand_color}
                                        onChange={(e) => setData('brand_color', e.target.value)}
                                        className="h-10 w-16 bg-slate-950 border border-slate-800 rounded cursor-pointer"
                                    />
                                    <span className="font-mono text-xs text-slate-400">{data.brand_color}</span>
                                </div>
                            </div>

                            <div className="pt-4 flex justify-end space-x-3 border-t border-slate-800">
                                <button
                                    type="button"
                                    onClick={() => setShowCreateModal(false)}
                                    className="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold rounded-lg"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg shadow-sm"
                                >
                                    {processing ? 'Provisioning...' : 'Provision Company'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </PlatformOwnerLayout>
    );
}
