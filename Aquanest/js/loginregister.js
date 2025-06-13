// loginregister.js
document.addEventListener("DOMContentLoaded", function() {
    const container = document.getElementById('container');
    const registerBtn = document.getElementById('register');
    const loginBtn = document.getElementById('login');
    
    if (registerBtn) {
        registerBtn.addEventListener('click', () => {
            container.classList.add("right-panel-active");
        });
    }
    
    if (loginBtn) {
        loginBtn.addEventListener('click', () => {
            container.classList.remove("right-panel-active");
        });
    }
    
    // Check if there's any URL parameter to switch panels
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('mode') && urlParams.get('mode') === 'register') {
        container.classList.add("right-panel-active");
    }
});