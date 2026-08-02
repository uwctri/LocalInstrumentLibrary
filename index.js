import { escapeHtml, getBadgeTypeClass, renderCategoryBadges, getBadgeForType, renderFieldListItem, compareSemanticVersions } from './utils.js';

// 0. Scoped Utility Helpers (Declared first to avoid initialization order ReferenceErrors)
const getCookie = (name) => {
    let nameEQ = name + "=";
    let ca = document.cookie.split(';');
    for (let i = 0; i < ca.length; i++) {
        let c = ca[i];
        while (c.charAt(0) === ' ') c = c.substring(1, c.length);
        if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
    }
    return null;
};

const setCookie = (name, value, days) => {
    let expires = "";
    if (days) {
        let date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        expires = "; expires=" + date.toUTCString();
    }
    document.cookie = name + "=" + (value || "") + expires + "; path=/; SameSite=Lax";
};



const getCardsPerRow = () => {
    const width = window.innerWidth;
    if (width >= 2000) return 4;
    if (width >= 1600) return 3;
    if (width >= 1100) return 2;
    return 1;
};

const showToast = (message, isError = false) => {
    const toast = $("#lib-toast");
    const icon = toast.find("i");
    if (isError) {
        icon.removeClass("fa-circle-check text-success success-icon")
            .addClass("fa-circle-xmark text-danger");
    } else {
        icon.removeClass("fa-circle-xmark text-danger")
            .addClass("fa-circle-check text-success");
    }
    $("#toast-message").text(message);
    toast.addClass("show");
    setTimeout(() => {
        toast.removeClass("show");
    }, 12000);
};

// 1. Scoped State Variables
let previewModal = new bootstrap.Modal(document.getElementById('preview-modal'));
let submitModal = new bootstrap.Modal(document.getElementById('submit-modal'));
let selectedCategories = ["All"];
let currentPage = 1;
let rowsPerPage = typeof defaultRowsPerPage !== "undefined" ? defaultRowsPerPage : 2;

// Load pagination rows-per-page cookie
const savedRows = getCookie("lib_rows_per_page");
const val = parseInt(savedRows);
if (!isNaN(val) && val > 0) {
    rowsPerPage = val;
    $("#rows-per-page-input").val(val);
}

// 2. Event Handlers & Core Functions

// rows-per-page input handler (handles both typing and datalist selection)
$("#rows-per-page-input").on("input change", (e) => {
    const valStr = $(e.currentTarget).val();
    const newRows = parseInt(valStr);
    if (!isNaN(newRows) && newRows > 0)
        setCookie("lib_rows_per_page", newRows, 365);
    currentPage = 1;
    filterGrid();
});

// Search Input Functionality
$("#lib-search").on("input", () => {
    currentPage = 1;
    filterGrid();
});

// Pagination controls event binding
$("#pagination-controls").on("click", ".page-item:not(.disabled):not(.active)", (e) => {
    currentPage = parseInt($(e.target).closest(".page-item").attr("data-page")) || 1;
    filterGrid();
    $("html, body").animate({ scrollTop: $("#instruments-grid").offset().top - 20 }, 200);
});

// Toggle Dropdown Menu visibility
$("#category-dropdown-trigger").on("click", (e) => {
    e.stopPropagation();
    $("#category-dropdown-menu").toggleClass("d-none");
    if (!$("#category-dropdown-menu").hasClass("d-none")) {
        $("#category-search").focus().val("");
        filterDropdownOptions("");
    }
});

// Click outside closes the dropdown menu
$(document).on("click", (e) => {
    if (!$(e.target).closest(".category-select-wrapper").length)
        $("#category-dropdown-menu").addClass("d-none");
});

// Search Input Filtering Categories
$("#category-search").on("input", (e) => {
    const val = $(e.currentTarget).val().toLowerCase().trim();
    filterDropdownOptions(val);
});

