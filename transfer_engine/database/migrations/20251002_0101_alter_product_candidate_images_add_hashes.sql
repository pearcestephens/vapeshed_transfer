-- Extend product_candidate_images with perceptual hash fields
ALTER TABLE product_candidate_images
  ADD COLUMN p_hash CHAR(64) NULL AFTER content_hash,
  ADD COLUMN d_hash CHAR(32) NULL AFTER p_hash,
  ADD COLUMN a_hash CHAR(32) NULL AFTER d_hash,
  ADD COLUMN dominant_color CHAR(7) NULL AFTER a_hash,
  ADD COLUMN vision_labels JSON NULL AFTER dominant_color,
  ADD KEY idx_p_hash (p_hash),
  ADD KEY idx_d_hash (d_hash),
  ADD KEY idx_a_hash (a_hash);
