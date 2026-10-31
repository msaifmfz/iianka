<?php

declare(strict_types=1);

namespace App\Application\Quotation;

use App\Models\Quotation;
use Illuminate\Support\Facades\File;
use Illuminate\Support\HtmlString;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class QuotationPdf
{
    public function render(Quotation $quotation, string $document, bool $preview = false): string
    {
        $directory = storage_path('app/private/quotation-pdf-cache');
        File::ensureDirectoryExists($directory, 0700);
        $pdf = new Mpdf([
            'mode' => 'utf-8', 'format' => 'A4', 'margin_left' => 14, 'margin_right' => 14,
            'margin_top' => 14, 'margin_bottom' => 18, 'tempDir' => $directory,
            'fontDir' => [...(new ConfigVariables)->getDefaults()['fontDir'], resource_path('fonts')],
            'fontdata' => [
                ...(new FontVariables)->getDefaults()['fontdata'],
                'ipaexg' => ['R' => 'ipaexg.ttf'],
                'notoemoji' => ['R' => 'NotoEmoji.ttf'],
            ],
            'default_font' => 'ipaexg', 'default_font_size' => 9,
            'useSubstitutions' => true, 'backupSubsFont' => ['dejavusanscondensed', 'notoemoji'],
            'shrink_tables_to_fit' => 1,
        ]);
        $pdf->SetTitle(($document === 'quotation' ? '御見積書 ' : '内訳明細書 ').$quotation->number);
        $pdf->SetAuthor($quotation->issuer['name']);
        $pdf->SetHTMLFooter('<div style="font-size:8pt;text-align:right;color:#666">'.e($quotation->number).'　{PAGENO} / {nbpg}</div>');
        $pdf->WriteHTML(view('quotations.pdf', [
            'quote' => $quotation, 'document' => $document, 'preview' => $preview,
            'money' => self::money(...),
        ])->render());

        return $pdf->Output('', Destination::STRING_RETURN);
    }

    public static function money(string|int $value): string
    {
        [$integer, $decimal] = array_pad(explode('.', (string) $value, 2), 2, '');
        $decimal = rtrim($decimal, '0');

        return preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $integer).($decimal === '' ? '' : '.'.$decimal);
    }

    public static function text(?string $value, int $maxWordLength = 16): HtmlString
    {
        // Explicit break opportunities avoid mPDF shrinking a whole table to fit one long word.
        // Split graphemes rather than code points so combining marks and emoji stay together.
        $wrapped = preg_replace_callback('/\S+/u', function (array $match) use ($maxWordLength): string {
            preg_match_all('/\X/u', $match[0], $characters);

            return count($characters[0]) > $maxWordLength ? implode("\u{200B}", $characters[0]) : $match[0];
        }, str_replace(["\r\n", "\r"], "\n", $value ?? ''));

        return new HtmlString(nl2br(e($wrapped)));
    }

    /** @return list<string> */
    public static function noteParts(string $note): array
    {
        // mPDF cannot split a table row across pages. Bound explicit lines per row
        // so a newline-heavy note cannot force the renderer to shrink the table.
        return array_map(
            fn (array $lines): string => implode("\n", $lines),
            array_chunk(explode("\n", str_replace(["\r\n", "\r"], "\n", $note)), 12),
        );
    }
}
