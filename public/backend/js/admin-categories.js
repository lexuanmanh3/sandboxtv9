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

  const fileInput = document.querySelector("[data-category-file-input]");
  const imageTextInput = document.querySelector("[data-category-image-input]");
  const imagePreview = document.querySelector("[data-category-image-preview]");
  const imagePlaceholder = document.querySelector("[data-category-image-placeholder]");

  function resolveImageUrl(src) {
    if (!src || typeof src !== "string") return "";
    src = src.trim();
    if (src.startsWith("data:") || src.startsWith("blob:") || src.startsWith("http://") || src.startsWith("https://")) {
      return src;
    }
    const baseUrl = window.APP_ASSET_BASE_URL || "/";
    const cleanSrc = src.startsWith("/") ? src.slice(1) : src;
    const cleanBase = baseUrl.endsWith("/") ? baseUrl : baseUrl + "/";
    return cleanBase + cleanSrc;
  }

  function updateImagePreview(src) {
    const fullUrl = resolveImageUrl(src);
    if (fullUrl && imagePreview && imagePlaceholder) {
      imagePreview.src = fullUrl;
      imagePreview.style.display = "block";
      imagePlaceholder.style.display = "none";
      imagePreview.onerror = () => {
        imagePreview.style.display = "none";
        imagePlaceholder.style.display = "block";
        imagePlaceholder.textContent = "Lỗi ảnh";
      };
      imagePreview.onload = () => {
        imagePreview.style.display = "block";
        imagePlaceholder.style.display = "none";
      };
    } else if (imagePreview && imagePlaceholder) {
      imagePreview.src = "";
      imagePreview.style.display = "none";
      imagePlaceholder.style.display = "block";
      imagePlaceholder.textContent = "Chưa có ảnh";
    }
  }

  fileInput?.addEventListener("change", () => {
    const file = fileInput.files?.[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = (e) => {
        updateImagePreview(e.target?.result);
      };
      reader.readAsDataURL(file);
    }
  });

  imageTextInput?.addEventListener("input", () => {
    const val = imageTextInput.value.trim();
    if (val) {
      updateImagePreview(val);
    } else {
      updateImagePreview("");
    }
  });

  // NOTE: Mo modal tao loai san pham moi voi action store va method POST.
  function prepareCreateForm() {
    categoryForm?.reset();
    setField("dich_vu_id", "");
    setField("loai_san_pham_cha_id", "");
    setField("trang_thai", "hoat_dong");
    setField("thu_tu", "0");
    if (fileInput) fileInput.value = "";
    updateImagePreview("");
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
    if (fileInput) fileInput.value = "";
    updateImagePreview(category.hinh_anh);
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
  const bulkDeleteHiddenInputs = document.getElementById("bulkCategoryDeleteHiddenInputs");
  const bulkDeleteConfirmCount = document.getElementById("bulkCategoryDeleteConfirmCount");
  const bulkDeleteModal = document.querySelector('[data-category-modal="bulk-delete"]');

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
