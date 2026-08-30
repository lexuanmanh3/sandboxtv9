document.addEventListener("DOMContentLoaded", () => {
  const page = document.body;
  const menuButton = document.querySelector("[data-admin-menu]");
  const backdrop = document.querySelector("[data-admin-backdrop]");
  const sidebar = document.querySelector("[data-admin-sidebar]");
  const sidebarResizer = document.querySelector("[data-sidebar-resizer]");
  const navTriggers = document.querySelectorAll("[data-nav-trigger]");
  const filterToggle = document.querySelector("[data-filter-toggle]");
  const filterBody = document.querySelector("[data-filter-body]");
  const userMenu = document.querySelector("[data-admin-user-menu]");
  const userTriggers = document.querySelectorAll("[data-admin-user-trigger]");
  const userDropdown = document.querySelector("[data-admin-user-dropdown]");

  function closeUserMenu() {
    if (!userDropdown) return;
    userDropdown.hidden = true;
    userTriggers.forEach((trigger) => trigger.setAttribute("aria-expanded", "false"));
  }

  userTriggers.forEach((trigger) => {
    trigger.addEventListener("click", (event) => {
      event.stopPropagation();
      const willOpen = userDropdown.hidden;
      closeUserMenu();
      if (willOpen) {
        userDropdown.hidden = false;
        userTriggers.forEach((item) => item.setAttribute("aria-expanded", "true"));
      }
    });
  });

  document.addEventListener("click", (event) => {
    if (userMenu && !userMenu.contains(event.target)) closeUserMenu();
  });

  function closeSidebar() {
    page.classList.remove("sidebar-open");
  }

  menuButton?.addEventListener("click", () => {
    if (window.matchMedia("(max-width: 900px)").matches) {
      page.classList.toggle("sidebar-open");
      return;
    }

    const collapsed = page.classList.toggle("sidebar-collapsed");
    localStorage.setItem("adminSidebarCollapsed", String(collapsed));
  });

  if (localStorage.getItem("adminSidebarCollapsed") === "true" && !window.matchMedia("(max-width: 900px)").matches) {
    page.classList.add("sidebar-collapsed");
  }

  const savedSidebarWidth = Number(localStorage.getItem("adminSidebarWidth"));
  if (savedSidebarWidth >= 220 && savedSidebarWidth <= 480) {
    document.documentElement.style.setProperty("--admin-sidebar-width", `${savedSidebarWidth}px`);
  }

  sidebarResizer?.addEventListener("pointerdown", (event) => {
    if (window.matchMedia("(max-width: 900px)").matches) return;

    event.preventDefault();
    page.classList.remove("sidebar-collapsed");
    page.classList.add("sidebar-resizing");
    sidebarResizer.setPointerCapture(event.pointerId);
  });

  sidebarResizer?.addEventListener("pointermove", (event) => {
    if (!page.classList.contains("sidebar-resizing") || !sidebar) return;

    const shellLeft = sidebar.parentElement?.getBoundingClientRect().left ?? 0;
    const width = Math.min(480, Math.max(220, event.clientX - shellLeft));
    document.documentElement.style.setProperty("--admin-sidebar-width", `${width}px`);
  });

  function finishSidebarResize(event) {
    if (!page.classList.contains("sidebar-resizing") || !sidebar) return;

    page.classList.remove("sidebar-resizing");
    localStorage.setItem("adminSidebarCollapsed", "false");
    localStorage.setItem("adminSidebarWidth", String(Math.round(sidebar.getBoundingClientRect().width)));
    if (sidebarResizer?.hasPointerCapture(event.pointerId)) sidebarResizer.releasePointerCapture(event.pointerId);
  }

  sidebarResizer?.addEventListener("pointerup", finishSidebarResize);
  sidebarResizer?.addEventListener("pointercancel", finishSidebarResize);

  backdrop?.addEventListener("click", closeSidebar);

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      closeSidebar();
      closeUserMenu();
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

  // Tự động đóng toàn bộ menu hành động đang mở khi người dùng lăn chuột (scroll) hoặc resize cửa sổ
  function closeAllRowMenus() {
    document.querySelectorAll(".account-row-actions.is-open").forEach((wrapper) => {
      wrapper.classList.remove("is-open");
      wrapper.querySelector(".account-row-menu")?.setAttribute("hidden", "");
    });
  }

  window.addEventListener("scroll", closeAllRowMenus, { passive: true, capture: true });
  document.addEventListener("scroll", closeAllRowMenus, { passive: true, capture: true });
  window.addEventListener("resize", closeAllRowMenus, { passive: true });
});
