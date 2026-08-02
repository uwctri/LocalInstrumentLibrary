import { escapeHtml, renderFieldListItem, compareSemanticVersions, renderCategoryBadgesHtml, downloadMockFile, downloadMockVersion } from './utils.js';

const previewModalEl = document.getElementById('preview-modal');
let previewModalObj = previewModalEl ? new bootstrap.Modal(previewModalEl) : null;
const editModalEl = document.getElementById('edit-modal');
let editModalObj = editModalEl ? new bootstrap.Modal(editModalEl) : null;
const reviewModalEl = document.getElementById('review-modal');
let reviewModalObj = reviewModalEl ? new bootstrap.Modal(reviewModalEl) : null;

$("#admin-search").on("input", (e) => {
    if ($("#adminTabs .nav-link.active").attr("id") === "active-tab") {
        if ($.fn.DataTable && $.fn.DataTable.isDataTable('#active-library-table')) $('#active-library-table').DataTable().search(e.target.value).draw();
    } else {
        renderPendingSubmissions();
    }
});

$('button[data-bs-toggle="tab"]').on('shown.bs.tab', () => triggerCurrentFilter());

$(document).on('change', '.edit-cat-checkbox', (e) => {
    const $this = $(e.target).closest(".edit-cat-checkbox"), label = $(`label[for="${$this.attr('id')}"]`);
    if ($this.is(':checked')) {
        label.find('.unchecked-icon').addClass('d-none');
        label.find('.checked-icon').removeClass('d-none');
    } else {
        label.find('.unchecked-icon').removeClass('d-none');
        label.find('.checked-icon').addClass('d-none');
    }
});

$("#edit-instrument-form").on("submit", (e) => {
    e.preventDefault();
    const form = e.currentTarget, selectedCats = [];
    $(".edit-cat-checkbox:checked").each((idx, el) => selectedCats.push($(el).val()));

    if (selectedCats.length === 0) return $("#edit-categories-invalid").removeClass("d-none");
    $("#edit-categories-invalid").addClass("d-none");

    if (!form.checkValidity()) {
        e.stopPropagation();
        $(form).addClass("was-validated");
        return;
    }

    const id = $("#edit-id").val(), type = $("#edit-type").val(), title = $("#edit-title").val(), desc = $("#edit-desc").val(), authorName = $("#edit-author-name").val(), authorEmail = $("#edit-author-email").val(), owner = $("#edit-owner").val().trim();

    if (type === 'active') {
        const instIndex = activeInstruments.findIndex(i => i.id === id);
        if (instIndex === -1) return;
        activeInstruments[instIndex].title = title;
        activeInstruments[instIndex].description = desc;
        activeInstruments[instIndex].categories = selectedCats;
        activeInstruments[instIndex].author_name = authorName;
        activeInstruments[instIndex].author_email = authorEmail;
        activeInstruments[instIndex].owner = owner;

        const saveBtn = $("#save-edit-btn").prop("disabled", true).html('<i class="fas fa-spinner fa-spin me-2"></i>Saving...');
        module.ajax("save-metadata", { id, title, description: desc, categories: selectedCats, author_name: authorName, author_email: authorEmail, owner }).then(response => {
            saveBtn.prop("disabled", false).html('<i class="fas fa-save me-2"></i>Save Changes');
            if (editModalObj) editModalObj.hide();
            renderActiveLibrary();
            showToast(response && response.status === 'success' ? `<i class="fas fa-save text-success me-1"></i> "${title}" metadata updated!` : `<i class="fas fa-exclamation-triangle text-danger me-1"></i> ${response ? response.message : 'Error.'}`);
        }).catch(() => {
            saveBtn.prop("disabled", false).html('<i class="fas fa-save me-2"></i>Save Changes');
            if (editModalObj) editModalObj.hide();
            showToast('<i class="fas fa-exclamation-triangle text-danger me-1"></i> Error saving metadata.');
        });
        return;
    }

    const subIndex = pendingSubmissions.findIndex(s => s.id === id);
    if (subIndex === -1) return;
    pendingSubmissions[subIndex].title = title;
    pendingSubmissions[subIndex].description = desc;
    pendingSubmissions[subIndex].categories = selectedCats;
    pendingSubmissions[subIndex].author_name = authorName;
    pendingSubmissions[subIndex].author_email = authorEmail;
    if (owner) pendingSubmissions[subIndex].owner = owner;

    renderPendingSubmissions();
    if (editModalObj) editModalObj.hide();
    showToast(`<i class="fas fa-save text-success me-1"></i> Submission "${title}" metadata updated!`);
});

