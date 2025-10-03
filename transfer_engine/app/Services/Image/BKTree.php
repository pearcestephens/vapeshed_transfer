<?php
declare(strict_types=1);
namespace VapeshedTransfer\App\Services\Image;

/** Simple BK-Tree for perceptual hash (hex) Hamming distance nearest grouping */
class BKTree
{
    private ?array $root = null; // ['hash'=>string,'children'=>[distance=>node]]
    public function add(string $hash): void
    {
        if($this->root===null){ $this->root=['hash'=>$hash,'children'=>[]]; return; }
        $node =& $this->root; $distFn=fn($a,$b)=> self::hammingHex($a,$b) ?? 9999; $h=$hash;
        while(true){
            $d = $distFn($h,$node['hash']);
            if($d===0) return; // already present
            if(!isset($node['children'][$d])){ $node['children'][$d]=['hash'=>$h,'children'=>[]]; return; }
            $node =& $node['children'][$d];
        }
    }
    public function radiusSearch(string $hash, int $radius): array
    {
        if($this->root===null) return [];
        $results=[]; $stack=[ $this->root ];
        while($stack){ $n=array_pop($stack); $d=self::hammingHex($hash,$n['hash']); if($d!==null && $d <= $radius){ $results[]=$n['hash']; }
            foreach($n['children'] as $dist=>$child){ if($dist >= $d-$radius && $dist <= $d+$radius){ $stack[]=$child; } }
        }
        return $results;
    }
    public static function hammingHex(string $h1,string $h2): ?int
    { if(strlen($h1)!==strlen($h2)) return null; $b1=hex2bin($h1); $b2=hex2bin($h2); if($b1===false||$b2===false)return null; $len=strlen($b1); $dist=0; for($i=0;$i<$len;$i++){ $x=ord($b1[$i])^ord($b2[$i]); $dist+=substr_count(decbin($x),'1'); } return $dist; }
}
