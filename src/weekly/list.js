const weekListSection = document.getElementById('week-list-section');

function createWeekArticle(week) {
  const article = document.createElement('article');

  article.innerHTML = `
        <h2>${week.title}</h2>
        <p>Starts on: ${week.start_date}</p>
        <p>${week.description}</p>
        <a href="details.html?id=${week.id}">View Details & Discussion</a>
    `;

  return article;
}

async function loadWeeks() {
  try {
    const response = await fetch('./api/index.php');
    const result = await response.json();

    weekListSection.innerHTML = '';

    if (result.success && result.data.length > 0) {
      result.data.forEach(week => {
        const article = createWeekArticle(week);
        weekListSection.appendChild(article);
      });
    } else {
      weekListSection.innerHTML = '<p>No weeks found.</p>';
    }
  } catch (error) {
    weekListSection.innerHTML = '<p>Error loading weeks.</p>';
    console.error(error);
  }
}

loadWeeks();