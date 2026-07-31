let previewModalObj, editModalObj;

$(document).ready(function() {
    previewModalObj = new bootstrap.Modal(document.getElementById('preview-modal'));
    editModalObj = new bootstrap.Modal(document.getElementById('edit-modal'));

    // Bind Search to update active view dynamically
    $("#admin-search").on("input", function() {
        triggerCurrentFilter();
    });

    // Listen for tab switches to re-apply the search filter
    $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
        triggerCurrentFilter();
    });

    // Initialize lists
    renderActiveLibrary();
    renderPendingSubmissions();

    // Checkbox styling toggle on click
    $(document).on('change', '.edit-cat-checkbox', function() {
        const label = $(`label[for="${$(this).attr('id')}"]`);
        if ($(this).is(':checked')) {
            label.find('.unchecked-icon').addClass('d-none');
            label.find('.checked-icon').removeClass('d-none');
        } else {
            label.find('.unchecked-icon').removeClass('d-none');
            label.find('.checked-icon').addClass('d-none');
        }
    });

    // Edit form submission
    $("#edit-instrument-form").on("submit", function(e) {
        e.preventDefault();

        // Validate checkboxes
        const selectedCats = [];
        $(".edit-cat-checkbox:checked").each(function() {
            selectedCats.push($(this).val());
        });

        if (selectedCats.length === 0) {
            $("#edit-categories-invalid").removeClass("d-none");
            return;
        } else {
            $("#edit-categories-invalid").addClass("d-none");
        }

        if (!this.checkValidity()) {
            e.stopPropagation();
            $(this).addClass("was-validated");
            return;
        }

        const id = $("#edit-id").val();
        const type = $("#edit-type").val();
        const title = $("#edit-title").val();
        const desc = $("#edit-desc").val();
        const authorName = $("#edit-author-name").val();
        const authorEmail = $("#edit-author-email").val();
        const estTime = $("#edit-time").val();

        if (type === 'active') {
            const instIndex = activeInstruments.findIndex(i => i.id === id);
            if (instIndex > -1) {
                activeInstruments[instIndex].title = title;
                activeInstruments[instIndex].description = desc;
                activeInstruments[instIndex].categories = selectedCats;
                activeInstruments[instIndex].author_name = authorName;
                activeInstruments[instIndex].author_email = authorEmail;
                activeInstruments[instIndex].time = estTime;

                renderActiveLibrary();
                editModalObj.hide();
                showToast(`<i class="fas fa-save text-success me-1"></i> "${title}" metadata updated successfully!`);
            }
        } else {
            const subIndex = pendingSubmissions.findIndex(s => s.id === id);
            if (subIndex > -1) {
                pendingSubmissions[subIndex].title = title;
                pendingSubmissions[subIndex].description = desc;
                pendingSubmissions[subIndex].categories = selectedCats;
                pendingSubmissions[subIndex].author_name = authorName;
                pendingSubmissions[subIndex].author_email = authorEmail;
                pendingSubmissions[subIndex].time = estTime;

                renderPendingSubmissions();
                editModalObj.hide();
                showToast(`<i class="fas fa-save text-success me-1"></i> Submission "${title}" metadata updated successfully!`);
            }
        }
    });
});

// Helper to filter currently active tab
function triggerCurrentFilter() {
    const activeTab = $("#adminTabs .nav-link.active").attr("id");
    if (activeTab === "active-tab") {
        renderActiveLibrary();
    } else {
        renderPendingSubmissions();
    }
}

