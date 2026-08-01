import { escapeHtml, getBadgeTypeClass, renderCategoryBadges, getBadgeForType, renderFieldListItem } from './utils.js';

// 0. Scoped Modal References
let previewModalObj = new bootstrap.Modal(document.getElementById('preview-modal'));
let editModalObj = new bootstrap.Modal(document.getElementById('edit-modal'));

// Bind Search to update active view dynamically
$("#admin-search").on("input", (e) => {
    const activeTab = $("#adminTabs .nav-link.active").attr("id");
    if (activeTab === "active-tab") {
        if ($.fn.DataTable && $.fn.DataTable.isDataTable('#active-library-table')) {
            $('#active-library-table').DataTable().search(e.target.value).draw();
        }
    } else {
        renderPendingSubmissions();
    }
});

// Listen for tab switches to re-apply the search filter
$('button[data-bs-toggle="tab"]').on('shown.bs.tab', () => triggerCurrentFilter());

// Checkbox styling toggle on click
$(document).on('change', '.edit-cat-checkbox', (e) => {
    const $this = $(e.target).closest(".edit-cat-checkbox");
    const label = $(`label[for="${$this.attr('id')}"]`);
    if ($this.is(':checked')) {
        label.find('.unchecked-icon').addClass('d-none');
        label.find('.checked-icon').removeClass('d-none');
    } else {
        label.find('.unchecked-icon').removeClass('d-none');
        label.find('.checked-icon').addClass('d-none');
    }
});

// Edit form submission
$("#edit-instrument-form").on("submit", (e) => {
    e.preventDefault();
    const form = e.currentTarget;

    // Validate checkboxes
    const selectedCats = [];
    $(".edit-cat-checkbox:checked").each((idx, el) => selectedCats.push($(el).val()));

    if (selectedCats.length === 0) {
        $("#edit-categories-invalid").removeClass("d-none");
        return;
    }
    $("#edit-categories-invalid").addClass("d-none");

    if (!form.checkValidity()) {
        e.stopPropagation();
        $(form).addClass("was-validated");
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
        if (instIndex === -1) return;
        activeInstruments[instIndex].title = title;
        activeInstruments[instIndex].description = desc;
        activeInstruments[instIndex].categories = selectedCats;
        activeInstruments[instIndex].author_name = authorName;
        activeInstruments[instIndex].author_email = authorEmail;
        activeInstruments[instIndex].time = estTime;

        renderActiveLibrary();
        editModalObj.hide();
        showToast(`<i class="fas fa-save text-success me-1"></i> "${title}" metadata updated successfully!`);
        return;
    }

    const subIndex = pendingSubmissions.findIndex(s => s.id === id);
    if (subIndex === -1) return;
    pendingSubmissions[subIndex].title = title;
    pendingSubmissions[subIndex].description = desc;
    pendingSubmissions[subIndex].categories = selectedCats;
    pendingSubmissions[subIndex].author_name = authorName;
    pendingSubmissions[subIndex].author_email = authorEmail;
    pendingSubmissions[subIndex].time = estTime;

    renderPendingSubmissions();
    editModalObj.hide();
    showToast(`<i class="fas fa-save text-success me-1"></i> Submission "${title}" metadata updated successfully!`);
});

// Helper to filter currently active tab
const triggerCurrentFilter = () => {
    const activeTab = $("#adminTabs .nav-link.active").attr("id");
    if (activeTab === "active-tab")
        renderActiveLibrary();
    else
        renderPendingSubmissions();
};

