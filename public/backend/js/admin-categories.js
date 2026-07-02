document.addEventListener("DOMContentLoaded", () => {
  const filterToggle = document.querySelector("[data-account-filter-toggle]");
  const filter = document.querySelector("[data-account-filter]");
  const rows = [...document.querySelectorAll("[data-category-row]")];

  // NOTE: Dieu khien dong/mo bo loc nang cao, form filter submit GET ve Laravel.
  // Dung chung ten data-attribute voi man tai khoan/dich vu de tai su dung cung 1 kieu UI.
  filterToggle?.addEventListener("click", () => {
    const isOpen = filterToggle.getAttribute("aria-expanded") === "true";
    const section = filterToggle.closest(".account-filter-section");
    filterToggle.setAttribute("aria-expanded", String(!isOpen));
    section?.classList.toggle("is-open", !isOpen);
    if (filter) {
      filter.hidden = isOpen;
    }
  });

  // NOTE: Chon dong bang click de giu cam giac table hien tai, giong man tai khoan/dich vu.
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

  document.addEventListener("click", () => {
    document.querySelectorAll(".account-row-actions.is-open").forEach((wrapper) => {
      wrapper.classList.remove("is-open");
      wrapper.querySelector(".account-row-menu")?.setAttribute("hidden", "");
    });
  });

  const categoryModal = document.querySelector('[data-category-modal="form"]');
  const deleteModal = document.querySelector('[data-category-modal="delete"]');
  const categoryForm = document.querySelector("[data-category-form]");
  const categoryFormMethod = document.querySelector("[data-category-form-method]");
  const categoryModalTitle = document.querySelector("[data-category-modal-title]");
  const categorySubmitBtn = document.querySelector("[data-category-submit]");
  const deleteForm = document.querySelector("[data-category-delete-form]");
  const deleteCategoryName = document.querySelector("[data-category-delete-name]");

  if (categoryForm) {
    categoryForm.dataset.storeUrl = categoryForm.getAttribute("action");
  }

  function parseCategory(button) {
    try {
      return JSON.parse(button.dataset.category || "{}");
    } catch (error) {
      return {};
    }
  }

  function closeModals() {
    document.querySelectorAll("[data-category-modal]").forEach((modal) => {
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
    const field = categoryForm?.querySelector(`[name="${name}"]`);
    if (field) {
      field.value = value ?? "";
    }
  }

  // NOTE: Bat/tat toan bo input trong form theo che do xem/tao/sua.
  // Che do "view" chi doc du lieu, khong cho sua va an luon nut Luu.
  function setFormReadonly(isReadonly) {
    categoryForm?.querySelectorAll("input, select").forEach((field) => {
      field.disabled = isReadonly;
    });
    if (categorySubmitBtn) {
      categorySubmitBtn.hidden = isReadonly;
    }
  }

  // NOTE: Mo modal tao loai san pham moi voi action store va method POST.
  function prepareCreateForm() {
    categoryForm?.reset();
    setField("dich_vu_id", "");
    setField("loai_san_pham_cha_id", "");
    setField("trang_thai", "hoat_dong");
    setField("thu_tu", "0");
    if (categoryForm?.dataset.storeUrl) {
      categoryForm.action = categoryForm.dataset.storeUrl;
    }
    if (categoryFormMethod) {
      categoryFormMethod.value = "POST";
    }
    setFormReadonly(false);
    if (categoryModalTitle) {
      categoryModalTitle.textContent = "Thêm mới loại sản phẩm";
    }
  }

  // NOTE: Do du lieu loai san pham dang chon vao form, dung chung cho ca che do sua va xem.
  function fillCategoryData(category) {
    setField("ma_loai_san_pham", category.ma_loai_san_pham);
    setField("ten_loai_san_pham", category.ten_loai_san_pham);
    setField("dich_vu_id", category.dich_vu_id ?? "");
    setField("loai_san_pham_cha_id", category.loai_san_pham_cha_id ?? "");
    setField("trang_thai", category.trang_thai);
    setField("thu_tu", category.thu_tu ?? 0);
    setField("hinh_anh", category.hinh_anh);
    setField("mo_ta", category.mo_ta);
  }

  // NOTE: Mo modal sua loai san pham, submit PUT ve route update tuong ung.
  function prepareEditForm(category) {
    categoryForm?.reset();
    fillCategoryData(category);
    if (categoryForm) {
      categoryForm.action = category.urls?.update || "#";
    }
    if (categoryFormMethod) {
      categoryFormMethod.value = "PUT";
    }
    setFormReadonly(false);
    if (categoryModalTitle) {
      categoryModalTitle.textContent = `Chỉnh sửa loại sản phẩm: ${category.ten_loai_san_pham || ""}`;
    }
  }

  // NOTE: Mo modal o che do chi xem, khong submit di dau ca.
  function prepareViewForm(category) {
    categoryForm?.reset();
    fillCategoryData(category);
    setFormReadonly(true);
    if (categoryModalTitle) {
      categoryModalTitle.textContent = `Chi tiết loại sản phẩm: ${category.ten_loai_san_pham || ""}`;
    }
  }

  function prepareDeleteForm(category) {
    if (deleteForm) {
      deleteForm.action = category.urls?.destroy || "#";
    }
    if (deleteCategoryName) {
      deleteCategoryName.textContent = category.ten_loai_san_pham || "";
    }
  }

  document.querySelectorAll("[data-category-modal-open]").forEach((button) => {
    button.addEventListener("click", (event) => {
      event.stopPropagation();
      const target = button.dataset.categoryModalOpen;
      const category = parseCategory(button);

      if (target === "create") {
        prepareCreateForm();
        openModal(categoryModal);
      }

      if (target === "edit") {
        prepareEditForm(category);
        openModal(categoryModal);
      }

      if (target === "view") {
        prepareViewForm(category);
        openModal(categoryModal);
      }

      if (target === "delete") {
        prepareDeleteForm(category);
        openModal(deleteModal);
      }
    });
  });

  document.querySelectorAll("[data-category-modal-close]").forEach((button) => {
    button.addEventListener("click", closeModals);
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      closeModals();
    }
  });
});