// Render Active Library Table
function renderActiveLibrary() {
    const tbody = $("#active-library-tbody");
    const query = $("#admin-search").val().toLowerCase().trim();
    tbody.empty();

    let filtered = activeInstruments.filter(inst => {
        const matchSearch =
            inst.id.toLowerCase().includes(query) ||
            inst.title.toLowerCase().includes(query) ||
            inst.description.toLowerCase().includes(query) ||
            inst.author_name.toLowerCase().includes(query) ||
            (inst.submitted_by && inst.submitted_by.toLowerCase().includes(query)) ||
            (inst.categories && inst.categories.some(c => c.toLowerCase().includes(query)));
        return matchSearch;
    });

    $("#active-count").text(activeInstruments.length);

    if (filtered.length === 0) {
        $("#active-library-table").parent().addClass("d-none");
        $("#active-empty-state").removeClass("d-none");
        return;
    }

    $("#active-library-table").parent().removeClass("d-none");
    $("#active-empty-state").addClass("d-none");

    filtered.forEach(inst => {
        // Generate Categories Badges
        let catBadges = "";
        inst.categories.forEach(cat => {
            const map = categoryMapping.find(c => c.name === cat);
            const cls = map ? map.class : "bg-secondary";
            catBadges += `<span class="badge ${cls} me-1 mb-1 px-2.5 py-1.5 text-uppercase fw-bold" style="font-size: 0.68rem; letter-spacing: 0.05em;">${escapeHtml(cat)}</span>`;
        });

        // Generate Versions Dropdown with submission metadata
        let versionsDropdown = "";
        if (inst.versions && inst.versions.length > 0) {
            let dropdownItems = "";
            inst.versions.forEach(vObj => {
                const isLatest = ('v' + vObj.version) === inst.version;
                const latestBadge = isLatest ? '<span class="badge bg-success ms-2 font-weight-bold" style="font-size:0.65rem;">Active</span>' : '';
                dropdownItems += `
                    <li>
                        <a class="dropdown-item dropdown-version-item py-2 px-3 cursor-pointer" onclick="downloadMockVersion('${inst.id}', '${vObj.version}')">
                            <div class="d-flex align-items-center justify-content-between">
                                <span class="fw-bold font-monospace text-dark">v${vObj.version}</span>
                                ${latestBadge}
                            </div>
                            <div class="text-muted small mt-1" style="font-size: 0.72rem;">
                                <i class="fas fa-user me-1"></i>${escapeHtml(vObj.submitted_by || 'admin')}
                                <span class="mx-1">•</span>
                                <i class="far fa-calendar-alt me-1"></i>${escapeHtml(vObj.submitted_date || 'N/A')}
                            </div>
                        </a>
                    </li>
                `;
            });

            versionsDropdown = `
                <div class="dropdown">
                    <button class="btn btn-sm btn-light border dropdown-toggle fw-semibold font-monospace" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        ${inst.version}
                    </button>
                    <ul class="dropdown-menu shadow-sm" style="font-size: 0.85rem; min-width: 210px;">
                        <li class="dropdown-header text-uppercase tracking-wider fw-bold text-muted" style="font-size:0.68rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.4rem; margin-bottom: 0.4rem;">Versions & Submissions</li>
                        ${dropdownItems}
                    </ul>
                </div>
            `;
        } else {
            versionsDropdown = `<span class="badge bg-light border text-secondary">${inst.version}</span>`;
        }

        // Status Badge
        const isHidden = inst.status === 'hidden';
        const statusBadge = isHidden ?
            `<span class="badge-status badge-status-hidden"><i class="fas fa-eye-slash"></i> Hidden</span>` :
            `<span class="badge-status badge-status-active"><i class="fas fa-check-circle"></i> Active</span>`;

        const hideText = isHidden ? "Unhide" : "Hide";
        const hideIcon = isHidden ? "fa-eye" : "fa-eye-slash";
        const hideBtnClass = isHidden ? "btn-outline-success" : "btn-outline-warning";

        const rowClass = isHidden ? "row-hidden" : "";

        const tr = `
            <tr class="${rowClass}">
                <td>
                    <div class="fw-bold text-dark mb-1" style="font-size:0.95rem;">${escapeHtml(inst.title)}</div>
                    <div class="text-secondary font-monospace mb-1" style="font-size:0.75rem;">ID: ${escapeHtml(inst.id)}</div>
                    <div class="text-muted small mb-2" style="font-size: 0.78rem;">
                        <i class="fas fa-user me-1"></i>Submitted by: <strong>${escapeHtml(inst.submitted_by || 'admin')}</strong> on <strong>${escapeHtml(inst.submitted_date || 'N/A')}</strong>
                    </div>
                    <div class="mt-1">${statusBadge}</div>
                </td>
                <td>
                    <div class="d-flex flex-wrap">${catBadges}</div>
                </td>
                <td>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        ${versionsDropdown}
                    </div>
                </td>
                <td>
                    <div class="fw-semibold text-dark" style="font-size:0.85rem;"><i class="fas fa-list me-1.5 opacity-50"></i>${inst.fields.length} Fields</div>
                    <div class="text-muted small" style="font-size:0.78rem;"><i class="far fa-clock me-1.5 opacity-50"></i>${escapeHtml(inst.time)}</div>
                </td>
                <td>
                    <div class="fw-semibold text-dark">${escapeHtml(inst.author_name)}</div>
                    <div class="text-muted small" style="font-size:0.78rem;">${escapeHtml(inst.author_email)}</div>
                </td>
                <td>
                    <div class="d-flex justify-content-center" style="gap: 8px !important;">
                        <button class="btn btn-sm btn-outline-primary btn-circle-action" onclick="openEditModal('${inst.id}', 'active')" title="Edit Metadata">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm ${hideBtnClass} btn-circle-action" onclick="toggleHideInstrument('${inst.id}')" title="${hideText} Instrument">
                            <i class="fas ${hideIcon}"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-secondary btn-circle-action" onclick="openPreview('${inst.id}', 'active')" title="Preview Fields">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
        tbody.append(tr);
    });
}

// Render Pending Submissions
function renderPendingSubmissions() {
    const container = $("#pending-submissions-container");
    const query = $("#admin-search").val().toLowerCase().trim();
    container.empty();

    let filtered = pendingSubmissions.filter(sub => {
        const matchSearch =
            sub.id.toLowerCase().includes(query) ||
            sub.title.toLowerCase().includes(query) ||
            sub.description.toLowerCase().includes(query) ||
            sub.author_name.toLowerCase().includes(query) ||
            (sub.submitted_by && sub.submitted_by.toLowerCase().includes(query)) ||
            (sub.categories && sub.categories.some(c => c.toLowerCase().includes(query)));
        return matchSearch;
    });

    $("#pending-badge").text(pendingSubmissions.length);

    if (filtered.length === 0) {
        container.addClass("d-none");
        $("#pending-empty-state").removeClass("d-none");
        return;
    }

    container.removeClass("d-none");
    $("#pending-empty-state").addClass("d-none");

    filtered.forEach(sub => {
        // Generate Categories Badges
        let catBadges = "";
        sub.categories.forEach(cat => {
            const map = categoryMapping.find(c => c.name === cat);
            const cls = map ? map.class : "bg-secondary";
            catBadges += `<span class="badge ${cls} me-1 mb-1 px-2.5 py-1.5 text-uppercase fw-bold" style="font-size:0.68rem;">${escapeHtml(cat)}</span>`;
        });

        const card = `
            <div class="col-12 col-xl-6" id="submission-card-${sub.id}">
                <div class="submission-card shadow-sm h-100 d-flex flex-column justify-content-between">
                    <div>
                        <div class="submission-card-header d-flex justify-content-between align-items-start gap-2 flex-wrap">
                            <div>
                                <h4 class="h5 fw-bold text-dark mb-1">${escapeHtml(sub.title)}</h4>
                                <div class="text-secondary small font-monospace" style="font-size:0.75rem;">Submitted ID: ${escapeHtml(sub.id)}</div>
                            </div>
                            <span class="badge bg-warning text-dark fw-bold px-2.5 py-1.5" style="font-size:0.7rem; letter-spacing:0.03em;">
                                <i class="far fa-clock me-1"></i>PENDING REVIEW
                            </span>
                        </div>
                        <div class="submission-card-body">
                            <div class="mb-3 d-flex flex-wrap gap-2 text-secondary small">
                                <span><i class="fas fa-user me-1 text-muted"></i>Submitter: <strong>${escapeHtml(sub.submitted_by)}</strong></span>
                                <span class="text-muted opacity-50">|</span>
                                <span><i class="far fa-envelope me-1 text-muted"></i>${escapeHtml(sub.author_email)}</span>
                                <span class="text-muted opacity-50">|</span>
                                <span><i class="far fa-calendar-alt me-1 text-muted"></i>Subed: ${escapeHtml(sub.submitted_date)}</span>
                            </div>
                            <p class="text-muted mb-3 small" style="line-height:1.5; font-size:0.85rem;">
                                ${escapeHtml(sub.description)}
                            </p>
                            <div class="d-flex flex-wrap mb-3">${catBadges}</div>
                            <div class="border rounded bg-light p-2.5 mb-2 d-flex justify-content-between align-items-center" style="font-size:0.85rem;">
                                <div>
                                    <span class="fw-semibold text-dark"><i class="fas fa-list me-2 text-secondary"></i>${sub.fields.length} Fields</span>
                                    <span class="text-muted mx-2">|</span>
                                    <span class="text-muted"><i class="far fa-clock me-1.5 text-secondary"></i>${escapeHtml(sub.time)}</span>
                                </div>
                                <button class="btn btn-sm btn-link text-primary text-decoration-none p-0 fw-semibold" onclick="openPreview('${sub.id}', 'pending')">
                                    <i class="fas fa-search me-1"></i>Review Fields
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="p-3 border-top bg-light bg-opacity-50 rounded-bottom-3 d-flex gap-2 justify-content-end">
                        <button class="btn btn-outline-primary btn-sm py-2 px-3 fw-bold d-inline-flex align-items-center gap-1.5" onclick="openEditModal('${sub.id}', 'pending')">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button class="btn btn-outline-danger btn-sm py-2 px-3 fw-bold d-inline-flex align-items-center gap-1.5" onclick="rejectSubmission('${sub.id}')">
                            <i class="fas fa-trash-alt"></i> Reject
                        </button>
                        <button class="btn btn-success btn-sm py-2 px-4 fw-bold d-inline-flex align-items-center gap-1.5" onclick="approveSubmission('${sub.id}')">
                            <i class="fas fa-check-circle"></i> Approve
                        </button>
                    </div>
                </div>
            </div>
        `;
        container.append(card);
    });
}

// Toggle Hide/Unhide status of active instrument
function toggleHideInstrument(id) {
    const inst = activeInstruments.find(i => i.id === id);
    if (inst) {
        const willHide = inst.status !== 'hidden';
        inst.status = willHide ? 'hidden' : 'active';
        renderActiveLibrary();

        const statusText = willHide ? 'hidden from' : 'returned to';
        showToast(`<i class="fas ${willHide ? 'fa-eye-slash text-warning' : 'fa-eye text-success'} me-1"></i> "${inst.title}" has been ${statusText} the public library.`);
    }
}

// Open Edit Modal
function openEditModal(id, type = 'active') {
    const list = (type === 'active') ? activeInstruments : pendingSubmissions;
    const inst = list.find(i => i.id === id);
    if (inst) {
        $("#edit-id").val(inst.id);
        $("#edit-type").val(type);
        $("#edit-title").val(inst.title);
        $("#edit-desc").val(inst.description);
        $("#edit-author-name").val(inst.author_name);
        $("#edit-author-email").val(inst.author_email);
        $("#edit-time").val(inst.time);

        // Uncheck all category boxes first
        $(".edit-cat-checkbox").prop("checked", false);
        $(".edit-cat-checkbox").each(function() {
            const label = $(`label[for="${$(this).attr('id')}"]`);
            label.find('.unchecked-icon').removeClass('d-none');
            label.find('.checked-icon').addClass('d-none');
        });

        // Check matching category boxes
        inst.categories.forEach(cat => {
            const checkbox = $(`.edit-cat-checkbox[value="${cat}"]`);
            if (checkbox.length) {
                checkbox.prop("checked", true);
                const label = $(`label[for="${checkbox.attr('id')}"]`);
                label.find('.unchecked-icon').addClass('d-none');
                label.find('.checked-icon').removeClass('d-none');
            }
        });

        $("#edit-instrument-form").removeClass("was-validated");
        $("#edit-categories-invalid").addClass("d-none");
        editModalObj.show();
    }
}

// Open Fields & Metadata Preview Modal
function openPreview(id, type) {
    const list = (type === 'active') ? activeInstruments : pendingSubmissions;
    const data = list.find(i => i.id === id);
    if (!data) return;

    // Build categories
    let catsHtml = "";
    data.categories.forEach(cat => {
        const map = categoryMapping.find(c => c.name === cat);
        const cls = map ? map.class : "bg-secondary";
        catsHtml += `<span class="category-tag badge ${cls} px-2.5 py-1.5 text-uppercase fw-bold" style="font-size: 0.65rem;">${escapeHtml(cat)}</span>`;
    });
    $("#modal-categories").html(catsHtml);

    // Version & details
    $("#modal-version").text(data.version || 'v1.0.0');
    $("#modal-author").text(data.author_name);
    $("#modal-author-email").text(data.author_email);
    $("#modal-desc").text(data.description);

    // Build fields list
    const fieldListContainer = $("#modal-field-list");
    fieldListContainer.empty();
    $("#modal-fields-heading").html(`<i class="fas fa-list me-2 text-primary"></i>Instrument Fields (${data.fields.length})`);

    data.fields.forEach((field, index) => {
        const badgeTypeClass = getBadgeTypeClass(field.type);
        const li = `
            <li class="list-group-item d-flex justify-content-between align-items-center py-3 border-bottom px-4">
                <div style="max-width: 75%;">
                    <div class="fw-semibold text-dark mb-1" style="font-size:0.9rem;">${index + 1}. ${escapeHtml(field.label)}</div>
                    <div class="text-secondary small font-monospace" style="font-size:0.75rem;">Variable Name: ${escapeHtml(field.variable)}</div>
                </div>
                <span class="badge ${badgeTypeClass} border py-1.5 px-2.5 text-uppercase" style="font-size:0.68rem;">${escapeHtml(field.type)}</span>
            </li>
        `;
        fieldListContainer.append(li);
    });

    // Configure mock download zip button
    $("#modal-download-btn").off("click").on("click", function(e) {
        e.preventDefault();
        downloadMockFile(data.id, data.version || '1.0.0', data.title);
    });

    previewModalObj.show();
}

// Approve Pending Submission
function approveSubmission(id) {
    const subIndex = pendingSubmissions.findIndex(s => s.id === id);
    if (subIndex > -1) {
        const sub = pendingSubmissions[subIndex];

        // Remove from pending
        pendingSubmissions.splice(subIndex, 1);

        // Transform and add to active (simulate approval)
        const newInst = {
            id: sub.id,
            title: sub.title,
            categories: sub.categories,
            versions: sub.versions,
            description: sub.description,
            installs: 0,
            time: sub.time,
            author_name: sub.author_name,
            author_email: sub.author_email,
            fields: sub.fields,
            version: 'v' + sub.versions[0].version,
            status: 'active',
            submitted_by: sub.submitted_by,
            submitted_date: sub.submitted_date
        };
        activeInstruments.push(newInst);

        // Re-render
        renderActiveLibrary();
        renderPendingSubmissions();

        showToast(`<i class="fas fa-check-circle text-success me-1"></i> Submission "${sub.title}" approved and added to active library!`);
    }
}

// Reject Pending Submission
function rejectSubmission(id) {
    const subIndex = pendingSubmissions.findIndex(s => s.id === id);
    if (subIndex > -1) {
        const sub = pendingSubmissions[subIndex];

        // Remove from pending
        pendingSubmissions.splice(subIndex, 1);

        // Re-render
        renderPendingSubmissions();

        showToast(`<i class="fas fa-trash-alt text-danger me-1"></i> Submission "${sub.title}" has been rejected.`);
    }
}

// Trigger mock ZIP package downloads for specific versions
function downloadMockVersion(instId, versionStr) {
    const inst = activeInstruments.find(i => i.id === instId);
    if (inst) {
        downloadMockFile(instId, versionStr, inst.title);
    }
}

// Helper to generate and download mock ZIP binary
function downloadMockFile(id, version, title) {
    const mockZipContent = "PK\x03\x04\n\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x0e\x00\t\x00metadata.json" + JSON.stringify({
        id,
        version,
        title
    }, null, 2);
    const blob = new Blob([mockZipContent], {
        type: "application/zip"
    });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `${id}_v${version}_instrument.zip`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    showToast(`<i class="fas fa-file-archive text-primary me-1"></i> Downloaded ZIP package for "${title}" (v${version})`);
}

// Utility: Toast displayer
function showToast(htmlMessage) {
    const toast = $("#admin-toast");
    toast.find("#toast-message").html(htmlMessage);
    toast.addClass("show");
    setTimeout(() => {
        toast.removeClass("show");
    }, 4000);
}

// Helper: Map field types to CSS badges
function getBadgeTypeClass(type) {
    switch (type.toLowerCase()) {
        case 'text':
            return 'type-text';
        case 'date':
        case 'datetime':
            return 'type-date';
        case 'radio':
            return 'type-radio';
        case 'checkbox':
            return 'type-checkbox';
        case 'dropdown':
            return 'type-dropdown';
        case 'calc':
            return 'type-calc';
        default:
            return 'bg-secondary text-white';
    }
}

// Helper: HTML escape helper
function escapeHtml(str) {
    if (typeof str !== 'string') return '';
    return str
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}
