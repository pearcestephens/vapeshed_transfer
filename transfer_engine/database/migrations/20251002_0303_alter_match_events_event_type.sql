-- Widen event_type to VARCHAR to allow new analytic events without enum churn
ALTER TABLE product_candidate_match_events 
  MODIFY COLUMN event_type VARCHAR(64) NOT NULL;
