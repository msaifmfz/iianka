<!DOCTYPE html>
<html lang="ja">
<head><meta charset="UTF-8"><style>
body { font-family: ipaexg; color: #202b34; font-size: 9pt; line-height: 1.6; }
h1 { text-align: center; font-size: 23pt; font-weight: normal; letter-spacing: 5pt; margin: 0 0 5mm; }
h2 { font-size: 11pt; margin: 5mm 0 3mm; }
p { margin: 0 0 2mm; }
table { width: 100%; border-collapse: collapse; table-layout: fixed; }
.meta td { vertical-align: top; padding: 2mm 0; }
.right { text-align: right; }
.recipient { font-size: 14pt; }
.muted { color: #596772; font-size: 8pt; }
.company { font-size: 12pt; }
.amount { background: #eef3f6; padding: 4mm; margin: 5mm 0; font-size: 18pt; }
.amount span { font-size: 10pt; }
.lines th { background: #e9eef2; font-size: 8pt; font-weight: normal; border: 0.2mm solid #b8c3cb; padding: 2.5mm 1.5mm; }
.lines td { border: 0.2mm solid #cbd3d9; padding: 2.5mm 1.5mm; vertical-align: top; }
.lines tr { page-break-inside: avoid; }
.note { font-size: 7.5pt; }
.totals { margin-top: 4mm; page-break-inside: avoid; }
.totals td { padding: 1.5mm 2mm; border-bottom: 0.2mm solid #d5dde3; }
.total td { background: #eef3f6; font-size: 12pt; }
.conditions { margin-top: 5mm; font-size: 8pt; }
</style></head>
<body>
@php
    $input = $quote->input;
    $calculation = $quote->calculation;
    $text = \App\Application\Quotation\QuotationPdf::text(...);
    $isCover = $document === 'quotation';
    $lump = $isCover && $calculation['display_mode'] === 'lump_sum';
    $lines = $lump ? [[
        'name' => $input['category'].'一式', 'specification' => '', 'quantity' => '1', 'unit' => '式',
        'unit_price' => $calculation['total'], 'amount' => $calculation['total'], 'note' => '内訳明細書参照',
    ]] : $calculation['lines'];
    $pages = $isCover ? [$lines] : array_chunk($lines, 12);
@endphp
@foreach($pages as $page)
    @if(!$loop->first)<pagebreak />@endif
    <h1>{{ $isCover ? '御見積書' : '内訳明細書' }}</h1>
    @if($preview ?? false)<p class="muted">プレビュー（未発行・入力内容は保存されません）</p>@endif
    <p class="right muted">見積番号：{{ $quote->number }}　　作成日：{{ $quote->issued_on->format('Y年m月d日') }}</p>
    <table class="meta"><tr>
        <td style="width:55%"><p class="recipient">{{ $text($input['client_name']) }} 御中</p><p>工事名：{{ $text($input['project_name']) }}</p><p>引渡場所：{{ $text(($input['delivery_location'] ?? '') ?: $input['project_name']) }}</p>
        @if($isCover)<p class="muted">下記の通り御見積り申し上げます。<br>何卒ご用命の程御願い申し上げます。</p>@endif</td>
        <td style="width:45%" class="right"><p class="company">{{ $text($quote->issuer['name']) }}</p><p class="muted">{{ $text($quote->issuer['address']) }}<br>TEL {{ $text($quote->issuer['telephone']) }}　FAX {{ $text($quote->issuer['fax']) }}<br>登録番号 {{ $text($quote->issuer['registration_number']) }}<br>担当：{{ $text($input['person_in_charge']) }}</p></td>
    </tr></table>
    @if($isCover)
        <div class="amount"><span>御見積金額　</span>￥{{ $money($calculation['total']) }} <span>（税別）</span></div>
        <p class="muted">見積期限：各材料の価格値上の通達により報告　　支払条件：現金100％</p>
    @endif
    <h2>{{ $input['category'] }}</h2>
    <table class="lines"><thead><tr>
        <th style="width:22%">品名</th><th style="width:19%">規格・摘要</th><th style="width:7%">数量</th><th style="width:5%">単位</th><th style="width:13%">単価</th><th style="width:14%">金額</th><th style="width:20%">備考</th>
    </tr></thead><tbody>
    @foreach($page as $line)
        @foreach(\App\Application\Quotation\QuotationPdf::noteParts($line['note']) as $note)
            <tr>
                @if($loop->first)
                    <td>{{ $text($line['name'], 8) }}</td><td>{{ $text($line['specification'], 6) }}</td><td class="right">{{ $text($money($line['quantity']), 5) }}</td><td>{{ $text($line['unit'], 1) }}</td><td class="right">{{ $text($money($line['unit_price']), 12) }}</td><td class="right">{{ $text($money($line['amount']), 12) }}</td>
                @else
                    <td colspan="6" class="muted">備考（続き）</td>
                @endif
                <td class="note">{{ $text($note, 12) }}</td>
            </tr>
        @endforeach
    @endforeach
    </tbody></table>
    @if($loop->last)
        <table class="totals">
            @if(!$lump)
                <tr><td style="width:70%">明細合計</td><td class="right">￥{{ $money($calculation['subtotal']) }}</td></tr>
                @foreach(['small_job_charge' => '少量施工加算', 'holiday_charge' => '休日加算', 'expenses' => '交通費・諸経費'] as $key => $label)
                    @if((float) $calculation[$key] != 0)<tr><td>{{ $label }}</td><td class="right">￥{{ $money($calculation[$key]) }}</td></tr>@endif
                @endforeach
            @endif
            <tr class="total"><td>合計（税別・法定福利費含）</td><td class="right">￥{{ $money($calculation['total']) }}</td></tr>
            @if($calculation['net'] !== null)<tr><td>NET参考額（税別）</td><td class="right">￥{{ $money($calculation['net']) }}</td></tr>@endif
        </table>
        @if(($calculation['rounding_policy'] ?? null) === 'line_half_up_yen')<p class="muted right">明細金額・諸経費は1円未満を四捨五入しています。</p>@endif
        @if($calculation['net'] !== null)<p class="muted right">NETは参考表示です。通常合計・内訳金額は変更していません。</p>@endif
        @if($input['notes'] ?? '')<h2>備考</h2><p>{{ $text($input['notes']) }}</p>@endif
        @if($isCover)
            <div class="conditions">
                <p>・電気、墨出し、水源（水道）、ガラ処理、のろ処理、仮設足場、廃材処分は先方支給。</p>
                <p>・過度に鉄筋が干渉した場合は別途料金が発生します。</p>
                <p>・少量施工は選択時のみ、施工費が最低施工費￥{{ $money($input['minimum_charge']) }}に満たない差額を加算します。材料納品代は判定に含めず、材料納品のみの場合は対象外です。</p>
                <p>・休日料金は選択時のみ、通常料金に￥20,000を加算します。長期休暇は別途相談。交通費・諸経費は別途計上。</p>
            </div>
        @endif
    @endif
@endforeach
</body></html>
