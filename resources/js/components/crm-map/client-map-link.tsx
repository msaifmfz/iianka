import { Link } from '@inertiajs/react';
import { MapPin } from 'lucide-react';
import crmMap from '@/actions/App/Http/Controllers/CrmMapController';
import type { ClientSummary } from '@/types';
import { HelpButton } from './action-help';

export default function ClientMapLink({ client }: { client: ClientSummary }) {
    return (
        <HelpButton
            asChild
            size="sm"
            variant="outline"
            helpTitle="顧客を地図で表示"
            help="この顧客の地点が収まる範囲で地図を開きます。アーカイブ済みの地点も含みます。"
        >
            <Link
                href={crmMap({ query: { client: client.id, archived: 1 } })}
                aria-label={`${client.name}を地図で表示`}
            >
                <MapPin className="size-4" />
                地図で表示
            </Link>
        </HelpButton>
    );
}
