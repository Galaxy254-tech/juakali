<?php
session_start();

// Simple configuration
define('APP_NAME', 'JuaKali Lend');
define('APP_URL', 'http://localhost:8081');

// Simple functions
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function redirect($url) {
    header("Location: $url");
    exit;
}

// Mock data for demonstration
$total_users = 1250;
$active_loans = 342;
$total_disbursed = 8500000;
$featured_products = [];
$testimonials = [];
$top_lenders = [];
$blog_posts = [];
$faqs = [];
$daily_stats = ['total_disbursed' => 250000];
$active_users = ['active_users' => 18];
$personalized_products = [];
$trending_products = [];
$activity_feed = [];
$market_intelligence = [];
$top_lenders_enhanced = [];
$system_status = [
    'all_systems' => 'operational',
    'mpesa_integration' => 'live',
    'support_agents_online' => 12
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JuaKali Lend - Goods & Products Lending Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/custom.css" rel="stylesheet">
    <style>
        .hero-section {
            background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
            color: white;
            padding: 6rem 0;
            text-align: center;
            animation: fadeIn 0.8s ease-out;
        }
        .hero-section h1 {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .hero-section p {
            font-size: 1.25rem;
            margin-bottom: 2rem;
            opacity: 0.95;
        }
        .hero-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn-hero {
            padding: 0.75rem 2rem;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .btn-hero-primary {
            background: white;
            color: #10b981;
        }
        .btn-hero-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
            color: #10b981;
        }
        .btn-hero-secondary {
            background: transparent;
            color: white;
            border: 2px solid white;
        }
        .btn-hero-secondary:hover {
            background: white;
            color: #10b981;
        }
        .features-section {
            padding: 4rem 0;
            background: white;
        }
        .feature-card {
            text-align: center;
            padding: 2rem;
            border-radius: 12px;
            background: white;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            animation: fadeIn 0.6s ease-out;
        }
        .feature-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 24px rgba(16, 185, 129, 0.15);
        }
        .feature-icon {
            font-size: 3rem;
            color: #10b981;
            margin-bottom: 1rem;
        }
        .stats-section {
            background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
            color: white;
            padding: 4rem 0;
            text-align: center;
        }
        .stat-item {
            padding: 2rem;
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .stat-label {
            font-size: 1rem;
            opacity: 0.9;
        }
        .how-it-works {
            padding: 4rem 0;
            background: #f8fafc;
        }
        .step-card {
            text-align: center;
            padding: 2rem;
            position: relative;
        }
        .step-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
            color: white;
            border-radius: 50%;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        /* Added styles for featured products section */
        .featured-products-section {
            padding: 4rem 0;
            background: white;
        }
        .product-card {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .product-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 24px rgba(16, 185, 129, 0.15);
        }
        .product-image {
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
        }
        .product-info {
            padding: 1.5rem;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        .product-name {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #1f2937;
        }
        .product-supplier {
            font-size: 0.875rem;
            color: #6b7280;
            margin-bottom: 1rem;
        }
        .product-price {
            font-size: 1.5rem;
            font-weight: 700;
            color: #10b981;
            margin-bottom: 1rem;
        }
        .product-btn {
            background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        .product-btn:hover {
            transform: scale(1.05);
            color: white;
        }
        /* Added styles for testimonials section */
        .testimonials-section {
            padding: 4rem 0;
            background: #f8fafc;
        }
        .testimonial-card {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
            border-left: 4px solid #10b981;
        }
        .testimonial-rating {
            color: #fbbf24;
            margin-bottom: 1rem;
        }
        .testimonial-text {
            color: #4b5563;
            margin-bottom: 1rem;
            font-style: italic;
        }
        .testimonial-author {
            font-weight: 600;
            color: #1f2937;
        }
        .testimonial-company {
            font-size: 0.875rem;
            color: #6b7280;
        }
        /* Added styles for lender showcase section */
        .lender-showcase-section {
            padding: 4rem 0;
            background: white;
        }
        .lender-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
        }
        .lender-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 24px rgba(16, 185, 129, 0.15);
        }
        .lender-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            margin: 0 auto 1rem;
        }
        .lender-name {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #1f2937;
        }
        .lender-stats {
            display: flex;
            justify-content: space-around;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e5e7eb;
        }
        .lender-stat {
            text-align: center;
        }
        .lender-stat-value {
            font-weight: 700;
            color: #10b981;
            font-size: 1.25rem;
        }
        .lender-stat-label {
            font-size: 0.75rem;
            color: #6b7280;
            text-transform: uppercase;
        }
        /* Added styles for blog section */
        .blog-section {
            padding: 4rem 0;
            background: #f8fafc;
        }
        .blog-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .blog-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 24px rgba(16, 185, 129, 0.15);
        }
        .blog-image {
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
        }
        .blog-content {
            padding: 1.5rem;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        .blog-title {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #1f2937;
        }
        .blog-excerpt {
            color: #6b7280;
            font-size: 0.875rem;
            margin-bottom: 1rem;
            flex-grow: 1;
        }
        .blog-link {
            color: #10b981;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .blog-link:hover {
            color: #059669;
        }
        /* Added styles for FAQ section */
        .faq-section {
            padding: 4rem 0;
            background: white;
        }
        .faq-item {
            margin-bottom: 1rem;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            overflow: hidden;
        }
        .faq-question {
            padding: 1.5rem;
            background: #f8fafc;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
            font-weight: 600;
            color: #1f2937;
        }
        .faq-question:hover {
            background: #f0fdf4;
        }
        .faq-icon {
            color: #10b981;
            transition: transform 0.3s ease;
        }
        .faq-answer {
            padding: 1.5rem;
            background: white;
            color: #4b5563;
            display: none;
            border-top: 1px solid #e5e7eb;
        }
        .faq-answer.active {
            display: block;
        }
        .faq-icon.active {
            transform: rotate(180deg);
        }
        .footer {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: white;
            padding: 3rem 0 1rem;
            text-align: center;
        }
        .live-ticker {
            background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
            color: white;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            animation: slideIn 0.6s ease-out;
        }
        .ticker-item {
            display: inline-block;
            margin-right: 2rem;
            font-weight: 600;
        }
        .ticker-value {
            font-size: 1.5rem;
            color: #fbbf24;
        }
        .activity-feed {
            max-height: 400px;
            overflow-y: auto;
            background: #f8fafc;
            border-radius: 8px;
            padding: 1rem;
        }
        .activity-item {
            padding: 0.75rem;
            border-left: 3px solid #10b981;
            margin-bottom: 0.75rem;
            background: white;
            border-radius: 4px;
        }
        .activity-time {
            font-size: 0.75rem;
            color: #6b7280;
        }
        .market-intelligence {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }
        .intelligence-item {
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }
        .intelligence-item:last-child {
            border-bottom: none;
        }
        .intelligence-category {
            font-weight: 600;
            color: #1f2937;
        }
        .intelligence-stat {
            color: #10b981;
            font-weight: 600;
        }
        .system-status {
            background: #f0fdf4;
            border-left: 4px solid #10b981;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        .status-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
        }
        .status-badge {
            background: #10b981;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        @keyframes slideIn {
            from {
                transform: translateX(-100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-leaf"></i> JuaKali Lend
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="#features">Features</a></li>
                    <li class="nav-item"><a class="nav-link" href="#products">Products</a></li>
                    <li class="nav-item"><a class="nav-link" href="#how-it-works">How It Works</a></li>
                    <li class="nav-item"><a class="nav-link" href="#testimonials">Testimonials</a></li>
                    <li class="nav-item"><a class="nav-link" href="#faq">FAQ</a></li>
                    <?php if (isLoggedIn()): ?>
                        <li class="nav-item"><a class="nav-link" href="pages/dashboard/<?php echo $_SESSION['role']; ?>/">Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link" href="pages/auth/logout.php">Logout</a></li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="pages/auth/login.php">Login</a></li>
                        <li class="nav-item"><a class="nav-link" href="pages/auth/register.php">Register</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <h1><i class="fas fa-handshake"></i> Welcome to JuaKali Lend</h1>
            <p>Empowering retailers with credit-based goods and products lending</p>
            
            <!-- Live Market Ticker -->
            <div class="live-ticker">
                <div class="ticker-item">
                    <i class="fas fa-coins"></i> <span class="ticker-value">KES <?php echo number_format($daily_stats['total_disbursed'] ?? 0, 0); ?></span> loans disbursed today
                </div>
                <div class="ticker-item">
                    <i class="fas fa-users"></i> <span class="ticker-value"><?php echo $active_users['active_users'] ?? 0; ?></span> retailers funded this hour
                </div>
                <div class="ticker-item">
                    <i class="fas fa-chart-line"></i> <span class="ticker-value">98.3%</span> repayment rate this week
                </div>
            </div>
            
            <div class="hero-buttons">
                <a href="pages/auth/register.php" class="btn-hero btn-hero-primary">
                    <i class="fas fa-user-plus"></i> Get Started
                </a>
                <a href="pages/auth/login.php" class="btn-hero btn-hero-secondary">
                    <i class="fas fa-sign-in-alt"></i> Login
                </a>
            </div>
        </div>
    </section>

    <!-- Live Activity Feed Section -->
    <section style="padding: 4rem 0; background: white;">
        <div class="container">
            <h2 class="text-center mb-5" style="font-size: 2.5rem; font-weight: 700;">Live Activity</h2>
            <div class="row">
                <div class="col-md-6">
                    <h4 style="margin-bottom: 1.5rem;">Real-Time Activity Feed</h4>
                    <div class="activity-feed">
                        <?php if ($activity_feed): ?>
                            <?php foreach ($activity_feed as $activity): ?>
                                <div class="activity-item">
                                    <div><?php echo htmlspecialchars($activity['message']); ?></div>
                                    <div class="activity-time"><i class="fas fa-clock"></i> <?php echo date('H:i', strtotime($activity['created_at'])); ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted">No recent activity</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <h4 style="margin-bottom: 1.5rem;">Local Market Intelligence</h4>
                    <div class="market-intelligence">
                        <?php if ($market_intelligence): ?>
                            <?php foreach ($market_intelligence as $intel): ?>
                                <div class="intelligence-item">
                                    <div class="intelligence-category"><?php echo htmlspecialchars($intel['category']); ?></div>
                                    <div style="font-size: 0.875rem; color: #6b7280; margin-top: 0.5rem;">
                                        <span class="intelligence-stat"><?php echo $intel['sales_count']; ?></span> sales this week
                                        | Avg price: <span class="intelligence-stat">KES <?php echo number_format($intel['avg_price'], 0); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted">No market data available</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- System Status Dashboard -->
    <section style="padding: 4rem 0; background: #f8fafc;">
        <div class="container">
            <h2 class="text-center mb-5" style="font-size: 2.5rem; font-weight: 700;">System Status</h2>
            <div class="row">
                <div class="col-md-8 offset-md-2">
                    <div class="system-status">
                        <div class="status-item">
                            <span><i class="fas fa-server"></i> All Systems</span>
                            <span class="status-badge"><i class="fas fa-check-circle"></i> Operational</span>
                        </div>
                        <div class="status-item">
                            <span><i class="fas fa-mobile-alt"></i> M-Pesa Integration</span>
                            <span class="status-badge"><i class="fas fa-check-circle"></i> Live</span>
                        </div>
                        <div class="status-item">
                            <span><i class="fas fa-headset"></i> Support Agents Online</span>
                            <span class="status-badge"><?php echo $system_status['support_agents_online']; ?> Available</span>
                        </div>
                        <div class="status-item">
                            <span><i class="fas fa-lock"></i> Data Protection</span>
                            <span class="status-badge"><i class="fas fa-check-circle"></i> Bank-Level Encryption</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section" id="features">
        <div class="container">
            <h2 class="text-center mb-5" style="font-size: 2.5rem; font-weight: 700;">Why Choose JuaKali Lend?</h2>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon"><i class="fas fa-credit-card"></i></div>
                        <h3>Easy Credit Access</h3>
                        <p>Get instant credit approval with flexible repayment terms tailored to your business needs.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon"><i class="fas fa-chart-line"></i></div>
                        <h3>Build Credit Score</h3>
                        <p>Improve your credit rating with every successful repayment and unlock higher credit limits.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon"><i class="fas fa-lock"></i></div>
                        <h3>Secure & Safe</h3>
                        <p>Your data is protected with enterprise-grade security and encryption standards.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon"><i class="fas fa-mobile-alt"></i></div>
                        <h3>Mobile Friendly</h3>
                        <p>Manage your loans and repayments on the go with our responsive mobile platform.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon"><i class="fas fa-headset"></i></div>
                        <h3>24/7 Support</h3>
                        <p>Our dedicated support team is always ready to help you with any questions or issues.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon"><i class="fas fa-rocket"></i></div>
                        <h3>Fast Processing</h3>
                        <p>Quick loan approval and disbursement to keep your business running smoothly.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon"><i class="fas fa-chart-area"></i></div>
                        <h3>Market Insights</h3>
                        <p>Gain access to real-time market data and trends to make informed decisions.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon"><i class="fas fa-users-cog"></i></div>
                        <h3>User Dashboard</h3>
                        <p>Personalized dashboard for tracking your loans, repayments, and account activity.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon"><i class="fas fa-hand-holding-usd"></i></div>
                        <h3>Flexible Pricing</h3>
                        <p>Competitive pricing on loans and products to suit your budget.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon"><i class="fas fa-map-marker-alt"></i></div>
                        <h3>Local Delivery</h3>
                        <p>Products delivered directly to your location for convenience.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon"><i class="fas fa-thumbs-up"></i></div>
                        <h3>High Customer Satisfaction</h3>
                        <p>Enjoy high ratings and positive feedback from satisfied users.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon"><i class="fas fa-shield-alt"></i></div>
                        <h3>Financial Protection</h3>
                        <p>Protect your business with insurance coverage on all loans.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Featured Products Section -->
    <section class="featured-products-section" id="products">
        <div class="container">
            <h2 class="text-center mb-5" style="font-size: 2.5rem; font-weight: 700;">Featured Products</h2>
            <div class="row g-4">
                <?php if ($featured_products): ?>
                    <?php foreach ($featured_products as $product): ?>
                        <div class="col-md-4">
                            <div class="product-card">
                                <div class="product-image">
                                    <i class="fas fa-box"></i>
                                </div>
                                <div class="product-info">
                                    <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                                    <div class="product-supplier"><?php echo htmlspecialchars($product['company_name']); ?></div>
                                    <div class="product-price">KES <?php echo number_format($product['price'], 2); ?></div>
                                    <a href="auth/login.php" class="product-btn">
                                        <i class="fas fa-shopping-cart"></i> Order Now
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12 text-center">
                        <p class="text-muted">No featured products available at the moment.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-section" id="stats">
        <div class="container">
            <div class="row">
                <div class="col-md-3">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo number_format($total_users); ?></div>
                        <div class="stat-label">Active Users</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo number_format($active_loans); ?></div>
                        <div class="stat-label">Active Loans</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-item">
                        <div class="stat-number">KES <?php echo number_format($total_disbursed / 1000000, 1); ?>M</div>
                        <div class="stat-label">Disbursed</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-item">
                        <div class="stat-number">98%</div>
                        <div class="stat-label">Success Rate</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section class="how-it-works" id="how-it-works">
        <div class="container">
            <h2 class="text-center mb-5" style="font-size: 2.5rem; font-weight: 700;">How It Works</h2>
            <div class="row">
                <div class="col-md-3">
                    <div class="step-card">
                        <div class="step-number">1</div>
                        <h4>Register</h4>
                        <p>Create your account and complete your profile with basic information.</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="step-card">
                        <div class="step-number">2</div>
                        <h4>Browse Products</h4>
                        <p>Explore our wide range of products available on credit.</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="step-card">
                        <div class="step-number">3</div>
                        <h4>Get Approved</h4>
                        <p>Receive instant credit approval based on your profile and history.</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="step-card">
                        <div class="step-number">4</div>
                        <h4>Repay & Grow</h4>
                        <p>Make timely repayments and build your credit score for higher limits.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="testimonials-section" id="testimonials">
        <div class="container">
            <h2 class="text-center mb-5" style="font-size: 2.5rem; font-weight: 700;">What Our Users Say</h2>
            <div class="row">
                <?php if ($testimonials): ?>
                    <?php foreach ($testimonials as $testimonial): ?>
                        <div class="col-md-6">
                            <div class="testimonial-card">
                                <div class="testimonial-rating">
                                    <?php for ($i = 0; $i < $testimonial['rating']; $i++): ?>
                                        <i class="fas fa-star"></i>
                                    <?php endfor; ?>
                                </div>
                                <div class="testimonial-text">"<?php echo htmlspecialchars($testimonial['content']); ?>"</div>
                                <div class="testimonial-author"><?php echo htmlspecialchars($testimonial['first_name'] . ' ' . $testimonial['last_name']); ?></div>
                                <div class="testimonial-company"><?php echo htmlspecialchars($testimonial['company_name']); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12 text-center">
                        <p class="text-muted">No testimonials available yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Lender Showcase Section -->
    <section class="lender-showcase-section">
        <div class="container">
            <h2 class="text-center mb-5" style="font-size: 2.5rem; font-weight: 700;">Top Performing Lenders</h2>
            <div class="row g-4">
                <?php if ($top_lenders_enhanced): ?>
                    <?php foreach ($top_lenders_enhanced as $lender): ?>
                        <div class="col-md-3">
                            <div class="lender-card">
                                <div class="lender-avatar">
                                    <i class="fas fa-user-tie"></i>
                                </div>
                                <div class="lender-name"><?php echo htmlspecialchars($lender['company_name']); ?></div>
                                <div style="color: #fbbf24; margin-bottom: 1rem;">
                                    <?php for ($i = 0; $i < floor($lender['rating']); $i++): ?>
                                        <i class="fas fa-star"></i>
                                    <?php endfor; ?>
                                </div>
                                <div class="lender-stats">
                                    <div class="lender-stat">
                                        <div class="lender-stat-value">KES <?php echo number_format($lender['total_invested'] / 1000000, 1); ?>M</div>
                                        <div class="lender-stat-label">Invested</div>
                                    </div>
                                    <div class="lender-stat">
                                        <div class="lender-stat-value"><?php echo number_format($lender['roi'], 1); ?>%</div>
                                        <div class="lender-stat-label">ROI</div>
                                    </div>
                                    <div class="lender-stat">
                                        <div class="lender-stat-value"><?php echo $lender['active_investments']; ?></div>
                                        <div class="lender-stat-label">Active</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12 text-center">
                        <p class="text-muted">No lenders available yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Blog Section -->
    <section class="blog-section">
        <div class="container">
            <h2 class="text-center mb-5" style="font-size: 2.5rem; font-weight: 700;">Latest News & Updates</h2>
            <div class="row g-4">
                <?php if ($blog_posts): ?>
                    <?php foreach ($blog_posts as $post): ?>
                        <div class="col-md-4">
                            <div class="blog-card">
                                <div class="blog-image">
                                    <i class="fas fa-newspaper"></i>
                                </div>
                                <div class="blog-content">
                                    <div class="blog-title"><?php echo htmlspecialchars($post['title']); ?></div>
                                    <div class="blog-excerpt"><?php echo htmlspecialchars(substr($post['content'], 0, 100)) . '...'; ?></div>
                                    <a href="#" class="blog-link">Read More <i class="fas fa-arrow-right"></i></a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12 text-center">
                        <p class="text-muted">No blog posts available yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="faq-section" id="faq">
        <div class="container">
            <h2 class="text-center mb-5" style="font-size: 2.5rem; font-weight: 700;">Frequently Asked Questions</h2>
            <div class="row">
                <div class="col-md-8 offset-md-2">
                    <?php if ($faqs): ?>
                        <?php foreach ($faqs as $faq): ?>
                            <div class="faq-item">
                                <div class="faq-question" onclick="toggleFAQ(this)">
                                    <span><?php echo htmlspecialchars($faq['question']); ?></span>
                                    <i class="fas fa-chevron-down faq-icon"></i>
                                </div>
                                <div class="faq-answer">
                                    <?php echo htmlspecialchars($faq['answer']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center">
                            <p class="text-muted">No FAQs available yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 JuaKali Lend. All rights reserved.</p>
            <p>Empowering Small Businesses Through Credit Access</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleFAQ(element) {
            const answer = element.nextElementSibling;
            const icon = element.querySelector('.faq-icon');
            
            // Close all other FAQs
            document.querySelectorAll('.faq-answer').forEach(a => {
                if (a !== answer) {
                    a.classList.remove('active');
                    a.previousElementSibling.querySelector('.faq-icon').classList.remove('active');
                }
            });
            
            // Toggle current FAQ
            answer.classList.toggle('active');
            icon.classList.toggle('active');
        }
    </script>
</body>
</html>
