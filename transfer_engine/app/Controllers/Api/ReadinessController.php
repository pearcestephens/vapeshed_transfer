<?php
declare(strict_types=1);
namespace App\Controllers\Api;
use App\Controllers\BaseController;
use mysqli;

class ReadinessController extends BaseController
{
    public function index(): void
    {
        header('Content-Type: application/json');
        try {
            $db = new mysqli(getenv('DB_HOST')?:'localhost', getenv('DB_USER')?:'root', getenv('DB_PASS')?:'', getenv('DB_NAME')?:'cis');
            if($db->connect_errno){ echo json_encode(['success'=>false,'error'=>'db_connect_failed','message'=>$db->connect_error]); return; }
            $out = $this->diagnostics($db);
            echo json_encode(['success'=>true,'data'=>$out]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success'=>false,'error'=>'exception','message'=>$e->getMessage()]);
        }
    }

    private function diagnostics(mysqli $db): array
    {
        $tables = [
            'product_candidate_matches','product_candidate_match_events','brand_synonyms','brand_synonym_candidates','feature_flags'
        ];
        $exists=[]; foreach($tables as $t){ $exists[$t]=$this->tableExists($db,$t); }
        $eventCol = $this->columnInfo($db,'product_candidate_match_events','event_type');
        $enum = $eventCol ? (stripos($eventCol['COLUMN_TYPE'],'enum(')!==false) : null;
        return [
            'tables'=>$exists,
            'event_type_is_enum'=>$enum,
            'suggest_disable'=>[
                'brand_weighting'=> !$exists['brand_synonyms'],
                'synonym_learning'=> !$exists['brand_synonym_candidates'],
                'category_analytics'=> $enum === true,
            ],
            'timestamp'=>date('c')
        ];
    }

    private function tableExists(mysqli $db,string $table): bool
    { $t=$db->real_escape_string($table); $res=$db->query("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='$t' LIMIT 1"); return (bool)($res && $res->num_rows); }
    private function columnInfo(mysqli $db,string $table,string $col): ?array
    { $t=$db->real_escape_string($table); $c=$db->real_escape_string($col); $res=$db->query("SELECT COLUMN_TYPE FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='$t' AND column_name='$c' LIMIT 1"); if($res && $r=$res->fetch_assoc()) return $r; return null; }
}
