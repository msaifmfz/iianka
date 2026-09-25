<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Crm\Enums\ClientPlaceKind;
use App\Domain\Crm\Enums\ClientPlaceLogAttachmentKind;
use App\Domain\Crm\Enums\ClientPlaceLogType;
use App\Domain\Crm\Enums\ClientReaction;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientDocument;
use App\Models\ClientPlaceLog;
use App\Models\ClientPlaceLogAttachment;
use App\Models\User;
use App\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use LogicException;
use RuntimeException;

class CrmDemoSeeder extends Seeder
{
    private const string DEMO_PNG_BASE64 =
        'iVBORw0KGgoAAAANSUhEUgAAAEAAAABACAIAAAAlC+aJAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAu0lEQVRo3u3Xuw2AMAxA'
        .'wSRiJsQQDJQRGIiaAZiFgoIBaBEFIh+cxDx3SCD5bCC2HfxiWg5nGg8AAAAAAJAUXcQzs++vl+O0FgTYoJP4lnoNjLeAh9TL'
        .'MlzG7IPulAOE5iRs0P4bjSunZBNUdyClkGJNYJQAAOCbuUBsptD+CsUVUnKk+8FHHFpO4Yna5c1Jfh/4zUamZCfmJAYAAAAA'
        .'AJWF3faDDgAAAAAAAAAAAAAAAAAAAAAAAABoLk5BBTLgezYoxwAAAABJRU5ErkJggg==';

    private const string DEMO_JPEG_BASE64 =
        '/9j/4AAQSkZJRgABAQEAYABgAAD//gA7Q1JFQVRPUjogZ2QtanBlZyB2MS4wICh1c2luZyBJSkcgSlBFRyB2NjIpLCBxdWFs'
        .'aXR5ID0gNDAK/9sAQwAUDg8SDw0UEhASFxUUGB4yIR4cHB49LC4kMklATEtHQEZFUFpzYlBVbVZFRmSIZW13e4GCgU5gjZeM'
        .'fZZzfoF8/9sAQwEVFxceGh47ISE7fFNGU3x8fHx8fHx8fHx8fHx8fHx8fHx8fHx8fHx8fHx8fHx8fHx8fHx8fHx8fHx8fHx8'
        .'fHx8/8AAEQgAQABAAwEiAAIRAQMRAf/EAB8AAAEFAQEBAQEBAAAAAAAAAAABAgMEBQYHCAkKC//EALUQAAIBAwMCBAMFBQQE'
        .'AAABfQECAwAEEQUSITFBBhNRYQcicRQygZGhCCNCscEVUtHwJDNicoIJChYXGBkaJSYnKCkqNDU2Nzg5OkNERUZHSElKU1RV'
        .'VldYWVpjZGVmZ2hpanN0dXZ3eHl6g4SFhoeIiYqSk5SVlpeYmZqio6Slpqeoqaqys7S1tre4ubrCw8TFxsfIycrS09TV1tfY'
        .'2drh4uPk5ebn6Onq8fLz9PX29/j5+v/EAB8BAAMBAQEBAQEBAQEAAAAAAAABAgMEBQYHCAkKC//EALURAAIBAgQEAwQHBQQE'
        .'AAECdwABAgMRBAUhMQYSQVEHYXETIjKBCBRCkaGxwQkjM1LwFWJy0QoWJDThJfEXGBkaJicoKSo1Njc4OTpDREVGR0hJSlNU'
        .'VVZXWFlaY2RlZmdoaWpzdHV2d3h5eoKDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW'
        .'19jZ2uLj5OXm5+jp6vLz9PX29/j5+v/aAAwDAQACEQMRAD8AfRRWhp2ni4XzZshM4AHG7/61cCTk7I75SUVdmfRXSGytimww'
        .'pj2GD+fWsfULH7IylCWjboT1BqpU3FXIjUUnYp0UUVBoFFFFABXSWJU2UOzGNo6DHPf9a5urlhfm0yjLujY5IHUH1q6clF6m'
        .'dSLktDfqnqxUWLbsZJG3jv8A/qzQdVtQm4MxP90Lz/hWTe3jXbglQqr0XrW05q1kZQg73ZWooormOkKKKKAJvstx/wA8Jf8A'
        .'vg0fZbj/AJ4S/wDfBrpaK39ku5z+2fY5r7Lcf88Jf++DR9luP+eEv/fBrpaKPZLuHtn2Oa+y3H/PCX/vg0fZbj/nhL/3wa6W'
        .'ij2S7h7Z9jmvstx/zwl/74NH2W4/54S/98Guloo9ku4e2fYKKKK2MQooooAKKKKACiiigD//2Q==';

