let currentWeekId = null;
let currentComments = [];

const weekTitle = document.getElementById('week-title');
const weekStartDate = document.getElementById('week-start-date');
const weekDesc = document.getElementById('week-description');
const weekLinksList = document.getElementById('week-links-list');
const commentList = document.getElementById('comment-list');
const commentForm = document.getElementById('comment-form');
const newCommentInput = document.getElementById('new-comment');

function getWeekIdFromURL() {
  const params = new URLSearchParams(window.location.search);
  return params.get('id');
}

function renderWeekDetails(week) {
  weekTitle.textContent = week.title;
  weekStartDate.textContent = 'Starts on: ' + week.start_date;
  weekDesc.textContent = week.description;

  weekLinksList.innerHTML = '';
  week.links.forEach(url => {
    const li = document.createElement('li');
    const a = document.createElement('a');
    a.href = url;
    a.textContent = url;
    a.target = '_blank';
    li.appendChild(a);
    weekLinksList.appendChild(li);
  });
}

function createCommentArticle(comment) {
  const article = document.createElement('article');
  article.innerHTML = `
        <p>${comment.text}</p>
        <footer>Posted by: ${comment.author}</footer>
    `;
  return article;
}

function renderComments() {
  commentList.innerHTML = '';
  if (currentComments.length === 0) {
    commentList.innerHTML = '<p>No comments yet. Be the first!</p>';
    return;
  }
  currentComments.forEach(comment => {
    commentList.appendChild(createCommentArticle(comment));
  });
}

async function handleAddComment(event) {
  event.preventDefault();
  const commentText = newCommentInput.value.trim();
  if (!commentText) return;

  try {
    const response = await fetch('./api/index.php?action=comment', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        week_id: currentWeekId,
        author: 'Student',
        text: commentText
      })
    });
    const result = await response.json();
    if (result.success) {
      currentComments.push(result.data);
      renderComments();
      newCommentInput.value = '';
    }
  } catch (error) {
    console.error('Error posting comment:', error);
  }
}

async function initializePage() {
  currentWeekId = getWeekIdFromURL();

  if (!currentWeekId) {
    weekTitle.textContent = 'Week not found.';
    return;
  }

  try {
    const [weekRes, commentsRes] = await Promise.all([
      fetch(`./api/index.php?id=${currentWeekId}`),
      fetch(`./api/index.php?action=comments&week_id=${currentWeekId}`)
    ]);

    const weekData = await weekRes.json();
    const commentsData = await commentsRes.json();

    currentComments = commentsData.data ?? [];

    if (weekData.success) {
      renderWeekDetails(weekData.data);
      renderComments();
      commentForm.addEventListener('submit', handleAddComment);
    } else {
      weekTitle.textContent = 'Week not found.';
    }
  } catch (error) {
    weekTitle.textContent = 'Error loading week.';
    console.error(error);
  }
}

initializePage();