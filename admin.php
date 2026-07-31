<?php

/** @var \UWMadison\LocalInstrumentLibrary\LocalInstrumentLibrary $module */

// Include REDCap Control Center Header
require_once APP_PATH_DOCROOT . 'ControlCenter/header.php';

$instruments = $module->getInstruments();
require_once __DIR__ . '/dummy_submissions.php';

foreach ($instruments as &$inst) {
    $inst['status'] = 'active'; // Add initial status
}
unset($inst);

// Retrieve categories from system settings
$configured_categories = $module->getSystemSetting('categories');
$raw_categories = [];

$defaults = $module->getDefaults();
$default_cats = $defaults['default_categories'] ?? [];

if (empty($configured_categories)) {
    foreach ($default_cats as $catName) {
        $raw_categories[] = ["name" => $catName];
    }
} else {
    foreach ($configured_categories as $catName) {
        $name = trim($catName);
        if (!empty($name))
            $raw_categories[] = ["name" => $name];
    }
}

// Pastel category tag color configurations
$nice_colors = $defaults['category_colors'] ?? [];

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

<!-- Load Local Styles and Fonts -->
<link rel="stylesheet" href="<?= $module->getUrl('admin.css'); ?>">

<style id="dynamic-admin-styles">
    <?= $dynamic_category_styles ?>
</style>

<div id="local-instrument-lib-admin-container" class="container-fluid px-0">
    <h4 class="mt-0 mb-4"><i class="fas fa-cogs"></i> <span>Instrument Library Administration</span></h4>

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
</script>
<script type="text/javascript" src="<?= $module->getUrl('admin.js'); ?>"></script>

<?php
// Include REDCap Control Center Footer
require_once APP_PATH_DOCROOT . 'ControlCenter/footer.php';
?>