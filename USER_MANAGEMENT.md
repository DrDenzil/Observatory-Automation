# User Management System

Complete user administration system for Bayfordbury Observatory with legacy system integration and future Microsoft OAuth support.

## Overview

The user management system allows admins to:
- **List & search** users with filtering by role and type
- **Add users manually** for external partners or test accounts
- **Bulk import** users from legacy system via CSV
- **Update user details** (name, role, department)
- **Deactivate users** (soft delete, preserves history)
- **Preserve legacy IDs** for project/data migration

## Database Schema

The `User` model includes:

```python
class User(Base):
    id: str                          # UUID, primary key
    legacy_id: int | None            # Maps to old system (unique, indexed)
    email: str                        # Unique, indexed
    name: str
    hashed_password: str | None      # Nullable for OAuth-only users
    role: str                         # observer | staff | admin
    user_type: str                    # student | staff | external
    is_active: bool                   # False = soft-deleted
    oauth_sub: str | None            # Microsoft OAuth subject (future)
    department: str | None            # e.g. "Astronomy", "Physics"
    created_at: datetime
    updated_at: datetime
```

## API Endpoints

### List Users
```
GET /api/users?search=&role=admin&user_type=student&is_active=true&limit=100&offset=0
```
Auth: Admin only. Returns paginated list with filtering.

### Get User
```
GET /api/users/{user_id}
```
Auth: Admin only.

### Create User (Manual)
```
POST /api/users
{
  "email": "user@herts.ac.uk",
  "name": "John Doe",
  "role": "observer",
  "user_type": "student",
  "legacy_id": 42,
  "department": "Astronomy"
}
```
Auth: Admin only. Creates user **without password** (OAuth or admin-reset only).

### Update User
```
PATCH /api/users/{user_id}
{
  "name": "Jane Doe",
  "role": "staff",
  "user_type": "staff",
  "is_active": true,
  "department": "Physics"
}
```
Auth: Admin only. All fields optional.

### Delete User (Soft Delete)
```
DELETE /api/users/{user_id}
```
Auth: Admin only. Marks user as inactive; cannot delete self.

### Bulk Import CSV
```
POST /api/admin/import/users-csv
Content-Type: multipart/form-data
file: <csv_file>
```
Auth: Admin only. Imports users from CSV file.

## User Management UI

Access: `/users` (admin only, visible in header for admin role)

### Features

**Search & Filter**
- Search by name or email
- Filter by role (observer/staff/admin)
- Filter by user type (student/staff/external)
- Results paginated, 100 per page

**Add User**
- Manual user creation for external partners
- Set role, type, legacy ID (for migration)
- No password required (OAuth or admin-reset later)

**Import CSV**
- Bulk import from legacy system
- Expected CSV columns:
  - `user_id` (maps to legacy_id)
  - `name`
  - `email`
  - `account_level` (optional: Administrator → admin, Student → observer)
  - `user_type` (optional: student/staff/external)
- Reports: imported count, skipped count, errors

**User Table**
- Shows: Name, Email, Role, Type, Legacy ID, Department, Registered date, Status
- Deactivate button (if active)
- Bulk actions planned

## Migration from Legacy System

### Step 1: Export Legacy Data
From the old system (`accounts.php`), export a CSV with columns:
```
user_id,name,email,account_level,user_type
1,David Campbell,d.a.campbell2@herts.ac.uk,Administrator,staff
33,Thomas Spriggs,ts11adi@herts.ac.uk,Student,student
36,Mark Thompson,m.a.thompson@herts.ac.uk,Student,student
...
```

### Step 2: Upload CSV
1. Go to `/users` (as admin)
2. Click "Import CSV"
3. Select exported file
4. Review results (imported vs. skipped vs. errors)

### Step 3: Verify
- Users appear in user list with legacy_id preserved
- Old project/observation IDs still reference correct users via legacy_id

### Step 4: Link Projects
When migrating observation requests/projects:
```python
# Old system: project_user_id = 33
# New system: query User where legacy_id == 33
# Link new project to user.id (UUID)
```

## Roles & Permissions

| Role | Permissions |
|------|-------------|
| **admin** | Full access: users, telescopes, approvals, job dispatch |
| **staff** | Approvals, job queue management, view dashboards |
| **observer** | Submit requests, view own activity |

## User Types

