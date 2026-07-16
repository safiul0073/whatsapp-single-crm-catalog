const grid = document.querySelector("[data-team-grid]");
if (grid) {
  const cards = Array.from(grid.querySelectorAll("[data-department]"));
  const infoEl = document.querySelector("[data-team-info]");
  const pagesEl = document.querySelector("[data-team-pages]");
  const prevBtn = document.querySelector("[data-team-prev]");
  const nextBtn = document.querySelector("[data-team-next]");

  const PER_PAGE = 4;
  let page = 1;
  const total = cards.length;
  const totalPages = Math.ceil(total / PER_PAGE);

  const render = () => {
    const start = (page - 1) * PER_PAGE;
    const end = Math.min(start + PER_PAGE, total);

    cards.forEach((card, i) => {
      card.style.display = i >= start && i < end ? "" : "none";
    });

    if (infoEl) {
      infoEl.textContent = `Showing ${start + 1}–${end} of ${total} team members`;
    }

    prevBtn.disabled = page === 1;
    nextBtn.disabled = page === totalPages;

    pagesEl.innerHTML = "";
    for (let p = 1; p <= totalPages; p++) {
      const btn = document.createElement("button");
      btn.textContent = p;
      btn.setAttribute("aria-label", `Page ${p}`);
      btn.className =
        p === page
          ? "w-9 h-9 flex items-center justify-center rounded-xl border border-brand-blue bg-tint-blue font-mono text-micro font-semibold text-brand-blue"
          : "w-9 h-9 flex items-center justify-center rounded-xl border border-border-default bg-white font-mono text-micro font-semibold text-text-muted hover:border-brand-blue hover:text-brand-blue transition-colors duration-150";
      btn.addEventListener("click", () => {
        page = p;
        render();
        scrollToGrid();
      });
      pagesEl.appendChild(btn);
    }

    window.lucide?.createIcons({ nodes: [prevBtn, nextBtn] });
  }

  const scrollToGrid = () => {
    const section = document.getElementById("full-team");
    if (section) section.scrollIntoView({ behavior: "smooth", block: "start" });
  };

  prevBtn.addEventListener("click", () => {
    if (page > 1) { page--; render(); scrollToGrid(); }
  });
  nextBtn.addEventListener("click", () => {
    if (page < totalPages) { page++; render(); scrollToGrid(); }
  });

  render();
}
