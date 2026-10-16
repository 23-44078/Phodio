-- Adds the database-backed client portal session table.
--
-- Run this in the Supabase SQL Editor if you already created the schema with
-- an older version of supabase_schema.sql. A fresh database that imports the
-- current supabase_schema.sql already contains this table.
--
-- Safe to run more than once.

CREATE TABLE IF NOT EXISTS phodio_sessions (
    session_id VARCHAR(64) PRIMARY KEY,
    client_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    client_username VARCHAR(150) NOT NULL,
    client_name VARCHAR(200) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    expires_at TIMESTAMPTZ NOT NULL
);

CREATE INDEX IF NOT EXISTS phodio_sessions_client_id ON phodio_sessions (client_id);
CREATE INDEX IF NOT EXISTS phodio_sessions_expires_at ON phodio_sessions (expires_at);

-- Optional housekeeping: remove sessions that already expired.
DELETE FROM phodio_sessions WHERE expires_at < NOW();

ALTER TABLE phodio_sessions ENABLE ROW LEVEL SECURITY;
