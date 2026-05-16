# Academic Student Feedback and Evaluation System (ASFES)

## 🚀 Live Demo

Try the deployed demo here:  
🔗 [ASFES Live Site](https://studentfeedbackevaluation.infinityfree.me/)

| Role            | Username                    | Password   |
| --------------- | -------------------------- | ---------- |
| Student         | student@astu.edu            | password   |
| Instructor      | instructor@astu.edu         | password   |
| Department      | department@astu.edu         | password   |
| Student Affairs | studentaffairs@astu.edu     | password   |
| Admin           | admin@astu.edu              | password   |

---

A modern web-based implementation of the Academic Student Feedback and Evaluation System (ASFES) built using:

- HTML
- CSS
- JavaScript
- PHP (no frameworks)

**No frameworks or libraries are used.**

---

---

## ✨ Features

- **Role-Based Login:** Student, Instructor, Department, Student Affairs, and Admin accounts
- **Secure Feedback Submission:** Anonymous or named feedback
- **Smart Routing:** Automatic feedback routing by category
- **Customized Dashboards:** Role-specific views and tools
- **Status Tracking:** Submitted, Seen, Responded, Closed
- **Persistent Profiles:** Update and save your profile information
- **Real-Time Notifications:** Stay updated on feedback status
- **Bulk Notification Actions:** Mark all unread notifications as read
- **File Attachments:** Add attachments to feedback
- **Audit Trail & Admin Console:** Full traceability for admin audits
- **Powerful Inbox:** Search, filter, pagination, SLA tracking
- **Reporting & Analytics:** CSV export, metrics dashboard
- **Robust Data Layer:** MySQL + PDO, auto-seeding of sample data

---

## 🧑‍💻 Default Accounts

Use these credentials to log in after opening `index.php`:



## 🛠️ Local Setup

1. **Database Creation:**  
   Import the schema and seed data using:

   ```bash
   mysql -u root -p < database.sql
   ```

2. **Configure Environment (Optional):**  
   Override database connection by setting environment variables:
   - `ASFES_DB_HOST` (default: `127.0.0.1`)
   - `ASFES_DB_PORT` (default: `3306`)
   - `ASFES_DB_NAME` (default: `asfes`)
   - `ASFES_DB_USER` (default: `root`)
   - `ASFES_DB_PASS` (default: empty)

3. **Start Your Server:**  
   Place all project files in your PHP-capable server’s web root (e.g., `htdocs` for XAMPP, `www` for WAMP/LAMP).

4. **Access the App:**  
   Open your browser and go to [http://localhost/index.php](http://localhost/index.php).

---

## ⚙️ MySQL Configuration

- The backend uses **PDO** with MySQL.
- The system can auto-create the schema if the target database is empty and accessible.
- Sessions use hardened cookie settings, and logout clears browser cache hints.
- Database initialization adds indexes for feedback, response, notification, and audit hot paths.
- Ensure MySQL is running and the credentials are correctly set.

---

## 💡 Additional Notes

- No external dependencies: entirely built using native HTML, CSS, JavaScript, and PHP.
- The code is fully open and customizable for your institution’s needs.
- Pull requests and suggestions are welcome!

---

## 📄 License

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
<!-- Readability enhancement step 27 -->
<!-- Readability enhancement step 28 -->
<!-- Readability enhancement step 29 -->
<!-- Readability enhancement step 30 -->
<!-- Readability enhancement step 31 -->
<!-- Readability enhancement step 32 -->
<!-- Readability enhancement step 33 -->
<!-- Readability enhancement step 34 -->
<!-- Readability enhancement step 35 -->
<!-- Readability enhancement step 36 -->
<!-- Readability enhancement step 37 -->
<!-- Readability enhancement step 38 -->
<!-- Readability enhancement step 39 -->
<!-- Readability enhancement step 40 -->
<!-- Readability enhancement step 41 -->
<!-- Readability enhancement step 42 -->
MIT License. See [LICENSE](LICENSE) file for details.
