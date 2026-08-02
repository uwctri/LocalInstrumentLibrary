import { getBadgeTypeClass, renderFieldListItem, compareSemanticVersions } from './utils.js';

const getCookie = (name) => {
    const ca = document.cookie.split(';');
    for (let i = 0; i < ca.length; i++) {
        let c = ca[i].trim();
        if (c.indexOf(name + "=") === 0) return c.substring(name.length + 1);
    }
    return null;
};

const setCookie = (name, value, days) => {
    let expires = days ? "; expires=" + new Date(Date.now() + days * 864e5).toUTCString() : "";
    document.cookie = name + "=" + (value || "") + expires + "; path=/; SameSite=Lax";
};

const getCardsPerRow = () => {
    const w = window.innerWidth;
    return w >= 2000 ? 4 : w >= 1600 ? 3 : w >= 1100 ? 2 : 1;
};

const showToast = (message, isError = false) => {
    const toast = $("#lib-toast"), icon = toast.find("i");
    if (isError) icon.removeClass("fa-circle-check text-success success-icon").addClass("fa-circle-xmark text-danger");
    else icon.removeClass("fa-circle-xmark text-danger").addClass("fa-circle-check text-success");
    $("#toast-message").text(message);
    toast.addClass("show");
    setTimeout(() => toast.removeClass("show"), 12000);
};

let previewModal = new bootstrap.Modal(document.getElementById('preview-modal'));
let submitModal = new bootstrap.Modal(document.getElementById('submit-modal'));
let selectedCategories = ["All"];
let currentPage = 1;
let rowsPerPage = typeof defaultRowsPerPage !== "undefined" ? defaultRowsPerPage : 2;

const savedRows = parseInt(getCookie("lib_rows_per_page"));
if (!isNaN(savedRows) && savedRows > 0) {
    rowsPerPage = savedRows;
    $("#rows-per-page-input").val(savedRows);
}

$("#rows-per-page-input").on("input change", (e) => {
    const newRows = parseInt($(e.currentTarget).val());
    if (!isNaN(newRows) && newRows > 0) setCookie("lib_rows_per_page", newRows, 365);
    currentPage = 1;
    filterGrid();
});

$("#lib-search").on("input", () => { currentPage = 1; filterGrid(); });

$("#pagination-controls").on("click", ".page-item:not(.disabled):not(.active)", (e) => {
    currentPage = parseInt($(e.target).closest(".page-item").attr("data-page")) || 1;
    filterGrid();
    $("html, body").animate({ scrollTop: $("#instruments-grid").offset().top - 20 }, 200);
});

$("#category-dropdown-trigger").on("click", (e) => {
    e.stopPropagation();
    const menu = $("#category-dropdown-menu").toggleClass("d-none");
    if (!menu.hasClass("d-none")) {
        $("#category-search").focus().val("");
        filterDropdownOptions("");
    }
});

$(document).on("click", (e) => {
    if (!$(e.target).closest(".category-select-wrapper").length) $("#category-dropdown-menu").addClass("d-none");
});

$("#category-search").on("input", (e) => filterDropdownOptions($(e.currentTarget).val().toLowerCase().trim()));

const filterDropdownOptions = (query) => {
    $("#category-dropdown-menu .dropdown-item").each((idx, el) => {
        const text = $(el).text().toLowerCase();
        if (text.includes(query) || $(el).data("value") === "All") $(el).removeClass("d-none");
        else $(el).addClass("d-none");
    });
};

