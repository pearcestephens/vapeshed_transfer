<?php
declare(strict_types=1);

// seed_brand_synonyms.php
// Idempotently populate brand_synonyms table with canonical -> synonym mappings.

use VapeshedTransfer\Core\Logger;

require_once __DIR__.'/_cli_bootstrap.php';

$logger = new Logger('seed_brand_synonyms');
$mysqli = cli_db();
if ($mysqli->connect_errno) { fwrite(STDERR, "DB connect failed: {$mysqli->connect_error}\n"); exit(1); }

// Canonical brand => array of synonyms / misspellings / spaced variants
$brands = [
    'SMOK' => ['smok','smoktech','smok tech','smok-tech','sm0k'],
    'Vaporesso' => ['vaporesso','vaporeso','vapo resso','vaporesso'],
    'VOOPOO' => ['voopoo','voopo','vooppo','voo poo','voo-poo'],
    'Geekvape' => ['geekvape','geek vape','geek-vape','gk vape','geekvap'],
    'Aspire' => ['aspire','a-spire'],
    'Uwell' => ['uwell','u well','u-well','uwel'],
    'Innokin' => ['innokin','innokintech','innokin tech','inn0kin'],
    'Freemax' => ['freemax','free max','free-max','freemaxx'],
    'HorizonTech' => ['horizontech','horizon tech','horizon-tech','horizontec'],
    'OXVA' => ['oxva','ox va','ox-va'],
    'Lost Vape' => ['lostvape','lost vape','lost-vape','lostvap'],
    'Elf Bar' => ['elfbar','elf bar','elf-bar','elfbarr'],
    'Caliburn' => ['caliburn','cali burn','cali-burn'], // product line but often used like brand in titles
];

$inserted = 0; $skipped = 0; $errors=0;
foreach ($brands as $canonical => $syns) {
    foreach ($syns as $syn) {
        $canonicalNorm = trim($canonical);
        $synNorm = strtolower(trim($syn));
        if ($synNorm === '') { continue; }
        $cEsc = $mysqli->real_escape_string($canonicalNorm);
        $sEsc = $mysqli->real_escape_string($synNorm);
        // Check existing
        $check = $mysqli->query("SELECT synonym_id FROM brand_synonyms WHERE synonym='$sEsc' LIMIT 1");
        if ($check && $check->num_rows) { $skipped++; continue; }
        $stmt = $mysqli->prepare("INSERT INTO brand_synonyms (canonical,synonym,weight) VALUES (?,?,1.0)");
        if (!$stmt) { $errors++; $logger->warning('Prepare failed',['synonym'=>$synNorm,'error'=>$mysqli->error]); continue; }
        $stmt->bind_param('ss',$cEsc,$sEsc);
        if ($stmt->execute()) { $inserted++; } else { $errors++; }
        $stmt->close();
    }
}

$logger->info('Brand synonyms seeding complete',[ 'inserted'=>$inserted, 'skipped_existing'=>$skipped, 'errors'=>$errors ]);
echo json_encode(['inserted'=>$inserted,'skipped'=>$skipped,'errors'=>$errors], JSON_PRETTY_PRINT)."\n";
