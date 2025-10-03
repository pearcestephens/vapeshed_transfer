<?php
declare(strict_types=1);
namespace VapeshedTransfer\App\Repositories;
use mysqli;

class MatchThresholdRepository
{
    private array $cache = [];
    public function __construct(private mysqli $db) {}

    public function get(string $scope='global'): array
    {
        if(isset($this->cache[$scope])) return $this->cache[$scope];
        $s=$this->db->real_escape_string($scope);
        $res=$this->db->query("SELECT primary_threshold, secondary_threshold, vision_threshold, min_token_base FROM product_match_thresholds WHERE scope='$s' LIMIT 1");
        if($res && $row=$res->fetch_assoc()){
            return $this->cache[$scope]=[
                'primary'=>(float)$row['primary_threshold'],
                'secondary'=>(float)$row['secondary_threshold'],
                'vision'=>(float)$row['vision_threshold'],
                'min_token_base'=>(float)$row['min_token_base'],
            ];
        }
        // Fallback defaults
        return $this->cache[$scope]=[
            'primary'=>0.78,
            'secondary'=>0.72,
            'vision'=>0.70,
            'min_token_base'=>0.45,
        ];
    }

    public function set(string $scope, float $primary, float $secondary, float $vision, float $minToken): bool
    {
        $s=$this->db->real_escape_string($scope);
        $p=$primary; $se=$secondary; $v=$vision; $mt=$minToken;
        $sql="INSERT INTO product_match_thresholds(scope,primary_threshold,secondary_threshold,vision_threshold,min_token_base) VALUES('$s',$p,$se,$v,$mt) ON DUPLICATE KEY UPDATE primary_threshold=VALUES(primary_threshold), secondary_threshold=VALUES(secondary_threshold), vision_threshold=VALUES(vision_threshold), min_token_base=VALUES(min_token_base)";
        $ok=(bool)$this->db->query($sql);
        if($ok) unset($this->cache[$scope]);
        return $ok;
    }
}
