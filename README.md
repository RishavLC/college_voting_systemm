# 🗳️ College Voting System

A secure and user-friendly web-based **College Voting System** developed using **Core PHP, MySQL, HTML, CSS, JavaScript, and Bootstrap**. This system allows colleges to conduct transparent Class Representative (CR) elections digitally while ensuring that each eligible student can vote only once through OTP verification.

---

## 📌 Features

### 👨‍💼 Admin Panel

- Secure Admin Login
- Import student data from Excel/CSV
- Prevent duplicate file imports
- Manage student records
- Mark students as:
  - Candidate
  - Present
  - Absent
- Create and manage election events
- Schedule one active election at a time
- View OTP requests
- Reset attendance status
- Manage election process

---

### 🎓 Student Panel

- Scan QR Code to access voting portal
- Verify identity using registered phone number
- Receive OTP via SMS
- OTP Verification
- Vote for eligible candidates
- One student can vote only once
- Secure voting process

---

## 🔒 Security Features

- OTP-based student verification
- Duplicate vote prevention
- Phone number verification
- Session management
- SQL Injection prevention using Prepared Statements
- Election scheduling control
- Candidate validation

---

## 🛠️ Technologies Used

| Technology | Description |
|------------|-------------|
| PHP | Backend Development |
| MySQL | Database |
| HTML5 | Structure |
| CSS3 | Styling |
| Bootstrap 5 | Responsive UI |
| JavaScript | Client-side Validation |
| XAMPP | Local Server |
| Git & GitHub | Version Control |

---

## 📂 Project Structure

```
College_Voting_System/
│
├── Admin/
│   ├── login.php
│   ├── home.php
│   ├── students.php
│   ├── events.php
│   ├── import_student.php
│   ├── mark_candidate.php
│   ├── mark_present.php
│   ├── mark_absent.php
│   ├── otp_requests.php
│   └── ...
│
├── Student/
│   ├── dashboard.php
│   ├── student_verify.php
│   ├── verify_otp.php
│   ├── vote.php
│   └── ...
│
├── Database/
│   ├── clz_voting_system.sql
│   ├── db_connect.php
│   ├── sms_config.php
│   └── ...
│
├── assets/
│   ├── css/
│   ├── img/
│   └── uploads/
│
├── result.php
└── README.md
```

---

## ⚙️ Installation

### 1. Clone the repository

```bash
git clone https://github.com/RishavLC/college_voting_systemm.git
```

### 2. Move the project

Copy the project into your **htdocs** folder.

```
C:\xampp\htdocs\
```

### 3. Start XAMPP

Start:

- Apache
- MySQL

### 4. Import Database

Open **phpMyAdmin**

Create a database named:

```
clz_voting_system
```

Import

```
Database/clz_voting_system.sql
```

### 5. Configure Database

Open

```
Database/db_connect.php
```

Update database credentials if necessary.

```php
$host = "localhost";
$user = "root";
$password = "";
$database = "clz_voting_system";
```

### 6. Configure SMS API

Edit

```
Database/sms_config.php
```

Add your SMS API credentials.

---

## 🚀 Running the Project

Open:

```
http://localhost/clz_voting_system/
```

Admin Login:

```
http://localhost/clz_voting_system/Admin/login.php
```

Student Portal:

```
http://localhost/clz_voting_system/Student/
```

---

## 📋 Voting Workflow

1. Admin imports student records.
2. Admin creates an election.
3. Admin marks candidates.
4. Admin marks students as present.
5. Student scans the election QR Code.
6. Student enters their registered phone number.
7. System sends an OTP.
8. Student verifies OTP.
9. Student casts a vote.
10. Vote is securely stored.
11. Student cannot vote again.

---

## 📷 Screenshots

You can add screenshots here.

```
screenshots/
│── admin-dashboard.png
│── student-verification.png
│── otp-verification.png
│── voting-page.png
│── results-page.png
```

---

## 🎯 Future Improvements

- Email Notifications
- Face Recognition Authentication
- Biometric Verification
- Real-time Live Results
- Audit Logs
- Multiple Election Support
- Faculty-wise Elections
- Mobile Application
- Analytics Dashboard

---

## 👨‍💻 Developer

**Rishav Shrestha**

Bachelor in Information Management (BIM)

GitHub:
https://github.com/RishavLC

---

## 📄 License

This project is developed for educational purposes and college election management.

---

## ⭐ Support

If you found this project useful, consider giving it a ⭐ on GitHub.

```
Made with ❤️ using PHP & MySQL
```