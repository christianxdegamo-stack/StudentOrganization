# OrgSync: Student Organization Event & Membership Management System

PHP 8 + MySQL (PDO) + Bootstrap 5, built to run locally on **XAMPP (Windows)**.

---

## 1. Install & run (step by step)

1. **Install XAMPP** (PHP 8.0 or newer) from https://www.apachefriends.org and open the **XAMPP Control Panel**.
2. Click **Start** next to **Apache** and **MySQL**. Both should turn green.
3. **Copy the project folder.** Unzip `orgsync.zip` so you end up with `C:\xampp\htdocs\orgsync\` (the folder must contain `index.php`, `admin`, `includes`, etc.).
4. **Import the database** (phpMyAdmin):
   1. Open http://localhost/phpmyadmin
   2. Click the **Import** tab (top).
   3. Click **Choose File** and select `C:\xampp\htdocs\orgsync\database\orgsync_db.sql`.
   4. Click **Import** at the bottom. You should see the new database `orgsync_db` with 8 tables.
5. **Open the app:** http://localhost/orgsync/
6. Default XAMPP MySQL login is user `root` with **no password**. If yours is different, edit `config/database.php`.

> The pages load Bootstrap and icons from the internet (CDN), so the computer needs an internet connection the first time.
> Re-importing `orgsync_db.sql` **resets all data** back to the demo records.

## 2. Demo accounts

| Role | Log in with | Password |
|---|---|---|
| Super Admin | `admin` | `Admin@123` |
| Club Officer (Computer Society) | `2022-0101` | `Officer@123` |
| Club Officer (Entrepreneurs Guild) | `2022-0102` | `Officer@123` |
| Club Officer (Cultural Dance Troupe) | `2023-0103` | `Officer@123` |
| Student | `2023-0104` (Carlo Mendoza) | `Student@123` |
| Student | `2024-0108` (Paolo Ramos) | `Student@123` |

Other students: `2023-0105`, `2023-0106`, `2024-0107`, `2024-0109`, `2025-0110`, `2025-0111`, `2025-0112` (all `Student@123`).
Students can log in with their **Student ID or email**. All names are fictional.

**How roles work:** a *Club Officer* is a student whose membership in an organization has role **Officer** and status **Active**. Officers see the Officer panel for their own organization *and* keep their Student view. The Super Admin assigns officers (Organizations → View → "Assign officer", or Memberships → "Make Officer").

## 3. Folder structure

```
orgsync/
  config/database.php        database settings + PDO connection
  auth/                      login.php, register.php, logout.php
  admin/                     Super Admin pages
  officer/                   Club Officer pages
  student/                   Student pages
  includes/
    functions.php            security + helper functions (CSRF, escaping, queries, CSV)
    auth_check.php           role-based access control
    header.php / sidebar.php / footer.php   shared layout
    modules/                 the real page logic (admin & officer pages share it)
  assets/css/style.css, assets/js/script.js
  database/orgsync_db.sql    schema + demo data
  index.php, README.md
