<?php
declare(strict_types=1);

namespace VapeshedTransfer\App\Services\Vision;

/**
 * ImageHashService
 * Lightweight perceptual hash utilities (placeholder math using GD) for similarity prefiltering.
 * NOTE: Relies on GD extension; production should validate availability.
 */
class ImageHashService
{
    public function computeAll(string $binary): array
    {
        $im = @imagecreatefromstring($binary);
        if (!$im) { return ['p_hash'=>null,'d_hash'=>null,'a_hash'=>null,'dominant_color'=>null]; }
        $p = $this->pHash($im); $d=$this->dHash($im); $a=$this->aHash($im); $dom=$this->dominantColor($im);
        imagedestroy($im);
        return ['p_hash'=>$p,'d_hash'=>$d,'a_hash'=>$a,'dominant_color'=>$dom];
    }

    private function pHash($im): ?string
    {
        $small = imagescale($im,32,32,IMG_BICUBIC_FIXED); if(!$small){ return null; }
        // Convert to grayscale matrix
        $vals=[]; for($y=0;$y<32;$y++){ for($x=0;$x<32;$x++){ $rgb=imagecolorat($small,$x,$y); $r=($rgb>>16)&0xff; $g=($rgb>>8)&0xff; $b=$rgb&0xff; $vals[]=(int)(0.299*$r+0.587*$g+0.114*$b); } }
        imagedestroy($small);
        $avg = array_sum($vals)/count($vals);
        $bits=''; foreach($vals as $v){ $bits.=($v>$avg)?'1':'0'; }
        return hash('sha256',$bits); // compress bits via sha256
    }

    private function dHash($im): ?string
    {
        $w=9;$h=8; $small=imagescale($im,$w,$h,IMG_BICUBIC_FIXED); if(!$small){ return null; }
        $bits='';
        for($y=0;$y<$h;$y++){
            $prev=null; for($x=0;$x<$w;$x++){ $rgb=imagecolorat($small,$x,$y); $r=($rgb>>16)&0xff; $g=($rgb>>8)&0xff; $b=$rgb&0xff; $gray=(int)(($r+$g+$b)/3); if($prev!==null){ $bits.= ($prev<$gray)?'1':'0'; } $prev=$gray; }
        }
        imagedestroy($small);
        return substr(hash('sha256',$bits),0,32);
    }

    private function aHash($im): ?string
    {
        $small=imagescale($im,8,8,IMG_BICUBIC_FIXED); if(!$small){ return null; }
        $vals=[]; for($y=0;$y<8;$y++){ for($x=0;$x<8;$x++){ $rgb=imagecolorat($small,$x,$y); $r=($rgb>>16)&0xff; $g=($rgb>>8)&0xff; $b=$rgb&0xff; $vals[]=(int)(($r+$g+$b)/3); } }
        imagedestroy($small); $avg=array_sum($vals)/count($vals); $bits=''; foreach($vals as $v){ $bits.=($v>=$avg)?'1':'0'; }
        return substr(hash('sha256',$bits),0,32);
    }

    private function dominantColor($im): ?string
    {
        $sx=imagesx($im); $sy=imagesy($im); $sample=10; $acc=[0,0,0]; $count=0;
        for($i=0;$i<$sample;$i++){ $x=random_int(0,$sx-1); $y=random_int(0,$sy-1); $rgb=imagecolorat($im,$x,$y); $r=($rgb>>16)&0xff; $g=($rgb>>8)&0xff; $b=$rgb&0xff; $acc[0]+=$r; $acc[1]+=$g; $acc[2]+=$b; $count++; }
        if($count===0){ return null; }
        $r=(int)($acc[0]/$count); $g=(int)($acc[1]/$count); $b=(int)($acc[2]/$count);
        return sprintf('#%02X%02X%02X',$r,$g,$b);
    }
}