const triggerCurrentFilter = () => {
    if ($("#adminTabs .nav-link.active").attr("id") === "active-tab") renderActiveLibrary();
    else renderPendingSubmissions();
};

const renderActiveLibrary = () => {
    if ($.fn.DataTable && $.fn.DataTable.isDataTable('#active-library-table')) $('#active-library-table').DataTable().destroy();
    const tbody = $("#active-library-tbody").empty();
    $("#active-count").text(activeInstruments.length);

    if (activeInstruments.length === 0) {
        $("#active-library-table").parent().addClass("d-none");
        $("#active-empty-state").removeClass("d-none");
        return;
    }

    $("#active-library-table").parent().removeClass("d-none");
    $("#active-empty-state").addClass("d-none");

    activeInstruments.forEach(inst => {
        const catBadges = renderCategoryBadgesHtml(inst.categories, categoryMapping);
        const isHidden = inst.status === 'hidden';
        const statusBadge = isHidden ? `<span class="badge-status badge-status-hidden"><i class="fas fa-eye-slash"></i> Hidden</span>` : `<span class="badge-status badge-status-active"><i class="fas fa-check-circle"></i> Active</span>`;

        tbody.append(`
            <tr class="${isHidden ? "row-hidden" : ""}">
                <td>
                    <div class="fw-bold text-dark mb-1 fs-95">${escapeHtml(inst.title)}</div>
                    <div class="text-secondary font-monospace mb-1 fs-7">ID: ${escapeHtml(inst.id)}</div>
                    <div class="text-muted small mb-2 fs-75">
                        <i class="fas fa-user me-1"></i>Submitted by: <strong>${escapeHtml(inst.submitted_by || 'admin')}</strong> on <strong>${escapeHtml(inst.submitted_date || 'N/A')}</strong>
                    </div>
                    <div class="mt-1">${statusBadge}</div>
                </td>
                <td><div class="d-flex flex-wrap">${catBadges}</div></td>
                <td><span class="font-monospace text-dark fw-semibold fs-88">${escapeHtml(inst.version)}</span></td>
                <td><span class="fw-semibold text-dark fs-88">${inst.fields.length}</span></td>
                <td>
                    <div class="fw-semibold text-dark fs-88">${escapeHtml(inst.author_name)}</div>
                    <div class="text-muted small fs-75">${escapeHtml(inst.author_email)}</div>
                </td>
                <td><div class="font-monospace text-dark fs-8">${escapeHtml(inst.owner || '—')}</div></td>
                <td>
                    <div class="d-flex justify-content-center gap-xs">
                        <button class="btn btn-sm btn-outline-primary btn-circle-action" onclick="openEditModal('${inst.id}', 'active')" title="Edit Metadata"><i class="fas fa-edit"></i></button>
                        <button class="btn btn-sm ${isHidden ? "btn-outline-success" : "btn-outline-warning"} btn-circle-action" onclick="toggleHideInstrument('${inst.id}')" title="${isHidden ? "Unhide" : "Hide"} Instrument"><i class="fas ${isHidden ? "fa-eye" : "fa-eye-slash"}"></i></button>
                        <button class="btn btn-sm btn-outline-secondary btn-circle-action" onclick="openPreview('${inst.id}', 'active')" title="Preview Fields"><i class="fas fa-search"></i></button>
                    </div>
                </td>
            </tr>
        `);
    });

    if ($.fn.DataTable) {
        const dt = $('#active-library-table').DataTable({
            pageLength: 10, dom: '<"p-3 d-flex justify-content-between align-items-center"l>t<"p-3 d-flex justify-content-between align-items-center"ip>',
            columnDefs: [{ orderable: false, targets: 6 }],
            language: { emptyTable: "No instruments found", zeroRecords: "No matching instruments found" }
        });
        const query = $("#admin-search").val().trim();
        if (query) dt.search(query).draw();
    }
};

