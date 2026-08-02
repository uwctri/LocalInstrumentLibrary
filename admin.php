<?php

/** @var \UWMadison\LocalInstrumentLibrary\LocalInstrumentLibrary $module */

require_once APP_PATH_DOCROOT . 'ControlCenter/header.php';

$instruments = $module->getInstruments(false, true);
$dummy_submissions = $module->getPendingSubmissions();

$categories_list = $module->getCategoriesList();
$module->initializeJavascriptModuleObject();
$settings = $module->getSystemSettings();
$default_sender_email = $module->getSenderEmailAddress();
$current_username = defined('USERID') ? USERID : '';

$dynamic_category_styles = $module->renderCategoryCSS($categories_list, ['#local-instrument-lib-admin-container', '#preview-modal', '#review-modal', '#edit-modal']);
$dynamic_field_type_styles = $module->renderFieldTypeCSS(array_merge($instruments, $dummy_submissions), ['#local-instrument-lib-admin-container', '#preview-modal', '#review-modal', '#edit-modal']);
?>

<script defer src="<?= $module->getUrl('alpine.min.js'); ?>"></script>
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
            <i class="fas fa-external-link-alt"></i><span>View Public Library</span>
        </a>
    </div>

    <div class="mb-4" id="admin-search-wrapper">
        <div class="position-relative">
            <i class="fas fa-search position-absolute start-0 top-50 translate-middle-y ms-3 text-muted" id="admin-search-icon"></i>
            <input type="text" id="admin-search" class="form-control ps-5 py-2 shadow-sm" placeholder="Search instruments by ID, title, author, submitter, or description...">
        </div>
    </div>

    <div class="d-flex align-items-center justify-content-between mb-4 admin-tab-bar position-relative">
        <ul class="nav nav-tabs nav-tabs-custom border-0 m-0" id="adminTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active d-flex align-items-center" id="active-tab" data-bs-toggle="tab" data-bs-target="#active-pane" type="button" role="tab" aria-controls="active-pane" aria-selected="true">
                    <i class="fas fa-table me-2"></i>Active Library (<span id="active-count"><?= count($instruments); ?></span>)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link d-flex align-items-center" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending-pane" type="button" role="tab" aria-controls="pending-pane" aria-selected="false">
                    <i class="fas fa-history me-2"></i>Pending Submissions
                    <span class="badge bg-danger ms-2 fs-7 align-self-center py-1 px-2 rounded-pill fw-semibold" id="pending-badge"><?= count($dummy_submissions); ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link d-flex align-items-center" id="settings-tab" data-bs-toggle="tab" data-bs-target="#settings-pane" type="button" role="tab" aria-controls="settings-pane" aria-selected="false">
                    <i class="fas fa-sliders-h me-2"></i>Module Settings
                </button>
            </li>
        </ul>

        <div id="save-settings-btn-container" class="d-none">
            <button type="submit" form="settings-form" class="btn btn-primary px-3 py-1.5 fw-semibold d-inline-flex align-items-center gap-2 shadow-sm fs-88 lh-sm" id="save-settings-btn">
                <i class="fas fa-save"></i><span>Save Settings</span>
            </button>
        </div>
    </div>

    <div class="tab-content" id="adminTabsContent">
        <div class="tab-pane fade show active" id="active-pane" role="tabpanel" aria-labelledby="active-tab">
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
                            <th class="text-center" style="width: 12%;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="active-library-tbody"></tbody>
                </table>
            </div>
            <div class="text-center py-5 px-4 text-secondary d-none bg-white border rounded-3 shadow-sm d-flex flex-column align-items-center justify-content-center min-h-280" id="active-empty-state">
                <div class="rounded-circle bg-light p-3 mb-3 d-inline-flex align-items-center justify-content-center icon-circle-lg">
                    <i class="fas fa-folder-open text-secondary opacity-75 fs-2"></i>
                </div>
                <h3 class="h5 text-dark fw-bold mb-1">No instruments found</h3>
                <p class="text-muted small mb-0 max-w-400">Try refining your search term or adjusting filter options.</p>
            </div>
        </div>

        <div class="tab-pane fade" id="pending-pane" role="tabpanel" aria-labelledby="pending-tab">
            <div class="border rounded-3 overflow-hidden bg-white shadow-sm">
                <table class="table-custom w-100" id="pending-submissions-table">
                    <thead>
                        <tr>
                            <th style="width: 40%;">Instrument Details</th>
                            <th style="width: 25%;">Categories</th>
                            <th style="width: 10%;">Version</th>
                            <th style="width: 10%;">Fields</th>
                            <th class="text-center" style="width: 15%;">Review</th>
                        </tr>
                    </thead>
                    <tbody id="pending-submissions-tbody"></tbody>
                </table>
            </div>
            <div class="text-center py-5 px-4 text-secondary d-none bg-white border rounded-3 shadow-sm d-flex flex-column align-items-center justify-content-center min-h-280" id="pending-empty-state">
                <div class="rounded-circle bg-success bg-opacity-10 p-3 mb-3 d-inline-flex align-items-center justify-content-center icon-circle-lg">
                    <i class="fas fa-check-circle text-success fs-2"></i>
                </div>
                <h3 class="h5 text-dark fw-bold mb-1">All submissions moderated</h3>
                <p class="text-muted small mb-0 max-w-400">No new submissions require review at this time.</p>
            </div>
        </div>

        <div class="tab-pane fade" id="settings-pane" role="tabpanel" aria-labelledby="settings-tab">
            <form id="settings-form" novalidate>
                <div class="row g-4">
                    <!-- Card 1: Library Categories -->
                    <div class="col-12 col-lg-6">
                        <div class="bg-white border rounded-3 p-4 shadow-sm h-100 d-flex flex-column justify-content-between">
                            <div>
                                <h5 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                                    <i class="fas fa-tags text-primary"></i><span>Library Categories</span>
                                </h5>
                                <p class="text-muted small mb-3">Manage categories available for classifying data collection instruments across the library.</p>
                                <div class="mb-3">
                                    <div class="input-group">
                                        <input type="text" class="form-control border-secondary border-opacity-25 py-2 fs-88" id="new-category-input" placeholder="Add new category...">
                                        <button class="btn btn-outline-primary fw-semibold px-3 fs-88" type="button" id="add-category-btn"><i class="fas fa-plus me-1"></i>Add Category</button>
                                    </div>
                                </div>
                            </div>
                            <div class="border rounded-3 p-3 bg-light bg-opacity-50 flex-fill min-h-110">
                                <div class="d-flex flex-wrap gap-2" id="settings-categories-list"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Card 2: System Controls & Rules -->
                    <div class="col-12 col-lg-6">
                        <div class="bg-white border rounded-3 p-4 shadow-sm h-100 d-flex flex-column justify-content-between">
                            <div>
                                <h5 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                                    <i class="fas fa-sliders-h text-primary"></i><span>System Controls & Rules</span>
                                </h5>
                                <p class="text-muted small mb-3">Configure global behaviors, visibility permissions, and automated module rules.</p>
                                <div class="form-check form-switch p-0 d-flex align-items-center justify-content-between gap-3 mb-3 pb-3 border-bottom">
                                    <div>
                                        <label for="setting-show-demo" class="fw-semibold text-dark mb-1 d-flex align-items-center gap-2 cursor-pointer">
                                            <i class="fas fa-flask text-primary opacity-85 fs-9"></i><span>Show Demo Instruments</span>
                                        </label>
                                        <p class="text-muted small mb-0">Include pre-configured demonstration instruments in the library.</p>
                                    </div>
                                    <input class="form-check-input flex-shrink-0 cursor-pointer m-0 switch-lg" type="checkbox" id="setting-show-demo" <?= $settings['show_demo_instruments']['value'] ? 'checked' : ''; ?>>
                                </div>

                                <div class="form-check form-switch p-0 d-flex align-items-center justify-content-between gap-3 mb-3 pb-3 border-bottom">
                                    <div>
                                        <label for="setting-enable-by-default" class="fw-semibold text-dark mb-1 d-flex align-items-center gap-2 cursor-pointer">
                                            <i class="fas fa-power-off text-primary opacity-85 fs-9"></i><span>Enable Module on All Projects</span>
                                        </label>
                                        <p class="text-muted small mb-0">Off by default to allow for configuration after install</p>
                                    </div>
                                    <input class="form-check-input flex-shrink-0 cursor-pointer m-0 switch-lg" type="checkbox" id="setting-enable-by-default" <?= ($settings['enabled']['value'] ?? false) ? 'checked' : ''; ?>>
                                </div>

                                <div class="form-check form-switch p-0 d-flex align-items-center justify-content-between gap-3 mb-3 pb-3 border-bottom">
                                    <div>
                                        <label for="setting-hide-from-non-admins" class="fw-semibold text-dark mb-1 d-flex align-items-center gap-2 cursor-pointer">
                                            <i class="fas fa-eye-slash text-primary opacity-85 fs-9"></i><span>Hide Module from Non-Admins</span>
                                        </label>
                                        <p class="text-muted small mb-0">Hide this module from non-admins in the list of enabled modules on each project.</p>
                                    </div>
                                    <input class="form-check-input flex-shrink-0 cursor-pointer m-0 switch-lg" type="checkbox" id="setting-hide-from-non-admins" <?= ($settings['reserved-hide-from-non-admins-in-project-list']['value'] ?? false) ? 'checked' : ''; ?>>
                                </div>

                                <div class="form-check form-switch p-0 d-flex align-items-center justify-content-between gap-3 mb-3 pb-3 border-bottom">
                                    <div>
                                        <label for="setting-allow-owner-bypass" class="fw-semibold text-dark mb-1 d-flex align-items-center gap-2 cursor-pointer">
                                            <i class="fas fa-user-shield text-primary opacity-85 fs-9"></i><span>Allow Owner Direct Updates</span>
                                        </label>
                                        <p class="text-muted small mb-0">Allow instrument owners to update versions without admin review.</p>
                                    </div>
                                    <input class="form-check-input flex-shrink-0 cursor-pointer m-0 switch-lg" type="checkbox" id="setting-allow-owner-bypass" <?= $settings['allow_owner_direct_update']['value'] ? 'checked' : ''; ?>>
                                </div>

                                <div>
                                    <label for="setting-popular-method" class="fw-semibold text-dark mb-1 d-flex align-items-center gap-2">
                                        <i class="fas fa-fire text-danger opacity-85 fs-9"></i><span>"Popular" Tag Calculation Rule</span>
                                    </label>
                                    <p class="text-muted small mb-2">Select how the Popular badge is assigned to instruments, or disable it.</p>
                                    <select class="form-select border-secondary border-opacity-25 py-2 fs-88 w-100" id="setting-popular-method">
                                        <option value="disabled" <?= $settings['popular_calculation_method']['value'] === 'disabled' ? 'selected' : ''; ?>>Disabled (Hide Popular Tag)</option>
                                        <option value="relative_60" <?= $settings['popular_calculation_method']['value'] === 'relative_60' ? 'selected' : ''; ?>>Relative (60%+ max installs, min 5)</option>
                                        <option value="min_5" <?= $settings['popular_calculation_method']['value'] === 'min_5' ? 'selected' : ''; ?>>Fixed Threshold (5+ Installs)</option>
                                        <option value="min_10" <?= $settings['popular_calculation_method']['value'] === 'min_10' ? 'selected' : ''; ?>>Fixed Threshold (10+ Installs)</option>
                                        <option value="top_3" <?= $settings['popular_calculation_method']['value'] === 'top_3' ? 'selected' : ''; ?>>Top 3 Most Installed</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Card 3: Sender Email Address -->
                    <div class="col-12 col-lg-6">
                        <div class="bg-white border rounded-3 p-4 shadow-sm h-100">
                            <h5 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                                <i class="fas fa-paper-plane text-primary"></i><span>Sender Email Address</span>
                            </h5>
                            <p class="text-muted small mb-3">The "From" email address used for approval, decline, and alert notifications sent by this module.</p>
                            <div>
                                <label for="setting-sender-email" class="form-label small fw-bold text-secondary mb-1">Sender Email Address</label>
                                <input type="email" class="form-control border-secondary border-opacity-25 py-2 fs-88" id="setting-sender-email" value="<?= htmlspecialchars($settings['sender_email_address']['value'], ENT_QUOTES); ?>" placeholder="<?= htmlspecialchars($default_sender_email, ENT_QUOTES); ?>">
                                <div class="form-text text-muted small">If left blank, defaults to REDCap Administrator Email (<?= htmlspecialchars($default_sender_email, ENT_QUOTES); ?>).</div>
                            </div>
                        </div>
                    </div>

                    <!-- Card 4: Default Decline Email Language -->
                    <div class="col-12 col-lg-6">
                        <div class="bg-white border rounded-3 p-4 shadow-sm h-100">
                            <h5 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                                <i class="fas fa-comment-dots text-primary"></i><span>Default Decline Email Language</span>
                            </h5>
                            <p class="text-muted small mb-3">Pre-populated feedback message template sent to submitters when their instrument submission is declined.</p>
                            <div>
                                <label for="setting-decline-email-body" class="form-label small fw-bold text-secondary mb-1">Decline Email Feedback Body</label>
                                <textarea class="form-control border-secondary border-opacity-25 py-2 fs-88" id="setting-decline-email-body" rows="3" required><?= htmlspecialchars($settings['default_decline_email_body']['value']); ?></textarea>
                                <div class="invalid-feedback">Please enter default decline email language.</div>
                            </div>
                        </div>
                    </div>

                    <!-- Card 5: Submission Alert Notifications -->
                    <div class="col-12">
                        <div class="bg-white border rounded-3 p-4 shadow-sm">
                            <h5 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                                <i class="fas fa-envelope text-primary"></i><span>Submission Alert Notifications</span>
                            </h5>
                            <p class="text-muted small mb-3">Email addresses listed below will be notified whenever a user submits a new instrument or update.</p>
                            <div class="input-group mb-3 max-w-500">
                                <input type="email" class="form-control border-secondary border-opacity-25 py-2 fs-88" id="new-alert-email-input" placeholder="example@<?= $module->getContactEmailDomain(); ?>">
                                <button class="btn btn-outline-primary fw-semibold px-3 fs-88" type="button" id="add-alert-email-btn"><i class="fas fa-plus me-1"></i>Add Email</button>
                            </div>
                            <div class="border rounded-3 p-3 bg-light bg-opacity-50 min-h-110">
                                <div class="d-flex flex-wrap gap-2" id="settings-emails-list"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Card 6: Public Page Access & Branding -->
                    <div class="col-12 col-lg-6">
                        <div class="bg-white border rounded-3 p-4 shadow-sm h-100">
                            <h5 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                                <i class="fas fa-globe text-primary"></i><span>Public Page Access & Branding</span>
                            </h5>
                            <p class="text-muted small mb-3">Configure title, description, and access controls for the public instrument library page.</p>
                            <div class="form-check form-switch p-0 d-flex align-items-center justify-content-between gap-3 mb-4 pb-3 border-bottom">
                                <div>
                                    <label for="setting-enable-public" class="fw-semibold text-dark mb-1 d-block cursor-pointer">Enable Public Page</label>
                                    <p class="text-muted small mb-0">Allow unauthenticated users to access the public instrument library.</p>
                                </div>
                                <input class="form-check-input flex-shrink-0 cursor-pointer m-0 switch-lg" type="checkbox" id="setting-enable-public" <?= $settings['enable_public_page']['value'] ? 'checked' : ''; ?>>
                            </div>
                            <div class="mb-3">
                                <label for="setting-public-title" class="form-label small fw-bold text-secondary mb-1">Public Page Title</label>
                                <input type="text" class="form-control border-secondary border-opacity-25 py-2 fs-88" id="setting-public-title" value="<?= htmlspecialchars($settings['public_library_title']['value'], ENT_QUOTES); ?>" required>
                                <div class="invalid-feedback">Please enter a public page title.</div>
                            </div>
                            <div>
                                <label for="setting-public-blurb" class="form-label small fw-bold text-secondary mb-1">Public Page Description</label>
                                <textarea class="form-control border-secondary border-opacity-25 py-2 fs-88" id="setting-public-blurb" rows="3" required><?= htmlspecialchars($settings['public_library_blurb']['value']); ?></textarea>
                                <div class="invalid-feedback">Please enter a public page description.</div>
                            </div>
                        </div>
                    </div>

                    <!-- Card 7: Internal Page Branding -->
                    <div class="col-12 col-lg-6">
                        <div class="bg-white border rounded-3 p-4 shadow-sm h-100">
                            <h5 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                                <i class="fas fa-layer-group text-primary"></i><span>Internal Page Branding</span>
                            </h5>
                            <p class="text-muted small mb-3">Configure title and description displayed on the internal REDCap library page.</p>
                            <div class="mb-3">
                                <label for="setting-internal-title" class="form-label small fw-bold text-secondary mb-1">Internal Page Title</label>
                                <input type="text" class="form-control border-secondary border-opacity-25 py-2 fs-88" id="setting-internal-title" value="<?= htmlspecialchars($settings['internal_library_title']['value'], ENT_QUOTES); ?>" required>
                                <div class="invalid-feedback">Please enter an internal page title.</div>
                            </div>
                            <div>
                                <label for="setting-internal-blurb" class="form-label small fw-bold text-secondary mb-1">Internal Page Description</label>
                                <textarea class="form-control border-secondary border-opacity-25 py-2 fs-88" id="setting-internal-blurb" rows="3" required><?= htmlspecialchars($settings['internal_library_blurb']['value']); ?></textarea>
                                <div class="invalid-feedback">Please enter an internal page description.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="preview-modal" tabindex="-1" aria-labelledby="preview-modal-label" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header bg-light border-bottom px-4 py-3">
                <h3 class="modal-title m-0 h5 fw-bold text-dark" id="preview-modal-label">
                    <i class="fas fa-search me-2 text-primary"></i>Instrument Preview
                </h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="container-fluid px-0">
                    <div class="row g-0">
                        <div class="col-12 col-md-4 bg-light border-end p-4 d-flex flex-column gap-3">
                            <div>
                                <h4 class="text-secondary text-uppercase fw-bold mb-1 fs-xs tracking-wide">Categories</h4>
                                <div id="modal-categories" class="d-flex flex-wrap gap-1 mt-1"></div>
                            </div>
                            <div>
                                <h4 class="text-secondary text-uppercase fw-bold mb-1 fs-xs tracking-wide">Version</h4>
                                <span id="modal-version" class="badge bg-white border text-secondary fw-semibold">v1.0.0</span>
                            </div>
                            <div>
                                <h4 class="text-secondary text-uppercase fw-bold mb-1 fs-xs tracking-wide">Author</h4>
                                <p id="modal-author" class="fw-semibold text-dark mb-0 fs-9"></p>
                                <p id="modal-author-email" class="text-muted small mb-0"></p>
                            </div>
                            <div>
                                <h4 class="text-secondary text-uppercase fw-bold mb-1 fs-xs tracking-wide">Description</h4>
                                <p id="modal-desc" class="text-muted mb-0 fs-85 lh-base"></p>
                            </div>
                            <div class="mt-2 border-top pt-3">
                                <h4 class="text-secondary text-uppercase fw-bold mb-2 fs-xs tracking-wide">Version Downloads</h4>
                                <div id="modal-version-downloads" class="d-flex flex-column gap-2"></div>
                            </div>
                        </div>
                        <div class="col-12 col-md-8 p-4 d-flex flex-column overflow-auto max-h-600">
                            <h4 class="text-dark fw-bold mb-3 h6" id="modal-fields-heading">Instrument Fields</h4>
                            <div class="border rounded-3 bg-white shadow-inner">
                                <ul class="list-group list-group-flush m-0 p-0" id="modal-field-list"></ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="edit-modal" tabindex="-1" aria-labelledby="edit-modal-label" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header bg-light border-bottom px-4 py-3">
                <h3 class="modal-title m-0 h5 fw-bold text-dark" id="edit-modal-label">
                    <i class="fas fa-edit me-2 text-primary"></i>Edit Instrument Metadata
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

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary mb-1">Categories (Select at least one)</label>
                        <div class="p-3 border border-secondary border-opacity-25 rounded-3 bg-light bg-opacity-50">
                            <div class="row g-2" id="edit-categories-container">
                                <?php foreach ($categories_list as $cat): ?>
                                    <?php $cleanId = 'edit-cat-' . strtolower(str_replace(' ', '', $cat['name'])); ?>
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
                        <div class="form-text text-muted small">The owner can submit version updates that bypass admin approval (when enabled).</div>
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

