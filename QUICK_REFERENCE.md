# QuickFix - Quick Reference Guide

A quick reference for using the QuickFix service provider platform.

---

## 📋 Table of Contents
- [Default Login Credentials](#default-login-credentials)
- [Common Tasks - Admin](#common-tasks---admin)
- [Common Tasks - Customer](#common-tasks---customer)
- [Common Tasks - Provider](#common-tasks---provider)
- [Troubleshooting Quick Fixes](#troubleshooting-quick-fixes)

---

## 🔑 Default Login Credentials

All test accounts use password: **`password`**

| Role | Username | What You Can Do |
|------|----------|-----------------|
| **Admin** | admin | Manage everything |
| **Customer** | john_doe | Browse and book services |
| **Customer** | jane_smith | Browse and book services |
| **Customer** | mike_wilson | Browse and book services |
| **Provider** | bob_cleaner | Offer cleaning services |
| **Provider** | alice_plumber | Offer plumbing services |
| **Provider** | charlie_electric | Offer electrical services |
| **Provider** | david_gardener | Offer gardening services |

**Login URL**: `http://localhost/quickfix/auth/login.php`

---

## 👑 Common Tasks - Admin

### View System Statistics
1. Login as admin
2. Dashboard shows: Total users, providers, services, bookings
3. Scroll down for recent activities

### Add a New Service Category
1. Go to **Services** page
2. Click **Add Service** button
3. Fill in:
   - Service name (e.g., "Pest Control")
   - Description
   - Category (e.g., "Home Services")
   - Base price
4. Click **Save**

### Manage Users
1. Go to **Users** page
2. View all registered users
3. Options for each user:
   - **View Profile**: See user details
   - **Edit**: Change user information
   - **Activate/Deactivate**: Control account status
   - **Delete**: Remove user (careful!)

### View All Bookings
1. Go to **Bookings** page
2. See all bookings across platform
3. Filter by status: Pending, Confirmed, Completed, Cancelled
4. Click on any booking to see details

### Generate Reports
1. Go to **Reports** page
2. Select report type
3. Choose date range
4. Click **Generate**

---

## 👤 Common Tasks - Customer

### Browse Available Services
1. Login as customer
2. Click **Browse Services** in navigation
3. Use filters:
   - Search by name
   - Filter by category
4. Click on service card to see details

### Book a Service
1. Find the service you want
2. Click **Book Now** button
3. Fill in booking form:
   - Select date
   - Select time
   - Add any special notes
4. Click **Confirm Booking**
5. Wait for provider to confirm

### Check Booking Status
1. Go to **My Bookings**
2. See all your bookings with status:
   - **Pending**: Waiting for provider
   - **Confirmed**: Provider accepted
   - **In Progress**: Service being done
   - **Completed**: Service finished
   - **Cancelled**: Booking cancelled
3. Click booking to see full details

### Cancel a Booking
1. Go to **My Bookings**
2. Find the booking (must be Pending or Confirmed)
3. Click on booking
4. Click **Cancel Booking** button
5. Confirm cancellation

### Leave a Review
1. Go to **My Bookings**
2. Find a completed booking
3. Click **Leave Review** button
4. Rate the service (1-5 stars)
5. Write your feedback
6. Click **Submit Review**

### Add to Favorites
1. Browse services
2. Click the heart icon on any service
3. Access favorites from **Favorites** page
4. Quick book favorite services

### Update Profile
1. Click your name in top-right
2. Select **Profile Settings**
3. Update your information
4. Upload profile photo
5. Click **Save Changes**

---

## 🛠️ Common Tasks - Provider

### Add Services You Offer
1. Login as provider
2. Go to **Add Service**
3. Browse available service categories
4. Click **Add This Service** on services you offer
5. Set your custom price
6. Click **Add Service**

### View Your Services
1. Go to **My Services**
2. See all services you offer with:
   - Total bookings received
   - Total earnings
   - Availability status
3. Toggle availability with switch
4. Remove services if needed

### Accept/Decline Booking Requests
1. Go to **Bookings**
2. See all booking requests
3. For pending bookings:
   - Click **Accept** to confirm
   - Click **Decline** to reject
4. Customer will be notified

### Update Booking Status
1. Go to **Bookings**
2. Find the confirmed booking
3. When you start work:
   - Click **Mark as In Progress**
4. When you finish:
   - Click **Mark as Completed**

### Track Your Earnings
1. Go to **Earnings**
2. View:
   - Total earnings
   - Earnings by service
   - Earnings by month
3. Filter by date range
4. Export reports if needed

### Generate Invoice
1. Go to **Bookings**
2. Find a completed booking
3. Click **Generate Invoice**
4. Invoice shows:
   - Customer details
   - Service provided
   - Amount
   - Date
5. Print or download

### Update Your Profile
1. Click your name in top-right
2. Select **Profile Settings**
3. Update:
   - Contact information
   - Business name
   - Service area
4. Upload profile photo
5. Click **Save**

---

## 🔧 Troubleshooting Quick Fixes

### Can't Login
**Problem**: Login page shows error

**Solutions**:
- ✓ Check username/password (test accounts use "password")
- ✓ Clear browser cookies/cache
- ✓ Try different browser
- ✓ Verify database has user data

### Database Connection Error
**Problem**: "Connection error" message

**Solutions**:
- ✓ Start MySQL in XAMPP Control Panel
- ✓ Check `config/database.php` has correct credentials
- ✓ Verify database `quickfix_db` exists in phpMyAdmin
- ✓ Re-import SQL file if database is empty

### Page Not Found (404)
**Problem**: Browser shows 404 error

**Solutions**:
- ✓ Check files are in `htdocs/quickfix/` folder
- ✓ Verify Apache is running in XAMPP
- ✓ Try: `http://localhost/quickfix/index.php`
- ✓ Check file permissions (Mac/Linux)

### Styling Not Working
**Problem**: Page looks broken, no colors

**Solutions**:
- ✓ Clear browser cache (Ctrl+F5 or Cmd+Shift+R)
- ✓ Check if `assets/css/style.css` exists
- ✓ Open browser console (F12) to check for errors
- ✓ Verify file paths are correct

### Can't Upload Profile Photo
**Problem**: Profile photo upload fails

**Solutions**:
- ✓ Check `uploads/profile_photos/` folder exists
- ✓ Verify folder permissions (Mac/Linux: `chmod 777 uploads/`)
- ✓ Check file size (max 5MB)
- ✓ Use supported formats: JPG, PNG, GIF

### Bookings Not Showing
**Problem**: Bookings page is empty

**Solutions**:
- ✓ Import sample data from SQL file
- ✓ Create a test booking manually
- ✓ Check you're logged in as correct user type
- ✓ Verify bookings table exists in database

---

## 🎯 Quick Tips

### For Customers
- ⭐ Add services to favorites for quick access
- 📅 Book in advance for better availability
- 💬 Leave reviews to help other customers
- 📱 Platform works great on mobile!

### For Providers
- 💰 Set competitive prices to get more bookings
- ⚡ Respond quickly to booking requests
- ✅ Mark bookings as completed promptly
- 🌟 Good reviews lead to more customers

### For Admins
- 👀 Check dashboard regularly for overview
- 📊 Generate reports for business insights
- 🔒 Keep admin password secure
- 🛠️ Monitor system settings regularly

---

## 📱 Mobile Access

Access QuickFix on mobile:
1. Ensure phone is on same network as XAMPP
2. Find your computer's IP address
3. Access: `http://[YOUR-IP]/quickfix`
4. Login and use normally

Example: `http://192.168.1.100/quickfix`

---

## 🆘 Need More Help?

- 📖 **Full Documentation**: See [FEATURES.md](FEATURES.md)
- 🔧 **Setup Issues**: Check [SETUP_GUIDE.md](SETUP_GUIDE.md)
- ✅ **Installation Check**: Run `verify_installation.php`
- 📝 **Platform Info**: Read [README.md](README.md)

---

## ⌨️ Keyboard Shortcuts

| Shortcut | Action |
|----------|--------|
| `Ctrl+F5` (Win) / `Cmd+Shift+R` (Mac) | Hard refresh page |
| `F12` | Open browser developer tools |
| `Ctrl+U` | View page source |
| `Esc` | Close modal dialogs |

---

## 🔗 Quick Links

### For Everyone
- Homepage: `/quickfix/`
- Login: `/quickfix/auth/login.php`
- Register: `/quickfix/auth/register.php`

### Admin
- Dashboard: `/quickfix/admin/dashboard.php`
- Manage Users: `/quickfix/admin/users.php`
- Manage Services: `/quickfix/admin/services.php`
- View Bookings: `/quickfix/admin/bookings.php`

### Customer
- Dashboard: `/quickfix/user/dashboard.php`
- Browse Services: `/quickfix/user/services.php`
- My Bookings: `/quickfix/user/bookings.php`
- Favorites: `/quickfix/user/favorites.php`

### Provider
- Dashboard: `/quickfix/provider/dashboard.php`
- My Services: `/quickfix/provider/services.php`
- Add Service: `/quickfix/provider/add_service.php`
- Bookings: `/quickfix/provider/bookings.php`
- Earnings: `/quickfix/provider/earnings.php`

---

**Quick Tip**: Bookmark this page for easy reference!

**Last Updated**: October 2025