const renderPendingSubmissions = () => {
    if ($.fn.DataTable && $.fn.DataTable.isDataTable('#pending-submissions-table')) $('#pending-submissions-table').DataTable().destroy();
    const tbody = $("#pending-submissions-tbody").empty();
    $("#pending-badge").text(pendingSubmissions.length);

    if (pendingSubmissions.length === 0) {
        $("#pending-submissions-table").parent().addClass("d-none");
        $("#pending-empty-state").removeClass("d-none");
        return;
    }

    $("#pending-submissions-table").parent().removeClass("d-none");
    $("#pending-empty-state").addClass("d-none");

    pendingSubmissions.forEach(sub => {
        const catBadges = renderCategoryBadgesHtml(sub.categories, categoryMapping);

        tbody.append(`
            <tr>
                <td>
                    <div class="fw-bold text-dark mb-1 fs-95">${escapeHtml(sub.title)}</div>
                    <div class="text-secondary font-monospace mb-1 fs-7">ID: ${escapeHtml(sub.id)}</div>
                    <div class="text-muted small mb-1 fs-75">${escapeHtml(sub.description)}</div>
                </td>
                <td><div class="d-flex flex-wrap">${catBadges}</div></td>
                <td><span class="font-monospace text-dark fw-semibold fs-88">${escapeHtml(sub.version || 'v1.0.0')}</span></td>
                <td><span class="fw-semibold text-dark fs-88">${sub.fields.length}</span></td>
                <td class="text-center">
                    <div class="d-flex justify-content-center">
                        <button class="btn btn-sm btn-outline-primary btn-circle-action" onclick="openReviewModal('${sub.id}')" title="Review Submission"><i class="fas fa-clipboard-check"></i></button>
                    </div>
                </td>
            </tr>
        `);
    });

    if ($.fn.DataTable) {
        const dt = $('#pending-submissions-table').DataTable({
            pageLength: 10, dom: '<"p-3 d-flex justify-content-between align-items-center"l>t<"p-3 d-flex justify-content-between align-items-center"ip>',
            columnDefs: [{ orderable: false, targets: 4 }],
            language: { emptyTable: "No pending submissions found", zeroRecords: "No matching pending submissions found" }
        });
        const query = $("#admin-search").val().trim();
        if (query) dt.search(query).draw();
    }
};

const toggleHideInstrument = (id) => {
    const inst = activeInstruments.find(i => i.id === id);
    if (!inst) return;
    const willHide = inst.status !== 'hidden';
    const newStatus = willHide ? 'hidden' : 'active';

    module.ajax("save-metadata", { id: inst.id, status: newStatus }).then(response => {
        if (response && response.status === 'success') {
            inst.status = newStatus;
            renderActiveLibrary();
            showToast(`<i class="fas ${willHide ? 'fa-eye-slash text-warning' : 'fa-eye text-success'} me-1"></i> "${inst.title}" has been ${willHide ? 'hidden from' : 'returned to'} public library.`);
        } else {
            showToast(`<i class="fas fa-exclamation-triangle text-danger me-1"></i> ${response ? response.message : 'Error updating status.'}`);
        }
    }).catch(() => {
        showToast('<i class="fas fa-exclamation-triangle text-danger me-1"></i> Error updating instrument status.');
    });
};

const openEditModal = (id, type = 'active') => {
    const inst = ((type === 'active') ? activeInstruments : pendingSubmissions).find(i => i.id === id);
    if (!inst) return;

    $("#edit-id").val(inst.id); $("#edit-type").val(type); $("#edit-title").val(inst.title);
    $("#edit-desc").val(inst.description); $("#edit-author-name").val(inst.author_name);
    $("#edit-author-email").val(inst.author_email); $("#edit-owner").val(inst.owner || '');

    $(".edit-cat-checkbox").prop("checked", false).each((idx, el) => {
        const label = $(`label[for="${$(el).attr('id')}"]`);
        label.find('.unchecked-icon').removeClass('d-none');
        label.find('.checked-icon').addClass('d-none');
    });

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
    if (editModalObj) editModalObj.show();
};