```

Admin and officer pages with the same name (events, memberships, registrations, attendance, reports) are thin files that check the role and load the **same module** from `includes/modules/`. The module automatically limits an officer to their own organization.

## 4. Database design and improvements over the proposal

Database: `orgsync_db` (InnoDB, utf8mb4). The proposal's four tables are kept; these changes were needed to deliver the features it describes:

| Change | Why |
|---|---|
| **New `memberships` table** | The proposal describes Membership data and ERD relationships but listed no table. Unique key `(student_id, org_id)` prevents duplicate memberships. |
| Membership `status` also allows **Pending** and **Rejected** | Proposal values were Active/Inactive; students must *apply* and officers *approve/reject*. For a pending application, `date_joined` stores the application date and is reset to the approval date when approved. |
| **New `event_registrations` table** | Sign-up and actual attendance are different things. Unique `(event_id, student_id)` blocks duplicates; status is `Registered` or `Cancelled` (capacity counts only `Registered`). |
| `events.event_description` added | Proposal's Event data lists a description but the table lacked the column. |
| `event_attendance.status` default changed `'Attended'` -> **`'Present'`**; allowed values `Present` / `Excused` (CHECK) | Matches the proposal's attendance statuses. Unique `(event_id, student_id)` prevents duplicate attendance. |
| **New `users` table** | Super Admins are not students, so they need a separate login table. Students/officers authenticate from `students.password_hash`. |
| **New `activity_logs` table** | Powers "Recent system activities". `user_id` stores a student ID or `ADMIN-n`, so it is intentionally not a foreign key. |
| CHECK constraints, indexes, `ON DELETE CASCADE` foreign keys | Data integrity (e.g. deleting an organization removes its events/memberships). |

## 5. Security features

- Passwords stored with `password_hash()` (bcrypt) and checked with `password_verify()`; never plain text.
- 100% PDO prepared statements (real, not emulated).
- CSRF token on every POST form, verified server-side (including logout).
- Output escaping with `htmlspecialchars` (helper `e()`) everywhere user data is displayed.
- Session hardening: HttpOnly + SameSite cookies, session ID regenerated at login, 30-minute inactivity timeout, secure logout (POST + CSRF, session destroyed).
- Role checks on every page **and** ownership checks on every action (an officer cannot change another organization's data even by editing the form).
- Login throttling (5 wrong tries = 60 second lock for that browser session).
- Server-side validation of all inputs; CSV export protects against spreadsheet formula injection.
- `.htaccess` blocks direct browser access to `config/`, `includes/`, `database/`.

## 6. Testing instructions (by module)

Use a normal browser window for one role and a private window for another so you can test two roles at once.

**A. Login / Registration**
1. Log in as `admin` / `Admin@123` -> goes to Admin Dashboard. Log out (top-right menu).
2. Log in with a wrong password -> error message. 5 wrong tries -> lock message.
3. Click "Create an account", register a new student (e.g. ID `2026-0001`), then log in with it -> Student Dashboard.
4. While logged in as a student, type `.../orgsync/admin/dashboard.php` -> you are redirected with "no permission".

**B. Admin Dashboard** - cards show counts (12 students, 5 organizations, etc.). Click a card to open its page. Add a student, then reload the dashboard: the count changes.

**C. Students (admin)** - Add a student; edit them (leave password blank to keep it); search by name/ID; filter by course and year; delete a student (confirm dialog). Try adding a duplicate ID or email -> error.

**D. Organizations (admin)** - Add, edit, delete. Click a name to see details; use "Assign officer" to make a student an officer, then log in as that student and confirm the Officer panel appears. Use "Remove" to undo.

**E. Memberships**
1. As student `2023-0104`: Organizations -> apply to "Entrepreneurs Guild" -> status Pending. Applying again is not offered (no duplicates).
2. As officer `2022-0102`: Memberships -> Pending tab -> Approve (or Reject).
3. Back as the student: My Memberships shows the new status. Rejected applications can be re-applied.
4. Admin: Memberships -> use Add Membership, Make Officer / Make Member, Deactivate / Reactivate, and filters.

**F. Events** - As an officer (or admin) create an event with a future date, edit it, view details, filter Upcoming/Past/All, search. Officers only see their own organization's events. Lowering capacity below the number already registered is refused.

**G. Event registration**
1. As a student: Browse Events -> Register. Registering again is not offered. Cancel works for upcoming events.
2. "Startup Pitch Night" has capacity 3 and 3 registered: it shows **Full** and cannot be registered for. Cancel one registration (as admin: Event Registrations -> Cancel) and the seat opens.
3. Officer/Admin: Registrations page lists participants and allows Cancel / Restore (restore is blocked if the event is full).

**H. Attendance** - Officer: Attendance -> record a student (pick event, student, status, time). Recording the same student twice for one event is refused. Use the **Quick Check-in** list after choosing an event. Change status or delete from the history table. A student sees the result in "My Attendance".

**I. Reports** - Admin/Officer: Reports -> choose a report tab, set filters, click Generate, **Print** (browser print dialog hides menus) or **Export CSV** (opens in Excel). Officers only get their own organization's data; only the admin has the Student List report.

## 7. Notes & limits

- Tables show up to 500 attendance rows at a time and are not paginated (fine for a school project; add pagination before real-world scale).
- Dates/times use the Philippine time zone (`Asia/Manila`); change `APP_TIMEZONE`/`MYSQL_TZ` in `config/database.php` if needed.
- To use a different folder name than `orgsync`, just rename the folder - the web path is detected automatically.
- Troubleshooting: **"Database connection failed"** -> MySQL not started, database not imported, or wrong password in `config/database.php`. **Blank page** -> check `C:\xampp\apache\logs\error.log`. **Page looks unstyled** -> no internet for the Bootstrap CDN.
