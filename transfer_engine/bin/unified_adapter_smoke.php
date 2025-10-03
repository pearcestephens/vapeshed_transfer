<?php
declare(strict_types=1);
/** unified_adapter_smoke.php (Phase M2)
 * Verifies Support layer + BalancerAdapter can be instantiated without affecting legacy scripts.
 * Usage: php bin/unified_adapter_smoke.php
 */
require_once __DIR__.'/_cli_bootstrap.php';

// Temporary autoload for Support namespace (simple) until unified composer/autoload introduced.
$supportDir = __DIR__.'/../src/Support';
foreach(['Env','Config','Util','Logger','Idem','Http','Validator','Pdo','BalancerAdapter'] as $cls){
    $path = $supportDir.'/'.$cls.'.php';
    if(is_file($path) && !class_exists("Unified\\Support\\$cls")) require_once $path; else if(!is_file($path)) fwrite(STDERR, "[adapter_smoke] Missing $path\n");
}

use Unified\Support\Logger; use Unified\Support\BalancerAdapter;

$logger = new Logger('adapter_smoke');
$adapter = new BalancerAdapter($logger);
$res = $adapter->simulatePlan();

// Load Guardrail classes
$guardDir = __DIR__.'/../src/Guardrail';
foreach(['GuardrailInterface','AbstractGuardrail','CostFloorGuardrail','DeltaCapGuardrail','RoiViabilityGuardrail','DonorFloorGuardrail','ReceiverOvershootGuardrail','GuardrailChain'] as $g){
  $gp = $guardDir.'/'.$g.'.php'; if(is_file($gp)) require_once $gp; }

use Unified\Guardrail\GuardrailChain; use Unified\Guardrail\CostFloorGuardrail; use Unified\Guardrail\DeltaCapGuardrail; use Unified\Guardrail\RoiViabilityGuardrail; use Unified\Guardrail\DonorFloorGuardrail; use Unified\Guardrail\ReceiverOvershootGuardrail;

$chain = new GuardrailChain($logger);
$chain->register(new CostFloorGuardrail());
$chain->register(new DeltaCapGuardrail());
$chain->register(new RoiViabilityGuardrail());
$chain->register(new DonorFloorGuardrail());
$chain->register(new ReceiverOvershootGuardrail());

$ctx = [ 'cost'=>5.00,'current_price'=>12.00,'candidate_price'=>12.50,'min_margin_pct'=>0.22,
         'projected_roi'=>1.5,'donor_dsr_post'=>7,'receiver_dsr_post'=>15 ];
$gr = $chain->evaluate($ctx);

// Load Scoring and Insights
$scoreDir = __DIR__.'/../src/Scoring';
$insightDir = __DIR__.'/../src/Insights';
foreach(['ScoringEngine'] as $s){ $sp=$scoreDir.'/'.$s.'.php'; if(is_file($sp)) require_once $sp; }
foreach(['InsightEmitter'] as $ie){ $ip=$insightDir.'/'.$ie.'.php'; if(is_file($ip)) require_once $ip; }

use Unified\Scoring\ScoringEngine; use Unified\Insights\InsightEmitter;

$scoring = new ScoringEngine($logger);
$scoreRes = $scoring->score([
  'margin_uplift'=>0.4,
  'competitor_alignment'=>0.2,
  'risk_penalty'=>-0.1,
  'inventory_balance'=>0.15
]);
$emitter = new InsightEmitter($logger);
$emitter->emit('pattern','ScoringEngine smoke evaluation', ['band'=>$scoreRes['band'],'score'=>$scoreRes['score']]);

// Realtime (test harness style)
$rtDir = __DIR__.'/../src/Realtime';
foreach(['EventStream','HeartbeatEmitter'] as $rt){ $rp=$rtDir.'/'.$rt.'.php'; if(is_file($rp)) require_once $rp; }
use Unified\Realtime\EventStream; use Unified\Realtime\HeartbeatEmitter;

$stream = new EventStream($logger);
$hbEmitter = new HeartbeatEmitter($logger);
ob_start();
$hbEmitter->emit($stream,1,1000);
$hbOut = ob_get_clean();

// Drift & Views
$driftDir = __DIR__.'/../src/Drift'; $viewsDir = __DIR__.'/../src/Views';
foreach(['PsiCalculator'] as $d){ $dp=$driftDir.'/'.$d.'.php'; if(is_file($dp)) require_once $dp; }
foreach(['ViewMaterializer'] as $vv){ $vp=$viewsDir.'/'.$vv.'.php'; if(is_file($vp)) require_once $vp; }
// Persistence
$persistDir = __DIR__.'/../src/Persistence';
foreach(['Db','ProposalRepository','GuardrailTraceRepository','InsightRepository','RunLogRepository','DriftMetricsRepository','ProposalStore','CooloffRepository','ActionAuditRepository'] as $pr){ $ppr=$persistDir.'/'.$pr.'.php'; if(is_file($ppr)) require_once $ppr; }
use Unified\Persistence\ProposalRepository; use Unified\Persistence\GuardrailTraceRepository; use Unified\Persistence\InsightRepository; use Unified\Persistence\RunLogRepository; use Unified\Persistence\DriftMetricsRepository;
use Unified\Persistence\ProposalStore; use Unified\Persistence\CooloffRepository; use Unified\Persistence\ActionAuditRepository;