const filterDropdownOptions = (query) => {
    $("#category-dropdown-menu .dropdown-item").each((idx, el) => {
        const text = $(el).text().toLowerCase();
        if (text.includes(query) || $(el).data("value") === "All")
            $(el).removeClass("d-none");
        else
            $(el).addClass("d-none");
    });
};

// Selection Handling (Multi-select)
$("#category-dropdown-menu").on("click", ".dropdown-item", (e) => {
    e.stopPropagation(); // Prevent closing dropdown on item click

    const $this = $(e.target).closest(".dropdown-item");
    const val = $this.data("value");
    const check = $this.find(".check-icon");

    if (val === "All") {
        // Clicking "All" clears all other selections
        selectedCategories = ["All"];
        $("#category-dropdown-menu .dropdown-item").removeClass("selected");
        $("#category-dropdown-menu .dropdown-item .check-icon").addClass("d-none");

        $this.addClass("selected");
        check.removeClass("d-none");
    } else {
        // If "All" was active, remove it
        const allIdx = selectedCategories.indexOf("All");
        if (allIdx > -1) {
            selectedCategories.splice(allIdx, 1);
            $("#category-dropdown-menu .dropdown-item[data-value='All']").removeClass("selected");
            $("#category-dropdown-menu .dropdown-item[data-value='All'] .check-icon").addClass("d-none");
        }

        // Toggle current selection
        const idx = selectedCategories.indexOf(val);
        if (idx > -1) {
            // Deselect
            selectedCategories.splice(idx, 1);
            $this.removeClass("selected");
            check.addClass("d-none");
        } else {
            // Select
            selectedCategories.push(val);
            $this.addClass("selected");
            check.removeClass("d-none");
        }

        // If nothing is selected, fall back to "All"
        if (selectedCategories.length === 0) {
            selectedCategories = ["All"];
            const allItem = $("#category-dropdown-menu .dropdown-item[data-value='All']");
            allItem.addClass("selected");
            allItem.find(".check-icon").removeClass("d-none");
        }
    }

    // Update trigger button label
    updateDropdownTriggerLabel();

    // Sync with hidden input
    $("#lib-category-select").val(selectedCategories.join(","));
    currentPage = 1;
    // Filter grid
    filterGrid();
});

const updateDropdownTriggerLabel = () => {
    if (selectedCategories.includes("All"))
        $("#selected-category-text").text("All Categories");
    else if (selectedCategories.length === 1)
        $("#selected-category-text").text(selectedCategories[0]);
    else if (selectedCategories.length === 2)
        $("#selected-category-text").text(selectedCategories.join(", "));
    else
        $("#selected-category-text").text(selectedCategories.length + " Categories Selected");
};

// 3. Submit Modal Drag and Drop Uploader
const dropZone = $("#file-drop-zone");
const fileInput = $("#submit-file-input");
const uploadStatus = $("#file-upload-status");
const fileInvalidMsg = $("#file-invalid-msg");

dropZone.on("click", () => {
    fileInput.click();
});

fileInput.on("change", (e) => {
    handleSelectedFiles(e.target.files);
});

// Drag events styling highlights
dropZone.on("dragover", (e) => {
    e.preventDefault();
    e.stopPropagation();
    dropZone.css("border-color", "#4f46e5").css("background-color", "#f1f5f9");
});

dropZone.on("dragleave", (e) => {
    e.preventDefault();
    e.stopPropagation();
    dropZone.css("border-color", "#cbd5e1").css("background-color", "#f8fafc");
});

dropZone.on("drop", (e) => {
    e.preventDefault();
    e.stopPropagation();
    dropZone.css("border-color", "#cbd5e1").css("background-color", "#f8fafc");

    const files = e.originalEvent.dataTransfer.files;
    handleSelectedFiles(files);
});

let attachedFile = null;

