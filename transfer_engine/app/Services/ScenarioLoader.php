<?php
declare(strict_types=1);

namespace App\Services;

/**
 * ScenarioLoader
 * - Loads scenario definitions from JSON files
 * - Normalizes to engine format (outlets with outlet_id, products with outlet_id keyed maps)
 * - Allows filtering by outlet codes and product tags
 */
class ScenarioLoader
{
    /**
     * Load and decode a JSON scenario file.
     * Expected structure:
     * {
     *   "meta": { ... },
     *   "outlets": [ { "code":"GLE", "name":"Glenfield", "turnover_rate":5.1 }, ... ],
     *   "products": [
     *      {
     *        "product_id":"P-NEW-01",
     *        "tags":["new","launch"],
     *        "warehouse_stock":36,
     *        "outlet_stocks": { "GLE":0, "BB":0, ... },
     *        "sales_velocity": { "GLE":2, "BB":2, ... }
     *      }, ...
     *   ]
     * }
     *
     * @param string $path Absolute filesystem path.
     * @return array{outlets: array<int, array<string,mixed>>, products: array<int, array<string,mixed>>, meta?: array}
     */
    public function loadFromFile(string $path): array
    {
        if (!is_file($path)) {
            throw new \RuntimeException("Scenario file not found: {$path}");
        }
        $json = file_get_contents($path);
        if ($json === false) {
            throw new \RuntimeException("Unable to read scenario file: {$path}");
        }
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new \RuntimeException("Invalid scenario JSON: {$path}");
        }
        $outlets = isset($data['outlets']) && is_array($data['outlets']) ? $data['outlets'] : [];
        $products = isset($data['products']) && is_array($data['products']) ? $data['products'] : [];
        $meta = isset($data['meta']) && is_array($data['meta']) ? $data['meta'] : [];
        return ['outlets' => $outlets, 'products' => $products, 'meta' => $meta];
    }

    /**
     * Map decoded scenario arrays to engine-ready arrays.
     *
     * @param array $scenario Output of loadFromFile
     * @param array<string> $onlyCodes Optional list of store codes to include
     * @param array<string> $onlyTags Optional list of product tags to include
     * @param int|null $limit Optional product limit (after tag filtering)
     * @return array{outlets: array<int, array<string,mixed>>, products: array<int, array<string,mixed>>}
     */
    public function mapToEngine(array $scenario, array $onlyCodes = [], array $onlyTags = [], ?int $limit = null): array
    {
        $outlets = [];
        $codeToId = [];
        foreach ($scenario['outlets'] as $o) {
            $code = (string)($o['code'] ?? '');
            if ($code === '') { continue; }
            if (!empty($onlyCodes) && !in_array($code, $onlyCodes, true)) { continue; }
            $outlet = [
                'outlet_id' => 'O-' . $code,
                'name' => (string)($o['name'] ?? $code),
                'store_code' => $code,
                'turnover_rate' => (float)($o['turnover_rate'] ?? 4.0),
            ];
            $outlets[] = $outlet;
            $codeToId[$code] = $outlet['outlet_id'];
        }

        // If onlyCodes specified but none matched, fall back to all outlets
        if (!empty($onlyCodes) && empty($outlets)) {
            foreach ($scenario['outlets'] as $o) {
                $code = (string)($o['code'] ?? '');
                if ($code === '') { continue; }
                $outlet = [
                    'outlet_id' => 'O-' . $code,
                    'name' => (string)($o['name'] ?? $code),
                    'store_code' => $code,
                    'turnover_rate' => (float)($o['turnover_rate'] ?? 4.0),
                ];
                $outlets[] = $outlet;
                $codeToId[$code] = $outlet['outlet_id'];
            }
        }

        $products = [];
        foreach ($scenario['products'] as $p) {
            $tags = array_values(array_filter(array_map('strval', (array)($p['tags'] ?? []))));
            if (!empty($onlyTags)) {
                $intersect = array_intersect($onlyTags, $tags);
                if (count($intersect) === 0) { continue; }
            }
            $pid = (string)($p['product_id'] ?? '');
            if ($pid === '') { continue; }
            $wh = (int)($p['warehouse_stock'] ?? 0);
            $stockByCode = (array)($p['outlet_stocks'] ?? []);
            $velByCode = (array)($p['sales_velocity'] ?? []);

            // Map code-keyed maps to outlet_id keyed maps; include only outlets we have
            $stocks = [];
            $vel = [];
            foreach ($codeToId as $code => $oid) {
                if (array_key_exists($code, $stockByCode)) {
                    $stocks[$oid] = (int)$stockByCode[$code];
                } else {
                    $stocks[$oid] = 0;
                }
                if (array_key_exists($code, $velByCode)) {
                    $vel[$oid] = (float)$velByCode[$code];
                } else {
                    $vel[$oid] = 0.0;
                }
            }

            $products[] = [
                'product_id' => $pid,
                'warehouse_stock' => $wh,
                'outlet_stocks' => $stocks,
                'sales_velocity' => $vel,
                'turnover_rate' => (array)($p['turnover_rate'] ?? []),
                'tags' => $tags,
            ];
            if ($limit !== null && count($products) >= $limit) { break; }
        }

        return ['outlets' => $outlets, 'products' => $products];
    }
}
