const resourceListSection = document.getElementById("resource-list-section");

function createResourceArticle(resource) {
  const article = document.createElement("article");
  article.innerHTML = `
    <h2>${resource.title}</h2>
    <p>${resource.description}</p>
    <a href="details.html?id=${resource.id}">View Resource &amp; Discussion</a>
  `;
  return article;
}

async function loadResources() {
  const res = await fetch("./api/index.php");
  const result = await res.json();
  resourceListSection.innerHTML = "";
  (result.data || []).forEach(r => resourceListSection.appendChild(createResourceArticle(r)));
}

loadResources();
