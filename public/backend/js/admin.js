document.addEventListener("DOMContentLoaded", () => {
  const page = document.body;
  const menuButton = document.querySelector("[data-admin-menu]");
  const backdrop = document.querySelector("[data-admin-backdrop]");
  const navTriggers = document.querySelectorAll("[data-nav-trigger]");
  const filterToggle = document.querySelector("[data-filter-toggle]");
  const filterBody = document.querySelector("[data-filter-body]");

  function closeSidebar() {
    page.classList.remove("sidebar-open");
  }

  menuButton?.addEventListener("click", () => {
    page.classList.toggle("sidebar-open");
  });

  backdrop?.addEventListener("click", closeSidebar);

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      closeSidebar();
    }
  });

  navTriggers.forEach((trigger) => {
    trigger.addEventListener("click", () => {
      const group = trigger.closest("[data-nav-group]");
      if (!group) return;

      const isOpen = group.classList.toggle("is-open");
      trigger.setAttribute("aria-expanded", String(isOpen));
    });
  });

  filterToggle?.addEventListener("click", () => {
    const panel = filterToggle.closest(".filter-panel");
    if (!panel) return;

    const isCollapsed = panel.classList.toggle("is-collapsed");
    filterToggle.setAttribute("aria-expanded", String(!isCollapsed));
  });

  filterBody?.addEventListener("submit", (event) => {
    event.preventDefault();
  });
});
