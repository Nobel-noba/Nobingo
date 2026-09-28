export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    balance: number;
    formatted_balance: string;
    company_id: number | null;
    roles: string[];
    is_platform_owner: boolean;
    is_company_admin: boolean;
    is_game_manager: boolean;
    is_player: boolean;
}

export interface Tenant {
    id: number;
    name: string;
    slug: string;
    status: string;
    settings?: {
        brand_color?: string;
        tagline?: string;
        currency?: string;
        [key: string]: unknown;
    };
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
    tenant?: Tenant | null;
    flash?: {
        success?: string | null;
        error?: string | null;
    };
};
