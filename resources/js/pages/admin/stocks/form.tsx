import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Plus, Trash2, X } from 'lucide-react';
import {
    destroy as stockDestroy,
    index as stockIndex,
    store as stockStore,
    update as stockUpdate,
} from '@/actions/App/Http/Controllers/Admin/StockController';
import FormField from '@/components/form-field';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { formatStockQuantity } from '@/lib/stock';

type ManagedStock = {
    id: number;
    sku: string | null;
    name: string;
    current_quantity: string;
    allows_fractional_quantity: boolean;
    is_active: boolean;
    aliases: { id: number; alias: string }[];
    has_history: boolean;
};

type Props = {
    managedStock: ManagedStock | null;
};

type StockForm = {
    _method: 'put' | '';
    name: string;
    sku: string;
    allows_fractional_quantity: boolean;
    is_active: boolean;
    aliases: string[];
    initial_quantity: string;
};

export default function AdminStockForm({ managedStock }: Props) {
    const { data, setData, post, processing, errors } = useForm<StockForm>({
        _method: managedStock ? 'put' : '',
        name: managedStock?.name ?? '',
        sku: managedStock?.sku ?? '',
        allows_fractional_quantity:
            managedStock?.allows_fractional_quantity ?? false,
        is_active: managedStock?.is_active ?? true,
        aliases: managedStock?.aliases.map((alias) => alias.alias) ?? [],
        initial_quantity: '',
    });
    const formErrors = errors as Record<string, string | undefined>;
    const submitLabel = managedStock ? '在庫を修正' : '在庫を追加';
    const processingLabel = managedStock
        ? '在庫を修正中...'
        : '在庫を追加中...';

    function submit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();

        post(
            managedStock ? stockUpdate.url(managedStock.id) : stockStore.url(),
        );
    }

    function setAlias(index: number, value: string) {
        setData(
            'aliases',
            data.aliases.map((alias, aliasIndex) =>
                aliasIndex === index ? value : alias,
            ),
        );
    }

    function removeAlias(index: number) {
        setData(
            'aliases',
            data.aliases.filter((_, aliasIndex) => aliasIndex !== index),
        );
    }

    return (
        <>
            <Head title={managedStock ? '在庫編集' : '在庫追加'} />
            <div className="mx-auto max-w-4xl space-y-6 px-2 py-4 sm:p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            Admin Stock Management
                        </p>
                        <h1 className="text-2xl font-bold">
                            {managedStock ? '在庫編集' : '在庫追加'}
                        </h1>
                    </div>
                    <Button asChild variant="outline">
                        <Link href={stockIndex()}>
                            <ArrowLeft className="size-4" />
                            一覧へ戻る
                        </Link>
                    </Button>
                </div>

                <form onSubmit={submit} className="grid gap-6 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>基本情報</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-5">
                            <FormField
                                label="在庫名"
                                required
                                error={errors.name}
                            >
                                <Input
                                    required
                                    value={data.name}
                                    onChange={(event) =>
                                        setData('name', event.target.value)
                                    }
                                    placeholder="例）ボンド"
                                />
                            </FormField>
                            {/* SKU input hidden until SKU workflows exist; the backend accepts and stores sku. */}
                            {/* <FormField label="SKU" error={errors.sku}> */}
                            {/*     <Input */}
                            {/*         value={data.sku} */}
                            {/*         onChange={(event) => */}
                            {/*             setData('sku', event.target.value) */}
                            {/*         } */}
                            {/*         placeholder="未設定でも可" */}
                            {/*     /> */}
                            {/* </FormField> */}
                            <div className="grid gap-2 text-sm font-medium">
                                <span>別名</span>
                                <p className="text-xs font-normal text-muted-foreground">
                                    予定の内容欄でこの在庫を指す別の呼び方を登録できます。
                                </p>
                                {data.aliases.map((alias, index) => (
                                    <div key={index} className="grid gap-1">
                                        <div className="flex gap-2">
                                            <Input
                                                value={alias}
                                                onChange={(event) =>
                                                    setAlias(
                                                        index,
                                                        event.target.value,
                                                    )
                                                }
                                                aria-label={`別名 ${index + 1}`}
                                            />
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="icon"
                                                onClick={() =>
                                                    removeAlias(index)
                                                }
                                                aria-label={`別名 ${index + 1} を削除`}
                                            >
                                                <X className="size-4" />
                                            </Button>
                                        </div>
                                        {formErrors[`aliases.${index}`] && (
                                            <p className="text-xs text-destructive">
                                                {formErrors[`aliases.${index}`]}
                                            </p>
                                        )}
                                    </div>
                                ))}
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="justify-self-start"
                                    onClick={() =>
                                        setData('aliases', [
                                            ...data.aliases,
                                            '',
                                        ])
                                    }
                                >
                                    <Plus className="size-4" />
                                    別名を追加
                                </Button>
                                {errors.aliases && (
                                    <p className="text-xs text-destructive">
                                        {errors.aliases}
                                    </p>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    <div className="space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>設定</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <label className="flex items-start gap-3 rounded-lg border p-4 text-sm transition dark:border-neutral-800">
                                    <input
                                        type="checkbox"
                                        checked={
                                            data.allows_fractional_quantity
                                        }
                                        onChange={(event) =>
                                            setData(
                                                'allows_fractional_quantity',
                                                event.target.checked,
                                            )
                                        }
                                        className="mt-1"
                                    />
                                    <span>
                                        <span className="block font-semibold">
                                            小数の数量を許可
                                        </span>
                                        <span className="mt-1 block text-muted-foreground">
                                            0.5
                                            など小数点以下3桁までの数量を扱えます。
                                        </span>
                                    </span>
                                </label>
                                {managedStock && (
                                    <label className="flex items-start gap-3 rounded-lg border p-4 text-sm transition dark:border-neutral-800">
                                        <input
                                            type="checkbox"
                                            checked={data.is_active}
                                            onChange={(event) =>
                                                setData(
                                                    'is_active',
                                                    event.target.checked,
                                                )
                                            }
                                            className="mt-1"
                                        />
                                        <span>
                                            <span className="block font-semibold">
                                                有効
                                            </span>
                                            <span className="mt-1 block text-muted-foreground">
                                                無効にすると予定への新規使用や仕入の追加ができなくなります。
                                            </span>
                                        </span>
                                    </label>
                                )}
                                {managedStock && (
                                    <div className="flex items-center justify-between rounded-lg border p-4 text-sm dark:border-neutral-800">
                                        <span className="text-muted-foreground">
                                            現在庫数
                                        </span>
                                        <Badge variant="secondary">
                                            {formatStockQuantity(
                                                managedStock.current_quantity,
                                            )}
                                        </Badge>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {!managedStock && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>初期数量</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <FormField
                                        label="現在の在庫数"
                                        error={errors.initial_quantity}
                                    >
                                        <Input
                                            value={data.initial_quantity}
                                            onChange={(event) =>
                                                setData(
                                                    'initial_quantity',
                                                    event.target.value,
                                                )
                                            }
                                            inputMode="decimal"
                                            placeholder="例）10"
                                        />
                                    </FormField>
                                    <p className="mt-2 text-xs text-muted-foreground">
                                        現在の月度の仕入として記録されます。
                                    </p>
                                </CardContent>
                            </Card>
                        )}

                        <Card>
                            <CardContent className="space-y-3 p-4">
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="w-full"
                                >
                                    {processing ? processingLabel : submitLabel}
                                </Button>
                                <Button
                                    asChild
                                    variant="outline"
                                    className="w-full"
                                >
                                    <Link href={stockIndex()}>キャンセル</Link>
                                </Button>
                            </CardContent>
                        </Card>

                        {managedStock && (
                            <Card>
                                <CardContent className="space-y-3 p-4">
                                    {managedStock.has_history ? (
                                        <Button
                                            type="button"
                                            variant="destructive"
                                            className="w-full"
                                            disabled
                                        >
                                            <Trash2 className="size-4" />
                                            在庫を削除
                                        </Button>
                                    ) : (
                                        <Button
                                            asChild
                                            variant="destructive"
                                            className="w-full"
                                        >
                                            <Link
                                                href={stockDestroy(
                                                    managedStock.id,
                                                )}
                                                method="delete"
                                                as="button"
                                                onBefore={() =>
                                                    confirm(
                                                        `${managedStock.name} を削除しますか？`,
                                                    )
                                                }
                                            >
                                                <Trash2 className="size-4" />
                                                在庫を削除
                                            </Link>
                                        </Button>
                                    )}
                                    {managedStock.has_history && (
                                        <p className="text-xs text-muted-foreground">
                                            仕入・使用などの履歴があるため削除できません。使用を止める場合は「有効」を外して保存してください。
                                        </p>
                                    )}
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </form>
            </div>
        </>
    );
}

AdminStockForm.layout = {
    breadcrumbs: [
        {
            title: '在庫管理',
            href: stockIndex(),
        },
    ],
};
