<?php
require_once '../config/config.php';
checkAccess(['provider']);

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo '<div class="alert alert-danger">Invalid invoice ID</div>';
    exit;
}

$database = new Database();
$db = $database->getConnection();
$provider_id = $_SESSION['user_id'];
$invoice_id = $_GET['id'];

// Get invoice details
$query = "SELECT b.*, s.name as service_name, s.category, 
                 u.full_name as customer_name, u.email as customer_email, 
                 u.phone as customer_phone, u.address as customer_address,
                 p.full_name as provider_name, p.email as provider_email,
                 p.phone as provider_phone, p.address as provider_address
          FROM bookings b 
          JOIN services s ON b.service_id = s.id 
          JOIN users u ON b.user_id = u.id 
          JOIN users p ON b.provider_id = p.id
          WHERE b.id = :invoice_id AND b.provider_id = :provider_id";

$stmt = $db->prepare($query);
$stmt->bindParam(':invoice_id', $invoice_id);
$stmt->bindParam(':provider_id', $provider_id);
$stmt->execute();
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invoice) {
    echo '<div class="alert alert-danger">Invoice not found or access denied</div>';
    exit;
}

// Calculate fees
$platform_fee_rate = 0.10;
$platform_fee = $invoice['total_amount'] * $platform_fee_rate;
$net_earnings = $invoice['total_amount'] - $platform_fee;
?>

<div class="invoice-details">
    <div class="invoice-header">
        <div class="row">
            <div class="col-md-6">
                <h3 class="invoice-title">Invoice #<?php echo str_pad($invoice['id'], 6, '0', STR_PAD_LEFT); ?></h3>
                <p class="invoice-date">Date: <?php echo date('F d, Y', strtotime($invoice['created_at'])); ?></p>
            </div>
            <div class="col-md-6 text-end">
                <div class="status-display">
                    <span class="status-badge status-<?php echo $invoice['status']; ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $invoice['status'])); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="invoice-body">
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="info-section">
                    <h5><i class="fas fa-user"></i> Customer Information</h5>
                    <div class="info-details">
                        <strong><?php echo htmlspecialchars($invoice['customer_name']); ?></strong><br>
                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($invoice['customer_email']); ?><br>
                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($invoice['customer_phone']); ?><br>
                        <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($invoice['customer_address']); ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="info-section">
                    <h5><i class="fas fa-user-tie"></i> Provider Information</h5>
                    <div class="info-details">
                        <strong><?php echo htmlspecialchars($invoice['provider_name']); ?></strong><br>
                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($invoice['provider_email']); ?><br>
                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($invoice['provider_phone']); ?><br>
                        <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($invoice['provider_address']); ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="service-details">
            <h5><i class="fas fa-cogs"></i> Service Details</h5>
            <div class="service-info-card">
                <div class="row">
                    <div class="col-md-8">
                        <h6 class="service-name"><?php echo htmlspecialchars($invoice['service_name']); ?></h6>
                        <p class="service-category"><?php echo htmlspecialchars($invoice['category']); ?></p>
                        <p class="service-date">
                            <i class="fas fa-calendar"></i> <?php echo date('F d, Y', strtotime($invoice['booking_date'])); ?>
                            <i class="fas fa-clock ms-3"></i> <?php echo date('h:i A', strtotime($invoice['booking_time'])); ?>
                        </p>
                        <?php if (!empty($invoice['notes'])): ?>
                        <p class="service-notes">
                            <i class="fas fa-sticky-note"></i> <?php echo htmlspecialchars($invoice['notes']); ?>
                        </p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="amount-display">
                            <span class="amount-label">Service Amount</span>
                            <span class="amount-value">₱<?php echo number_format($invoice['total_amount'], 2); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="payment-breakdown">
            <h5><i class="fas fa-calculator"></i> Payment Breakdown</h5>
            <div class="breakdown-table">
                <div class="breakdown-row">
                    <span class="breakdown-label">Service Amount</span>
                    <span class="breakdown-value">₱<?php echo number_format($invoice['total_amount'], 2); ?></span>
                </div>
                <div class="breakdown-row">
                    <span class="breakdown-label">Platform Fee (10%)</span>
                    <span class="breakdown-value fee">-₱<?php echo number_format($platform_fee, 2); ?></span>
                </div>
                <div class="breakdown-row total">
                    <span class="breakdown-label"><strong>Your Earnings</strong></span>
                    <span class="breakdown-value"><strong>₱<?php echo number_format($net_earnings, 2); ?></strong></span>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.invoice-details {
    font-family: 'Poppins', sans-serif;
}

.invoice-header {
    border-bottom: 2px solid var(--glass-border);
    padding-bottom: 1rem;
    margin-bottom: 2rem;
}

.invoice-title {
    color: #4facfe;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.invoice-date {
    color: var(--text-dark);
    opacity: 0.8;
    margin-bottom: 0;
}

.status-display {
    margin-top: 1rem;
}

.info-section {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 15px;
    padding: 1.5rem;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.info-section h5 {
    color: var(--text-dark);
    font-weight: 600;
    margin-bottom: 1rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    padding-bottom: 0.5rem;
}

.info-details {
    color: var(--text-dark);
    line-height: 1.8;
}

.info-details i {
    color: #4facfe;
    width: 16px;
    margin-right: 0.5rem;
}

.service-details {
    margin: 2rem 0;
}

.service-details h5 {
    color: var(--text-dark);
    font-weight: 600;
    margin-bottom: 1rem;
}

.service-info-card {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 15px;
    padding: 1.5rem;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.service-name {
    color: var(--text-dark);
    font-weight: 600;
    font-size: 1.1rem;
    margin-bottom: 0.5rem;
}

.service-category {
    color: #4facfe;
    font-size: 0.9rem;
    margin-bottom: 1rem;
}

.service-date, .service-notes {
    color: var(--text-dark);
    opacity: 0.8;
    margin-bottom: 0.5rem;
}

.service-date i, .service-notes i {
    color: #4facfe;
    margin-right: 0.5rem;
}

.amount-display {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
}

.amount-label {
    color: var(--text-dark);
    opacity: 0.8;
    font-size: 0.9rem;
}

.amount-value {
    color: #4facfe;
    font-size: 1.5rem;
    font-weight: 700;
    margin-top: 0.5rem;
}

.payment-breakdown {
    margin-top: 2rem;
}

.payment-breakdown h5 {
    color: var(--text-dark);
    font-weight: 600;
    margin-bottom: 1rem;
}

.breakdown-table {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 15px;
    padding: 1.5rem;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.breakdown-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.breakdown-row:last-child {
    border-bottom: none;
}

.breakdown-row.total {
    border-top: 2px solid var(--glass-border);
    margin-top: 0.5rem;
    padding-top: 1rem;
}

.breakdown-label {
    color: var(--text-dark);
    font-size: 0.95rem;
}

.breakdown-value {
    color: var(--text-dark);
    font-size: 1rem;
    font-weight: 600;
}

.breakdown-value.fee {
    color: #e74c3c;
}

.breakdown-row.total .breakdown-value {
    color: #27ae60;
    font-size: 1.2rem;
}
</style>