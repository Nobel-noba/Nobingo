import CompanyAdminLayout from '@/Layouts/CompanyAdminLayout';
import { PageProps } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface TemplateItem {
    id: number;
    name: string;
    description: string;
    pattern_mode: string;
    required_pattern_count: number;
    default_call_interval: number;
    default_entry_fee: number;
    default_min_players: number;
    default_max_players: number;
}

interface Props extends PageProps {
    templates: TemplateItem[];
}

export default function GameCreate({ templates, tenant }: Props) {
    const companySlug = tenant?.slug || 'default';
    const firstTemplate = templates[0];

    const { data, setData, post, processing, errors } = useForm({
        game_template_id: firstTemplate?.id || '',
        name: '',
        description: '',
        entry_fee: firstTemplate ? firstTemplate.default_entry_fee : 0,
        min_players: firstTemplate ? firstTemplate.default_min_players : 1,
        max_players: firstTemplate ? firstTemplate.default_max_players : 100,
        call_interval: firstTemplate ? firstTemplate.default_call_interval : 5,
        auto_open: true,
    });

    const handleTemplateChange = (templateId: number) => {
        const tmpl = templates.find((t) => t.id === templateId);
        if (tmpl) {
            setData({
                ...data,
                game_template_id: tmpl.id,
                name: `${tmpl.name} Match`,
                description: tmpl.description,
                entry_fee: tmpl.default_entry_fee,
                min_players: tmpl.default_min_players,
                max_players: tmpl.default_max_players,
                call_interval: tmpl.default_call_interval,
            });
        }
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(`/c/${companySlug}/admin/games`);
    };

    return (
        <CompanyAdminLayout
            header={
                <div className="flex items-center space-x-3">
                    <Link
                        href={`/c/${companySlug}/admin/games`}
                        className="text-xs text-neutral-400 hover:text-white"
                    >
                        &larr; Back to Games
                    </Link>
                    <span className="text-neutral-600">/</span>
                    <h1 className="text-xl font-bold text-white tracking-tight">
                        Schedule New Bingo Match
                    </h1>
                </div>
            }
        >
            <Head title="Create Game" />

            <div className="max-w-2xl mx-auto bg-neutral-900 border border-neutral-800 rounded-3xl p-6 sm:p-8 shadow-xl">
                <form onSubmit={submit} className="space-y-6">
                    {/* Template Selection */}
                    <div>
                        <label className="block text-xs font-bold text-neutral-300 uppercase tracking-wider mb-2">
                            Select Game Template
                        </label>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            {templates.map((tmpl) => (
                                <button
                                    key={tmpl.id}
                                    type="button"
                                    onClick={() => handleTemplateChange(tmpl.id)}
                                    className={`p-3.5 rounded-2xl border text-left transition ${
                                        Number(data.game_template_id) === tmpl.id
                                            ? 'bg-indigo-600/10 border-indigo-500 text-white shadow-md'
                                            : 'bg-neutral-950/60 border-neutral-800 text-neutral-400 hover:border-neutral-700'
                                    }`}
                                >
                                    <div className="font-bold text-sm text-white">{tmpl.name}</div>
                                    <div className="text-[11px] text-neutral-400 mt-1 line-clamp-1">{tmpl.description}</div>
                                    <div className="text-[10px] text-indigo-400 font-mono mt-1 font-semibold">
                                        Required lines: {tmpl.required_pattern_count}
                                    </div>
                                </button>
                            ))}
                        </div>
                        {errors.game_template_id && (
                            <div className="text-xs text-rose-400 mt-1">{errors.game_template_id}</div>
                        )}
                    </div>

                    {/* Game Name */}
                    <div>
                        <label className="block text-xs font-bold text-neutral-300 uppercase tracking-wider mb-1">
                            Match Title / Room Name
                        </label>
                        <input
                            type="text"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            placeholder="e.g. Friday Jackpot Special"
                            className="w-full bg-neutral-950 border border-neutral-800 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-indigo-500"
                        />
                        {errors.name && <div className="text-xs text-rose-400 mt-1">{errors.name}</div>}
                    </div>

                    {/* Pricing and Timing Grid */}
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label className="block text-xs font-bold text-neutral-300 uppercase tracking-wider mb-1">
                                Entry Fee (Cents)
                            </label>
                            <input
                                type="number"
                                min="0"
                                step="50"
                                value={data.entry_fee}
                                onChange={(e) => setData('entry_fee', parseInt(e.target.value, 10) || 0)}
                                className="w-full bg-neutral-950 border border-neutral-800 rounded-xl px-4 py-2.5 text-sm text-white font-mono focus:outline-none focus:border-indigo-500"
                            />
                            <span className="text-[10px] text-neutral-500 mt-0.5 block">
                                ${(data.entry_fee / 100).toFixed(2)} USD
                            </span>
                        </div>

                        <div>
                            <label className="block text-xs font-bold text-neutral-300 uppercase tracking-wider mb-1">
                                Max Capacity
                            </label>
                            <input
                                type="number"
                                min="2"
                                max="500"
                                value={data.max_players}
                                onChange={(e) => setData('max_players', parseInt(e.target.value, 10) || 100)}
                                className="w-full bg-neutral-950 border border-neutral-800 rounded-xl px-4 py-2.5 text-sm text-white font-mono focus:outline-none focus:border-indigo-500"
                            />
                            <span className="text-[10px] text-neutral-500 mt-0.5 block">Players cap</span>
                        </div>

                        <div>
                            <label className="block text-xs font-bold text-neutral-300 uppercase tracking-wider mb-1">
                                Ball Call Interval
                            </label>
                            <input
                                type="number"
                                min="2"
                                max="60"
                                value={data.call_interval}
                                onChange={(e) => setData('call_interval', parseInt(e.target.value, 10) || 5)}
                                className="w-full bg-neutral-950 border border-neutral-800 rounded-xl px-4 py-2.5 text-sm text-white font-mono focus:outline-none focus:border-indigo-500"
                            />
                            <span className="text-[10px] text-neutral-500 mt-0.5 block">Seconds per ball</span>
                        </div>
                    </div>

                    {/* Auto Open Checkbox */}
                    <div className="flex items-center space-x-3 pt-2">
                        <input
                            type="checkbox"
                            id="auto_open"
                            checked={data.auto_open}
                            onChange={(e) => setData('auto_open', e.target.checked)}
                            className="rounded bg-neutral-950 border-neutral-800 text-indigo-600 focus:ring-0 focus:ring-offset-0 h-4 w-4"
                        />
                        <label htmlFor="auto_open" className="text-xs font-medium text-neutral-300">
                            Open room immediately for player registration and card assignment
                        </label>
                    </div>

                    {/* Submit Actions */}
                    <div className="pt-4 border-t border-neutral-800 flex justify-end space-x-3">
                        <Link
                            href={`/c/${companySlug}/admin/games`}
                            className="px-4 py-2 bg-neutral-800 hover:bg-neutral-700 text-neutral-300 text-xs font-semibold rounded-xl"
                        >
                            Cancel
                        </Link>
                        <button
                            type="submit"
                            disabled={processing}
                            className="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl shadow-lg shadow-indigo-600/20"
                        >
                            {processing ? 'Creating Room...' : 'Create & Schedule Game'}
                        </button>
                    </div>
                </form>
            </div>
        </CompanyAdminLayout>
    );
}
