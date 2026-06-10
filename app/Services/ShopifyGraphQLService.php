<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ShopifyGraphQLService
{
    public function isConfigured(): bool
    {
        return filled(config('shopify.store_domain'))
            && filled(config('shopify.access_token'));
    }

    public function findProductByHandle(string $handle): ?array
    {
        $query = <<<'GRAPHQL'
        query productByHandle($handle: String!) {
            productByHandle(handle: $handle) {
                id
                handle
                title
                variants(first: 1) {
                    nodes {
                        id
                        sku
                    }
                }
            }
        }
        GRAPHQL;

        $response = $this->execute($query, ['handle' => $handle]);

        return $response['data']['productByHandle'] ?? null;
    }

    public function importOrUpdate(array $row): array
    {
        $handle = $row['Handle'];
        $existing = $this->findProductByHandle($handle);
        $input = $this->mapCsvRowToProductSetInput($row);

        if ($existing) {
            $input['id'] = $existing['id'];
        }

        $mutation = <<<'GRAPHQL'
        mutation productSet($input: ProductSetInput!) {
            productSet(synchronous: true, input: $input) {
                product {
                    id
                    handle
                    title
                    variants(first: 1) {
                        nodes {
                            id
                            sku
                        }
                    }
                }
                userErrors {
                    field
                    message
                }
            }
        }
        GRAPHQL;

        $response = $this->execute($mutation, ['input' => $input]);
        $payload = $response['data']['productSet'] ?? null;

        $this->assertNoUserErrors($payload['userErrors'] ?? [], 'productSet');

        $product = $payload['product'];
        $this->addProductToCollection($product['id']);

        return [
            'product' => $product,
            'was_updated' => (bool) $existing,
        ];
    }

    public function addProductToCollection(string $productId): void
    {
        $collectionGid = config('shopify.collection_gid');

        if (! $collectionGid) {
            return;
        }

        $mutation = <<<'GRAPHQL'
        mutation collectionAddProducts($id: ID!, $productIds: [ID!]!) {
            collectionAddProducts(id: $id, productIds: $productIds) {
                collection {
                    id
                    title
                }
                userErrors {
                    field
                    message
                }
            }
        }
        GRAPHQL;

        $response = $this->execute($mutation, [
            'id' => $collectionGid,
            'productIds' => [$productId],
        ]);

        $payload = $response['data']['collectionAddProducts'] ?? null;
        $this->assertNoUserErrors($payload['userErrors'] ?? [], 'collectionAddProducts');
    }

    public function mapCsvRowToProductSetInput(array $row): array
    {
        $tags = collect(explode(',', $row['Tags'] ?? ''))
            ->map(fn ($tag) => trim($tag))
            ->filter()
            ->values()
            ->all();

        $published = $this->toBool($row['Published'] ?? 'TRUE');

        $variant = array_filter([
            'optionValues' => [
                ['optionName' => 'Title', 'name' => 'Default Title'],
            ],
            'sku' => $row['Variant SKU'] ?? null,
            'price' => isset($row['Variant Price']) ? (string) $row['Variant Price'] : null,
            'compareAtPrice' => filled($row['Variant Compare At Price'] ?? null)
                ? (string) $row['Variant Compare At Price']
                : null,
            'taxable' => $this->toBool($row['Variant Taxable'] ?? 'TRUE'),
            'inventoryPolicy' => strtoupper($row['Variant Inventory Policy'] ?? 'DENY'),
        ], fn ($value) => $value !== null && $value !== '');

        if (filled($row['Variant Weight'] ?? null)) {
            $variant['inventoryItem'] = [
                'measurement' => [
                    'weight' => [
                        'value' => (float) $row['Variant Weight'],
                        'unit' => $this->mapWeightUnit($row['Variant Weight Unit'] ?? 'KILOGRAMS'),
                    ],
                ],
            ];
        }

        $input = [
            'title' => $row['Title'],
            'handle' => $row['Handle'],
            'descriptionHtml' => $row['Body HTML'] ?? '',
            'vendor' => $row['Vendor'] ?? null,
            'productType' => $row['Product Type'] ?? null,
            'tags' => $tags,
            'status' => $published ? 'ACTIVE' : 'DRAFT',
            'productOptions' => [
                ['name' => 'Title', 'values' => [['name' => 'Default Title']]],
            ],
            'variants' => [$variant],
        ];

        if (filled($row['Image Src'] ?? null)) {
            $input['files'] = [[
                'alt' => $row['Image Alt Text'] ?? $row['Title'],
                'contentType' => 'IMAGE',
                'originalSource' => $row['Image Src'],
            ]];
        }

        if (
            filled(config('shopify.location_id'))
            && filled($row['Variant Inventory Qty'] ?? null)
            && ($row['Variant Inventory Tracker'] ?? '') === 'shopify'
        ) {
            $input['variants'][0]['inventoryQuantities'] = [[
                'locationId' => config('shopify.location_id'),
                'quantity' => (int) $row['Variant Inventory Qty'],
                'name' => 'available',
            ]];
        }

        return array_filter($input, fn ($value) => $value !== null && $value !== '');
    }

    private function execute(string $query, array $variables = []): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException(
                'Shopify credentials are not configured. Set SHOPIFY_STORE_DOMAIN and SHOPIFY_ACCESS_TOKEN in .env'
            );
        }

        $url = config('shopify.graphql_url') ?: sprintf(
            'https://%s/admin/api/%s/graphql.json',
            config('shopify.store_domain'),
            config('shopify.api_version')
        );

        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => config('shopify.access_token'),
                'Content-Type' => 'application/json',
            ])
                ->timeout(30)
                ->post($url, [
                    'query' => $query,
                    'variables' => $variables,
                ])
                ->throw();
        } catch (RequestException $exception) {
            throw new RuntimeException(
                'Shopify GraphQL request failed: '.$exception->getMessage(),
                previous: $exception
            );
        }

        $body = $response->json();

        if (! is_array($body)) {
            throw new RuntimeException('Invalid GraphQL response from Shopify.');
        }

        if (! empty($body['errors'])) {
            $messages = collect($body['errors'])
                ->pluck('message')
                ->filter()
                ->implode('; ');

            throw new RuntimeException('Shopify GraphQL errors: '.$messages);
        }

        return $body;
    }

    private function assertNoUserErrors(array $userErrors, string $operation): void
    {
        if (empty($userErrors)) {
            return;
        }

        $messages = collect($userErrors)
            ->map(fn ($error) => ($error['field'][0] ?? 'field').': '.$error['message'])
            ->implode('; ');

        throw new RuntimeException("Shopify {$operation} user errors: {$messages}");
    }

    private function toBool(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'y'], true);
    }

    private function mapWeightUnit(string $unit): string
    {
        return match (strtolower(trim($unit))) {
            'kg', 'kilograms' => 'KILOGRAMS',
            'g', 'grams' => 'GRAMS',
            'lb', 'lbs', 'pounds' => 'POUNDS',
            'oz', 'ounces' => 'OUNCES',
            default => strtoupper($unit),
        };
    }
}