// // Render Active Library Table
const renderActiveLibrary = () => {
    if ($.fn.DataTable && $.fn.DataTable.isDataTable('#active-library-table')) {
        $('#active-library-table').DataTable().destroy();
    }

    const tbody = $("#active-library-tbody");
    tbody.empty();

    $("#active-count").text(activeInstruments.length);

    if (activeInstruments.length === 0) {
        $("#active-library-table").parent().addClass("d-none");
        $("#active-empty-state").removeClass("d-none");
        return;
    }

    $("#active-library-table").parent().removeClass("d-none");
    $("#active-empty-state").addClass("d-none");

    activeInstruments.forEach(inst => {
        // Generate Categories Badges
        let catBadges = "";
        inst.categories.forEach(cat => {
            const map = categoryMapping.find(c => c.name === cat);
            const cls = map ? map.class : "bg-secondary";
            catBadges += `<span class="badge ${cls} me-1 mb-1 px-2.5 py-1.5 text-uppercase fw-bold" style="font-size: 0.68rem; letter-spacing: 0.05em;">${escapeHtml(cat)}</span>`;
        });

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
                    <span class="badge bg-light border text-secondary fw-semibold font-monospace py-1.5 px-2.5" style="font-size:0.82rem;">${escapeHtml(inst.version)}</span>
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

    if ($.fn.DataTable) {
        const activeDataTable = $('#active-library-table').DataTable({
            pageLength: 10,
            dom: '<"p-3 d-flex justify-content-between align-items-center"l>t<"p-3 d-flex justify-content-between align-items-center"ip>',
            columnDefs: [
                { orderable: false, targets: 5 }
            ],
            language: {
                emptyTable: "No instruments found",
                zeroRecords: "No matching instruments found"
            }
        });

        const query = $("#admin-search").val().trim();
        if (query) {
            activeDataTable.search(query).draw();
        }
    }
};

// Render Pending Submissions
const renderPendingSubmissions = () => {
    if ($.fn.DataTable && $.fn.DataTable.isDataTable('#pending-submissions-table')) {
        $('#pending-submissions-table').DataTable().destroy();
    }

    const tbody = $("#pending-submissions-tbody");
    tbody.empty();

    $("#pending-badge").text(pendingSubmissions.length);

    if (pendingSubmissions.length === 0) {
        $("#pending-submissions-table").parent().addClass("d-none");
        $("#pending-empty-state").removeClass("d-none");
        return;
    }

    $("#pending-submissions-table").parent().removeClass("d-none");
    $("#pending-empty-state").addClass("d-none");

    pendingSubmissions.forEach(sub => {
        // Generate Categories Badges
        let catBadges = "";
        sub.categories.forEach(cat => {
            const map = categoryMapping.find(c => c.name === cat);
            const cls = map ? map.class : "bg-secondary";
            catBadges += `<span class="badge ${cls} me-1 mb-1 px-2.5 py-1.5 text-uppercase fw-bold" style="font-size:0.68rem; letter-spacing: 0.05em;">${escapeHtml(cat)}</span>`;
        });

        const tr = `
            <tr>
                <td>
                    <div class="fw-bold text-dark mb-1" style="font-size:0.95rem;">${escapeHtml(sub.title)}</div>
                    <div class="text-secondary font-monospace mb-1" style="font-size:0.75rem;">ID: ${escapeHtml(sub.id)}</div>
                    <div class="text-muted small mb-1" style="font-size:0.78rem;">${escapeHtml(sub.description)}</div>
                </td>
                <td>
                    <div class="d-flex flex-wrap">${catBadges}</div>
                </td>
                <td>
                    <span class="badge bg-light border text-secondary fw-semibold font-monospace py-1.5 px-2.5" style="font-size:0.82rem;">${escapeHtml(sub.version || 'v1.0.0')}</span>
                </td>
                <td>
                    <div class="fw-semibold text-dark" style="font-size:0.85rem;"><i class="fas fa-list me-1.5 opacity-50"></i>${sub.fields.length} Fields</div>
                    <div class="text-muted small" style="font-size:0.78rem;"><i class="far fa-clock me-1.5 opacity-50"></i>${escapeHtml(sub.time)}</div>
                </td>
                <td class="text-center">
                    <span class="badge bg-warning text-dark fw-bold px-2 py-1" style="font-size:0.68rem; letter-spacing:0.03em;">
                        <i class="far fa-clock me-1"></i>PENDING
                    </span>
                </td>
                <td>
                    <div class="d-flex justify-content-center gap-1">
                        <button class="btn btn-sm btn-outline-success btn-circle-action" onclick="approveSubmission('${sub.id}')" title="Approve Submission">
                            <i class="fas fa-check"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger btn-circle-action" onclick="rejectSubmission('${sub.id}')" title="Reject Submission">
                            <i class="fas fa-times"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-secondary btn-circle-action" onclick="openPreview('${sub.id}', 'pending')" title="Review Fields">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
        tbody.append(tr);
    });

    if ($.fn.DataTable) {
        const pendingDataTable = $('#pending-submissions-table').DataTable({
            pageLength: 10,
            dom: '<"p-3 d-flex justify-content-between align-items-center"l>t<"p-3 d-flex justify-content-between align-items-center"ip>',
            columnDefs: [
                { orderable: false, targets: 5 }
            ],
            language: {
                emptyTable: "No pending submissions found",
                zeroRecords: "No matching pending submissions found"
            }
        });

        const query = $("#admin-search").val().trim();
        if (query) {
            pendingDataTable.search(query).draw();
        }
    }
};

// Toggle Hide/Unhide status of active instrument
const toggleHideInstrument = (id) => {
    const inst = activeInstruments.find(i => i.id === id);
    if (!inst) return;
    const willHide = inst.status !== 'hidden';
    inst.status = willHide ? 'hidden' : 'active';
    renderActiveLibrary();

    const statusText = willHide ? 'hidden from' : 'returned to';
    showToast(`<i class="fas ${willHide ? 'fa-eye-slash text-warning' : 'fa-eye text-success'} me-1"></i> "${inst.title}" has been ${statusText} the public library.`);
};

// Open Edit Modal
const openEditModal = (id, type = 'active') => {
    const list = (type === 'active') ? activeInstruments : pendingSubmissions;
    const inst = list.find(i => i.id === id);
    if (!inst) return;
    $("#edit-id").val(inst.id);
    $("#edit-type").val(type);
    $("#edit-title").val(inst.title);
    $("#edit-desc").val(inst.description);
    $("#edit-author-name").val(inst.author_name);
    $("#edit-author-email").val(inst.author_email);
    $("#edit-time").val(inst.time);

    // Uncheck all category boxes first
    $(".edit-cat-checkbox").prop("checked", false);
    $(".edit-cat-checkbox").each((idx, el) => {
        const label = $(`label[for="${$(el).attr('id')}"]`);
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
};

// Open Fields & Metadata Preview Modal
const openPreview = (id, type) => {
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

    data.fields.forEach((field) => {
        fieldListContainer.append(renderFieldListItem(field));
    });

    // Populate Version Downloads list
    const versionsContainer = $("#modal-version-downloads");
    versionsContainer.empty();

    const versionsList = (data.versions && data.versions.length > 0) ? data.versions : [
        { version: String(data.version || '1.0.0').replace(/^v/, ''), submitted_by: data.submitted_by || 'admin', submitted_date: data.submitted_date || 'N/A' }
    ];

    versionsList.forEach(vObj => {
        const vStr = String(vObj.version).replace(/^v/, '');
        const isLatest = ('v' + vStr) === data.version;
        const latestBadge = isLatest ? '<span class="badge bg-success ms-1 font-weight-bold" style="font-size:0.6rem;">Active</span>' : '';

        const item = `
            <div class="border rounded p-2.5 bg-white d-flex align-items-center justify-content-between gap-2 shadow-sm">
                <div>
                    <div class="fw-bold font-monospace text-dark d-flex align-items-center" style="font-size:0.82rem;">
                        v${vStr} ${latestBadge}
                    </div>
                    <div class="text-muted small" style="font-size:0.72rem;">
                        <i class="fas fa-user me-1"></i>${escapeHtml(vObj.submitted_by || 'admin')}
                        <span class="mx-1">•</span>
                        <i class="far fa-calendar-alt me-1"></i>${escapeHtml(vObj.submitted_date || 'N/A')}
                    </div>
                </div>
                <button class="btn btn-sm btn-outline-primary fw-semibold d-inline-flex align-items-center gap-1 py-1 px-2.5" onclick="downloadMockVersion('${data.id}', '${vStr}')" title="Download v${vStr} ZIP">
                    <i class="fas fa-download"></i>
                    <span style="font-size:0.75rem;">ZIP</span>
                </button>
            </div>
        `;
        versionsContainer.append(item);
    });

    previewModalObj.show();
};

// Approve Pending Submission
const approveSubmission = (id) => {
    const subIndex = pendingSubmissions.findIndex(s => s.id === id);
    if (subIndex === -1) return;
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
};

// Reject Pending Submission
const rejectSubmission = (id) => {
    const subIndex = pendingSubmissions.findIndex(s => s.id === id);
    if (subIndex === -1) return;
    const sub = pendingSubmissions[subIndex];

    // Remove from pending
    pendingSubmissions.splice(subIndex, 1);

    // Re-render
    renderPendingSubmissions();

    showToast(`<i class="fas fa-trash-alt text-danger me-1"></i> Submission "${sub.title}" has been rejected.`);
};

// Trigger mock ZIP package downloads for specific versions
const downloadMockVersion = (instId, versionStr) => {
    const inst = activeInstruments.find(i => i.id === instId);
    if (inst)
        downloadMockFile(instId, versionStr, inst.title);
};

// Helper to generate and download mock ZIP binary
const downloadMockFile = (id, version, title) => {
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
};

// Utility: Toast displayer
const showToast = (htmlMessage) => {
    const toast = $("#admin-toast");
    toast.find("#toast-message").html(htmlMessage);
    toast.addClass("show");
    setTimeout(() => {
        toast.removeClass("show");
    }, 4000);
};

// Helper: HTML escape helper (now imported from utils.js)
// Expose necessary functions globally for HTML string onclick event handlers
window.downloadMockVersion = downloadMockVersion;
window.openEditModal = openEditModal;
window.toggleHideInstrument = toggleHideInstrument;
window.openPreview = openPreview;
window.rejectSubmission = rejectSubmission;
window.approveSubmission = approveSubmission;

// Initial render call and badge updates
$("#active-count").text(activeInstruments.length);
$("#pending-badge").text(pendingSubmissions.length);
triggerCurrentFilter();