const handleSelectedFiles = (files) => {
    if (!files || files.length === 0) return;
    const file = files[0];
    if (file.name.endsWith(".zip")) {
        attachedFile = file;
        uploadStatus.text("Attached file: " + file.name)
            .removeClass("d-none text-danger").addClass("text-primary");
        fileInvalidMsg.addClass("d-none");
        dropZone.find("i").removeClass("fa-cloud-arrow-up").addClass("fa-file-zipper");
    } else {
        attachedFile = null;
        uploadStatus.text("Error: Please upload a ZIP file package.")
            .removeClass("d-none text-primary").addClass("text-danger");
        dropZone.find("i").removeClass("fa-file-zipper").addClass("fa-cloud-arrow-up");
    }
};

// Dynamically update email placeholder on modal show using active REDCap domain
$("#submit-modal").on("show.bs.modal", () => {
    $("#submit-author-email").attr("placeholder", "jane.doe@" + contactEmailDomain);
});

// Clear all validation styling from the form
const clearValidationState = () => {
    const form = $("#submit-instrument-form");
    form.removeClass("was-validated");
    form.find(".is-invalid").removeClass("is-invalid");
    form.find(".is-valid").removeClass("is-valid");

    // Clear categories list validation styles
    $(".submit-cat-checkbox").closest(".p-3").removeClass("border-danger");
    $("#categories-invalid-msg").addClass("d-none");

    // Clear dropzone invalid styles
    $("#file-drop-zone").removeClass("border-danger").css("border-color", "");
    $("#file-invalid-msg").addClass("d-none");
};

// Method selection tab checking
let activeTab = "zip"; // "zip" or "project"

$('button[data-bs-toggle="tab"]').on('shown.bs.tab', (e) => {
    activeTab = e.target.id === "project-tab" ? "project" : "zip";

    // Clear any previous validations when switching tabs
    clearValidationState();

    if (activeTab === "project")
        $("#submit-project-instrument").prop("disabled", false);
    else
        $("#submit-project-instrument").prop("disabled", true);
});

// Submission Type selection logic (New vs Update)
$('input[name="submission-type"]').on("change", (e) => {
    const $this = $(e.currentTarget);
    const val = $this.val();
    const wrapper = $("#existing-instrument-wrapper");
    const selectEl = $("#submit-existing-instrument");
    const titleWrapper = $("#submit-title-wrapper");
    const titleInput = $("#submit-title");
    const versionInput = $("#submit-version");
    const versionInfo = $("#version-current-info");
    const versionInvalid = $("#version-invalid-feedback");

    // Clear validation when switching type
    clearValidationState();

    if (val === "update") {
        wrapper.show();
        selectEl.prop("required", true).prop("disabled", false);
        titleWrapper.hide();
        titleInput.prop("required", false).removeClass("is-invalid").val("");
        // Reset version validations
        versionInput.removeClass("is-invalid");
        versionInvalid.text("Please specify a version.");
        triggerVersionCheck();
        return;
    }

    wrapper.hide();
    selectEl.prop("required", false).prop("disabled", true).removeClass("is-invalid").val("");
    titleWrapper.show();
    titleInput.prop("required", true);
    // Reset version inputs
    versionInfo.addClass("d-none").text("");
    versionInput.removeClass("is-invalid");
    versionInvalid.text("Please specify a version.");

    // Clear fields since we switched back to New
    $(".submit-cat-checkbox").prop("checked", false);
    $("#submit-author-name").val("");
    $("#submit-author-email").val("");
    $("#submit-description").val("");
    $("#submit-version").val("1.0.0");

    // Clear any invalid states on categories
    $("#categories-invalid-msg").addClass("d-none").removeClass("d-block");
    $(".submit-cat-checkbox").closest(".p-3").removeClass("border-danger");
});

