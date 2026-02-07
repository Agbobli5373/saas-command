import { Form, Head } from '@inertiajs/react';
import { CircleAlert, MailPlus, Users } from 'lucide-react';
import WorkspaceInvitationController from '@/actions/App/Http/Controllers/Workspace/WorkspaceInvitationController';
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
};

type PendingInvitation = {
    id: number;
    email: string;
    role: 'admin' | 'member';
    expiresAt: string | null;
};

type WorkspaceProps = {
    status?: string;
    workspace: WorkspaceSummary;
    plan: WorkspacePlan;
    members: WorkspaceMember[];
    pendingInvitations: PendingInvitation[];
    canInviteMembers: boolean;
    seatCount: number;
    seatLimit: number | null;
    remainingSeatCapacity: number | null;
    hasReachedSeatLimit: boolean;
    billedSeatCount: number;
};

export default function Workspace({
    status,
    workspace,
    plan,
    members,
    pendingInvitations,
    canInviteMembers,
    seatCount,
    seatLimit,
    remainingSeatCapacity,
    hasReachedSeatLimit,
    billedSeatCount,
}: WorkspaceProps) {
    const canSubmitInvites = canInviteMembers && !hasReachedSeatLimit;

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
                                className="flex items-center justify-between rounded-lg border border-border/60 px-3 py-2"
                            >
                                <div>
                                    <p className="text-sm font-medium">{member.name}</p>
                                    <p className="text-xs text-muted-foreground">{member.email}</p>
                                </div>
                                <Badge variant="secondary" className="capitalize">
                                    {member.role}
                                </Badge>
                            </div>
                        ))}
                    </CardContent>
                </Card>

                {canInviteMembers ? (
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
                            {hasReachedSeatLimit && seatLimit !== null ? (
                                <Alert>
                                    <CircleAlert className="h-4 w-4" />
                                    <AlertTitle>Seat limit reached</AlertTitle>
                                    <AlertDescription>
                                        This plan allows up to {seatLimit} seats. Upgrade to invite more teammates.
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
