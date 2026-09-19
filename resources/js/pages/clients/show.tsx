import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    Building2,
    Mail,
    MapPin,
    Pencil,
    Phone,
    Plus,
    Trash2,
    UserRound,
} from 'lucide-react';
import { useState } from 'react';
import {
    destroy as contactDestroy,
    store as contactStore,
    update as contactUpdate,
} from '@/actions/App/Http/Controllers/ClientContactController';
import {
    destroy as clientDestroy,
    edit as clientEdit,
    index as clientIndex,
} from '@/actions/App/Http/Controllers/ClientController';
import {
    create as placeCreate,
    edit as placeEdit,
} from '@/actions/App/Http/Controllers/ClientPlaceController';
import crmMap from '@/actions/App/Http/Controllers/CrmMapController';
import ClientBadge from '@/components/client-badge';
import { HelpButton as Button } from '@/components/crm-map/action-help';
import ClientMapLink from '@/components/crm-map/client-map-link';
import MapReturnLink from '@/components/crm-map/map-return-link';
import FormField from '@/components/form-field';
import { Badge } from '@/components/ui/badge';
import { Button as PlainButton } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useConfirmDialog } from '@/hooks/use-confirm-dialog';
import { lastActivityLabel } from '@/lib/crm';
import { cn } from '@/lib/utils';
import type { ClientContact, ClientDetail } from '@/types';

type Props = {
    client: ClientDetail;
    canManage: boolean;
};

type ContactForm = {
    name: string;
    title: string;
    phone: string;
    email: string;
    note: string;
};

function contactFormData(contact: ClientContact | null): ContactForm {
    return {
        name: contact?.name ?? '',
        title: contact?.title ?? '',
        phone: contact?.phone ?? '',
        email: contact?.email ?? '',
        note: contact?.note ?? '',
    };
}

function ContactEditor({
    clientId,
    contact,
    onDone,
}: {
    clientId: number;
    contact: ClientContact | null;
    onDone: () => void;
}) {
    const form = useForm<ContactForm>(contactFormData(contact));

    function submit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();

        const options = { preserveScroll: true, onSuccess: onDone };

        if (contact) {
            form.patch(contactUpdate.url(contact.id), options);
        } else {
            form.post(contactStore.url(clientId), options);
        }
    }

    return (
        <form
            onSubmit={submit}
            className="grid gap-3 rounded-xl border bg-neutral-50 p-4 sm:grid-cols-2 dark:border-neutral-800 dark:bg-neutral-900"
        >
            <FormField label="氏名" required error={form.errors.name}>
                <Input
                    required
                    value={form.data.name}
                    onChange={(event) =>
                        form.setData('name', event.target.value)
                    }
                />
            </FormField>
            <FormField label="役職" error={form.errors.title}>
                <Input
                    value={form.data.title}
                    onChange={(event) =>
                        form.setData('title', event.target.value)
                    }
                />
            </FormField>
            <FormField label="電話番号" error={form.errors.phone}>
                <Input
                    type="tel"
                    value={form.data.phone}
                    onChange={(event) =>
                        form.setData('phone', event.target.value)
                    }
                />
            </FormField>
            <FormField label="メールアドレス" error={form.errors.email}>
                <Input
                    type="email"
                    value={form.data.email}
                    onChange={(event) =>
                        form.setData('email', event.target.value)
                    }
                />
            </FormField>
            <FormField
                label="メモ"
                error={form.errors.note}
                className="sm:col-span-2"
            >
                <Input
                    value={form.data.note}
                    onChange={(event) =>
                        form.setData('note', event.target.value)
                    }
                />
            </FormField>
            <div className="flex justify-end gap-2 sm:col-span-2">
                <PlainButton type="button" variant="ghost" onClick={onDone}>
                    キャンセル
                </PlainButton>
                <Button
                    helpTitle="担当者を保存"
                    help="氏名・連絡先などをこの顧客の担当者として保存します。"
                    type="submit"
                    disabled={form.processing}
                >
                    保存
                </Button>
            </div>
        </form>
    );
}

