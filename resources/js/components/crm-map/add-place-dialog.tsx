import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { create as placeCreate } from '@/actions/App/Http/Controllers/ClientPlaceController';
import { store } from '@/actions/App/Http/Controllers/MapClientController';
import FormField from '@/components/form-field';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import type { ClientPlaceKind, ClientSummary, CrmOption } from '@/types';

type Props = {
    position: { lat: number; lng: number };
    clients: ClientSummary[];
    kinds: CrmOption[];
    onClose: () => void;
    onCreated: () => void;
};

export default function AddPlaceDialog({
    position,
    clients,
    kinds,
    onClose,
    onCreated,
}: Props) {
    const [creatingClient, setCreatingClient] = useState(false);
    const [search, setSearch] = useState('');
    const form = useForm({
        name: '',
        short_label: '',
        note: '',
        place_name: '本社',
        kind: 'office' as ClientPlaceKind,
        address: '',
        lat: Number(position.lat.toFixed(7)),
        lng: Number(position.lng.toFixed(7)),
    });
    const choices = clients.filter((client) =>
        client.name
            .toLocaleLowerCase()
            .includes(search.trim().toLocaleLowerCase()),
    );

    function submit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (form.processing) {
            return;
        }

        form.post(store.url(), {
            preserveScroll: true,
            onSuccess: onCreated,
        });
    }

    return (
        <Dialog
            open
            onOpenChange={(open) => {
                if (!open && !form.processing) {
                    onClose();
                }
            }}
        >
            <DialogContent className="flex max-h-[90svh] flex-col overflow-hidden">
                <DialogHeader className="shrink-0 pr-5">
                    <DialogTitle>
                        {creatingClient
                            ? '新しい顧客と地点を追加'
                            : 'ここに地点を追加'}
                    </DialogTitle>
                    <DialogDescription>
                        {creatingClient
                            ? '顧客と最初の地点をまとめて保存します。住所を入力しても選択した位置は変わりません。'
                            : '新しい顧客を登録するか、既存の顧客を選択してください。'}
                    </DialogDescription>
                </DialogHeader>
                <p
                    className="shrink-0 rounded-lg bg-muted px-3 py-2 text-xs tabular-nums"
                    aria-label="選択した位置"
                >
                    選択した位置：{form.data.lat.toFixed(7)},{' '}
                    {form.data.lng.toFixed(7)}
                </p>
                {creatingClient ? (
                    <form
                        onSubmit={submit}
                        className="flex min-h-0 flex-col gap-4"
                    >
                        <div className="grid min-h-0 gap-4 overflow-y-auto px-1">
                            <fieldset
                                disabled={form.processing}
                                className="grid gap-4"
                            >
                                <legend className="mb-3 text-sm font-semibold">
                                    顧客情報
                                </legend>
                                <FormField
                                    label="顧客名"
                                    required
                                    error={form.errors.name}
                                >
                                    <Input
                                        autoFocus
                                        required
                                        maxLength={255}
                                        value={form.data.name}
                                        onChange={(event) =>
                                            form.setData(
                                                'name',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                                <FormField
                                    label="略称（3文字以内）"
                                    required
                                    error={form.errors.short_label}
                                >
                                    <Input
                                        required
                                        maxLength={3}
                                        value={form.data.short_label}
                                        onChange={(event) =>
                                            form.setData(
                                                'short_label',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                                <FormField
                                    label="顧客メモ"
                                    error={form.errors.note}
                                >
                                    <Textarea
                                        rows={2}
                                        maxLength={5000}
                                        value={form.data.note}
                                        onChange={(event) =>
                                            form.setData(
                                                'note',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                            </fieldset>
                            <fieldset
                                disabled={form.processing}
                                className="grid gap-4 border-t pt-3"
                            >
                                <legend className="px-1 text-sm font-semibold">
                                    最初の地点
                                </legend>
                                <FormField
                                    label="地点名"
                                    required
                                    error={form.errors.place_name}
                                >
                                    <Input
                                        required
                                        maxLength={255}
                                        value={form.data.place_name}
                                        onChange={(event) =>
                                            form.setData(
                                                'place_name',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                                <FormField
                                    label="種別"
                                    required
                                    error={form.errors.kind}
                                >
                                    <NativeSelect
                                        value={form.data.kind}
                                        onChange={(event) =>
                                            form.setData(
                                                'kind',
                                                event.target
                                                    .value as ClientPlaceKind,
                                            )
                                        }
                                    >
                                        {kinds.map((kind) => (
                                            <option
                                                key={kind.value}
                                                value={kind.value}
                                            >
                                                {kind.label}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                </FormField>
                                <FormField
                                    label="住所"
                                    error={form.errors.address}
                                >
                                    <Input
                                        maxLength={255}
                                        value={form.data.address}
                                        onChange={(event) =>
                                            form.setData(
                                                'address',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                            </fieldset>
                            {(form.errors.lat || form.errors.lng) && (
                                <p
                                    role="alert"
                                    className="text-sm text-destructive"
                                >
                                    {form.errors.lat ?? form.errors.lng}
                                </p>
                            )}

                            {clients.length > 0 && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    disabled={form.processing}
                                    onClick={() => setCreatingClient(false)}
                                >
                                    既存の顧客から選び直す
                                </Button>
                            )}
                        </div>
                        <DialogFooter className="shrink-0">
                            <Button
                                type="button"
                                variant="outline"
                                disabled={form.processing}
                                onClick={onClose}
                            >
                                キャンセル
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing
                                    ? '保存中...'
                                    : '顧客と地点を保存'}
                            </Button>
                        </DialogFooter>
                    </form>
                ) : (
                    <div className="grid min-h-0 gap-4 overflow-y-auto">
                        <Button onClick={() => setCreatingClient(true)}>
                            新しい顧客と地点を追加
                        </Button>
                        {clients.length > 0 && (
                            <>
                                <p className="text-sm font-medium">
                                    既存の顧客に地点を追加
                                </p>
                                <Input
                                    aria-label="地点を追加する顧客を探す"
                                    placeholder="顧客名で検索"
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                />
                                {choices.length === 0 ? (
                                    <p className="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">
                                        該当する顧客がいません。
                                    </p>
                                ) : (
                                    <ul className="divide-y rounded-xl border">
                                        {choices.map((client) => (
                                            <li key={client.id}>
                                                <Link
                                                    href={placeCreate(
                                                        client.id,
                                                        { query: position },
                                                    )}
                                                    className="flex items-center gap-3 p-3 text-sm hover:bg-muted"
                                                >
                                                    {client.name}
                                                </Link>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </>
                        )}
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
