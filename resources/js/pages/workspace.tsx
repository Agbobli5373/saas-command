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
import AppLayout from '@/layouts/app-layout';
import { workspace } from '@/routes';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Workspace',
        href: workspace().url,
    },
];

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
    const canSubmitInvites = canInviteMembers;
    const ownershipCandidates = members.filter((member) => !member.isOwner);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Workspace" />

            <div className="space-y-6 px-4 py-6">
                <Heading
                    title={workspace.name}
                    description="Manage your team members, seats, and invitations"
                />

                {status ? (
                    <Alert>
                        <CircleAlert className="h-4 w-4" />
                        <AlertTitle>Workspace update</AlertTitle>
                        <AlertDescription>{status}</AlertDescription>
                    </Alert>
                ) : null}

                <Card className="border-border/70 bg-gradient-to-br from-background via-background to-muted/30">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Users className="h-5 w-5" />
                            Team members
                        </CardTitle>
                        <CardDescription>
                            Current members in this workspace and their roles.
                        </CardDescription>
                        <div className="flex flex-wrap gap-2 pt-1">
                            {plan.title ? <Badge variant="secondary">Plan: {plan.title}</Badge> : null}
                            <Badge variant="outline">{seatCount} active seats</Badge>
                            <Badge variant="outline">{billedSeatCount} billed seats</Badge>
                            {seatLimit !== null ? (
                                <Badge variant="outline">Seat capacity {seatCount}/{seatLimit}</Badge>
                            ) : null}
                            {remainingSeatCapacity !== null ? (
                                <Badge variant="outline">{remainingSeatCapacity} seats available</Badge>
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
                                        {member.role}
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
                                                        <option value="member">Member</option>
                                                        <option value="admin">Admin</option>
                                                    </select>
                                                    <Button size="sm" type="submit" disabled={processing || member.isOwner}>
                                                        Save role
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
                                                    Remove member
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
                        <CardTitle>Usage this period</CardTitle>
                        <CardDescription>
                            Metered workspace activity for {usagePeriod.label}.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {usageMetrics.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No usage metrics configured.
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
                                            {metric.isUnlimited ? `${metric.used} used` : `${metric.used}/${metric.quota}`}
                                        </Badge>
                                    </div>
                                    {metric.description ? (
                                        <p className="text-xs text-muted-foreground">{metric.description}</p>
                                    ) : null}
                                    {metric.isUnlimited ? (
                                        <p className="text-xs text-muted-foreground">Unlimited on current plan.</p>
                                    ) : (
                                        <p className="text-xs text-muted-foreground">
                                            {metric.remaining ?? 0} remaining this month.
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
                            <CardTitle>Transfer ownership</CardTitle>
                            <CardDescription>
                                Move workspace ownership to another current member.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Form {...WorkspaceController.transferOwnership.form()}>
                                {({ processing, errors }) => (
                                    <div className="grid gap-4 md:grid-cols-[1fr_auto] md:items-end">
                                        <div className="grid gap-2">
                                            <Label htmlFor="owner_id">New owner</Label>
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
                                            Transfer ownership
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
                                Invite teammate
                            </CardTitle>
                            <CardDescription>
                                Send an email invite and assign a role.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {!canInviteMembers && inviteEntitlement.message ? (
                                <Alert>
                                    <CircleAlert className="h-4 w-4" />
                                    <AlertTitle>Invitations unavailable</AlertTitle>
                                    <AlertDescription>
                                        {inviteEntitlement.message}
                                    </AlertDescription>
                                </Alert>
                            ) : null}

                            <Form {...WorkspaceInvitationController.store.form()}>
                                {({ processing, errors }) => (
                                    <div className="grid gap-4 md:grid-cols-[1fr_180px_auto] md:items-end">
                                        <div className="grid gap-2">
                                            <Label htmlFor="invite-email">Email</Label>
                                            <Input
                                                id="invite-email"
                                                name="email"
                                                type="email"
                                                placeholder="teammate@example.com"
                                                autoComplete="off"
                                            />
                                            <InputError message={errors.email} />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="invite-role">Role</Label>
                                            <select
                                                id="invite-role"
                                                name="role"
                                                defaultValue="member"
                                                className="border-input bg-background focus-visible:border-ring focus-visible:ring-ring/50 h-9 rounded-md border px-3 text-sm outline-none focus-visible:ring-[3px]"
                                            >
                                                <option value="member">Member</option>
                                                <option value="admin">Admin</option>
                                            </select>
                                            <InputError message={errors.role} />
                                        </div>

                                        <Button type="submit" disabled={processing || !canSubmitInvites}>
                                            Send invite
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
                            <CardTitle>Outbound webhooks</CardTitle>
                            <CardDescription>
                                Send signed events to integration endpoints with automatic retries.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <Form {...WorkspaceWebhookEndpointController.store.form()}>
                                {({ processing, errors }) => (
                                    <div className="grid gap-4 rounded-lg border border-border/60 p-4 md:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor="webhook-name">Endpoint name</Label>
                                            <Input id="webhook-name" name="name" placeholder="CRM Integration" />
                                            <InputError message={errors.name} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="webhook-url">Destination URL</Label>
                                            <Input id="webhook-url" name="url" placeholder="https://api.example.com/webhooks" />
                                            <InputError message={errors.url} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="webhook-secret">Signing secret</Label>
                                            <Input id="webhook-secret" name="signing_secret" placeholder="whsec_..." />
                                            <InputError message={errors.signing_secret} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="webhook-events">Events (select one or more)</Label>
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
                                                Add webhook endpoint
                                            </Button>
                                        </div>
                                    </div>
                                )}
                            </Form>

                            {webhookEndpoints.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No webhook endpoints configured yet.
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
                                                {endpoint.isActive ? 'Active' : 'Disabled'}
                                            </Badge>
                                        </div>
                                        <p className="text-xs text-muted-foreground">
                                            Events: {endpoint.events.join(', ')}
                                        </p>
                                        {endpoint.lastErrorMessage ? (
                                            <p className="text-xs text-destructive">
                                                Last error: {endpoint.lastErrorMessage}
                                            </p>
                                        ) : null}
                                        <p className="text-xs text-muted-foreground">
                                            Failures: {endpoint.failureCount}
                                            {endpoint.lastDispatchedAt
                                                ? ` . Last delivered ${new Date(endpoint.lastDispatchedAt).toLocaleString()}`
                                                : ''}
                                        </p>
                                        {endpoint.isActive ? (
                                            <Form {...WorkspaceWebhookEndpointController.destroy.form(endpoint.id)}>
                                                {({ processing }) => (
                                                    <Button size="sm" variant="outline" type="submit" disabled={processing}>
                                                        Disable endpoint
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
                        <CardTitle>Pending invitations</CardTitle>
                        <CardDescription>
                            Invitations that are still waiting for acceptance.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {pendingInvitations.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No pending invitations.
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
                                            Expires{' '}
                                            {invitation.expiresAt
                                                ? new Date(invitation.expiresAt).toLocaleString()
                                                : 'Never'}
                                        </p>
                                    </div>
                                    <Badge variant="outline" className="capitalize">
                                        {invitation.role}
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
