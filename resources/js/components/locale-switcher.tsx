import { router } from '@inertiajs/react';
import { useState } from 'react';
import { cn } from '@/lib/utils';
import { update as updateLocale } from '@/routes/locale';
import { useI18n } from '@/hooks/use-i18n';

type Props = {
    variant?: 'inline' | 'stacked';
    className?: string;
    showLabel?: boolean;
    onSwitched?: () => void;
};

export default function LocaleSwitcher({
    variant = 'inline',
    className = '',
    showLabel = true,
    onSwitched,
}: Props) {
    const { locale, supportedLocales, t } = useI18n();
    const [pendingLocale, setPendingLocale] = useState<string | null>(null);

    const handleSwitch = (localeCode: string): void => {
        if (localeCode === locale || pendingLocale !== null) {
            return;
        }

        setPendingLocale(localeCode);

        router.post(
            updateLocale.url(),
            {
                locale: localeCode,
            },
            {
                preserveScroll: true,
                preserveState: false,
                onSuccess: () => onSwitched?.(),
                onFinish: () => setPendingLocale(null),
            },
        );
    };

    return (
        <div className={cn('space-y-1', className)}>
            {showLabel ? (
                <p className="px-1 text-xs font-medium text-muted-foreground">
                    {t('Language')}
                </p>
            ) : null}
            <div
                className={cn(
                    variant === 'inline'
                        ? 'inline-flex items-center gap-1 rounded-md border border-border bg-background p-1'
                        : 'flex flex-col gap-1',
                )}
            >
                {supportedLocales.map((supportedLocale) => {
                    const isActive = supportedLocale.code === locale;

                    return (
                        <button
                            key={supportedLocale.code}
                            type="button"
                            onClick={() => handleSwitch(supportedLocale.code)}
                            disabled={isActive || pendingLocale !== null}
                            className={cn(
                                'rounded-sm px-2.5 py-1 text-sm transition-colors',
                                variant === 'inline'
                                    ? 'text-muted-foreground hover:bg-muted hover:text-foreground'
                                    : 'w-full text-left text-muted-foreground hover:bg-muted hover:text-foreground',
                                isActive
                                    ? 'bg-muted text-foreground'
                                    : '',
                            )}
                        >
                            {supportedLocale.label}
                        </button>
                    );
                })}
            </div>
        </div>
    );
}
