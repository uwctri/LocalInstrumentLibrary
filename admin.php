<?php

/** @var \UWMadison\LocalInstrumentLibrary\LocalInstrumentLibrary $module */

// Protect page from non-admin users (ControlCenter/header.php automatically does this, but it's good practice)
if (!SUPER_USER) {
    print "<div class='red'>Access Denied</div>";
    return;
}

// Include REDCap Control Center Header
require_once APP_PATH_DOCROOT . 'ControlCenter/header.php';

// Load mock data
require_once __DIR__ . '/dummy_data.php';
require_once __DIR__ . '/dummy_submissions.php';

// Helper to compare semantic versions
if (!function_exists('compareAdminVersions')) {
    function compareAdminVersions($v1, $v2)
    {
        $parts1 = explode('.', $v1);
        $parts2 = explode('.', $v2);
        for ($i = 0; $i < max(count($parts1), count($parts2)); $i++) {
            $p1 = isset($parts1[$i]) ? (int)$parts1[$i] : 0;
            $p2 = isset($parts2[$i]) ? (int)$parts2[$i] : 0;
            if ($p1 > $p2) return 1;
            if ($p1 < $p2) return -1;
        }
        return 0;
    }
}

// Process instruments array to determine latest version dynamically
foreach ($instruments as &$inst) {
    $latest = "0.0.0";
    if (isset($inst['versions']) && is_array($inst['versions'])) {
        foreach ($inst['versions'] as $vObj) {
            $v = $vObj['version'];
            if (compareAdminVersions($v, $latest) > 0) {
                $latest = $v;
            }
        }
    }
    $inst['version'] = 'v' . $latest;
    $inst['status'] = 'active'; // Add initial status
}
unset($inst);

// Retrieve categories from system settings
$configured_categories = $module->getSystemSetting('categories');
$raw_categories = [];

if (empty($configured_categories)) {
    $raw_categories = [
        ["name" => "Cardiology"],
        ["name" => "Clinical"],
        ["name" => "Demographics"],
        ["name" => "Mental Health"],
        ["name" => "Neurology"],
        ["name" => "Oncology"],
        ["name" => "Patient Feedback"],
        ["name" => "Pediatrics"],
        ["name" => "Screening"]
    ];
} else {
    foreach ($configured_categories as $catName) {
        $name = trim($catName);
        if (!empty($name)) {
            $raw_categories[] = ["name" => $name];
        }
    }
}

// Pastel category tag color configurations
$nice_colors = [
    ["bg" => "#eff6ff", "text" => "#1e40af"],
    ["bg" => "#f0fdf4", "text" => "#166534"],
    ["bg" => "#faf5ff", "text" => "#6b21a8"],
    ["bg" => "#fff7ed", "text" => "#9a3412"],
    ["bg" => "#fff1f2", "text" => "#9f1239"],
    ["bg" => "#f0fdfa", "text" => "#0f766e"],
    ["bg" => "#f0f9ff", "text" => "#075985"],
    ["bg" => "#fdf2f8", "text" => "#9d174d"],
    ["bg" => "#fefce8", "text" => "#854d0e"],
    ["bg" => "#f1f5f9", "text" => "#334155"]
];

$categories_list = [];
foreach ($raw_categories as $index => $cat) {
    $color_index = $index % count($nice_colors);
    $color = $nice_colors[$color_index];
    $name = $cat['name'];

    $categories_list[] = [
        "name" => $name,
        "class" => "category-" . strtolower(str_replace(' ', '', $name)),
        "bg" => $color['bg'],
        "text" => $color['text']
    ];
}

// Format CSS for dynamic categories
$dynamic_category_styles = implode(array_map(function ($cat) {
    return sprintf(
        "#local-instrument-lib-admin-container .%s,\n#preview-modal .%s,\n#edit-modal .%s {\n    background-color: %s !important;\n    color: %s !important;\n    border-color: %s !important;\n}\n\n",
        $cat['class'],
        $cat['class'],
        $cat['class'],
        $cat['bg'],
        $cat['text'],
        $cat['bg']
    );
}, $categories_list));
?>

