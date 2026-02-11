import { Link, router, usePage } from '@inertiajs/react';
import { Building2, Check, LogOut, Settings } from 'lucide-react';
import LocaleSwitcher from '@/components/locale-switcher';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { useI18n } from '@/hooks/use-i18n';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { logout } from '@/routes';
import { edit } from '@/routes/profile';
import { update as updateCurrentWorkspace } from '@/routes/workspaces/current';
import type { SharedData, User } from '@/types';

type Props = {
    user: User;
};

export function UserMenuContent({ user }: Props) {
    const cleanup = useMobileNavigation();
    const { auth } = usePage<SharedData>().props;
    const { t } = useI18n();

    const handleLogout = () => {
        cleanup();
        router.flushAll();
    };

    const handleWorkspaceSwitch = (workspaceId: number) => {
        cleanup();

        router.put(
            updateCurrentWorkspace.url(),
            {
                workspace_id: workspaceId,
            },
            {
                preserveScroll: true,
                preserveState: true,
            },
        );
    };

    return (
        <>
            <DropdownMenuLabel className="p-0 font-normal">
                <div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                    <UserInfo user={user} showEmail={true} />
                </div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuGroup>
                <DropdownMenuItem asChild>
                    <Link
                        className="block w-full cursor-pointer"
                        href={edit()}
                        prefetch
                        onClick={cleanup}
                    >
                        <Settings className="mr-2" />
                        {t('Settings')}
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuGroup>
            {auth.workspaces.length > 0 ? (
                <>
                    <DropdownMenuSeparator />
                    <DropdownMenuGroup>
                        {auth.workspaces.map((workspace) => (
                            <DropdownMenuItem
                                key={workspace.id}
                                className="cursor-pointer"
                                disabled={workspace.id === auth.current_workspace?.id}
                                onClick={() => handleWorkspaceSwitch(workspace.id)}
                            >
                                <Building2 className="mr-2" />
                                <span className="flex-1 truncate">{workspace.name}</span>
                                {workspace.id === auth.current_workspace?.id ? (
                                    <Check className="size-4 text-muted-foreground" />
                                ) : null}
                            </DropdownMenuItem>
                        ))}
                    </DropdownMenuGroup>
                </>
            ) : null}
            <DropdownMenuSeparator />
            <DropdownMenuGroup>
                <LocaleSwitcher
                    variant="stacked"
                    className="px-1"
                    onSwitched={cleanup}
                />
            </DropdownMenuGroup>
            <DropdownMenuSeparator />
            <DropdownMenuItem asChild>
                <Link
                    className="block w-full cursor-pointer"
                    href={logout()}
                    as="button"
                    onClick={handleLogout}
                    data-test="logout-button"
                >
                    <LogOut className="mr-2" />
                    {t('Log out')}
                </Link>
            </DropdownMenuItem>
        </>
    );
}