const openPreview = (id, type) => {
    const data = ((type === 'active') ? activeInstruments : pendingSubmissions).find(i => i.id === id);
    if (!data) return;

    $("#modal-categories").html(renderCategoryBadgesHtml(data.categories, categoryMapping));
    $("#modal-version").text(data.version || 'v1.0.0');
    $("#modal-author").text(data.author_name);
    $("#modal-author-email").text(data.author_email);
    $("#modal-desc").text(data.description);

    const fieldListContainer = $("#modal-field-list").empty();
    $("#modal-fields-heading").html(`<i class="fas fa-list me-2 text-primary"></i>Instrument Fields (${data.fields.length})`);
    data.fields.forEach((field) => fieldListContainer.append(renderFieldListItem(field)));

    const versionsContainer = $("#modal-version-downloads").empty();
    const rawVersionsList = (data.versions && data.versions.length > 0) ? data.versions : [{ version: String(data.version || '1.0.0').replace(/^v/, ''), submitted_by: data.submitted_by || 'admin', submitted_date: data.submitted_date || 'N/A' }];
    const versionsList = [...rawVersionsList].sort((a, b) => compareSemanticVersions(b.version, a.version));

    versionsList.forEach(vObj => {
        const vStr = String(vObj.version).replace(/^v/, '');
        const isLatest = ('v' + vStr) === data.version || vStr === String(data.version).replace(/^v/, '');
        versionsContainer.append(`
            <div class="border rounded-3 p-3 bg-white d-flex align-items-center justify-content-between gap-3 shadow-sm mb-2">
                <div>
                    <div class="fw-bold font-monospace text-dark d-flex align-items-center fs-9">
                        v${vStr} ${isLatest ? '<span class="badge bg-success ms-2 font-weight-bold fs-xs">Active</span>' : ''}
                    </div>
                    <div class="text-muted small mt-1 fs-75">
                        <i class="fas fa-user me-1 text-secondary opacity-75"></i>${escapeHtml(vObj.submitted_by || 'admin')}
                        <span class="mx-1.5 text-muted opacity-50">•</span>
                        <i class="far fa-calendar-alt me-1 text-secondary opacity-75"></i>${escapeHtml(vObj.submitted_date || 'N/A')}
                    </div>
                </div>
                <button class="btn btn-sm btn-outline-primary fw-semibold d-inline-flex align-items-center gap-1.5 py-1.5 px-3" onclick="downloadMockVersion('${data.id}', '${vStr}')" title="Download v${vStr} ZIP">
                    <i class="fas fa-download"></i><span class="fs-8">ZIP</span>
                </button>
            </div>
        `);
    });

    if (previewModalObj) previewModalObj.show();
};

let currentReviewDecision = 'approve';
let reviewDeclineReasonText = defaultDeclineEmailBody;

const openReviewModal = (id) => {
    const sub = pendingSubmissions.find(s => s.id === id);
    if (!sub) return;

    reviewDeclineReasonText = defaultDeclineEmailBody;
    $("#review-id").val(sub.id); $("#review-title").text(sub.title); $("#review-version").text(sub.version || 'v1.0.0');
    $("#review-submitter").text(sub.submitted_by || 'Unknown'); $("#review-author").text(`${sub.author_name} (${sub.author_email})`);
    $("#review-desc").text(sub.description); $("#review-decline-reason").val(defaultDeclineEmailBody);

    $("#review-categories").html(renderCategoryBadgesHtml(sub.categories, categoryMapping));

    const list = $("#review-field-list").empty();
    sub.fields.forEach((field, index) => list.append(renderFieldListItem(field, index)));

    const downloadContainer = $("#review-version-downloads").empty();
    const dUrl = (typeof downloadPageUrl !== 'undefined') ? downloadPageUrl : 'download.php';
    let hasRealFiles = false;

    if (sub.versions && sub.versions.length) {
        sub.versions.forEach(vObj => {
            if (vObj.doc_id && !isNaN(vObj.doc_id)) {
                hasRealFiles = true;
                downloadContainer.append(`
                    <a href="${dUrl}?doc_id=${encodeURIComponent(vObj.doc_id)}" class="btn btn-sm btn-outline-secondary w-100 fw-semibold d-inline-flex align-items-center justify-content-between py-1.5 px-3">
                        <span><i class="fas fa-file-archive me-1.5 text-primary"></i>${escapeHtml(vObj.version)}</span><i class="fas fa-download text-muted"></i>
                    </a>
                `);
            }
        });
    }

    if (!hasRealFiles && sub.doc_id && !isNaN(sub.doc_id)) {
        hasRealFiles = true;
        downloadContainer.append(`
            <a href="${dUrl}?doc_id=${encodeURIComponent(sub.doc_id)}" class="btn btn-sm btn-outline-secondary w-100 fw-semibold d-inline-flex align-items-center justify-content-between py-1.5 px-3">
                <span><i class="fas fa-file-archive me-1.5 text-primary"></i>${escapeHtml(sub.version || 'v1.0.0')}</span><i class="fas fa-download text-muted"></i>
            </a>
        `);
    }

    if (!hasRealFiles) {
        downloadContainer.append(`
            <button type="button" class="btn btn-sm btn-outline-secondary w-100 fw-semibold d-inline-flex align-items-center justify-content-between py-1.5 px-3" onclick="downloadMockVersion('${sub.id}', '${sub.version || 'v1.0.0'}')">
                <span><i class="fas fa-file-csv me-1.5 text-primary"></i>${escapeHtml(sub.version || 'v1.0.0')} (Demo Package)</span><i class="fas fa-download text-muted"></i>
            </button>
        `);
    }

    switchReviewDecisionTab('approve');
    goToReviewStep(1);
    if (reviewModalObj) reviewModalObj.show();
};