<!-- Include Google Fonts and FontAwesome for premium design -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= $module->getUrl('style.css'); ?>">

<style id="dynamic-admin-styles">
    <?= $dynamic_category_styles ?>

    /* Force full-width container overrides on REDCap layout */
    #center,
    #pagecontainer,
    .col-md-9,
    #sub-viewport,
    .viewport {
        max-width: 100% !important;
        width: 100% !important;
    }

    #local-instrument-lib-admin-container {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        max-width: 100% !important;
        width: 100% !important;
        margin: 0 !important;
        box-sizing: border-box;
    }

    #local-instrument-lib-admin-container .admin-header {
        background: linear-gradient(135deg, #4f46e5 0%, #06b6d4 100%);
        border-radius: 0.5rem;
    }

    /* Tabs styling overrides */
    #local-instrument-lib-admin-container .nav-tabs-custom {
        border-bottom: 2px solid #cbd5e1;
    }

    #local-instrument-lib-admin-container .nav-tabs-custom .nav-link {
        border: none !important;
        color: #64748b;
        font-weight: 600;
        padding: 0.8rem 1.5rem;
        position: relative;
        transition: color 0.2s;
        box-shadow: none !important;
        outline: none !important;
    }

    #local-instrument-lib-admin-container .nav-tabs-custom .nav-link:hover,
    #local-instrument-lib-admin-container .nav-tabs-custom .nav-link:focus,
    #local-instrument-lib-admin-container .nav-tabs-custom .nav-link:active {
        color: #0f172a;
        background: none !important;
        border: none !important;
        outline: none !important;
        box-shadow: none !important;
    }

    #local-instrument-lib-admin-container .nav-tabs-custom .nav-link.active {
        color: #4f46e5;
        background: none !important;
        border: none !important;
        outline: none !important;
        box-shadow: none !important;
    }

    #local-instrument-lib-admin-container .nav-tabs-custom .nav-link.active::after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 0;
        right: 0;
        height: 2px;
        background-color: #4f46e5;
    }

    #adminTabsContent {
        padding: 0 !important;
        border: none !important;
    }

    /* Premium Custom Table styling */
    .table-custom {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }

    .table-custom th {
        background-color: #f8fafc;
        color: #475569;
        font-weight: 600;
        font-size: 0.82rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 1rem 1.25rem;
        border-bottom: 1px solid #e2e8f0;
    }

    .table-custom td {
        padding: 1.25rem;
        border-bottom: 1px solid #e2e8f0;
        color: #334155;
        font-size: 0.88rem;
        vertical-align: middle;
    }

    .table-custom tbody tr {
        transition: background-color 0.2s;
    }

    .table-custom tbody tr:hover {
        background-color: #f8fafc;
    }

    .table-custom tr.row-hidden {
        background-color: #f8fafc;
        opacity: 0.75;
    }

    /* Status Badges */
    .badge-status {
        font-size: 0.75rem;
        font-weight: 600;
        padding: 0.25rem 0.6rem;
        border-radius: 9999px;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
    }

    .badge-status-active {
        background-color: #dcfce7;
        color: #15803d;
    }

    .badge-status-hidden {
        background-color: #fee2e2;
        color: #b91c1c;
    }

    /* Submission Cards */
    .submission-card {
        border: 1px solid #e2e8f0;
        border-radius: 0.5rem;
        background-color: #fff;
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .submission-card:hover {
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.05);
    }

    .submission-card-header {
        background-color: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        padding: 1rem 1.25rem;
        border-top-left-radius: 0.5rem;
        border-top-right-radius: 0.5rem;
    }

    .submission-card-body {
        padding: 1.25rem;
    }

    /* Floating Toast Notification */
    .admin-toast {
        position: fixed;
        bottom: 2rem;
        right: 2rem;
        background-color: #1e293b;
        color: #fff;
        padding: 1rem 1.5rem;
        border-radius: 0.5rem;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        display: flex;
        align-items: center;
        gap: 0.75rem;
        z-index: 9999;
        transform: translateY(100px);
        opacity: 0;
        transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.3s ease;
    }

    .admin-toast.show {
        transform: translateY(0);
        opacity: 1;
    }

    .cursor-pointer {
        cursor: pointer;
    }

    .dropdown-version-item:hover {
        background-color: #f1f5f9;
    }

    .btn-circle-action {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0;
        transition: all 0.2s;
    }

    .btn-circle-action:hover {
        background-color: #e2e8f0;
    }

    /* Custom Category Checkbox styling (matches submission form size & padding) */
    .edit-cat-checkbox:checked+label {
        background-color: #eff6ff !important;
        border-color: #3b82f6 !important;
        color: #1d4ed8 !important;
        box-shadow: 0 2px 8px rgba(59, 130, 246, 0.08) !important;
    }
