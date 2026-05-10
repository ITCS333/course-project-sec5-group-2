/*
  Discussion Topic Page — topic.js
  API base URL: ./api/index.php
*/

// --- Global Data Store ---
let currentTopicId = null;
let currentReplies = [];

// --- Element Selections ---
const topicSubject       = document.getElementById('topic-subject');
const opMessage          = document.getElementById('op-message');
const opFooter           = document.getElementById('op-footer');
const opActions          = document.getElementById('op-actions');
const replyListContainer = document.getElementById('reply-list-container');
const replyForm          = document.getElementById('reply-form');
const newReplyText       = document.getElementById('new-reply');

// --- Functions ---

/**
 * Read the topic id from the URL query string (?id=...).
 */
function getTopicIdFromURL() {
  const params = new URLSearchParams(window.location.search);
  return params.get('id');
}

/**
 * Populate the original-post section with the topic's data.
 */
function renderOriginalPost(topic) {
  topicSubject.textContent = topic.subject;
  opMessage.textContent    = topic.message;
  opFooter.textContent     = `Posted by: ${topic.author} on ${topic.created_at}`;

  // Inject Edit and Delete buttons
  opActions.innerHTML = '';

  const editBtn = document.createElement('button');
  editBtn.className   = 'edit-topic-btn';
  editBtn.dataset.id  = topic.id;
  editBtn.textContent = 'Edit';

  const deleteBtn = document.createElement('button');
  deleteBtn.className   = 'delete-btn';
  deleteBtn.dataset.id  = topic.id;
  deleteBtn.textContent = 'Delete';

  editBtn.addEventListener('click', () => handleEditTopic(topic));
  deleteBtn.addEventListener('click', () => handleDeleteTopic(topic.id));

  opActions.appendChild(editBtn);
  opActions.appendChild(deleteBtn);
}

/**
 * Build an <article> element for one reply.
 */
function createReplyArticle(reply) {
  const article = document.createElement('article');

  const p = document.createElement('p');
  p.textContent = reply.text;

  const footer = document.createElement('footer');
  footer.textContent = `Posted by: ${reply.author} on ${reply.created_at}`;

  const div = document.createElement('div');

  const deleteBtn = document.createElement('button');
  deleteBtn.className   = 'delete-reply-btn';
  deleteBtn.dataset.id  = reply.id;
  deleteBtn.textContent = 'Delete';

  div.appendChild(deleteBtn);

  article.appendChild(p);
  article.appendChild(footer);
  article.appendChild(div);

  return article;
}

/**
 * Clear and re-render the reply list.
 */
function renderReplies() {
  replyListContainer.innerHTML = '';
  currentReplies.forEach(reply => {
    replyListContainer.appendChild(createReplyArticle(reply));
  });
}

/**
 * Handle adding a new reply.
 */
async function handleAddReply(event) {
  event.preventDefault();

  const replyText = newReplyText.value.trim();
  if (!replyText) return;

  const response = await fetch('./api/index.php?action=reply', {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify({
      topic_id: currentTopicId,
      author:   'Student',
      text:     replyText,
    }),
  });

  const result = await response.json();

  if (result.success) {
    currentReplies.push(result.data);
    renderReplies();
    newReplyText.value = '';
  } else {
    alert('Error posting reply: ' + (result.message || 'Unknown error'));
  }
}

/**
 * Delegated click handler for the reply list (delete replies).
 */
async function handleReplyListClick(event) {
  if (event.target.classList.contains('delete-reply-btn')) {
    const id = Number(event.target.dataset.id);

    const response = await fetch(`./api/index.php?action=delete_reply&id=${id}`, {
      method: 'DELETE',
    });
    const result = await response.json();

    if (result.success) {
      currentReplies = currentReplies.filter(r => r.id !== id);
      renderReplies();
    } else {
      alert('Error deleting reply: ' + (result.message || 'Unknown error'));
    }
  }
}

/**
 * Handle editing the original topic (inline prompt-based edit).
 */
async function handleEditTopic(topic) {
  const newSubject = prompt('Edit subject:', topic.subject);
  if (newSubject === null) return; // cancelled

  const newMessage = prompt('Edit message:', topic.message);
  if (newMessage === null) return;

  const response = await fetch('./api/index.php', {
    method:  'PUT',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify({ id: topic.id, subject: newSubject, message: newMessage }),
  });

  const result = await response.json();
  if (result.success) {
    topic.subject = newSubject;
    topic.message = newMessage;
    renderOriginalPost(topic);
  } else {
    alert('Error updating topic: ' + (result.message || 'Unknown error'));
  }
}

/**
 * Handle deleting the original topic and redirecting.
 */
async function handleDeleteTopic(id) {
  if (!confirm('Delete this topic and all its replies?')) return;

  const response = await fetch(`./api/index.php?id=${id}`, { method: 'DELETE' });
  const result   = await response.json();

  if (result.success) {
    window.location.href = 'board.html';
  } else {
    alert('Error deleting topic: ' + (result.message || 'Unknown error'));
  }
}

/**
 * Fetch the topic and its replies, then wire up all listeners.
 */
async function initializePage() {
  currentTopicId = getTopicIdFromURL();

  if (!currentTopicId) {
    topicSubject.textContent = 'Topic not found.';
    return;
  }

  const [topicRes, repliesRes] = await Promise.all([
    fetch(`./api/index.php?id=${currentTopicId}`),
    fetch(`./api/index.php?action=replies&topic_id=${currentTopicId}`),
  ]);

  const topicResult   = await topicRes.json();
  const repliesResult = await repliesRes.json();

  currentReplies = repliesResult.success ? repliesResult.data : [];

  if (topicResult.success && topicResult.data) {
    const topic = topicResult.data;
    renderOriginalPost(topic);
    renderReplies();

    replyForm.addEventListener('submit', handleAddReply);
    replyListContainer.addEventListener('click', handleReplyListClick);
  } else {
    topicSubject.textContent = 'Topic not found.';
  }
}

// --- Initial Page Load ---
initializePage();