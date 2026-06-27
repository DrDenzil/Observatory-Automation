"""One-time migration: add arduino_available column to scopes table."""
import sqlite3, sys

db_path = sys.argv[1] if len(sys.argv) > 1 else "observatory.db"
conn = sqlite3.connect(db_path)
cur = conn.cursor()

cols = {row[1] for row in cur.execute("PRAGMA table_info(scopes)")}

if "arduino_available" not in cols:
    cur.execute("ALTER TABLE scopes ADD COLUMN arduino_available BOOLEAN NOT NULL DEFAULT 0")
    print("Added arduino_available")
else:
    print("arduino_available already exists")

conn.commit()
conn.close()
print("Migration complete.")
