<?php
declare(strict_types=1);
use VapeshedTransfer\App\Repositories\BrandSynonymCandidateRepository; 
use VapeshedTransfer\App\Repositories\BrandSynonymRepository; 
require_once __DIR__.'/_cli_bootstrap.php';
$mysqli = cli_db();
if($mysqli->connect_errno){ fwrite(STDERR,"DB connect failed: {$mysqli->connect_error}\n"); exit(1);} 
$candRepo = new BrandSynonymCandidateRepository($mysqli);
$synRepo = new BrandSynonymRepository($mysqli);
$cmd = $argv[1] ?? 'top';
switch($cmd){
    case 'top':
        $limit = (int)($argv[2] ?? 20);
        $res = $mysqli->query("SELECT token, occurrences, sample_candidate_ref FROM brand_synonym_candidates WHERE flagged=0 ORDER BY occurrences DESC LIMIT $limit");
        while($res && $row=$res->fetch_assoc()){
            echo $row['token'].'|occ='.$row['occurrences'].'|sample='.$row['sample_candidate_ref']."\n";
        }
        break;
    case 'promote':
        $token = $argv[2] ?? null; $canonical = $argv[3] ?? null;
        if(!$token||!$canonical){ fwrite(STDERR,"Usage: promote <token> <canonical>\n"); exit(1);} 
        $tEsc = $mysqli->real_escape_string($token); $cEsc=$mysqli->real_escape_string($canonical);
        $mysqli->query("INSERT IGNORE INTO brand_synonyms(canonical,synonym) VALUES('$cEsc','$tEsc')");
        $mysqli->query("UPDATE brand_synonym_candidates SET flagged=1 WHERE token='$tEsc'");
        echo "Promoted $token -> $canonical\n";
        break;
    case 'flag':
        $token = $argv[2] ?? null; if(!$token){ fwrite(STDERR,"Usage: flag <token>\n"); exit(1);} 
        $tEsc=$mysqli->real_escape_string($token); $mysqli->query("UPDATE brand_synonym_candidates SET flagged=1 WHERE token='$tEsc'"); echo "Flagged $token\n"; break;
    default:
        fwrite(STDERR,"Commands: top [limit] | promote <token> <canonical> | flag <token>\n"); exit(1);
}
exit(0);
