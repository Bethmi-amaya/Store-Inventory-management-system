const form = document.getElementById('loginForm');
const emailInput = document.getElementById('email');
const passwordInput = document.getElementById('password');
const message = document.getElementById('formMessage');

const setMessage = (text, type) => {
  if (!message) return;
  message.textContent = text;
  message.className = `form-message ${type}`;
};

const query = new URLSearchParams(window.location.search);
if (query.get('error') === '1') {
  setMessage('Invalid email or password. Please try again.', 'error');
}

if (form && emailInput && passwordInput) {
  form.addEventListener('submit', (event) => {
    const email = emailInput.value.trim();
    const password = passwordInput.value.trim();

    if (!email || !password) {
      event.preventDefault();
      setMessage('Please enter both your email and password.', 'error');
      return;
    }

    const validEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    if (!validEmail) {
      event.preventDefault();
      setMessage('Please use a valid email address.', 'error');
      return;
    }

    if (password.length < 6) {
      event.preventDefault();
      setMessage('Password must be at least 6 characters long.', 'error');
      return;
    }

    setMessage('Checking credentials...', 'success');
  });
}