export default function ClientShow({ client, canManage }: Props) {
    const { confirm, dialog } = useConfirmDialog();
    const [editingContactId, setEditingContactId] = useState<
        number | 'new' | null
    >(null);

    async function deleteClient() {
        if (
            !(await confirm({
                title: `${client.name} を削除しますか？`,
                description: '地点と記録は地図に表示されなくなります。',
                confirmLabel: '削除',
                variant: 'destructive',
            }))
        ) {
            return;
        }

        router.delete(clientDestroy.url(client.id));
    }

    async function deleteContact(contact: ClientContact) {
        if (
            !(await confirm({
                title: `${contact.name} を削除しますか？`,
                confirmLabel: '削除',
                variant: 'destructive',
            }))
        ) {
            return;
        }

        router.delete(contactDestroy.url(contact.id), {
            preserveScroll: true,
        });
    }

    return (
        <>
            <Head title={client.name} />
            {dialog}
            <div className="mx-auto w-full max-w-5xl space-y-6 p-4 md:p-6 xl:p-8">
                <div className="flex flex-wrap gap-2">
                    <MapReturnLink />
                    <ClientMapLink client={client} />
                    <Button
                        helpTitle="顧客一覧"
                        help="顧客の一覧に戻ります。"
                        asChild
                        variant="ghost"
                    >
                        <Link href={clientIndex()}>顧客一覧</Link>
                    </Button>
                </div>
                <section
                    aria-label={`${client.name}の顧客情報`}
                    className="relative flex flex-wrap items-start justify-between gap-4 overflow-hidden rounded-2xl border border-l-8 bg-white p-5 shadow-sm sm:p-7 dark:border-neutral-800 dark:bg-neutral-950"
                    style={{
                        borderLeftColor: client.color,
                        backgroundImage: `linear-gradient(110deg, ${client.color}18, transparent 75%)`,
                    }}
                >
                    <div className="flex min-w-0 flex-1 items-start gap-4">
                        <ClientBadge
                            client={client}
                            className="size-16 shrink-0 rounded-2xl text-xl ring-4 ring-white dark:ring-neutral-900"
                        />
                        <div className="min-w-0">
                            <p className="mb-1 text-xs font-semibold tracking-widest text-muted-foreground">
                                顧客詳細 · CLIENT #{client.id}
                            </p>
                            <h1 className="text-2xl font-bold wrap-anywhere sm:text-3xl">
                                {client.name}
                            </h1>
                            <p className="mt-2 text-sm text-muted-foreground">
                                担当者 {client.contacts.length}名 ・ 地点{' '}
                                {client.places.length}件
                            </p>
                            {client.note && (
                                <p className="mt-1 text-sm whitespace-pre-line text-muted-foreground">
                                    {client.note}
                                </p>
                            )}
                        </div>
                    </div>
                    {canManage && (
                        <div className="flex flex-wrap gap-2">
                            <Button
                                helpTitle="顧客を編集"
                                help="この顧客の名前・略称・地図の色・メモを変更します。"
                                asChild
                                variant="outline"
                                size="sm"
                            >
                                <Link href={clientEdit(client.id)}>
                                    <Pencil className="size-4" />
                                    編集
                                </Link>
                            </Button>
                            <Button
                                helpTitle="顧客を削除"
                                help="確認後、この顧客と関連する地点・記録を非表示にします。通常の画面からは戻せません。"
                                variant="outline"
                                size="sm"
                                onClick={deleteClient}
                            >
                                <Trash2 className="size-4" />
                                削除
                            </Button>
                        </div>
                    )}
                </section>

                <section className="space-y-3">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-semibold">担当者</h2>
                        {canManage && editingContactId !== 'new' && (
                            <Button
                                helpTitle="担当者を追加"
                                help="この顧客の担当者と連絡先を登録します。記録を追加するときに選べるようになります。"
                                size="sm"
                                variant="outline"
                                onClick={() => setEditingContactId('new')}
                            >
                                <Plus className="size-4" />
                                追加
                            </Button>
                        )}
                    </div>
                    {editingContactId === 'new' && (
                        <ContactEditor
                            clientId={client.id}
                            contact={null}
                            onDone={() => setEditingContactId(null)}
                        />
                    )}
                    {client.contacts.length === 0 &&
                        editingContactId !== 'new' && (
                            <p className="text-sm text-muted-foreground">
                                担当者は登録されていません。
                            </p>
                        )}
                    <ul className="grid gap-3 sm:grid-cols-2">
                        {client.contacts.map((contact) =>
                            editingContactId === contact.id ? (
                                <li key={contact.id} className="sm:col-span-2">
                                    <ContactEditor
                                        clientId={client.id}
                                        contact={contact}
                                        onDone={() => setEditingContactId(null)}
                                    />
                                </li>
                            ) : (
                                <li
                                    key={contact.id}
                                    className="space-y-1 rounded-xl border bg-white p-4 text-sm dark:border-neutral-800 dark:bg-neutral-950"
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <p className="flex items-center gap-2 font-medium">
                                            <UserRound className="size-4 text-muted-foreground" />
                                            {contact.name}
                                            {contact.title && (
                                                <span className="font-normal text-muted-foreground">
                                                    {contact.title}
                                                </span>
                                            )}
                                        </p>
                                        {canManage && (
                                            <div className="flex">
                                                <Button
                                                    helpTitle="担当者を編集"
                                                    help="この担当者の氏名・役職・連絡先を変更します。"
                                                    size="icon"
                                                    variant="ghost"
                                                    aria-label="編集"
                                                    onClick={() =>
                                                        setEditingContactId(
                                                            contact.id,
                                                        )
                                                    }
                                                >
                                                    <Pencil className="size-4" />
                                                </Button>
                                                <Button
                                                    helpTitle="担当者を削除"
                                                    help="確認後、この担当者を削除します。過去の記録の担当者欄は未設定になります。"
                                                    size="icon"
                                                    variant="ghost"
                                                    aria-label="削除"
                                                    onClick={() =>
                                                        deleteContact(contact)
                                                    }
                                                >
                                                    <Trash2 className="size-4" />
                                                </Button>
                                            </div>
                                        )}
                                    </div>
                                    {contact.phone && (
                                        <a
                                            href={`tel:${contact.phone}`}
                                            className="flex items-center gap-2 text-muted-foreground hover:text-foreground"
                                        >
                                            <Phone className="size-4" />
                                            {contact.phone}
                                        </a>
                                    )}
                                    {contact.email && (
                                        <a
                                            href={`mailto:${contact.email}`}
                                            className="flex items-center gap-2 text-muted-foreground hover:text-foreground"
                                        >
                                            <Mail className="size-4" />
                                            {contact.email}
                                        </a>
                                    )}
                                    {contact.note && (
                                        <p className="text-muted-foreground">
                                            {contact.note}
                                        </p>
                                    )}
                                </li>
                            ),
                        )}
                    </ul>
                </section>

                <section className="space-y-3">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-semibold">地点</h2>
                        {canManage && (
                            <Button
                                helpTitle="地点を追加"
                                help="この顧客の事務所・現場・打ち合わせ場所を地図に登録します。"
                                asChild
                                size="sm"
                                variant="outline"
                            >
                                <Link href={placeCreate(client.id)}>
                                    <Plus className="size-4" />
                                    地点を追加
                                </Link>
                            </Button>
                        )}
                    </div>
                    {client.places.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            地点は登録されていません。
                        </p>
                    ) : (
                        <ul className="divide-y rounded-2xl border bg-white dark:border-neutral-800 dark:bg-neutral-950">
                            {client.places.map((place) => (
                                <li
                                    key={place.id}
                                    className={cn(
                                        'flex items-center gap-3 p-4 text-sm',
                                        place.archived_at && 'opacity-50',
                                    )}
                                >
                                    <Link
                                        href={crmMap({
                                            query: {
                                                place: place.id,
                                                ...(place.archived_at
                                                    ? { archived: 1 }
                                                    : {}),
                                            },
                                        })}
                                        className="flex min-w-0 flex-1 items-center gap-3 hover:underline"
                                    >
                                        {place.kind === 'office' ? (
                                            <Building2 className="size-5 shrink-0 text-muted-foreground" />
                                        ) : (
                                            <MapPin className="size-5 shrink-0 text-muted-foreground" />
                                        )}
                                        <span className="min-w-0 flex-1">
                                            <span className="flex items-center gap-2 font-medium">
                                                {place.name}
                                                <Badge variant="outline">
                                                    {place.kind_label}
                                                </Badge>
                                                {place.archived_at && (
                                                    <Badge variant="secondary">
                                                        アーカイブ
                                                    </Badge>
                                                )}
                                            </span>
                                            {place.address && (
                                                <span className="block truncate text-muted-foreground">
                                                    {place.address}
                                                </span>
                                            )}
                                        </span>
                                        <span className="shrink-0 text-xs text-muted-foreground">
                                            {lastActivityLabel(
                                                place.last_logged_at,
                                            )}
                                        </span>
                                    </Link>
                                    {canManage && (
                                        <Button
                                            helpTitle="地点を編集"
                                            help="この地点の名前・住所・地図上の位置を変更します。"
                                            asChild
                                            size="icon"
                                            variant="ghost"
                                            aria-label="地点を編集"
                                        >
                                            <Link href={placeEdit(place.id)}>
                                                <Pencil className="size-4" />
                                            </Link>
                                        </Button>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </>
    );
}

ClientShow.layout = {
    breadcrumbs: [{ title: '顧客', href: clientIndex() }],
};
