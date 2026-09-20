import { HttpResponseError } from '@inertiajs/core';
import { useHttp } from '@inertiajs/react';
import { useState } from 'react';
import { store } from '@/actions/App/Http/Controllers/ClientContactController';
import FormField from '@/components/form-field';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import type { ClientContact, ClientSummary } from '@/types';
import { HelpButton } from './action-help';

type ContactFields = {
    name: string;
    title: string;
    phone: string;
    email: string;
};

export default function ContactCreateDialog({
    client,
    onCreated,
    onClose,
}: {
    client: ClientSummary;
    onCreated: (contact: ClientContact) => void;
    onClose: () => void;
}) {
    const form = useHttp<ContactFields, { contact: ClientContact }>({
        name: '',
        title: '',
        phone: '',
        email: '',
    });
    const [requestError, setRequestError] = useState<string | null>(null);

    async function submit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        event.stopPropagation();

        if (form.processing) {
            return;
        }

        setRequestError(null);

        try {
            const response = await form.post(store.url(client.id));
            onCreated(response.contact);
        } catch (error) {
            // A 422 already populated form.errors field by field; a banner on
            // top of those would just repeat them less precisely.
            if (
                error instanceof HttpResponseError &&
                error.response.status === 422
            ) {
                return;
            }

            setRequestError(
                '登録できませんでした。通信状況を確認して、もう一度お試しください。',
            );
        }
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
            <DialogContent className="max-h-[90svh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>担当者を追加</DialogTitle>
                    <DialogDescription>
                        {client.name}
                        の担当者を登録します。記入中の記録はそのまま残り、登録後にこの担当者が選択されます。
                    </DialogDescription>
                </DialogHeader>
                <form
                    onSubmit={(event) => void submit(event)}
                    className="grid gap-4"
                >
                    <FormField label="氏名" required error={form.errors.name}>
                        <Input
                            autoFocus
                            required
                            maxLength={255}
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                        />
                    </FormField>
                    <FormField label="役職" error={form.errors.title}>
                        <Input
                            maxLength={255}
                            value={form.data.title}
                            onChange={(event) =>
                                form.setData('title', event.target.value)
                            }
                        />
                    </FormField>
                    <FormField label="電話番号" error={form.errors.phone}>
                        <Input
                            type="tel"
                            maxLength={50}
                            value={form.data.phone}
                            onChange={(event) =>
                                form.setData('phone', event.target.value)
                            }
                        />
                    </FormField>
                    <FormField label="メールアドレス" error={form.errors.email}>
                        <Input
                            type="email"
                            maxLength={255}
                            value={form.data.email}
                            onChange={(event) =>
                                form.setData('email', event.target.value)
                            }
                        />
                    </FormField>
                    {requestError && (
                        <p role="alert" className="text-sm text-destructive">
                            {requestError}
                        </p>
                    )}
                    <div className="flex flex-wrap justify-end gap-2">
                        <Button
                            type="button"
                            variant="ghost"
                            disabled={form.processing}
                            onClick={onClose}
                        >
                            キャンセル
                        </Button>
                        <HelpButton
                            helpTitle="登録して選択"
                            help="担当者を顧客の連絡先に保存し、この記録の担当者(客)として選びます。記録自体はまだ保存されません。"
                            type="submit"
                            disabled={form.processing}
                        >
                            {form.processing ? '登録中...' : '登録して選択'}
                        </HelpButton>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
