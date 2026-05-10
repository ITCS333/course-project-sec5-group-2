const assignmentListSection = document.getElementById('assignment-list-section');

function createAssignmentArticle(assignment) {
  const article = document.createElement('article');
  article.innerHTML = `
    <h2>${assignment.title}</h2>
    <p>Due: ${assignment.due_date}</p>
    <p>${assignment.description}</p>
    <a href="details.html?id=${assignment.id}">View Details &amp; Discussion</a>
  `;
  return article;
}

async function loadAssignments() {
  const response = await fetch('./api/index.php');
  const result = await response.json();
  assignmentListSection.innerHTML = '';
  if (result.success) {
    result.data.forEach(assignment => {
      assignmentListSection.appendChild(createAssignmentArticle(assignment));
    });
  }
}

loadAssignments();