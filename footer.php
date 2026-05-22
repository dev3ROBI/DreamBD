<!-- Footer -->
<footer class="footer dream-footer" role="contentinfo">
    <!-- Decorative top wave -->
    <div class="footer-wave" aria-hidden="true">
        <svg viewBox="0 0 1440 80" preserveAspectRatio="none">
            <path d="M0,40 C360,120 1080,-40 1440,40 L1440,0 L0,0 Z" fill="currentColor"/>
        </svg>
    </div>

    <!-- Floating decoration circles -->
    <div class="footer-deco footer-deco-1" aria-hidden="true"></div>
    <div class="footer-deco footer-deco-2" aria-hidden="true"></div>

    <div class="container">
        <div class="footer-content">
            <!-- Company Info -->
            <div class="footer-section footer-brand-section">
                <a href="index.php?page=home" class="footer-logo" aria-label="DreamBD Home">
                    <span class="logo-icon">🚀</span>
                    <span class="logo-text">Dream<span class="highlight">BD</span></span>
                </a>
                <p class="footer-description">
                    Building premium digital solutions with modern design 
                    and cutting-edge technology since 2023.
                </p>
                <div class="social-links" aria-label="Social media links">
                    <a href="https://facebook.com" class="social-link social-facebook" target="_blank" rel="noopener noreferrer" aria-label="Facebook">
                        <i class="fab fa-facebook-f" aria-hidden="true"></i>
                    </a>
                    <a href="https://twitter.com" class="social-link social-twitter" target="_blank" rel="noopener noreferrer" aria-label="Twitter">
                        <i class="fab fa-twitter" aria-hidden="true"></i>
                    </a>
                    <a href="https://instagram.com" class="social-link social-instagram" target="_blank" rel="noopener noreferrer" aria-label="Instagram">
                        <i class="fab fa-instagram" aria-hidden="true"></i>
                    </a>
                    <a href="https://linkedin.com" class="social-link social-linkedin" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn">
                        <i class="fab fa-linkedin-in" aria-hidden="true"></i>
                    </a>
                    <a href="https://github.com" class="social-link social-github" target="_blank" rel="noopener noreferrer" aria-label="GitHub">
                        <i class="fab fa-github" aria-hidden="true"></i>
                    </a>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="footer-section">
                <h3 class="footer-title">Quick Links</h3>
                <ul class="footer-links" role="list">
                    <li><a href="index.php?page=home" data-page="home"><i class="fas fa-house"></i> Home</a></li>
                    <li><a href="index.php?page=community" data-page="community"><i class="fas fa-users"></i> Community</a></li>
                    <li><a href="index.php?page=products" data-page="products"><i class="fas fa-store"></i> Products</a></li>
                    <li><a href="index.php?page=tournaments" data-page="tournaments"><i class="fas fa-trophy"></i> Tournaments</a></li>
                    <li><a href="index.php?page=how-it-works" data-page="how-it-works"><i class="fas fa-circle-info"></i> How It Works</a></li>
                    <li><a href="index.php?page=faq" data-page="faq"><i class="fas fa-question-circle"></i> FAQ</a></li>
                </ul>
            </div>

            <!-- Services -->
            <div class="footer-section">
                <h3 class="footer-title">Our Services</h3>
                <ul class="footer-links" role="list">
                    <li><a href="#"><i class="fas fa-globe"></i> Web Development</a></li>
                    <li><a href="#"><i class="fas fa-mobile-alt"></i> Mobile Apps</a></li>
                    <li><a href="#"><i class="fas fa-paint-brush"></i> UI/UX Design</a></li>
                    <li><a href="#"><i class="fas fa-chart-line"></i> Digital Marketing</a></li>
                    <li><a href="#"><i class="fas fa-search"></i> SEO Services</a></li>
                </ul>
            </div>

            <!-- Contact & Newsletter -->
            <div class="footer-section">
                <h3 class="footer-title">Contact Us</h3>
                <div class="contact-info" role="list">
                    <div class="contact-item" role="listitem">
                        <i class="fas fa-map-marker-alt" aria-hidden="true"></i>
                        <address>Dhaka, Bangladesh</address>
                    </div>
                    <div class="contact-item" role="listitem">
                        <i class="fas fa-phone" aria-hidden="true"></i>
                        <a href="tel:+8801234567890">+880 1234 567890</a>
                    </div>
                    <div class="contact-item" role="listitem">
                        <i class="fas fa-envelope" aria-hidden="true"></i>
                        <a href="mailto:hello@dreambd.com">hello@dreambd.com</a>
                    </div>
                </div>
                
                <div class="newsletter">
                    <h4>Stay Updated</h4>
                    <form class="newsletter-form" id="footerNewsletterForm" aria-label="Newsletter subscription" novalidate>
                        <div class="newsletter-input-wrap">
                            <input type="email" placeholder="Your email address" aria-label="Email address" required>
                            <button type="submit" aria-label="Subscribe">
                                <i class="fas fa-paper-plane" aria-hidden="true"></i>
                            </button>
                        </div>
                        <div class="newsletter-feedback" id="newsletterFeedback" aria-live="polite"></div>
                    </form>
                    <p class="newsletter-note">No spam. Unsubscribe anytime.</p>
                </div>
            </div>
        </div>

        <!-- Footer Bottom -->
        <div class="footer-bottom">
            <p class="footer-copy">&copy; <span id="footerYear"><?= date('Y') ?></span> DreamBD. All rights reserved.</p>
            <div class="footer-trust">
                <span class="trust-badge"><i class="fas fa-shield-alt"></i> Secure</span>
                <span class="trust-badge"><i class="fas fa-lock"></i> Privacy</span>
                <span class="trust-badge"><i class="fas fa-clock"></i> 24/7 Support</span>
            </div>
            <div class="footer-bottom-links">
                <a href="privacy.php">Privacy Policy</a>
                <a href="terms.php">Terms of Service</a>
                <a href="cookies.php">Cookie Policy</a>
            </div>
        </div>
    </div>
</footer>
