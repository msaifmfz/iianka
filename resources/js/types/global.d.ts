import type { Auth } from '@/types/auth';
import type { AttentionSummary } from '@/types/navigation';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            attention: AttentionSummary;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
