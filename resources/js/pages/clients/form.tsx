import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Check } from 'lucide-react';
import {
    index as clientIndex,
    show as clientShow,
    store as clientStore,
    update as clientUpdate,
} from '@/actions/App/Http/Controllers/ClientController';
import ClientBadge from '@/components/client-badge';
import {
    ActionHelp,
    HelpButton as Button,
} from '@/components/crm-map/action-help';
import FormField from '@/components/form-field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { CLIENT_COLOR_PALETTE, pickClientColor } from '@/lib/crm-colors';
import { cn } from '@/lib/utils';
import type { ClientSummary } from '@/types';

type Props = {
    client: (ClientSummary & { note: string | null }) | null;
    usedColors: string[];
};

type ClientForm = {
    name: string;
    short_label: string;
    color: string;
    note: string;
};

export default function ClientFormPage({ client, usedColors }: Props) {
    const { data, setData, post, patch, processing, errors } =
        useForm<ClientForm>({
            name: client?.name ?? '',
            short_label: client?.short_label ?? '',
            color: client?.color ?? pickClientColor(usedColors),
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
                        label="略称（地図のピンに表示・3文字まで）"
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

                    <FormField
                        as="div"
                        label={
                            <span className="flex items-center gap-1">
                                色（この顧客のピンすべてに使われます）
                                <ActionHelp title="色を選ぶ">
                                    丸い色ボタンを押すと、この顧客のピンの色になります。薄く表示された色は他の顧客が使用中です。「その他の色」から好きな色も選べます。
                                </ActionHelp>
                            </span>
                        }
                        required
                        error={errors.color}
                    >
                        <div className="flex flex-wrap items-center gap-2">
                            {CLIENT_COLOR_PALETTE.map((color) => {
                                const isSelected = data.color === color;
                                const isUsed = usedColors.includes(color);

                                return (
                                    <button
                                        key={color}
                                        type="button"
                                        aria-label={`${color}${isUsed ? '（使用中）' : ''}`}
                                        aria-pressed={isSelected}
                                        onClick={() => setData('color', color)}
                                        className={cn(
                                            'relative inline-flex size-9 items-center justify-center rounded-full ring-offset-2 transition dark:ring-offset-neutral-950',
                                            isSelected && 'ring-2 ring-ring',
                                            isUsed &&
                                                !isSelected &&
                                                'opacity-40',
                                        )}
                                        style={{ backgroundColor: color }}
                                    >
                                        {isSelected && (
                                            <Check className="size-4 text-white" />
                                        )}
                                    </button>
                                );
                            })}
                            <Input
                                type="color"
                                aria-label="その他の色"
                                className="h-9 w-14 cursor-pointer p-1"
                                value={data.color}
                                onChange={(event) =>
                                    setData('color', event.target.value)
                                }
                            />
                        </div>
                        <p className="text-xs font-normal text-muted-foreground">
                            薄く表示された色は他の顧客が使用中です。
                        </p>
                    </FormField>

                    <div className="flex items-center gap-3 rounded-2xl bg-neutral-50 p-4 text-sm dark:bg-neutral-900">
                        <ClientBadge
                            client={{
                                color: data.color,
                                short_label: data.short_label || '?',
                            }}
                        />
                        <span className="text-muted-foreground">
                            地図ではこのように表示されます
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
                            help="顧客名・略称・地図の色・メモを保存します。"
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
