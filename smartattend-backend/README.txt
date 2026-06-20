================================================================
  SmartAttend — Backend Setup Guide
  PHP + MySQL 8.0 + XMPP (ejabberd)
================================================================

WHAT YOU NEED TO INSTALL
--------------------------
1. XAMPP (Apache + PHP + MySQL 8.0)
   Download: https://www.apachefriends.org

2. ejabberd (XMPP server)
   Download: https://www.ejabberd.im/download/

================================================================
  STEP 1 — SET UP MYSQL DATABASE
================================================================

A. Open XAMPP Control Panel → Start MySQL → Click "Shell"
   OR open Windows Start → search "MySQL 8.0 Command Line"

B. Login to MySQL:
   mysql -u root -p
   (press Enter if no password set)

C. Run the database file:
   source C:/xampp/htdocs/smartattend-backend/database.sql

   You should see:
   Query OK, 0 rows affected (database created)
   Query OK, 0 rows affected (tables created)
   Query OK, 3 rows affected (sample data inserted)

D. Verify it worked:
   USE smartattend;
   SHOW TABLES;

   You should see these 10 tables:
   +---------------------------+
   | departments               |
   | hods                      |
   | leave_requests            |
   | leave_subjects            |
   | mentors                   |
   | notifications             |
   | proof_documents           |
   | sessions                  |
   | students                  |
   | xmpp_messages             |
   +---------------------------+

E. Set real bcrypt passwords for demo users:
   (Run this in MySQL shell)

   UPDATE students SET password = '$2y$10$YourHashHere' WHERE usn = '4NM21CS042';

   To generate a bcrypt hash, create test_hash.php in htdocs:
   <?php echo password_hash('Password@123', PASSWORD_BCRYPT); ?>
   Then visit http://localhost/test_hash.php and copy the hash.

================================================================
  STEP 2 — SET UP PHP BACKEND IN XAMPP
================================================================

A. Copy the smartattend-backend folder to:
   C:/xampp/htdocs/smartattend-backend/

B. Open config/db.php and update:
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASS', '');          ← your MySQL password
   define('DB_NAME', 'smartattend');

C. Create upload folders with write permission:
   C:/xampp/htdocs/smartattend-backend/uploads/permissions/
   C:/xampp/htdocs/smartattend-backend/uploads/proofs/

D. Start Apache in XAMPP Control Panel

E. Test the API:
   Open browser → http://localhost/smartattend-backend/api/auth/login.php
   You should see: {"success":false,"message":"Only POST method allowed",...}
   That means PHP is working correctly.

================================================================
  STEP 3 — SET UP ejabberd (XMPP SERVER)
================================================================

A. Download ejabberd installer for Windows:
   https://www.ejabberd.im/download/
   Install it (default settings are fine for development)

B. ejabberd installs as a Windows Service.
   Start it: Open Services → find ejabberd → Start
   OR from command line:
   cd C:\ejabberd\bin
   ejabberdctl start

C. Open ejabberd admin panel:
   http://localhost:5280/admin
   Default login: admin / password

D. Create user accounts for your demo users:
   In the admin panel → Users → Add User

   Create these JIDs:
   arjun@localhost        (student)
   priya@localhost        (student)
   anitha@localhost       (mentor)
   ramesh.hod@localhost   (HOD)
   admin@localhost        (system bot — sends notifications)

   OR use ejabberdctl from command line:
   ejabberdctl register arjun    localhost Password@123
   ejabberdctl register anitha   localhost Password@123
   ejabberdctl register ramesh   localhost Password@123
   ejabberdctl register admin    localhost admin123

E. Enable REST API in ejabberd config:
   Open C:\ejabberd\conf\ejabberd.yml
   Find the 'listen:' section and make sure this is present:

   listen:
     -
       port: 5280
       module: ejabberd_http
       request_handlers:
         "/api": mod_http_api

   Add under 'modules:':
     mod_http_api: {}

   Restart ejabberd after saving.

F. Update config/db.php with your XMPP domain:
   define('XMPP_DOMAIN', 'localhost');    ← use localhost for dev
   define('XMPP_ADMIN',  'admin@localhost');
   define('XMPP_ADMIN_PASS', 'admin123');

G. Test XMPP is working:
   Visit: http://localhost:5280/api/status
   Should return: {"status":"success"}

================================================================
  STEP 4 — CONNECT FRONTEND TO BACKEND
================================================================

A. Copy the smartattend (frontend) folder to:
   C:/xampp/htdocs/smartattend/