</style>

<div id="local-instrument-lib-admin-container" class="container-fluid px-0">
    <!-- Header Banner -->
    <div class="admin-header p-4 p-md-5 mb-4 text-white position-relative shadow-sm">
        <h2 class="display-6 fw-bold mb-2" style="font-family: 'Outfit', sans-serif;">Instrument Library Administration</h2>
        <p class="lead mb-0 text-white-50" style="max-width: 850px; font-size: 1.02rem;">
            Manage active instruments, hide or unhide library forms, edit metadata details, review clinical metrics, and moderate incoming user submissions.
        </p>
    </div>

    <!-- Search bar (outside the cards, flat on background) -->
    <div class="mb-4">
        <div class="position-relative">
            <i class="fas fa-search position-absolute start-0 top-50 translate-middle-y ms-3 text-muted"></i>
            <input type="text" id="admin-search" class="form-control ps-5 py-2 border-secondary border-opacity-25 bg-light shadow-sm" placeholder="Search instruments by ID, title, author, submitter, or description...">
        </div>
    </div>

    <!-- Navigation Tabs (flat on background, controls inner content) -->
    <div class="mb-4">
        <ul class="nav nav-tabs nav-tabs-custom mb-3 border-0" id="adminTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active d-flex align-items-center" id="active-tab" data-bs-toggle="tab" data-bs-target="#active-pane" type="button" role="tab" aria-controls="active-pane" aria-selected="true">
                    <i class="fas fa-table me-2"></i>Active Library (<span id="active-count">0</span>)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link d-flex align-items-center" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending-pane" type="button" role="tab" aria-controls="pending-pane" aria-selected="false">
                    <i class="fas fa-history me-2"></i>Pending Submissions
                    <span class="badge bg-danger ms-2" id="pending-badge" style="font-size: 0.82rem; font-weight: 600; padding: 0.25rem 0.55rem; border-radius: 9999px;">0</span>
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="adminTabsContent">
            <!-- Active Library Tab -->
            <div class="tab-pane fade show active" id="active-pane" role="tabpanel" aria-labelledby="active-tab">
                <!-- Inner table card wrapper restored, table wraps in its own clean card box -->
                <div class="border rounded-3 overflow-hidden bg-white shadow-sm">
                    <table class="table-custom" id="active-library-table">
                        <thead>
                            <tr>
                                <th style="width: 32%;">Instrument Details</th>
                                <th style="width: 20%;">Categories</th>
                                <th style="width: 18%;">Versions & Downloads</th>
                                <th style="width: 13%;">Fields & Time</th>
                                <th style="width: 17%;">Author</th>
                                <th style="width: 10%; text-align: center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="active-library-tbody">
                            <!-- Populated dynamically via JavaScript -->
                        </tbody>
                    </table>
                </div>
                <div class="text-center py-5 text-secondary d-none bg-white border rounded-3 shadow-sm" id="active-empty-state">
                    <i class="fas fa-folder-open display-4 mb-3 text-muted opacity-50"></i>
                    <h3 class="h5 text-dark fw-semibold mb-1">No instruments found</h3>
                    <p class="text-muted small">Try refining your search term.</p>
                </div>
            </div>

            <!-- Pending Submissions Tab -->
            <div class="tab-pane fade" id="pending-pane" role="tabpanel" aria-labelledby="pending-tab">
                <div class="row g-4" id="pending-submissions-container">
                    <!-- Populated dynamically via JavaScript -->
                </div>
                <div class="text-center py-5 text-secondary d-none bg-white border rounded-3 shadow-sm" id="pending-empty-state">
                    <i class="fas fa-check-circle display-4 mb-3 text-success opacity-75"></i>
                    <h3 class="h5 text-dark fw-semibold mb-1">All submissions moderated</h3>
                    <p class="text-muted small mb-0">No new submissions require review at this time.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Preview Modal -->
