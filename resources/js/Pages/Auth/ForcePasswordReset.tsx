import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function ForcePasswordReset({ user }: { user: { name: string; email: string } }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('password.force-reset.update'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Set New Password" />

            <div className="mb-6">
                <div className="flex items-center gap-2 mb-2">
                    <span className="p-2 rounded-xl bg-amber-500/10 text-amber-400 border border-amber-500/20 text-lg">
                        🔒
                    </span>
                    <div>
                        <h2 className="text-lg font-bold text-white tracking-tight">Set Your Personal Password</h2>
                        <p className="text-xs text-slate-400">Welcome, <strong className="text-white">{user.name}</strong></p>
                    </div>
                </div>
                <div className="bg-slate-900 border border-slate-800 rounded-xl p-3.5 text-xs text-slate-300 space-y-1">
                    <p>
                        Your account was provisioned by a venue Game Manager. For your security, you must establish a permanent personal password before accessing game lobbies or your wallet.
                    </p>
                </div>
            </div>

            <form onSubmit={submit} className="space-y-4">
                <div>
                    <InputLabel htmlFor="password" value="New Password" />

                    <TextInput
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        className="mt-1 block w-full"
                        autoComplete="new-password"
                        isFocused={true}
                        onChange={(e) => setData('password', e.target.value)}
                        placeholder="At least 8 characters"
                    />

                    <InputError message={errors.password} className="mt-2" />
                </div>

                <div>
                    <InputLabel htmlFor="password_confirmation" value="Confirm New Password" />

                    <TextInput
                        id="password_confirmation"
                        type="password"
                        name="password_confirmation"
                        value={data.password_confirmation}
                        className="mt-1 block w-full"
                        autoComplete="new-password"
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                        placeholder="Re-enter your new password"
                    />

                    <InputError message={errors.password_confirmation} className="mt-2" />
                </div>

                <div className="pt-2 flex items-center justify-between">
                    <a
                        href="/logout"
                        onClick={(e) => {
                            e.preventDefault();
                            const form = document.createElement('form');
                            form.method = 'POST';
                            form.action = '/logout';
                            const csrfInput = document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement;
                            if (csrfInput) {
                                const input = document.createElement('input');
                                input.type = 'hidden';
                                input.name = '_token';
                                input.value = csrfInput.content;
                                form.appendChild(input);
                            }
                            document.body.appendChild(form);
                            form.submit();
                        }}
                        className="text-xs text-slate-400 hover:text-slate-200 transition underline"
                    >
                        Sign out
                    </a>

                    <PrimaryButton disabled={processing} className="bg-rose-600 hover:bg-rose-500">
                        {processing ? 'Saving...' : 'Set Password & Continue'}
                    </PrimaryButton>
                </div>
            </form>
        </GuestLayout>
    );
}
