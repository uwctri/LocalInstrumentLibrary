<?php

/** @var \UWMadison\LocalInstrumentLibrary\LocalInstrumentLibrary $module */

// Include REDCap Control Center Header
require_once APP_PATH_DOCROOT . 'ControlCenter/header.php';

$instruments = $module->getInstruments(false);
$dummy_submissions = $module->getPendingSubmissions();

foreach ($instruments as &$inst) {
    $inst['status'] = 'active'; // Add initial status
}
unset($inst);

$categories_list = $module->getCategoriesList();
$module->initializeJavascriptModuleObject();
$setting_show_demo = (bool)$module->getSystemSetting('show_demo_instruments');
$setting_enable_public = $module->getSystemSetting('enable_public_page');
if ($setting_enable_public === null) $setting_enable_public = true;
$setting_enable_public = (bool)$setting_enable_public;
$setting_public_title = (string)$module->getSystemSetting('public_library_title');
$setting_public_blurb = (string)$module->getSystemSetting('public_library_blurb');
$setting_internal_title = (string)$module->getSystemSetting('internal_library_title');
$setting_internal_blurb = (string)$module->getSystemSetting('internal_library_blurb');
$setting_alert_emails = (array)($module->getSystemSetting('submission_alert_emails') ?? []);
$setting_decline_email_body = (string)$module->getSystemSetting('default_decline_email_body');
$setting_allow_owner_bypass = (bool)$module->getSystemSetting('allow_owner_direct_update');
$current_username = defined('USERID') ? USERID : '';

$dynamic_category_styles = $module->renderCategoryCSS($categories_list, [
    '#local-instrument-lib-admin-container',
    '#preview-modal',
    '#review-modal',
    '#edit-modal'
]);

$all_admin_instruments = array_merge($instruments, $dummy_submissions);
$dynamic_field_type_styles = $module->renderFieldTypeCSS($all_admin_instruments, [
    '#local-instrument-lib-admin-container',
    '#preview-modal',
    '#review-modal',
    '#edit-modal'
]);
?>

<!-- Load Local Styles and Fonts -->
<link rel="stylesheet" href="<?= $module->getUrl('admin.css'); ?>">

<style id="dynamic-admin-styles">
    <?= $dynamic_category_styles ?><?= $dynamic_field_type_styles ?>
</style>

