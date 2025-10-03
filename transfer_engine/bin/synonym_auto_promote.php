<?php
declare(strict_types=1);
// synonym_auto_promote.php - promote high occurrence candidate tokens automatically based on thresholds.
require_once __DIR__.'/_cli_bootstrap.php';
$mysqli = cli_db();
if($mysqli->connect_errno){ fwrite(STDERR,"DB connect failed: {$mysqli->connect_error}\n"); exit(1);} 
$occThreshold = (int)($argv[1] ?? 8); // minimum occurrences
$maxPromote = (int)($argv[2] ?? 10);
// Add safety: require token appearing across >= distinct_sku_threshold accepted matches
$distinctSkuThreshold = (int)($argv[3] ?? 2);
$sql = "SELECT c.token, c.occurrences, COUNT(DISTINCT m.sku_id) sku_spread
        FROM brand_synonym_candidates c
        LEFT JOIN product_candidate_matches m ON m.candidate_id = c.sample_candidate_ref AND m.status='accepted'
        WHERE c.flagged=0 AND c.occurrences >= $occThreshold
        GROUP BY c.token,c.occurrences
        HAVING sku_spread >= $distinctSkuThreshold
        ORDER BY c.occurrences DESC LIMIT $maxPromote";
$res = $mysqli->query($sql);
$promoted=[]; while($res && $row=$res->fetch_assoc()){
    $token = $row['token'];
    $tEsc = $mysqli->real_escape_string($token);
    $mysqli->query("INSERT IGNORE INTO brand_synonyms(canonical,synonym) VALUES('$tEsc','$tEsc')");
    $mysqli->query("UPDATE brand_synonym_candidates SET flagged=1 WHERE token='$tEsc'");
    $occ=(int)$row['occurrences']; $spread=(int)$row['sku_spread']; $by='auto';
    $mysqli->query("INSERT INTO brand_synonym_promotion_audit(token,canonical,occurrences,sku_spread,promoted_by) VALUES('$tEsc','$tEsc',$occ,$spread,'$by')");
    $promoted[]=['token'=>$token,'occurrences'=>$row['occurrences'],'sku_spread'=>$row['sku_spread']];
}
echo json_encode(['promoted'=>$promoted,'count'=>count($promoted),'occ_threshold'=>$occThreshold,'distinct_sku_threshold'=>$distinctSkuThreshold], JSON_PRETTY_PRINT)."\n";

