document.addEventListener("DOMContentLoaded", () => {
  const roleModal = document.querySelector("[data-role-modal]");
  const roleForm = document.querySelector("[data-role-permission-form]");
  const roleModalTitle = document.querySelector("[data-role-modal-title]");
  const permissionSearch = document.querySelector("[data-permission-search]");
  const permissionTree = document.querySelector("[data-permission-tree]");

  document.querySelectorAll("[data-row-action]").forEach((button) => {
    button.addEventListener("click", (event) => {
      event.stopPropagation();

      const wrapper = button.closest(".account-row-actions");
      const menu = wrapper?.querySelector(".account-row-menu");
      if (!wrapper || !menu) return;

      document.querySelectorAll(".account-row-actions.is-open").forEach((openWrapper) => {
        if (openWrapper === wrapper) return;
        openWrapper.classList.remove("is-open");
        openWrapper.querySelector(".account-row-menu")?.setAttribute("hidden", "");
      });

      const isOpen = wrapper.classList.toggle("is-open");
      menu.hidden = !isOpen;

      if (isOpen) {
        const rect = button.getBoundingClientRect();
        menu.style.left = `${rect.left}px`;
        menu.style.top = `${rect.bottom + 6}px`;
      }
    });
  });

  document.addEventListener("click", () => {
    document.querySelectorAll(".account-row-actions.is-open").forEach((wrapper) => {
      wrapper.classList.remove("is-open");
      wrapper.querySelector(".account-row-menu")?.setAttribute("hidden", "");
    });
  });

  function parseRole(button) {
    try {
      return JSON.parse(button.dataset.role || "{}");
    } catch (error) {
      return {};
    }
  }

  function activateTab(tabName) {
    document.querySelectorAll("[data-account-tab]").forEach((button) => {
      button.classList.toggle("is-active", button.dataset.accountTab === tabName);
    });

    document.querySelectorAll("[data-account-tab-panel]").forEach((panel) => {
      panel.classList.toggle("is-active", panel.dataset.accountTabPanel === tabName);
    });
  }

  function closeModal() {
    if (roleModal) {
      roleModal.hidden = true;
    }
    activateTab("role-info");
  }

  function openModal() {
    if (roleModal) {
      roleModal.hidden = false;
    }
  }

  function setReadonlyField(selector, value) {
    const field = document.querySelector(selector);
    if (field) {
      field.value = value || "";
    }
  }

  function setDefaultRoleCheckbox(checked) {
    const field = document.querySelector("[data-role-default]");
    if (field) {
      field.checked = Boolean(checked);
    }
  }

  function getPermissionRows() {
    return [...document.querySelectorAll("[data-permission-item]")];
  }

  function getPermissionCheckboxes() {
    return [...document.querySelectorAll("[data-permission-checkbox]")];
  }

  function getChildRows(parentId) {
    return getPermissionRows().filter((row) => row.dataset.permissionParent === String(parentId));
  }

  function getDescendantCheckboxes(parentId) {
    const directChildren = getChildRows(parentId);

    return directChildren.flatMap((row) => {
      const checkbox = row.querySelector("[data-permission-checkbox]");
      return [checkbox, ...getDescendantCheckboxes(row.dataset.permissionId)].filter(Boolean);
    });
  }

  function getAncestorRows(row) {
    const ancestors = [];
    let parentId = row?.dataset.permissionParent;

    while (parentId) {
      const parentRow = document.querySelector(`[data-permission-id="${parentId}"]`);
      if (!parentRow) break;

      ancestors.push(parentRow);
      parentId = parentRow.dataset.permissionParent;
    }

    return ancestors;
  }

  function updateParentState(parentId) {
    if (!parentId) return;

    const parentRow = document.querySelector(`[data-permission-id="${parentId}"]`);
    const parentCheckbox = parentRow?.querySelector("[data-permission-checkbox]");
    const childCheckboxes = getChildRows(parentId)
      .map((row) => row.querySelector("[data-permission-checkbox]"))
      .filter(Boolean);

    if (!parentCheckbox || childCheckboxes.length === 0) return;

    const checkedCount = childCheckboxes.filter((checkbox) => checkbox.checked).length;
    const partialCount = childCheckboxes.filter((checkbox) => checkbox.indeterminate).length;

    parentCheckbox.checked = checkedCount === childCheckboxes.length;
    parentCheckbox.indeterminate = checkedCount > 0 && checkedCount < childCheckboxes.length;

    if (partialCount > 0) {
      parentCheckbox.checked = false;
      parentCheckbox.indeterminate = true;
    }

    updateParentState(parentRow.dataset.permissionParent);
  }

  function updateAllParentStates() {
    getPermissionRows()
      .slice()
      .reverse()
      .forEach((row) => updateParentState(row.dataset.permissionParent));
  }

  function setDescendantsChecked(parentId, checked) {
    getDescendantCheckboxes(parentId).forEach((checkbox) => {
      checkbox.checked = checked;
      checkbox.indeterminate = false;
    });
  }

  function clearPermissionChecks() {
    getPermissionCheckboxes().forEach((checkbox) => {
      checkbox.checked = false;
      checkbox.indeterminate = false;
    });
  }

  function setPermissionChecks(permissionIds) {
    const ids = (permissionIds || []).map((id) => String(id));

    getPermissionCheckboxes().forEach((checkbox) => {
      checkbox.checked = ids.includes(checkbox.value);
      checkbox.indeterminate = false;
    });

    updateAllParentStates();
  }

  function prepareRoleForm(role) {
    if (roleForm) {
      roleForm.action = role.urls?.permissions || "#";
    }

    if (roleModalTitle) {
      roleModalTitle.textContent = `Chỉnh sửa vai trò: ${role.ten_vai_tro || ""}`;
    }

    setReadonlyField("[data-role-code]", role.ma_vai_tro);
    setReadonlyField("[data-role-name]", role.ten_vai_tro);
    setReadonlyField("[data-role-status]", role.trang_thai);
    setReadonlyField("[data-role-description]", role.mo_ta);
    setDefaultRoleCheckbox(role.mac_dinh);
    clearPermissionChecks();
    setPermissionChecks(role.quyen);
  }

  document.querySelectorAll("[data-role-modal-open]").forEach((button) => {
    button.addEventListener("click", (event) => {
      event.stopPropagation();
      prepareRoleForm(parseRole(button));
      openModal();
    });
  });

  document.querySelectorAll("[data-role-modal-close]").forEach((button) => {
    button.addEventListener("click", closeModal);
  });

  document.querySelectorAll("[data-account-tab]").forEach((button) => {
    button.addEventListener("click", () => activateTab(button.dataset.accountTab));
  });

  permissionTree?.addEventListener("change", (event) => {
    const checkbox = event.target.closest("[data-permission-checkbox]");
    if (!checkbox) return;

    const row = checkbox.closest("[data-permission-item]");
    checkbox.indeterminate = false;
    setDescendantsChecked(row.dataset.permissionId, checkbox.checked);
    updateParentState(row.dataset.permissionParent);
  });

  permissionSearch?.addEventListener("input", () => {
    const keyword = permissionSearch.value.trim().toLowerCase();
    const rows = getPermissionRows();

    if (!keyword) {
      rows.forEach((row) => {
        row.hidden = false;
      });
      return;
    }

    const visibleRows = new Set();

    rows.forEach((row) => {
      const haystack = row.dataset.permissionSearchText || row.textContent.toLowerCase();
      if (haystack.includes(keyword)) {
        visibleRows.add(row);
        getAncestorRows(row).forEach((ancestor) => visibleRows.add(ancestor));
      }
    });

    rows.forEach((row) => {
      row.hidden = !visibleRows.has(row);
    });
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      closeModal();
    }
  });
});