<div id="local-instrument-lib-admin-container" class="container-fluid px-0">
    <?php
    $public_library_url = $module->getUrl('index.php', true);
    if (preg_match('#/redcap_v\d+\.\d+\.\d+/#i', $public_library_url)) {
        $public_library_url = preg_replace('#/redcap_v\d+\.\d+\.\d+/ExternalModules/\?#i', '/api/?type=module&', $public_library_url);
        $public_library_url = preg_replace('#/redcap_v\d+\.\d+\.\d+/#i', '/', $public_library_url);
    }
    ?>
    <div class="d-flex justify-content-between align-items-center mt-0 mb-4">
        <h4 class="m-0"><i class="fas fa-cogs me-2"></i><span>Instrument Library Administration</span></h4>
        <a href="<?= htmlspecialchars($public_library_url, ENT_QUOTES); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-public-link btn-sm px-3 py-2 fw-semibold d-inline-flex align-items-center gap-2 shadow-sm">
            <i class="fas fa-external-link-alt"></i>
            <span>View Public Library</span>
        </a>
    </div>

    <!-- Search bar (outside the cards, flat on background) -->
    <div class="mb-4" id="admin-search-wrapper">
        <div class="position-relative">
            <i class="fas fa-search position-absolute start-0 top-50 translate-middle-y ms-3 text-muted" id="admin-search-icon"></i>
            <input type="text" id="admin-search" class="form-control ps-5 py-2 shadow-sm" placeholder="Search instruments by ID, title, author, submitter, or description...">
        </div>
    </div>

    <!-- Navigation Tabs (flat on background, controls inner content) -->
    <div class="mb-4">
        <ul class="nav nav-tabs nav-tabs-custom mb-3 border-0" id="adminTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active d-flex align-items-center" id="active-tab" data-bs-toggle="tab" data-bs-target="#active-pane" type="button" role="tab" aria-controls="active-pane" aria-selected="true">
                    <i class="fas fa-table me-2"></i>Active Library (<span id="active-count"><?= count($instruments); ?></span>)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link d-flex align-items-center" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending-pane" type="button" role="tab" aria-controls="pending-pane" aria-selected="false">
                    <i class="fas fa-history me-2"></i>Pending Submissions
                    <span class="badge bg-danger ms-2" id="pending-badge" style="font-size: 0.82rem; font-weight: 600; padding: 0.25rem 0.55rem; border-radius: 9999px;"><?= count($dummy_submissions); ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link d-flex align-items-center" id="settings-tab" data-bs-toggle="tab" data-bs-target="#settings-pane" type="button" role="tab" aria-controls="settings-pane" aria-selected="false">
                    <i class="fas fa-sliders-h me-2"></i>Module Settings
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
                                <th style="width: 18%;">Categories</th>
                                <th style="width: 10%;">Version</th>
                                <th style="width: 10%;">Fields</th>
                                <th style="width: 13%;">Author</th>
                                <th style="width: 10%;">Owner</th>
                                <th style="width: 12%; text-align: center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="active-library-tbody">
                            <!-- Populated dynamically via JavaScript -->
                        </tbody>
                    </table>
                </div>
                <div class="text-center py-5 px-4 text-secondary d-none bg-white border rounded-3 shadow-sm d-flex flex-column align-items-center justify-content-center" id="active-empty-state" style="min-height: 280px;">
                    <div class="rounded-circle bg-light p-3 mb-3 d-inline-flex align-items-center justify-content-center" style="width: 70px; height: 70px;">
                        <i class="fas fa-folder-open text-secondary opacity-75" style="font-size: 2.2rem;"></i>
                    </div>
                    <h3 class="h5 text-dark fw-bold mb-1">No instruments found</h3>
                    <p class="text-muted small mb-0" style="max-width: 400px;">Try refining your search term or adjusting filter options.</p>
                </div>
            </div>

            <!-- Pending Submissions Tab -->
            <div class="tab-pane fade" id="pending-pane" role="tabpanel" aria-labelledby="pending-tab">
                <div class="border rounded-3 overflow-hidden bg-white shadow-sm">
                    <table class="table-custom" id="pending-submissions-table" style="width: 100%;">
                        <thead>
                            <tr>
                                <th style="width: 40%;">Instrument Details</th>
                                <th style="width: 25%;">Categories</th>
                                <th style="width: 10%;">Version</th>
                                <th style="width: 10%;">Fields</th>
                                <th style="width: 15%; text-align: center;">Review</th>
                            </tr>
                        </thead>
                        <tbody id="pending-submissions-tbody">
                            <!-- Populated dynamically via JavaScript -->
                        </tbody>
                    </table>
                </div>
                <div class="text-center py-5 px-4 text-secondary d-none bg-white border rounded-3 shadow-sm d-flex flex-column align-items-center justify-content-center" id="pending-empty-state" style="min-height: 280px;">
                    <div class="rounded-circle bg-success bg-opacity-10 p-3 mb-3 d-inline-flex align-items-center justify-content-center" style="width: 70px; height: 70px;">
                        <i class="fas fa-check-circle text-success" style="font-size: 2.2rem;"></i>
                    </div>
                    <h3 class="h5 text-dark fw-bold mb-1">All submissions moderated</h3>
                    <p class="text-muted small mb-0" style="max-width: 400px;">No new submissions require review at this time.</p>
                </div>
            </div>

            <!-- Module Settings Tab -->
            <div class="tab-pane fade" id="settings-pane" role="tabpanel" aria-labelledby="settings-tab">
                <form id="settings-form" novalidate>
                    <div class="row g-4">
                        <!-- Left Column: Settings Cards -->
                        <div class="col-12 col-lg-7 d-flex flex-column gap-4">
                            <!-- Demo Instruments Switch -->
                            <div class="bg-white border rounded-3 p-4 shadow-sm">
                                <h5 class="fw-bold text-dark mb-3 d-flex align-items-center gap-2">
                                    <i class="fas fa-flask text-primary"></i>
                                    <span>Demonstration Data</span>
                                </h5>
                                <div class="form-check form-switch p-0 d-flex align-items-center justify-content-between gap-3">
                                    <div>
                                        <label for="setting-show-demo" class="fw-semibold text-dark mb-1 d-block cursor-pointer">Show Demo Instruments</label>
                                        <p class="text-muted small mb-0">Include pre-configured demonstration instruments and mock pending submissions in the library.</p>
                                    </div>
                                    <input class="form-check-input flex-shrink-0 cursor-pointer m-0" type="checkbox" id="setting-show-demo" style="width: 2.75em; height: 1.5em;" <?= $setting_show_demo ? 'checked' : ''; ?>>
                                </div>
                            </div>

                            <!-- Owner Direct Update Switch -->
                            <div class="bg-white border rounded-3 p-4 shadow-sm">
                                <h5 class="fw-bold text-dark mb-3 d-flex align-items-center gap-2">
                                    <i class="fas fa-user-shield text-primary"></i>
                                    <span>Owner Permissions</span>
                                </h5>
                                <div class="form-check form-switch p-0 d-flex align-items-center justify-content-between gap-3">
                                    <div>
                                        <label for="setting-allow-owner-bypass" class="fw-semibold text-dark mb-1 d-block cursor-pointer">Allow Owner Direct Updates</label>
                                        <p class="text-muted small mb-0">When enabled, the recorded owner of an instrument can submit version updates that bypass admin approval and go live immediately.</p>
                                    </div>
                                    <input class="form-check-input flex-shrink-0 cursor-pointer m-0" type="checkbox" id="setting-allow-owner-bypass" style="width: 2.75em; height: 1.5em;" <?= $setting_allow_owner_bypass ? 'checked' : ''; ?>>
                                </div>
                            </div>

                            <!-- Submission Alert Emails Card -->
                            <div class="bg-white border rounded-3 p-4 shadow-sm">
                                <h5 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                                    <i class="fas fa-envelope text-primary"></i>
                                    <span>Submission Alert Notifications</span>
                                </h5>
                                <p class="text-muted small mb-3">Email addresses listed below will be notified whenever a user submits a new instrument or update.</p>

                                <div class="input-group mb-3">
                                    <input type="email" class="form-control border-secondary border-opacity-25" id="new-alert-email-input" placeholder="admin@example.edu">
                                    <button class="btn btn-outline-primary fw-semibold px-3" type="button" id="add-alert-email-btn">
                                        <i class="fas fa-plus me-1"></i>Add Email
                                    </button>
                                </div>

                                <div class="border rounded-3 p-3 bg-light bg-opacity-50" style="min-height: 80px;">
                                    <div class="d-flex flex-wrap gap-2" id="settings-emails-list">
                                        <!-- Rendered dynamically -->
                                    </div>
                                </div>
                            </div>

                            <!-- Public Page Branding Card -->
                            <div class="bg-white border rounded-3 p-4 shadow-sm">
                                <h5 class="fw-bold text-dark mb-3 d-flex align-items-center gap-2">
                                    <i class="fas fa-globe text-primary"></i>
                                    <span>Public Page Access & Branding (Unauthenticated View)</span>
                                </h5>
                                <div class="form-check form-switch p-0 d-flex align-items-center justify-content-between gap-3 mb-4 pb-3 border-bottom">
                                    <div>
                                        <label for="setting-enable-public" class="fw-semibold text-dark mb-1 d-block cursor-pointer">Enable Public Page</label>
                                        <p class="text-muted small mb-0">Allow unauthenticated users to access the public instrument library without logging in.</p>
                                    </div>
                                    <input class="form-check-input flex-shrink-0 cursor-pointer m-0" type="checkbox" id="setting-enable-public" style="width: 2.75em; height: 1.5em;" <?= $setting_enable_public ? 'checked' : ''; ?>>
                                </div>
                                <div class="mb-3">
                                    <label for="setting-public-title" class="form-label small fw-bold text-secondary mb-1">Public Page Title</label>
                                    <input type="text" class="form-control border-secondary border-opacity-25 py-2" id="setting-public-title" value="<?= htmlspecialchars($setting_public_title, ENT_QUOTES); ?>" required>
                                    <div class="invalid-feedback">Please enter a public page title.</div>
                                </div>
                                <div>
                                    <label for="setting-public-blurb" class="form-label small fw-bold text-secondary mb-1">Public Page Blurb / Description</label>
                                    <textarea class="form-control border-secondary border-opacity-25" id="setting-public-blurb" rows="3" required><?= htmlspecialchars($setting_public_blurb); ?></textarea>
                                    <div class="invalid-feedback">Please enter a public page description.</div>
                                </div>
                            </div>

                            <!-- Internal Page Branding Card -->
                            <div class="bg-white border rounded-3 p-4 shadow-sm">
                                <h5 class="fw-bold text-dark mb-3 d-flex align-items-center gap-2">
                                    <i class="fas fa-layer-group text-primary"></i>
                                    <span>Internal Page Branding (REDCap Project View)</span>
                                </h5>
                                <div class="mb-3">
                                    <label for="setting-internal-title" class="form-label small fw-bold text-secondary mb-1">Internal Page Title</label>
                                    <input type="text" class="form-control border-secondary border-opacity-25 py-2" id="setting-internal-title" value="<?= htmlspecialchars($setting_internal_title, ENT_QUOTES); ?>" required>
                                    <div class="invalid-feedback">Please enter an internal page title.</div>
                                </div>
                                <div>
                                    <label for="setting-internal-blurb" class="form-label small fw-bold text-secondary mb-1">Internal Page Blurb / Description</label>
                                    <textarea class="form-control border-secondary border-opacity-25" id="setting-internal-blurb" rows="3" required><?= htmlspecialchars($setting_internal_blurb); ?></textarea>
                                    <div class="invalid-feedback">Please enter an internal page description.</div>
                                </div>
                            </div>

                            <!-- Default Decline Email Language Card -->
                            <div class="bg-white border rounded-3 p-4 shadow-sm">
                                <h5 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                                    <i class="fas fa-comment-dots text-primary"></i>
                                    <span>Default Decline Email Language</span>
                                </h5>
                                <p class="text-muted small mb-3">Pre-populated feedback message sent to submitters when their instrument submission is declined.</p>

                                <div>
                                    <label for="setting-decline-email-body" class="form-label small fw-bold text-secondary mb-1">Decline Email Feedback Body</label>
                                    <textarea class="form-control border-secondary border-opacity-25" id="setting-decline-email-body" rows="4" required><?= htmlspecialchars($setting_decline_email_body); ?></textarea>
                                    <div class="invalid-feedback">Please enter default decline email language.</div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Category Management & Save Action -->
                        <div class="col-12 col-lg-5 d-flex flex-column gap-4">
                            <!-- Category Manager Card -->
                            <div class="bg-white border rounded-3 p-4 shadow-sm flex-fill d-flex flex-column">
                                <h5 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                                    <i class="fas fa-tags text-primary"></i>
                                    <span>Library Categories</span>
                                </h5>
                                <p class="text-muted small mb-3">Manage categories available for classifying data collection instruments.</p>

                                <div class="input-group mb-3">
                                    <input type="text" class="form-control border-secondary border-opacity-25" id="new-category-input" placeholder="Add new category...">
                                    <button class="btn btn-outline-primary fw-semibold px-3" type="button" id="add-category-btn">
                                        <i class="fas fa-plus me-1"></i>Add
                                    </button>
                                </div>

                                <div class="border rounded-3 p-3 bg-light bg-opacity-50 flex-fill overflow-auto" style="min-height: 200px; max-height: 320px;">
                                    <div class="d-flex flex-wrap gap-2" id="settings-categories-list">
                                        <!-- Categories rendered dynamically -->
                                    </div>
                                </div>
                            </div>

                            <!-- Save Action Button -->
                            <div class="bg-white border rounded-3 p-4 shadow-sm">
                                <button type="submit" class="btn btn-primary w-100 py-2.5 fw-semibold d-inline-flex align-items-center justify-content-center gap-2 shadow-sm" id="save-settings-btn">
                                    <i class="fas fa-save"></i>
                                    <span>Save Module Settings</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
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
                            <div class="mt-2 border-top pt-3">
                                <h4 class="text-secondary text-uppercase fw-bold mb-2" style="font-size: 0.72rem; letter-spacing: 0.05em;">Version Downloads</h4>
                                <div id="modal-version-downloads" class="d-flex flex-column gap-2">
                                    <!-- Populated dynamically via JS -->
                                </div>
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
                        <div class="invalid-feedback">Please enter an instrument title.</div>
                    </div>

                    <div class="mb-3">
                        <label for="edit-desc" class="form-label small fw-bold text-secondary mb-1">Description / Purpose</label>
                        <textarea class="form-control border-secondary border-opacity-25" id="edit-desc" rows="3" required></textarea>
                        <div class="invalid-feedback">Please enter a description.</div>
                    </div>

                    <!-- Category Checkbox List (Reverted Grid Format) -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary mb-1">Categories (Select at least one)</label>
                        <div class="p-3 border border-secondary border-opacity-25 rounded-3 bg-light bg-opacity-50">
                            <div class="row g-2" id="edit-categories-container">
                                <?php foreach ($categories_list as $cat): ?>
                                    <?php
                                    $cleanId = 'edit-cat-' . strtolower(str_replace(' ', '', $cat['name']));
                                    ?>
                                    <div class="col-6 col-md-4">
                                        <input class="btn-check edit-cat-checkbox" type="checkbox" value="<?= htmlspecialchars($cat['name']); ?>" id="<?= $cleanId; ?>" autocomplete="off">
                                        <label class="btn btn-outline-secondary w-100 py-2 px-3 text-start d-flex align-items-center justify-content-between rounded-3 cursor-pointer" for="<?= $cleanId; ?>">
                                            <span class="small fw-semibold text-dark"><?= htmlspecialchars($cat['name']); ?></span>
                                            <span class="checkbox-box">
                                                <i class="far fa-square text-muted unchecked-icon"></i>
                                                <i class="fas fa-check-square text-primary checked-icon d-none"></i>
                                            </span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="text-danger small d-none mt-1" id="edit-categories-invalid">Please select at least one category.</div>
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

                    <div class="mb-3">
                        <label for="edit-owner" class="form-label small fw-bold text-secondary mb-1">Owner (REDCap Username)</label>
                        <input type="text" class="form-control border-secondary border-opacity-25 py-2 font-monospace" id="edit-owner" placeholder="redcap_username">
                        <div class="form-text text-muted small">The owner can submit version updates that bypass admin approval (when the system setting is enabled).</div>
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