B. In each JS file that calls the API, the base URL is:
   http://localhost/smartattend-backend/api/

C. Example: update js/auth.js doLogin() function:
   Replace the simulated login with a real fetch:

   async function doLogin() {
     const res = await fetch('http://localhost/smartattend-backend/api/auth/login.php', {
       method: 'POST',
       headers: { 'Content-Type': 'application/json' },
       credentials: 'include',
       body: JSON.stringify({
         usn_or_email: document.getElementById('loginUser').value,
         password:     document.getElementById('loginPass').value,
         role:         currentRole
       })
     });
     const data = await res.json();
     if (data.success) {
       // continue with dashboard setup
     } else {
       alert(data.message);
     }
   }

D. Open browser:
   http://localhost/smartattend/index.html

================================================================
  COMPLETE FOLDER STRUCTURE
================================================================

C:/xampp/htdocs/
│
├── smartattend/                     ← Frontend (your existing code)
│   ├── index.html
│   ├── css/
│   ├── js/
│   └── pages/
│
└── smartattend-backend/             ← Backend (this folder)
    │
    ├── database.sql                 ← Run this in MySQL first
    │
    ├── config/
    │   ├── db.php                   ← DB connection + helper functions
    │   └── xmpp.php                 ← XMPP notification functions
    │
    ├── api/
    │   ├── auth/
    │   │   ├── login.php            ← POST: login for all 3 roles
    │   │   ├── logout.php           ← POST: destroy session
    │   │   └── notifications.php    ← GET/POST: bell notifications
    │   │
    │   ├── student/
    │   │   ├── submit_leave.php     ← POST: submit leave + subjects + file
    │   │   ├── get_requests.php     ← GET: all requests for this student
    │   │   └── upload_proof.php     ← POST: upload proof after HOD approval
    │   │
    │   ├── mentor/
    │   │   ├── get_pending.php      ← GET: pending requests + proof queue
    │   │   ├── approve_reject.php   ← POST: approve or reject leave
    │   │   └── verify_proof.php     ← POST: verify or reject uploaded proof
    │   │
    │   └── hod/
    │       ├── get_pending.php      ← GET: mentor-approved + proof queue
    │       ├── approve_reject.php   ← POST: final leave approval
    │       └── verify_proof.php     ← POST: final proof verify → triggers attendance update
    │
    └── uploads/
        ├── permissions/             ← Permission letter files stored here
        └── proofs/                  ← Event proof files stored here

================================================================
  API REFERENCE SUMMARY
================================================================

AUTH
  POST /api/auth/login.php           Login (student/mentor/hod)
  POST /api/auth/logout.php          Logout
  GET  /api/auth/notifications.php   Get notifications
  POST /api/auth/notifications.php   Mark notifications read

STUDENT
  POST /api/student/submit_leave.php    Submit new leave request
  GET  /api/student/get_requests.php    Get my leave requests
  POST /api/student/upload_proof.php    Upload event proof

MENTOR
  GET  /api/mentor/get_pending.php      Get pending requests
  POST /api/mentor/approve_reject.php   Approve or reject leave
  POST /api/mentor/verify_proof.php     Verify or reject proof

HOD
  GET  /api/hod/get_pending.php         Get mentor-approved requests
  POST /api/hod/approve_reject.php      Approve or reject leave
  POST /api/hod/verify_proof.php        Final verify → updates attendance

================================================================
  XMPP MESSAGE FLOW SUMMARY
================================================================

  Student submits leave
    → XMPP message sent to Mentor JID
    → Notification saved in DB

  Mentor approves
    → XMPP message sent to HOD JID

  HOD approves
    → XMPP message sent to Student JID (provisional approval)

  Student uploads proof
    → XMPP message sent to Mentor JID

  Mentor verifies proof
    → XMPP message sent to HOD JID

  HOD verifies proof (FINAL)
    → XMPP message sent to each Faculty JID (attendance update)
    → XMPP message sent to Student JID (confirmation)
    → All subjects marked notified in DB

================================================================
  DEMO LOGIN CREDENTIALS
================================================================

  Role     | USN/Email              | Password
  ---------|------------------------|-------------
  Student  | 4NM21CS042             | Password@123
  Mentor   | anitha@college.edu     | Password@123
  HOD      | ramesh.hod@college.edu | Password@123

  NOTE: The database.sql inserts placeholder bcrypt hashes.
  You must regenerate real hashes using test_hash.php (see Step 1E)
  and UPDATE the passwords in MySQL before logging in.

================================================================
