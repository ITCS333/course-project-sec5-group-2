let resources = [];

const resourceForm = document.getElementById("resource-form");
const resourcesTbody = document.getElementById("resources-tbody");

function createResourceRow(resource) {
  const tr = document.createElement("tr");
  tr.innerHTML = `
    <td>${resource.title}</td>
    <td>${resource.description}</td>
    <td>${resource.link}</td>
    <td>
      <button class="edit-btn" data-id="${resource.id}">Edit</button>
      <button class="delete-btn" data-id="${resource.id}">Delete</button>
    </td>
  `;
  return tr;
}

function renderTable(arr) {
  resourcesTbody.innerHTML = "";
  (arr || resources).forEach(r => resourcesTbody.appendChild(createResourceRow(r)));
}

let editingId = null;

function handleAddResource(event) {
  event.preventDefault();
  const title = document.getElementById("resource-title").value.trim();
  const description = document.getElementById("resource-description").value.trim();
  const link = document.getElementById("resource-link").value.trim();
  if (editingId !== null) {
    fetch("./api/index.php", {
      method: "PUT",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id: editingId, title, description, link })
    }).then(r => r.json()).then(result => {
      if (result.success) {
        const idx = resources.findIndex(r => r.id === editingId);
        if (idx !== -1) resources[idx] = { id: editingId, title, description, link };
        renderTable();
        resourceForm.reset();
        document.getElementById("add-resource").textContent = "Add Resource";
        editingId = null;
      }
    });
  } else {
    fetch("./api/index.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ title, description, link })
    }).then(r => r.json()).then(result => {
      if (result.success) {
        resources.push({ id: result.id, title, description, link });
        renderTable();
        resourceForm.reset();
      }
    });
  }
}

function handleTableClick(event) {
  if (event.target.classList.contains("delete-btn")) {
    const id = event.target.dataset.id;
    fetch("./api/index.php?id=" + id, { method: "DELETE" })
      .then(r => r.json()).then(result => {
        if (result.success) {
          resources = resources.filter(r => r.id != id);
          renderTable();
        }
      });
  } else if (event.target.classList.contains("edit-btn")) {
    const id = parseInt(event.target.dataset.id);
    const resource = resources.find(r => r.id === id);
    if (!resource) return;
    document.getElementById("resource-title").value = resource.title;
    document.getElementById("resource-description").value = resource.description;
    document.getElementById("resource-link").value = resource.link;
    document.getElementById("add-resource").textContent = "Update Resource";
    editingId = id;
  }
}

async function loadAndInitialize() {
  const res = await fetch("./api/index.php");
  const result = await res.json();
  resources = result.data || [];
  renderTable();
  resourceForm.addEventListener("submit", handleAddResource);
  resourcesTbody.addEventListener("click", handleTableClick);
}

loadAndInitialize();
