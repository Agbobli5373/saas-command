import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, CheckCircle2, ShieldCheck, Sparkles, Users } from 'lucide-react';
import LocaleSwitcher from '@/components/locale-switcher';
import { useI18n } from '@/hooks/use-i18n';
import { dashboard, login, register } from '@/routes';
import type { SharedData } from '@/types';

export default function Welcome({
    canRegister = true,
}: {
    canRegister?: boolean;
}) {
    const { auth } = usePage<SharedData>().props;
    const { t } = useI18n();

    return (
        <>
            <Head title={t('Modern SaaS Starter')}>
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link
                    href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700"
                    rel="stylesheet"
                />
            </Head>

            <div className="min-h-screen bg-[radial-gradient(circle_at_top,_#dbeafe_0%,_#f8fafc_42%,_#ffffff_100%)] px-6 py-8 text-slate-900 dark:bg-[radial-gradient(circle_at_top,_#1f2937_0%,_#0b1220_38%,_#030712_100%)] dark:text-slate-100 lg:px-10">
                <header className="mx-auto flex w-full max-w-6xl items-center justify-between rounded-2xl border border-slate-200/80 bg-white/70 px-4 py-3 shadow-sm backdrop-blur dark:border-slate-700/70 dark:bg-slate-900/70">
                    <div>
                        <p className="text-sm font-semibold tracking-tight">{t('SaaS Command')}</p>
                        <p className="text-xs text-slate-600 dark:text-slate-400">{t('Production-ready billing starter')}</p>
                    </div>

                    <nav className="flex items-center gap-3">
                        <LocaleSwitcher showLabel={false} />
                        {auth.user ? (
                            <Link
                                href={dashboard()}
                                className="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-white"
                            >
                                {t('Open dashboard')}
                                <ArrowRight className="h-4 w-4" />
                            </Link>
                        ) : (
                            <>
                                <Link
                                    href={login()}
                                    className="rounded-lg px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800"
                                >
                                    {t('Log in')}
                                </Link>
                                {canRegister ? (
                                    <Link
                                        href={register()}
                                        className="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-white"
                                    >
                                        {t('Create account')}
                                        <ArrowRight className="h-4 w-4" />
                                    </Link>
                                ) : null}
                            </>
                        )}
                    </nav>
                </header>

                <main className="mx-auto mt-10 grid w-full max-w-6xl gap-6 lg:grid-cols-[1.25fr_0.9fr]">
                    <section className="rounded-3xl border border-slate-200/80 bg-white/90 p-8 shadow-sm backdrop-blur dark:border-slate-700 dark:bg-slate-900/85 lg:p-10">
                        <div className="inline-flex items-center gap-2 rounded-full border border-sky-200 bg-sky-50 px-3 py-1 text-xs font-medium text-sky-800 dark:border-sky-800 dark:bg-sky-950/60 dark:text-sky-200">
                            <Sparkles className="h-3.5 w-3.5" />
                            {t('SaaS starter foundation')}
                        </div>

                        <h1 className="mt-5 text-3xl font-semibold tracking-tight sm:text-4xl lg:text-5xl">
                            {t('Ship a polished SaaS experience with subscriptions, workspaces, and growth-ready tooling.')}
                        </h1>

                        <p className="mt-4 max-w-2xl text-sm leading-relaxed text-slate-600 dark:text-slate-300 sm:text-base">
                            {t('This template gives you billing, onboarding, team collaboration, and plan-based limits out of the box so you can focus on your core product.')}
                        </p>

                        <div className="mt-8 grid gap-3 sm:grid-cols-2">
                            <div className="rounded-xl border border-slate-200 bg-slate-50/70 p-4 dark:border-slate-700 dark:bg-slate-800/60">
                                <p className="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">{t('Monetization')}</p>
                                <p className="mt-1 text-sm font-medium">{t('Stripe checkout, billing portal, audit timeline')}</p>
                            </div>
                            <div className="rounded-xl border border-slate-200 bg-slate-50/70 p-4 dark:border-slate-700 dark:bg-slate-800/60">
                                <p className="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">{t('Multi-tenant')}</p>
                                <p className="mt-1 text-sm font-medium">{t('Workspace roles, invitations, ownership transfer')}</p>
                            </div>
                        </div>
                    </section>

                    <section className="space-y-4">
                        <div className="rounded-3xl border border-slate-200/80 bg-white/90 p-6 shadow-sm backdrop-blur dark:border-slate-700 dark:bg-slate-900/85">
                            <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{t('What you get')}</h2>
                            <ul className="mt-4 space-y-3 text-sm">
                                <li className="flex items-start gap-3">
                                    <CheckCircle2 className="mt-0.5 h-4 w-4 text-emerald-500" />
                                    {t('Plan-aware features and usage metering')}
                                </li>
                                <li className="flex items-start gap-3">
                                    <Users className="mt-0.5 h-4 w-4 text-sky-500" />
                                    {t('Team workflows with workspace context')}
                                </li>
                                <li className="flex items-start gap-3">
                                    <ShieldCheck className="mt-0.5 h-4 w-4 text-violet-500" />
                                    {t('Secure auth with profile and security settings')}
                                </li>
                            </ul>
                        </div>

                        <div className="rounded-3xl border border-slate-200/80 bg-slate-900 p-6 text-slate-100 shadow-sm dark:border-slate-700">
                            <p className="text-xs uppercase tracking-wide text-slate-300">{t('Ready to launch')}</p>
                            <p className="mt-2 text-lg font-semibold">{t('Start building your product, not your boilerplate.')}</p>
                            <p className="mt-2 text-sm text-slate-300">
                                {t('Customize branding, plug in your domain logic, and move directly into feature delivery.')}
                            </p>
                        </div>
                    </section>
                </main>
            </div>
        </>
    );
}
