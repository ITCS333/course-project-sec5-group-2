let assignments = [];

const assignmentForm   = document.getElementById('assignment-form');
const assignmentsTbody = document.getElementById('assignments-tbody');

function createAssignmentRow(assignment) {
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td>${assignment.title}</td>
    <td>${assignment.due_date}</td>
    <td>${assignment.description}</td>
    <td>
      <button class="edit-btn"   data-id="${assignment.id}">Edit</button>
      <button class="delete-btn" data-id="${assignment.id}">Delete</button>
    </td>
  `;
  return tr;
}

function renderTable() {
  assignmentsTbody.innerHTML = '';
  assignments.forEach(assignment => {
    assignmentsTbody.appendChild(createAssignmentRow(assignment));
  });
}

async function handleAddAssignment(event) {
  event.preventDefault();

  const title       = document.getElementById('assignment-title').value.trim();
  const due_date    = document.getElementById('assignment-due-date').value;
  const description = document.getElementById('assignment-description').value.trim();
  const files       = document.getElementById('assignment-files').value
                        .split('\n')
                        .map(f => f.trim())
                        .filter(f => f !== '');

  const submitBtn = document.getElementById('add-assignment');
  const editId    = submitBtn.dataset.editId;

  if (editId) {
    await handleUpdateAssignment(editId, { title, due_date, description, files });
    return;
  }

  const response = await fetch('./api/index.php', {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify({ title, due_date, description, files })
  });

  const result = await response.json();
  if (result.success) {
    assignments.push({ id: result.id, title, due_date, description, files });
    renderTable();
    assignmentForm.reset();
  }
}

async function handleUpdateAssignment(id, fields) {
  const response = await fetch('./api/index.php', {
    method:  'PUT',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify({ id, ...fields })
  });

  const result = await response.json();
  if (result.success) {
    assignments = assignments.map(a => a.id == id ? { ...a, ...fields } : a);
    renderTable();
    assignmentForm.reset();
    const submitBtn = document.getElementById('add-assignment');
    submitBtn.textContent = 'Add Assignment';
    delete submitBtn.dataset.editId;
  }
}

async function handleTableClick(event) {
  const target = event.target;
  const id     = target.dataset.id;

  if (target.classList.contains('delete-btn')) {
    const response = await fetch(`./api/index.php?id=${id}`, { method: 'DELETE' });
    const result   = await response.json();
    if (result.success) {
      assignments = assignments.filter(a => a.id != id);
      renderTable();
    }
  }

  if (target.classList.contains('edit-btn')) {
    const assignment = assignments.find(a => a.id == id);
    if (!assignment) return;

    document.getElementById('assignment-title').value       = assignment.title;
    document.getElementById('assignment-due-date').value    = assignment.due_date;
    document.getElementById('assignment-description').value = assignment.description;
    document.getElementById('assignment-files').value       = assignment.files.join('\n');

    const submitBtn = document.getElementById('add-assignment');
    submitBtn.textContent    = 'Update Assignment';
    submitBtn.dataset.editId = assignment.id;
  }
}

async function loadAndInitialize() {
  const response = await fetch('./api/index.php');
  const result   = await response.json();

  if (result.success) {
    assignments = result.data;
    renderTable();
  }

  assignmentForm.addEventListener('submit', handleAddAssignment);
  assignmentsTbody.addEventListener('click', handleTableClick);
}

loadAndInitialize();