// Policy orchestrator wiring (with proposal store returning IDs now)
$proposalRepo = new ProposalRepository($logger);
$traceRepo = new GuardrailTraceRepository($logger);
$proposalStore = new ProposalStore($logger,$proposalRepo);
$cooloffRepo = new CooloffRepository($logger);
$auditRepo = new ActionAuditRepository($logger);
$policy = new PolicyOrchestrator($logger,$chain,$scoring,$proposalStore,$traceRepo,$cooloffRepo,$auditRepo);
$policyRes = $policy->process($ctx,[
  'margin_uplift'=>0.4,
  'competitor_alignment'=>0.2,
  'risk_penalty'=>-0.1,
  'inventory_balance'=>0.15
]);

// Pricing engine skeleton execution (Phase M13)
$pricingDir = __DIR__.'/../src/Pricing';
foreach(['CandidateBuilder','RuleEvaluator','PricingEngine'] as $pc){ $pp=$pricingDir.'/'.$pc.'.php'; if(is_file($pp)) require_once $pp; }
use Unified\Pricing\CandidateBuilder; use Unified\Pricing\RuleEvaluator; use Unified\Pricing\PricingEngine;
$candidateBuilder = new CandidateBuilder($logger);
$ruleEvaluator = new RuleEvaluator($logger);
$pricingEngine = new PricingEngine($logger,$candidateBuilder,$ruleEvaluator,$scoring,$policy);
$pricingRun = $pricingEngine->run(['run_id'=>bin2hex(random_bytes(8))]);

// Transfer service execution (Phase M14)
$transferDir = __DIR__.'/../src/Transfer';
foreach(['DsrCalculator','LegacyAdapter','TransferService'] as $tf){ $tp=$transferDir.'/'.$tf.'.php'; if(is_file($tp)) require_once $tp; }
use Unified\Transfer\DsrCalculator; use Unified\Transfer\LegacyAdapter; use Unified\Transfer\TransferService;
$dsrCalc = new DsrCalculator($logger);
$legacyAdapter = new LegacyAdapter($logger,$adapter);
$transferService = new TransferService($logger,$legacyAdapter,$dsrCalc,$scoring,$policy);
$transferRun = $transferService->run(['run_id'=>bin2hex(random_bytes(8))]);

// Matching utilities (Phase M15)
$matchingDir = __DIR__.'/../src/Matching';
foreach(['BrandNormalizer','TokenExtractor','FuzzyMatcher'] as $mc){ $mp=$matchingDir.'/'.$mc.'.php'; if(is_file($mp)) require_once $mp; }
use Unified\Matching\BrandNormalizer; use Unified\Matching\TokenExtractor; use Unified\Matching\FuzzyMatcher;
$bn = new BrandNormalizer($logger); $te = new TokenExtractor($logger); $fm = new FuzzyMatcher($logger);
$brandNorm = $bn->normalize('The Vape Shed');
$toksA = $te->extract('Geek Vape Super Tank 3000');
$toksB = $te->extract('GeekVape SuperTank 3000 Replacement');
$simScore = $fm->similarity($toksA,$toksB);

// Forecast heuristic provider (Phase M16)
$forecastDir = __DIR__.'/../src/Forecast';
foreach(['HeuristicProvider'] as $fh){ $fp=$forecastDir.'/'.$fh.'.php'; if(is_file($fp)) require_once $fp; }
use Unified\Forecast\HeuristicProvider;
$heur = new HeuristicProvider($logger);
$forecastSummary = $heur->summarize([12,11,9,14,13,10,8,15]);

// Insight enrichment (Phase M17)
foreach(['InsightEnricher'] as $ieCls){ $iePath=$insightDir.'/'.$ieCls.'.php'; if(is_file($iePath)) require_once $iePath; }
use Unified\Insights\InsightEnricher;
$enricher = new InsightEnricher($logger,$proposalRepo,new DriftMetricsRepository($logger));
$enrichment = $enricher->snapshot();

$output = ['ok'=>true,'simulate'=>$res,'guardrail'=>$gr,'scoring'=>$scoreRes,'heartbeat_sample'=>$hbOut,'psi'=>$psiData,'materialization'=>$matRes,'policy'=>$policyRes,'pricing'=>$pricingRun,'transfer'=>$transferRun,'matching'=>['brand_norm'=>$brandNorm,'sim'=>$simScore],'forecast'=>$forecastSummary,'enrichment'=>$enrichment];
echo json_encode($output, JSON_UNESCAPED_SLASHES)."\n";
