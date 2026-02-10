import { Form, Head } from '@inertiajs/react';
import { CircleAlert, MailPlus, Users } from 'lucide-react';
import WorkspaceController from '@/actions/App/Http/Controllers/Workspace/WorkspaceController';
import WorkspaceInvitationController from '@/actions/App/Http/Controllers/Workspace/WorkspaceInvitationController';
import WorkspaceWebhookEndpointController from '@/actions/App/Http/Controllers/Workspace/WorkspaceWebhookEndpointController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useI18n } from '@/hooks/use-i18n';
import AppLayout from '@/layouts/app-layout';
import { workspace as workspaceRoute } from '@/routes';
import type { BreadcrumbItem } from '@/types';

type WorkspaceSummary = {
    id: number;
    name: string;
};

type WorkspacePlan = {
    key: string | null;
    title: string | null;
    billingMode: 'free' | 'stripe' | null;
};

type WorkspaceMember = {
    id: number;
    name: string;
    email: string;
    role: 'owner' | 'admin' | 'member';
    isOwner: boolean;
};

type PendingInvitation = {
    id: number;
    email: string;
    role: 'admin' | 'member';
    expiresAt: string | null;
};

type UsagePeriod = {
    start: string;
    end: string;
    label: string;
};

type UsageMetric = {
    key: string;
    title: string;
    description: string | null;
    quota: number | null;
    used: number;
    remaining: number | null;
    percentage: number | null;
    isUnlimited: boolean;
    isExceeded: boolean;
};

type WebhookEndpoint = {
    id: number;
    name: string;
    url: string;
    events: string[];
    isActive: boolean;
    lastDispatchedAt: string | null;
    lastErrorAt: string | null;
    lastErrorMessage: string | null;
    failureCount: number;
};

type InviteEntitlement = {
    reasonCode:
        | 'ok'
        | 'insufficient_role'
        | 'feature_unavailable'
        | 'seat_limit_reached'
        | 'usage_limit_reached';
    message: string | null;
    usageQuota: number | null;
    usageUsed: number;
    usageRemaining: number | null;
};

type WorkspaceProps = {
    status?: string;
    workspace: WorkspaceSummary;
    plan: WorkspacePlan;
    members: WorkspaceMember[];
    pendingInvitations: PendingInvitation[];
    webhookEndpoints: WebhookEndpoint[];
    supportedWebhookEvents: Record<string, string>;
    canInviteMembers: boolean;
    canManageInvitations: boolean;
    canManageWebhooks: boolean;
    inviteEntitlement: InviteEntitlement;
    canManageMembers: boolean;
    canTransferOwnership: boolean;
    currentUserId: number;
    seatCount: number;
    seatLimit: number | null;
    remainingSeatCapacity: number | null;
    hasReachedSeatLimit: boolean;
    billedSeatCount: number;
    usagePeriod: UsagePeriod;
    usageMetrics: UsageMetric[];
};