const switchReviewDecisionTab = (mode) => {
    currentReviewDecision = mode;
    const approveTab = $("#review-tab-approve"), declineTab = $("#review-tab-decline"), declineInputContainer = $("#review-decline-input-container");
    if (mode === 'approve') {
        approveTab.addClass("active btn-success text-white").removeClass("btn-outline-success text-success");
        declineTab.removeClass("active btn-danger text-white").addClass("btn-outline-danger");
        declineInputContainer.addClass("d-none");
    } else {
        declineTab.addClass("active btn-danger text-white").removeClass("btn-outline-danger");
        approveTab.removeClass("active btn-success text-white").addClass("btn-outline-success");
        declineInputContainer.removeClass("d-none");
    }
    if (!$("#review-step2-right").hasClass("d-none")) renderStep2Confirmation();
};

const goToReviewStep = (step) => {
    if (step === 1) {
        $("#review-step1-right, #review-step1-nav").removeClass("d-none");
        $("#review-step2-right, #review-step2-nav").addClass("d-none");
    } else {
        renderStep2Confirmation();
        $("#review-step1-right, #review-step1-nav").addClass("d-none");
        $("#review-step2-right, #review-step2-nav").removeClass("d-none");
    }
};

const renderStep2Confirmation = () => {
    const id = $("#review-id").val(), sub = pendingSubmissions.find(s => s.id === id);
    if (!sub) return;
    const container = $("#review-finalize-container").empty();

    if (currentReviewDecision === 'approve') {
        container.html(`
            <div class="card border-success border-opacity-25 bg-success bg-opacity-10 p-4 rounded-3 mb-4 shadow-sm">
                <div class="d-flex align-items-center gap-3 mb-3 pb-3 border-bottom border-success border-opacity-25">
                    <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 icon-circle-md"><i class="fas fa-check fs-5"></i></div>
                    <div><h5 class="fw-bold text-success-emphasis m-0">Publish Instrument</h5><p class="text-secondary small m-0">Publish this submission into active library.</p></div>
                </div>
                <div class="mb-4">
                    <div class="p-3 bg-white rounded-3 border mb-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="fw-bold text-dark fs-6">${escapeHtml(sub.title)}</span>
                            <span class="badge bg-light text-secondary border font-monospace">${escapeHtml(sub.version || 'v1.0.0')}</span>
                        </div>
                        <p class="text-muted small mb-0">${escapeHtml(sub.description)}</p>
                    </div>
                    <div class="alert alert-success bg-white border-success border-opacity-50 text-success-emphasis rounded-3 p-3 small mb-0">
                        <i class="fas fa-info-circle me-2"></i>Once approved, <strong>${escapeHtml(sub.title)}</strong> will become available to all users. Confirmation email will be sent to <strong>${escapeHtml(sub.author_email)}</strong>.
                    </div>
                </div>
                <div class="d-flex justify-content-end">
                    <button type="button" class="btn btn-success px-4 py-2 fw-semibold d-inline-flex align-items-center gap-2 shadow-sm" id="review-finalize-approve-btn"><i class="fas fa-check"></i><span>Publish Instrument</span></button>
                </div>
            </div>
        `);
        $("#review-finalize-approve-btn").on("click", () => performReviewApprove());
    } else {
        const currentReason = (typeof reviewDeclineReasonText !== 'undefined' && reviewDeclineReasonText) ? reviewDeclineReasonText : defaultDeclineEmailBody;
        container.html(`
            <div class="card border-danger border-opacity-25 bg-danger bg-opacity-10 p-4 rounded-3 mb-4 shadow-sm">
                <div class="d-flex align-items-center gap-3 mb-3 pb-3 border-bottom border-danger border-opacity-25">
                    <div class="bg-danger text-white rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 icon-circle-md"><i class="fas fa-times fs-5"></i></div>
                    <div><h5 class="fw-bold text-danger-emphasis m-0">Decline Submission</h5><p class="text-secondary small m-0">Reject and remove from pending queue.</p></div>
                </div>
                <div class="mb-4">
                    <div class="mb-3">
                        <label for="review-decline-reason" class="form-label small fw-bold text-secondary mb-1 fs-xs tracking-wide">Feedback Reason for Submitter (Optional)</label>
                        <textarea class="form-control border-secondary border-opacity-25 p-3 small bg-white text-dark" id="review-decline-reason" rows="4" placeholder="Explain why this submission was declined...">${escapeHtml(currentReason)}</textarea>
                    </div>
                    <div class="alert alert-danger bg-white border-danger border-opacity-50 text-danger-emphasis rounded-3 p-3 small mb-0">
                        <i class="fas fa-exclamation-circle me-2"></i>Declining will permanently remove this submission.
                    </div>
                </div>
                <div class="d-flex align-items-center justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-secondary px-3 py-2 fw-semibold" id="review-finalize-decline-skip-btn">Decline</button>
                    <button type="button" class="btn btn-danger px-4 py-2 fw-semibold d-inline-flex align-items-center gap-2 shadow-sm" id="review-finalize-decline-send-btn"><i class="fas fa-paper-plane"></i><span>Decline & Send Email</span></button>
                </div>
            </div>
        `);
        $("#review-decline-reason").on("input", function () { reviewDeclineReasonText = $(this).val(); });
        $("#review-finalize-decline-send-btn").on("click", () => performReviewDecline(true));
        $("#review-finalize-decline-skip-btn").on("click", () => performReviewDecline(false));
    }
};

