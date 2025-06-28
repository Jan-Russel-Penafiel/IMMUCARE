document.addEventListener('DOMContentLoaded', function() {
    // Mobile Navigation Toggle
    const hamburger = document.querySelector('.hamburger');
    const navLinks = document.querySelector('.nav-links');
    
    if (hamburger) {
        hamburger.addEventListener('click', function() {
            navLinks.classList.toggle('active');
            
            // Animate hamburger to X
            const spans = hamburger.querySelectorAll('span');
            spans.forEach(span => span.classList.toggle('active'));
        });
    }
    
    // Smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            if (this.getAttribute('href') !== '#') {
                e.preventDefault();
                
                const targetId = this.getAttribute('href');
                const targetElement = document.querySelector(targetId);
                
                if (targetElement) {
                    // Close mobile menu if open
                    if (navLinks.classList.contains('active')) {
                        navLinks.classList.remove('active');
                        hamburger.querySelectorAll('span').forEach(span => span.classList.remove('active'));
                    }
                    
                    window.scrollTo({
                        top: targetElement.offsetTop - 80, // Offset for fixed header
                        behavior: 'smooth'
                    });
                }
            }
        });
    });
    
    // Form submission handling
    const contactForm = document.querySelector('.contact-form form');
    if (contactForm) {
        contactForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Get form data
            const name = document.getElementById('name').value;
            const email = document.getElementById('email').value;
            const subject = document.getElementById('subject').value;
            const message = document.getElementById('message').value;
            
            // Simple validation
            if (!name || !email || !subject || !message) {
                alert('Please fill in all fields');
                return;
            }
            
            // Here you would normally send the form data to a server
            // For demo purposes, we'll just show a success message
            
            // Clear form
            contactForm.reset();
            
            // Show success message
            alert('Thank you for your message. We will get back to you soon!');
        });
    }
    
    // Scroll reveal animations
    const revealElements = document.querySelectorAll('.feature-card, .step, .benefit-card, .testimonial');
    
    const revealOnScroll = function() {
        revealElements.forEach(element => {
            const elementTop = element.getBoundingClientRect().top;
            const windowHeight = window.innerHeight;
            
            if (elementTop < windowHeight - 100) {
                element.classList.add('revealed');
            }
        });
    };
    
    // Add CSS class for animation
    const style = document.createElement('style');
    style.textContent = `
        .feature-card, .step, .benefit-card, .testimonial {
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.6s ease, transform 0.6s ease;
        }
        
        .feature-card.revealed, .step.revealed, .benefit-card.revealed, .testimonial.revealed {
            opacity: 1;
            transform: translateY(0);
        }
    `;
    document.head.appendChild(style);
    
    // Initial check and add scroll event listener
    revealOnScroll();
    window.addEventListener('scroll', revealOnScroll);
    
    // Sticky header on scroll
    const header = document.querySelector('header');
    const nav = document.querySelector('nav');
    let lastScrollTop = 0;
    
    window.addEventListener('scroll', function() {
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        
        if (scrollTop > 100) {
            nav.classList.add('sticky');
            
            // Add CSS for sticky header
            if (!document.querySelector('#sticky-header-styles')) {
                const stickyStyle = document.createElement('style');
                stickyStyle.id = 'sticky-header-styles';
                stickyStyle.textContent = `
                    nav.sticky {
                        position: fixed;
                        top: 0;
                        left: 0;
                        right: 0;
                        background-color: white;
                        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
                        z-index: 1000;
                        padding: 10px 0;
                        transition: padding 0.3s ease;
                    }
                    
                    nav.sticky + .hero {
                        padding-top: 80px;
                    }
                `;
                document.head.appendChild(stickyStyle);
            }
        } else {
            nav.classList.remove('sticky');
        }
        
        // Hide/show header on scroll up/down
        if (scrollTop > lastScrollTop && scrollTop > 200) {
            // Scrolling down
            nav.style.transform = 'translateY(-100%)';
        } else {
            // Scrolling up
            nav.style.transform = 'translateY(0)';
        }
        
        lastScrollTop = scrollTop;
    });
}); 