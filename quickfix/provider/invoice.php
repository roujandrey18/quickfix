<?php 
require_once '../config/config.php';
checkAccess(['provider']);

$database = new Database();
$db = $database->getConnection();

$provider_id = $_SESSION['user_id'];

// Get provider data including profile image
$user_query = "SELECT * FROM users WHERE id = :provider_id";
$user_stmt = $db->prepare($user_query);
$user_stmt->bindParam(':provider_id', $provider_id);
$user_stmt->execute();
$provider = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Handle search and filters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build query conditions
$where_conditions = ["b.provider_id = :provider_id"];
$params = [':provider_id' => $provider_id];

if (!empty($search)) {
    $where_conditions[] = "(u.full_name LIKE :search OR s.name LIKE :search OR b.id = :search_id)";
    $params[':search'] = "%$search%";
    $params[':search_id'] = is_numeric($search) ? $search : 0;
}

if (!empty($status_filter)) {
    $where_conditions[] = "b.status = :status";
    $params[':status'] = $status_filter;
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(b.created_at) >= :date_from";
    $params[':date_from'] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(b.created_at) <= :date_to";
    $params[':date_to'] = $date_to;
}

$where_clause = implode(' AND ', $where_conditions);

// Get completed bookings (invoices)
$query = "SELECT b.*, s.name as service_name, u.full_name as customer_name, 
                 u.email as customer_email, u.phone as customer_phone,
                 u.address as customer_address, s.category
          FROM bookings b 
          JOIN services s ON b.service_id = s.id 
          JOIN users u ON b.user_id = u.id 
          WHERE $where_clause
          ORDER BY b.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get summary statistics
$stats_query = "SELECT 
    COUNT(*) as total_invoices,
    SUM(CASE WHEN status = 'completed' THEN total_amount ELSE 0 END) as total_revenue,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_jobs,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_jobs,
    AVG(CASE WHEN status = 'completed' THEN total_amount ELSE NULL END) as avg_invoice_value
    FROM bookings 
    WHERE provider_id = :provider_id";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->bindParam(':provider_id', $provider_id);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Calculate commission (assuming 10% platform fee)