<!-- Review Submission Modal (2-Step Review & Moderation Workbench) -->
<div class="modal fade" id="review-modal" tabindex="-1" aria-labelledby="review-modal-label" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3 overflow-hidden">
            <div class="modal-header bg-light border-bottom px-4 py-3">
                <h3 class="modal-title m-0 h5 fw-bold text-dark" id="review-modal-label">
                    <i class="fas fa-clipboard-check me-2 text-primary"></i>
                    Review Instrument Submission
                </h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <input type="hidden" id="review-id">
                <div class="container-fluid px-0">
                    <div class="row g-0">
                        <!-- Left Sidebar: Details & Moderation Action -->
                        <div class="col-12 col-md-4 bg-light border-end p-4 d-flex flex-column gap-3" style="max-height: 620px; overflow-y: auto;">
                            <div>
                                <h4 class="text-secondary text-uppercase fw-bold mb-1" style="font-size: 0.72rem; letter-spacing: 0.05em;">Instrument Title</h4>
                                <p id="review-title" class="fw-bold text-dark mb-0" style="font-size: 0.95rem;"></p>
                            </div>
                            <div>
                                <h4 class="text-secondary text-uppercase fw-bold mb-1" style="font-size: 0.72rem; letter-spacing: 0.05em;">Categories</h4>
                                <div id="review-categories" class="d-flex flex-wrap gap-1 mt-1"></div>
                            </div>
                            <div class="d-flex gap-3">
                                <div>
                                    <h4 class="text-secondary text-uppercase fw-bold mb-1" style="font-size: 0.72rem; letter-spacing: 0.05em;">Version</h4>
                                    <span id="review-version" class="badge bg-white border text-secondary fw-semibold">v1.0.0</span>
                                </div>
                                <div>
                                    <h4 class="text-secondary text-uppercase fw-bold mb-1" style="font-size: 0.72rem; letter-spacing: 0.05em;">Submitted By</h4>
                                    <span id="review-submitter" class="fw-semibold text-dark small"></span>
                                </div>
                            </div>
                            <div>
                                <h4 class="text-secondary text-uppercase fw-bold mb-1" style="font-size: 0.72rem; letter-spacing: 0.05em;">Author Contact</h4>
                                <p id="review-author" class="fw-semibold text-dark mb-0" style="font-size: 0.85rem;"></p>
                            </div>
                            <div>
                                <h4 class="text-secondary text-uppercase fw-bold mb-1" style="font-size: 0.72rem; letter-spacing: 0.05em;">Description</h4>
                                <p id="review-desc" class="text-muted mb-0" style="font-size: 0.85rem; line-height: 1.5;"></p>
                            </div>

                            <!-- Instrument Package Download Section -->
                            <div class="mt-1 border-top pt-3">
                                <h4 class="text-secondary text-uppercase fw-bold mb-2" style="font-size: 0.72rem; letter-spacing: 0.05em;">Instrument Package Download</h4>
                                <div id="review-version-downloads" class="d-flex flex-column gap-2">
                                    <!-- Populated dynamically via JS -->
                                </div>
                            </div>

                            <!-- Moderation Action Box -->
                            <div class="mt-2 border-top pt-3" id="review-moderation-card">
                                <h4 class="text-secondary text-uppercase fw-bold mb-2" style="font-size: 0.72rem; letter-spacing: 0.05em;">Moderation Decision</h4>

                                <div class="btn-group w-100 mb-3 p-1 bg-white border rounded-3 shadow-sm" role="group" aria-label="Moderation Decision Toggle">
                                    <button type="button" class="btn btn-sm py-1.5 fw-bold active btn-success text-white" id="review-tab-approve" onclick="switchReviewDecisionTab('approve')">
                                        <i class="fas fa-check me-1"></i>Approve
                                    </button>
                                    <button type="button" class="btn btn-sm py-1.5 fw-bold btn-outline-danger" id="review-tab-decline" onclick="switchReviewDecisionTab('decline')">
                                        <i class="fas fa-times me-1"></i>Decline
                                    </button>
                                </div>

                                <!-- Navigation Buttons -->
                                <div id="review-step1-nav">
                                    <button type="button" class="btn btn-primary w-100 py-2 fw-semibold d-inline-flex align-items-center justify-content-center gap-2 shadow-sm" onclick="goToReviewStep(2)">
                                        <span>Review</span>
                                        <i class="fas fa-arrow-right ms-1"></i>
                                    </button>
                                </div>
                                <div id="review-step2-nav" class="d-none">
                                    <button type="button" class="btn btn-outline-secondary w-100 py-2 fw-semibold d-inline-flex align-items-center justify-content-center gap-2 shadow-sm" onclick="goToReviewStep(1)">
                                        <i class="fas fa-arrow-left me-1.5"></i>
                                        <span>Back</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Right Panel: Step 1 (Fields Preview) OR Step 2 (Final Confirmation) -->
                        <div class="col-12 col-md-8 p-4 d-flex flex-column" style="max-height: 620px; overflow-y: auto;">
                            <!-- Step 1 View: Instrument Fields List -->
                            <div id="review-step1-right">
                                <h4 class="text-dark fw-bold mb-3 h6" id="review-fields-heading">Instrument Fields Preview</h4>
                                <div class="border rounded-3 bg-white shadow-inner">
                                    <ul class="list-group list-group-flush" id="review-field-list" style="margin: 0; padding: 0;">
                                        <!-- Fields populated dynamically via JS -->
                                    </ul>
                                </div>
                            </div>

                            <!-- Step 2 View: Final Confirmation & Decision Summary -->
                            <div id="review-step2-right" class="d-none">
                                <h4 class="text-dark fw-bold mb-3 h6">Finalize Moderation Decision</h4>
                                <div id="review-finalize-container">
                                    <!-- Rendered dynamically via JS when advancing to Step 2 -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Floating Notification Toast -->
