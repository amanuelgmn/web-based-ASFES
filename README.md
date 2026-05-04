# Academic Student Feedback and Evaluation System (ASFES)

A modern web-based implementation of the Academic Student Feedback and Evaluation System (ASFES) built using:

- HTML
- CSS
- JavaScript
- PHP (no frameworks)

**No frameworks or libraries are used.**

---

## 🚀 Live Demo

Try the deployed demo here:  
🔗 [ASFES Live Site](https://studentfeedbackevaluation.infinityfree.me/)

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

| Role            | Username                    | Password   |
| --------------- | -------------------------- | ---------- |
| Student         | student@astu.edu            | password   |
| Instructor      | instructor@astu.edu         | password   |
| Department      | department@astu.edu         | password   |
| Student Affairs | studentaffairs@astu.edu     | password   |
| Admin           | admin@astu.edu              | password   |

---

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

MIT License. See [LICENSE](LICENSE) file for details.
