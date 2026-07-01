import type { Auth } from '@/types/auth';
import type { AttentionSummary } from '@/types/navigation';
import type { FlashToast } from '@/types/ui';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        flashDataType: {
            toast?: FlashToast;
            [key: string]: unknown;
        };
        sharedPageProps: {
            name: string;
            auth: Auth;
            attention: AttentionSummary;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
