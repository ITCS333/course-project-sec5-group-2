let users = [];

const userTableBody = document.getElementById('user-table-body');
const addUserForm = document.getElementById('add-user-form');
const changePasswordForm = document.getElementById('password-form');
const searchInput = document.getElementById('search-input');
const tableHeaders = document.querySelectorAll('#user-table thead th');

function createUserRow(user) {
  const tr = document.createElement('tr');
  tr.innerHTML = `
        <td>${user.name}</td>
        <td>${user.email}</td>
        <td>${user.is_admin == 1 ? 'Yes' : 'No'}</td>
        <td>
            <button class="edit-btn"   data-id="${user.id}">Edit</button>
            <button class="delete-btn" data-id="${user.id}">Delete</button>
        </td>
    `;
  return tr;
}

function renderTable(userArray) {
  userTableBody.innerHTML = '';
  userArray.forEach(user => {
    userTableBody.appendChild(createUserRow(user));
  });
}

function handleChangePassword(event) {
  event.preventDefault();

  const currentPassword = document.getElementById('current-password').value;
  const newPassword = document.getElementById('new-password').value;
  const confirmPassword = document.getElementById('confirm-password').value;

  if (newPassword !== confirmPassword) {
    alert('Passwords do not match.');
    return;
  }

  if (newPassword.length < 8) {
    alert('Password must be at least 8 characters.');
    return;
  }

  fetch('../api/index.php?action=change_password', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      id: 1,
      current_password: currentPassword,
      new_password: newPassword
    })
  })
    .then(res => res.json())
    .then(result => {
      if (result.success) {
        alert('Password updated successfully!');
        document.getElementById('current-password').value = '';
        document.getElementById('new-password').value = '';
        document.getElementById('confirm-password').value = '';
      } else {
        alert(result.message || 'Failed to update password.');
      }
    })
    .catch(() => alert('Connection error.'));
}

function handleAddUser(event) {
  event.preventDefault();

  const name = document.getElementById('user-name').value.trim();
  const email = document.getElementById('user-email').value.trim();
  const password = document.getElementById('default-password').value.trim();
  const is_admin = document.getElementById('is-admin').value;

  if (!name || !email || !password) {
    alert('Please fill out all required fields.');
    return;
  }

  if (password.length < 8) {
    alert('Password must be at least 8 characters.');
    return;
  }

  fetch('../api/index.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ name, email, password, is_admin })
  })
    .then(res => res.json())
    .then(result => {
      if (result.success) {
        loadUsersAndInitialize();
        addUserForm.reset();
      } else {
        alert(result.message || 'Failed to add user.');
      }
    })
    .catch(() => alert('Connection error.'));
}

function handleTableClick(event) {
  const target = event.target;
  const id = target.dataset.id;

  if (target.classList.contains('delete-btn')) {
    fetch('../api/index.php?id=' + id, { method: 'DELETE' })
      .then(res => res.json())
      .then(result => {
        if (result.success) {
          users = users.filter(u => u.id != id);
          renderTable(users);
        } else {
          alert(result.message || 'Failed to delete user.');
        }
      })
      .catch(() => alert('Connection error.'));
  }

  if (target.classList.contains('edit-btn')) {
    const user = users.find(u => u.id == id);
    if (!user) return;
    const newName = prompt('Enter new name:', user.name);
    if (!newName) return;

    fetch('../api/index.php', {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: user.id, name: newName })
    })
      .then(res => res.json())
      .then(result => {
        if (result.success) {
          users = users.map(u => u.id == id ? { ...u, name: newName } : u);
          renderTable(users);
        } else {
          alert(result.message || 'Failed to update user.');
        }
      })
      .catch(() => alert('Connection error.'));
  }
}

function handleSearch(event) {
  const term = event.target.value.toLowerCase();
  if (!term) {
    renderTable(users);
    return;
  }
  const filtered = users.filter(u =>
    u.name.toLowerCase().includes(term) ||
    u.email.toLowerCase().includes(term)
  );
  renderTable(filtered);
}

function handleSort(event) {
  const th = event.currentTarget;
  const index = th.cellIndex;
  const keys = ['name', 'email', 'is_admin'];
  const key = keys[index];

  if (!key) return;

  const dir = th.dataset.sortDir === 'asc' ? 'desc' : 'asc';
  th.dataset.sortDir = dir;

  users.sort((a, b) => {
    if (key === 'is_admin') {
      return dir === 'asc' ? a[key] - b[key] : b[key] - a[key];
    }
    return dir === 'asc'
      ? a[key].localeCompare(b[key])
      : b[key].localeCompare(a[key]);
  });

  renderTable(users);
}

loadUsersAndInitialize._listenersAttached = false;

async function loadUsersAndInitialize() {
  try {
    const response = await fetch('../api/index.php');
    if (!response.ok) {
      console.error('Failed to fetch users');
      alert('Failed to load users.');
      return;
    }
    const result = await response.json();
    users = result.data ?? [];
    renderTable(users);

    if (!loadUsersAndInitialize._listenersAttached) {
      loadUsersAndInitialize._listenersAttached = true;

      changePasswordForm.addEventListener('submit', handleChangePassword);
      addUserForm.addEventListener('submit', handleAddUser);
      userTableBody.addEventListener('click', handleTableClick);
      searchInput.addEventListener('input', handleSearch);
      tableHeaders.forEach(th => th.addEventListener('click', handleSort));
    }
  } catch (error) {
    console.error(error);
    alert('Connection error.');
  }
}

loadUsersAndInitialize();