const performReviewApprove = () => {
    const id = $("#review-id").val(), btn = $("#review-finalize-approve-btn").prop("disabled", true).html('<i class="fas fa-spinner fa-spin me-2"></i>Publishing...');
    module.ajax("approve-submission", { id: id }).then(response => {
        btn.prop("disabled", false);
        if (reviewModalObj) reviewModalObj.hide();
        if (response && response.status === 'success') {
            showToast('<i class="fas fa-check-circle text-success me-1"></i> Submission approved!');
            setTimeout(() => location.reload(), 1000);
        } else showToast(`<i class="fas fa-exclamation-triangle text-danger me-1"></i> ${response ? response.message : 'Error.'}`);
    }).catch(() => {
        btn.prop("disabled", false);
        if (reviewModalObj) reviewModalObj.hide();
        showToast('<i class="fas fa-exclamation-triangle text-danger me-1"></i> Error approving submission.');
    });
};

const performReviewDecline = (sendEmail) => {
    const id = $("#review-id").val(), reason = $("#review-decline-reason").val(), btn = (sendEmail ? $("#review-finalize-decline-send-btn") : $("#review-finalize-decline-skip-btn")).prop("disabled", true);
    module.ajax("decline-submission", { id, send_email: sendEmail, reason }).then(response => {
        btn.prop("disabled", false);
        if (reviewModalObj) reviewModalObj.hide();
        if (response && response.status === 'success') {
            showToast('<i class="fas fa-trash-alt text-danger me-1"></i> Submission declined.');
            setTimeout(() => location.reload(), 1000);
        } else showToast(`<i class="fas fa-exclamation-triangle text-danger me-1"></i> ${response ? response.message : 'Error.'}`);
    }).catch(() => {
        btn.prop("disabled", false);
        if (reviewModalObj) reviewModalObj.hide();
        showToast('<i class="fas fa-exclamation-triangle text-danger me-1"></i> Error declining submission.');
    });
};


