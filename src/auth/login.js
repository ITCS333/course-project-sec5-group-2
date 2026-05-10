function displayMessage(message, type) {
  const container = document.getElementById('message-container');
  container.textContent = message;
  container.className = type;
}

function isValidEmail(email) {
  const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return regex.test(email);
}

function isValidPassword(password) {
  return password.length >= 8;
}

async function handleLogin(event) {
  event.preventDefault();

  const email = document.getElementById('email').value.trim();
  const password = document.getElementById('password').value.trim();

  if (!isValidEmail(email)) {
    displayMessage('Please enter a valid email address.', 'error');
    return;
  }

  if (!isValidPassword(password)) {
    displayMessage('Password must be at least 8 characters.', 'error');
    return;
  }

  try {
    const response = await fetch('./api/index.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email, password })
    });

    const result = await response.json();

    if (result.success) {
      displayMessage('Login successful! Redirecting...', 'success');
      setTimeout(() => {
        window.location.href = '../../index.html';
      }, 1000);
    } else {
      displayMessage(result.message || 'Invalid email or password.', 'error');
    }
  } catch (error) {
    displayMessage('Connection error. Please try again.', 'error');
  }
}

function setupLoginForm() {
  const form = document.getElementById('login-form');
  if (form) {
    form.addEventListener('submit', handleLogin);
  }
}

setupLoginForm();