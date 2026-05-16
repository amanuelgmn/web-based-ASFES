# ASTU SFES

This folder contains a web-based implementation of the Academic Student Feedback and Evaluation System using only:

- HTML
- CSS
- JavaScript
- PHP

No frameworks are used.

## Live Deployment

The project is deployed on InfinityFree and can be accessed here:

- https://studentfeedbackevaluation.infinityfree.me/


## Features

- Role-based login
- Student feedback submission
- Anonymous or named feedback handling
- Automatic routing by category
- Instructor, department, student affairs, and admin views
- Status tracking: Submitted, Seen, Responded, Closed
- Persistent profile updates
- Persistent notifications
- Attachments on feedback submissions
- Audit trail and admin console
- Search, filter, pagination, and SLA tracking in inbox/report views
- Reports and analytics page with CSV export
- MySQL-backed PHP storage with auto-seeding sample data

## Default accounts

Use these credentials after opening `index.php`:

- Student: `student@astu.edu` / `password`
- Instructor: `instructor@astu.edu` / `password`
- Department: `department@astu.edu` / `password`
- Student Affairs: `studentaffairs@astu.edu` / `password`
- Admin: `admin@astu.edu` / `password`


## Local Setup

If you want to run the project locally, create the MySQL database and load the schema + seed data:

```bash
mysql -u root -p < database.sql
```

Then place the folder in a PHP-capable local server directory and open `index.php` in your browser.

## MySQL configuration

You can override the default database connection with environment variables:

- `ASFES_DB_HOST`
- `ASFES_DB_PORT`
- `ASFES_DB_NAME`
- `ASFES_DB_USER`
- `ASFES_DB_PASS`

Defaults:

- Host: `127.0.0.1`
- Port: `3306`
- Database: `asfes`
- User: `root`
- Password: empty

## Notes

- The backend uses PDO with MySQL and can also auto-create the schema if the database is reachable.
<!-- Readability enhancement step 1 -->
<!-- Readability enhancement step 2 -->
<!-- Readability enhancement step 3 -->
<!-- Readability enhancement step 4 -->
<!-- Readability enhancement step 5 -->
<!-- Readability enhancement step 6 -->
<!-- Readability enhancement step 7 -->
<!-- Readability enhancement step 8 -->
<!-- Readability enhancement step 9 -->
<!-- Readability enhancement step 10 -->
<!-- Readability enhancement step 11 -->
<!-- Readability enhancement step 12 -->
<!-- Readability enhancement step 13 -->
<!-- Readability enhancement step 14 -->
<!-- Readability enhancement step 15 -->
<!-- Readability enhancement step 16 -->
<!-- Readability enhancement step 17 -->
<!-- Readability enhancement step 18 -->
<!-- Readability enhancement step 19 -->
<!-- Readability enhancement step 20 -->
<!-- Readability enhancement step 21 -->
<!-- Readability enhancement step 22 -->
<!-- Readability enhancement step 23 -->
<!-- Readability enhancement step 24 -->
<!-- Readability enhancement step 25 -->
<!-- Readability enhancement step 26 -->
