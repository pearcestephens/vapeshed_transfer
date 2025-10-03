<?php
declare(strict_types=1);

namespace VapeshedTransfer\App\Services\Extraction;

class StructuredExtractor
{
    public function extractAll(string $html): array
    {
        $jsonLd = $this->extractJsonLd($html);
        $micro = $this->extractMicrodata($html);
        $og = $this->extractOpenGraph($html);
        $heur = $this->heuristicFields($html);
        $platform = $this->detectPlatform($html);
        $shopify = $platform === 'shopify' ? $this->extractShopifyProduct($html) : [];
        $woo = $platform === 'woocommerce' ? $this->extractWooCommerceProduct($html) : [];
        // Stock badge detection (generic textual scan)
        $stockDetected = $this->detectStockStatus($html);
        $images = $this->extractImages($html, $og, $jsonLd, $shopify, $woo);

        $primary = $this->choosePrimary($jsonLd, $micro, $og, $heur, $shopify, $woo);
        $normalized = $this->normalizeProduct($primary, $jsonLd, $micro, $og, $heur, $shopify, $woo, $platform, $stockDetected);

        return [
            'primary' => $primary,
            'normalized' => $normalized,
            'json_ld' => $jsonLd,
            'microdata' => $micro,
            'open_graph' => $og,
            'heuristic' => $heur,
            'platform' => $platform,
            'shopify' => $shopify,
            'woocommerce' => $woo,
            'stock_detected' => $stockDetected,
            'images' => $images
        ];
    }

