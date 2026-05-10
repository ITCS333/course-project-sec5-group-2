let currentResourceId = null;
let currentComments = [];

const resourceTitle = document.getElementById("resource-title");
const resourceDescription = document.getElementById("resource-description");
const resourceLink = document.getElementById("resource-link");
const commentList = document.getElementById("comment-list");
const commentForm = document.getElementById("comment-form");
const newCommentInput = document.getElementById("new-comment");

function getResourceIdFromURL() {
  const params = new URLSearchParams(window.location.search);
  return params.get("id");
}

function renderResourceDetails(resource) {
  resourceTitle.textContent = resource.title;
  resourceDescription.textContent = resource.description;
  resourceLink.href = resource.link;
}

function createCommentArticle(comment) {
  const article = document.createElement("article");
  article.innerHTML = `<p>${comment.text}</p><footer>Posted by: ${comment.author}</footer>`;
  return article;
}

function renderComments() {
  commentList.innerHTML = "";
  currentComments.forEach(c => commentList.appendChild(createCommentArticle(c)));
}

function handleAddComment(event) {
  event.preventDefault();
  const commentText = newCommentInput.value.trim();
  if (!commentText) return;
  fetch("./api/index.php?action=comment", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ resource_id: currentResourceId, author: "Student", text: commentText })
  }).then(r => r.json()).then(result => {
    if (result.success) {
      currentComments.push(result.data);
      renderComments();
      newCommentInput.value = "";
    }
  });
}

async function initializePage() {
  currentResourceId = getResourceIdFromURL();
  if (!currentResourceId) {
    resourceTitle.textContent = "Resource not found.";
    return;
  }
  const [rRes, cRes] = await Promise.all([
    fetch("./api/index.php?id=" + currentResourceId),
    fetch("./api/index.php?resource_id=" + currentResourceId + "&action=comments")
  ]);
  const rResult = await rRes.json();
  const cResult = await cRes.json();
  currentComments = cResult.data || [];
  if (rResult.success && rResult.data) {
    renderResourceDetails(rResult.data);
    renderComments();
    commentForm.addEventListener("submit", handleAddComment);
  } else {
    resourceTitle.textContent = "Resource not found.";
  }
}

initializePage();
