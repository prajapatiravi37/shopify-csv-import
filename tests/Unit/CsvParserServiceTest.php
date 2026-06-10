<?php

namespace Tests\Unit;

use App\Services\CsvParserService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CsvParserServiceTest extends TestCase
{
    #[Test]
    public function it_parses_valid_csv_rows(): void
    {
        $path = base_path('samples/shopify-products-sample.csv');

        $rows = app(CsvParserService::class)->parse($path);

        $this->assertNotEmpty($rows);
        $this->assertSame('modern-desk-lamp', $rows[0]['data']['Handle']);
        $this->assertSame('Modern Desk Lamp', $rows[0]['data']['Title']);
    }
}
