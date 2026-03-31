# 💊 MediCare — Smart Medicine Reminder App

A full-featured medicine reminder web app with login, dashboard, reminders, adherence tracking, and a health diary.

---

## 📁 File Structure

```
medicine_reminder/
├── index.html          ← Login & Register page
├── dashboard.html      ← Main app (all pages in one)
├── config.php          ← Database config & helpers
├── auth.php            ← Login / Register / Profile API
├── medicines.php       ← Medicines CRUD + Logs + Stats API
├── database.sql        ← MySQL schema (run this first!)
└── README.md           ← This file
```

---

## 🚀 Setup Instructions

### 1. Requirements
- PHP 7.4+ (with PDO and PDO_MySQL)
- MySQL 5.7+ or MariaDB
- Apache/Nginx (XAMPP, WAMP, MAMP, or Laragon)

### 2. Create the Database
1. Open **phpMyAdmin** → http://localhost/phpmyadmin
2. Click **"Import"** tab
3. Choose the `database.sql` file
4. Click **"Go"** — this creates the `medicine_reminder` database and all tables

### 3. Configure Database
Edit `config.php` and update your credentials:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // Your MySQL username
define('DB_PASS', '');           // Your MySQL password
define('DB_NAME', 'medicine_reminder');
```

### 4. Place Files
Copy all files into your web server's root:
- **XAMPP**: `C:/xampp/htdocs/medicine_reminder/`
- **WAMP**:  `C:/wamp64/www/medicine_reminder/`
- **Linux**: `/var/www/html/medicine_reminder/`

### 5. Open the App
Visit: **http://localhost/medicine_reminder/index.html**

---

## ✨ Features

### 🔐 Authentication
- Secure login & registration with bcrypt password hashing
- Session-based authentication
- Profile update and password change

### 💊 Medicine Management
- Add/Edit/Delete medicines
- Types: Tablet, Capsule, Syrup, Injection, Drops, Inhaler, Cream
- Frequency: Once/Twice/Thrice daily, Every 4h/6h/8h, Weekly, Custom
- Multiple reminder times per medicine
- Color tags for easy identification
- Activate/Deactivate medicines
- Start and end dates

### 📋 Today's Schedule
- See all doses scheduled for today
- Mark doses as **Taken** or **Skipped**
- Real-time badge showing pending doses
- Instructions displayed per dose

### 📊 Dashboard & Analytics
- Active medicine count
- 30-day adherence rate with circular progress ring
- Day streak tracker 🔥
- Taken/Skipped/Missed breakdown with bars

### 📓 Health Diary
- Log daily health notes
- Mood tracking (Great / Good / Okay / Bad)
- View history of entries

### 📈 History Logs
- Filter by 7, 14, or 30 days
- Color-coded status badges
- Timestamped dose logs

### 👤 Profile
- Update personal info (name, phone, DOB, gender)
- Change password securely

---

## 🔧 Troubleshooting

**"Database connection failed"**  
→ Check DB credentials in `config.php`  
→ Make sure MySQL is running

**"Unauthorized" / redirects to login**  
→ PHP sessions need cookies — use `http://localhost/...` not `file://`

**Blank page**  
→ Enable PHP error reporting: add `error_reporting(E_ALL); ini_set('display_errors',1);` at the top of `config.php`

**CORS errors**  
→ Make sure you're accessing via localhost, not opening HTML files directly

---

## 🛡️ Security Features
- Passwords hashed with bcrypt (PHP `password_hash`)
- PDO prepared statements (SQL injection safe)
- Session-based auth with httpOnly cookies
- Input validation on all endpoints

---

Built with ❤️ — HTML, CSS, JavaScript, PHP & MySQL
"# smartmedical" 
