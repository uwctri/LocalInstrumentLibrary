// Modal References
let previewModal;
let submitModal;

// Active Category selections (Multi-select)
let selectedCategories = ["All"];

$(document).ready(function () {
    // Instantiate the Bootstrap 5 Modals
    previewModal = new bootstrap.Modal(document.getElementById('preview-modal'));
    submitModal = new bootstrap.Modal(document.getElementById('submit-modal'));

    // 1. Search Functionality
    $("#lib-search").on("input", function () {
        filterGrid();
    });

    // 2. Custom Category Filter Dropdown Functionality

    // Toggle Dropdown Menu visibility
    $("#category-dropdown-trigger").on("click", function (e) {
        e.stopPropagation();
        $("#category-dropdown-menu").toggleClass("d-none");
        if (!$("#category-dropdown-menu").hasClass("d-none")) {
            $("#category-search").focus().val("");
            filterDropdownOptions("");
        }
    });

    // Click outside closes the dropdown menu
    $(document).on("click", function (e) {
        if (!$(e.target).closest(".category-select-wrapper").length) {
            $("#category-dropdown-menu").addClass("d-none");
        }
    });

    // Search Input Filtering Categories
    $("#category-search").on("input", function () {
        const val = $(this).val().toLowerCase().trim();
        filterDropdownOptions(val);
    });

    function filterDropdownOptions(query) {
        $("#category-dropdown-menu .dropdown-item").each(function () {
            const text = $(this).text().toLowerCase();
            if (text.includes(query) || $(this).data("value") === "All") {
                $(this).removeClass("d-none");
            } else {
                $(this).addClass("d-none");
            }
        });
    }
    // Selection Handling (Multi-select)
    $("#category-dropdown-menu").on("click", ".dropdown-item", function (e) {
        e.stopPropagation(); // Prevent closing dropdown on item click

        const val = $(this).data("value");
        const check = $(this).find(".check-icon");

        if (val === "All") {
            // Clicking "All" clears all other selections
            selectedCategories = ["All"];
            $("#category-dropdown-menu .dropdown-item").removeClass("selected");
            $("#category-dropdown-menu .dropdown-item .check-icon").addClass("d-none");

            $(this).addClass("selected");
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
                $(this).removeClass("selected");
                check.addClass("d-none");
            } else {
                // Select
                selectedCategories.push(val);
                $(this).addClass("selected");
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
        // Filter grid
        filterGrid();
    });

    // 3. Submit Modal Drag and Drop Uploader Mockup
    const dropZone = $("#file-drop-zone");
    const fileInput = $("#submit-file-input");
    const uploadStatus = $("#file-upload-status");
    const fileInvalidMsg = $("#file-invalid-msg");

    dropZone.on("click", function () {
        fileInput.click();
    });

    fileInput.on("change", function (e) {
        handleSelectedFiles(e.target.files);
    });

    // Drag events styling highlights
    dropZone.on("dragover", function (e) {
        e.preventDefault();
        e.stopPropagation();
        dropZone.css("border-color", "#4f46e5").css("background-color", "#f1f5f9");
    });

    dropZone.on("dragleave", function (e) {
        e.preventDefault();
        e.stopPropagation();
        dropZone.css("border-color", "#cbd5e1").css("background-color", "#f8fafc");
    });

    dropZone.on("drop", function (e) {
        e.preventDefault();
        e.stopPropagation();
        dropZone.css("border-color", "#cbd5e1").css("background-color", "#f8fafc");

        const files = e.originalEvent.dataTransfer.files;
        handleSelectedFiles(files);
    });

    let attachedFile = null;

    function handleSelectedFiles(files) {
        if (files && files.length > 0) {
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
        }
    }

    // Dynamic REDCap Administrator Contact Domain extraction
    function getREDCapAdminDomain() {
        try {
            let contactLink = $("#west a:contains('Contact'), #west a:contains('contact')").first().attr("href");
            if (!contactLink) {
                contactLink = $("a[href^='mailto:']").first().attr("href");
            }
            if (contactLink && contactLink.indexOf('@') !== -1) {
                let emailPart = contactLink.split(':')[1] || '';
                emailPart = emailPart.split('?')[0]; // remove query parameters
                let domain = emailPart.split('@')[1];
                if (domain) {
                    return domain.trim().toLowerCase();
                }
            }
        } catch (e) {
            console.error("Error retrieving REDCap admin domain:", e);
        }
        return "wisconsin.edu"; // Safe fallback domain
    }

    // Dynamically update email placeholder on modal show using active REDCap domain
    $("#submit-modal").on("show.bs.modal", function () {
        const domain = getREDCapAdminDomain();
        $("#submit-author-email").attr("placeholder", "jane.doe@" + domain);
    });

    // Clear all validation styling from the form
    function clearValidationState() {
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
    }

    // Method selection tab checking
    let activeTab = "zip"; // "zip" or "project"

    $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
        activeTab = e.target.id === "project-tab" ? "project" : "zip";

        // Clear any previous validations when switching tabs
        clearValidationState();

        if (activeTab === "project") {
            $("#submit-project-instrument").prop("disabled", false);
        } else {
            $("#submit-project-instrument").prop("disabled", true);
        }
    });

    // Submission Type selection logic (New vs Update)
    $('input[name="submission-type"]').on("change", function () {
        const val = $(this).val();
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
        } else {
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
        }
    });

    // Check version dynamically and load settings when dropdown selection changes
    $("#submit-existing-instrument").on("change", function () {
        // Clear validation when a new instrument is selected
        clearValidationState();

        triggerVersionCheck();

        const selectedVal = $(this).val();
        const data = instrumentFields[selectedVal];

        // Clear all categories first
        $(".submit-cat-checkbox").prop("checked", false);

        if (data) {
            // Pre-populate categories
            if (data.categories) {
                data.categories.forEach(function (catName) {
                    $(".submit-cat-checkbox[value='" + catName + "']").prop("checked", true);
                });
            }

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
        } else {
            // Clear fields if placeholder is selected
            $("#submit-author-name").val("");
            $("#submit-author-email").val("");
            $("#submit-description").val("");
            $("#submit-version").val("1.0.0");
        }

        // Clear any invalid states on categories
        $("#categories-invalid-msg").addClass("d-none").removeClass("d-block");
        $(".submit-cat-checkbox").closest(".p-3").removeClass("border-danger");
    });

    function triggerVersionCheck(runValidation = false) {
        const selectEl = $("#submit-existing-instrument");
        const versionInfo = $("#version-current-info");
        const selectedOpt = selectEl.find("option:selected");
        const currentVer = selectedOpt.attr("data-version");

        if (currentVer) {
            versionInfo.removeClass("d-none").html('<i class="fa-solid fa-info-circle me-1"></i>Current live version: <strong>' + currentVer + '</strong>');
        } else {
            versionInfo.addClass("d-none").text("");
        }

        if (runValidation || $("#submit-instrument-form").hasClass("was-validated")) {
            validateVersionInput();
        } else {
            $("#submit-version").removeClass("is-invalid");
        }
    }

    $("#submit-version").on("input", function () {
        validateVersionInput();
    });

    function validateVersionInput() {
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

        if (subType === "update" && currentVer) {
            if (compareSemanticVersions(enteredVer, currentVer) <= 0) {
                versionInput.addClass("is-invalid");
                versionInvalid.html("Must be higher than the current version <strong>" + currentVer + "</strong>.");
                return false;
            }
        }
        versionInput.removeClass("is-invalid");
        return true;
    }

    // Clear category list error on checkbox toggle
    $(".submit-cat-checkbox").on("change", function () {
        if ($(".submit-cat-checkbox:checked").length > 0) {
            $("#categories-invalid-msg").addClass("d-none").removeClass("d-block");
            $(".submit-cat-checkbox").closest(".p-3").removeClass("border-danger");
        }
    });

    // Clear validation when a new project instrument is selected
    $("#submit-project-instrument").on("change", function () {
        clearValidationState();
    });

    // Form Submission handler
    $("#submit-instrument-form").on("submit", function (e) {
        e.preventDefault();

        const form = this;
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
        if (activeTab === "zip") {
            if (!attachedFile) {
                isTabValid = false;
                fileInvalidMsg.removeClass("d-none");
                dropZone.addClass("border-danger").css("border-color", "#dc3545");
            } else {
                fileInvalidMsg.addClass("d-none");
                dropZone.removeClass("border-danger").css("border-color", "");
            }
        } else if (activeTab === "project") {
            if (!projectInstrumentSelect.val()) {
                isTabValid = false;
                projectInstrumentSelect.addClass("is-invalid");
            } else {
                projectInstrumentSelect.removeClass("is-invalid");
            }
        }

        // Existing instrument selection validation if type is update
        let isExistingValid = true;
        if (subType === "update") {
            if (!existingInstrumentSelect.val()) {
                isExistingValid = false;
                existingInstrumentSelect.addClass("is-invalid");
            } else {
                existingInstrumentSelect.removeClass("is-invalid");
            }
        }

        // Version semantic validation (enforced for both types)
        let isVersionValid = validateVersionInput();

        if (!isFormValid || !isCategoryChecked || !isTabValid || !isExistingValid || !isVersionValid) {
            return;
        }

        // Trigger simulated submitting loader state
        const submitBtn = $("#submit-form-btn");
        submitBtn.prop("disabled", true)
            .html('<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Submitting...');

        // Disable all fields
        $(form).find("input, select, textarea, button").prop("disabled", true);

        setTimeout(function () {
            // Get title text for toast (cleaning current version wrapper if update)
            const titleText = subType === "update" ?
                $("#submit-existing-instrument option:selected").text().replace(/\s*\(v.*\)/i, '') :
                titleInput.val();

            // Hide Modal
            submitModal.hide();

            // Re-enable form fields for future submissions
            $(form).find("input, select, textarea, button").prop("disabled", false);
            submitBtn.html('<i class="fa-solid fa-paper-plane"></i><span>Submit Request</span>');
            $(form).removeClass("was-validated");

            // Reset fields
            form.reset();
            attachedFile = null;
            uploadStatus.addClass("d-none");
            dropZone.find("i").removeClass("fa-file-zipper").addClass("fa-cloud-arrow-up");

            // Reset categories validation styles
            $(".submit-cat-checkbox").closest(".p-3").removeClass("border-danger");
            categoriesInvalidMsg.addClass("d-none");

            // Reset dropzone red invalid validation styles
            dropZone.removeClass("border-danger").css("border-color", "");
            fileInvalidMsg.addClass("d-none");

            projectInstrumentSelect.prop("disabled", true).removeClass("is-invalid");

            // Reset Existing Instrument dropdown slide & title input state
            $("#existing-instrument-wrapper").hide();
            $("#submit-title-wrapper").show();
            titleInput.prop("required", true);
            existingInstrumentSelect.prop("required", false).prop("disabled", true).removeClass("is-invalid");
            $("#version-current-info").addClass("d-none").text("");

            // Select first tab by default
            const zipTabTrigger = document.querySelector('#zip-tab');
            if (zipTabTrigger) {
                const tab = new bootstrap.Tab(zipTabTrigger);
                tab.show();
            }

            // Trigger Toast success confirmation
            const msg = subType === "update" ?
                "Thank you! Your update request for instrument '" + titleText + "' has been submitted." :
                "Thank you! Your instrument '" + titleText + "' has been submitted for approval.";
            showToast(msg);
        }, 1800);
    });
});