<div class="modal fade" id="review-modal" tabindex="-1" aria-labelledby="review-modal-label" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3 overflow-hidden">
            <div class="modal-header bg-light border-bottom px-4 py-3">
                <h3 class="modal-title m-0 h5 fw-bold text-dark" id="review-modal-label">
                    <i class="fas fa-clipboard-check me-2 text-primary"></i>Review Instrument Submission
                </h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <input type="hidden" id="review-id">
                <div class="container-fluid px-0">
                    <div class="row g-0">
                        <div class="col-12 col-md-4 bg-light border-end p-4 d-flex flex-column gap-3 overflow-auto max-h-620">
                            <div>
                                <h4 class="text-secondary text-uppercase fw-bold mb-1 fs-xs tracking-wide">Instrument Title</h4>
                                <p id="review-title" class="fw-bold text-dark mb-0 fs-95"></p>
                            </div>
                            <div>
                                <h4 class="text-secondary text-uppercase fw-bold mb-1 fs-xs tracking-wide">Categories</h4>
                                <div id="review-categories" class="d-flex flex-wrap gap-1 mt-1"></div>
                            </div>
                            <div class="d-flex gap-3">
                                <div>
                                    <h4 class="text-secondary text-uppercase fw-bold mb-1 fs-xs tracking-wide">Version</h4>
                                    <span id="review-version" class="badge bg-white border text-secondary fw-semibold">v1.0.0</span>
                                </div>
                                <div>
                                    <h4 class="text-secondary text-uppercase fw-bold mb-1 fs-xs tracking-wide">Submitted By</h4>
                                    <span id="review-submitter" class="fw-semibold text-dark small"></span>
                                </div>
                            </div>
                            <div>
                                <h4 class="text-secondary text-uppercase fw-bold mb-1 fs-xs tracking-wide">Author Contact</h4>
                                <p id="review-author" class="fw-semibold text-dark mb-0 fs-85"></p>
                            </div>
                            <div>
                                <h4 class="text-secondary text-uppercase fw-bold mb-1 fs-xs tracking-wide">Description</h4>
                                <p id="review-desc" class="text-muted mb-0 fs-85 lh-base"></p>
                            </div>

                            <div class="mt-1 border-top pt-3">
                                <h4 class="text-secondary text-uppercase fw-bold mb-2 fs-xs tracking-wide">Instrument Package Download</h4>
                                <div id="review-version-downloads" class="d-flex flex-column gap-2"></div>
                            </div>

                            <div class="mt-2 border-top pt-3" id="review-moderation-card">
                                <h4 class="text-secondary text-uppercase fw-bold mb-2 fs-xs tracking-wide">Moderation Decision</h4>
                                <div class="btn-group w-100 mb-3 p-1 bg-white border rounded-3 shadow-sm" role="group">
                                    <button type="button" class="btn btn-sm py-1.5 fw-bold active btn-success text-white" id="review-tab-approve" onclick="switchReviewDecisionTab('approve')">
                                        <i class="fas fa-check me-1"></i>Approve
                                    </button>
                                    <button type="button" class="btn btn-sm py-1.5 fw-bold btn-outline-danger" id="review-tab-decline" onclick="switchReviewDecisionTab('decline')">
                                        <i class="fas fa-times me-1"></i>Decline
                                    </button>
                                </div>
                                <div id="review-step1-nav">
                                    <button type="button" class="btn btn-primary w-100 py-2 fw-semibold d-inline-flex align-items-center justify-content-center gap-2 shadow-sm" onclick="goToReviewStep(2)">
                                        <span>Review</span><i class="fas fa-arrow-right ms-1"></i>
                                    </button>
                                </div>
                                <div id="review-step2-nav" class="d-none">
                                    <button type="button" class="btn btn-outline-secondary w-100 py-2 fw-semibold d-inline-flex align-items-center justify-content-center gap-2 shadow-sm" onclick="goToReviewStep(1)">
                                        <i class="fas fa-arrow-left me-1.5"></i><span>Back</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-md-8 p-4 d-flex flex-column overflow-auto max-h-620">
                            <div id="review-step1-right">
                                <h4 class="text-dark fw-bold mb-3 h6" id="review-fields-heading">Instrument Fields Preview</h4>
                                <div class="border rounded-3 bg-white shadow-inner">
                                    <ul class="list-group list-group-flush m-0 p-0" id="review-field-list"></ul>
                                </div>
                            </div>

                            <div id="review-step2-right" class="d-none">
                                <h4 class="text-dark fw-bold mb-3 h6">Finalize Moderation Decision</h4>
                                <div id="review-finalize-container"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="admin-toast" id="admin-toast">
    <span id="toast-message" class="fw-semibold">Action completed successfully!</span>
</div>

<script type="text/javascript">
    const module = <?= $module->getJavascriptModuleObjectName(); ?>;
    const downloadPageUrl = <?= json_encode($module->getUrl('download.php')); ?>;
    const categoryMapping = <?= json_encode($categories_list); ?>;
    let configuredCategories = <?= json_encode(array_values(array_map(fn($c) => $c['name'], $categories_list))); ?>;
    let configuredAlertEmails = <?= json_encode(array_values($settings['submission_alert_emails']['value'])); ?>;
    let defaultDeclineEmailBody = <?= json_encode($settings['default_decline_email_body']['value']); ?>;
    const currentUsername = <?= json_encode($current_username); ?>;
    const allowOwnerBypass = <?= json_encode($settings['allow_owner_direct_update']['value']); ?>;
    let activeInstruments = <?= json_encode(array_values($instruments)); ?>;
    let pendingSubmissions = <?= json_encode(array_values($dummy_submissions)); ?>;
</script>
<script type="module" src="<?= $module->getUrl('admin.js'); ?>"></script>

<?php require_once APP_PATH_DOCROOT . 'ControlCenter/footer.php'; ?>