$("#category-dropdown-menu").on("click", ".dropdown-item", (e) => {
    e.stopPropagation();
    const $this = $(e.target).closest(".dropdown-item"), val = $this.data("value"), check = $this.find(".check-icon");
    if (val === "All") {
        selectedCategories = ["All"];
        $("#category-dropdown-menu .dropdown-item").removeClass("selected");
        $("#category-dropdown-menu .dropdown-item .check-icon").addClass("d-none");
        $this.addClass("selected");
        check.removeClass("d-none");
    } else {
        const allIdx = selectedCategories.indexOf("All");
        if (allIdx > -1) {
            selectedCategories.splice(allIdx, 1);
            $("#category-dropdown-menu .dropdown-item[data-value='All']").removeClass("selected");
            $("#category-dropdown-menu .dropdown-item[data-value='All'] .check-icon").addClass("d-none");
        }
        const idx = selectedCategories.indexOf(val);
        if (idx > -1) {
            selectedCategories.splice(idx, 1);
            $this.removeClass("selected");
            check.addClass("d-none");
        } else {
            selectedCategories.push(val);
            $this.addClass("selected");
            check.removeClass("d-none");
        }
        if (selectedCategories.length === 0) {
            selectedCategories = ["All"];
            const allItem = $("#category-dropdown-menu .dropdown-item[data-value='All']");
            allItem.addClass("selected");
            allItem.find(".check-icon").removeClass("d-none");
        }
    }
    updateDropdownTriggerLabel();
    $("#lib-category-select").val(selectedCategories.join(","));
    currentPage = 1;
    filterGrid();
});

const updateDropdownTriggerLabel = () => {
    if (selectedCategories.includes("All")) $("#selected-category-text").text("All Categories");
    else if (selectedCategories.length === 1) $("#selected-category-text").text(selectedCategories[0]);
    else if (selectedCategories.length === 2) $("#selected-category-text").text(selectedCategories.join(", "));
    else $("#selected-category-text").text(selectedCategories.length + " Categories Selected");
};

const dropZone = $("#file-drop-zone"), fileInput = $("#submit-file-input"), uploadStatus = $("#file-upload-status"), fileInvalidMsg = $("#file-invalid-msg");

dropZone.on("click", () => fileInput.click());
fileInput.on("change", (e) => handleSelectedFiles(e.target.files));

dropZone.on("dragover", (e) => { e.preventDefault(); e.stopPropagation(); dropZone.css({ "border-color": "#4f46e5", "background-color": "#f1f5f9" }); });
dropZone.on("dragleave", (e) => { e.preventDefault(); e.stopPropagation(); dropZone.css({ "border-color": "#cbd5e1", "background-color": "#f8fafc" }); });
dropZone.on("drop", (e) => {
    e.preventDefault(); e.stopPropagation();
    dropZone.css({ "border-color": "#cbd5e1", "background-color": "#f8fafc" });
    handleSelectedFiles(e.originalEvent.dataTransfer.files);
});

let attachedFile = null;

const handleSelectedFiles = (files) => {
    if (!files || files.length === 0) return;
    const file = files[0];
    if (file.name.endsWith(".zip")) {
        attachedFile = file;
        uploadStatus.text("Attached file: " + file.name).removeClass("d-none text-danger").addClass("text-primary");
        fileInvalidMsg.addClass("d-none");
        dropZone.find("i").removeClass("fa-cloud-arrow-up").addClass("fa-file-zipper");
    } else {
        attachedFile = null;
        uploadStatus.text("Error: Please upload a ZIP file package.").removeClass("d-none text-primary").addClass("text-danger");
        dropZone.find("i").removeClass("fa-file-zipper").addClass("fa-cloud-arrow-up");
    }
};

$("#submit-modal").on("show.bs.modal", () => $("#submit-author-email").attr("placeholder", "jane.doe@" + contactEmailDomain));

const clearValidationState = () => {
    const form = $("#submit-instrument-form");
    form.removeClass("was-validated");
    form.find(".is-invalid, .is-valid").removeClass("is-invalid is-valid");
    $(".submit-cat-checkbox").closest(".p-3").removeClass("border-danger");
    $("#categories-invalid-msg, #file-invalid-msg").addClass("d-none");
    $("#file-drop-zone").removeClass("border-danger").css("border-color", "");
};

let activeTab = "zip";

$('button[data-bs-toggle="tab"]').on('shown.bs.tab', (e) => {
    activeTab = e.target.id === "project-tab" ? "project" : "zip";
    clearValidationState();
    $("#submit-project-instrument").prop("disabled", activeTab !== "project");
});