// Check version dynamically and load settings when dropdown selection changes
$("#submit-existing-instrument").on("change", (e) => {
    // Clear validation when a new instrument is selected
    clearValidationState();

    triggerVersionCheck();

    const selectedVal = $(e.currentTarget).val();
    const data = instrumentFields[selectedVal];

    // Clear all categories first
    $(".submit-cat-checkbox").prop("checked", false);

    if (!data) {
        // Clear fields if placeholder is selected
        $("#submit-author-name").val("");
        $("#submit-author-email").val("");
        $("#submit-description").val("");
        $("#submit-version").val("1.0.0");

        // Clear any invalid states on categories
        $("#categories-invalid-msg").addClass("d-none").removeClass("d-block");
        $(".submit-cat-checkbox").closest(".p-3").removeClass("border-danger");
        return;
    }

    // Pre-populate categories
    if (data.categories)
        data.categories.forEach((catName) => $(".submit-cat-checkbox[value='" + catName + "']").prop("checked", true));

    // Pre-populate author details
    $("#submit-author-name").val(data.author_name || "");
    $("#submit-author-email").val(data.author_email || "");

    // Pre-populate description
    $("#submit-description").val(data.description || "");

    // Pre-populate version (current version without leading 'v')
    if (data.version) {
        const cleanVer = data.version.replace(/^v/i, '');
        $("#submit-version").val(cleanVer);
    }

    // Clear any invalid states on categories
    $("#categories-invalid-msg").addClass("d-none").removeClass("d-block");
    $(".submit-cat-checkbox").closest(".p-3").removeClass("border-danger");
});

const triggerVersionCheck = (runValidation = false) => {
    const selectEl = $("#submit-existing-instrument");
    const versionInfo = $("#version-current-info");
    const selectedOpt = selectEl.find("option:selected");
    const currentVer = selectedOpt.attr("data-version");

    if (currentVer)
        versionInfo.removeClass("d-none").html('<i class="fa-solid fa-info-circle me-1"></i>Current live version: <strong>' + currentVer + '</strong>');
    else
        versionInfo.addClass("d-none").text("");

    if (runValidation || $("#submit-instrument-form").hasClass("was-validated"))
        validateVersionInput();
    else
        $("#submit-version").removeClass("is-invalid");
};

$("#submit-version").on("input", () => {
    validateVersionInput();
});

const validateVersionInput = () => {
    const subType = $('input[name="submission-type"]:checked').val();
    const versionInput = $("#submit-version");
    const versionInvalid = $("#version-invalid-feedback");
    const selectEl = $("#submit-existing-instrument");
    const selectedOpt = selectEl.find("option:selected");
    const currentVer = selectedOpt.attr("data-version");

    const enteredVer = versionInput.val().trim();
    if (!enteredVer) {
        versionInput.addClass("is-invalid");
        versionInvalid.text("Please specify a version.");
        return false;
    }

    const versionRegex = /^\d+(\.\d+)*$/;
    if (!versionRegex.test(enteredVer)) {
        versionInput.addClass("is-invalid");
        versionInvalid.text("Please enter a valid numeric version (1.2.0).");
        return false;
    }

    if (subType === "update" && currentVer && compareSemanticVersions(enteredVer, currentVer) <= 0) {
        versionInput.addClass("is-invalid");
        versionInvalid.html("Must be higher than the current version <strong>" + currentVer + "</strong>.");
        return false;
    }
    versionInput.removeClass("is-invalid");
    return true;
};

// Clear category list error on checkbox toggle
$(".submit-cat-checkbox").on("change", () => {
    if ($(".submit-cat-checkbox:checked").length > 0) {
        $("#categories-invalid-msg").addClass("d-none").removeClass("d-block");
        $(".submit-cat-checkbox").closest(".p-3").removeClass("border-danger");
    }
});

// Clear validation when a new project instrument is selected
$("#submit-project-instrument").on("change", () => {
    clearValidationState();
});

