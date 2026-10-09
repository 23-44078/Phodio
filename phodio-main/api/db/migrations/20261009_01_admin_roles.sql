-- 20261009_01 — Unified login: studio account roles (admin / super_admin)
--
-- Run in the Supabase SQL Editor after 20261002_manuscript_alignment.sql.
-- Safe to re-run. Back up the database before applying it.
--
-- What this adds:
--   admin.role          'admin' or 'super_admin'
--   admin.full_name     display name used in the sidebar and chat
--   admin.is_active     lets a super admin disable an account without deleting it
--   admin.last_login_at audit trail for the unified login page
--
-- Until this file is applied the PHP app degrades gracefully: every existing
-- studio account behaves as a super admin.

ALTER TABLE admin
    ADD COLUMN IF NOT EXISTS role VARCHAR(20) NOT NULL DEFAULT 'admin';

ALTER TABLE admin
    ADD COLUMN IF NOT EXISTS full_name VARCHAR(120);

ALTER TABLE admin
    ADD COLUMN IF NOT EXISTS is_active BOOLEAN NOT NULL DEFAULT TRUE;

ALTER TABLE admin
    ADD COLUMN IF NOT EXISTS last_login_at TIMESTAMPTZ;

-- Re-runnable constraint: drop it first, then recreate it.
ALTER TABLE admin
    DROP CONSTRAINT IF EXISTS admin_role_check;

ALTER TABLE admin
    ADD CONSTRAINT admin_role_check
    CHECK (role IN ('admin', 'super_admin'));

-- Existing studio accounts keep full control after the upgrade.
UPDATE admin
SET role = 'super_admin'
WHERE NOT EXISTS (
    SELECT 1 FROM admin WHERE role = 'super_admin'
);

CREATE INDEX IF NOT EXISTS admin_is_active_idx
    ON admin (is_active);
