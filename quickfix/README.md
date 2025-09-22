# QuickFix - Local Service Provider Platform

A complete responsive web application for connecting customers with local service providers, built with PHP, MySQL, and modern glassmorphism design.

## Features

### 🎨 Modern Design
- **Glassmorphism UI**: Beautiful glass-effect design with gradient backgrounds
- **Fully Responsive**: Works perfectly on desktop, tablet, and mobile devices
- **Animated Elements**: Smooth animations and hover effects
- **Professional Layout**: Clean and intuitive user interface

### 👥 Multi-User System
- **Admin Panel**: Complete administrative control
- **Customer Dashboard**: Easy service booking and management
- **Provider Dashboard**: Service management and booking handling
- **Secure Authentication**: Password hashing and session management

### 🛠️ Service Management
- **Service Categories**: Organized service listings
- **Provider Profiles**: Detailed service provider information
- **Booking System**: Complete appointment scheduling
- **Review System**: Customer feedback and ratings
- **Real-time Status Updates**: Live booking status tracking

## Installation Guide

### Prerequisites
- **XAMPP** (Apache + MySQL + PHP 7.4+)
- Web browser (Chrome, Firefox, Safari, Edge)
- Text editor (optional for customization)

### Step 1: Download XAMPP
1. Visit [https://www.apachefriends.org/](https://www.apachefriends.org/)
2. Download XAMPP for your operating system
3. Install XAMPP with default settings
4. Start Apache and MySQL services

### Step 2: Setup Database
1. Open your browser and go to `http://localhost/phpmyadmin`
2. Click "New" to create a new database
3. Name it `quickfix_db`
4. Click "Import" tab
5. Choose the `database/quickfix_db.sql` file
6. Click "Go" to import the database structure and sample data

### Step 3: Install Website Files
1. Copy all website files to your XAMPP htdocs folder:
   ```
   C:\xampp\htdocs\quickfix\  (Windows)
   /Applications/XAMPP/htdocs/quickfix/  (Mac)
   /opt/lampp/htdocs/quickfix/  (Linux)
   ```

2. Your folder structure should look like:
   ```
   quickfix/
   ├── config/
   │   ├── config.php
   │   └── database.php
   ├── admin/
   │   ├── dashboard.php
   │   └── ...
   ├── user/
   │   ├── dashboard.php
   │   └── ...
   ├── provider/
   │   ├── dashboard.php
   │   └── ...
   ├── auth/
   │   ├── login.php
   │   ├── register.php
   │   └── logout.php
   ├── assets/
   │   ├── css/style.css
   │   └── js/main.js
   ├── database/
   │   └── quickfix_db.sql
   └── index.php
   ```

### Step 4: Configure Database Connection
1. Open `config/database.php`
2. Update database credentials if needed:
   ```php
   private $host = "localhost";
   private $db_name = "quickfix_db";
   private $username = "root";
   private $password = "";  // Usually empty for XAMPP
   ```

### Step 5: Access the Website
1. Open your browser
2. Navigate to: `http://localhost/quickfix`
3. You should see the QuickFix homepage

## Default Login Credentials

### Admin Access
- **Username**: `admin`
- **Password**: `password`
- **URL**: `http://localhost/quickfix/auth/login.php`

### Test the System
1. Register as a new customer or service provider
2. Book services as a customer
3. Manage bookings as a provider
4. Monitor everything as an admin

## File Structure Explained

### Configuration Files
- `config/config.php` - Main application settings
- `config/database.php` - Database connection class

### Authentication
- `auth/login.php` - User login page
- `auth/register.php` - User registration (customer/provider)
- `auth/logout.php` - Session termination

### User Dashboards
- `admin/dashboard.php` - Administrative panel
- `user/dashboard.php` - Customer interface
- `provider/dashboard.php` - Service provider panel

### Styling & Scripts
- `assets/css/style.css` - Complete glassmorphism styling
- `assets/js/main.js` - Interactive functionality

### Database
- `database/quickfix_db.sql` - Complete database structure with sample data

## Key Features Breakdown

### 🎯 Customer Features
- Browse and search services
- View provider profiles and ratings
- Book appointments with preferred providers
- Track booking status in real-time
- Leave reviews after service completion
- Manage personal profile and booking history

### 🛠️ Provider Features
- Create and manage service listings
- Set custom pricing for services
- Accept or decline booking requests
- Update service availability
- Track earnings and performance
- Manage customer communications

### 👑 Admin Features
- Complete user management (customers, providers, admins)
- Service category management
- Booking oversight and intervention
- System analytics and reporting
- Content management
- Platform configuration

## Customization Options

### Styling
- Edit `assets/css/style.css` to modify colors, gradients, and layouts
- CSS custom properties make theme changes easy
- Responsive breakpoints can be adjusted

### Functionality
- Add new service categories in the database
- Implement additional user roles
- Extend booking system with more fields
- Add payment gateway integration
- Implement email notifications

## Browser Compatibility
- ✅ Chrome 80+
- ✅ Firefox 75+
- ✅ Safari 13+
- ✅ Edge 80+
- ✅ Mobile browsers (iOS Safari, Chrome Mobile)

## Security Features
- Password hashing using PHP's password_hash()
- SQL injection prevention with prepared statements
- XSS protection with htmlspecialchars()
- Session-based authentication
- Role-based access control

## Troubleshooting

### Common Issues

**Database Connection Error**
- Ensure MySQL is running in XAMPP
- Check database credentials in `config/database.php`
- Verify database `quickfix_db` exists

**404 Errors**
- Ensure files are in the correct htdocs folder
- Check file permissions (Linux/Mac users)
- Verify Apache is running

**Styling Issues**
- Clear browser cache
- Check if `assets/css/style.css` exists
- Verify file paths in HTML

**Login Problems**
- Use default admin credentials: admin/password
- Check if users table has data
- Clear browser cookies and try again

## Support & Development

This is a complete, production-ready local service platform that can be extended with additional features like:

- Payment gateway integration
- SMS/Email notifications  
- Google Maps integration
- Advanced search and filtering
- Mobile app development
- Multi-language support

## License
Open source project - feel free to modify and use for your business needs.

---

**Created with ❤️ for local service communities**