const showToast = (htmlMessage) => {
    const toast = $("#admin-toast");
    toast.find("#toast-message").html(htmlMessage);
    toast.addClass("show");
    setTimeout(() => toast.removeClass("show"), 4000);
};

const renderSettingsCategories = () => {
    const listContainer = $("#settings-categories-list").empty();
    if (!configuredCategories || configuredCategories.length === 0) return listContainer.html('<span class="text-muted small">No categories configured.</span>');
    configuredCategories.forEach((cat) => {
        listContainer.append(`
            <span class="badge bg-white border text-dark p-2 d-inline-flex align-items-center gap-1.5 shadow-sm fs-8">
                ${escapeHtml(cat)}
                <i class="fas fa-times text-muted cursor-pointer ms-1 hover-danger" onclick="removeSettingCategory('${escapeHtml(cat)}')" title="Remove Category"></i>
            </span>
        `);
    });
};

$("#add-category-btn").on("click", () => {
    const input = $("#new-category-input"), val = input.val().trim();
    if (!val) return;
    if (configuredCategories.some(c => c.toLowerCase() === val.toLowerCase())) return showToast(`<i class="fas fa-exclamation-circle text-warning me-1"></i> Category "${escapeHtml(val)}" exists.`);
    configuredCategories.push(val);
    input.val("");
    renderSettingsCategories();
});

$("#new-category-input").on("keypress", (e) => {
    if (e.which === 13) { e.preventDefault(); $("#add-category-btn").click(); }
});

const removeSettingCategory = (catName) => {
    configuredCategories = configuredCategories.filter(c => c !== catName);
    renderSettingsCategories();
};
window.removeSettingCategory = removeSettingCategory;

const renderSettingsAlertEmails = () => {
    const listContainer = $("#settings-emails-list").empty();
    if (!configuredAlertEmails || configuredAlertEmails.length === 0) return listContainer.html('<span class="text-muted small">No alert emails configured. Notifications disabled.</span>');
    configuredAlertEmails.forEach((email) => {
        listContainer.append(`
            <span class="badge bg-white border text-dark p-2 d-inline-flex align-items-center gap-1.5 shadow-sm fs-8">
                <i class="fas fa-envelope text-secondary me-1 fs-7"></i>${escapeHtml(email)}
                <i class="fas fa-times text-muted cursor-pointer ms-1 hover-danger" onclick="removeSettingAlertEmail('${escapeHtml(email)}')" title="Remove Email"></i>
            </span>
        `);
    });
};

$("#add-alert-email-btn").on("click", () => {
    const input = $("#new-alert-email-input"), val = input.val().trim();
    if (!val) return;
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) return showToast(`<i class="fas fa-exclamation-circle text-warning me-1"></i> Enter a valid email.`);
    if (configuredAlertEmails.some(e => e.toLowerCase() === val.toLowerCase())) return showToast(`<i class="fas fa-exclamation-circle text-warning me-1"></i> Email "${escapeHtml(val)}" is already in list.`);
    configuredAlertEmails.push(val);
    input.val("");
    renderSettingsAlertEmails();
});

$("#new-alert-email-input").on("keypress", (e) => {
    if (e.which === 13) { e.preventDefault(); $("#add-alert-email-btn").click(); }
});

const removeSettingAlertEmail = (emailStr) => {
    configuredAlertEmails = configuredAlertEmails.filter(e => e !== emailStr);
    renderSettingsAlertEmails();
};
window.removeSettingAlertEmail = removeSettingAlertEmail;

const renderSettingsTrustedUsers = () => {
    const listContainer = $("#settings-trusted-users-list").empty();
    if (!configuredTrustedUsers || configuredTrustedUsers.length === 0) return listContainer.html('<span class="text-muted small">No trusted users configured. Standard moderation applies to non-admins.</span>');
    configuredTrustedUsers.forEach((user) => {
        listContainer.append(`
            <span class="badge bg-white border text-dark p-2 d-inline-flex align-items-center gap-1.5 shadow-sm fs-8">
                <i class="fas fa-user-check text-primary me-1 fs-7"></i>${escapeHtml(user)}
                <i class="fas fa-times text-muted cursor-pointer ms-1 hover-danger" onclick="removeSettingTrustedUser('${escapeHtml(user)}')" title="Remove User"></i>
            </span>
        `);
    });
};