// Form Submission handler
$("#submit-instrument-form").on("submit", (e) => {
    e.preventDefault();

    const form = e.currentTarget;
    const titleInput = $("#submit-title");
    const authorNameInput = $("#submit-author-name");
    const authorEmailInput = $("#submit-author-email");
    const descInput = $("#submit-description");
    const projectInstrumentSelect = $("#submit-project-instrument");
    const existingInstrumentSelect = $("#submit-existing-instrument");
    const subType = $('input[name="submission-type"]:checked').val();

    // Standard HTML5 validation check
    let isFormValid = form.checkValidity();

    // Highlight validation styles
    $(form).addClass("was-validated");

    // Category Checklist validation
    let isCategoryChecked = $(".submit-cat-checkbox:checked").length > 0;
    const categoriesInvalidMsg = $("#categories-invalid-msg");
    if (!isCategoryChecked) {
        categoriesInvalidMsg.removeClass("d-none").addClass("d-block");
        $(".submit-cat-checkbox").closest(".p-3").addClass("border-danger");
    } else {
        categoriesInvalidMsg.addClass("d-none").removeClass("d-block");
        $(".submit-cat-checkbox").closest(".p-3").removeClass("border-danger");
    }

    // Tab-specific validation
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
    } else if (activeTab === "project") {
        projectInstrumentSelect.removeClass("is-invalid");
    }

    // Existing instrument selection validation if type is update
    let isExistingValid = true;
    if (subType === "update" && !existingInstrumentSelect.val()) {
        isExistingValid = false;
        existingInstrumentSelect.addClass("is-invalid");
    } else if (subType === "update") {
        existingInstrumentSelect.removeClass("is-invalid");
    }

    // Version semantic validation (enforced for both types)
    let isVersionValid = validateVersionInput();

    if (!isFormValid || !isCategoryChecked || !isTabValid || !isExistingValid || !isVersionValid)
        return;

    // Trigger submitting loader state
    const submitBtn = $("#submit-form-btn");
    submitBtn.prop("disabled", true)
        .html('<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Submitting...');

    // Disable all fields
    $(form).find("input, select, textarea, button").prop("disabled", true);

    // Get title text
    const titleText = subType === "update" ?
        $("#submit-existing-instrument option:selected").text().replace(/\s*\(v.*\)/i, '') :
        titleInput.val();

    // Extract selected categories
    const categories = [];
    $(".submit-cat-checkbox:checked").each((idx, el) => {
        categories.push($(el).val());
    });

    const payload = {
        subType: subType,
        type: activeTab,
        title: titleText,
        description: descInput.val(),
        author_name: authorNameInput.val(),
        author_email: authorEmailInput.val(),
        version: $("#submit-version").val(),
        categories: categories,
        existingId: existingInstrumentSelect.val()
    };

    const performSubmit = (payloadData) => {
        module.ajax('submit', payloadData).then((response) => {
            // Re-enable form fields
            $(form).find("input, select, textarea, button").prop("disabled", false);
            submitBtn.html('<i class="fa-solid fa-paper-plane"></i><span>Submit Request</span>');

            let data;
            try {
                data = typeof response === "string" ? JSON.parse(response) : response;
            } catch (e) {
                data = response;
            }

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

            // Reset validation styles
            $(".submit-cat-checkbox").closest(".p-3").removeClass("border-danger");
            categoriesInvalidMsg.addClass("d-none");
            dropZone.removeClass("border-danger").css("border-color", "");
            fileInvalidMsg.addClass("d-none");

            projectInstrumentSelect.prop("disabled", true).removeClass("is-invalid");
            $("#existing-instrument-wrapper").hide();
            $("#submit-title-wrapper").show();
            titleInput.prop("required", true);
            existingInstrumentSelect.prop("required", false).prop("disabled", true).removeClass("is-invalid");
            $("#version-current-info").addClass("d-none").text("");

            // Select first tab
            const zipTabTrigger = document.querySelector('#zip-tab');
            if (zipTabTrigger) {
                const tab = new bootstrap.Tab(zipTabTrigger);
                tab.show();
            }

            // Reload page to reflect new uploaded instrument
            showToast(data.message || "Submission successful! Reloading...");
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        }).catch((error) => {
            $(form).find("input, select, textarea, button").prop("disabled", false);
            submitBtn.html('<i class="fa-solid fa-paper-plane"></i><span>Submit Request</span>');
            alert("AJAX request failed: " + error);
        });
    };

    if (activeTab === "zip") {
        const reader = new FileReader();
        reader.onload = (event) => {
            const base64Data = event.target.result.split(',')[1];
            payload.fileData = base64Data;
            payload.fileName = attachedFile.name;
            performSubmit(payload);
        };
        reader.readAsDataURL(attachedFile);
    } else {
        payload.instrumentName = projectInstrumentSelect.val();
        performSubmit(payload);
    }
});

