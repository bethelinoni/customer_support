<?php
/**
 * BethelDesk - Modern SaaS Customer Support Platform
 * Landing Page
 */
session_start();
require_once __DIR__ . '/config.php';

$isUserLoggedIn = isset($_SESSION['user_id']);
$isAdminLoggedIn = isset($_SESSION['admin']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> — Modern Customer Support &amp; Helpdesk Platform</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?php echo file_exists(__DIR__ . '/style.css') ? filemtime(__DIR__ . '/style.css') : time(); ?>">
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css">
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/bold/style.css">
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/fill/style.css">
    <link rel="stylesheet" href="css/all.min.css">
    <script>
        (function() {
            try {
                var savedTheme = localStorage.getItem('bethel_theme');
                if (!savedTheme) {
                    savedTheme = (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
                }
                if (savedTheme === 'dark') {
                    document.documentElement.setAttribute('data-theme', 'dark');
                } else {
                    document.documentElement.setAttribute('data-theme', 'light');
                }
            } catch (e) {}
        })();
    </script>
</head>
<body class="landing-body">

    <!-- Floating Glassy Top Navigation Bar (Reduced Width & Curved Sides) -->
    <div class="landing-navbar-wrapper">
        <header class="landing-navbar">
            <div class="landing-brand">
                <?php echo renderBethelDeskLogo('sm', '', 'index.php'); ?>
            </div>

            <nav class="landing-nav-links" aria-label="Main Navigation">
                <a href="#features">Features</a>
                <a href="#how-it-works">How It Works</a>
                <a href="#metrics">Impact</a>
                <a href="#reviews">Reviews</a>
                <a href="#security">Security</a>
            </nav>

            <div style="display: flex; align-items: center; gap: 10px;">
                <button type="button" id="themeToggleBtn" class="theme-toggle-btn" aria-label="Toggle theme" title="Switch between dark and light mode">
                    <i class="ph ph-moon theme-moon-icon"></i>
                    <i class="ph ph-sun theme-sun-icon"></i>
                </button>
                <?php if ($isUserLoggedIn): ?>
                    <a href="dashboard.php" class="btn btn-gold btn-sm">
                        <i class="ph ph-squares-four"></i> Dashboard
                    </a>
                    <a href="logout.php" class="btn btn-ghost btn-sm">
                        <i class="ph ph-sign-out"></i> Logout
                    </a>
                <?php elseif ($isAdminLoggedIn): ?>
                    <a href="admin_dashboard.php" class="btn btn-gold btn-sm">
                        <i class="ph ph-shield-check"></i> Admin Console
                    </a>
                    <a href="logout.php" class="btn btn-ghost btn-sm">
                        <i class="ph ph-sign-out"></i> Logout
                    </a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-ghost btn-sm">
                        <i class="ph ph-sign-in"></i> Sign In
                    </a>
                    <a href="register.php" class="btn btn-gold btn-sm">
                        <i class="ph ph-user-plus"></i> Get Started
                    </a>
                <?php endif; ?>
            </div>
        </header>
    </div>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-inner">
            <div class="hero-badge">
                <i class="ph-bold ph-sparkle" style="color: #F59E0B;"></i>
                <span>BethelDesk 2.4</span> &bull; <span>Autonomous &amp; Collaborative Support</span>
            </div>

            <h1 class="hero-title">
                <span class="hero-title-customer">Customer Care</span> <span class="hero-title-support">Support</span>, <span class="hero-rotator-wrap"><span class="hero-title-highlight hero-rotating-word" id="heroRotatingWord">elevated &amp; simplified.</span></span>
            </h1>

            <p class="hero-subtitle">
                Resolve inquiries faster, maintain complete lifecycle visibility, and deliver 5-star customer satisfaction with BethelDesk's intelligent support suite.
            </p>

            <div class="hero-cta-group">
                <?php if ($isUserLoggedIn): ?>
                    <a href="dashboard.php" class="btn btn-gold btn-lg">
                        <i class="ph ph-arrow-right"></i> Open Customer Portal
                    </a>
                <?php elseif ($isAdminLoggedIn): ?>
                    <a href="admin_dashboard.php" class="btn btn-gold btn-lg">
                        <i class="ph ph-arrow-right"></i> Open Agent Workspace
                    </a>
                <?php else: ?>
                    <a href="register.php" class="btn btn-gold btn-lg">
                        <i class="ph-bold ph-lightning"></i> Get Started Free
                    </a>
                    <a href="login.php" class="btn btn-secondary btn-lg">
                        <i class="ph ph-sign-in"></i> Customer Sign In
                    </a>
                    <a href="login.php?type=admin" class="btn btn-ghost btn-lg">
                        <i class="ph ph-shield-check"></i> Agent Portal
                    </a>
                <?php endif; ?>
            </div>

            <!-- Social Proof & Trust Strip -->
            <div class="hero-trust-bar">
                <div class="hero-trust-item">
                    <i class="ph-fill ph-star gold"></i>
                    <i class="ph-fill ph-star gold"></i>
                    <i class="ph-fill ph-star gold"></i>
                    <i class="ph-fill ph-star gold"></i>
                    <i class="ph-fill ph-star gold"></i>
                    <span><strong>4.9/5</strong> CSAT Score</span>
                </div>
                <span class="hero-trust-separator">&bull;</span>
                <div class="hero-trust-item">
                    <i class="ph-bold ph-lightning gold"></i>
                    <span><strong>&lt; 30m</strong> Fast Resolution</span>
                </div>
                <span class="hero-trust-separator">&bull;</span>
                <div class="hero-trust-item">
                    <i class="ph-bold ph-shield-check gold"></i>
                    <span><strong>100%</strong> Data Protection</span>
                </div>
                <span class="hero-trust-separator">&bull;</span>
                <div class="hero-trust-item">
                    <i class="ph-bold ph-ticket gold"></i>
                    <span><strong>14,800+</strong> Handled Requests</span>
                </div>
            </div>

            <!-- Interactive Visual Preview Mockup with Ambient Glow Backdrop -->
            <div class="hero-preview-container">
                <div class="hero-preview-glow" aria-hidden="true"></div>
                <div class="hero-preview-wrap">
                    <div class="mockup-header">
                        <div class="mockup-dots">
                            <span class="dot dot-red"></span>
                            <span class="dot dot-yellow"></span>
                            <span class="dot dot-green"></span>
                        </div>
                        <div class="mockup-search-bar">
                            <i class="ph ph-lock-simple" style="color: #F59E0B;"></i> betheldesk.app/portal/dashboard
                        </div>
                        <div class="mockup-status">
                            <span class="mockup-live-indicator"></span> Live System Active
                        </div>
                    </div>

                    <!-- High-Res Dashboard Preview Stage with Floating Badges -->
                    <div class="hero-image-stage">
                        <img 
                            src="uploads/saas_dashboard_preview.jpg" 
                            alt="BethelDesk Enterprise Customer Support Dashboard Preview" 
                            class="hero-dashboard-img"
                            loading="eager"
                            decoding="async"
                        >

                        <!-- Floating Micro-Widget 1 (SLA Target) -->
                        <div class="hero-floating-card hero-float-sla">
                            <div class="float-icon-gold">
                                <i class="ph-bold ph-lightning"></i>
                            </div>
                            <div>
                                <div style="font-weight: 700; color: #F59E0B; line-height: 1.2;">SLA Target 100%</div>
                                <div style="font-size: 11px; opacity: 0.85;">All open tickets assigned &amp; moving</div>
                            </div>
                        </div>

                        <!-- Floating Micro-Widget 2 (CSAT Verified) -->
                        <div class="hero-floating-card hero-float-csat">
                            <div class="float-icon-gold">
                                <i class="ph-fill ph-star"></i>
                            </div>
                            <div>
                                <div style="font-weight: 700; color: #F59E0B; line-height: 1.2;">5.0 Star Rating</div>
                                <div style="font-size: 11px; opacity: 0.85;">&ldquo;Agent resolved our inquiry in 18 mins!&rdquo;</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Live Performance Metrics Section (Linear / Stripe Concept) -->
    <section class="metrics-section" id="metrics">
        <div class="section-container">
            <div class="metrics-grid">
                <div class="metric-card">
                    <span class="metric-value">&lt; 42m</span>
                    <span class="metric-label">Average First Response</span>
                    <span class="metric-sub">Fast automated routing &amp; live agent dispatch</span>
                </div>
                <div class="metric-card">
                    <span class="metric-value">99.4%</span>
                    <span class="metric-label">Customer Satisfaction</span>
                    <span class="metric-sub">Verified 5-star post-resolution feedback ratings</span>
                </div>
                <div class="metric-card">
                    <span class="metric-value">14,800+</span>
                    <span class="metric-label">Inquiries Resolved</span>
                    <span class="metric-sub">Across technical, billing, and account categories</span>
                </div>
                <div class="metric-card">
                    <span class="metric-value">99.98%</span>
                    <span class="metric-label">Platform Availability</span>
                    <span class="metric-sub">High-availability uptime and audit-ready data retention</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section" id="features">
        <div class="section-container">
            <div class="section-header">
                <div class="section-badge">
                    <i class="ph-bold ph-sparkle" style="color: #F59E0B;"></i> Standout Capabilities
                </div>
                <h2 class="section-title">Everything your team needs to deliver standout care</h2>
                <p class="section-desc">Designed from the ground up for speed, transparency, and dependable resolution tracking.</p>
            </div>

            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon-box icon-box-blue">
                        <i class="ph ph-stack"></i>
                    </div>
                    <h3 class="feature-title">Multi-Category Smart Routing</h3>
                    <p class="feature-text">Categorize requests across Technical, Billing, Account Security, and Customer Service with granular priority levels.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon-box icon-box-amber">
                        <i class="ph ph-paperclip"></i>
                    </div>
                    <h3 class="feature-title">File &amp; Screenshot Attachments</h3>
                    <p class="feature-text">Upload JPG, PNG, and PDF documentation with instantaneous client-side preview and modal lightbox inspection.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon-box icon-box-emerald">
                        <i class="ph ph-envelope-simple"></i>
                    </div>
                    <h3 class="feature-title">Automated Email Notifications</h3>
                    <p class="feature-text">High-deliverability transactional emails powered by PHPMailer keep customers informed at every step of resolution.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon-box icon-box-purple">
                        <i class="ph ph-arrow-counter-clockwise"></i>
                    </div>
                    <h3 class="feature-title">1-Click Ticket Reopening</h3>
                    <p class="feature-text">If a resolution doesn't fully solve your issue, reopen the ticket with a single click to alert agents immediately.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon-box icon-box-amber">
                        <i class="ph-fill ph-star" style="color: #F59E0B;"></i>
                    </div>
                    <h3 class="feature-title">Customer Satisfaction (CSAT)</h3>
                    <p class="feature-text">Interactive 5-star rating widget with written feedback, score revisions, and automatic CSAT aggregation analytics.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon-box icon-box-cyan">
                        <i class="ph ph-receipt"></i>
                    </div>
                    <h3 class="feature-title">Official Printable Receipts</h3>
                    <p class="feature-text">Generate formal, audit-ready PDF/print receipts with complete incident metadata and agent sign-off.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section class="how-it-works-section" id="how-it-works">
        <div class="section-container">
            <div class="section-header">
                <div class="section-badge">
                    <i class="ph ph-check-square-offset"></i> 4-Step Process
                </div>
                <h2 class="section-title">How BethelDesk Works</h2>
                <p class="section-desc">A straightforward 4-step workflow that keeps you and your support agents aligned.</p>
            </div>

            <div class="steps-grid-wrapper">
                <div class="steps-connector-bar" aria-hidden="true"></div>
                <div class="steps-grid">
                    <!-- Step 1 -->
                    <a href="<?php echo $isUserLoggedIn ? 'dashboard.php' : 'register.php'; ?>" class="step-card step-card-interactive" title="<?php echo $isUserLoggedIn ? 'Go to your portal dashboard' : 'Create your free BethelDesk account'; ?>">
                        <div class="step-header-row">
                            <div class="step-num-badge step-num-1">1</div>
                            <i class="ph ph-user-plus step-icon-badge"></i>
                        </div>
                        <h4 class="step-title">Create an Account</h4>
                        <p class="step-desc">Register securely with your email and password. Your personal ticket portal is provisioned instantly.</p>
                        <span class="step-action-prompt">
                            <?php echo $isUserLoggedIn ? 'Go to dashboard' : 'Create free account'; ?> <i class="ph ph-arrow-right"></i>
                        </span>
                    </a>

                    <!-- Step 2 -->
                    <a href="<?php echo $isUserLoggedIn ? 'submit_ticket.php' : 'login.php?return_to=submit_ticket.php'; ?>" class="step-card step-card-interactive" title="Submit a support ticket">
                        <div class="step-header-row">
                            <div class="step-num-badge step-num-2">2</div>
                            <i class="ph ph-paper-plane-tilt step-icon-badge"></i>
                        </div>
                        <h4 class="step-title">Submit Ticket</h4>
                        <p class="step-desc">Select category, set urgency, provide details, and attach error logs or screenshots with live preview.</p>
                        <span class="step-action-prompt">
                            Submit ticket <i class="ph ph-arrow-right"></i>
                        </span>
                    </a>

                    <!-- Step 3 -->
                    <div class="step-card">
                        <div class="step-header-row">
                            <div class="step-num-badge step-num-3">3</div>
                            <i class="ph ph-headset step-icon-badge"></i>
                        </div>
                        <h4 class="step-title">Agent Collaborates</h4>
                        <p class="step-desc">Our dedicated support agents review, investigate, update ticket status, and provide detailed written answers.</p>
                        <span class="step-static-badge">
                            <i class="ph-bold ph-lightning" style="color: #F59E0B;"></i> Real-time Queue
                        </span>
                    </div>

                    <!-- Step 4 -->
                    <?php if ($isUserLoggedIn): ?>
                        <a href="view_tickets.php" class="step-card step-card-interactive" title="View your submitted complaints and ratings">
                            <div class="step-header-row">
                                <div class="step-num-badge step-num-4">4</div>
                                <i class="ph-fill ph-star step-icon-badge" style="color: #F59E0B;"></i>
                            </div>
                            <h4 class="step-title">Resolution &amp; Feedback</h4>
                            <p class="step-desc">Review the solution, print your official receipt, rate your agent, or reopen if you need further clarification.</p>
                            <span class="step-action-prompt">
                                Track your tickets <i class="ph ph-arrow-right"></i>
                            </span>
                        </a>
                    <?php else: ?>
                        <div class="step-card">
                            <div class="step-header-row">
                                <div class="step-num-badge step-num-4">4</div>
                                <i class="ph-fill ph-star step-icon-badge" style="color: #F59E0B;"></i>
                            </div>
                            <h4 class="step-title">Resolution &amp; Feedback</h4>
                            <p class="step-desc">Review the solution, print your official receipt, rate your agent, or reopen if you need further clarification.</p>
                            <span class="step-static-badge">
                                <i class="ph-fill ph-star" style="color: #F59E0B;"></i> 100% CSAT Goal
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Customer Stories & Testimonials Section (Stripe / Raycast Concept) -->
    <section class="testimonials-section" id="reviews">
        <div class="section-container">
            <div class="section-header">
                <div class="section-badge">
                    <i class="ph-fill ph-star" style="color: #F59E0B;"></i> Customer Experiences
                </div>
                <h2 class="section-title">Loved by customers &amp; support teams</h2>
                <p class="section-desc">See how companies rely on BethelDesk to streamline communication and resolve tickets faster.</p>
            </div>

            <div class="testimonials-grid">
                <div class="testimonial-card">
                    <div class="testimonial-stars">
                        <i class="ph-fill ph-star"></i>
                        <i class="ph-fill ph-star"></i>
                        <i class="ph-fill ph-star"></i>
                        <i class="ph-fill ph-star"></i>
                        <i class="ph-fill ph-star"></i>
                    </div>
                    <p class="testimonial-quote">
                        &ldquo;BethelDesk cut our team's resolution bottlenecks in half. Our customers love the instant screenshot preview and the clear real-time status updates.&rdquo;
                    </p>
                    <div class="testimonial-author">
                        <div class="testimonial-avatar">SM</div>
                        <div class="testimonial-info">
                            <h5>Sarah Morrison</h5>
                            <p>VP of Customer Experience, ApexFlow</p>
                        </div>
                    </div>
                </div>

                <div class="testimonial-card">
                    <div class="testimonial-stars">
                        <i class="ph-fill ph-star"></i>
                        <i class="ph-fill ph-star"></i>
                        <i class="ph-fill ph-star"></i>
                        <i class="ph-fill ph-star"></i>
                        <i class="ph-fill ph-star"></i>
                    </div>
                    <p class="testimonial-quote">
                        &ldquo;The 1-click reopen feature and CSAT star rating system give us immediate feedback on agent performance. It's the most reliable helpdesk we've ever used.&rdquo;
                    </p>
                    <div class="testimonial-author">
                        <div class="testimonial-avatar">DK</div>
                        <div class="testimonial-info">
                            <h5>David Kalu</h5>
                            <p>Operations Director, FinTrack Global</p>
                        </div>
                    </div>
                </div>

                <div class="testimonial-card">
                    <div class="testimonial-stars">
                        <i class="ph-fill ph-star"></i>
                        <i class="ph-fill ph-star"></i>
                        <i class="ph-fill ph-star"></i>
                        <i class="ph-fill ph-star"></i>
                        <i class="ph-fill ph-star"></i>
                    </div>
                    <p class="testimonial-quote">
                        &ldquo;Generating audit-ready PDF receipts with official agent sign-offs saved our accounting department countless hours every month. Clean, fast, and elegant.&rdquo;
                    </p>
                    <div class="testimonial-author">
                        <div class="testimonial-avatar">ER</div>
                        <div class="testimonial-info">
                            <h5>Elena Rodriguez</h5>
                            <p>Lead Systems Engineer, CloudSphere</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Security & Trust Section -->
    <section class="security-section" id="security">
        <div class="section-container">
            <div class="section-header">
                <div class="section-badge">
                    <i class="ph ph-shield-check"></i> Enterprise Reliability
                </div>
                <h2 class="section-title">Built with Enterprise Reliability</h2>
                <p class="section-desc">Engineered with robust security standards so your data stays protected.</p>
            </div>

            <div class="security-grid">
                <div class="security-card sec-emerald">
                    <div class="feature-icon-box" style="background: linear-gradient(135deg, #D1FAE5, #A7F3D0); color: #047857;">
                        <i class="ph ph-lock"></i>
                    </div>
                    <span class="security-badge-tag">Cryptographic Hash</span>
                    <h3 class="feature-title">Bcrypt Password Hashing</h3>
                    <p class="feature-text">Industry-standard cryptographic hashing ensures credentials are never stored in plaintext.</p>
                </div>

                <div class="security-card sec-blue">
                    <div class="feature-icon-box" style="background: linear-gradient(135deg, #F4F4F5, #E4E4E7); color: #09090B;">
                        <i class="ph ph-database"></i>
                    </div>
                    <span class="security-badge-tag">Zero SQL Injection</span>
                    <h3 class="feature-title">Prepared SQL Statements</h3>
                    <p class="feature-text">100% parameterized queries shield the entire database against SQL injection vulnerabilities.</p>
                </div>

                <div class="security-card sec-purple">
                    <div class="feature-icon-box" style="background: linear-gradient(135deg, #EDE9FE, #DDD6FE); color: #6D28D9;">
                        <i class="ph ph-shield-check"></i>
                    </div>
                    <span class="security-badge-tag">Strict Boundary Auth</span>
                    <h3 class="feature-title">Role-Based Access Control</h3>
                    <p class="feature-text">Strict boundary separation between customer portals and authenticated support agent consoles.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Banner -->
    <section class="cta-banner">
        <div class="section-container">
            <h2 class="cta-title">Ready to experience faster support?</h2>
            <p class="cta-desc">Get started now to submit your inquiries and track resolutions in real time.</p>
            <div class="cta-banner-actions">
                <a href="register.php" class="btn btn-gold btn-lg">
                    <i class="ph-bold ph-rocket-launch"></i> Create Free Account
                </a>
                <a href="login.php" class="btn btn-lg cta-banner-ghost">
                    <i class="ph ph-sign-in"></i> Sign In to Portal
                </a>
            </div>
        </div>
    </section>

    <!-- Landing Page Footer -->
    <footer class="landing-footer">
        <div class="section-container" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
            <div>
                <?php echo renderBethelDeskLogo('sm', '', 'index.php'); ?>
                <p style="margin: 8px 0 0; color: var(--text-muted); font-size: 13px;">
                    &copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved. &bull; Enterprise Customer Care
                </p>
            </div>
            <div style="display: flex; gap: 20px; align-items: center; font-size: 13.5px; flex-wrap: wrap;">
                <a href="login.php">Customer Login</a>
                <span>&bull;</span>
                <a href="register.php">Register</a>
                <span>&bull;</span>
                <a href="forgot_password.php">Forgot Password</a>
                <span>&bull;</span>
                <a href="login.php?type=admin" style="color: #F59E0B; font-weight: 600;">
                    <i class="ph ph-lock"></i> Staff Access
                </a>
            </div>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Theme toggle button
            var btn = document.getElementById('themeToggleBtn');
            if (btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    var current = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
                    var next = current === 'dark' ? 'light' : 'dark';
                    document.documentElement.setAttribute('data-theme', next);
                    try {
                        localStorage.setItem('bethel_theme', next);
                    } catch(err) {}
                });
            }

            // Headline rotating text animation (tasteful SaaS cadence)
            (function() {
                var el = document.getElementById('heroRotatingWord');
                if (!el) return;
                if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                    return;
                }
                var words = [
                    'elevated & simplified.',
                    'faster & transparent.',
                    'built for reliability.',
                    'seamless for teams.'
                ];
                var currentIndex = 0;
                setInterval(function() {
                    el.classList.add('rot-out');
                    setTimeout(function() {
                        currentIndex = (currentIndex + 1) % words.length;
                        el.textContent = words[currentIndex];
                        el.classList.remove('rot-out');
                        el.classList.add('rot-in');
                        setTimeout(function() {
                            el.classList.remove('rot-in');
                        }, 350);
                    }, 350);
                }, 3400);
            })();
        });
    </script>
</body>
</html>
