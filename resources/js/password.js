        function togglePassword() {
            const input = document.getElementById('password');
            const icon = document.getElementById('eyeIcon');
            const isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            icon.innerHTML = isHidden
                ? '<path d="M3 3l18 18M10.6 10.6a2 2 0 002.8 2.8M9.9 5.1A9.7 9.7 0 0112 5c6.5 0 10 7 10 7a15.6 15.6 0 01-4 4.6M6.3 6.3A15.9 15.9 0 002 12s3.5 7 10 7a9.7 9.7 0 004.1-.9"/>'
                : '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/>';
        }
