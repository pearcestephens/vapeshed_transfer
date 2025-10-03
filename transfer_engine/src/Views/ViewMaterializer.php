<?php
declare(strict_types=1);
namespace Unified\Views;
use Unified\Support\Config; use Unified\Support\Pdo; use Unified\Support\Logger;
/** ViewMaterializer (Phase M7)
 * Manages optional materialization of logical views into mv_* tables.
 */
final class ViewMaterializer
{
    public function __construct(private Logger $logger) {}

    /**
     * @param array $views list of logical view names (e.g., v_sales_daily)
     * @return array status entries
     */
    public function run(array $views): array
    {
        $pdo = Pdo::instance();
        $out = [];
        foreach ($views as $v) {
            $flagKey = 'neuro.unified.views.materialize.'.$v;
            $enabled = (bool)Config::get($flagKey,false);
            if (!$enabled) { $out[]=['view'=>$v,'materialized'=>false,'reason'=>'disabled']; continue; }
            $target = 'mv_'.$v;
            $pdo->exec("DROP TABLE IF EXISTS `$target`");
            // naive snapshot (future: include metadata row)
            $pdo->exec("CREATE TABLE `$target` AS SELECT * FROM `$v`");
            $out[] = ['view'=>$v,'materialized'=>true,'target'=>$target];
        }
        $this->logger->info('views.materialize',['count'=>count($out)]);
        return $out;
    }
}
