# QuickFix - Features Documentation

## Overview

QuickFix is a complete service provider platform with three distinct user interfaces, each designed for specific user roles. This document provides detailed information about all available features.

---

## 🔐 Authentication System

### Features
- **Secure Login**: Password hashing using PHP's `password_hash()` with bcrypt
- **User Registration**: Separate registration flows for customers and providers
- **Email Verification**: Optional email verification system
- **Session Management**: Secure session-based authentication
- **Role-Based Access**: Automatic redirection based on user role (admin/customer/provider)
- **Logout**: Secure session termination

### Pages
- `auth/login.php` - Login page for all user types
- `auth/register.php` - Registration page with user type selection
- `auth/logout.php` - Logout handler
- `auth/forgot_password.php` - Password recovery (optional)
- `auth/verify_email.php` - Email verification (optional)

---

## 👑 Admin Interface

### Dashboard (`admin/dashboard.php`)
- **Statistics Overview**:
  - Total customers count
  - Total providers count
  - Total services available
  - Total bookings made
- **Recent Activities**: Latest bookings with status
- **Quick Actions**: Direct links to management pages

### User Management (`admin/users.php`)
- View all registered users (customers, providers, admins)
- Filter users by type
- View user details (profile, contact information)
- Activate/deactivate user accounts
- Change user roles
- Delete user accounts
- Search users by name, email, or username

### Service Management (`admin/services.php`)
- View all available services
- Add new service categories
- Edit service details (name, description, category, base price)
- Activate/deactivate services
- Delete services
- Upload service images
- View which providers offer each service

### Booking Management (`admin/bookings.php`)
- View all bookings across the platform
- Filter bookings by:
  - Status (pending, confirmed, in progress, completed, cancelled)
  - Date range
  - Customer or provider
- View booking details
- Update booking status
- Cancel bookings if needed
- View booking history

### Reports (`admin/reports.php`)
- Generate system reports
- View booking statistics
- Revenue reports
- User growth analytics
- Service popularity metrics

### Settings (`admin/settings.php`)
- Configure system settings
- Manage site information
- Set notification preferences
- Update platform configuration

### Profile (`admin/profile.php`)
- Update admin profile information
- Change password
- Upload profile photo
- Update contact details

---

## 👤 Customer/User Interface

### Dashboard (`user/dashboard.php`)
- **Welcome Message**: Personalized greeting
- **Quick Stats**:
  - Total bookings count
  - Pending bookings
  - Completed bookings
- **Featured Services**: Top 6 available services with providers
- **Recent Bookings**: Latest booking history
- **Quick Actions**: Browse services, view bookings

### Browse Services (`user/services.php`)
- View all available services
- Filter by category (Cleaning, Maintenance, Outdoor, etc.)
- Search services by name or description
- View service details:
  - Service name and description
  - Category
  - Provider information
  - Pricing
  - Provider ratings (if available)
- Add services to favorites
- Book services directly

### Book Service (`user/book_service.php`)
- Select service and provider
- Choose booking date and time
- Add special notes/instructions
- View total cost
- Confirm booking
- Receive booking confirmation

### My Bookings (`user/bookings.php`)
- View all personal bookings
- Filter by status:
  - Pending (awaiting provider confirmation)
  - Confirmed (accepted by provider)
  - In Progress (service being performed)
  - Completed (service finished)
  - Cancelled
- View booking details
- Cancel pending bookings
- Leave reviews for completed bookings
- Track booking status in real-time

### Booking Details (`user/booking_details.php`)
- Complete booking information
- Service details
- Provider information
- Date and time
- Status updates
- Action buttons (cancel, review)

### Favorites (`user/favorites.php`)
- View saved favorite services
- Quick access to preferred services
- Remove from favorites
- Book favorite services directly

### Reviews (`user/review.php`)
- Leave reviews for completed bookings
- Rate providers (1-5 stars)
- Write detailed feedback
- View own review history

### Profile (`user/profile.php`)
- Update personal information:
  - Full name
  - Email address
  - Phone number
  - Address
- Change password
- Upload profile photo
- View account creation date

---

## 🛠️ Provider Interface

### Dashboard (`provider/dashboard.php`)
- **Welcome Message**: Personalized greeting
- **Statistics**:
  - Total bookings received
  - Pending booking requests
  - Completed jobs
  - Total earnings
