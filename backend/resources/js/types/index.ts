export interface User {
    id: string;
    name: string;
    email: string;
    email_verified_at?: string;
    avatar?: string;
    created_at: string;
    updated_at: string;
}

export interface FlashMessages {
    success?: string;
    error?: string;
    info?: string;
}

export interface PageProps {
    auth: {
        user: User | null;
    };
    ziggy: {
        url: string;
        port: number | null;
        defaults: Record<string, string>;
        routes: Record<string, { uri: string; methods: string[] }>;
        location: string;
    };
    flash: FlashMessages;
    [key: string]: unknown;
}

export interface PaginatedResponse<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
    links: PaginationLink[];
}

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}
