"""One-time migration: add webcam_available and last_ip columns to scopes table."""
import sqlite3, sys

db_path = sys.argv[1] if len(sys.argv) > 1 else "observatory.db"
conn = sqlite3.connect(db_path)
cur = conn.cursor()

cols = {row[1] for row in cur.execute("PRAGMA table_info(scopes)")}

if "webcam_available" not in cols:
    cur.execute("ALTER TABLE scopes ADD COLUMN webcam_available BOOLEAN NOT NULL DEFAULT 0")
    print("Added webcam_available")
else:
    print("webcam_available already exists")

if "last_ip" not in cols:
    cur.execute("ALTER TABLE scopes ADD COLUMN last_ip VARCHAR(45)")
    print("Added last_ip")
else:
    print("last_ip already exists")

conn.commit()
conn.close()
print("Migration complete.")
