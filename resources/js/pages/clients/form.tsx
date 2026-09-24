import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import {
    index as clientIndex,
    show as clientShow,
    store as clientStore,
    update as clientUpdate,
} from '@/actions/App/Http/Controllers/ClientController';
import ClientBadge from '@/components/client-badge';
import { HelpButton as Button } from '@/components/crm-map/action-help';
import FormField from '@/components/form-field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import type { ClientSummary } from '@/types';

type Props = {
    client: (ClientSummary & { note: string | null }) | null;
};

type ClientForm = {
    name: string;
    short_label: string;
    note: string;
};

export default function ClientFormPage({ client }: Props) {
    const { data, setData, post, patch, processing, errors } =
        useForm<ClientForm>({
            name: client?.name ?? '',
            short_label: client?.short_label ?? '',
            note: client?.note ?? '',
        });
    const title = client ? '顧客を編集' : '顧客を追加';

    function submit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (client) {
            patch(clientUpdate.url(client.id));
        } else {
            post(clientStore.url());
        }
    }

    return (
        <>
            <Head title={title} />
            <div className="mx-auto w-full max-w-3xl space-y-6 p-4 md:p-6 xl:p-8">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p className="text-sm text-muted-foreground">CRM</p>
                        <h1 className="text-2xl font-bold">{title}</h1>
                    </div>
                    <Button
                        helpTitle="戻る"
                        help="入力内容を保存せずに、顧客の画面へ戻ります。"
                        asChild
                        variant="outline"
                    >
                        <Link
                            href={
                                client ? clientShow(client.id) : clientIndex()
                            }
                        >
                            <ArrowLeft className="size-4" />
                            戻る
                        </Link>
                    </Button>
                </div>

                <form
                    onSubmit={submit}
                    className="grid gap-6 rounded-2xl border bg-white p-5 shadow-sm dark:border-neutral-800 dark:bg-neutral-950"
                >
                    <FormField label="顧客名" required error={errors.name}>
                        <Input
                            required
                            value={data.name}
                            onChange={(event) =>
                                setData('name', event.target.value)
                            }
                        />
                    </FormField>

                    <FormField
                        label="略称（3文字まで）"
                        required
                        error={errors.short_label}
                    >
                        <Input
                            required
                            maxLength={3}
                            className="w-28"
                            value={data.short_label}
                            onChange={(event) =>
                                setData('short_label', event.target.value)
                            }
                        />
                    </FormField>

                    <div className="flex items-center gap-3 rounded-2xl bg-neutral-50 p-4 text-sm dark:bg-neutral-900">
                        <ClientBadge
                            client={{
                                short_label: data.short_label || '?',
                            }}
                        />
                        <span className="text-muted-foreground">
                            顧客一覧ではこのように表示されます
                        </span>
                    </div>

                    <FormField label="メモ" error={errors.note}>
                        <Textarea
                            rows={4}
                            value={data.note}
                            onChange={(event) =>
                                setData('note', event.target.value)
                            }
                        />
                    </FormField>

                    <div className="flex justify-end">
                        <Button
                            helpTitle={title}
                            help="顧客名・略称・メモを保存します。"
                            type="submit"
                            disabled={processing}
                        >
                            {processing ? '保存中...' : title}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

ClientFormPage.layout = {
    breadcrumbs: [{ title: '顧客', href: clientIndex() }],
};