$('input[name="submission-type"]').on("change", (e) => {
    const val = $(e.currentTarget).val();
    const wrapper = $("#existing-instrument-wrapper"), selectEl = $("#submit-existing-instrument"), titleWrapper = $("#submit-title-wrapper"), titleInput = $("#submit-title"), versionInput = $("#submit-version"), versionInfo = $("#version-current-info"), versionInvalid = $("#version-invalid-feedback");
    clearValidationState();
    if (val === "update") {
        wrapper.show();
        selectEl.prop({ required: true, disabled: false });
        titleWrapper.hide();
        titleInput.prop("required", false).removeClass("is-invalid").val("");
        versionInput.removeClass("is-invalid");
        versionInvalid.text("Please specify a version.");
        triggerVersionCheck();
        return;
    }
    wrapper.hide();
    selectEl.prop({ required: false, disabled: true }).removeClass("is-invalid").val("");
    titleWrapper.show();
    titleInput.prop("required", true);
    versionInfo.addClass("d-none").text("");
    versionInput.removeClass("is-invalid");
    versionInvalid.text("Please specify a version.");
    $(".submit-cat-checkbox").prop("checked", false);
    $("#submit-author-name, #submit-author-email, #submit-description").val("");
    $("#submit-version").val("1.0.0");
    $("#categories-invalid-msg").addClass("d-none").removeClass("d-block");
    $(".submit-cat-checkbox").closest(".p-3").removeClass("border-danger");
});

$("#submit-existing-instrument").on("change", (e) => {
    clearValidationState();
    triggerVersionCheck();
    const data = instrumentFields[$(e.currentTarget).val()];
    $(".submit-cat-checkbox").prop("checked", false);
    if (!data) {
        $("#submit-author-name, #submit-author-email, #submit-description").val("");
        $("#submit-version").val("1.0.0");
        return;
    }
    if (data.categories) data.categories.forEach((catName) => $(".submit-cat-checkbox[value='" + catName + "']").prop("checked", true));
    $("#submit-author-name").val(data.author_name || "");
    $("#submit-author-email").val(data.author_email || "");
    $("#submit-description").val(data.description || "");
    if (data.version) $("#submit-version").val(data.version.replace(/^v/i, ''));
});

const triggerVersionCheck = (runValidation = false) => {
    const currentVer = $("#submit-existing-instrument option:selected").attr("data-version");
    if (currentVer) $("#version-current-info").removeClass("d-none").html('<i class="fa-solid fa-info-circle me-1"></i>Current live version: <strong>' + currentVer + '</strong>');
    else $("#version-current-info").addClass("d-none").text("");
    if (runValidation || $("#submit-instrument-form").hasClass("was-validated")) validateVersionInput();
    else $("#submit-version").removeClass("is-invalid");
};

$("#submit-version").on("input", () => validateVersionInput());

const validateVersionInput = () => {
    const subType = $('input[name="submission-type"]:checked').val(), versionInput = $("#submit-version"), versionInvalid = $("#version-invalid-feedback"), currentVer = $("#submit-existing-instrument option:selected").attr("data-version");
    const enteredVer = versionInput.val().trim();
    if (!enteredVer) {
        versionInput.addClass("is-invalid");
        versionInvalid.text("Please specify a version.");
        return false;
    }
    if (!/^\d+(\.\d+)*$/.test(enteredVer)) {
        versionInput.addClass("is-invalid");
        versionInvalid.text("Please enter a valid numeric version (1.2.0).");
        return false;
    }
    if (subType === "update" && currentVer && compareSemanticVersions(enteredVer, currentVer) <= 0) {
        versionInput.addClass("is-invalid");
        versionInvalid.html("Must be higher than current version <strong>" + currentVer + "</strong>.");
        return false;
    }
    versionInput.removeClass("is-invalid");
    return true;
};

$(".submit-cat-checkbox").on("change", () => {
    if ($(".submit-cat-checkbox:checked").length > 0) {
        $("#categories-invalid-msg").addClass("d-none").removeClass("d-block");
        $(".submit-cat-checkbox").closest(".p-3").removeClass("border-danger");
    }
});

$("#submit-project-instrument").on("change", () => clearValidationState());

