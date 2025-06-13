// navbar.js
document.addEventListener('DOMContentLoaded', function() {
    // Toggle mobile menu
    const navbarToggle = document.getElementById('navbar-toggle');
    const navbarContent = document.getElementById('navbar-content');
    
    if (navbarToggle && navbarContent) {
        navbarToggle.addEventListener('click', function() {
            navbarToggle.classList.toggle('active');
            navbarContent.classList.toggle('active');
            
            // Prevent scroll when menu is open
            document.body.classList.toggle('menu-open');
        });
    }
    
    // Toggle user dropdown
    const userDropdown = document.querySelector('.user-dropdown');
    
    if (userDropdown) {
        const dropdownToggle = userDropdown.querySelector('.user-dropdown-toggle');
        
        dropdownToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            userDropdown.classList.toggle('active');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!userDropdown.contains(e.target)) {
                userDropdown.classList.remove('active');
            }
        });
    }
    
    // Close mobile menu when clicking a link
    const navLinks = document.querySelectorAll('.navbar-link');
    
    navLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (navbarContent.classList.contains('active')) {
                navbarToggle.classList.remove('active');
                navbarContent.classList.remove('active');
                document.body.classList.remove('menu-open');
            }
        });
    });
    
    // Add some small styles to body when menu open
    function addBodyStyles() {
        const style = document.createElement('style');
        style.textContent = `
            body.menu-open {
                overflow: hidden;
            }
            
            @media (max-width: 992px) {
                body.menu-open::after {
                    content: '';
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background-color: rgba(0, 0, 0, 0.5);
                    z-index: 99;
                }
            }
        `;
        document.head.appendChild(style);
    }
    
    addBodyStyles();
});