function updateDropdownTriggerLabel() {
    if (selectedCategories.includes("All")) {
        $("#selected-category-text").text("All Categories");
    } else if (selectedCategories.length === 1) {
        $("#selected-category-text").text(selectedCategories[0]);
    } else if (selectedCategories.length === 2) {
        $("#selected-category-text").text(selectedCategories.join(", "));
    } else {
        $("#selected-category-text").text(selectedCategories.length + " Categories Selected");
    }
}

// Primary filter function combining search input + category dropdown selection
function filterGrid() {
    const query = $("#lib-search").val().toLowerCase().trim();
    let visibleCount = 0;

    $(".instrument-card-col").each(function () {
        const cardTitle = $(this).data("title").toLowerCase();
        const cardDesc = $(this).data("desc").toLowerCase();

        // Get comma-separated list of categories from data-categories attribute
        const cardCats = ($(this).data("categories") || "").split(",");

        const matchesQuery = query === "" || cardTitle.includes(query) || cardDesc.includes(query);

        // Check if selectedCategories contains "All" OR intersects with cardCats
        const matchesCategory = selectedCategories.includes("All") || cardCats.some(cat => selectedCategories.includes(cat));

        if (matchesQuery && matchesCategory) {
            $(this).fadeIn(200);
            visibleCount++;
        } else {
            $(this).fadeOut(200);
        }
    });

    // Show empty state if no instruments match filters
    if (visibleCount === 0) {
        $("#instruments-grid").hide();
        $("#lib-empty").fadeIn(200);
    } else {
        $("#lib-empty").hide();
        $("#instruments-grid").show();
    }
}

