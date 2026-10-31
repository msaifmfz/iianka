<?php

declare(strict_types=1);

namespace App\Application\Quotation;

class QuotationCatalog
{
    /** @return list<array<string, mixed>> */
    public function items(): array
    {
        return json_decode(file_get_contents(resource_path('data/quotation-catalog.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    /** @return array<string, array<string, mixed>> */
    public function indexed(): array
    {
        return array_column($this->items(), null, 'id');
    }
}