$platform_fee_rate = 0.10;
$total_earnings = $stats['total_revenue'] * (1 - $platform_fee_rate);
$platform_fees = $stats['total_revenue'] * $platform_fee_rate;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Management - QuickFix Provider</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');

        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --accent-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --dark-gradient: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            --glass-bg: rgba(255, 255, 255, 0.25);
            --glass-border: rgba(255, 255, 255, 0.18);
            --text-dark: #2c3e50;
            --text-light: #ecf0f1;
            --shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
            --shadow-hover: 0 12px 40px 0 rgba(31, 38, 135, 0.5);
            --navbar-height: 65px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--primary-gradient);
            min-height: 100vh;
            color: var(--text-dark);
            overflow-x: hidden;
            line-height: 1.6;
            position: relative;
            padding-top: var(--navbar-height);
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 50%, rgba(120, 119, 198, 0.3) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255, 119, 198, 0.3) 0%, transparent 50%),
                radial-gradient(circle at 40% 80%, rgba(120, 219, 255, 0.3) 0%, transparent 50%);
            z-index: -2;
            animation: backgroundShift 20s ease-in-out infinite;
            pointer-events: none;
        }

        @keyframes backgroundShift {
            0%, 100% { transform: scale(1) rotate(0deg); }
            50% { transform: scale(1.1) rotate(5deg); }
        }

        .navbar {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--glass-border);
            padding: 0.75rem 0;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            width: 100%;
            height: var(--navbar-height);
            z-index: 1000;
            display: flex;
            align-items: center;
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            height: 100%;
        }

        .nav-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: 700;
            background: var(--accent-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-decoration: none;
            z-index: 1001;
            white-space: nowrap;
        }

        .provider-info {
            display: flex;
            flex-direction: column;
            margin-left: 1rem;
        }

        .provider-name {
            font-weight: 600;
            font-size: 0.95rem;
            color: var(--text-dark);
        }

        .provider-role {
            font-size: 0.8rem;
            opacity: 0.8;
            color: var(--text-dark);
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-link {
            padding: 0.5rem 1rem;
            border-radius: 10px;
            text-decoration: none;
            color: var(--text-dark);
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-dark);
        }

        .nav-link.active {
            background: var(--accent-gradient);
            color: white;
        }

        .nav-profile {
            position: relative;
        }

        .profile-dropdown .profile-btn {
            background: none;
            border: none;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.4rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-size: 0.8rem;
        }

        .profile-dropdown .profile-btn:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .profile-img {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .dropdown-menu {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 15px;
            box-shadow: var(--shadow);
            margin-top: 0.5rem;
            min-width: 200px;
            max-width: 250px;
            z-index: 1000;
        }

        .dropdown-item {
            color: var(--text-dark);
            padding: 0.75rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transition: all 0.3s ease;
        }

        .dropdown-item:hover {
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-dark);
        }

        .dropdown-item i {
            width: 16px;
            text-align: center;
            opacity: 0.8;
        }

        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
            position: relative;
            z-index: 1;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .header-icon {
            width: 60px;
            height: 60px;
            background: var(--accent-gradient);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            box-shadow: var(--shadow);
        }

        .header-text .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-dark);
            margin: 0;
            line-height: 1.2;
        }

        .header-text .page-subtitle {
            color: var(--text-dark);
            opacity: 0.8;
            margin: 0.5rem 0 0 0;
            font-size: 1rem;
        }

        .header-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .header-actions .btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            border-radius: 12px;
            transition: all 0.3s ease;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 10px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
        }

        .btn-primary {
            background: var(--accent-gradient);
            color: white;
            box-shadow: var(--shadow);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
            color: white;
        }

        .btn-outline-primary {
            background: transparent;
            color: #4facfe;
            border: 1px solid #4facfe;
        }

        .btn-outline-primary:hover {
            background: #4facfe;
            color: white;
        }

        .btn-outline-secondary {
            background: transparent;
            color: var(--text-dark);
            border: 1px solid rgba(44, 62, 80, 0.3);
        }

        .btn-outline-secondary:hover {
            background: var(--text-dark);
            color: white;
        }

        .btn-secondary {
            background: rgba(108, 117, 125, 0.8);
            color: white;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-info {
            background: #17a2b8;
            color: white;
        }

        .btn-sm {
            padding: 0.25rem 0.75rem;
            font-size: 0.8rem;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--glass-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-radius: 20px;
            border: 1px solid var(--glass-border);
            padding: 2rem;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-hover);
        }

        .stat-card.featured {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transform: scale(1.05);
        }

        .stat-card.featured .stat-details h3,
        .stat-card.featured .stat-details p {
            color: white;
        }

        .stat-card.featured .stat-icon {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }

        .stat-content {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            background: var(--accent-gradient);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .stat-details h3 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }

        .stat-details p {
            color: var(--text-dark);
            opacity: 0.8;
            font-weight: 500;
            margin-bottom: 0;
        }

        .stat-trend {
            position: absolute;
            top: 1rem;
            right: 1rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.8rem;
            font-weight: 600;
            color: #27ae60;
        }

        .stat-note {
            font-size: 0.75rem;
            opacity: 0.7;
            margin-top: 0.25rem;
        }

        .glass-container {
            background: var(--glass-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-radius: 20px;
            border: 1px solid var(--glass-border);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
            position: relative;
            z-index: 2;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .section-title-group {
            flex: 1;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-subtitle {
            color: var(--text-dark);
            opacity: 0.7;
            font-size: 0.9rem;
            margin: 0.25rem 0 0 0;
        }

        .view-controls {
            display: flex;
            gap: 0.5rem;
        }

        .view-controls .btn {
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
            border-radius: 8px;
        }

        .view-controls .btn.active {
            background: var(--primary-gradient);
            color: white;
            border-color: transparent;
        }

        .filter-form {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group .form-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-dark);
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .form-group .form-label i {
            color: #4facfe;
            font-size: 0.8rem;
        }

        .form-control, .form-select {
            background: var(--glass-bg);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            color: var(--text-dark);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            padding: 0.5rem 1rem;
        }

        .form-control:focus, .form-select:focus {
            background: var(--glass-bg);
            border-color: #4facfe;
            box-shadow: 0 0 0 0.2rem rgba(79, 172, 254, 0.25);
            color: var(--text-dark);
            outline: none;
        }

        .form-control::placeholder {
            color: rgba(44, 62, 80, 0.6);
        }

        .invoice-view {
            display: none;
            margin-top: 1.5rem;
            position: relative;
            z-index: 1;
        }

        .invoice-view.active {
            display: block;
        }

        .table-responsive {
            border-radius: 15px;
            overflow: hidden;
        }

        .invoice-table {
            background: transparent;
            border: none;
            width: 100%;
        }

        .invoice-table th {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            padding: 1.25rem 1rem;
            font-weight: 700;
            color: var(--text-dark);
            white-space: nowrap;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .invoice-table td {
            border: none;
            padding: 1.25rem 1rem;
            vertical-align: middle;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .invoice-row:hover {
            background: rgba(255, 255, 255, 0.05);
            transform: translateX(5px);
            transition: all 0.3s ease;
        }

        .invoice-number-cell {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .invoice-number {
            color: #4facfe;
            font-family: 'Courier New', monospace;
            font-size: 1rem;
            font-weight: 700;
        }

        .invoice-date {
            color: var(--text-dark);
            opacity: 0.6;
            font-size: 0.75rem;
        }

        .customer-cell,
        .service-cell {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .customer-avatar,
        .service-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--accent-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.85rem;
        }

        .customer-details,
        .service-details {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .customer-name,
        .service-name {
            color: var(--text-dark);
            font-weight: 600;
            font-size: 0.95rem;
        }

        .customer-email,
        .service-category {
            color: var(--text-dark);
            opacity: 0.7;
            font-size: 0.8rem;
        }

        .datetime-cell {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .booking-date,
        .booking-time {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-dark);
            font-size: 0.85rem;
        }

        .booking-date i,
        .booking-time i {
            color: #4facfe;
            font-size: 0.75rem;
            width: 12px;
        }

        .amount-cell,
        .earnings-cell {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            text-align: right;
        }

        .total-amount,
        .earnings-amount {
            color: #4facfe;
            font-size: 1.1rem;
            font-weight: 700;
        }

        .platform-fee,
        .earnings-percent {
            color: var(--text-dark);
            opacity: 0.7;
            font-size: 0.75rem;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .status-pending {
            background: rgba(255, 193, 7, 0.2);
            color: #ffc107;
            border: 1px solid rgba(255, 193, 7, 0.3);
        }

        .status-confirmed {
            background: rgba(23, 162, 184, 0.2);
            color: #17a2b8;
            border: 1px solid rgba(23, 162, 184, 0.3);
        }

        .status-in-progress {
            background: rgba(255, 87, 34, 0.2);
            color: #ff5722;
            border: 1px solid rgba(255, 87, 34, 0.3);
        }

        .status-completed {
            background: rgba(40, 167, 69, 0.2);
            color: #28a745;
            border: 1px solid rgba(40, 167, 69, 0.3);
        }

        .status-cancelled {
            background: rgba(220, 53, 69, 0.2);
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.3);
        }

        .action-buttons {
            display: flex;
            gap: 0.25rem;
            flex-wrap: wrap;
        }

        .action-buttons .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
        }

        .invoice-cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .invoice-card {
            background: var(--glass-bg);
            backdrop-filter: blur(16px);
            border-radius: 20px;
            border: 1px solid var(--glass-border);
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
        }

        .invoice-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
        }

        .invoice-card-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .invoice-info h4 {
            color: #4facfe;
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .invoice-amount {
            text-align: right;
        }

        .invoice-amount .earnings {
            color: #27ae60;
            font-size: 1.4rem;
            font-weight: 700;
            display: block;
        }

        .invoice-amount small {
            color: var(--text-dark);
            opacity: 0.7;
            font-size: 0.8rem;
        }

        .invoice-card-body {
            padding: 1.5rem;
            flex-grow: 1;
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
        }

        .customer-section,
        .service-section,
        .schedule-section {
            padding: 1rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .customer-section h5,
        .service-section h5,
        .schedule-section h5 {
            color: var(--text-dark);
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .customer-section h5 i,
        .service-section h5 i,
        .schedule-section h5 i {
            color: #4facfe;
            font-size: 0.8rem;
        }

        .customer-section p,
        .service-section p,
        .schedule-section p {
            color: var(--text-dark);
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .invoice-card-footer {
            padding: 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .financial-breakdown {
            margin-bottom: 1rem;
        }

        .breakdown-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            color: var(--text-dark);
            font-size: 0.85rem;
        }

        .breakdown-item:first-child {
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .card-actions {
            display: flex;
            gap: 0.5rem;
        }

        .card-actions .btn {
            flex: 1;
            font-size: 0.85rem;
            padding: 0.5rem 1rem;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--text-dark);
            opacity: 0.7;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            color: #4facfe;
            opacity: 0.8;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--text-dark);
        }

        .empty-state p {
            font-size: 1rem;
            margin-bottom: 2rem;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.6;
        }

        .empty-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .empty-actions .btn {
            min-width: 150px;
        }

        .modal-content.glass-modal {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            position: relative;
            z-index: 1050;
        }

        .glass-modal .modal-header {
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 1.5rem;
        }

        .glass-modal .modal-body {
            padding: 2rem;
        }

        .glass-modal .modal-footer {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding: 1.5rem;
        }

        .glass-modal .modal-title {
            color: var(--text-dark);
            font-weight: 600;
        }

        .loading-spinner {
            text-align: center;
            padding: 2rem;
            color: var(--text-dark);
        }

        .loading-spinner i {
            color: #4facfe;
            margin-bottom: 1rem;
        }

        .notification-toast {
            position: fixed;
            top: 100px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
            max-width: 400px;
            border-radius: 15px;
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: var(--shadow);
            animation: slideInRight 0.3s ease;
            pointer-events: auto;
        }

        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .notification-toast .btn-close {
            filter: brightness(0) invert(1);
        }

        .text-danger { color: #dc3545 !important; }
        .text-muted { opacity: 0.6; }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .header-content {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .header-actions {
                width: 100%;
                justify-content: flex-end;
            }
            
            .invoice-cards-grid {
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .stats-row {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .stat-card.featured {
                transform: none;
                grid-column: 1 / -1;
            }
            
            .invoice-table th,
            .invoice-table td {
                padding: 0.75rem 0.5rem;
                font-size: 0.8rem;
            }
            
            .customer-cell,
            .service-cell {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            
            .customer-avatar,
            .service-icon {
                width: 30px;
                height: 30px;
                font-size: 0.8rem;
            }
            
            .invoice-cards-grid {
                grid-template-columns: 1fr;
            }
            
            .nav-links {
                flex-wrap: wrap;
            }
            
            .nav-link span {
                display: none;
            }

            .main-container {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-left">
                <a href="dashboard.php" class="logo">QuickFix</a>
                <div class="provider-info">
                    <span class="provider-name"><?php echo htmlspecialchars($provider['full_name']); ?></span>
                    <span class="provider-role">Provider Dashboard</span>
                </div>
            </div>
            <div class="nav-right">
                <div class="nav-links">
                    <a href="dashboard.php" class="nav-link">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="bookings.php" class="nav-link">
                        <i class="fas fa-calendar-check"></i>
                        <span>Bookings</span>
                    </a>
                    <a href="services.php" class="nav-link">
                        <i class="fas fa-cogs"></i>
                        <span>Services</span>
                    </a>
                    <a href="invoice.php" class="nav-link active">
                        <i class="fas fa-file-invoice-dollar"></i>
                        <span>Invoices</span>
                    </a>
                    <a href="earnings.php" class="nav-link">
                        <i class="fas fa-chart-line"></i>
                        <span>Earnings</span>
                    </a>
                    <div class="nav-profile">
                        <div class="profile-dropdown">
                            <button class="profile-btn" type="button" data-bs-toggle="dropdown">
                                <img src="../uploads/profile_photos/<?php echo $provider['profile_image']; ?>" 
                                     alt="Profile" class="profile-img">
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="profile.php">
                                    <i class="fas fa-user"></i> Profile Settings
                                </a></li>
                                <li><a class="dropdown-item" href="earnings.php">
                                    <i class="fas fa-wallet"></i> Earnings
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="../auth/logout.php">
                                    <i class="fas fa-sign-out-alt"></i> Logout
                                </a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="main-container">
        <!-- Enhanced Page Header -->
        <div class="page-header">
            <div class="header-content">
                <div class="header-left">
                    <div class="header-icon">
                        <i class="fas fa-file-invoice-dollar"></i>
                    </div>
                    <div class="header-text">
                        <h1 class="page-title">Invoice Management</h1>
                        <p class="page-subtitle">Track your earnings, manage billing, and generate financial reports</p>
                    </div>
                </div>
                <div class="header-actions">
                    <button onclick="exportInvoices()" class="btn btn-outline-primary">
                        <i class="fas fa-download"></i>
                        <span>Export Data</span>
                    </button>
                    <button onclick="generateReport()" class="btn btn-primary">
                        <i class="fas fa-chart-bar"></i>
                        <span>Financial Report</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Enhanced Statistics Cards -->
        <div class="stats-row">
            <div class="stat-card featured">
                <div class="stat-content">
                    <div class="stat-icon">
                        <i class="fas fa-peso-sign"></i>
                    </div>
                    <div class="stat-details">
                        <h3>₱<?php echo number_format($total_earnings, 2); ?></h3>
                        <p>Your Total Earnings</p>
                        <small class="stat-note">After platform fees</small>
                    </div>
                </div>
                <div class="stat-trend">
                    <i class="fas fa-arrow-up"></i>
                    <span>+12.5%</span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-content">
                    <div class="stat-icon">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['total_invoices']); ?></h3>
                        <p>Total Invoices</p>
                        <small class="stat-note">All time</small>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-content">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['completed_jobs']); ?></h3>
                        <p>Completed Jobs</p>
                        <small class="stat-note">Successfully finished</small>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-content">
                    <div class="stat-icon">
                        <i class="fas fa-calculator"></i>
                    </div>
                    <div class="stat-details">
                        <h3>₱<?php echo number_format($stats['avg_invoice_value'] ?? 0, 2); ?></h3>
                        <p>Average Invoice</p>
                        <small class="stat-note">Per completed job</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Enhanced Search and Filter Section -->
        <div class="glass-container">
            <div class="section-header">
                <div class="section-title-group">
                    <h2 class="section-title">
                        <i class="fas fa-filter"></i> Filter & Search
                    </h2>
                    <p class="section-subtitle">Find specific invoices and transactions</p>
                </div>
                <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" 
                        data-bs-target="#filterCollapse" aria-expanded="false">
                    <i class="fas fa-sliders-h"></i> Advanced Filters
                </button>
            </div>
            
            <div class="collapse show" id="filterCollapse">
                <form method="GET" class="filter-form">
                    <div class="row g-3">
                        <div class="col-lg-4 col-md-6">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-search"></i> Quick Search
                                </label>
                                <input type="text" name="search" class="form-control" 
                                       placeholder="Customer name, service, or invoice #" 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        <div class="col-lg-2 col-md-3">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-flag"></i> Status
                                </label>
                                <select name="status" class="form-select">
                                    <option value="">All Status</option>
                                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="confirmed" <?php echo $status_filter == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                    <option value="in_progress" <?php echo $status_filter == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-lg-2 col-md-3">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-calendar"></i> From Date
                                </label>
                                <input type="date" name="date_from" class="form-control" 
                                       value="<?php echo htmlspecialchars($date_from); ?>">
                            </div>
                        </div>
                        <div class="col-lg-2 col-md-3">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-calendar"></i> To Date
                                </label>
                                <input type="date" name="date_to" class="form-control" 
                                       value="<?php echo htmlspecialchars($date_to); ?>">
                            </div>
                        </div>
                        <div class="col-lg-2 col-md-3">
                            <div class="form-group">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i>
                                    </button>
                                    <a href="invoice.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-times"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Enhanced Invoices List -->
        <div class="glass-container">
            <div class="section-header">
                <div class="section-title-group">
                    <h2 class="section-title">
                        <i class="fas fa-list-ul"></i> Invoice Records
                    </h2>
                    <p class="section-subtitle"><?php echo count($invoices); ?> invoices found</p>
                </div>
                <div class="view-controls">
                    <button class="btn btn-outline-primary btn-sm active" onclick="toggleView('table')">
                        <i class="fas fa-table"></i> Table View
                    </button>
                    <button class="btn btn-outline-primary btn-sm" onclick="toggleView('cards')">
                        <i class="fas fa-th-large"></i> Card View
                    </button>
                </div>
            </div>
            
            <?php if (count($invoices) > 0): ?>
            <!-- Table View -->
            <div id="tableView" class="invoice-view active">
                <div class="table-responsive">
                    <table class="table invoice-table">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Customer</th>
                                <th>Service</th>
                                <th>Date & Time</th>
                                <th>Amount</th>
                                <th>Your Earnings</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($invoices as $invoice): 
                                $platform_fee = $invoice['total_amount'] * $platform_fee_rate;
                                $net_earnings = $invoice['total_amount'] - $platform_fee;
                            ?>
                            <tr class="invoice-row">
                                <td>
                                    <div class="invoice-number-cell">
                                        <strong class="invoice-number">#<?php echo str_pad($invoice['id'], 6, '0', STR_PAD_LEFT); ?></strong>
                                        <small class="invoice-date">Created: <?php echo date('M d, Y', strtotime($invoice['created_at'])); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <div class="customer-cell">
                                        <div class="customer-avatar">
                                            <i class="fas fa-user-circle"></i>
                                        </div>
                                        <div class="customer-details">
                                            <strong class="customer-name"><?php echo htmlspecialchars($invoice['customer_name']); ?></strong>
                                            <small class="customer-email"><?php echo htmlspecialchars($invoice['customer_email']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="service-cell">
                                        <div class="service-icon">
                                            <i class="fas fa-tools"></i>
                                        </div>
                                        <div class="service-details">
                                            <strong class="service-name"><?php echo htmlspecialchars($invoice['service_name']); ?></strong>
                                            <small class="service-category"><?php echo htmlspecialchars($invoice['category']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="datetime-cell">
                                        <div class="booking-date">
                                            <i class="fas fa-calendar"></i>
                                            <?php echo date('M d, Y', strtotime($invoice['booking_date'])); ?>
                                        </div>
                                        <div class="booking-time">
                                            <i class="fas fa-clock"></i>
                                            <?php echo date('h:i A', strtotime($invoice['booking_time'])); ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="amount-cell">
                                        <strong class="total-amount">₱<?php echo number_format($invoice['total_amount'], 2); ?></strong>
                                        <small class="platform-fee">Platform fee: ₱<?php echo number_format($platform_fee, 2); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <div class="earnings-cell">
                                        <strong class="earnings-amount">₱<?php echo number_format($net_earnings, 2); ?></strong>
                                        <small class="earnings-percent"><?php echo number_format((1-$platform_fee_rate)*100, 1); ?>% of total</small>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $invoice['status']; ?>">
                                        <i class="fas fa-<?php 
                                            switch($invoice['status']) {
                                                case 'pending': echo 'clock'; break;
                                                case 'confirmed': echo 'check'; break;
                                                case 'in_progress': echo 'spinner'; break;
                                                case 'completed': echo 'check-circle'; break;
                                                case 'cancelled': echo 'times-circle'; break;
                                                default: echo 'question';
                                            }
                                        ?>"></i>
                                        <?php echo ucfirst(str_replace('_', ' ', $invoice['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button onclick="viewInvoice(<?php echo $invoice['id']; ?>)" 
                                                class="btn btn-sm btn-outline-primary" 
                                                title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button onclick="downloadInvoice(<?php echo $invoice['id']; ?>)" 
                                                class="btn btn-sm btn-outline-success" 
                                                title="Download PDF">
                                            <i class="fas fa-download"></i>
                                        </button>
                                        <?php if ($invoice['status'] == 'completed'): ?>
                                        <button onclick="sendInvoice(<?php echo $invoice['id']; ?>)" 
                                                class="btn btn-sm btn-outline-info" 
                                                title="Email Invoice">
                                            <i class="fas fa-envelope"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Card View -->
            <div id="cardView" class="invoice-view">
                <div class="invoice-cards-grid">
                    <?php foreach ($invoices as $invoice): 
                        $platform_fee = $invoice['total_amount'] * $platform_fee_rate;
                        $net_earnings = $invoice['total_amount'] - $platform_fee;
                    ?>
                    <div class="invoice-card">
                        <div class="invoice-card-header">
                            <div class="invoice-info">
                                <h4 class="invoice-number">#<?php echo str_pad($invoice['id'], 6, '0', STR_PAD_LEFT); ?></h4>
                                <span class="status-badge status-<?php echo $invoice['status']; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $invoice['status'])); ?>
                                </span>
                            </div>
                            <div class="invoice-amount">
                                <strong class="earnings">₱<?php echo number_format($net_earnings, 2); ?></strong>
                                <small>Your Earnings</small>
                            </div>
                        </div>
                        <div class="invoice-card-body">
                            <div class="customer-section">
                                <h5><i class="fas fa-user"></i> Customer</h5>
                                <p><strong><?php echo htmlspecialchars($invoice['customer_name']); ?></strong></p>
                                <p class="text-muted"><?php echo htmlspecialchars($invoice['customer_email']); ?></p>
                            </div>
                            <div class="service-section">
                                <h5><i class="fas fa-cogs"></i> Service</h5>
                                <p><strong><?php echo htmlspecialchars($invoice['service_name']); ?></strong></p>
                                <p class="text-muted"><?php echo htmlspecialchars($invoice['category']); ?></p>
                            </div>
                            <div class="schedule-section">
                                <h5><i class="fas fa-calendar-alt"></i> Schedule</h5>
                                <p><?php echo date('F d, Y', strtotime($invoice['booking_date'])); ?></p>
                                <p class="text-muted"><?php echo date('h:i A', strtotime($invoice['booking_time'])); ?></p>
                            </div>
                        </div>
                        <div class="invoice-card-footer">
                            <div class="financial-breakdown">
                                <div class="breakdown-item">
                                    <span>Total Amount</span>
                                    <span>₱<?php echo number_format($invoice['total_amount'], 2); ?></span>
                                </div>
                                <div class="breakdown-item">
                                    <span>Platform Fee</span>
                                    <span class="text-danger">-₱<?php echo number_format($platform_fee, 2); ?></span>
                                </div>
                            </div>
                            <div class="card-actions">
                                <button onclick="viewInvoice(<?php echo $invoice['id']; ?>)" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-eye"></i> View
                                </button>
                                <button onclick="downloadInvoice(<?php echo $invoice['id']; ?>)" class="btn btn-outline-success btn-sm">
                                    <i class="fas fa-download"></i> PDF
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-file-invoice"></i>
                </div>
                <h3>No Invoices Found</h3>
                <p>No invoices match your current search criteria. Try adjusting your filters or completing some bookings to generate invoices.</p>
                <div class="empty-actions">
                    <a href="bookings.php" class="btn btn-primary">
                        <i class="fas fa-calendar-check"></i> View Bookings
                    </a>
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-tachometer-alt"></i> Back to Dashboard
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Enhanced Invoice Modal -->
    <div class="modal fade" id="invoiceModal" tabindex="-1" aria-labelledby="invoiceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content glass-modal">
                <div class="modal-header">
                    <h5 class="modal-title" id="invoiceModalLabel">
                        <i class="fas fa-file-invoice-dollar"></i> Invoice Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="invoiceContent">
                    <div class="loading-spinner">
                        <i class="fas fa-spinner fa-spin"></i>
                        <p>Loading invoice details...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Close
                    </button>
                    <button type="button" class="btn btn-success" onclick="downloadCurrentInvoice()">
                        <i class="fas fa-download"></i> Download PDF
                    </button>
                    <button type="button" class="btn btn-info" onclick="sendCurrentInvoice()" id="sendInvoiceBtn">
                        <i class="fas fa-envelope"></i> Send Email
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentInvoiceId = null;
        let currentView = 'table';

        // View Toggle Functions
        function toggleView(view) {
            currentView = view;
            
            // Update button states
            document.querySelectorAll('.view-controls .btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // Show/hide views
            document.getElementById('tableView').classList.remove('active');
            document.getElementById('cardView').classList.remove('active');
            
            if (view === 'table') {
                document.getElementById('tableView').classList.add('active');
            } else {
                document.getElementById('cardView').classList.add('active');
            }
            
            // Save preference
            localStorage.setItem('invoice_view_preference', view);
        }

        // Load saved view preference
        document.addEventListener('DOMContentLoaded', function() {
            const savedView = localStorage.getItem('invoice_view_preference') || 'table';
            if (savedView === 'cards') {
                // Simulate click on cards button
                setTimeout(() => {
                    const cardsBtn = document.querySelector('.view-controls .btn:last-child');
                    if (cardsBtn) cardsBtn.click();
                }, 100);
            }
        });

        function viewInvoice(invoiceId) {
            currentInvoiceId = invoiceId;
            
            // Show loading state
            document.getElementById('invoiceContent').innerHTML = `
                <div class="loading-spinner text-center py-5">
                    <i class="fas fa-spinner fa-spin fa-2x text-primary mb-3"></i>
                    <p class="text-muted">Loading invoice details...</p>
                </div>
            `;
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('invoiceModal'));
            modal.show();
            
            // Load invoice details via AJAX
            fetch(`get_invoice_details.php?id=${invoiceId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.text();
                })
                .then(data => {
                    document.getElementById('invoiceContent').innerHTML = data;
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('invoiceContent').innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Error loading invoice details</strong>
                            <p>Please try again or contact support if the problem persists.</p>
                        </div>
                    `;
                });
        }

        function downloadInvoice(invoiceId) {
            // Show loading indicator
            const originalText = event.target.innerHTML;
            event.target.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            event.target.disabled = true;
            
            // Simulate download preparation
            setTimeout(() => {
                window.open(`generate_pdf_invoice.php?id=${invoiceId}`, '_blank');
                event.target.innerHTML = originalText;
                event.target.disabled = false;
            }, 500);
        }

        function downloadCurrentInvoice() {
            if (currentInvoiceId) {
                downloadInvoice(currentInvoiceId);
            }
        }

        function sendInvoice(invoiceId) {
            if (confirm('Send invoice via email to customer?')) {
                const btn = event.target.closest('button');
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
                btn.disabled = true;
                
                fetch('send_invoice.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        invoice_id: invoiceId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                    
                    if (data.success) {
                        showNotification('Invoice sent successfully!', 'success');
                    } else {
                        showNotification('Error sending invoice: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                    showNotification('Error sending invoice', 'error');
                });
            }
        }

        function sendCurrentInvoice() {
            if (currentInvoiceId) {
                sendInvoice(currentInvoiceId);
            }
        }

        function exportInvoices() {
            const searchParams = new URLSearchParams(window.location.search);
            searchParams.append('export', '1');
            window.open(`export_invoices.php?${searchParams.toString()}`, '_blank');
        }

        function generateReport() {
            window.open('generate_invoice_report.php', '_blank');
        }

        // Enhanced notification system
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'} notification-toast`;
            notification.innerHTML = `
                <i class="fas fa-${type === 'error' ? 'exclamation-circle' : type === 'success' ? 'check-circle' : 'info-circle'}"></i>
                ${message}
                <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
            `;
            
            // Add to page
            document.body.appendChild(notification);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 5000);
        }

        // Auto-refresh every 60 seconds (increased from 30)
        setInterval(function() {
            if (!document.querySelector('.modal.show')) {
                location.reload();
            }
        }, 60000);

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl+E for export
            if (e.ctrlKey && e.key === 'e') {
                e.preventDefault();
                exportInvoices();
            }
            
            // Ctrl+R for report
            if (e.ctrlKey && e.key === 'r') {
                e.preventDefault();
                generateReport();
            }
        });
    </script>
</body>
</html>