export default function Workspace({
    status,
    workspace,
    plan,
    members,
    pendingInvitations,
    webhookEndpoints,
    supportedWebhookEvents,
    canInviteMembers,
    canManageInvitations,
    canManageWebhooks,
    inviteEntitlement,
    canManageMembers,
    canTransferOwnership,
    currentUserId,
    seatCount,
    seatLimit,
    remainingSeatCapacity,
    hasReachedSeatLimit,
    billedSeatCount,
    usagePeriod,
    usageMetrics,
}: WorkspaceProps) {
    const { t, formatDateTime } = useI18n();
    const canSubmitInvites = canInviteMembers;
    const ownershipCandidates = members.filter((member) => !member.isOwner);

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: t('Workspace'),
            href: workspaceRoute().url,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Workspace')} />

            <div className="space-y-6 px-4 py-6">
                <Heading
                    title={workspace.name}
                    description={t('Manage your team members, seats, and invitations')}
                />

                {status ? (
                    <Alert>
                        <CircleAlert className="h-4 w-4" />
                        <AlertTitle>{t('Workspace update')}</AlertTitle>
                        <AlertDescription>{status}</AlertDescription>
                    </Alert>
                ) : null}

                <Card className="border-border/70 bg-gradient-to-br from-background via-background to-muted/30">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Users className="h-5 w-5" />
                            {t('Team members')}
                        </CardTitle>
                        <CardDescription>
                            {t('Current members in this workspace and their roles.')}
                        </CardDescription>
                        <div className="flex flex-wrap gap-2 pt-1">
                            {plan.title ? <Badge variant="secondary">{t('Plan: :plan', { plan: plan.title })}</Badge> : null}
                            <Badge variant="outline">{t(':count active seats', { count: seatCount })}</Badge>
                            <Badge variant="outline">{t(':count billed seats', { count: billedSeatCount })}</Badge>
                            {seatLimit !== null ? (
                                <Badge variant="outline">
                                    {t('Seat capacity :used/:limit', {
                                        used: seatCount,
                                        limit: seatLimit,
                                    })}
                                </Badge>
                            ) : null}
                            {remainingSeatCapacity !== null ? (
                                <Badge variant="outline">{t(':count seats available', { count: remainingSeatCapacity })}</Badge>
                            ) : null}
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {members.map((member) => (
                            <div
                                key={member.id}
                                className="space-y-3 rounded-lg border border-border/60 px-3 py-3"
                            >
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="text-sm font-medium">{member.name}</p>
                                        <p className="text-xs text-muted-foreground">{member.email}</p>
                                    </div>
                                    <Badge variant="secondary" className="capitalize">
                                        {member.isOwner ? t('Owner') : member.role === 'admin' ? t('Admin') : t('Member')}
                                    </Badge>
                                </div>

                                {canManageMembers ? (
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Form {...WorkspaceController.updateMemberRole.form(member.id)}>
                                            {({ processing }) => (
                                                <div className="flex items-center gap-2">
                                                    <select
                                                        name="role"
                                                        defaultValue={member.role === 'owner' ? 'admin' : member.role}
                                                        disabled={processing || member.isOwner}
                                                        className="border-input bg-background focus-visible:border-ring focus-visible:ring-ring/50 h-9 rounded-md border px-3 text-sm outline-none focus-visible:ring-[3px]"
                                                    >
                                                        <option value="member">{t('Member')}</option>
                                                        <option value="admin">{t('Admin')}</option>
                                                    </select>
                                                    <Button size="sm" type="submit" disabled={processing || member.isOwner}>
                                                        {t('Save role')}
                                                    </Button>
                                                </div>
                                            )}
                                        </Form>

                                        <Form {...WorkspaceController.destroyMember.form(member.id)}>
                                            {({ processing }) => (
                                                <Button
                                                    size="sm"
                                                    variant="destructive"
                                                    type="submit"
                                                    disabled={processing || member.isOwner || member.id === currentUserId}
                                                >
                                                    {t('Remove member')}
                                                </Button>
                                            )}
                                        </Form>
                                    </div>
                                ) : null}
                            </div>
                        ))}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('Usage this period')}</CardTitle>
                        <CardDescription>
                            {t('Metered workspace activity for :period.', {
                                period: usagePeriod.label,
                            })}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {usageMetrics.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                {t('No usage metrics configured.')}
                            </p>
                        ) : (
                            usageMetrics.map((metric) => (
                                <div
                                    key={metric.key}
                                    className="space-y-2 rounded-lg border border-border/60 px-3 py-3"
                                >
                                    <div className="flex items-center justify-between gap-2">
                                        <p className="text-sm font-medium">{metric.title}</p>
                                        <Badge variant={metric.isExceeded ? 'destructive' : 'outline'}>
                                            {metric.isUnlimited
                                                ? t(':count used', { count: metric.used })
                                                : t(':used/:quota', {
                                                    used: metric.used,
                                                    quota: metric.quota ?? 0,
                                                })}
                                        </Badge>
                                    </div>
                                    {metric.description ? (
                                        <p className="text-xs text-muted-foreground">{metric.description}</p>
                                    ) : null}
                                    {metric.isUnlimited ? (
                                        <p className="text-xs text-muted-foreground">{t('Unlimited on current plan.')}</p>
                                    ) : (
                                        <p className="text-xs text-muted-foreground">
                                            {t(':count remaining this month.', { count: metric.remaining ?? 0 })}
                                        </p>
                                    )}
                                </div>
                            ))
                        )}
                    </CardContent>
                </Card>

                {canTransferOwnership ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('Transfer ownership')}</CardTitle>
                            <CardDescription>
                                {t('Move workspace ownership to another current member.')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Form {...WorkspaceController.transferOwnership.form()}>
                                {({ processing, errors }) => (
                                    <div className="grid gap-4 md:grid-cols-[1fr_auto] md:items-end">
                                        <div className="grid gap-2">
                                            <Label htmlFor="owner_id">{t('New owner')}</Label>

                                            <select
                                                id="owner_id"
                                                name="owner_id"
                                                defaultValue={ownershipCandidates[0]?.id ?? ''}
                                                className="border-input bg-background focus-visible:border-ring focus-visible:ring-ring/50 h-9 rounded-md border px-3 text-sm outline-none focus-visible:ring-[3px]"
                                            >
                                                {ownershipCandidates.map((member) => (
                                                    <option key={member.id} value={member.id}>
                                                        {member.name} ({member.email})
                                                    </option>
                                                ))}
                                            </select>
                                            <InputError message={errors.owner_id} />
                                        </div>
                                        <Button type="submit" disabled={processing || ownershipCandidates.length === 0}>
                                            {t('Transfer ownership')}
                                        </Button>
                                    </div>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
                ) : null}

                {canManageInvitations ? (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <MailPlus className="h-5 w-5" />
                                {t('Invite teammate')}
                            </CardTitle>
                            <CardDescription>
                                {t('Send an email invite and assign a role.')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {!canInviteMembers && inviteEntitlement.message ? (
                                <Alert>
                                    <CircleAlert className="h-4 w-4" />
                                    <AlertTitle>{t('Invitations unavailable')}</AlertTitle>
                                    <AlertDescription>
                                        {inviteEntitlement.message}
                                    </AlertDescription>
                                </Alert>
                            ) : null}

                            <Form {...WorkspaceInvitationController.store.form()}>
                                {({ processing, errors }) => (
                                    <div className="grid gap-4 md:grid-cols-[1fr_180px_auto] md:items-end">
                                        <div className="grid gap-2">
                                            <Label htmlFor="invite-email">{t('Email')}</Label>

                                            <Input
                                                id="invite-email"
                                                name="email"
                                                type="email"
                                                placeholder={t('teammate@example.com')}
                                                autoComplete="off"
                                            />
                                            <InputError message={errors.email} />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="invite-role">{t('Role')}</Label>
                                            <select
                                                id="invite-role"
                                                name="role"
                                                defaultValue="member"
                                                className="border-input bg-background focus-visible:border-ring focus-visible:ring-ring/50 h-9 rounded-md border px-3 text-sm outline-none focus-visible:ring-[3px]"
                                            >
                                                <option value="member">{t('Member')}</option>
                                                <option value="admin">{t('Admin')}</option>
                                            </select>
                                            <InputError message={errors.role} />
                                        </div>

                                        <Button type="submit" disabled={processing || !canSubmitInvites}>
                                            {t('Send invite')}
                                        </Button>
                                    </div>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
                ) : null}

                {canManageWebhooks ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('Outbound webhooks')}</CardTitle>
                            <CardDescription>
                                {t('Send signed events to integration endpoints with automatic retries.')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <Form {...WorkspaceWebhookEndpointController.store.form()}>
                                {({ processing, errors }) => (
                                    <div className="grid gap-4 rounded-lg border border-border/60 p-4 md:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor="webhook-name">{t('Endpoint name')}</Label>
                                            <Input id="webhook-name" name="name" placeholder={t('CRM Integration')} />
                                            <InputError message={errors.name} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="webhook-url">{t('Destination URL')}</Label>
                                            <Input id="webhook-url" name="url" placeholder={t('https://api.example.com/webhooks')} />
                                            <InputError message={errors.url} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="webhook-secret">{t('Signing secret')}</Label>
                                            <Input id="webhook-secret" name="signing_secret" placeholder={t('whsec_...')} />
                                            <InputError message={errors.signing_secret} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="webhook-events">{t('Events (select one or more)')}</Label>
                                            <select
                                                id="webhook-events"
                                                name="events[]"
                                                multiple
                                                defaultValue={Object.keys(supportedWebhookEvents)}
                                                className="border-input bg-background focus-visible:border-ring focus-visible:ring-ring/50 min-h-32 rounded-md border px-3 py-2 text-sm outline-none focus-visible:ring-[3px]"
                                            >
                                                {Object.entries(supportedWebhookEvents).map(([eventKey, label]) => (
                                                    <option key={eventKey} value={eventKey}>
                                                        {label}
                                                    </option>
                                                ))}
                                            </select>
                                            <InputError message={errors.events} />
                                        </div>
                                        <div className="md:col-span-2">
                                            <Button type="submit" disabled={processing}>
                                                {t('Add webhook endpoint')}
                                            </Button>
                                        </div>
                                    </div>
                                )}
                            </Form>

                            {webhookEndpoints.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    {t('No webhook endpoints configured yet.')}
                                </p>
                            ) : (
                                webhookEndpoints.map((endpoint) => (
                                    <div key={endpoint.id} className="space-y-2 rounded-lg border border-border/60 p-4">
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <div>
                                                <p className="text-sm font-medium">{endpoint.name}</p>
                                                <p className="text-xs text-muted-foreground">{endpoint.url}</p>
                                            </div>
                                            <Badge variant={endpoint.isActive ? 'default' : 'outline'}>
                                                {endpoint.isActive ? t('Active') : t('Disabled')}
                                            </Badge>
                                        </div>
                                        <p className="text-xs text-muted-foreground">
                                            {t('Events: :events', { events: endpoint.events.join(', ') })}
                                        </p>
                                        {endpoint.lastErrorMessage ? (
                                            <p className="text-xs text-destructive">
                                                {t('Last error: :error', { error: endpoint.lastErrorMessage })}
                                            </p>
                                        ) : null}
                                        <p className="text-xs text-muted-foreground">
                                            {t('Failures: :count', { count: endpoint.failureCount })}
                                            {endpoint.lastDispatchedAt
                                                ? ` . ${t('Last delivered :time', { time: formatDateTime(endpoint.lastDispatchedAt) })}`
                                                : ''}
                                        </p>
                                        {endpoint.isActive ? (
                                            <Form {...WorkspaceWebhookEndpointController.destroy.form(endpoint.id)}>
                                                {({ processing }) => (
                                                    <Button size="sm" variant="outline" type="submit" disabled={processing}>
                                                        {t('Disable endpoint')}
                                                    </Button>
                                                )}
                                            </Form>
                                        ) : null}
                                    </div>
                                ))
                            )}
                        </CardContent>
                    </Card>
                ) : null}

                <Card>
                    <CardHeader>
                        <CardTitle>{t('Pending invitations')}</CardTitle>
                        <CardDescription>
                            {t('Invitations that are still waiting for acceptance.')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {pendingInvitations.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                {t('No pending invitations.')}
                            </p>
                        ) : (
                            pendingInvitations.map((invitation) => (
                                <div
                                    key={invitation.id}
                                    className="flex items-center justify-between rounded-lg border border-border/60 px-3 py-2"
                                >
                                    <div>
                                        <p className="text-sm font-medium">{invitation.email}</p>
                                        <p className="text-xs text-muted-foreground">
                                            {t('Expires')}{' '}
                                            {invitation.expiresAt
                                                ? formatDateTime(invitation.expiresAt)
                                                : t('Never')}
                                        </p>
                                    </div>
                                    <Badge variant="outline" className="capitalize">
                                        {invitation.role === 'admin' ? t('Admin') : t('Member')}
                                    </Badge>
                                </div>
                            ))
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