$("#add-trusted-user-btn").on("click", () => {
    const input = $("#new-trusted-user-input"), val = input.val().trim();
    if (!val) return;
    if (configuredTrustedUsers.some(u => u.toLowerCase() === val.toLowerCase())) return showToast(`<i class="fas fa-exclamation-circle text-warning me-1"></i> User "${escapeHtml(val)}" is already in list.`);
    configuredTrustedUsers.push(val);
    input.val("");
    renderSettingsTrustedUsers();
});

$("#new-trusted-user-input").on("keypress", (e) => {
    if (e.which === 13) { e.preventDefault(); $("#add-trusted-user-btn").click(); }
});

const removeSettingTrustedUser = (userStr) => {
    configuredTrustedUsers = configuredTrustedUsers.filter(u => u !== userStr);
    renderSettingsTrustedUsers();
};
window.removeSettingTrustedUser = removeSettingTrustedUser;

renderSettingsCategories();
renderSettingsAlertEmails();
renderSettingsTrustedUsers();

$("#settings-form").on("submit", function (e) {
    e.preventDefault();
    const btn = $("#save-settings-btn").prop("disabled", true).html('<i class="fas fa-spinner fa-spin me-2"></i>Saving Settings...');
    const payload = {
        show_demo_instruments: $("#setting-show-demo").is(":checked"),
        enable_public_page: $("#setting-enable-public").is(":checked"),
        enable_project_by_default: $("#setting-enable-by-default").is(":checked"),
        hide_from_non_admins: $("#setting-hide-from-non-admins").is(":checked"),
        public_library_title: $("#setting-public-title").val().trim(),
        public_library_blurb: $("#setting-public-blurb").val().trim(),
        internal_library_title: $("#setting-internal-title").val().trim(),
        internal_library_blurb: $("#setting-internal-blurb").val().trim(),
        categories: configuredCategories,
        submission_alert_emails: configuredAlertEmails,
        trusted_users: configuredTrustedUsers,
        default_decline_email_body: $("#setting-decline-email-body").val().trim(),
        allow_owner_direct_update: $("#setting-allow-owner-bypass").is(":checked"),
        sender_email_address: $("#setting-sender-email").val().trim(),
        popular_calculation_method: $("#setting-popular-method").val()
    };
    module.ajax("save-settings", payload).then((response) => {
        btn.prop("disabled", false).html('<i class="fas fa-save me-2"></i>Save Module Settings');
        if (response && response.status === 'success') {
            showToast('<i class="fas fa-check-circle text-success me-1"></i> Module settings saved!');
            setTimeout(() => location.reload(), 1000);
        } else showToast('<i class="fas fa-exclamation-triangle text-danger me-1"></i> ' + (response ? response.message : 'Failed to save settings.'));
    }).catch(() => {
        btn.prop("disabled", false).html('<i class="fas fa-save me-2"></i>Save Module Settings');
        showToast('<i class="fas fa-exclamation-triangle text-danger me-1"></i> Error saving module settings.');
    });
});

$('button[data-bs-toggle="tab"]').on('shown.bs.tab', (e) => {
    const isSettings = $(e.target).attr('id') === 'settings-tab';
    $("#admin-search").prop("disabled", isSettings);
    $("#admin-search-wrapper").toggleClass("disabled-wrapper", isSettings);
    $("#save-settings-btn-container").toggleClass("d-none", !isSettings);
});

window.downloadMockVersion = (instId, versionStr) => downloadMockVersion(instId, versionStr, [...activeInstruments, ...pendingSubmissions], showToast);
window.openEditModal = openEditModal;
window.toggleHideInstrument = toggleHideInstrument;
window.openPreview = openPreview;
window.openReviewModal = openReviewModal;
window.switchReviewDecisionTab = switchReviewDecisionTab;
window.goToReviewStep = goToReviewStep;

$("#active-count").text(activeInstruments.length);
$("#pending-badge").text(pendingSubmissions.length);
renderSettingsCategories();
renderSettingsAlertEmails();
triggerCurrentFilter();
