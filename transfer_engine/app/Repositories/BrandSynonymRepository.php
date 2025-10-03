<?php
declare(strict_types=1);

namespace VapeshedTransfer\App\Repositories;

use mysqli;

class BrandSynonymRepository
{
    private array $cache = [];
    public function __construct(private mysqli $db) {}

    public function resolve(string $raw): string
    {
        $key = strtolower($raw);
        if (isset($this->cache[$key])) { return $this->cache[$key]; }
        $esc = $this->db->real_escape_string($raw);
        $res = $this->db->query("SELECT canonical FROM brand_synonyms WHERE synonym='$esc' LIMIT 1");
        if ($res && $row=$res->fetch_assoc()) { return $this->cache[$key]=$row['canonical']; }
        return $this->cache[$key]=$raw;
    }

    /** Bulk resolve array of tokens mapping synonyms to canonical. */
    public function normalizeTokens(array $tokens): array
    {
        return array_map(fn($t)=>$this->resolve($t), $tokens);
    }
}
