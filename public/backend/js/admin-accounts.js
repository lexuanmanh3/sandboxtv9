document.addEventListener("DOMContentLoaded", () => {
  const filterToggle = document.querySelector("[data-account-filter-toggle]");
  const filter = document.querySelector("[data-account-filter]");
  const rows = [...document.querySelectorAll("[data-account-row]")];
  const quickActionForm = document.getElementById("accountQuickActionForm");
  const excelToggle = document.querySelector("[data-account-excel-toggle]");
  const excelMenu = document.querySelector("[data-account-excel-menu]");

  // NOTE: Dieu khien dong/mo bo loc nang cao, form filter submit GET ve Laravel.
  filterToggle?.addEventListener("click", () => {
    const isOpen = filterToggle.getAttribute("aria-expanded") === "true";
    const section = filterToggle.closest(".account-filter-section");
    filterToggle.setAttribute("aria-expanded", String(!isOpen));
    section?.classList.toggle("is-open", !isOpen);
    if (filter) {
      filter.hidden = isOpen;
    }
  });

  excelToggle?.addEventListener("click", (event) => {
    event.stopPropagation();
    const isOpen = excelToggle.getAttribute("aria-expanded") === "true";
    excelToggle.setAttribute("aria-expanded", String(!isOpen));
    if (excelMenu) {
      excelMenu.hidden = isOpen;
    }
  });

  // NOTE: Chon dong bang click de giu cam giac table hien tai.
  rows.forEach((row) => {
    row.addEventListener("click", (event) => {
      if (event.target.closest(".account-row-actions")) return;

      rows.forEach((item) => item.classList.remove("is-selected"));
      row.classList.add("is-selected");
    });
  });

  // NOTE: Dropdown hanh dong dung position fixed de khong bi cat boi table overflow.
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

  function closeAllRowMenus() {
    document.querySelectorAll(".account-row-actions.is-open").forEach((wrapper) => {
      wrapper.classList.remove("is-open");
      wrapper.querySelector(".account-row-menu")?.setAttribute("hidden", "");
    });
  }

  document.addEventListener("click", (event) => {
    if (!event.target.closest(".account-row-actions")) {
      closeAllRowMenus();
    }
  });

  window.addEventListener("scroll", closeAllRowMenus, { passive: true, capture: true });
  document.addEventListener("scroll", closeAllRowMenus, { passive: true, capture: true });
  window.addEventListener("resize", closeAllRowMenus, { passive: true });

  // NOTE: Form an nay gui cac action nhanh nhu khoa/mo khoa voi CSRF va method spoofing.
  document.querySelectorAll("[data-account-action-submit]").forEach((button) => {
    button.addEventListener("click", (event) => {
      event.stopPropagation();
      if (!quickActionForm || button.disabled) return;

      quickActionForm.action = button.dataset.accountActionSubmit;
      quickActionForm.querySelector('input[name="_method"]').value = button.dataset.method || "PATCH";
      quickActionForm.submit();
    });
  });

  document.addEventListener("click", () => {
    excelToggle?.setAttribute("aria-expanded", "false");
    if (excelMenu) {
      excelMenu.hidden = true;
    }

    document.querySelectorAll(".account-row-actions.is-open").forEach((wrapper) => {
      wrapper.classList.remove("is-open");
      wrapper.querySelector(".account-row-menu")?.setAttribute("hidden", "");
    });
  });

  const userModal = document.querySelector('[data-account-modal="user"]');
  const passwordModal = document.querySelector('[data-account-modal="password"]');
  const deleteModal = document.querySelector('[data-account-modal="delete"]');
  const userForm = document.querySelector("[data-user-form]");
  const userFormMethod = document.querySelector("[data-user-form-method]");
  const userModalTitle = document.querySelector("[data-user-modal-title]");
  const passwordForm = document.querySelector("[data-password-form]");
  const deleteForm = document.querySelector("[data-delete-form]");
  const deleteAccountName = document.querySelector("[data-delete-account-name]");
  const randomPassword = document.querySelector("[data-random-password]");

  if (userForm) {
    userForm.dataset.storeUrl = userForm.getAttribute("action");
  }

  function parseAccount(button) {
    try {
      return JSON.parse(button.dataset.account || "{}");
    } catch (error) {
      return {};
    }
  }

  // NOTE: Dong modal va reset tab ve tab dau tien de lan mo sau khong bi giu state cu.
  function closeModals() {
    document.querySelectorAll("[data-account-modal]").forEach((modal) => {
      modal.hidden = true;
    });

    document.body.classList.remove("account-modal-open");
    activateTab("info");
  }

  function openModal(modal) {
    if (!modal) return;
    modal.hidden = false;
    document.body.classList.add("account-modal-open");
  }

  function activateTab(tabName) {
    document.querySelectorAll("[data-account-tab]").forEach((button) => {
      button.classList.toggle("is-active", button.dataset.accountTab === tabName);
    });

    document.querySelectorAll("[data-account-tab-panel]").forEach((panel) => {
      panel.classList.toggle("is-active", panel.dataset.accountTabPanel === tabName);
    });
  }

  function setField(name, value) {
    const field = userForm?.querySelector(`[name="${name}"]`);
    if (field) {
      field.value = value ?? "";
    }
  }

  function setCheckbox(name, checked) {
    const field = userForm?.querySelector(`[name="${name}"]`);
    if (field) {
      field.checked = Boolean(checked);
    }
  }

  function clearRoleChecks() {
    userForm?.querySelectorAll('input[name="vai_tro[]"]').forEach((checkbox) => {
      checkbox.checked = false;
    });
  }

  function setRoleChecks(roleIds) {
    const ids = (roleIds || []).map((id) => String(id));
    userForm?.querySelectorAll('input[name="vai_tro[]"]').forEach((checkbox) => {
      checkbox.checked = ids.includes(checkbox.value);
    });
  }

  // NOTE: Bat/tat field theo mode tao/sua de cac input an cung name khong bi submit nham.
  // Neu sau nay them field account-create-only/account-edit-only, logic bao tri tap trung tai day.
  function syncFormMode(mode) {
    userForm?.querySelectorAll(".account-create-only input, .account-create-only select, .account-create-only textarea").forEach((field) => {
      field.disabled = mode !== "create";
    });

    userForm?.querySelectorAll(".account-edit-only input, .account-edit-only select, .account-edit-only textarea").forEach((field) => {
      field.disabled = mode !== "edit";
    });
  }

  function syncRandomPasswordState() {
    const disabled = Boolean(randomPassword?.checked);
    userForm?.querySelectorAll('.account-create-only input[name="password"], .account-create-only input[name="password_confirmation"]').forEach((input) => {
      input.disabled = disabled;
      if (disabled) {
        input.value = "";
      }
    });
  }

  // NOTE: Mo modal tao tai khoan voi action store va method POST.
  function prepareCreateForm() {
    userForm?.reset();
    clearRoleChecks();
    // NOTE: Ep loai_tai_khoan ve customer sau reset() de tranh truong hop trinh duyet
    // giu lai lua chon truoc do hoac option dau tien trong select khac customer.
    setField("loai_tai_khoan", "customer");
    setField("trang_thai", "hoat_dong");
    if (userForm?.dataset.storeUrl) {
      userForm.action = userForm.dataset.storeUrl;
    }
    if (userFormMethod) {
      userFormMethod.value = "POST";
    }
    userForm?.classList.remove("account-edit-mode");
    userForm?.classList.add("account-create-mode");
    syncFormMode("create");
    if (userModalTitle) {
    userModalTitle.textContent = "Tạo tài khoản người dùng mới";
    }
    syncRandomPasswordState();
  }

  // NOTE: Mo modal sua tai khoan va do du lieu tu row vao form.
  function prepareEditForm(account) {
    userForm?.reset();
    if (userForm) {
      userForm.action = account.urls?.update || "#";
      userForm.classList.remove("account-create-mode");
      userForm.classList.add("account-edit-mode");
    }
    syncFormMode("edit");
    if (userFormMethod) {
      userFormMethod.value = "PUT";
    }
    if (userModalTitle) {
      userModalTitle.textContent = `Người cập nhật: ${account.ten_dang_nhap || ""}`;
    }

    setField("ten_dang_nhap", account.ten_dang_nhap);
    setField("ho", account.ho);
    setField("ten", account.ten);
    setField("name", account.name);
    setField("email", account.email);
    setField("so_dien_thoai", account.so_dien_thoai);
    setField("loai_tai_khoan", account.loai_tai_khoan);
    setField("trang_thai", account.trang_thai);
    setCheckbox("bi_khoa", account.bi_khoa);
    setRoleChecks(account.vai_tro);
  }

  // NOTE: Gan URL action cho form doi mat khau, khong dua password cu/hash vao DOM.
  function preparePasswordForm(account) {
    passwordForm?.reset();
    if (passwordForm) {
      passwordForm.action = account.urls?.password || "#";
    }
  }

  // NOTE: Gan URL action cho form xoa mem va hien username trong cau confirm.
  function prepareDeleteForm(account) {
    if (deleteForm) {
      deleteForm.action = account.urls?.destroy || "#";
    }
    if (deleteAccountName) {
      deleteAccountName.textContent = account.ten_dang_nhap || "";
    }
  }

  document.querySelectorAll("[data-account-modal-open]").forEach((button) => {
    button.addEventListener("click", (event) => {
      event.stopPropagation();
      const target = button.dataset.accountModalOpen;
      const account = parseAccount(button);

      if (target === "create") {
        prepareCreateForm();
        openModal(userModal);
      }

      if (target === "edit" && !button.disabled) {
        prepareEditForm(account);
        openModal(userModal);
      }

      if (target === "password" && !button.disabled) {
        preparePasswordForm(account);
        openModal(passwordModal);
      }

      if (target === "delete" && !button.disabled) {
        prepareDeleteForm(account);
        openModal(deleteModal);
      }
    });
  });

  document.querySelectorAll("[data-account-modal-close]").forEach((button) => {
    button.addEventListener("click", closeModals);
  });

  document.querySelectorAll("[data-account-tab]").forEach((button) => {
    button.addEventListener("click", () => activateTab(button.dataset.accountTab));
  });

  randomPassword?.addEventListener("change", syncRandomPasswordState);

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" || event.key === "Esc") {
      closeModals();
      document.querySelectorAll(".account-row-actions.is-open").forEach((wrapper) => {
        wrapper.classList.remove("is-open");
        wrapper.querySelector(".account-row-menu")?.setAttribute("hidden", "");
      });
    }
  });

  // Xử lý Checkbox Chọn nhiều & Thanh tác vụ nổi (Bulk Action Bar)
  const selectAllCheckbox = document.querySelector("[data-select-all]");
  const itemCheckboxes = document.querySelectorAll(".bulk-item-checkbox:not(:disabled)");
  const bulkActionBar = document.getElementById("bulkActionBar");
  const bulkSelectedCount = document.getElementById("bulkSelectedCount");
  const bulkDeselectBtn = document.getElementById("bulkDeselectBtn");
  const bulkDeleteBtn = document.getElementById("bulkDeleteBtn");
  const bulkDeleteHiddenInputs = document.getElementById("bulkAccountDeleteHiddenInputs");
  const bulkDeleteConfirmCount = document.getElementById("bulkAccountDeleteConfirmCount");
  const bulkDeleteModal = document.querySelector('[data-account-modal="bulk-delete"]');

  function updateBulkBar() {
    const checked = [...itemCheckboxes].filter(cb => cb.checked);
    const count = checked.length;

    if (bulkSelectedCount) bulkSelectedCount.textContent = count;
    if (bulkDeleteConfirmCount) bulkDeleteConfirmCount.textContent = count;

    if (count > 0) {
      bulkActionBar?.classList.add("is-visible");
    } else {
      bulkActionBar?.classList.remove("is-visible");
    }

    if (selectAllCheckbox) {
      selectAllCheckbox.checked = count > 0 && count === itemCheckboxes.length;
      selectAllCheckbox.indeterminate = count > 0 && count < itemCheckboxes.length;
    }
  }

  selectAllCheckbox?.addEventListener("change", (e) => {
    itemCheckboxes.forEach(cb => cb.checked = e.target.checked);
    updateBulkBar();
  });

  itemCheckboxes.forEach(cb => {
    cb.addEventListener("change", updateBulkBar);
    cb.addEventListener("click", (e) => e.stopPropagation());
  });

  bulkDeselectBtn?.addEventListener("click", () => {
    itemCheckboxes.forEach(cb => cb.checked = false);
    if (selectAllCheckbox) selectAllCheckbox.checked = false;
    updateBulkBar();
  });

  bulkDeleteBtn?.addEventListener("click", () => {
    const checked = [...itemCheckboxes].filter(cb => cb.checked);
    if (checked.length === 0) return;

    if (bulkDeleteHiddenInputs) {
      bulkDeleteHiddenInputs.innerHTML = checked.map(cb => `<input type="hidden" name="ids[]" value="${cb.value}">`).join('');
    }
    openModal(bulkDeleteModal);
  });
});