// Preview Modal Control
function openPreview(instId) {
    const data = instrumentFields[instId];
    if (!data) return;
    // Set text contents
    $("#modal-title").text(data.title);

    // Populate modal categories list
    const modalCats = $("#modal-categories");
    modalCats.empty();
    data.categories.forEach(function (cat) {
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

    data.fields.forEach(function (field) {
        const li = $('<li class="list-group-item d-flex justify-content-between align-items-center py-3 px-4 border-bottom"></li>');
        li.append('<span class="fw-semibold text-dark text-truncate" title="' + field.label + '" style="max-width: 60%;">' + field.label + '</span>');

        const info = $('<div class="d-flex align-items-center gap-2"></div>');
        info.append('<span class="font-monospace text-primary" style="font-size: 0.78rem;">[' + field.variable + ']</span>');

        // Format class based on field type for color-coding
        const cleanType = field.type.replace('_', '').replace(' ', '').toLowerCase();
        const typeClass = 'type-' + cleanType;
        info.append('<span class="badge border ' + typeClass + ' text-uppercase" style="font-size: 0.68rem; padding: 4px 8px;">' + field.type + '</span>');
        li.append(info);

        list.append(li);
    });

    // Configure Modal Install Button state
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
            .on("click", function () {
                previewModal.hide();
                removeInstrument(instId);
            });
    } else {
        modalInstallBtn.removeClass("is-installed")
            .prop("disabled", false)
            .html('<i class="fa-solid fa-download me-2"></i>Install to Project')
            .off("click")
            .on("click", function () {
                previewModal.hide();
                // Locate card button and trigger installation
                const btn = $(".instrument-card-col[data-id='" + instId + "'] .btn-install")[0];
                installInstrument(btn, instId);
            });

        modalRemoveBtn.addClass("d-none");
    }

    // Configure Modal Download Button click handler
    const modalDownloadBtn = $("#modal-download-btn");
    modalDownloadBtn.off("click").on("click", function (e) {
        e.preventDefault();

        // Create a mock blob containing fake zip binary data representing instrument metadata
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
    });

    // Show Bootstrap Modal
    previewModal.show();
}

// Close preview modal helper
function closePreview() {
    previewModal.hide();
}

// Installation Flow
function installInstrument(buttonEl, instId) {
    const btn = $(buttonEl);
    if (btn.hasClass("is-installed") || btn.prop("disabled")) return;

    // Transition to loading state
    btn.prop("disabled", true)
        .html('<i class="fa-solid fa-spinner fa-spin me-2"></i>Installing...');

    // Make AJAX call to save install status in project setting using module.ajax
    module.ajax('install', { id: instId })
        .then(function (response) {
            if (response && response.status === "success") {
                const data = instrumentFields[instId];
                if (data) {
                    data.installed = true;
                }

                // Add visual indicators to the card
                const card = $(".instrument-card-col[data-id='" + instId + "'] .instrument-card");
                card.addClass("installed");

                // Add Installed Badge if it doesn't exist
                const cardTop = $(".instrument-card-col[data-id='" + instId + "'] .card-top");
                if (cardTop.find(".bg-success").length === 0) {
                    cardTop.find(".d-flex").prepend('<span class="badge bg-success text-white px-2 py-1 fw-bold rounded-pill" style="font-size: 0.7rem;"><i class="fa-solid fa-circle-check me-1"></i>Installed</span>');
                }

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
            } else {
                btn.prop("disabled", false).html('<i class="fa-solid fa-download me-2"></i>Install to Project');
                const errMsg = (response && response.message) ? response.message : "Failed to install instrument. Please try again.";
                showToast(errMsg, true);
            }
        })
        .catch(function (xhr) {
            btn.prop("disabled", false).html('<i class="fa-solid fa-download me-2"></i>Install to Project');
            let errMsg = "Failed to install instrument due to network error.";
            if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                errMsg = xhr.responseJSON.message;
            }
            showToast(errMsg, true);
        });
}

