<div align="center">

# 🌟 DreamBD

### **Tournament | Social | E-commerce** — All in One Platform

[![PHP Version](https://img.shields.io/badge/PHP-7.4+-blue.svg?style=for-the-badge&logo=php)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-5.7+-orange.svg?style=for-the-badge&logo=mysql)](https://mysql.com)
[![JavaScript](https://img.shields.io/badge/JavaScript-ES6-yellow.svg?style=for-the-badge&logo=javascript)](https://javascript.com)
[![License](https://img.shields.io/badge/License-MIT-green.svg?style=for-the-badge)](LICENSE)

<p align="center">
  <img src="https://img.shields.io/badge/Tournament-Management-red" alt="Tournament">
  <img src="https://img.shields.io/badge/Social-Networking-blue" alt="Social">
  <img src="https://img.shields.io/badge/E-commerce-Product%20Selling-purple" alt="Ecommerce">
</p>

**DreamBD** is a powerful all-in-one web platform that combines **Tournament Management**, **Social Networking**, and **E-commerce** in a single system. Built for Bangladeshi users but usable anywhere!

</div>

---

## ✨ Features

### 🏆 Tournament Management
| Feature | Description |
|---------|-------------|
| Create Tournaments | Gaming, Cricket, Football, Quiz, Chess |
| Registration | Team/Single with payment (bKash, Nagad, SSLCommerz) |
| Bracket System | Knockout, League, Round Robin |
| Live Scores | Real-time score updates |
| Certificates | Auto-generate winner certificates |

### 👥 Social Networking
| Feature | Description |
|---------|-------------|
| User Profiles | Avatar, bio, location, interests |
| Posts & Feed | Create, like, comment, share |
| Friend System | Send requests, accept, messaging |
| Notifications | Real-time alerts (AJAX) |

### 🛍️ E-commerce
| Feature | Description |
|---------|-------------|
| Product Management | Upload, edit, delete products |
| Seller Panel | Manage products & orders |
| Shopping Cart | Add to cart, wishlist |
| Payment Gateway | bKash, Nagad, SSLCommerz |
| Order Tracking | Delivery status updates |

### 👑 Admin Panel
- Complete dashboard with analytics
- User management (ban/unban, roles)
- Tournament approval & monitoring
- Order & payment verification
- Site settings (logo, email, terms)

---

## 🛠️ Tech Stack

<div align="center">

| Category | Technology |
|----------|------------|
| **Frontend** | HTML5, CSS3, Bootstrap 5, Tailwind, JavaScript, AJAX, jQuery |
| **Backend** | PHP (Core) |
| **Database** | MySQL |
| **Payment** | bKash, Nagad, SSLCommerz |
| **Charts** | Chart.js |
| **Security** | Prepared Statements, XSS Protection, CSRF Tokens |

</div>

---

## 📁 Project Structure
DreamBD/
├── 📁 admin/ # Admin panel (dashboard, users, tournaments)
├── 📁 assets/ # CSS, JS, images, fonts
├── 📁 database/ # Database backups & schema
├── 📁 handlers/ # Backend logic handlers
├── 📁 includes/ # Header, footer, functions
├── 📁 pages/ # All frontend pages
├── 📄 index.php # Homepage
├── 📄 dream.sql # Complete database dump
├── 📄 .env.example # Environment configuration
└── 📄 README.md # This file

text

---

## 🚀 Installation Guide

### Requirements
- XAMPP / WAMP / Laragon (PHP 7.4+)
- MySQL 5.7+
- Git (optional)

### Step-by-Step Setup

#### 1️⃣ Clone the Repository
```bash
git clone https://github.com/dev3ROBI/DreamBD.git
2️⃣ Move to htdocs
XAMPP: C:/xampp/htdocs/DreamBD/

WAMP: C:/wamp/www/DreamBD/

3️⃣ Import Database
bash
# Open phpMyAdmin
# Create database named 'dreambd'
# Import dream.sql file
4️⃣ Configure Environment
bash
# Copy .env.example to .env
cp .env.example .env

# Edit .env with your credentials
DB_HOST=localhost
DB_USER=root
DB_PASS=
DB_NAME=dreambd
5️⃣ Start Server
bash
# Start Apache & MySQL from XAMPP control panel
# Visit: http://localhost/DreamBD
🔐 Default Login Credentials
Role	Email	Password
Admin	admin@dreambd.com	admin123
User	user@dreambd.com	user123
Seller	seller@dreambd.com	seller123
📸 Screenshots
<div align="center">
[Add your screenshots here]

text
🏠 Homepage     |  🏆 Tournaments   |  👥 Social Feed
🛍️ Products     |  👑 Admin Panel    |  📱 Mobile View
</div>
🔒 Security Features
✅ Password hashing with password_hash()

✅ SQL Injection prevention (Prepared Statements)

✅ XSS protection (htmlspecialchars())

✅ Session management

✅ CSRF protection on forms

✅ Input validation & sanitization

🤝 Contributing
Contributions are welcome! Follow these steps:

Fork the repository

Create a new branch: git checkout -b feature/YourFeature

Commit changes: git commit -m 'Add YourFeature'

Push to branch: git push origin feature/YourFeature

Open a Pull Request

📞 Contact
<div align="center">
Developer: Robiul Islam
GitHub: @dev3ROBI
Project Link: https://github.com/dev3ROBI/DreamBD

</div>
📜 License
This project is licensed under the MIT License - see the LICENSE file for details.

<div align="center">
⭐ If you like this project, give it a star! ⭐
Made with ❤️ for the Bangladesh tech community

</div> ```