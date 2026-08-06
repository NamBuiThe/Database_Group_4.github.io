Smart Fleet Management System

A full-stack web application for COS20031 — Database Design Project (Swinburne University of Technology, Semester 1 2026), built on top of the Smart Fleet Management Database.

The system supports two primary stakeholders:

Fleet Safety Operations Staff — vehicle assignments, telematics event logging, driver safety scoring and review.
Workshop Management Staff — maintenance job tracking, parts usage, mechanic assignment, and reporting.
Tech Stack
Backend: Plain PHP (PDO, prepared statements)
Database: MySQL 8.4 (Aiven Cloud)
Frontend: Server-rendered PHP + custom CSS
Auth: Session-based login with role-based access control (RBAC) and depot-level scoping
Project Structure
Smart Fleet/
├── assignments/     # Vehicle-driver assignment screens
├── includes/        # Shared layout (header/footer) + auth.php (RBAC helpers)
├── maintenance/      # Maintenance job & parts screens
├── reports/          # Read-only reporting screens
├── telematics/        # Telematics event logging & driver safety score
├── config.example.php # Template for local DB config (see setup below)
├── index.php          # Dashboard
├── login.php / logout.php
└── .gitignore
Roles
Role	Access
Admin	Full access to every screen and all depots
Fleet Safety Staff	Vehicle assignments, telematics events, safety reviews
Workshop Staff	Maintenance jobs, parts usage, workshop reports
Driver	Reserved for future driver-facing features
Setup
1. Requirements
PHP 8+ with the pdo_mysql extension enabled
Access to a MySQL 8.x database (a local XAMPP instance or the team's Aiven Cloud instance)
A local web server — e.g. XAMPP (htdocs/)
2. Clone the repository
bash
git clone https://github.com/NamBuiThe/Database_Group_4.github.io.git
cd "Database_Group_4.github.io/Smart Fleet"
3. Configure your database connection

The real config.php is not committed to this repository (it contains live database credentials and is listed in .gitignore). Instead, copy the provided template and fill in your own values:

bash
cp config.example.php config.php

Then open config.php and set your own credentials — either by editing the fallback values directly, or (recommended) by setting the following environment variables before running your local server:

Variable	Description	Example
DB_HOST	Database host	127.0.0.1 or the Aiven hostname
DB_PORT	Database port	3306 (local) / Aiven's assigned port
DB_NAME	Database name	SmartFleetDatabase
DB_USER	Database username	root (local) / your Aiven role account
DB_PASS	Database password	(never commit this)
DB_CHARSET	Connection charset	utf8mb4

⚠️ Do not commit your filled-in config.php. It's already covered by .gitignore — double check with git status that it shows as untracked before pushing.

4. Run locally

If using XAMPP:

Place the project folder inside htdocs/.
Start Apache and MySQL from the XAMPP control panel.
Import the project's SQL schema into your local MySQL instance (see the team's Confluence space for the latest .sql export).
Visit http://localhost/<project-folder>/Smart Fleet/login.php.
5. Log in

Use an account seeded in the Users table (one Admin / Fleet Safety Staff / Workshop Staff account exists per depot). Contact a team member for test credentials — these are not published in this README or in the app itself.

Security Notes
All database queries use PDO prepared statements (no string-concatenated SQL).
Passwords are stored with password_hash() / verified with password_verify() — never compared in raw SQL.
State-changing forms are protected with CSRF tokens.
Login includes basic brute-force throttling (progressive delay + temporary lockout after repeated failed attempts).
Access control is enforced both at the application layer (role/depot checks in includes/auth.php) and at the database layer (least-privilege MySQL roles via GRANT).
Team

Group 4 — "The Fantastic 4" — COS20031, Semester 1 2026, Swinburne University of Technology.