<div class="modal fade" id="preview-modal" tabindex="-1" aria-labelledby="preview-modal-label" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header bg-light border-bottom px-4 py-3">
                <h3 class="modal-title m-0 h5 fw-bold text-dark" id="preview-modal-label">
                    <i class="fas fa-search me-2 text-primary"></i>
                    Instrument Preview
                </h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="container-fluid px-0">
                    <div class="row g-0">
                        <!-- Sidebar Details -->
                        <div class="col-12 col-md-4 bg-light border-end p-4 d-flex flex-column gap-3">
                            <div>
                                <h4 class="text-secondary text-uppercase fw-bold mb-1" style="font-size: 0.72rem; letter-spacing: 0.05em;">Categories</h4>
                                <div id="modal-categories" class="d-flex flex-wrap gap-1 mt-1"></div>
                            </div>
                            <div>
                                <h4 class="text-secondary text-uppercase fw-bold mb-1" style="font-size: 0.72rem; letter-spacing: 0.05em;">Version</h4>
                                <span id="modal-version" class="badge bg-white border text-secondary fw-semibold">v1.0.0</span>
                            </div>
                            <div>
                                <h4 class="text-secondary text-uppercase fw-bold mb-1" style="font-size: 0.72rem; letter-spacing: 0.05em;">Author</h4>
                                <p id="modal-author" class="fw-semibold text-dark mb-0" style="font-size: 0.9rem;"></p>
                                <p id="modal-author-email" class="text-muted small mb-0"></p>
                            </div>
                            <div>
                                <h4 class="text-secondary text-uppercase fw-bold mb-1" style="font-size: 0.72rem; letter-spacing: 0.05em;">Description</h4>
                                <p id="modal-desc" class="text-muted mb-0" style="font-size: 0.85rem; line-height: 1.5;"></p>
                            </div>
                            <div class="mt-3">
                                <a class="btn btn-outline-secondary w-100 py-2.5 fw-semibold d-inline-flex align-items-center justify-content-center gap-2" id="modal-download-btn" href="#">
                                    <i class="fas fa-file-archive me-2"></i>Download ZIP Package
                                </a>
                            </div>
                        </div>

                        <!-- Fields List -->
                        <div class="col-12 col-md-8 p-4 d-flex flex-column" style="max-height: 600px; overflow-y: auto;">
                            <h4 class="text-dark fw-bold mb-3 h6" id="modal-fields-heading">Instrument Fields</h4>
                            <div class="border rounded-3 bg-white shadow-inner">
                                <ul class="list-group list-group-flush" id="modal-field-list" style="margin: 0; padding: 0;">
                                    <!-- Fields populated dynamically via JS -->
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Metadata Modal -->
<div class="modal fade" id="edit-modal" tabindex="-1" aria-labelledby="edit-modal-label" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header bg-light border-bottom px-4 py-3">
                <h3 class="modal-title m-0 h5 fw-bold text-dark" id="edit-modal-label">
                    <i class="fas fa-edit me-2 text-primary"></i>
                    Edit Instrument Metadata
                </h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form id="edit-instrument-form" novalidate>
                    <input type="hidden" id="edit-id">
                    <input type="hidden" id="edit-type">

                    <div class="mb-3">
                        <label for="edit-title" class="form-label small fw-bold text-secondary mb-1">Instrument Title</label>
                        <input type="text" class="form-control border-secondary border-opacity-25 py-2" id="edit-title" required>
                        <div class="invalid-feedback">Please enter a title.</div>
                    </div>

                    <div class="mb-3">
                        <label for="edit-desc" class="form-label small fw-bold text-secondary mb-1">Description</label>
                        <textarea class="form-control border-secondary border-opacity-25 py-2" id="edit-desc" rows="4" required></textarea>
                        <div class="invalid-feedback">Please enter a description.</div>
                    </div>

                    <!-- Categories Checklist -->
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-secondary mb-2 d-block">Categories</label>
                        <div class="p-3 border border-secondary border-opacity-25 rounded-3 bg-light bg-opacity-25">
                            <div class="row g-2" id="edit-categories-list">
                                <?php foreach ($categories_list as $cat): ?>
                                    <?php $cleanId = 'edit-cat-' . strtolower(str_replace(' ', '', $cat['name'])); ?>
                                    <div class="col-6 col-md-4">
                                        <input class="edit-cat-checkbox" type="checkbox" value="<?= htmlspecialchars($cat['name']); ?>" id="<?= $cleanId; ?>" autocomplete="off" style="display: none !important;">
                                        <label class="btn btn-outline-secondary w-100 py-2 px-3 text-start d-flex align-items-center justify-content-between rounded-3 cursor-pointer" for="<?= $cleanId; ?>">
                                            <span class="small fw-semibold text-dark"><?= htmlspecialchars($cat['name']); ?></span>
                                            <span class="checkbox-box" style="font-size: 1.15rem;">
                                                <i class="far fa-square text-muted unchecked-icon"></i>
                                                <i class="fas fa-check-square checked-icon d-none" style="color: #3b82f6 !important;"></i>
                                            </span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="invalid-feedback d-none" id="edit-categories-invalid" style="margin-top: 0.5rem;">Please select at least one category.</div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="edit-author-name" class="form-label small fw-bold text-secondary mb-1">Author Name</label>
                            <input type="text" class="form-control border-secondary border-opacity-25 py-2" id="edit-author-name" required>
                            <div class="invalid-feedback">Please enter the author's name.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="edit-author-email" class="form-label small fw-bold text-secondary mb-1">Author Email</label>
                            <input type="email" class="form-control border-secondary border-opacity-25 py-2" id="edit-author-email" required>
                            <div class="invalid-feedback">Please enter a valid email address.</div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="edit-time" class="form-label small fw-bold text-secondary mb-1">Est. Completion Time</label>
                            <input type="text" class="form-control border-secondary border-opacity-25 py-2" id="edit-time" placeholder="e.g. 5 mins" required>
                            <div class="invalid-feedback">Please enter estimated time.</div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 border-top pt-3 mt-4">
                        <button type="button" class="btn btn-light border fw-semibold py-2 px-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary fw-semibold py-2 px-4" id="save-edit-btn">
                            <i class="fas fa-save me-2"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Floating Notification Toast -->
<div class="admin-toast" id="admin-toast">
    <i class="fas fa-check-circle text-success fs-5" id="toast-icon"></i>
    <span id="toast-message" class="fw-semibold">Action completed successfully!</span>
</div>

<script type="text/javascript">
    // Dump initial PHP arrays into JS state variables
    const categoryMapping = <?= json_encode($categories_list); ?>;
    let activeInstruments = <?= json_encode(array_values($instruments)); ?>;
    let pendingSubmissions = <?= json_encode(array_values($dummy_submissions)); ?>;
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
</script>

<?php
// Include REDCap Control Center Footer
require_once APP_PATH_DOCROOT . 'ControlCenter/footer.php';
?>