<?php

return [
    'store_domain' => env('SHOPIFY_STORE_DOMAIN'),
    'access_token' => env('SHOPIFY_ACCESS_TOKEN'),
    'api_version' => env('SHOPIFY_API_VERSION', '2025-01'),
    'graphql_url' => env('SHOPIFY_GRAPHQL_URL'),
    'location_id' => env('SHOPIFY_LOCATION_ID'),
    'collection_id' => env('SHOPIFY_COLLECTION_ID'),
    'collection_gid' => env('SHOPIFY_COLLECTION_ID')
        ? 'gid://shopify/Collection/'.env('SHOPIFY_COLLECTION_ID')
        : null,
];
