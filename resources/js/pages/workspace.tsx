import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { workspace } from '@/routes';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Workspace',
        href: workspace().url,
    },
];

export default function Workspace() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Workspace" />

            <div className="space-y-6 px-4 py-6">
                <Heading
                    title="Paid Workspace"
                    description="This area is protected by the subscribed middleware"
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Subscription gated area</CardTitle>
                    </CardHeader>
                    <CardContent className="text-sm text-muted-foreground">
                        Your SaaS starter can place premium features here.
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