// Primary filter function combining search input + category dropdown selection
const filterGrid = () => {
    const query = $("#lib-search").val().toLowerCase().trim();
    const matchingCards = [];

    $(".instrument-card-col").each((idx, el) => {
        const cardTitle = $(el).data("title").toLowerCase();
        const cardDesc = $(el).data("desc").toLowerCase();
        const cardCats = ($(el).data("categories") || "").split(",");

        const matchesQuery = query === "" || cardTitle.includes(query) || cardDesc.includes(query);
        const matchesCategory = selectedCategories.includes("All") || cardCats.some(cat => selectedCategories.includes(cat));

        if (matchesQuery && matchesCategory)
            matchingCards.push($(el));
        else
            $(el).hide();
    });

    const totalItems = matchingCards.length;

    if (totalItems === 0) {
        $("#instruments-grid").hide();
        $("#lib-pagination-container").hide();
        $("#lib-empty").fadeIn(200);
        return;
    }

    $("#lib-empty").hide();
    $("#instruments-grid").show();

    // Dynamically retrieve and validate rows limit
    let rows = parseInt($("#rows-per-page-input").val());
    if (isNaN(rows) || rows <= 0)
        rows = typeof defaultRowsPerPage !== "undefined" ? defaultRowsPerPage : 2;
    rowsPerPage = rows;

    const cardsPerRow = getCardsPerRow();
    const limit = rows * cardsPerRow;

    const totalPages = Math.ceil(totalItems / limit);
    if (currentPage > totalPages)
        currentPage = Math.max(1, totalPages);

    const startIndex = (currentPage - 1) * limit;
    const endIndex = Math.min(startIndex + limit, totalItems);

    matchingCards.forEach(($card, index) => {
        if (index >= startIndex && index < endIndex)
            $card.show();
        else
            $card.hide();
    });

    renderPagination(totalItems, totalPages, startIndex, endIndex);
};

const renderPagination = (totalItems, totalPages, startIndex, endIndex) => {
    $("#lib-pagination-container").show();
    $("#pagination-info").text(`Showing ${startIndex + 1}-${endIndex} of ${totalItems} instruments`);

    const controls = $("#pagination-controls");
    controls.empty();

    if (totalPages <= 1) {
        controls.hide();
        return;
    }
    controls.show();

    const prevClass = currentPage === 1 ? "disabled" : "";
    controls.append(`<li class="page-item ${prevClass}" data-page="${currentPage - 1}"><span class="page-link">&laquo; Prev</span></li>`);

    for (let p = 1; p <= totalPages; p++) {
        const activeClass = p === currentPage ? "active" : "";
        controls.append(`<li class="page-item ${activeClass}" data-page="${p}"><span class="page-link">${p}</span></li>`);
    }

    const nextClass = currentPage === totalPages ? "disabled" : "";
    controls.append(`<li class="page-item ${nextClass}" data-page="${currentPage + 1}"><span class="page-link">Next &raquo;</span></li>`);
};

