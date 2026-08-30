document.addEventListener("DOMContentLoaded", () => {
  const filterToggle = document.querySelector("[data-account-filter-toggle]");
  const filter = document.querySelector("[data-account-filter]");
  const rows = [...document.querySelectorAll("[data-service-row]")];

  // NOTE: Dieu khien dong/mo bo loc nang cao, form filter submit GET ve Laravel.
  // Dung chung ten data-attribute voi man tai khoan de tai su dung cung 1 kieu UI.
  filterToggle?.addEventListener("click", () => {
    const isOpen = filterToggle.getAttribute("aria-expanded") === "true";
    const section = filterToggle.closest(".account-filter-section");
    filterToggle.setAttribute("aria-expanded", String(!isOpen));
    section?.classList.toggle("is-open", !isOpen);
    if (filter) {
      filter.hidden = isOpen;
    }
  });

  // NOTE: Chon dong bang click de giu cam giac table hien tai, giong man tai khoan.
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

  const serviceModal = document.querySelector('[data-service-modal="form"]');
  const deleteModal = document.querySelector('[data-service-modal="delete"]');
  const serviceForm = document.querySelector("[data-service-form]");
  const serviceFormMethod = document.querySelector("[data-service-form-method]");
  const serviceModalTitle = document.querySelector("[data-service-modal-title]");
  const serviceSubmitBtn = document.querySelector("[data-service-submit]");
  const deleteForm = document.querySelector("[data-service-delete-form]");
  const deleteServiceName = document.querySelector("[data-service-delete-name]");

  if (serviceForm) {
    serviceForm.dataset.storeUrl = serviceForm.getAttribute("action");
  }

  function parseService(button) {
    try {
      return JSON.parse(button.dataset.service || "{}");
    } catch (error) {
      return {};
    }
  }

  function closeModals() {
    document.querySelectorAll("[data-service-modal]").forEach((modal) => {
      modal.hidden = true;
    });
    document.body.classList.remove("account-modal-open");
  }

  function openModal(modal) {
    if (!modal) return;
    modal.hidden = false;
    document.body.classList.add("account-modal-open");
  }

  function setField(name, value) {
    const field = serviceForm?.querySelector(`[name="${name}"]`);
    if (field) {
      field.value = value ?? "";
    }
  }

  // NOTE: Bat/tat toan bo input trong form theo che do xem/tao/sua.
  // Che do "view" chi doc du lieu, khong cho sua va an luon nut Luu.
  function setFormReadonly(isReadonly) {
    serviceForm?.querySelectorAll("input, select").forEach((field) => {
      field.disabled = isReadonly;
    });
    if (serviceSubmitBtn) {
      serviceSubmitBtn.hidden = isReadonly;
    }
  }

  // NOTE: Mo modal tao dich vu moi voi action store va method POST.
  function prepareCreateForm() {
    serviceForm?.reset();
    setField("trang_thai", "hoat_dong");
    setField("thu_tu", "0");
    if (serviceForm?.dataset.storeUrl) {
      serviceForm.action = serviceForm.dataset.storeUrl;
    }
    if (serviceFormMethod) {
      serviceFormMethod.value = "POST";
    }
    setFormReadonly(false);
    if (serviceModalTitle) {
      serviceModalTitle.textContent = "Thêm mới dịch vụ";
    }
  }

  // NOTE: Do du lieu dich vu dang chon vao form, dung chung cho ca che do sua va xem.
  function fillServiceData(service) {
    setField("ma_dich_vu", service.ma_dich_vu);
    setField("ten_dich_vu", service.ten_dich_vu);
    setField("trang_thai", service.trang_thai);
    setField("thu_tu", service.thu_tu ?? 0);
    setField("mo_ta", service.mo_ta);
  }

  // NOTE: Mo modal sua dich vu, submit PUT ve route update tuong ung.
  function prepareEditForm(service) {
    serviceForm?.reset();
    fillServiceData(service);
    if (serviceForm) {
      serviceForm.action = service.urls?.update || "#";
    }
    if (serviceFormMethod) {
      serviceFormMethod.value = "PUT";
    }
    setFormReadonly(false);
    if (serviceModalTitle) {
      serviceModalTitle.textContent = `Chỉnh sửa dịch vụ: ${service.ten_dich_vu || ""}`;
    }
  }

  // NOTE: Mo modal o che do chi xem, khong submit di dau ca.
  function prepareViewForm(service) {
    serviceForm?.reset();
    fillServiceData(service);
    setFormReadonly(true);
    if (serviceModalTitle) {
      serviceModalTitle.textContent = `Chi tiết dịch vụ: ${service.ten_dich_vu || ""}`;
    }
  }

  function prepareDeleteForm(service) {
    if (deleteForm) {
      deleteForm.action = service.urls?.destroy || "#";
    }
    if (deleteServiceName) {
      deleteServiceName.textContent = service.ten_dich_vu || "";
    }
  }

  document.querySelectorAll("[data-service-modal-open]").forEach((button) => {
    button.addEventListener("click", (event) => {
      event.stopPropagation();
      const target = button.dataset.serviceModalOpen;
      const service = parseService(button);

      if (target === "create") {
        prepareCreateForm();
        openModal(serviceModal);
      }

      if (target === "edit") {
        prepareEditForm(service);
        openModal(serviceModal);
      }

      if (target === "view") {
        prepareViewForm(service);
        openModal(serviceModal);
      }

      if (target === "delete") {
        prepareDeleteForm(service);
        openModal(deleteModal);
      }
    });
  });

  document.querySelectorAll("[data-service-modal-close]").forEach((button) => {
    button.addEventListener("click", closeModals);
  });

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
  const itemCheckboxes = document.querySelectorAll(".bulk-item-checkbox");
  const bulkActionBar = document.getElementById("bulkActionBar");
  const bulkSelectedCount = document.getElementById("bulkSelectedCount");
  const bulkDeselectBtn = document.getElementById("bulkDeselectBtn");
  const bulkDeleteBtn = document.getElementById("bulkDeleteBtn");
  const bulkDeleteHiddenInputs = document.getElementById("bulkServiceDeleteHiddenInputs");
  const bulkDeleteConfirmCount = document.getElementById("bulkServiceDeleteConfirmCount");
  const bulkDeleteModal = document.querySelector('[data-service-modal="bulk-delete"]');

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