    private const string DEMO_WEBP_BASE64 =
        'UklGRuIAAABXRUJQVlA4INYAAAAwBgCdASpAAEAAPrVMn0mnJKKhMAgA4BaJYwDRvYp19NB2KWVQJ4PVg3jE2yeErJfGzW2D'
        .'vHc1ebzgAPnWhe2tG6hhijFVm9vvTDY1w7n1pD7P9feUOxD7Uk4llZKlwQaTR7Y5FQA0P48jzXHoQOvHQZnpz9E19vop5ize'
        .'SygziVDC1Eldee+3uBCftTAuWGWaYUolUOn5/wUlMRlJWMwWKC6rwyHV8AqnO7/KcaieRfUnn3eWStmt41fvvyKwztp7ts9O'
        .'iHyPmoVGtCWZ7smQF57EnQAA';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing', 'staging'])) {
            throw new LogicException('CRM demo data may only be seeded in local, testing or staging environments.');
        }

        DB::transaction(function (): void {
            $authors = $this->authors();

            foreach ($this->scenarios() as $scenario) {
                $this->seedClient($scenario, $authors);
            }
        });
    }

    /**
     * @return array{primary: User, secondary: User, viewer: User}
     */
    private function authors(): array
    {
        return [
            'primary' => $this->firstOrCreateAuthor('crm-demo-primary', 'デモ営業・佐藤', 'crm-demo-primary@example.invalid', UserRole::Editor),
            'secondary' => $this->firstOrCreateAuthor('crm-demo-secondary', 'デモ営業・高橋', 'crm-demo-secondary@example.invalid', UserRole::Viewer),
            'viewer' => $this->firstOrCreateAuthor('crm-demo-viewer', 'デモ閲覧スタッフ', 'crm-demo-viewer@example.invalid', UserRole::Viewer),
        ];
    }

    private function firstOrCreateAuthor(string $loginId, string $name, string $email, UserRole $role): User
    {
        return User::query()->firstOrCreate(
            ['login_id' => $loginId],
            [
                'name' => $name,
                'email' => $email,
                'password' => 'password',
                'email_verified_at' => now(),
                'role' => $role,
                'is_hidden_from_workers' => false,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $scenario
     * @param  array{primary: User, secondary: User, viewer: User}  $authors
     */
    private function seedClient(array $scenario, array $authors): void
    {
        $client = Client::withTrashed()->firstOrCreate(
            ['name' => '【デモ】'.$scenario['name']],
            [
                'short_label' => $scenario['label'],
                'note' => $scenario['note'] === null
                    ? null
                    : '操作練習用の架空データです。実在する企業・人物・現場とは関係ありません。'.$scenario['note'],
                'created_by_user_id' => $authors['primary']->id,
            ],
        );

        // Soft-deleted demo clients are intentionally not resurrected.
        if ($client->trashed()) {
            return;
        }

        /** @var array<string, ClientContact> $contacts */
        $contacts = [];

        foreach ($scenario['contacts'] as $contactData) {
            $contacts[$contactData['key']] = $client->contacts()->firstOrCreate(
                ['name' => $contactData['name']],
                [
                    'title' => $contactData['title'],
                    'phone' => $contactData['phone'] ?? null,
                    'email' => $contactData['email'] ?? null,
                    'note' => $contactData['note'] ?? null,
                ],
            );
        }

        foreach ($scenario['places'] as $placeData) {
            $place = $client->places()->firstOrCreate(
                ['name' => $placeData['name']],
                [
                    'kind' => $placeData['kind'],
                    'address' => $placeData['address'] ?? null,
                    'lat' => $placeData['lat'],
                    'lng' => $placeData['lng'],
                    'archived_at' => $placeData['archived_days_ago'] === null
                        ? null
                        : now()->subDays($placeData['archived_days_ago']),
                    'created_by_user_id' => $authors['primary']->id,
                ],
            );

            foreach ($placeData['logs'] as $logData) {
                $log = $place->logs()->firstOrCreate(
                    ['summary' => '【デモ】'.$logData['summary']],
                    [
                        'client_contact_id' => $this->contactId($contacts, $logData['contact'] ?? null),
                        'user_id' => $logData['author'] === null ? null : $authors[$logData['author']]->id,
                        'type' => $logData['type'],
                        'reaction' => $logData['reaction'],
                        'occurred_at' => now()->subDays($logData['days_ago'])->setTime($logData['hour'] ?? 10, $logData['minute'] ?? 0),
                    ],
                );

                foreach ($logData['attachments'] ?? [] as $attachment) {
                    $this->seedAttachment($log, $authors, $attachment);
                }
            }

            $place->refreshLastLoggedAt();
        }

        foreach ($scenario['documents'] ?? [] as $document) {
            $this->seedDocument($client, $authors, $document);
        }
    }

    /**
     * @param  array<string, ClientContact>  $contacts
     */
    private function contactId(array $contacts, ?string $key): ?int
    {
        return $key === null ? null : $contacts[$key]->id;
    }

    /**
     * @param  array{primary: User, secondary: User, viewer: User}  $authors
     * @param  array{name: string, extension: string, mime_type: string, kind: ClientPlaceLogAttachmentKind, duration_seconds: int|null}  $attachment
     */
    private function seedAttachment(ClientPlaceLog $log, array $authors, array $attachment): void
    {
        $contents = match ($attachment['kind']) {
            ClientPlaceLogAttachmentKind::Image => $this->demoImage($attachment['extension']),
            ClientPlaceLogAttachmentKind::Audio => $this->demoWav(),
            ClientPlaceLogAttachmentKind::Document => $this->demoDocument($attachment['extension'], $attachment['name']),
        };
        $path = 'crm-attachments/'.$log->client_place_id.'/demo-'.$log->id.'-'.$attachment['name'].'.'.$attachment['extension'];
        $disk = Storage::disk(ClientPlaceLogAttachment::DISK);

        // Re-seeding repairs a missing file but leaves a present one alone, so
        // a photo swapped in by hand survives the next run.
        if (! $disk->exists($path) && ! $disk->put($path, $contents)) {
            throw new RuntimeException("Unable to write CRM demo attachment [{$path}].");
        }

        ClientPlaceLogAttachment::query()->firstOrCreate(
            ['path' => $path],
            [
                'client_place_log_id' => $log->id,
                'uploaded_by_user_id' => $authors['primary']->id,
                'kind' => $attachment['kind'],
                'name' => $attachment['name'],
                'disk' => ClientPlaceLogAttachment::DISK,
                'mime_type' => $attachment['mime_type'],
                'extension' => $attachment['extension'],
                'size' => strlen($contents),
                'duration_seconds' => $attachment['duration_seconds'],
            ],
        );
    }

    /**
     * @param  array{primary: User, secondary: User, viewer: User}  $authors
     * @param  array{name: string, extension: string, mime_type: string, place: string|null, issued_days_ago: int|null, author: string|null}  $document
     */
    private function seedDocument(Client $client, array $authors, array $document): void
    {
        $contents = match ($document['extension']) {
            'png', 'jpg', 'webp' => $this->demoImage($document['extension']),
            default => $this->demoDocument($document['extension'], $document['name']),
        };
        $path = 'crm-documents/'.$client->id.'/demo-'.$document['name'].'.'.$document['extension'];
        $disk = Storage::disk(ClientDocument::DISK);

        // Same repair-but-keep rule as attachments.
        if (! $disk->exists($path) && ! $disk->put($path, $contents)) {
            throw new RuntimeException("Unable to write CRM demo document [{$path}].");
        }

        $place = $document['place'] === null
            ? null
            : $client->places()->where('name', $document['place'])->sole();

        ClientDocument::query()->firstOrCreate(
            ['path' => $path],
            [
                'client_id' => $client->id,
                'client_place_id' => $place?->id,
                'uploaded_by_user_id' => $document['author'] === null ? null : $authors[$document['author']]->id,
                'name' => $document['name'],
                'issued_on' => $document['issued_days_ago'] === null ? null : now()->subDays($document['issued_days_ago'])->toDateString(),
                'disk' => ClientDocument::DISK,
                'mime_type' => $document['mime_type'],
                'extension' => $document['extension'],
                'size' => strlen($contents),
            ],
        );
    }

    /**
     * Text formats carry readable Japanese sample rows; anything else is a
     * one-page PDF that browsers can actually open.
     */
    private function demoDocument(string $extension, string $name): string
    {
        return match ($extension) {
            'csv' => "品目,数量,単位,単価\n養生シート,40,枚,1200\n仮設照明,6,台,8500\n",
            'txt' => "【デモ】{$name}\n操作練習用の架空データです。\n",
            default => $this->demoPdf(),
        };
    }

    /**
     * A minimal but well-formed PDF (valid xref offsets) with one line of
     * ASCII text, so the built-in viewer renders a page instead of an error.
     */
    private function demoPdf(): string
    {
        $stream = 'BT /F1 18 Tf 72 720 Td (CRM demo document - fictional data) Tj ET';
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n{$object}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= 'xref'."\n".'0 '.(count($objects) + 1)."\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf.'trailer'."\n".'<< /Size '.(count($objects) + 1).' /Root 1 0 R >>'."\nstartxref\n{$xref}\n%%EOF\n";
    }

    /**
     * Small but visible fixtures, so the photo grid in the history panel shows
     * something a tester can actually see and tell apart. HEIC is deliberately
     * absent: most browsers cannot render it, so a demo HEIC would only ever
     * look like a broken image.
     */
    private function demoImage(string $extension): string
    {
        return (string) base64_decode(match ($extension) {
            'jpg' => self::DEMO_JPEG_BASE64,
            'webp' => self::DEMO_WEBP_BASE64,
            default => self::DEMO_PNG_BASE64,
        }, true);
    }

    private function demoWav(): string
    {
        $sampleRate = 8000;
        $samples = str_repeat("\0", $sampleRate * 2);
        $length = strlen($samples);

        return 'RIFF'.pack('V', 36 + $length).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, $sampleRate, $sampleRate * 2, 2, 16)
            .'data'.pack('V', $length).$samples;
    }

    /**
     * These scenarios cover empty states, every enum value, several staff on
     * one place plus a deleted author (the staff filter and legend),
     * archived-only places, overlapping pins, mixed attachments, client
     * documents (more than the panel lists), and the layout extremes — a
     * three-character label, long names, a multi-paragraph entry and two
     * entries sharing a timestamp.
     * Coordinates are fictional but clustered around Kansai.
     *
     * @return list<array<string, mixed>>
     */
    private function scenarios(): array
    {
        return [
            [
                'name' => 'なにわ総合建設株式会社', 'label' => '浪', 'note' => '大阪市内の小規模改修を中心に取引。',
                'contacts' => [
                    ['key' => 'site', 'name' => '森田 健介', 'title' => '工事部 主任', 'phone' => '06-5555-0101', 'email' => 'morita@example.invalid', 'note' => '現場窓口。午後の連絡がスムーズ。'],
                    ['key' => 'sales', 'name' => '上田 真由', 'title' => '営業部 課長', 'email' => 'ueda@example.invalid'],
                ],
                'places' => [
                    [
                        'name' => '本社事務所', 'kind' => ClientPlaceKind::Office, 'address' => '大阪府大阪市北区中之島', 'lat' => 34.6915, 'lng' => 135.4915, 'archived_days_ago' => null,
                        'logs' => [
                            ['summary' => '概算見積の相談。搬入口と養生範囲を確認した。', 'type' => ClientPlaceLogType::Visit, 'reaction' => ClientReaction::Positive, 'contact' => 'sales', 'author' => 'primary', 'days_ago' => 3],
                            ['summary' => '工程変更の電話。午後搬入案で再提案することになった。', 'type' => ClientPlaceLogType::Call, 'reaction' => ClientReaction::Neutral, 'contact' => 'site', 'author' => 'secondary', 'days_ago' => 14],
                        ],
                    ],
                    [
                        'name' => '中之島オフィス内装工事', 'kind' => ClientPlaceKind::Site, 'address' => '大阪府大阪市北区中之島3丁目', 'lat' => 34.7005, 'lng' => 135.5035, 'archived_days_ago' => null,
                        'logs' => [
                            ['summary' => '着工前の現地確認。既存什器の移動範囲を記録した。', 'type' => ClientPlaceLogType::Visit, 'reaction' => ClientReaction::Neutral, 'contact' => 'site', 'author' => 'primary', 'days_ago' => 45, 'attachments' => [['name' => '現地写真', 'extension' => 'png', 'mime_type' => 'image/png', 'kind' => ClientPlaceLogAttachmentKind::Image, 'duration_seconds' => null], ['name' => '現地音声メモ', 'extension' => 'wav', 'mime_type' => 'audio/wav', 'kind' => ClientPlaceLogAttachmentKind::Audio, 'duration_seconds' => 1], ['name' => '現地調査報告書', 'extension' => 'pdf', 'mime_type' => 'application/pdf', 'kind' => ClientPlaceLogAttachmentKind::Document, 'duration_seconds' => null], ['name' => '什器移動数量表', 'extension' => 'csv', 'mime_type' => 'text/csv', 'kind' => ClientPlaceLogAttachmentKind::Document, 'duration_seconds' => null]]],
                            ['summary' => '追加工事の相談。予算確認後に回答予定。', 'type' => ClientPlaceLogType::Other, 'reaction' => null, 'contact' => null, 'author' => 'viewer', 'days_ago' => 8, 'attachments' => [['name' => '音声メモ', 'extension' => 'wav', 'mime_type' => 'audio/wav', 'kind' => ClientPlaceLogAttachmentKind::Audio, 'duration_seconds' => 1]]],
                            ['summary' => '完了確認。是正箇所はなく、引き渡し日を確定した。', 'type' => ClientPlaceLogType::Meeting, 'reaction' => ClientReaction::Positive, 'contact' => 'sales', 'author' => null, 'days_ago' => 1],
                        ],
                    ],
                    [
                        'name' => '完了済み倉庫改修', 'kind' => ClientPlaceKind::Site, 'address' => '大阪府大阪市此花区', 'lat' => 34.6750, 'lng' => 135.4400, 'archived_days_ago' => 100,
                        'logs' => [['summary' => '完了後のご挨拶。次年度の修繕計画を伺った。', 'type' => ClientPlaceLogType::Visit, 'reaction' => ClientReaction::Positive, 'contact' => 'sales', 'author' => 'primary', 'days_ago' => 120]],
                    ],
                    [
                        'name' => '旧資材置場', 'kind' => ClientPlaceKind::Site, 'address' => '大阪府大阪市港区', 'lat' => 34.6650, 'lng' => 135.4500, 'archived_days_ago' => 60, 'logs' => [],
                    ],
                ],
                // More than the panel shows, so it offers the full list; one
                // undated, one from a deleted author, one scan, one text file.
                'documents' => [
                    ['name' => '中之島内装工事 見積書', 'extension' => 'pdf', 'mime_type' => 'application/pdf', 'place' => '中之島オフィス内装工事', 'issued_days_ago' => 40, 'author' => 'primary'],
                    ['name' => '取引基本契約書', 'extension' => 'pdf', 'mime_type' => 'application/pdf', 'place' => null, 'issued_days_ago' => 400, 'author' => 'primary'],
                    ['name' => '注文書（署名済みスキャン）', 'extension' => 'jpg', 'mime_type' => 'image/jpeg', 'place' => null, 'issued_days_ago' => 30, 'author' => 'secondary'],
                    ['name' => '請求書 8月分', 'extension' => 'pdf', 'mime_type' => 'application/pdf', 'place' => null, 'issued_days_ago' => 20, 'author' => 'viewer'],
                    ['name' => '年間修繕計画メモ', 'extension' => 'txt', 'mime_type' => 'text/plain', 'place' => null, 'issued_days_ago' => null, 'author' => null],
                    ['name' => '資材数量一覧', 'extension' => 'csv', 'mime_type' => 'text/csv', 'place' => null, 'issued_days_ago' => 10, 'author' => 'primary'],
                    ['name' => '本社事務所 平面図', 'extension' => 'pdf', 'mime_type' => 'application/pdf', 'place' => '本社事務所', 'issued_days_ago' => 200, 'author' => 'primary'],
                ],
            ],
            [
                'name' => '六甲みらい設備株式会社', 'label' => '六', 'note' => '空調更新が得意。夜間搬入の案件が多い。',
                'contacts' => [
                    ['key' => 'site', 'name' => '藤原 悠介', 'title' => '設備課 主任', 'phone' => '078-555-0202'],
                    ['key' => 'sales', 'name' => '岡本 彩香', 'title' => '購買担当', 'email' => 'okamoto@example.invalid'],
                ],
                'places' => [
                    ['name' => '三宮店舗空調更新', 'kind' => ClientPlaceKind::Site, 'address' => '兵庫県神戸市中央区磯上通', 'lat' => 34.6910, 'lng' => 135.1980, 'archived_days_ago' => null, 'logs' => [['summary' => '空調更新の仕様を打合せ。夜間搬入で合意した。', 'type' => ClientPlaceLogType::Meeting, 'reaction' => ClientReaction::Positive, 'contact' => 'site', 'author' => 'secondary', 'days_ago' => 6]]],
                    ['name' => '神戸本社事務所', 'kind' => ClientPlaceKind::Office, 'address' => '兵庫県神戸市灘区', 'lat' => 34.7120, 'lng' => 135.2200, 'archived_days_ago' => null, 'logs' => []],
                    ['name' => '大阪営業所', 'kind' => ClientPlaceKind::Office, 'address' => '大阪府大阪市淀川区', 'lat' => 34.7330, 'lng' => 135.4980, 'archived_days_ago' => null, 'logs' => []],
                    ['name' => '西宮営業所', 'kind' => ClientPlaceKind::Other, 'address' => '兵庫県西宮市', 'lat' => 34.7380, 'lng' => 135.3410, 'archived_days_ago' => null, 'logs' => []],
                ],
            ],
            [
                'name' => '京洛リノベーション合同会社', 'label' => '京', 'note' => '町家の改修。近隣への騒音配慮が重要。',
                'contacts' => [['key' => 'site', 'name' => '中西 翔太', 'title' => '現場監督', 'email' => 'nakanishi@example.invalid']],
                'places' => [['name' => '西陣町家改修計画', 'kind' => ClientPlaceKind::Site, 'address' => '京都府京都市上京区今出川通', 'lat' => 35.0295, 'lng' => 135.7500, 'archived_days_ago' => null, 'logs' => [['summary' => '近隣説明の電話。工事時間を短縮してほしいとの要望。', 'type' => ClientPlaceLogType::Call, 'reaction' => ClientReaction::Negative, 'contact' => 'site', 'author' => 'primary', 'days_ago' => 95]]]],
            ],
            [
                'name' => '大和あおば工務店', 'label' => '大', 'note' => '木造住宅の修繕が中心。月末にまとめて発注。',
                'contacts' => [['key' => 'site', 'name' => '松井 直樹', 'title' => '代表', 'phone' => '0742-555-0404']],
                'places' => [['name' => '新大宮住宅外壁改修', 'kind' => ClientPlaceKind::Site, 'address' => '奈良県奈良市大宮町', 'lat' => 34.6820, 'lng' => 135.8110, 'archived_days_ago' => null, 'logs' => []]],
            ],
            [
                'name' => '堺ベイエリア工業株式会社', 'label' => '堺', 'note' => '工場保全を担当。安全書類を早めに用意する。',
                'contacts' => [],
                'places' => [['name' => '臨海工場定期修繕', 'kind' => ClientPlaceKind::Site, 'address' => '大阪府堺市堺区築港八幡町', 'lat' => 34.5910, 'lng' => 135.4550, 'archived_days_ago' => null, 'logs' => []]],
            ],
            [
                'name' => '尼崎つばさ建装株式会社', 'label' => '尼', 'note' => '前回取引から時間が経っているため再訪問を検討。',
                'contacts' => [['key' => 'site', 'name' => '吉岡 達也', 'title' => '工事部', 'email' => 'yoshioka@example.invalid']],
                'places' => [['name' => '杭瀬集合住宅共用部改修', 'kind' => ClientPlaceKind::Site, 'address' => '兵庫県尼崎市杭瀬本町', 'lat' => 34.7200, 'lng' => 135.4370, 'archived_days_ago' => 180, 'logs' => [['summary' => '過去案件の完了確認。今後の改修計画は未定。', 'type' => ClientPlaceLogType::Other, 'reaction' => null, 'contact' => 'site', 'author' => 'secondary', 'days_ago' => 180]]]],
            ],
            [
                'name' => '空地点テスト商事', 'label' => '空', 'note' => '顧客詳細から地点を追加する空状態の確認用。', 'contacts' => [['key' => 'sales', 'name' => '空地点窓口', 'title' => '営業担当']], 'places' => [],
            ],
            [
                'name' => '共同現場サンプルA', 'label' => '共A', 'note' => '別顧客と同じ座標を使うクラスタ確認用。',
                'contacts' => [['key' => 'site', 'name' => '共有現場 A担当', 'title' => '現場担当']],
                'places' => [['name' => '梅田共同現場 A', 'kind' => ClientPlaceKind::Other, 'address' => '大阪府大阪市北区梅田', 'lat' => 34.7025, 'lng' => 135.4959, 'archived_days_ago' => null, 'logs' => [['summary' => '共同現場の入場手順を確認した。', 'type' => ClientPlaceLogType::Other, 'reaction' => ClientReaction::Neutral, 'contact' => 'site', 'author' => 'viewer', 'days_ago' => 20]]]],
            ],
            [
                'name' => '共同現場サンプルB', 'label' => '共B', 'note' => '同一地点に複数顧客が存在する表示確認用。',
                'contacts' => [['key' => 'site', 'name' => '共有現場 B担当', 'title' => '購買担当']],
                'places' => [['name' => '梅田共同現場 B', 'kind' => ClientPlaceKind::Other, 'address' => null, 'lat' => 34.7025, 'lng' => 135.4959, 'archived_days_ago' => null, 'logs' => [['summary' => '別会社との工程調整。次回は合同確認する。', 'type' => ClientPlaceLogType::Meeting, 'reaction' => null, 'contact' => null, 'author' => 'primary', 'days_ago' => 2]]]],
            ],
            [
                // The label is the full three characters the column allows; the
                // note is unset.
                'name' => '長名ラベル確認テスト株式会社関西支社サンプル', 'label' => '長名社', 'note' => null,
                'contacts' => [
                    ['key' => 'site', 'name' => '長名 担当太郎', 'title' => '品質管理部 生産技術課 主任技師'],
                ],
                'places' => [
                    [
                        'name' => '此花区北港緑地第二工区 仮設事務所・資材ヤード併設現場（長い地点名の表示確認用）',
                        'kind' => ClientPlaceKind::Site, 'address' => '大阪府大阪市此花区北港緑地2丁目 第二工区 仮設ゲート横',
                        'lat' => 34.6480, 'lng' => 135.3900, 'archived_days_ago' => null,
                        'logs' => [
                            [
                                'summary' => "現地確認と近隣調整の打ち合わせ。長文・改行の表示確認用。\n\n".
                                    "【確認事項】\n・既存設備の撤去範囲は二期に分割。一期は来月第2週から。\n".
                                    "・仮設電源は既存分電盤から分岐。容量は再計算のうえ回答する。\n".
                                    "・搬入経路は北ゲートのみ。大型車は朝礼後から正午までに限定。\n\n".
                                    "【近隣対応】\n南側集合住宅から騒音の懸念あり。防音パネルの追加と、".
                                    "作業時間の短縮案を次回までに用意する。掲示物は着工2週間前に配布予定。\n\n".
                                    "【見積・工程】\n一期分の概算は今週中に提出。二期分は撤去範囲の確定後に再見積とする。".
                                    "工期は当初計画から一週間の後ろ倒しを見込むが、内装業者の着手日には影響しない見通し。\n\n".
                                    "【安全・書類】\n作業員名簿と有資格者一覧は着工前週の金曜までに提出。".
                                    "高所作業の予定日は別途連絡し、立会いを依頼する。\n\n".
                                    "【次回まで】\n数量表の確定、安全書類一式の提出、仮設計画図の差し替え。".
                                    '先方の決裁は月末締めのため、それまでに見積を確定させる必要がある。',
                                'type' => ClientPlaceLogType::Meeting, 'reaction' => ClientReaction::Neutral,
                                'contact' => 'site', 'author' => 'primary', 'days_ago' => 4, 'hour' => 9, 'minute' => 30,
                                'attachments' => [
                                    ['name' => '全景写真', 'extension' => 'png', 'mime_type' => 'image/png', 'kind' => ClientPlaceLogAttachmentKind::Image, 'duration_seconds' => null],
                                    ['name' => '搬入ゲート', 'extension' => 'jpg', 'mime_type' => 'image/jpeg', 'kind' => ClientPlaceLogAttachmentKind::Image, 'duration_seconds' => null],
                                    ['name' => '既存分電盤', 'extension' => 'webp', 'mime_type' => 'image/webp', 'kind' => ClientPlaceLogAttachmentKind::Image, 'duration_seconds' => null],
                                    // Length is unknown for audio that did not come from the recorder.
                                    ['name' => '現場メモ（長さ不明）', 'extension' => 'wav', 'mime_type' => 'audio/wav', 'kind' => ClientPlaceLogAttachmentKind::Audio, 'duration_seconds' => null],
                                ],
                            ],
                            // Two entries at the same minute: the timeline falls back to id order.
                            ['summary' => '同時刻の記録A。朝礼で搬入時間の再確認を行った。', 'type' => ClientPlaceLogType::Visit, 'reaction' => null, 'contact' => null, 'author' => 'secondary', 'days_ago' => 5, 'hour' => 8, 'minute' => 0],
                            ['summary' => '同時刻の記録B。別担当が同じ時刻に電話連絡を記録した。', 'type' => ClientPlaceLogType::Call, 'reaction' => ClientReaction::Neutral, 'contact' => 'site', 'author' => 'primary', 'days_ago' => 5, 'hour' => 8, 'minute' => 0],
                        ],
                    ],
                ],
                'documents' => [
                    ['name' => '北港緑地第二工区 仮設計画図・搬入経路図・近隣説明資料一式（長い書類名の表示確認用）', 'extension' => 'pdf', 'mime_type' => 'application/pdf', 'place' => '此花区北港緑地第二工区 仮設事務所・資材ヤード併設現場（長い地点名の表示確認用）', 'issued_days_ago' => 3, 'author' => 'primary'],
                ],
            ],
        ];
    }
}