$("#submit-instrument-form").on("submit", (e) => {
    e.preventDefault();
    const form = e.currentTarget, titleInput = $("#submit-title"), authorNameInput = $("#submit-author-name"), authorEmailInput = $("#submit-author-email"), descInput = $("#submit-description"), projectInstrumentSelect = $("#submit-project-instrument"), existingInstrumentSelect = $("#submit-existing-instrument"), subType = $('input[name="submission-type"]:checked').val();

    let isFormValid = form.checkValidity();
    $(form).addClass("was-validated");

    let isCategoryChecked = $(".submit-cat-checkbox:checked").length > 0;
    if (!isCategoryChecked) {
        $("#categories-invalid-msg").removeClass("d-none").addClass("d-block");
        $(".submit-cat-checkbox").closest(".p-3").addClass("border-danger");
    } else {
        $("#categories-invalid-msg").addClass("d-none").removeClass("d-block");
        $(".submit-cat-checkbox").closest(".p-3").removeClass("border-danger");
    }

    let isTabValid = true;
    if (activeTab === "zip" && !attachedFile) {
        isTabValid = false;
        fileInvalidMsg.removeClass("d-none");
        dropZone.addClass("border-danger").css("border-color", "#dc3545");
    } else if (activeTab === "zip") {
        fileInvalidMsg.addClass("d-none");
        dropZone.removeClass("border-danger").css("border-color", "");
    } else if (activeTab === "project" && !projectInstrumentSelect.val()) {
        isTabValid = false;
        projectInstrumentSelect.addClass("is-invalid");
    }

    let isExistingValid = subType !== "update" || !!existingInstrumentSelect.val();
    if (!isExistingValid) existingInstrumentSelect.addClass("is-invalid");

    let isVersionValid = validateVersionInput();
    if (!isFormValid || !isCategoryChecked || !isTabValid || !isExistingValid || !isVersionValid) return;

    const submitBtn = $("#submit-form-btn");
    submitBtn.prop("disabled", true).html('<span class="spinner-border spinner-border-sm me-2" role="status"></span>Submitting...');
    $(form).find("input, select, textarea, button").prop("disabled", true);

    const titleText = subType === "update" ? $("#submit-existing-instrument option:selected").text().replace(/\s*\(v.*\)/i, '') : titleInput.val();
    const categories = [];
    $(".submit-cat-checkbox:checked").each((idx, el) => categories.push($(el).val()));

    const payload = { subType, type: activeTab, title: titleText, description: descInput.val(), author_name: authorNameInput.val(), author_email: authorEmailInput.val(), version: $("#submit-version").val(), categories, existingId: existingInstrumentSelect.val() };

    const performSubmit = (payloadData) => {
        module.ajax('submit', payloadData).then((response) => {
            $(form).find("input, select, textarea, button").prop("disabled", false);
            submitBtn.html('<i class="fa-solid fa-paper-plane"></i><span>Submit Request</span>');
            let data = typeof response === "string" ? JSON.parse(response) : response;
            if (!data || data.status !== 'success') {
                alert(data && data.message ? data.message : "An error occurred during submission.");
                return;
            }
            submitModal.hide();
            $(form).removeClass("was-validated");
            form.reset();
            attachedFile = null;
            uploadStatus.addClass("d-none");
            dropZone.find("i").removeClass("fa-cloud-arrow-up").addClass("fa-file-zipper");
            showToast(data.message || "Submission successful! Reloading...");
            setTimeout(() => window.location.reload(), 1000);
        }).catch((error) => {
            $(form).find("input, select, textarea, button").prop("disabled", false);
            submitBtn.html('<i class="fa-solid fa-paper-plane"></i><span>Submit Request</span>');
            alert("AJAX request failed: " + error);
        });
    };

    if (activeTab === "zip") {
        const reader = new FileReader();
        reader.onload = (event) => {
            payload.fileData = event.target.result.split(',')[1];
            payload.fileName = attachedFile.name;
            performSubmit(payload);
        };
        reader.readAsDataURL(attachedFile);
    } else {
        payload.instrumentName = projectInstrumentSelect.val();
        performSubmit(payload);
    }
});

