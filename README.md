# QuickFix - Service Provider Platform

A complete web-based service provider platform built with PHP, MySQL, and modern UI/UX design. QuickFix connects customers with local service providers for various services including home cleaning, plumbing, electrical work, gardening, and more.

## 🌟 Key Features

### Three User Interfaces

1. **Admin Interface**
   - Complete user management (customers, providers, admins)
   - Service and category management
   - Booking oversight and management
   - System analytics and reporting
   - Platform configuration

2. **Customer Interface**
   - Browse and search available services
   - Book services with preferred providers
   - View and manage booking history
   - Track booking status in real-time
   - Leave reviews and ratings
   - Manage personal profile

3. **Provider Interface**
   - Add and manage service offerings
   - Set custom pricing for services
   - View and manage booking requests
   - Accept or decline bookings
   - Update booking status
   - Track earnings and performance
   - Manage profile and availability

### Technical Features

- **Backend**: PHP 7.4+ with PDO for database operations
- **Database**: MySQL via phpMyAdmin
- **Authentication**: Secure password hashing and session-based authentication
- **Security**: SQL injection prevention, XSS protection, role-based access control
- **UI/UX**: Modern glassmorphism design with Bootstrap styling
- **Responsive**: Works on desktop, tablet, and mobile devices
- **Consistent Layout**: Unified header, navigation, and footer across all pages

## 🚀 Quick Start

### Prerequisites

- XAMPP (Apache + MySQL + PHP 7.4+)
- Web browser (Chrome, Firefox, Safari, or Edge)

### Installation

1. **Download and Install XAMPP**
   - Download from [apachefriends.org](https://www.apachefriends.org/)
   - Install and start Apache and MySQL services

2. **Setup Database**
   - Open `http://localhost/phpmyadmin`
   - Import `quickfix/database/quickfix_db.sql`

3. **Copy Files**
   - Copy the `quickfix` folder to your XAMPP htdocs directory

4. **Access Platform**
   - Navigate to `http://localhost/quickfix`

For detailed setup instructions, see [SETUP_GUIDE.md](SETUP_GUIDE.md)

## 🔑 Default Login Credentials

All test accounts use password: `password`

| User Type | Username | Features |
|-----------|----------|----------|
| **Admin** | admin | Full system access |
| **Customer** | john_doe | Browse & book services |
| **Provider** | bob_cleaner | Manage services & bookings |

See [SETUP_GUIDE.md](SETUP_GUIDE.md) for complete list of test accounts.

## 📁 Project Structure

```
quickfix/
├── admin/              # Admin interface pages
├── assets/            # CSS, JS, and static files
│   ├── css/
│   └── js/
├── auth/              # Authentication pages (login, register, logout)
├── config/            # Configuration files
│   ├── config.php     # Main configuration
│   └── database.php   # Database connection
├── database/          # SQL database files
│   └── quickfix_db.sql
├── provider/          # Provider interface pages
├── user/              # Customer interface pages
├── uploads/           # User uploaded files
└── index.php          # Homepage
```

## 🛠️ Technology Stack

- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+
- **Frontend**: HTML5, CSS3, JavaScript
- **Styling**: Custom CSS with glassmorphism effects
- **Icons**: Font Awesome 6.0
- **Server**: Apache (via XAMPP)

## 📖 Documentation

- [SETUP_GUIDE.md](SETUP_GUIDE.md) - Complete XAMPP installation and setup guide
- [quickfix/README.md](quickfix/README.md) - Detailed platform features and customization options

## 🔒 Security Features

- Password hashing using PHP's `password_hash()`
- SQL injection prevention with PDO prepared statements
- XSS protection with `htmlspecialchars()`
- Session-based authentication
- Role-based access control (RBAC)

## 🎨 UI/UX Design

- **Consistent Layout**: All pages share the same structure
  - Header with navigation
  - Main content area
  - User profile dropdown
- **Modern Design**: Glassmorphism effects with gradient backgrounds
- **Responsive**: Mobile-first approach with responsive breakpoints
- **Accessible**: Clean, intuitive interface for all user types

## 📝 Database Schema

The platform uses a comprehensive database schema:

- **users** - Admin, customer, and provider accounts
- **services** - Available service categories
- **provider_services** - Provider-specific service offerings with custom pricing
- **bookings** - Service booking records
- **reviews** - Customer reviews and ratings
- **user_favorites** - Customer favorite services
- **system_settings** - Platform configuration

## 🌐 Browser Compatibility

- ✅ Chrome 80+
- ✅ Firefox 75+
- ✅ Safari 13+
- ✅ Edge 80+
- ✅ Mobile browsers

## 📋 Requirements Met

✅ **Platform Setup**
- Works on XAMPP server
- Uses phpMyAdmin for database management

✅ **Backend**
- PHP for all backend logic
- MySQL database with complete schema

✅ **Three Interfaces**
- Admin: User management, service management, booking oversight
- Customer: Browse services, book services, view bookings
- Provider: View bookings, update status, manage services

✅ **Authentication**
- Secure login for each interface type
- Role-based access control

✅ **UI/UX**
- Consistent MVP design across all pages
- Shared header with navigation
- Shared footer
- Bootstrap-based responsive styling
- Clean, professional appearance

✅ **Basic Operations**
- CRUD operations for users, services, and bookings
- Booking workflow (create, confirm, complete, cancel)
- Service management by providers
- Review and rating system

## 🚧 Troubleshooting

Common issues and solutions are documented in [SETUP_GUIDE.md](SETUP_GUIDE.md#troubleshooting).

## 📄 License

Open source project - free to use and modify for your business needs.

## 🤝 Contributing

This is a complete, production-ready platform. Feel free to:
- Add new features (payment integration, email notifications)
- Customize the design
- Extend functionality
- Report issues

---

**Created with ❤️ for local service communities**