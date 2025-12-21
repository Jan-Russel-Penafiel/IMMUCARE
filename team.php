<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Our Team - IMMUCARE</title>
    <link rel="icon" href="images/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <header>
        <nav>
            <div class="container">
                <div class="logo">
                    <img src="images/logo.svg" alt="ImmuCare Logo">
                    <h1>ImmuCare</h1>
                </div>
                <ul class="nav-links">
                    <li><a href="index.html#features">Features</a></li>
                    <li><a href="index.html#how-it-works">How It Works</a></li>
                    <li><a href="index.html#benefits">Benefits</a></li>
                    <li><a href="index.html#contact">Contact</a></li>
                    <li><a href="login.php" class="btn btn-secondary">Login</a></li>
                    <li><a href="register.php" class="btn btn-primary">Register</a></li>
                </ul>
                <div class="hamburger">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
        </nav>
    </header>

    <section class="features" style="padding-top: 50px;">
        <div class="container">
            <h2 class="section-title">Meet Our Team</h2>
            <p style="text-align: center; max-width: 800px; margin: -20px auto 50px; color: var(--light-text); font-size: 1.1rem;">Dedicated professionals committed to improving immunization management</p>
            
            <p style="text-align: center; max-width: 900px; margin: 0 auto 50px; color: var(--text-color);">ImmuCare is powered by a team of experienced healthcare professionals, software engineers, and public health experts who are passionate about making a difference in community health.</p>
            
            <div class="feature-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-user-md"></i>
                    </div>
                    <h3>Dr. Maria Santos</h3>
                    <p style="color: var(--secondary-color); font-weight: 600; margin-bottom: 10px;">Chief Medical Officer</p>
                    <p>With over 15 years of experience in pediatric medicine and immunization programs, Dr. Santos leads our medical advisory team to ensure ImmuCare meets the highest standards of healthcare delivery.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-laptop-code"></i>
                    </div>
                    <h3>Juan Dela Cruz</h3>
                    <p style="color: var(--secondary-color); font-weight: 600; margin-bottom: 10px;">Chief Technology Officer</p>
                    <p>A seasoned software architect with expertise in healthcare systems, Juan oversees the development and security of the ImmuCare platform.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-heartbeat"></i>
                    </div>
                    <h3>Dr. Ana Reyes</h3>
                    <p style="color: var(--secondary-color); font-weight: 600; margin-bottom: 10px;">Director of Public Health</p>
                    <p>Dr. Reyes brings extensive experience in public health initiatives and vaccination campaigns, helping shape ImmuCare's impact on community health.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <h3>Carlos Mendoza</h3>
                    <p style="color: var(--secondary-color); font-weight: 600; margin-bottom: 10px;">Operations Manager</p>
                    <p>Carlos ensures smooth operations and excellent customer support, working closely with healthcare facilities to optimize their use of ImmuCare.</p>
                </div>
            </div>
        </div>
    </section>
    
    <section class="benefits" style="background-color: var(--bg-light);">
        <div class="container">
            <h2 class="section-title">Our Teams</h2>
            <div class="benefits-grid">
                <div class="benefit-card">
                    <h3>Development Team</h3>
                    <p>Our talented development team works continuously to enhance ImmuCare's features, improve user experience, and integrate the latest healthcare technologies.</p>
                </div>
                <div class="benefit-card">
                    <h3>Support Team</h3>
                    <p>Our dedicated support team is available to assist healthcare providers and patients, ensuring that everyone can make the most of ImmuCare's capabilities.</p>
                </div>
                <div class="benefit-card">
                    <h3>Join Our Team</h3>
                    <p>We're always looking for passionate individuals who want to make a difference in healthcare. Check out our <a href="careers.php" style="color: var(--primary-color); font-weight: 600;">career opportunities</a> to learn more!</p>
                </div>
            </div>
        </div>
    </section>

    <section class="cta">
        <div class="container">
            <h2>Work With Us</h2>
            <p>Be part of a team that's transforming immunization management.</p>
            <a href="careers.php" class="btn btn-primary">View Career Opportunities</a>
        </div>
    </section>

    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-logo">
                    <img src="images/logo.svg" alt="ImmuCare Logo">
                    <h2>ImmuCare</h2>
                    <p>Smart Immunization Registry with Automated Scheduling System and Alerts</p>
                </div>
                <div class="footer-links">
                    <div class="link-group">
                        <h3>Company</h3>
                        <ul>
                            <li><a href="about.php">About Us</a></li>
                            <li><a href="team.php">Our Team</a></li>
                            <li><a href="careers.php">Careers</a></li>
                            <li><a href="news.php">News</a></li>
                        </ul>
                    </div>
                    <div class="link-group">
                        <h3>Resources</h3>
                        <ul>
                            <li><a href="faqs.php">FAQs</a></li>
                            <li><a href="documentation.php">Documentation</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="copyright">
                <p>&copy; 2023 ImmuCare. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="script.js"></script>
</body>
</html>
