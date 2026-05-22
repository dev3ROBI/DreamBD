<div align="center">

# 🌟 DreamBD

### **Tournament | Social | E-commerce** — All in One Platform

[![PHP Version](https://img.shields.io/badge/PHP-8.0+-blue.svg?style=for-the-badge&logo=php)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-5.7+-orange.svg?style=for-the-badge&logo=mysql)](https://mysql.com)
[![JavaScript](https://img.shields.io/badge/JavaScript-ES6-yellow.svg?style=for-the-badge&logo=javascript)](https://javascript.com)
[![License](https://img.shields.io/badge/License-MIT-green.svg?style=for-the-badge)](LICENSE)

<p align="center">
  <img src="https://img.shields.io/badge/Tournament-Management-red" alt="Tournament">
  <img src="https://img.shields.io/badge/Social-Networking-blue" alt="Social">
  <img src="https://img.shields.io/badge/E-commerce-Product%20Selling-purple" alt="Ecommerce">
  <img src="https://img.shields.io/badge/PWA-Ready-green" alt="PWA">
</p>

**DreamBD** is a powerful all-in-one web platform that combines **Tournament Management**, **Social Networking**, and **E-commerce** in a single system. Engineered for high performance, security, and portability.

</div>

---

## ✨ Features

### 🏆 Tournament Management
- **Create & Manage:** Gaming (PUBG, Free Fire, etc.), Sports, and Quiz tournaments.
- **Dynamic Registration:** Team/Solo registration with automated fee handling.
- **Agent System:** Agents can create and fund their own tournaments.
- **Leaderboards:** Point-based ranking system for competitive play.

### 👥 Social Networking
- **Rich Profiles:** Personalized avatars, bios, location tracking, and social stats.
- **Interactive Feed:** Public/Friends privacy modes with reactions and nested comments.
- **Real-time Messaging:** Full messenger system with "Active Now" detection.
- **Live Notifications:** AJAX-powered alerts for social and system events.

### 🛍️ E-commerce & P2P
- **Product Marketplace:** Multi-category digital and physical product selling.
- **P2P Coin Trading:** Exchange bronze, silver, and gold coins between users.
- **Payment Verification:** Manual and automated (demo) payment processing.

---

## 🛠️ Tech Stack & Security

| Category | Technology |
|----------|------------|
| **Frontend** | HTML5, CSS3, Tailwind CSS, JavaScript (ES6), AJAX, jQuery |
| **Backend** | PHP 8.0+ (Core) |
| **Database** | MySQL (PDO with Prepared Statements) |
| **Config** | `.env` Environment Management |
| **PWA** | Web Manifest & Mobile optimization |

### 🔒 Security & Reliability
- **Automated Schema:** "Self-healing" database auto-migration on first run.
- **Encrypted Env:** Sensitive credentials managed via `.env` (not committed).
- **Time-Sync:** Unified MySQL Epoch tracking for accurate "Online" status.
- **Bug Audited:** Fully patched against critical vulnerabilities (XSS, SQLi, CSRF).

---

## 🚀 Quick Start (Live Deployment)

### 1️⃣ Prepare Environment
- PHP 8.0+ & MySQL 5.7+
- Copy `.env.example` to `.env`
- Set your `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASS`.

### 2️⃣ Zero-Config Database
DreamBD features an **Automated Schema System**. You do **not** need to import SQL manually.
1. Simply point your web server to the project root.
2. Visit `index.php` in your browser.
3. The system will automatically detect the missing database/tables and build the entire schema for you.

*Note: A backup `dream.sql` is still provided in the root for manual imports if preferred.*

---

## 📁 Project Structure
```text
Dream/
├── 📁 admin/        # Comprehensive Admin Dashboard
├── 📁 assets/       # PWA Icons, CSS, JS, and User Uploads
├── 📁 database/     # Config & Auto-Migration Logic
├── 📁 handlers/     # Core AJAX & Logic Handlers
├── 📁 includes/     # Reusable Components & Helpers
├── 📁 pages/        # Dynamic Page Templates
├── 📄 index.php     # Application Entry Point
├── 📄 dream.sql     # Unified Database Schema
└── 📄 .env.example  # Environment Template
```

---

## 🛠️ Setup for Developers

### Local Setup (XAMPP/WAMP)
1. Clone the repo to your `htdocs` or `www` folder.
2. Create a blank database named `dream`.
3. Configure your `.env` file.
4. Run the app!

### Deployment Notes
- Ensure the `assets/avatars`, `assets/posts`, and `assets/covers` folders have write permissions.
- Update `APP_URL` in `.env` for correct asset loading.

---

## 📞 Support & Community
**Developer:** Robiul Islam  
**GitHub:** [@dev3ROBI](https://github.com/dev3ROBI)  
**Project Health:** Check `bugs.txt` for the latest audit reports and fixed issues.

---
<div align="center">
⭐ If you like this project, give it a star! ⭐  
Made with ❤️ for the Bangladesh tech community
</div>
