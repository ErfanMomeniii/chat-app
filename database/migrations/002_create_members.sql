-- Create the members table with auto-increment id for cursor pagination
-- Composite PK replaced with UNIQUE constraint so id can be the PK
CREATE TABLE IF NOT EXISTS members (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    group_id INTEGER NOT NULL,
    user_id TEXT NOT NULL,
    joined_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%SZ', 'now')),
    UNIQUE (group_id, user_id),
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_members_group_id ON members(group_id, id);
CREATE INDEX IF NOT EXISTS idx_members_user_id ON members(user_id);
