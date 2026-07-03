<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ReceptionDocumentType;
use Illuminate\Database\Seeder;

class ReceptionDocumentTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (['見積依頼', '図面確認', '現調依頼', 'その他'] as $index => $name) {
            ReceptionDocumentType::query()->updateOrCreate(
                ['name' => $name],
                [
                    'sort_order' => ($index + 1) * 10,
                    'is_active' => true,
                ],
            );
        }
    }
}
