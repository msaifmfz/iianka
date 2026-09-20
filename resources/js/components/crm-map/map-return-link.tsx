import { Link, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useSyncExternalStore } from 'react';
import crmMap from '@/actions/App/Http/Controllers/CrmMapController';
import { readMapMemory } from '@/lib/crm-map-memory';
import { HelpButton } from './action-help';

const subscribeNever = () => () => {};

export default function MapReturnLink() {
    const { auth } = usePage().props;
    const href = useSyncExternalStore(
        subscribeNever,
        () => {
            const saved = readMapMemory(auth.user.id);

            return saved
                ? crmMap.url({
                      query: {
                          restore: 1,
                          ...(saved.selectedPlaceId
                              ? { place: saved.selectedPlaceId }
                              : {}),
                          ...(saved.archived ? { archived: 1 } : {}),
                      },
                  })
                : crmMap.url();
        },
        () => crmMap.url(),
    );

    return (
        <HelpButton
            helpTitle="地図に戻る"
            help="一覧へ移動する前の位置・拡大率・地図の種類・顧客の絞り込み・開いていた地点に戻ります。"
            asChild
            variant="outline"
        >
            <Link href={href}>
                <ArrowLeft className="size-4" />
                地図に戻る
            </Link>
        </HelpButton>
    );
}
