import { Search } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { quotationSelectClass, yen } from '@/lib/quotation';
import type { CatalogItem } from '@/types/quotation';

const normalize = (value: string) =>
    value.normalize('NFKC').toLowerCase().replace(/\s+/g, '');

export default function CatalogPicker({
    catalog,
    onSelect,
    label = '品目を選択',
}: {
    catalog: CatalogItem[];
    onSelect: (item: CatalogItem) => void;
    label?: string;
}) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');
    const [category, setCategory] = useState('all');
    const [family, setFamily] = useState('all');
    const families = [
        ...new Set(
            catalog
                .filter(
                    (item) => category === 'all' || item.category === category,
                )
                .map((item) => item.name),
        ),
    ];
    const words = search.trim().split(/\s+/).filter(Boolean).map(normalize);
    const matches = catalog.filter(
        (item) =>
            (category === 'all' || item.category === category) &&
            (family === 'all' || item.name === family) &&
            words.every((word) =>
                normalize(
                    `${item.id} ${item.name} ${item.specification} ${item.note}`,
                ).includes(word),
            ),
    );

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button type="button" variant="outline">
                    <Search className="size-4" />
                    {label}
                </Button>
            </DialogTrigger>
            <DialogContent className="flex max-h-[90dvh] flex-col sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>単価マスターから選択</DialogTitle>
                    <DialogDescription>
                        品名・径・深さ・条件で検索できます。単価は税別です。
                    </DialogDescription>
                </DialogHeader>
                <Input
                    aria-label="品目を検索"
                    placeholder="例：差筋 D10、ダイヤコア 150、天井 デッキ"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                />
                <div className="grid grid-cols-2 gap-3">
                    <select
                        aria-label="品目の分類"
                        className={quotationSelectClass}
                        value={category}
                        onChange={(e) => {
                            setCategory(e.target.value);
                            setFamily('all');
                        }}
                    >
                        <option value="all">すべての分類</option>
                        {['アンカー', 'コア', '共通'].map((value) => (
                            <option key={value}>{value}</option>
                        ))}
                    </select>
                    <select
                        aria-label="工事項目で絞り込み"
                        className={quotationSelectClass}
                        value={family}
                        onChange={(e) => setFamily(e.target.value)}
                    >
                        <option value="all">すべての工事項目</option>
                        {families.map((value) => (
                            <option key={value}>{value}</option>
                        ))}
                    </select>
                </div>
                <p className="text-xs text-muted-foreground" role="status">
                    {matches.length}件
                </p>
                <div className="min-h-0 overflow-y-auto rounded-lg border">
                    {matches.map((item) => (
                        <button
                            key={item.id}
                            type="button"
                            className="flex w-full items-start justify-between gap-4 border-b p-4 text-left last:border-0 hover:bg-accent focus-visible:bg-accent focus-visible:outline-none"
                            onClick={() => {
                                onSelect(item);
                                setOpen(false);
                            }}
                        >
                            <span className="min-w-0 space-y-1">
                                <span className="block text-xs text-muted-foreground">
                                    {item.category} · {item.id}
                                </span>
                                <span className="block font-medium">
                                    {item.name}{' '}
                                    <span className="font-normal">
                                        {item.specification}
                                    </span>
                                </span>
                                <span className="block text-sm text-muted-foreground">
                                    {item.note || '標準仕様'}
                                </span>
                            </span>
                            <span className="shrink-0 text-right font-semibold tabular-nums">
                                {yen(item.price)}
                                <span className="block text-xs font-normal text-muted-foreground">
                                    ／{item.unit}
                                </span>
                            </span>
                        </button>
                    ))}
                    {matches.length === 0 && (
                        <p className="p-10 text-center text-sm text-muted-foreground">
                            該当する品目がありません。検索条件を変えるか、自由入力をご利用ください。
                        </p>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
