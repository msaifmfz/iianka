export type User = {
    id: number;
    name: string;
    login_id: string;
    email: string | null;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_confirmed_at?: string | null;
    role?: 'admin' | 'editor' | 'viewer';
    is_admin?: boolean;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
    permissions: {
        manage_users: boolean;
        manage_content: boolean;
        view_all_content: boolean;
        view_audit_logs: boolean;
    };
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
