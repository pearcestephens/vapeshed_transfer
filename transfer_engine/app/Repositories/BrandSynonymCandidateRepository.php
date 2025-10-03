<?php
declare(strict_types=1);

namespace VapeshedTransfer\App\Repositories;

use mysqli; use RuntimeException;

class BrandSynonymCandidateRepository
{
    public function __construct(private mysqli $db) {}

    public function recordToken(string $token, ?string $sampleCandidateId=null): void
    {
        $token = strtolower(trim($token)); if ($token==='') return;
        $esc = $this->db->real_escape_string($token);
        $sample = $sampleCandidateId ? $this->db->real_escape_string($sampleCandidateId) : null;
        $res = $this->db->query("SELECT candidate_id FROM brand_synonym_candidates WHERE token='$esc' LIMIT 1");
        if ($res && $row=$res->fetch_assoc()) {
            $this->db->query("UPDATE brand_synonym_candidates SET occurrences=occurrences+1, last_seen_at=NOW() WHERE candidate_id=".(int)$row['candidate_id']);
            return;
        }
        $stmt = $this->db->prepare("INSERT INTO brand_synonym_candidates (token, occurrences, sample_candidate_ref) VALUES (?,1,?)");
        if(!$stmt){ throw new RuntimeException('Prepare failed: '.$this->db->error); }
        $stmt->bind_param('ss',$esc,$sample); $stmt->execute(); $stmt->close();
    }

    public function topUnflagged(int $limit=50, int $minOccurrences=3): array
    {
        $res = $this->db->query("SELECT * FROM brand_synonym_candidates WHERE flagged=0 AND occurrences >= " . (int)$minOccurrences . " ORDER BY occurrences DESC LIMIT $limit");
        return $res? $res->fetch_all(MYSQLI_ASSOC):[];
    }

    public function flag(int $id): bool
    { return (bool)$this->db->query("UPDATE brand_synonym_candidates SET flagged=1 WHERE candidate_id=".(int)$id); }

    public function promote(string $token, string $canonical): bool
    {
        $escT = $this->db->real_escape_string(strtolower($token));
        $escC = $this->db->real_escape_string($canonical);
        $check = $this->db->query("SELECT synonym_id FROM brand_synonyms WHERE synonym='$escT' LIMIT 1");
        if ($check && $check->num_rows) { return true; }
        $stmt=$this->db->prepare("INSERT INTO brand_synonyms (canonical,synonym,weight) VALUES (?,?,1.0)");
        if(!$stmt){ return false; }
        $stmt->bind_param('ss',$escC,$escT); $stmt->execute(); $ok=$stmt->affected_rows>0; $stmt->close(); return $ok;
    }
}
