document.addEventListener('DOMContentLoaded', function() {
    
    // Find all password toggle buttons on the page
    const toggleButtons = document.querySelectorAll('.toggle-password-btn');

    toggleButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            // Find the <input> element. We assume it's the element
            // right before the button in the HTML.
            const passwordInput = this.previousElementSibling;
            
            // Find the <i> icon element inside the button
            const icon = this.querySelector('i');

            // Toggle the input type
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                // Change icon to "eye slash"
                icon.classList.remove('bi-eye-fill');
                icon.classList.add('bi-eye-slash-fill');
            } else {
                passwordInput.type = 'password';
                // Change icon back to "eye"
                icon.classList.remove('bi-eye-slash-fill');
                icon.classList.add('bi-eye-fill');
            }
        });
    });

    // Optional: Add a simple CSS rule to make the
    // button look more clickable
    const style = document.createElement('style');
    style.innerHTML = '.toggle-password-btn { cursor: pointer; }';
    document.head.appendChild(style);
});