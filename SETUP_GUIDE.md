# QuickFix - Complete Setup Guide for XAMPP

This guide will walk you through setting up the QuickFix service provider platform on your local machine using XAMPP.

## Table of Contents
1. [System Requirements](#system-requirements)
2. [Download and Install XAMPP](#download-and-install-xampp)
3. [Setup the Database](#setup-the-database)
4. [Install QuickFix Files](#install-quickfix-files)
5. [Configure the Application](#configure-the-application)
6. [Access the Platform](#access-the-platform)
7. [Default Login Credentials](#default-login-credentials)
8. [Troubleshooting](#troubleshooting)

## System Requirements

- **Operating System**: Windows 7+, macOS 10.13+, or Linux
- **RAM**: Minimum 2GB (4GB recommended)
- **Disk Space**: Minimum 500MB free space
- **Browser**: Chrome 80+, Firefox 75+, Safari 13+, or Edge 80+

## Download and Install XAMPP

### Step 1: Download XAMPP

1. Visit the official XAMPP website: [https://www.apachefriends.org/](https://www.apachefriends.org/)
2. Download the appropriate version for your operating system:
   - **Windows**: Download the Windows installer (.exe)
   - **macOS**: Download the macOS installer (.dmg)
   - **Linux**: Download the Linux installer (.run)
3. Choose the latest version with PHP 7.4 or higher

### Step 2: Install XAMPP

#### Windows Installation:
1. Run the downloaded `.exe` file as Administrator
2. If Windows Firewall or antivirus shows a warning, allow the installation
3. Click "Next" through the installation wizard
4. Choose installation directory (default: `C:\xampp\`)
5. Select components (ensure Apache, MySQL, PHP, and phpMyAdmin are checked)
6. Complete the installation

#### macOS Installation:
1. Open the downloaded `.dmg` file
2. Drag XAMPP to the Applications folder
3. Open Applications and double-click on XAMPP
4. If prompted, allow installation from unidentified developers in System Preferences

#### Linux Installation:
1. Make the downloaded file executable:
   ```bash
   chmod +x xampp-linux-x64-*-installer.run
   ```
2. Run the installer with sudo:
   ```bash
   sudo ./xampp-linux-x64-*-installer.run
   ```
3. Follow the on-screen instructions

### Step 3: Start XAMPP Services

#### Windows:
1. Open XAMPP Control Panel from Start Menu
2. Click "Start" next to Apache
3. Click "Start" next to MySQL
4. Both should show green "Running" status

#### macOS:
1. Open XAMPP from Applications
2. Click "Start" on the General tab
3. Verify Apache and MySQL are running on the Services tab

#### Linux:
```bash
sudo /opt/lampp/lampp start
```

## Setup the Database

### Step 1: Access phpMyAdmin

1. Open your web browser
2. Navigate to: `http://localhost/phpmyadmin`
3. You should see the phpMyAdmin interface

### Step 2: Create the Database

**Option A: Import the Complete SQL File (Recommended)**
1. In phpMyAdmin, click on "Import" in the top menu
2. Click "Choose File"
3. Navigate to and select: `quickfix/database/quickfix_db.sql`
4. Scroll down and click "Go"
5. Wait for the import to complete
6. You should see a success message

**Option B: Manual Creation**
1. Click "New" in the left sidebar
2. Enter database name: `quickfix_db`
3. Click "Create"
4. Select the newly created database
5. Click "Import" and import the SQL file as described above

### Step 3: Verify Database Setup

1. In phpMyAdmin, click on `quickfix_db` in the left sidebar
2. You should see the following tables:
   - users
   - services
   - provider_services
   - bookings
   - reviews
   - user_favorites
   - system_settings
3. Click on the "users" table and browse to verify sample data is loaded

## Install QuickFix Files

### Step 1: Locate XAMPP htdocs Directory

The htdocs directory is where your web files should be placed:

- **Windows**: `C:\xampp\htdocs\`
- **macOS**: `/Applications/XAMPP/htdocs/`
- **Linux**: `/opt/lampp/htdocs/`

### Step 2: Copy QuickFix Files

1. Copy the entire `quickfix` folder to the htdocs directory
2. Your structure should look like:
   ```
   htdocs/
   └── quickfix/
       ├── admin/
       ├── assets/
       ├── auth/
       ├── config/
       ├── database/
       ├── provider/
       ├── user/
       ├── index.php
       └── README.md
   ```

### Step 3: Set Permissions (Linux/macOS only)

```bash
# Navigate to htdocs
cd /Applications/XAMPP/htdocs/quickfix  # macOS
# or
cd /opt/lampp/htdocs/quickfix          # Linux

# Set proper permissions
chmod -R 755 .
chmod -R 777 uploads/
```

## Configure the Application

### Step 1: Verify Database Connection

1. Open the file: `quickfix/config/database.php`
2. Verify the database settings (default XAMPP configuration):
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'quickfix_db');
   define('DB_USER', 'root');
   define('DB_PASS', '');  // Empty for default XAMPP
   ```
3. If you changed MySQL password during XAMPP setup, update `DB_PASS`

### Step 2: Verify Site Configuration

1. Open the file: `quickfix/config/config.php`
2. Check the site URL setting:
   ```php
   define('SITE_URL', 'http://localhost/quickfix');
   ```
3. If you placed the files in a different folder, update accordingly

### Step 3: Create Upload Directories

1. Navigate to `quickfix/uploads/`
2. Ensure the following subdirectories exist:
   - `profile_photos/`
   - `service_images/`
3. If they don't exist, create them

## Access the Platform

### Step 1: Open the Homepage

1. Open your web browser
2. Navigate to: `http://localhost/quickfix`
3. You should see the QuickFix homepage

### Step 2: Test the Installation

1. The homepage should display:
   - QuickFix branding
   - Navigation menu
   - Featured services
   - Login/Register buttons
2. Check that the styling loads correctly (glassmorphism design)

## Default Login Credentials

QuickFix comes with pre-configured test accounts for each user type:

### Admin Account
- **Username**: `admin`
- **Email**: `admin@quickfix.com`
- **Password**: `password`
- **URL**: `http://localhost/quickfix/auth/login.php`
- **Capabilities**: 
  - Manage all users
  - Manage all services
  - View and manage all bookings
  - Access system settings

### Customer/User Accounts
| Username | Email | Password |
|----------|-------|----------|
| john_doe | john.doe@email.com | password |
| jane_smith | jane.smith@email.com | password |
| mike_wilson | mike.wilson@email.com | password |

**Customer Capabilities**:
- Browse available services
- Book services with providers
- View booking history
- Leave reviews
- Manage profile

### Service Provider Accounts
| Username | Email | Password |
|----------|-------|----------|
| bob_cleaner | bob.cleaner@quickfix.com | password |
| alice_plumber | alice.plumber@quickfix.com | password |
| charlie_electric | charlie.electric@quickfix.com | password |
| david_gardener | david.gardener@quickfix.com | password |

**Provider Capabilities**:
- Add and manage service offerings
- View incoming bookings
- Accept/decline booking requests
- Update booking status
- Track earnings
- Manage profile

## Troubleshooting

### Database Connection Error

**Problem**: "Connection error: SQLSTATE[HY000] [1049] Unknown database"

**Solutions**:
1. Ensure MySQL is running in XAMPP Control Panel
2. Verify database name is `quickfix_db` in phpMyAdmin
3. Check `config/database.php` has correct database name
4. Try re-importing the SQL file

### Apache Won't Start

**Problem**: Apache service fails to start in XAMPP

**Solutions**:
1. **Port Conflict**: Another program (often Skype, IIS) is using port 80
   - Option A: Stop the conflicting program
   - Option B: Change Apache port in XAMPP Config
2. **Permission Issues**: Run XAMPP as Administrator (Windows)
3. Check Apache error logs in XAMPP Control Panel

### MySQL Won't Start

**Problem**: MySQL service fails to start

**Solutions**:
1. **Port Conflict**: Another MySQL instance is running on port 3306
2. **Permission Issues**: Run XAMPP as Administrator
3. Check MySQL error logs in XAMPP Control Panel
4. Restart your computer and try again

### 404 Not Found Error

**Problem**: Browser shows "404 Not Found" when accessing `http://localhost/quickfix`

**Solutions**:
1. Verify files are in correct directory (htdocs/quickfix/)
2. Check file permissions (Linux/macOS)
3. Ensure Apache is running
4. Try accessing: `http://localhost/quickfix/index.php` directly
5. Clear browser cache

### Styling Not Loading

**Problem**: Page appears but without proper styling

**Solutions**:
1. Check if `assets/css/style.css` file exists
2. Clear browser cache (Ctrl+F5 or Cmd+Shift+R)
3. Verify file paths in HTML are correct
4. Check browser console (F12) for 404 errors

### Login Issues

**Problem**: Cannot login with default credentials

**Solutions**:
1. Verify database was imported correctly
2. Check if users table has data in phpMyAdmin
3. Clear browser cookies and try again
4. Ensure correct password: `password`
5. Try different username/email: `admin` or `admin@quickfix.com`

### Blank White Page

**Problem**: Page loads but shows nothing (blank white page)

**Solutions**:
1. Enable PHP error reporting:
   - Open `config/config.php`
   - Add at the top after `<?php`:
     ```php
     error_reporting(E_ALL);
     ini_set('display_errors', 1);
     ```
2. Check Apache/PHP error logs in XAMPP
3. Verify PHP version is 7.4 or higher

### Upload Issues

**Problem**: Cannot upload profile photos

**Solutions**:
1. Check `uploads/` directory exists
2. Verify folder permissions (Linux/macOS):
   ```bash
   chmod -R 777 uploads/
   ```
3. Check PHP upload settings in `php.ini`:
   - `upload_max_filesize = 10M`
   - `post_max_size = 10M`

## Next Steps

After successful installation:

1. **Login as Admin**: Test the admin dashboard and explore features
2. **Login as Customer**: Browse services and make a test booking
3. **Login as Provider**: Check the provider interface and booking management
4. **Customize**: Edit `config/config.php` for site settings
5. **Style**: Modify `assets/css/style.css` for design changes
6. **Add Content**: Add more services, categories, and users as needed

## Security Notes

⚠️ **Important for Production Use**:

1. **Change Default Passwords**: All sample accounts use "password" - change these immediately
2. **Database Credentials**: Set a strong MySQL root password
3. **File Permissions**: Restrict permissions in production (755 for directories, 644 for files)
4. **HTTPS**: Use SSL certificate for production deployment
5. **Error Display**: Disable error display in production (`display_errors = Off` in php.ini)

## Support

If you encounter issues not covered in this guide:

1. Check the main README.md file
2. Review PHP error logs in XAMPP
3. Verify all prerequisites are met
4. Ensure you followed each step in order

---

**Created with ❤️ for local service communities**

Last Updated: October 2025