const filterGrid = () => {
    const query = $("#lib-search").val().toLowerCase().trim(), matchingCards = [];
    $(".instrument-card-col").each((idx, el) => {
        const title = $(el).data("title").toLowerCase(), desc = $(el).data("desc").toLowerCase(), cats = ($(el).data("categories") || "").split(",");
        const matchesQuery = query === "" || title.includes(query) || desc.includes(query);
        const matchesCat = selectedCategories.includes("All") || cats.some(cat => selectedCategories.includes(cat));
        if (matchesQuery && matchesCat) matchingCards.push($(el));
        else $(el).hide();
    });

    const totalItems = matchingCards.length;
    if (totalItems === 0) {
        $("#instruments-grid, #lib-pagination-container").hide();
        $("#lib-empty").fadeIn(200);
        return;
    }

    $("#lib-empty").hide();
    $("#instruments-grid").show();

    let rows = parseInt($("#rows-per-page-input").val());
    if (isNaN(rows) || rows <= 0) rows = typeof defaultRowsPerPage !== "undefined" ? defaultRowsPerPage : 2;
    rowsPerPage = rows;

    const limit = rows * getCardsPerRow(), totalPages = Math.ceil(totalItems / limit);
    if (currentPage > totalPages) currentPage = Math.max(1, totalPages);

    const startIndex = (currentPage - 1) * limit, endIndex = Math.min(startIndex + limit, totalItems);
    matchingCards.forEach(($card, index) => {
        if (index >= startIndex && index < endIndex) $card.show();
        else $card.hide();
    });

    renderPagination(totalItems, totalPages, startIndex, endIndex);
};

const renderPagination = (totalItems, totalPages, startIndex, endIndex) => {
    $("#lib-pagination-container").show();
    $("#pagination-info").text(`Showing ${startIndex + 1}-${endIndex} of ${totalItems} instruments`);
    const controls = $("#pagination-controls").empty();
    if (totalPages <= 1) return controls.hide();
    controls.show();

    controls.append(`<li class="page-item ${currentPage === 1 ? 'disabled' : ''}" data-page="${currentPage - 1}"><span class="page-link">&laquo; Prev</span></li>`);
    for (let p = 1; p <= totalPages; p++) controls.append(`<li class="page-item ${p === currentPage ? 'active' : ''}" data-page="${p}"><span class="page-link">${p}</span></li>`);
    controls.append(`<li class="page-item ${currentPage === totalPages ? 'disabled' : ''}" data-page="${currentPage + 1}"><span class="page-link">Next &raquo;</span></li>`);
};

const openPreview = (instId) => {
    const data = instrumentFields[instId];
    if (!data) return;

    $("#modal-title").text(data.title);
    const modalCats = $("#modal-categories").empty();
    data.categories.forEach((cat) => {
        const catName = (typeof cat === 'object' && cat !== null) ? cat.name : cat;
        modalCats.append(`<span class="badge category-${String(catName).toLowerCase().replace(/\s+/g, '')} px-2.5 py-1.5 text-uppercase fw-bold" style="font-size: 0.7rem;">${catName}</span>`);
    });

    $("#modal-version").text(data.version);
    $("#modal-author").text(data.author_name);
    $("#modal-author-email").attr("href", "mailto:" + data.author_email);
    $("#modal-desc").text(data.description);
    $("#modal-fields-heading").text("Instrument Fields (" + data.fields.length + ")");

    const list = $("#modal-field-list").empty();
    data.fields.forEach((field) => list.append(renderFieldListItem(field)));

    if (!isPublicView) {
        const modalInstallBtn = $("#modal-install-btn"), modalRemoveBtn = $("#modal-remove-btn"), cardInstallBtn = $(".instrument-card-col[data-id='" + instId + "'] .btn-install");
        if (cardInstallBtn.hasClass("is-installed")) {
            modalInstallBtn.addClass("is-installed").prop("disabled", true).html('<i class="fa-solid fa-check me-2"></i>Installed');
            modalRemoveBtn.removeClass("d-none").off("click").on("click", () => { previewModal.hide(); removeInstrument(instId); });
        } else {
            modalInstallBtn.removeClass("is-installed").prop("disabled", false).html('<i class="fa-solid fa-download me-2"></i>Install to Project').off("click").on("click", () => {
                previewModal.hide();
                installInstrument($(".instrument-card-col[data-id='" + instId + "'] .btn-install")[0], instId);
            });
            modalRemoveBtn.addClass("d-none");
        }
    }

    $("#modal-download-btn").off("click").on("click", (e) => {
        e.preventDefault();
        let docId = (data.versions && data.versions.length > 0) ? data.versions[data.versions.length - 1].doc_id : null;
        if (docId && /^\d+$/.test(docId)) {
            window.location.href = module.getUrl('download.php') + '&doc_id=' + docId;
            showToast("Starting download for '" + data.title + "'...");
        } else {
            const blob = new Blob(["PK\x03\x04\n\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x0e\x00\t\x00metadata.json" + JSON.stringify(data, null, 2)], { type: "application/zip" });
            const url = URL.createObjectURL(blob), a = document.createElement("a");
            a.href = url; a.download = data.id + "_instrument.zip";
            document.body.appendChild(a); a.click(); document.body.removeChild(a);
            URL.revokeObjectURL(url);
            showToast("Downloaded package for '" + data.title + "'!");
        }
    });

    previewModal.show();
};