- **My Services**: Services offered with stats
- **Recent Bookings**: Latest booking requests
- **Quick Actions**: Add service, view earnings

### My Services (`provider/services.php`)
- View all services offered
- Each service shows:
  - Service name and category
  - Custom pricing
  - Availability status
  - Number of bookings received
  - Total earnings from service
- Toggle service availability (available/unavailable)
- Remove services from offerings
- Link to add new services

### Add Service (`provider/add_service.php`)
- Browse available service categories
- Select services to offer
- Set custom pricing (can differ from base price)
- Set initial availability
- Add multiple services at once
- View service suggestions based on category

### Bookings (`provider/bookings.php`)
- View all booking requests
- Filter by status:
  - Pending (new requests)
  - Confirmed (accepted bookings)
  - In Progress (currently working on)
  - Completed (finished jobs)
  - Cancelled
- Accept or decline pending bookings
- Update booking status:
  - Confirm accepted bookings
  - Mark as "In Progress" when starting work
  - Mark as "Completed" when finished
- View customer information
- View booking details (date, time, notes)
- Sort by date, status, or customer

### Update Booking Status (`provider/update_booking.php`)
- Change booking status
- Add notes or updates
- Notify customers of status changes

### Earnings (`provider/earnings.php`)
- View total earnings
- Earnings breakdown by:
  - Service type
  - Date range
  - Month
- View payment history
- Track completed vs pending payments
- Export earnings reports

### Invoice (`provider/invoice.php`)
- Generate invoices for completed bookings
- View invoice details:
  - Customer information
  - Service details
  - Amount charged
  - Date and time
  - Booking reference
- Print invoices
- Email invoices to customers (if configured)

### Profile (`provider/profile.php`)
- Update provider information:
  - Full name
  - Business name (optional)
  - Email and phone
  - Service area/address
- Change password
- Upload profile photo
- View provider statistics
- View average rating

---

## 🎨 UI/UX Design Features

### Consistent Layout
All pages share a unified design:

#### Header/Navigation
- **Logo**: QuickFix branding with icon
- **Navigation Menu**: Role-specific links
  - Admin: Dashboard, Users, Services, Bookings, Reports, Settings
  - Customer: Dashboard, Browse Services, My Bookings, Favorites
  - Provider: Dashboard, My Services, Add Service, Bookings, Earnings
- **User Profile Dropdown**:
  - Avatar/profile photo
  - User name and email
  - Profile settings link
  - Logout button

#### Content Area
- **Glassmorphism Design**: Translucent cards with blur effects
- **Gradient Backgrounds**: Beautiful color gradients
- **Responsive Grid**: Adapts to screen size
- **Cards and Panels**: Consistent card styling
- **Icons**: Font Awesome icons throughout

#### Footer
- Copyright information
- Platform tagline
- Consistent across all pages

