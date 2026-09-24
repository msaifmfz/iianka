import { Head, Link } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import {
    create as clientCreate,
    index as clientIndex,
    show as clientShow,
} from '@/actions/App/Http/Controllers/ClientController';
import ClientBadge from '@/components/client-badge';
import { HelpButton as Button } from '@/components/crm-map/action-help';
import ClientMapLink from '@/components/crm-map/client-map-link';
import MapReturnLink from '@/components/crm-map/map-return-link';
import { Input } from '@/components/ui/input';
import { useUrlFilters } from '@/hooks/use-url-filters';
import { lastActivityLabel } from '@/lib/crm';
import type { ClientListItem } from '@/types';

type Props = {
    clients: ClientListItem[];
    filters: { search: string };
    canManage: boolean;
};

export default function ClientIndex({ clients, filters, canManage }: Props) {
    const { filters: localFilters, setFilter } = useUrlFilters(
        filters,
        clientIndex.url(),
    );

    return (
        <>
            <Head title="顧客" />
            <div className="mx-auto w-full max-w-5xl space-y-6 p-4 md:p-6 xl:p-8">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p className="text-sm text-muted-foreground">CRM</p>
                        <h1 className="text-2xl font-bold">顧客</h1>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <MapReturnLink />
                        {canManage && (
                            <Button
                                helpTitle="顧客を追加"
                                help="顧客名・略称を登録します。登録後に担当者や地点を追加できます。"
                                asChild
                            >
                                <Link href={clientCreate()}>
                                    <Plus className="size-4" />
                                    顧客を追加
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                <div className="relative">
                    <Search className="pointer-events-none absolute top-2.5 left-3 size-4 text-muted-foreground" />
                    <Input
                        aria-label="顧客名で検索"
                        className="pl-9"
                        placeholder="顧客名で検索"
                        value={localFilters.search}
                        onChange={(event) =>
                            setFilter('search', event.target.value)
                        }
                    />
                </div>

                {clients.length === 0 ? (
                    <p className="rounded-2xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                        {filters.search === ''
                            ? 'まだ顧客が登録されていません。'
                            : '該当する顧客がいません。'}
                    </p>
                ) : (
                    <ul className="divide-y rounded-2xl border bg-white dark:border-neutral-800 dark:bg-neutral-950">
                        {clients.map((client) => (
                            <li
                                key={client.id}
                                className="flex flex-wrap items-center gap-x-4 gap-y-3 p-4"
                            >
                                <Link
                                    href={clientShow(client.id)}
                                    className="flex min-w-0 basis-full items-center gap-3 rounded-lg transition hover:bg-neutral-50 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none sm:flex-1 sm:basis-auto dark:hover:bg-neutral-900"
                                >
                                    <ClientBadge client={client} />
                                    <span className="min-w-0 flex-1">
                                        <span className="block truncate font-medium">
                                            {client.name}
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            地点 {client.places_count}件 ・
                                            最終記録{' '}
                                            {lastActivityLabel(
                                                client.last_logged_at,
                                            )}
                                        </span>
                                    </span>
                                </Link>
                                <div className="ml-auto">
                                    <ClientMapLink client={client} />
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

ClientIndex.layout = {
    breadcrumbs: [{ title: '顧客', href: clientIndex() }],
};
