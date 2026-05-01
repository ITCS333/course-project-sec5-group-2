let weeks = [];

const weekForm = document.getElementById('week-form');
const weeksTbody = document.getElementById('weeks-tbody');

function createWeekRow(week) {
  const tr = document.createElement('tr');
  tr.innerHTML = `
        <td>${week.title}</td>
        <td>${week.start_date}</td>
        <td>${week.description}</td>
        <td>
            <button class="edit-btn"   data-id="${week.id}">Edit</button>
            <button class="delete-btn" data-id="${week.id}">Delete</button>
        </td>
    `;
  return tr;
}

function renderTable() {
  weeksTbody.innerHTML = '';
  weeks.forEach(week => {
    weeksTbody.appendChild(createWeekRow(week));
  });
}

async function handleAddWeek(event) {
  event.preventDefault();

  const titleVal = document.getElementById('week-title').value.trim();
  const dateVal = document.getElementById('week-start-date').value;
  const descVal = document.getElementById('week-description').value.trim();
  const linksVal = document.getElementById('week-links').value
    .split('\n')
    .map(l => l.trim())
    .filter(l => l !== '');

  const submitBtn = document.getElementById('add-week');
  const editId = submitBtn.dataset.editId;

  if (editId) {
    await handleUpdateWeek(editId, {
      title: titleVal,
      start_date: dateVal,
      description: descVal,
      links: linksVal
    });
  } else {
    try {
      const response = await fetch('./api/index.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          title: titleVal,
          start_date: dateVal,
          description: descVal,
          links: linksVal
        })
      });
      const result = await response.json();
      if (result.success) {
        weeks.push({
          id: result.id,
          title: titleVal,
          start_date: dateVal,
          description: descVal,
          links: linksVal
        });
        renderTable();
        weekForm.reset();
      }
    } catch (error) {
      console.error('Error adding week:', error);
    }
  }
}

async function handleUpdateWeek(id, fields) {
  try {
    const response = await fetch('./api/index.php', {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id, ...fields })
    });
    const result = await response.json();
    if (result.success) {
      weeks = weeks.map(w => w.id == id ? { ...w, ...fields } : w);
      renderTable();
      weekForm.reset();
      const submitBtn = document.getElementById('add-week');
      submitBtn.textContent = 'Add Week';
      delete submitBtn.dataset.editId;
    }
  } catch (error) {
    console.error('Error updating week:', error);
  }
}

async function handleTableClick(event) {
  const target = event.target;
  const id = target.dataset.id;

  if (target.classList.contains('delete-btn')) {
    if (!confirm('Are you sure you want to delete this week?')) return;
    try {
      const response = await fetch(`./api/index.php?id=${id}`, {
        method: 'DELETE'
      });
      const result = await response.json();
      if (result.success) {
        weeks = weeks.filter(w => w.id != id);
        renderTable();
      }
    } catch (error) {
      console.error('Error deleting week:', error);
    }
  }

  if (target.classList.contains('edit-btn')) {
    const week = weeks.find(w => w.id == id);
    if (!week) return;

    document.getElementById('week-title').value = week.title;
    document.getElementById('week-start-date').value = week.start_date;
    document.getElementById('week-description').value = week.description;
    document.getElementById('week-links').value = week.links.join('\n');

    const submitBtn = document.getElementById('add-week');
    submitBtn.textContent = 'Update Week';
    submitBtn.dataset.editId = week.id;

    window.scrollTo({ top: 0, behavior: 'smooth' });
  }
}

async function loadAndInitialize() {
  try {
    const response = await fetch('./api/index.php');
    const result = await response.json();
    if (result.success) {
      weeks = result.data;
      renderTable();
    }
  } catch (error) {
    console.error('Error loading weeks:', error);
  }

  weekForm.addEventListener('submit', handleAddWeek);
  weeksTbody.addEventListener('click', handleTableClick);
}

loadAndInitialize();