import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Download, Pencil } from 'lucide-react';
import {
    edit,
    index,
} from '@/actions/App/Http/Controllers/QuotationController';
import quotationPdf from '@/actions/App/Http/Controllers/QuotationPdfController';
import { Button } from '@/components/ui/button';
import { yen } from '@/lib/quotation';
import type { Quotation } from '@/types/quotation';

export default function QuotationShow({
    quotation: q,
    canManage,
}: {
    quotation: Quotation;
    canManage: boolean;
}) {
    const c = q.calculation;

    return (
        <>
            <Head title={`見積書 ${q.number}`} />
            <div className="mx-auto w-full max-w-6xl space-y-6 p-4 md:p-6 xl:p-8">
                <header className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            {q.number}
                        </p>
                        <h1 className="mt-1 text-2xl font-bold">
                            {q.project_name}
                        </h1>
                        <p className="mt-2 text-muted-foreground">
                            {q.client_name} 御中
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button asChild variant="outline">
                            <Link href={index()}>
                                <ArrowLeft className="size-4" />
                                一覧へ
                            </Link>
                        </Button>
                        {canManage && (
                            <Button asChild variant="outline">
                                <Link href={edit(q.id)}>
                                    <Pencil className="size-4" />
                                    編集
                                </Link>
                            </Button>
                        )}
                    </div>
                </header>
                <section className="flex flex-wrap items-center justify-between gap-6 rounded-xl border bg-card p-6">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            見積合計（税別）
                        </p>
                        <p className="mt-2 text-3xl font-bold tabular-nums">
                            {yen(c.total)}
                        </p>
                        {c.rounding_policy === 'line_half_up_yen' && (
                            <p className="mt-2 text-xs text-muted-foreground">
                                明細金額・諸経費は1円未満を四捨五入しています。
                            </p>
                        )}
                        {c.net !== null && (
                            <p className="mt-2 text-sm text-muted-foreground">
                                NET参考額 {yen(c.net)}（通常合計は変更しません）
                            </p>
                        )}
                    </div>
                    <div className="flex flex-wrap gap-3">
                        <Button asChild>
                            <a
                                href={quotationPdf.url({
                                    quotation: q.id,
                                    document: 'quotation',
                                })}
                            >
                                <Download className="size-4" />
                                見積書PDF
                            </a>
                        </Button>
                        <Button asChild variant="outline">
                            <a
                                href={quotationPdf.url({
                                    quotation: q.id,
                                    document: 'breakdown',
                                })}
                            >
                                <Download className="size-4" />
                                内訳書PDF
                            </a>
                        </Button>
                    </div>
                </section>
                <dl className="grid gap-4 rounded-xl border bg-card p-5 text-sm sm:grid-cols-2 lg:grid-cols-4">
                    {[
                        ['作成日', q.input.issued_on],
                        ['担当者', q.input.person_in_charge],
                        [
                            '引渡場所',
                            q.input.delivery_location || q.project_name,
                        ],
                        [
                            '表示方式',
                            c.display_mode === 'lump_sum'
                                ? '工事一式'
                                : '通常明細',
                        ],
                    ].map(([label, value]) => (
                        <div key={label}>
                            <dt className="text-xs text-muted-foreground">
                                {label}
                            </dt>
                            <dd className="mt-1 break-words">{value}</dd>
                        </div>
                    ))}
                </dl>
                <section className="overflow-hidden rounded-xl border bg-card">
                    <h2 className="border-b p-5 font-semibold">
                        内訳明細{' '}
                        <span className="text-sm font-normal text-muted-foreground">
                            {c.lines.length}件
                        </span>
                    </h2>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[700px] text-sm">
                            <thead className="bg-muted/60 text-left text-xs text-muted-foreground">
                                <tr>
                                    {[
                                        '品名・規格',
                                        '数量',
                                        '単価',
                                        '金額',
                                        '備考',
                                    ].map((label) => (
                                        <th
                                            key={label}
                                            className="p-4 font-medium"
                                        >
                                            {label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {c.lines.map((line) => (
                                    <tr
                                        key={line.position}
                                        className="border-t"
                                    >
                                        <td className="p-4">
                                            <span className="font-medium">
                                                {line.name}
                                            </span>
                                            <span className="mt-1 block text-xs text-muted-foreground">
                                                {line.specification}
                                            </span>
                                        </td>
                                        <td className="p-4 whitespace-nowrap">
                                            {Number(line.quantity)} {line.unit}
                                        </td>
                                        <td className="p-4 whitespace-nowrap tabular-nums">
                                            {yen(line.unit_price)}
                                        </td>
                                        <td className="p-4 font-medium whitespace-nowrap tabular-nums">
                                            {yen(line.amount)}
                                        </td>
                                        <td className="max-w-xs p-4 text-xs leading-relaxed text-muted-foreground">
                                            {line.note}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <dl className="ml-auto max-w-sm space-y-3 border-t p-5 text-sm">
                        {[
                            ['明細合計', c.subtotal],
                            ['少量施工加算', c.small_job_charge],
                            ['休日加算', c.holiday_charge],
                            ['交通費・諸経費', c.expenses],
                            ['合計（税別）', c.total],
                        ].map(([label, value]) => (
                            <div
                                key={label}
                                className="flex justify-between gap-4"
                            >
                                <dt>{label}</dt>
                                <dd className="font-medium tabular-nums">
                                    {yen(value)}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </section>
                {q.input.notes && (
                    <section className="rounded-xl border bg-card p-5">
                        <h2 className="mb-2 text-sm font-semibold">備考</h2>
                        <p className="text-sm whitespace-pre-wrap">
                            {q.input.notes}
                        </p>
                    </section>
                )}
            </div>
        </>
    );
}
QuotationShow.layout = { breadcrumbs: [{ title: '見積書', href: index() }] };