// Removal Flow (Uninstall)
function removeInstrument(instId) {
    const cardInstallBtn = $(".instrument-card-col[data-id='" + instId + "'] .btn-install");
    const btn = $(cardInstallBtn[0]);
    if (!btn.hasClass("is-installed")) return;

    btn.prop("disabled", true)
        .html('<i class="fa-solid fa-spinner fa-spin me-2"></i>Removing...');

    module.ajax('uninstall', { id: instId })
        .then(function (response) {
            if (response && response.status === "success") {
                const data = instrumentFields[instId];
                if (data) {
                    data.installed = false;
                }

                // Remove visual indicators from the card
                const card = $(".instrument-card-col[data-id='" + instId + "'] .instrument-card");
                card.removeClass("installed");

                // Remove Installed Badge
                const cardTop = $(".instrument-card-col[data-id='" + instId + "'] .card-top");
                cardTop.find(".bg-success").remove();

                // Restore install button state on card
                btn.removeClass("is-installed")
                    .removeClass("btn-success")
                    .addClass("btn-primary")
                    .html('<i class="fa-solid fa-download me-2"></i>Install to Project')
                    .prop("disabled", false);

                if (installedCount > 0) {
                    installedCount--;
                }
                $("#stat-installed").text(installedCount + " Installed");

                // Trigger Success Toast
                showToast("'" + (data ? data.title : "Instrument") + "' successfully removed from this project!");
            } else {
                btn.prop("disabled", true).html('<i class="fa-solid fa-check me-2"></i>Installed');
                const errMsg = (response && response.message) ? response.message : "Failed to remove instrument. Please try again.";
                showToast(errMsg, true);
            }
        })
        .catch(function (xhr) {
            btn.prop("disabled", true).html('<i class="fa-solid fa-check me-2"></i>Installed');
            let errMsg = "Failed to remove instrument due to network error.";
            if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                errMsg = xhr.responseJSON.message;
            }
            showToast(errMsg, true);
        });
}

// Toast notification display helper
function showToast(message, isError = false) {
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

    // Dismiss after 12 seconds
    setTimeout(function () {
        toast.removeClass("show");
    }, 12000);
}

// Semantic version comparison helper (v1, v2)
// Returns: 1 if v1 > v2, -1 if v1 < v2, 0 if v1 === v2
function compareSemanticVersions(v1, v2) {
    const clean1 = String(v1).replace(/^v/i, '');
    const clean2 = String(v2).replace(/^v/i, '');
    const parts1 = clean1.split('.').map(Number);
    const parts2 = clean2.split('.').map(Number);
    for (let i = 0; i < Math.max(parts1.length, parts2.length); i++) {
        const p1 = parts1[i] || 0;
        const p2 = parts2[i] || 0;
        if (p1 > p2) return 1;
        if (p1 < p2) return -1;
    }
    return 0;
}
