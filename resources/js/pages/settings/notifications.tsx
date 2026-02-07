import { Form, Head } from '@inertiajs/react';
import { Bell, CheckCheck, CircleAlert, ExternalLink } from 'lucide-react';
import NotificationController from '@/actions/App/Http/Controllers/Settings/NotificationController';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { index } from '@/routes/notifications';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Notification settings',
        href: index().url,
    },
];

type NotificationItem = {
    id: string;
    type: string;
    title: string;
    message: string;
    actionUrl: string | null;
    createdAt: string | null;
    readAt: string | null;
};

type NotificationCenterProps = {
    status?: string;
    unreadCount: number;
    notifications: NotificationItem[];
};

export default function NotificationCenter({
    status,
    unreadCount,
    notifications,
}: NotificationCenterProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Notifications" />

            <h1 className="sr-only">Notification Center</h1>

            <SettingsLayout>
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="Notifications"
                        description="Track billing and workspace events in one place"
                    />

                    {status ? (
                        <Alert>
                            <CircleAlert className="h-4 w-4" />
                            <AlertTitle>Notification update</AlertTitle>
                            <AlertDescription>{status}</AlertDescription>
                        </Alert>
                    ) : null}

                    <Card>
                        <CardHeader className="flex flex-row items-start justify-between gap-4">
                            <div>
                                <CardTitle className="flex items-center gap-2">
                                    <Bell className="h-5 w-5" />
                                    Account notification center
                                </CardTitle>
                                <CardDescription>
                                    You have {unreadCount} unread {unreadCount === 1 ? 'notification' : 'notifications'}.
                                </CardDescription>
                            </div>

                            <Form {...NotificationController.readAll.form()}>
                                {({ processing }) => (
                                    <Button variant="outline" disabled={processing || unreadCount === 0}>
                                        <CheckCheck className="h-4 w-4" />
                                        Mark all read
                                    </Button>
                                )}
                            </Form>
                        </CardHeader>

                        <CardContent className="space-y-3">
                            {notifications.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No notifications yet.
                                </p>
                            ) : (
                                notifications.map((notification) => (
                                    <div
                                        key={notification.id}
                                        className="space-y-3 rounded-lg border border-border/70 p-4"
                                    >
                                        <div className="flex items-center justify-between gap-3">
                                            <div>
                                                <p className="text-sm font-semibold">
                                                    {notification.title}
                                                </p>
                                                <p className="mt-1 text-sm text-muted-foreground">
                                                    {notification.message}
                                                </p>
                                            </div>

                                            <Badge variant={notification.readAt ? 'outline' : 'default'}>
                                                {notification.readAt ? 'Read' : 'Unread'}
                                            </Badge>
                                        </div>

                                        <div className="flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
                                            <span>
                                                {notification.createdAt
                                                    ? new Date(notification.createdAt).toLocaleString()
                                                    : 'Unknown time'}
                                            </span>

                                            {notification.actionUrl ? (
                                                <a
                                                    href={notification.actionUrl}
                                                    className="inline-flex items-center gap-1 text-sm text-primary underline underline-offset-4"
                                                >
                                                    Open
                                                    <ExternalLink className="h-3.5 w-3.5" />
                                                </a>
                                            ) : null}
                                        </div>

                                        {!notification.readAt ? (
                                            <Form {...NotificationController.read.form(notification.id)}>
                                                {({ processing }) => (
                                                    <Button size="sm" variant="secondary" disabled={processing}>
                                                        Mark as read
                                                    </Button>
                                                )}
                                            </Form>
                                        ) : null}
                                    </div>
                                ))
                            )}
                        </CardContent>
                    </Card>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