// Preview Modal Control
const openPreview = (instId) => {
    const data = instrumentFields[instId];
    if (!data) return;
    // Set text contents
    $("#modal-title").text(data.title);

    // Populate modal categories list
    const modalCats = $("#modal-categories");
    modalCats.empty();
    data.categories.forEach((cat) => {
        const catName = (typeof cat === 'object' && cat !== null) ? cat.name : cat;
        const catClass = 'category-' + String(catName).toLowerCase().replace(/\s+/g, '');
        modalCats.append('<span class="badge ' + catClass + ' px-2.5 py-1.5 text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.05em;">' + catName + '</span>');
    });

    $("#modal-version").text(data.version);
    $("#modal-author").text(data.author_name);
    $("#modal-author-email").attr("href", "mailto:" + data.author_email);
    $("#modal-desc").text(data.description);
    $("#modal-fields-heading").text("Instrument Fields (" + data.fields.length + ")");

    // Populate Fields List
    const list = $("#modal-field-list");
    list.empty();

    data.fields.forEach((field) => {
        list.append(renderFieldListItem(field));
    });

    // Configure Modal Install Button state (only for authenticated project views)
    if (!isPublicView) {
        const modalInstallBtn = $("#modal-install-btn");
        const modalRemoveBtn = $("#modal-remove-btn");

        // Check if currently installed
        const cardInstallBtn = $(".instrument-card-col[data-id='" + instId + "'] .btn-install");
        if (cardInstallBtn.hasClass("is-installed")) {
            modalInstallBtn.addClass("is-installed")
                .prop("disabled", true)
                .html('<i class="fa-solid fa-check me-2"></i>Installed');

            modalRemoveBtn.removeClass("d-none")
                .off("click")
                .on("click", () => {
                    previewModal.hide();
                    removeInstrument(instId);
                });
        } else {
            modalInstallBtn.removeClass("is-installed")
                .prop("disabled", false)
                .html('<i class="fa-solid fa-download me-2"></i>Install to Project')
                .off("click")
                .on("click", () => {
                    previewModal.hide();
                    // Locate card button and trigger installation
                    const btn = $(".instrument-card-col[data-id='" + instId + "'] .btn-install")[0];
                    installInstrument(btn, instId);
                });

            modalRemoveBtn.addClass("d-none");
        }
    }

    // Configure Modal Download Button click handler
    const modalDownloadBtn = $("#modal-download-btn");
    modalDownloadBtn.off("click").on("click", (e) => {
        e.preventDefault();

        // Get doc_id from latest version
        let docId = null;
        if (data.versions && data.versions.length > 0) {
            const latestVer = data.versions[data.versions.length - 1];
            if (latestVer && latestVer.doc_id)
                docId = latestVer.doc_id;
        }

        // If it is a real numeric doc_id, stream the file from download.php
        if (docId && /^\d+$/.test(docId)) {
            window.location.href = module.getUrl('download.php') + '&doc_id=' + docId;
            showToast("Starting download for '" + data.title + "'...");
        } else {
            // Otherwise, fall back to generating a mock zip file
            const mockZipContent = "PK\x03\x04\n\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x0e\x00\t\x00metadata.json" + JSON.stringify(data, null, 2);
            const blob = new Blob([mockZipContent], { type: "application/zip" });
            const url = URL.createObjectURL(blob);

            const a = document.createElement("a");
            a.href = url;
            a.download = data.id + "_instrument.zip";
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);

            showToast("Successfully downloaded ZIP package for '" + data.title + "'!");
        }
    });

    // Show Bootstrap Modal
    previewModal.show();
};

const closePreview = () => {
    previewModal.hide();
};

