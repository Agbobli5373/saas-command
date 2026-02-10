export type User = {
    id: number;
    name: string;
    email: string;
    locale?: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Workspace = {
    id: number;
    name: string;
    owner_id: number;
    is_personal: boolean;
    role: 'owner' | 'admin' | 'member';
};

export type Auth = {
    user: User;
    workspaces: Workspace[];
    current_workspace: Workspace | null;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
