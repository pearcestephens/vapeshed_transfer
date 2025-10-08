# ✅ Pilot Monitoring Checklist - Vapeshed Transfer Engine

**Date:** [Enter date]
**Responsible:** [Inventory Manager / IT Support]

---

## Daily Checks
- [ ] Review overnight cron logs: `tail -50 logs/cron_$(date +%Y%m%d).log`
- [ ] Verify health checks passing: `php bin/health_check.php`
- [ ] Check for errors: `grep -i error logs/*.log | tail -20`
- [ ] Review low stock items: `php tests/test_business_analysis.php | head -50`
- [ ] Confirm transfer recommendations sent to stores
- [ ] Collect staff feedback using template

## Weekly Checks
- [ ] Run full test suite: `php tests/test_transfer_engine_integration.php`
- [ ] Review cache performance: `php tests/test_cache_performance.php`
- [ ] Analyze transfer opportunities: `php tests/test_business_analysis.php`
- [ ] Document any data quality issues
- [ ] Prepare weekly review for executive team

## Incident Response
- [ ] Log any system errors in `logs/pilot_issues.log`
- [ ] Escalate critical issues to IT support
- [ ] Document resolution steps

## End-of-Pilot Review
- [ ] Summarize pilot results (accuracy, impact, feedback)
- [ ] Present findings to executive team
- [ ] Prepare for full rollout or address issues

---

**Signature:** ___________________________  
**Date:** ________________________________
