import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import Heading from '@/components/heading';
import LocaleSwitcher from '@/components/locale-switcher';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useI18n } from '@/hooks/use-i18n';
import { cn, toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editBilling } from '@/routes/billing';
import { index as notificationsIndex } from '@/routes/notifications';
import { show as operationsShow } from '@/routes/operations';
import { edit } from '@/routes/profile';
import { show } from '@/routes/two-factor';
import { edit as editPassword } from '@/routes/user-password';
import type { NavItem } from '@/types';

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentUrl } = useCurrentUrl();
    const { t } = useI18n();

    const sidebarNavItems: NavItem[] = [
        {
            title: t('Profile'),
            href: edit(),
            icon: null,
        },
        {
            title: t('Password'),
            href: editPassword(),
            icon: null,
        },
        {
            title: t('Two-Factor Auth'),
            href: show(),
            icon: null,
        },
        {
            title: t('Appearance'),
            href: editAppearance(),
            icon: null,
        },
        {
            title: t('Billing'),
            href: editBilling(),
            icon: null,
        },
        {
            title: t('Notifications'),
            href: notificationsIndex(),
            icon: null,
        },
        {
            title: t('Operations'),
            href: operationsShow(),
            icon: null,
        },
    ];

    // When server-side rendering, we only render the layout on the client...
    if (typeof window === 'undefined') {
        return null;
    }

    return (
        <div className="px-4 py-6">
            <div className="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                <Heading
                    title={t('Settings')}
                    description={t('Manage your profile and account settings')}
                />
                <LocaleSwitcher />
            </div>

            <div className="flex flex-col lg:flex-row lg:space-x-12">
                <aside className="w-full max-w-xl lg:w-48">
                    <nav
                        className="flex flex-col space-y-1 space-x-0"
                        aria-label={t('Settings')}
                    >
                        {sidebarNavItems.map((item, index) => (
                            <Button
                                key={`${toUrl(item.href)}-${index}`}
                                size="sm"
                                variant="ghost"
                                asChild
                                className={cn('w-full justify-start', {
                                    'bg-muted': isCurrentUrl(item.href),
                                })}
                            >
                                <Link href={item.href}>
                                    {item.icon && (
                                        <item.icon className="h-4 w-4" />
                                    )}
                                    {item.title}
                                </Link>
                            </Button>
                        ))}
                    </nav>
                </aside>

                <Separator className="my-6 lg:hidden" />

                <div className="flex-1 md:max-w-2xl">
                    <section className="max-w-xl space-y-12">
                        {children}
                    </section>
                </div>
            </div>
        </div>
    );
}