<div class="admin-toast" id="admin-toast">
    <span id="toast-message" class="fw-semibold">Action completed successfully!</span>
</div>

<script type="text/javascript">
    // Dump initial PHP arrays & JSMO object into JS state variables
    const module = <?= $module->getJavascriptModuleObjectName(); ?>;
    const downloadPageUrl = <?= json_encode($module->getUrl('download.php')); ?>;
    const categoryMapping = <?= json_encode($categories_list); ?>;
    let configuredCategories = <?= json_encode(array_values(array_map(function ($c) {
                                    return $c['name'];
                                }, $categories_list))); ?>;
    let configuredAlertEmails = <?= json_encode(array_values($setting_alert_emails)); ?>;
    let defaultDeclineEmailBody = <?= json_encode($setting_decline_email_body); ?>;
    const currentUsername = <?= json_encode($current_username); ?>;
    const allowOwnerBypass = <?= json_encode($setting_allow_owner_bypass); ?>;
    let activeInstruments = <?= json_encode(array_values($instruments)); ?>;
    let pendingSubmissions = <?= json_encode(array_values($dummy_submissions)); ?>;
</script>
<?php
loadJS('webpack/js/datatables/jquery.dataTables.min.js');
loadCSS('webpack/css/datatables/jquery.dataTables.min.css', true, false, APP_PATH_WEBPACK);
?>
<script type="module" src="<?= $module->getUrl('admin.js'); ?>"></script>

<?php
// Include REDCap Control Center Footer
require_once APP_PATH_DOCROOT . 'ControlCenter/footer.php';
?>