const installInstrument = (buttonEl, instId) => {
    const btn = $(buttonEl);
    if (btn.hasClass("is-installed") || btn.prop("disabled")) return;
    btn.prop("disabled", true).html('<i class="fa-solid fa-spinner fa-spin me-2"></i>Installing...');
    module.ajax('install', { id: instId }).then((response) => {
        if (!response || response.status !== "success") {
            btn.prop("disabled", false).html('<i class="fa-solid fa-download me-2"></i>Install to Project');
            showToast((response && response.message) ? response.message : "Failed to install instrument.", true);
            return;
        }
        const data = instrumentFields[instId];
        if (data) data.installed = true;
        $(".instrument-card-col[data-id='" + instId + "'] .instrument-card").addClass("installed");
        btn.addClass("is-installed").removeClass("btn-primary").addClass("btn-success").html('<i class="fa-solid fa-check me-2"></i>Installed').prop("disabled", true);
        installedCount++;
        $("#stat-installed").text(installedCount + " Installed in Project");
        showToast("'" + (data ? data.title : "Instrument") + "' installed to this project!");
    }).catch((xhr) => {
        btn.prop("disabled", false).html('<i class="fa-solid fa-download me-2"></i>Install to Project');
        showToast((xhr && xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : "Failed to install instrument.", true);
    });
};

const removeInstrument = (instId) => {
    const btn = $($(".instrument-card-col[data-id='" + instId + "'] .btn-install")[0]);
    if (!btn.hasClass("is-installed")) return;
    btn.prop("disabled", true).html('<i class="fa-solid fa-spinner fa-spin me-2"></i>Removing...');
    module.ajax('uninstall', { id: instId }).then((response) => {
        if (!response || response.status !== "success") {
            btn.prop("disabled", true).html('<i class="fa-solid fa-check me-2"></i>Installed');
            showToast((response && response.message) ? response.message : "Failed to remove instrument.", true);
            return;
        }
        const data = instrumentFields[instId];
        if (data) data.installed = false;
        $(".instrument-card-col[data-id='" + instId + "'] .instrument-card").removeClass("installed");
        btn.removeClass("is-installed btn-success").addClass("btn-primary").html('<i class="fa-solid fa-download me-2"></i>Install').prop("disabled", false);
        if (installedCount > 0) installedCount--;
        $("#stat-installed").text(installedCount + " Installed");
        showToast("'" + (data ? data.title : "Instrument") + "' removed from this project!");
    }).catch((xhr) => {
        btn.prop("disabled", true).html('<i class="fa-solid fa-check me-2"></i>Installed');
        showToast((xhr && xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : "Failed to remove instrument.", true);
    });
};

window.openPreview = openPreview;
window.installInstrument = installInstrument;

let lastCardsPerRow = getCardsPerRow();
$(window).on("resize", () => {
    const current = getCardsPerRow();
    if (current !== lastCardsPerRow) {
        lastCardsPerRow = current;
        filterGrid();
    }
});

filterGrid();
