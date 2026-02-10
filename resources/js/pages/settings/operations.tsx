import { Head } from '@inertiajs/react';
import { CircleAlert, CircleCheckBig, ShieldAlert } from 'lucide-react';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useI18n } from '@/hooks/use-i18n';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { show } from '@/routes/operations';
import type { BreadcrumbItem } from '@/types';

type ReadinessStatus = 'pass' | 'warning' | 'fail';

type ReadinessCheck = {
    key: string;
    label: string;
    status: ReadinessStatus;
    summary: string;
    value: number | string | null;
};

type OperationsProps = {
    status?: string;
    workspaceName: string;
    checkedAt: string;
    overallStatus: ReadinessStatus;
    checks: ReadinessCheck[];
};

function statusBadgeVariant(status: ReadinessStatus): 'default' | 'secondary' | 'destructive' {
    if (status === 'fail') {
        return 'destructive';
    }

    if (status === 'warning') {
        return 'secondary';
    }

    return 'default';
}

function statusIcon(status: ReadinessStatus) {
    if (status === 'fail') {
        return <ShieldAlert className="h-4 w-4" />;
    }

    if (status === 'warning') {
        return <CircleAlert className="h-4 w-4" />;
    }

    return <CircleCheckBig className="h-4 w-4" />;
}

export default function Operations({
    status,
    workspaceName,
    checkedAt,
    overallStatus,
    checks,
}: OperationsProps) {
    const { t, formatDateTime } = useI18n();

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: t('Operations readiness'),
            href: show().url,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Operations readiness')} />

            <h1 className="sr-only">{t('Operations Readiness')}</h1>

            <SettingsLayout>
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title={t('Production readiness')}
                        description={t('Operational checks for :workspace', {
                            workspace: workspaceName,
                        })}
                    />

                    {status ? (
                        <Alert>
                            <CircleAlert className="h-4 w-4" />
                            <AlertTitle>{t('Operations update')}</AlertTitle>
                            <AlertDescription>{status}</AlertDescription>
                        </Alert>
                    ) : null}

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                {statusIcon(overallStatus)}
                                {t('Readiness summary')}
                            </CardTitle>
                            <CardDescription>
                                {t('Last checked :time', { time: formatDateTime(checkedAt) })}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Badge variant={statusBadgeVariant(overallStatus)} className="capitalize">
                                {t(overallStatus)}
                            </Badge>
                        </CardContent>
                    </Card>

                    <div className="space-y-3">
                        {checks.map((check) => (
                            <Card key={check.key}>
                                <CardHeader className="gap-2">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <CardTitle className="text-base">{check.label}</CardTitle>
                                        <Badge variant={statusBadgeVariant(check.status)} className="capitalize">
                                            {t(check.status)}
                                        </Badge>
                                    </div>
                                    <CardDescription>{check.summary}</CardDescription>
                                </CardHeader>
                            </Card>
                        ))}
                    </div>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