// Installation Flow
const installInstrument = (buttonEl, instId) => {
    const btn = $(buttonEl);
    if (btn.hasClass("is-installed") || btn.prop("disabled")) return;

    // Transition to loading state
    btn.prop("disabled", true)
        .html('<i class="fa-solid fa-spinner fa-spin me-2"></i>Installing...');

    // Make AJAX call to save install status in project setting using module.ajax
    module.ajax('install', { id: instId })
        .then((response) => {
            if (!response || response.status !== "success") {
                btn.prop("disabled", false).html('<i class="fa-solid fa-download me-2"></i>Install to Project');
                const errMsg = (response && response.message) ? response.message : "Failed to install instrument. Please try again.";
                showToast(errMsg, true);
                return;
            }

            const data = instrumentFields[instId];
            if (data)
                data.installed = true;

            // Add visual indicators to the card
            const card = $(".instrument-card-col[data-id='" + instId + "'] .instrument-card");
            card.addClass("installed");



            // Finalize button state on card
            btn.addClass("is-installed")
                .removeClass("btn-primary")
                .addClass("btn-success")
                .html('<i class="fa-solid fa-check me-2"></i>Installed')
                .prop("disabled", true);
            installedCount++;
            $("#stat-installed").text(installedCount + " Installed in Project");

            // Trigger Success Toast
            showToast("'" + (data ? data.title : "Instrument") + "' successfully installed to this project!");
        })
        .catch((xhr) => {
            btn.prop("disabled", false).html('<i class="fa-solid fa-download me-2"></i>Install to Project');
            let errMsg = "Failed to install instrument due to network error.";
            if (xhr && xhr.responseJSON && xhr.responseJSON.message)
                errMsg = xhr.responseJSON.message;
            showToast(errMsg, true);
        });
};

// Removal Flow (Uninstall)
const removeInstrument = (instId) => {
    const cardInstallBtn = $(".instrument-card-col[data-id='" + instId + "'] .btn-install");
    const btn = $(cardInstallBtn[0]);
    if (!btn.hasClass("is-installed")) return;

    btn.prop("disabled", true)
        .html('<i class="fa-solid fa-spinner fa-spin me-2"></i>Removing...');

    module.ajax('uninstall', { id: instId })
        .then((response) => {
            if (!response || response.status !== "success") {
                btn.prop("disabled", true).html('<i class="fa-solid fa-check me-2"></i>Installed');
                const errMsg = (response && response.message) ? response.message : "Failed to remove instrument. Please try again.";
                showToast(errMsg, true);
                return;
            }

            const data = instrumentFields[instId];
            if (data)
                data.installed = false;

            // Remove visual indicators from the card
            const card = $(".instrument-card-col[data-id='" + instId + "'] .instrument-card");
            card.removeClass("installed");



            // Restore install button state on card
            btn.removeClass("is-installed")
                .removeClass("btn-success")
                .addClass("btn-primary")
                .html('<i class="fa-solid fa-download me-2"></i>Install')
                .prop("disabled", false);

            if (installedCount > 0)
                installedCount--;
            $("#stat-installed").text(installedCount + " Installed");

            // Trigger Success Toast
            showToast("'" + (data ? data.title : "Instrument") + "' successfully removed from this project!");
        })
        .catch((xhr) => {
            btn.prop("disabled", true).html('<i class="fa-solid fa-check me-2"></i>Installed');
            let errMsg = "Failed to remove instrument due to network error.";
            if (xhr && xhr.responseJSON && xhr.responseJSON.message)
                errMsg = xhr.responseJSON.message;
            showToast(errMsg, true);
        });
};

// Expose necessary functions globally for HTML inline onclick event handlers
window.openPreview = openPreview;
window.closePreview = closePreview;
window.installInstrument = installInstrument;

// Listen to window resize events to recalculate pages when grid columns change
let lastCardsPerRow = getCardsPerRow();
$(window).on("resize", () => {
    const current = getCardsPerRow();
    if (current !== lastCardsPerRow) {
        lastCardsPerRow = current;
        filterGrid();
    }
});

// Run filterGrid on initial page load to apply pagination
filterGrid();