### Visual Elements
- **Color Scheme**: 
  - Primary: Blue gradients (#4facfe, #00f2fe)
  - Secondary: Purple gradients (#f093fb, #f5576c)
  - Success: Green (#2ecc71)
  - Warning: Orange (#f39c12)
  - Danger: Red (#e74c3c)

- **Typography**:
  - Clean, modern fonts
  - Clear hierarchy
  - Readable sizes

- **Buttons**:
  - Primary: Blue gradient
  - Secondary: Gray with hover effects
  - Danger: Red for delete actions
  - Success: Green for confirmations

- **Forms**:
  - Clean input fields with focus effects
  - Inline validation messages
  - Submit buttons with loading states

### Responsive Design
- **Desktop**: Full layout with sidebar navigation
- **Tablet**: Adapted grid, collapsible sidebar
- **Mobile**: 
  - Hamburger menu
  - Stacked content
  - Touch-friendly buttons
  - Optimized images

---

## 🔒 Security Features

### Authentication & Authorization
- **Password Security**: BCrypt hashing (cost factor 10)
- **Session Management**: Secure PHP sessions with timeout
- **Role-Based Access Control**: Prevents unauthorized access
- **CSRF Protection**: (Can be enhanced with tokens)

### Data Protection
- **SQL Injection Prevention**: PDO prepared statements
- **XSS Prevention**: `htmlspecialchars()` for all outputs
- **Input Validation**: Server-side validation for all forms
- **File Upload Security**: Type and size restrictions

### Best Practices
- **Error Handling**: Graceful error messages (no sensitive info)
- **Database**: Separate credentials file
- **Sessions**: HTTPOnly and Secure flags (can be enabled)
- **Logout**: Complete session destruction

---

## 📊 Database Schema

### Tables

#### users
- `id`: Primary key
- `username`: Unique username
- `email`: Unique email address
- `password`: Hashed password
- `full_name`: User's full name
- `phone`: Contact number
- `address`: Physical address
- `user_type`: admin/user/provider
- `profile_image`: Profile photo filename
- `status`: active/inactive/pending
- `created_at`, `updated_at`: Timestamps

#### services
- `id`: Primary key
- `name`: Service name
- `description`: Service description
- `category`: Service category
- `base_price`: Default price
- `image`: Service image filename
- `status`: active/inactive
- `created_at`: Timestamp

#### provider_services
- Links providers to services they offer
- `id`: Primary key
- `provider_id`: Foreign key to users
- `service_id`: Foreign key to services
- `price`: Provider's custom price
- `availability`: available/unavailable

#### bookings
- `id`: Primary key
- `user_id`: Customer (foreign key)
- `provider_id`: Provider (foreign key)
- `service_id`: Service (foreign key)
- `booking_date`: Appointment date
- `booking_time`: Appointment time
- `status`: pending/confirmed/in_progress/completed/cancelled
- `total_amount`: Total cost
- `notes`: Special instructions
- `created_at`, `updated_at`: Timestamps

#### reviews
- `id`: Primary key
- `booking_id`: Related booking
- `user_id`: Customer who reviewed
- `provider_id`: Provider being reviewed
- `rating`: 1-5 stars
- `comment`: Review text
- `created_at`: Timestamp

#### user_favorites
- Customer's favorite services
- `id`: Primary key
- `user_id`: Customer
- `service_id`: Favorited service
- `provider_id`: Preferred provider
- `created_at`: Timestamp

#### system_settings
- Platform configuration
- `id`: Primary key
- `setting_key`: Setting name
- `setting_value`: Setting value
- `setting_type`: boolean/string/number
- `description`: Setting description
- `updated_at`: Timestamp

---

## 🚀 API Endpoints (AJAX)

### Status Updates
- `api/update_status.php`: Update booking status

### Provider Operations
- `provider/toggle_service.php`: Toggle service availability
- `provider/remove_service.php`: Remove service from offerings
- `provider/update_booking.php`: Update booking status
- `provider/get_invoice_details.php`: Fetch invoice data
- `provider/get_more_reviews.php`: Load more reviews

### User Operations
- `user/toggle_favorite.php`: Add/remove favorites
- `user/cancel_booking.php`: Cancel a booking
- `user/get_booking_details.php`: Fetch booking details

---

## 📱 Mobile Support

All features are fully responsive and work on:
- iOS (iPhone, iPad)
- Android (phones, tablets)
- Windows Mobile
- Mobile browsers

### Mobile-Optimized Features
- Touch-friendly buttons and forms
- Swipe navigation
- Responsive images
- Hamburger menu
- Simplified layouts for small screens

---

## 🔧 Customization

### Easy Customization Points

#### Colors (`assets/css/style.css`)
```css
:root {
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    /* Update these for custom colors */
}
```

#### Site Configuration (`config/config.php`)
```php
define('SITE_URL', 'http://localhost/quickfix');
define('SITE_NAME', 'QuickFix');
```

#### Database (`config/database.php`)
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'quickfix_db');
define('DB_USER', 'root');
define('DB_PASS', '');
```

---

## 📝 Future Enhancement Ideas

### Payment Integration
- Stripe or PayPal integration
- Payment history
- Refund management

### Notifications
- Email notifications
- SMS alerts
- Push notifications
- In-app notifications

### Advanced Features
- Real-time chat between customers and providers
- Calendar integration
- Google Maps for service area
- Multi-language support
- Advanced search and filters
- Provider verification badges
- Service packages/bundles
- Promotional codes and discounts
- Referral system

### Analytics
- Advanced reporting
- Revenue forecasting
- Customer behavior tracking
- Provider performance metrics

---

## 📞 Support

For technical support or questions:
1. Check the [SETUP_GUIDE.md](../SETUP_GUIDE.md) for installation help
2. Review the [README.md](../quickfix/README.md) for general information
3. Run `verify_installation.php` to diagnose issues

---

**Last Updated**: October 2025  
**Version**: 1.0  
**Platform**: QuickFix Service Provider Platform
