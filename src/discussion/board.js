/*
  Discussion Board — board.js
  API base URL: ./api/index.php
*/

// --- Global Data Store ---
let topics = [];

// --- Element Selections ---
const newTopicForm        = document.getElementById('new-topic-form');
const topicListContainer  = document.getElementById('topic-list-container');

// --- Functions ---

/**
 * Build an <article> element for one topic.
 */
function createTopicArticle(topic) {
  const article = document.createElement('article');

  const h3 = document.createElement('h3');
  const a  = document.createElement('a');
  a.href        = `topic.html?id=${topic.id}`;
  a.textContent = topic.subject;
  h3.appendChild(a);

  const footer = document.createElement('footer');
  footer.textContent = `Posted by: ${topic.author} on ${topic.created_at}`;

  const div = document.createElement('div');

  const editBtn = document.createElement('button');
  editBtn.className    = 'edit-btn';
  editBtn.dataset.id   = topic.id;
  editBtn.textContent  = 'Edit';

  const deleteBtn = document.createElement('button');
  deleteBtn.className   = 'delete-btn';
  deleteBtn.dataset.id  = topic.id;
  deleteBtn.textContent = 'Delete';

  div.appendChild(editBtn);
  div.appendChild(deleteBtn);

  article.appendChild(h3);
  article.appendChild(footer);
  article.appendChild(div);

  return article;
}

/**
 * Clear and re-render the topic list.
 */
function renderTopics() {
  topicListContainer.innerHTML = '';
  topics.forEach(topic => {
    topicListContainer.appendChild(createTopicArticle(topic));
  });
}

/**
 * Handle new-topic form submission.
 */
async function handleCreateTopic(event) {
  event.preventDefault();

  const subject = document.getElementById('topic-subject').value;
  const message = document.getElementById('topic-message').value;

  const response = await fetch('./api/index.php', {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify({ subject, message, author: 'Student' }),
  });

  const result = await response.json();

  if (result.success) {
    // Build a local topic object to avoid an extra GET
    const newTopic = {
      id:         result.id,
      subject,
      message,
      author:     'Student',
      created_at: new Date().toISOString().slice(0, 19).replace('T', ' '),
    };
    topics.push(newTopic);
    renderTopics();
    newTopicForm.reset();

    // Reset submit button in case it was in edit mode
    const submitBtn = document.getElementById('create-topic');
    submitBtn.textContent = 'Create Topic';
    delete submitBtn.dataset.editId;
  } else {
    alert('Error creating topic: ' + (result.message || 'Unknown error'));
  }
}

/**
 * Send a PUT request to update an existing topic.
 */
async function handleUpdateTopic(id, fields) {
  const response = await fetch('./api/index.php', {
    method:  'PUT',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify({ id, ...fields }),
  });

  const result = await response.json();

  if (result.success) {
    const idx = topics.findIndex(t => t.id === id);
    if (idx !== -1) {
      topics[idx] = { ...topics[idx], ...fields };
    }
    renderTopics();
  } else {
    alert('Error updating topic: ' + (result.message || 'Unknown error'));
  }
}

/**
 * Delegated click handler for the topic list (edit / delete).
 */
async function handleTopicListClick(event) {
  // --- DELETE ---
  if (event.target.classList.contains('delete-btn')) {
    const id = Number(event.target.dataset.id);

    const response = await fetch(`./api/index.php?id=${id}`, { method: 'DELETE' });
    const result   = await response.json();

    if (result.success) {
      topics = topics.filter(t => t.id !== id);
      renderTopics();
    } else {
      alert('Error deleting topic: ' + (result.message || 'Unknown error'));
    }
  }

  // --- EDIT ---
  if (event.target.classList.contains('edit-btn')) {
    const id    = Number(event.target.dataset.id);
    const topic = topics.find(t => t.id === id);
    if (!topic) return;

    document.getElementById('topic-subject').value = topic.subject;
    document.getElementById('topic-message').value = topic.message;

    const submitBtn = document.getElementById('create-topic');
    submitBtn.textContent      = 'Update Topic';
    submitBtn.dataset.editId   = id;
  }
}

/**
 * Override the form submit handler to handle both create and update.
 * We replace the listener so "Update Topic" calls handleUpdateTopic.
 */
function setupFormSubmit() {
  newTopicForm.addEventListener('submit', async (event) => {
    event.preventDefault();

    const submitBtn = document.getElementById('create-topic');
    const editId    = submitBtn.dataset.editId;

    const subject = document.getElementById('topic-subject').value;
    const message = document.getElementById('topic-message').value;

    if (editId) {
      // Update mode
      await handleUpdateTopic(Number(editId), { subject, message });
      newTopicForm.reset();
      submitBtn.textContent = 'Create Topic';
      delete submitBtn.dataset.editId;
    } else {
      // Create mode — reuse handleCreateTopic logic inline
      const response = await fetch('./api/index.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ subject, message, author: 'Student' }),
      });
      const result = await response.json();

      if (result.success) {
        const newTopic = {
          id:         result.id,
          subject,
          message,
          author:     'Student',
          created_at: new Date().toISOString().slice(0, 19).replace('T', ' '),
        };
        topics.push(newTopic);
        renderTopics();
        newTopicForm.reset();
      } else {
        alert('Error creating topic: ' + (result.message || 'Unknown error'));
      }
    }
  });
}

/**
 * Fetch all topics from the API, render them, attach listeners.
 */
async function loadAndInitialize() {
  const response = await fetch('./api/index.php');
  const result   = await response.json();

  if (result.success) {
    topics = result.data;
    renderTopics();
  }

  setupFormSubmit();

  topicListContainer.addEventListener('click', handleTopicListClick);
}

// --- Initial Page Load ---
loadAndInitialize();