    private function extractJsonLd(string $html): array
    {
        $out = [];
        if (preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $m)) {
            foreach ($m[1] as $blob) {
                $blob = html_entity_decode($blob);
                $json = json_decode($blob, true);
                if (is_array($json)) {
                    // Flatten @graph entries
                    if (isset($json['@graph']) && is_array($json['@graph'])) {
                        foreach ($json['@graph'] as $g) { if (is_array($g)) { $out[] = $g; } }
                    } else { $out[] = $json; }
                }
            }
        }
        return $out;
    }

    private function extractMicrodata(string $html): array
    {
        $items = [];
        if (preg_match_all('/<([a-z0-9]+)[^>]*itemscope[^>]*>(.*?)<\/\1>/is', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $block) {
                $segment = $block[2];
                $props = [];
                if (preg_match_all('/itemprop=["\']([^"\']+)["\'][^>]*content=["\']([^"\']+)["\']/i', $segment, $pm, PREG_SET_ORDER)) {
                    foreach ($pm as $p) { $props[$p[1]] = $p[2]; }
                }
                // Also capture inline <meta itemprop="..." content="...">
                if (preg_match_all('/<meta[^>]+itemprop=["\']([^"\']+)["\'][^>]+content=["\']([^"\']+)["\']/i', $segment, $mm, PREG_SET_ORDER)) {
                    foreach ($mm as $p) { $props[$p[1]] = $p[2]; }
                }
                if ($props) { $items[] = $props; }
            }
        }
        return $items;
    }

    private function extractOpenGraph(string $html): array
    {
        $og = [];
        if (preg_match_all('/<meta[^>]+property=["\']og:([^"\']+)["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m, PREG_SET_ORDER)) {
            foreach ($m as $tag) { $og[$tag[1]] = $tag[2]; }
        }
        return $og;
    }

    private function heuristicFields(string $html): array
    {
        $title = null;
        if (preg_match('/<title>(.*?)<\/title>/si', $html, $m)) { $title = trim(html_entity_decode($m[1])); }
        preg_match_all('/([$€£])\s?(\d{1,4}(?:[\.,]\d{2})?)/', $html, $pm, PREG_SET_ORDER);
        $prices=[]; foreach ($pm as $p) { $prices[] = ['currency'=>$p[1],'value'=>str_replace(',','.',$p[2])]; }
        return [ 'title'=>$title, 'prices'=>$prices ];
    }

    private function choosePrimary(array $jsonLd, array $micro, array $og, array $heur, array $shopify, array $woo): array
    {
        // Prefer explicit platform structured objects if available
        if (!empty($shopify['product'])) { return $shopify['product']; }
        if (!empty($woo['product'])) { return $woo['product']; }
        foreach ($jsonLd as $entry) {
            if (!is_array($entry)) { continue; }
            $type = $entry['@type'] ?? null;
            if ($type && ((is_string($type) && strtolower($type)==='product') || (is_array($type) && in_array('Product', $type, true)))) {
                return $entry;
            }
        }
        foreach ($micro as $m) { if (isset($m['price']) || isset($m['name'])) { return $m; } }
        if (!empty($og['title'])) { return ['name'=>$og['title'],'price'=>$og['price']??null,'image'=>$og['image']??null]; }
        return ['name'=>$heur['title'] ?? null, 'price'=>$heur['prices'][0]['value']??null];
    }

    private function normalizeProduct(array $primary, array $jsonLd, array $micro, array $og, array $heur, array $shopify, array $woo, ?string $platform, ?bool $stockDetected): array
    {
        $name = $primary['name'] ?? $primary['title'] ?? null;
        // Brand resolution: JSON-LD brand can be object or string or nested graph
        $brand = null;
        if (isset($primary['brand'])) {
            $brand = is_array($primary['brand']) ? ($primary['brand']['name'] ?? reset($primary['brand'])) : $primary['brand'];
        }
        if (!$brand) {
            foreach ($jsonLd as $entry) {
                if (isset($entry['@type']) && (in_array('Brand',(array)$entry['@type'],true) || $entry['@type']==='Brand')) {
                    $brand = $entry['name'] ?? null; if ($brand) break;
                }
            }
        }
        if (!$brand && $name) {
            if (preg_match('/^(\w{3,15})\b/i',$name,$bm)) { $brand=$bm[1]; }
        }

        // Offer / price selection
        $price = $primary['offers']['price'] ?? $primary['price'] ?? null;
        $currency = $primary['offers']['priceCurrency'] ?? ($primary['currency'] ?? null);
        $variants = [];
        $onSale = false; $available = null; $optionNames=[]; $coilOhms=[];
        if ($platform === 'shopify' && !empty($shopify['variants'])) {
            if (!empty($shopify['options']) && is_array($shopify['options'])) {
                foreach ($shopify['options'] as $opt) { if (is_array($opt) && isset($opt['name'])) { $optionNames[] = $opt['name']; } }
            }
            foreach ($shopify['variants'] as $v) {
                $vPrice = isset($v['price'])? (float)$v['price']:null;
                $vCompare = isset($v['compare_at_price'])? (float)$v['compare_at_price']:null;
                $vAvail = $v['available'] ?? ($v['inventory_quantity'] ?? 0) > 0;
                if ($vPrice !== null && $vCompare !== null && $vCompare > $vPrice) { $onSale = true; }
                if ($available !== true && $vAvail) { $available = true; }
                // Coil ohm detection from variant title / option values
                if (!empty($v['title'])) { $coilOhms = array_merge($coilOhms, $this->detectCoilOhms($v['title'])); }
                $variants[] = [
                    'sku' => $v['sku'] ?? null,
                    'price' => $vPrice,
                    'compare_at_price' => $vCompare,
                    'title' => $v['title'] ?? null,
                    'available' => $vAvail,
                    'options' => $v['options'] ?? null
                ];
            }
            if ($price === null && $variants) {
                $validPrices = array_values(array_filter(array_map(fn($vx)=>$vx['price'],$variants), fn($p)=>$p!==null));
                if ($validPrices) { $price = min($validPrices); }
            }
            if ($available !== true) { $available = false; }
        }
        if ($platform === 'woocommerce' && !empty($woo['variants'])) {
            foreach ($woo['variants'] as $v) {
                $variants[] = [ 'sku'=>$v['sku']??null, 'price'=>isset($v['price'])?(float)$v['price']:null, 'title'=>$v['title']??null ];
            }
            if ($price === null && $variants) {
                $val = array_filter(array_map(fn($vx)=>$vx['price'],$variants));
                if ($val) { $price = min($val); }
            }
        }
        if (!$price && $heur['prices']) { $price = $heur['prices'][0]['value']; $currency = $currency ?? $heur['prices'][0]['currency']; }
        if ($price !== null) { $price = (float)str_replace(',','.', (string)$price); }
        if ($available === null && $stockDetected !== null) { $available = $stockDetected; }
        if ($available === null) { $available = $price !== null; } // weak heuristic

        // Nicotine / volume / pack heuristics
        $nicotine = null; $volume = null; $pack=null; $variant=null;
        if ($name) {
            if (preg_match('/(\d{1,3})\s?mg/i', $name, $m)) { $nicotine = (float)$m[1]; }
            if (preg_match('/(\d{1,3})\s?ml/i', $name, $m)) { $volume = (float)$m[1]; }
            if (preg_match('/pack\s?of\s?(\d{1,2})/i', $name, $m)) { $pack = (int)$m[1]; }
            $variant = $name;
            if ($brand) { $variant = preg_replace('/'.preg_quote($brand,'/').'/i','',$variant); }
            $variant = trim(preg_replace('/\b(\d+mg|\d+ml|pack of \d+)\b/i','',$variant));
            $coilOhms = array_merge($coilOhms, $this->detectCoilOhms($name));
        }
        $coilOhms = array_values(array_unique(array_map(fn($v)=>(string)$v, $coilOhms)));

        $structureHash = hash('sha256', json_encode([$name,$brand,$price,$currency,$nicotine,$volume,$pack,$variant]));
        return [
            'name'=>$name,
            'brand'=>$brand,
            'variant'=>$variant ?: null,
            'price'=>$price,
            'currency'=>$currency,
            'nicotine_mg'=>$nicotine,
            'volume_ml'=>$volume,
            'pack_count'=>$pack,
            'structure_hash'=>$structureHash,
            'platform'=>$platform,
            'variants'=>$variants,
            'available'=>$available,
            'on_sale'=>$onSale,
            'option_names'=>$optionNames,
            'coil_ohms'=>$coilOhms,
            'primary_coil_ohm'=> $coilOhms ? (float)$coilOhms[0] : null
        ];
    }

    /**
     * Extract candidate image URLs from multiple hints (OpenGraph, JSON-LD, platform blobs, generic <img> tags).
     * Returns array with keys primary (string|null) and gallery (array<string>).
     */
    private function extractImages(string $html, array $og, array $jsonLd, array $shopify, array $woo): array
    {
        $primary = null; $gallery = [];
        if (!empty($og['image'])) { $primary = $og['image']; }
        // JSON-LD product image array or string
        foreach ($jsonLd as $entry) {
            if (!is_array($entry)) { continue; }
            $type = $entry['@type'] ?? null;
            if ($type && ( (is_string($type) && strtolower($type)==='product') || (is_array($type) && in_array('Product',$type,true)) )) {
                if (isset($entry['image'])) {
                    if (is_array($entry['image'])) { foreach ($entry['image'] as $im) { if (is_string($im)) { $gallery[] = $im; } } }
                    elseif (is_string($entry['image'])) { $gallery[] = $entry['image']; }
                }
            }
        }
        if ($shopify && isset($shopify['product']['images']) && is_array($shopify['product']['images'])) {
            foreach ($shopify['product']['images'] as $img) {
                if (is_array($img) && isset($img['src'])) { $gallery[] = $img['src']; }
            }
        }
        // Basic <img> tag heuristic (limit to first 8 to avoid noise)
        if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i',$html,$mImg)) {
            foreach (array_slice($mImg[1],0,8) as $src) { if (preg_match('/\.(png|jpe?g|webp)$/i',$src)) { $gallery[]=$src; } }
        }
        $gallery = array_values(array_unique(array_filter($gallery, fn($u)=>is_string($u)&&strlen($u)<1024)));
        if (!$primary && $gallery) { $primary = $gallery[0]; }
        return ['primary'=>$primary,'gallery'=>$gallery];
    }

    private function detectPlatform(string $html): ?string
    {
        if (stripos($html,'cdn.shopify.com') !== false || stripos($html,'Shopify.theme') !== false || preg_match('/var\s+meta\s*=\s*\{[^;]*shopName/i',$html)) { return 'shopify'; }
        if (stripos($html,'woocommerce') !== false || stripos($html,'wp-content/plugins/woocommerce') !== false) { return 'woocommerce'; }
        return null;
    }

    private function extractShopifyProduct(string $html): array
    {
        $out = ['product'=>null,'variants'=>[], 'options'=>[]];
        // Embedded JSON product: <script type="application/json" id="ProductJson-...">...</script>
        if (preg_match('/<script[^>]+id=["\']ProductJson[^>]*>(.*?)<\/script>/is', $html, $m)) {
            $json = json_decode($m[1], true); if (is_array($json)) { $out['product']=$json; $out['variants']=$json['variants']??[]; }
        }
        // Fallback: var meta = { product: {...}
        if (!$out['product'] && preg_match('/var\s+meta\s*=\s*\{[^;]*product"?\s*:\s*(\{.*?\})\s*,\s*"?shopName/si', $html, $m2)) {
            $blob = $this->balancedBraces($m2[1]);
            $json = json_decode($blob, true); if (is_array($json)) { $out['product']=$json; $out['variants']=$json['variants']??[]; }
        }
        if ($out['product'] && isset($out['product']['options'])) { $out['options'] = $out['product']['options']; }
        // Analytics meta: window.ShopifyAnalytics = { ... } search for product id
        return $out;
    }

    private function extractWooCommerceProduct(string $html): array
    {
        $out=['product'=>null,'variants'=>[]];
        // Look for data-product_id attributes with price meta
        if (preg_match('/<div[^>]+class=["\'][^"']*price[^"']*["\'][^>]*>(.*?)<\/div>/is',$html,$pm)) {
            if (preg_match('/([£€$])\s?(\d{1,4}(?:[\.,]\d{2})?)/',$pm[1],$pMatch)) {
                $out['product']=['price'=>$pMatch[2],'currency'=>$pMatch[1],'name'=>null];
            }
        }
        // Attempt to grab product title h1.entry-title
        if (preg_match('/<h1[^>]*class=["\'][^"']*entry-title[^"']*["\'][^>]*>(.*?)<\/h1>/is',$html,$tm)) {
            $title=strip_tags($tm[1]); if(!isset($out['product']['name'])){ $out['product']['name']=$title; }
        }
        return $out;
    }

    private function detectStockStatus(string $html): ?bool
    {
        $lower = strtolower($html);
        $posPatterns = ['in stock','available now','ready to ship'];
        $negPatterns = ['out of stock','sold out','unavailable'];
        foreach ($negPatterns as $p) { if (str_contains($lower,$p)) { return false; } }
        foreach ($posPatterns as $p) { if (str_contains($lower,$p)) { return true; } }
        return null;
    }

    private function detectCoilOhms(string $text): array
    {
        $out=[];
        if (preg_match_all('/(0\.[0-9]{2,3}|[0-9]\.[0-9]{2})\s?(ohm|Ω)/i', $text, $m)) {
            foreach ($m[1] as $val) { $out[] = (float)$val; }
        }
        return $out;
    }

    private function balancedBraces(string $jsonFragment): string
    {
        // Ensure truncated capture gets balanced braces; simple counter approach
        $depth=0; $out='';
        $len=strlen($jsonFragment);
        for($i=0;$i<$len;$i++){
            $ch=$jsonFragment[$i];
            if($ch==='{' ){ $depth++; }
            if($ch==='}' ){ $depth--; }
            $out.=$ch;
            if($depth===0){ break; }
        }
        return $out;
    }
}
