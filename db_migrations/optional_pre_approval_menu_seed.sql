-- ============================================================================
-- Menu seed: new submodule entries + permissions for the multi-step optional
-- pre-approval flow.
--
-- Adds two new submodule rows:
--   1. supervisor-queue (module 47 — Leave)  → for supervisor সুপারিশ
--   2. forward-queue   (module 45 — Admin)   → for center admin forwarding
--
-- Existing rows (unchanged):
--   175. optional-pre-approval        (module 47) — employee self-service
--   176. optional-pre-approval-queue  (module 47) — signatory chain queue
--
-- Run this AFTER optional_pre_approval_chain_alignment.sql.
-- ============================================================================

-- --- 1. Insert new submodules -----------------------------------------------
INSERT INTO submodules (submodule_name, module_id, page_link, slug, created_by, create_date, display_order)
VALUES ('ঐচ্ছিক ছুটি সুপারিশ', 47, 'views/optional-pre-approval/supervisor-queue.php', 'optional-pre-approval-supervisor-queue', 1, NOW(), 32);

INSERT INTO submodules (submodule_name, module_id, page_link, slug, created_by, create_date, display_order)
VALUES ('ঐচ্ছিক ছুটি সম্পাদনা', 45, 'views/optional-pre-approval/forward-queue.php', 'optional-pre-approval-forward-queue', 1, NOW(), 20);

-- Capture the inserted IDs into vars for the permission grants that follow.
-- (Use LAST_INSERT_ID() + a lookup fallback.)
SET @sup_id = (SELECT dataID FROM submodules WHERE slug = 'optional-pre-approval-supervisor-queue');
SET @fwd_id = (SELECT dataID FROM submodules WHERE slug = 'optional-pre-approval-forward-queue');

-- --- 2. Permission grants ---------------------------------------------------
-- Supervisor queue: any user could be a supervisor → grant broad access
-- (fetch API filters by signatory = me, so unauthorized users see empty list).
INSERT INTO group_access_permission (user_group_id, module_id, submodule_id)
SELECT g.id, 47, @sup_id
FROM user_group g
WHERE g.id IN (1, 2, 3, 4, 5, 6, 7, 8, 10, 11, 12)
  AND NOT EXISTS (SELECT 1 FROM group_access_permission gap
                  WHERE gap.user_group_id = g.id AND gap.submodule_id = @sup_id);

-- Forward queue: only center-admin-tier groups (mirrors allowed-leave-applications).
-- Fetch API additionally enforces user_list.isCenterAdmin = 1.
INSERT INTO group_access_permission (user_group_id, module_id, submodule_id)
SELECT g.id, 45, @fwd_id
FROM user_group g
WHERE g.id IN (1, 2, 3, 7, 8, 11, 12)
  AND NOT EXISTS (SELECT 1 FROM group_access_permission gap
                  WHERE gap.user_group_id = g.id AND gap.submodule_id = @fwd_id);