| Type | Purpose |
|------|---------|
| **student** | University student (can login via OAuth @herts.ac.uk) |
| **staff** | University staff (can login via OAuth @herts.ac.uk) |
| **external** | External partner (manually added by admin, no OAuth) |

## Future: Microsoft OAuth Integration

Currently, users log in with **email + password**. Future versions will integrate **Microsoft OAuth** for `@herts.ac.uk` domain:

### Planned Changes
1. Add OAuth client config (Azure AD)
2. Map OAuth `sub` claim → `User.oauth_sub`
3. Auto-provision users on first login (if from herts.ac.uk)
4. Fall back to manual login for external partners

### Configuration
```yaml
# In config or environment
OAUTH_CLIENT_ID=<azure-ad-client-id>
OAUTH_CLIENT_SECRET=<secret>
OAUTH_TENANT=common
OAUTH_REDIRECT_URI=https://observatory.herts.ac.uk/auth/callback
```

### Code Structure (Ready)
- `User.oauth_sub` field exists (for Microsoft subject claim)
- `User.hashed_password` is nullable (OAuth users won't have password)
- `User.user_type` distinguishes student/staff/external (external won't use OAuth)
- Manual user creation already allows no password

## Implementation Notes

### Soft Deletes
Users are soft-deleted (marked inactive), not hard-deleted, to:
- Preserve request/project history
- Maintain referential integrity
- Allow re-activation if needed
- Support auditing

### Password Handling
- **Manually-created users**: No password set; admin must reset or use OAuth
- **Legacy imports**: No passwords migrated; require reset on first login
- **OAuth users (future)**: No password, use OAuth flow

### Legacy ID Uniqueness
`legacy_id` is unique and indexed, allowing fast lookup during migration:
```python
await db.execute(select(User).where(User.legacy_id == 33))
```

### CSV Import Robustness
- Duplicate detection: skips if email or legacy_id exists
- Error collection: reports first 10 errors without stopping import
- Transaction safety: all-or-nothing on each row (commit after all rows)

## Testing

### Manual Test (Add User)
1. Login as admin
2. Click Users → Add User
3. Fill form (email, name, role, type)
4. Click Create
5. Verify user appears in list

### Manual Test (Import CSV)
1. Prepare CSV with sample users
2. Go to Users → Import CSV
3. Select file → Import
4. Check results panel
5. Verify users in list (with legacy_id preserved)

### Admin Reset Password (Future)
```python
# In admin panel (not yet built)
user = await db.get(User, user_id)
user.hashed_password = hash_password(new_password)
await db.commit()
```

## Security

- **Access Control**: All user endpoints require admin role (`require_role("admin")`)
- **CORS**: Same-origin only (configured in FastAPI middleware)
- **Input Validation**: Email, names, legacy_id ranges checked
- **SQL Injection**: SQLAlchemy ORM prevents injection
- **Soft Deletes**: Preserves compliance/audit trail

## Common Tasks

### Add an external partner
1. Admin → Users → Add User
2. Email: partner@company.com
3. Name: Partner Name
4. Role: observer (or staff)
5. User Type: external
6. Leave password blank
7. Create

### Import 50 legacy users
1. Export CSV from old `accounts.php`
2. Admin → Users → Import CSV
3. Select file
4. Review results (look for errors)
5. If errors, fix CSV and retry

### Deactivate a user
1. Find user in list
2. Click "Deactivate"
3. Confirm
4. User marked inactive, can't login

### Change user role
1. Click user in list (future: edit page)
2. Or use API: `PATCH /api/users/{id}` with new role
3. Changes immediate

## Troubleshooting

**Import fails: "CSV must have columns..."**
- Ensure CSV has: user_id, name, email
- account_level and user_type are optional

**Import reports all skipped**
- Users may already exist (check by email)
- Duplicate legacy_id values

**Can't create user: "Email already exists"**
- User with that email already in system
- Use different email or deactivate first

**User can't login**
- Check `is_active = true`
- Verify password was set (not OAuth-only user)
- Check role (observer/staff/admin, not role='disabled' or typo)

## Related Files

- Backend: `app/models/user.py`, `app/routes/users.py`, `app/routes/users_import.py`
- Frontend: `pages/Users.tsx`, `pages/Users.module.css`
- Schema: `app/schemas/user.py` (UserCreate, UserOut, UserUpdate, UserAdminCreate)
- Auth: `services/auth.py` (require_role dependency)
