-- 20261009_03 — Remove the free-text booking title
--
-- Run in the Supabase SQL Editor after 20261009_02_chat_messages.sql.
-- Safe to re-run. Back up the database before applying it.
--
-- A booking used to carry both a free-text "title" and a package name
-- (package_type), which produced two competing labels for the same thing in
-- the calendar, the client portal and the admin inspector. The package name is
-- now the single source of truth; see phodio_booking_label() in
-- api/includes/booking_helpers.php.
--
-- Nothing is lost: package_type already holds a readable label for every row,
-- and any row left with a blank label is filled in below before the column is
-- dropped.

UPDATE bookings
SET package_type = 'Photography Session'
WHERE package_type IS NULL
   OR btrim(package_type) = '';

ALTER TABLE bookings
    DROP COLUMN IF EXISTS title;
