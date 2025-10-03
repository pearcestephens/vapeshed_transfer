<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\Logger;

/**
 * AutoTuneApprovalController
 * UI workflow to review auto-generated SQL patch suggestions before applying.
 */
class AutoTuneApprovalController extends BaseController
{
    private string $patchDir;

    public function __construct()
    {
        parent::__construct();
        $this->patchDir = __DIR__ . '/../../var/tmp';
        if (!is_dir($this->patchDir)) {
            @mkdir($this->patchDir, 0775, true);
        }
    }

    public function index(): void
    {
        $this->requireAdmin('autotune_review');
        $patches = $this->listPatches();
        $this->render('autotune/index', [
            'title' => 'Auto-Tune Threshold Suggestions',
            'patches' => $patches
        ]);
    }

    public function apply(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo 'Method Not Allowed'; return; }
        $this->requireAdmin('autotune_apply');
        $this->validateCsrfToken();
        $file = $_POST['file'] ?? '';
        if (!preg_match('/^auto_tune_patch_\\d+\\.sql$/', $file)) {
            http_response_code(400); echo 'Invalid file'; return; }
        $full = $this->patchDir . '/' . $file;
        if (!is_file($full)) { http_response_code(404); echo 'Not found'; return; }
        $sql = file_get_contents($full);
        try {
            $db = \App\Core\Database::getInstance()->getConnection();
            $db->begin_transaction();
            foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
                if ($stmt === '') continue;
                $db->query($stmt);
            }
            $db->commit();
            $this->logger->info('Applied auto-tune patch '.$file);
            header('Location: /autotune?applied=1');
        } catch (\Throwable $e) {
            if (isset($db) && $db->errno === 0) { $db->rollback(); }
            $this->logger->error('Auto-tune apply failed '.$e->getMessage());
            http_response_code(500); echo 'Apply failed';
        }
    }

    private function listPatches(): array
    {
        $out = [];
        if (!is_dir($this->patchDir)) return $out;
        foreach (glob($this->patchDir.'/auto_tune_patch_*.sql') as $f) {
            $out[] = [
                'file' => basename($f),
                'size' => filesize($f),
                'mtime' => date('c', filemtime($f)),
                'preview' => htmlspecialchars(implode("\n", array_slice(file($f),0,20)))
            ];
        }
        usort($out, fn($a,$b)=>strcmp($b['file'],$a['file']));
        return $out;
    }
}
