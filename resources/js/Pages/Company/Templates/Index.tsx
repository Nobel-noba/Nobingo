import CompanyAdminLayout from '@/Layouts/CompanyAdminLayout';
import { PageProps } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import React, { useState } from 'react';

interface TemplateItem {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    pattern_mode: string;
    required_pattern_count: number;
    allowed_pattern_ids: number[];
    winner_policy: string;
    default_call_interval: number;
    default_min_players: number;
    default_max_players: number;
    default_entry_fee: number;
    formatted_entry_fee: string;
    default_prize_configuration: Record<string, any>;
    is_active: boolean;
    is_global: boolean;
}

interface PatternOption {
    id: number;
    name: string;
    slug: string;
    type: string;
}

interface Props extends PageProps {
    company: {
        id: number;
        name: string;
        slug: string;
    };
    templates: TemplateItem[];
    patterns: PatternOption[];
    flash?: {
        success?: string;
    };
}

export default function TemplatesIndex({ auth, company, templates, patterns, flash }: Props) {
    const [isCreating, setIsCreating] = useState(false);

    const form = useForm({
        name: '',
        description: '',
        pattern_mode: 'single_pattern',
        required_pattern_count: 1,
        allowed_pattern_ids: patterns.length > 0 ? [patterns[0].id] : [],
        winner_policy: 'first_valid',
        default_call_interval: 3,
        default_min_players: 2,
        default_max_players: 100,
        default_entry_fee: 1,
        fixed_prize: 100,
    });

    const handleCreateSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(`/c/${company.slug}/admin/templates`, {
            preserveScroll: true,
            onSuccess: () => {
                setIsCreating(false);
                form.reset();
            },
        });
    };

    const handleToggle = (templateId: number) => {
        router.patch(`/c/${company.slug}/admin/templates/${templateId}/toggle`, {}, {
            preserveScroll: true,
        });
    };

    const togglePatternId = (id: number) => {
        const current = form.data.allowed_pattern_ids;
        if (current.includes(id)) {
            if (current.length > 1) {
                form.setData('allowed_pattern_ids', current.filter((x) => x !== id));
            }
        } else {
            form.setData('allowed_pattern_ids', [...current, id]);
        }
    };

    return (
        <CompanyAdminLayout>
            <Head title={`Game Templates - ${company.name}`} />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-white tracking-tight">Game Templates & Rulesets</h1>
                        <p className="text-sm text-neutral-400 mt-1">
                            Reusable room blueprints with preconfigured pattern requirements, entry fees, and prize formulas.
                        </p>
                    </div>

                    <button
                        onClick={() => setIsCreating(true)}
                        className="bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs px-4 py-2.5 rounded-xl transition shadow flex items-center self-start sm:self-auto"
                    >
                        <span className="mr-1.5">+</span> Create New Template
                    </button>
                </div>

                {/* Templates Grid */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    {templates.map((tpl) => (
                        <div
                            key={tpl.id}
                            className={`bg-neutral-900 border rounded-2xl p-5 shadow-sm flex flex-col justify-between transition ${
                                tpl.is_active ? 'border-neutral-800' : 'border-neutral-800/60 opacity-60'
                            }`}
                        >
                            <div className="space-y-3">
                                <div className="flex items-start justify-between">
                                    <div>
                                        <h3 className="text-base font-bold text-white">{tpl.name}</h3>
                                        <span className="text-[11px] font-mono text-neutral-500">{tpl.slug}</span>
                                    </div>
                                    <span
                                        className={`inline-flex px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border ${
                                            tpl.is_active
                                                ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20'
                                                : 'bg-neutral-800 text-neutral-400 border-neutral-700'
                                        }`}
                                    >
                                        {tpl.is_active ? 'Active' : 'Disabled'}
                                    </span>
                                </div>

                                <p className="text-xs text-neutral-400 min-h-[32px] line-clamp-2">
                                    {tpl.description || 'Custom company game template.'}
                                </p>

                                <div className="grid grid-cols-2 gap-2 text-xs pt-2 border-t border-neutral-800">
                                    <div>
                                        <span className="text-neutral-500 block text-[10px] uppercase font-semibold">Required:</span>
                                        <span className="text-neutral-200 font-semibold">{tpl.required_pattern_count} Pattern(s)</span>
                                    </div>
                                    <div>
                                        <span className="text-neutral-500 block text-[10px] uppercase font-semibold">Policy:</span>
                                        <span className="text-neutral-200 font-semibold uppercase">{tpl.winner_policy.replace('_', ' ')}</span>
                                    </div>
                                    <div>
                                        <span className="text-neutral-500 block text-[10px] uppercase font-semibold">Entry Fee:</span>
                                        <span className="text-emerald-400 font-bold">{tpl.formatted_entry_fee}</span>
                                    </div>
                                    <div>
                                        <span className="text-neutral-500 block text-[10px] uppercase font-semibold">Call Interval:</span>
                                        <span className="text-neutral-200">{tpl.default_call_interval}s per ball</span>
                                    </div>
                                    <div>
                                        <span className="text-neutral-500 block text-[10px] uppercase font-semibold">Player Cap:</span>
                                        <span className="text-neutral-200">{tpl.default_min_players} - {tpl.default_max_players}</span>
                                    </div>
                                    <div>
                                        <span className="text-neutral-500 block text-[10px] uppercase font-semibold">Prize:</span>
                                        <span className="text-amber-400 font-bold">
                                            {tpl.default_prize_configuration?.fixed_prize
                                                ? `$${(tpl.default_prize_configuration.fixed_prize / 100).toFixed(2)}`
                                                : 'Dynamic Pot'}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div className="pt-4 border-t border-neutral-800/80 mt-4 flex items-center justify-between">
                                <span className="text-[10px] text-neutral-500">
                                    {tpl.is_global ? 'Global Platform Template' : 'Tenant Template'}
                                </span>
                                <button
                                    onClick={() => handleToggle(tpl.id)}
                                    className={`text-xs px-3 py-1 rounded transition border ${
                                        tpl.is_active
                                            ? 'bg-neutral-800 hover:bg-neutral-700 text-neutral-300 border-neutral-700'
                                            : 'bg-emerald-950/60 hover:bg-emerald-900 text-emerald-300 border-emerald-700/60'
                                    }`}
                                >
                                    {tpl.is_active ? 'Disable' : 'Enable'}
                                </button>
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            {/* Create Template Modal */}
            {isCreating && (
                <div className="fixed inset-0 z-50 bg-black/75 backdrop-blur-sm flex items-center justify-center p-4">
                    <div className="bg-neutral-900 border border-neutral-800 rounded-2xl max-w-xl w-full p-6 shadow-2xl space-y-4 max-h-[90vh] overflow-y-auto">
                        <div className="flex justify-between items-center">
                            <h3 className="text-lg font-bold text-white">Create New Game Template</h3>
                            <button onClick={() => setIsCreating(false)} className="text-neutral-400 hover:text-white">✕</button>
                        </div>

                        <form onSubmit={handleCreateSubmit} className="space-y-4 text-xs">
                            <div>
                                <label className="block uppercase font-bold text-neutral-400 mb-1">Template Name</label>
                                <input
                                    type="text"
                                    required
                                    placeholder="e.g. Saturday Triple Line Special"
                                    value={form.data.name}
                                    onChange={(e) => form.setData('name', e.target.value)}
                                    className="w-full bg-neutral-950 border border-neutral-700 rounded-lg px-3 py-2 text-white focus:ring-indigo-500"
                                />
                            </div>

                            <div>
                                <label className="block uppercase font-bold text-neutral-400 mb-1">Description</label>
                                <textarea
                                    rows={2}
                                    placeholder="Explain rules or target game audience..."
                                    value={form.data.description}
                                    onChange={(e) => form.setData('description', e.target.value)}
                                    className="w-full bg-neutral-950 border border-neutral-700 rounded-lg px-3 py-2 text-white focus:ring-indigo-500"
                                />
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block uppercase font-bold text-neutral-400 mb-1">Required Pattern Count</label>
                                    <input
                                        type="number"
                                        min="1"
                                        max="15"
                                        value={form.data.required_pattern_count}
                                        onChange={(e) => form.setData('required_pattern_count', parseInt(e.target.value) || 1)}
                                        className="w-full bg-neutral-950 border border-neutral-700 rounded-lg px-3 py-2 text-white font-bold"
                                    />
                                </div>
                                <div>
                                    <label className="block uppercase font-bold text-neutral-400 mb-1">Winner Policy</label>
                                    <select
                                        value={form.data.winner_policy}
                                        onChange={(e) => form.setData('winner_policy', e.target.value)}
                                        className="w-full bg-neutral-950 border border-neutral-700 rounded-lg px-3 py-2 text-white"
                                    >
                                        <option value="first_valid">First Valid Winner (Single)</option>
                                        <option value="simultaneous">Simultaneous Split (Ties Allowed)</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label className="block uppercase font-bold text-neutral-400 mb-2">Allowed Winning Patterns</label>
                                <div className="grid grid-cols-2 gap-2 bg-neutral-950 p-3 rounded-lg border border-neutral-800 max-h-40 overflow-y-auto">
                                    {patterns.map((p) => (
                                        <label key={p.id} className="flex items-center space-x-2 text-neutral-300 hover:text-white cursor-pointer">
                                            <input
                                                type="checkbox"
                                                checked={form.data.allowed_pattern_ids.includes(p.id)}
                                                onChange={() => togglePatternId(p.id)}
                                                className="rounded bg-neutral-900 border-neutral-700 text-indigo-600 focus:ring-indigo-500"
                                            />
                                            <span className="truncate">{p.name}</span>
                                        </label>
                                    ))}
                                </div>
                            </div>

                            <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                <div>
                                    <label className="block uppercase font-bold text-neutral-400 mb-1">Entry Fee ($)</label>
                                    <input
                                        type="number"
                                        min="0"
                                        value={form.data.default_entry_fee}
                                        onChange={(e) => form.setData('default_entry_fee', parseInt(e.target.value) || 0)}
                                        className="w-full bg-neutral-950 border border-neutral-700 rounded-lg px-3 py-2 text-white font-bold"
                                    />
                                </div>
                                <div>
                                    <label className="block uppercase font-bold text-neutral-400 mb-1">Fixed Prize ($)</label>
                                    <input
                                        type="number"
                                        min="0"
                                        value={form.data.fixed_prize}
                                        onChange={(e) => form.setData('fixed_prize', parseInt(e.target.value) || 0)}
                                        className="w-full bg-neutral-950 border border-neutral-700 rounded-lg px-3 py-2 text-white font-bold"
                                    />
                                </div>
                                <div>
                                    <label className="block uppercase font-bold text-neutral-400 mb-1">Interval (sec)</label>
                                    <input
                                        type="number"
                                        min="2"
                                        max="60"
                                        value={form.data.default_call_interval}
                                        onChange={(e) => form.setData('default_call_interval', parseInt(e.target.value) || 3)}
                                        className="w-full bg-neutral-950 border border-neutral-700 rounded-lg px-3 py-2 text-white font-bold"
                                    />
                                </div>
                                <div>
                                    <label className="block uppercase font-bold text-neutral-400 mb-1">Max Players</label>
                                    <input
                                        type="number"
                                        min="2"
                                        max="1000"
                                        value={form.data.default_max_players}
                                        onChange={(e) => form.setData('default_max_players', parseInt(e.target.value) || 100)}
                                        className="w-full bg-neutral-950 border border-neutral-700 rounded-lg px-3 py-2 text-white font-bold"
                                    />
                                </div>
                            </div>

                            <div className="flex justify-end space-x-2 pt-4 border-t border-neutral-800">
                                <button
                                    type="button"
                                    onClick={() => setIsCreating(false)}
                                    className="px-4 py-2 rounded-lg font-semibold text-neutral-400 hover:text-white bg-neutral-800"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={form.processing}
                                    className="px-5 py-2 rounded-lg font-bold text-white bg-indigo-600 hover:bg-indigo-500 shadow"
                                >
                                    {form.processing ? 'Creating...' : 'Save Template'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </CompanyAdminLayout>
    );
}
