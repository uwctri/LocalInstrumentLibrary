<?php

/** @var \UWMadison\LocalInstrumentLibrary\LocalInstrumentLibrary $module */

// Check if authenticated and in project context
$is_authenticated = defined('USERID') && USERID !== null;
$has_project = defined('PROJECT_ID') && PROJECT_ID > 0;
$is_public = !$is_authenticated || !$has_project;

if (!$is_public) {
    require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
} else {
    // Public view: Render standalone HTML page structure and load dependencies via framework helpers
?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Local Instrument Library</title>
        <?php
        $module->loadREDCapCSS();
        $module->loadREDCapJS();
        $module->loadBootstrap();
        ?>
    </head>

    <body class="bg-light">
    <?php
}

$instruments = $module->getInstruments();

$module->initializeJavascriptModuleObject();

// Retrieve the installed instruments list for this project
$installed_instruments = (!$is_public) ? ($module->getProjectSetting('installed_instruments') ?? []) : [];

// Map installed status dynamically
foreach ($instruments as &$inst) {
    $inst['installed'] = in_array($inst['id'], $installed_instruments);
}
unset($inst);

// Load configurable title and blurb from system settings
$library_title = $module->getSystemSetting($is_public ? 'public_library_title' : 'internal_library_title')
    ?: 'Local Instrument Library';
$library_blurb = $module->getSystemSetting($is_public ? 'public_library_blurb' : 'internal_library_blurb')
    ?: ($is_public
        ? 'Browse pre-approved data collection instruments. Select from validated clinical scales, standard demographics questionnaires, and specialized screeners.'
        : 'Browse and install pre-approved data collection instruments directly into your current REDCap project. Select from validated clinical scales, standard demographics questionnaires, and specialized screeners.');

// Retrieve categories from system settings configuration
$configured_categories = $module->getSystemSetting('categories');
$raw_categories = [];

$defaults = $module->getDefaults();
$default_cats = $defaults['default_categories'] ?? [];

if (empty($configured_categories)) {
    // Load default categories list from defaults.json
    foreach ($default_cats as $catName) {
        $raw_categories[] = ["name" => $catName];
    }
} else {
    // Collect non-empty category names from settings (flat array)
    foreach ($configured_categories as $catName) {
        $name = trim($catName);
        if (!empty($name))
            $raw_categories[] = ["name" => $name];
    }
}

$nice_colors = $defaults['category_colors'] ?? [];
$field_type_colors = $defaults['field_type_colors'] ?? [];

// Map index-based color overrides and generate CSS class keys dynamically
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

// Format a CSS Blob for dynamic category styles
$dynamic_category_styles = implode(array_map(function ($cat) {
    return sprintf(
        "#local-instrument-lib-container .%s,\n#preview-modal .%s {\n    background-color: %s !important;\n    color: %s !important;\n    border-color: %s !important;\n}\n\n",
        $cat['class'],
        $cat['class'],
        $cat['bg'],
        $cat['text'],
        $cat['bg']
    );
}, $categories_list));

// Collect all unique field types present in the loaded instruments
$unique_types = [];
foreach ($instruments as $inst) {
    if (isset($inst['fields']) && is_array($inst['fields']))
        foreach ($inst['fields'] as $field) {
            $type = strtolower(trim($field['type'] ?? ''));
            if (!empty($type))
                $unique_types[$type] = $type;
        }
}

// Generate field type styles dynamically, falling back to default configuration if not defined
$dynamic_field_type_styles = "";
$default_colors = $field_type_colors['default'] ?? ["bg" => "#f1f5f9", "text" => "#475569", "border" => "#cbd5e1"];

foreach ($unique_types as $type) {
    $colors = $field_type_colors[$type] ?? $default_colors;
    $dynamic_field_type_styles .= sprintf(
        "#local-instrument-lib-container .type-%s,\n#preview-modal .type-%s {\n    background-color: %s !important;\n    color: %s !important;\n    border-color: %s !important;\n    font-weight: 600 !important;\n}\n\n",
        $type,
        $type,
        $colors['bg'],
        $colors['text'],
        $colors['border']
    );
}
    ?>

    <!-- Load Local Styles and Fonts -->
    <link rel="stylesheet" href="<?= $module->getUrl('style.css'); ?>">

    <style id="dynamic-styles">
        <?= $dynamic_category_styles ?><?= $dynamic_field_type_styles ?>
    </style>

    <?php if ($is_public): ?><div class="px-4 px-md-5"><?php endif; ?>
        <div id="local-instrument-lib-container" class="container-fluid py-4 px-3 px-sm-4 bg-light border rounded-3 mt-3 shadow-sm">
            <!-- Header Banner -->
            <div class="lib-header p-4 p-md-5 mb-4 rounded-3 text-white position-relative">
                <h2 class="display-6 fw-bold mb-2" style="font-family: 'Outfit', sans-serif;"><?= htmlspecialchars($library_title) ?></h2>
                <p class="lead mb-4" style="max-width: 750px; font-size: 1.05rem;"><?= htmlspecialchars($library_blurb) ?></p>
                <div class="d-flex gap-3 flex-wrap">
                    <div class="lib-stat-badge bg-white bg-opacity-10 border border-white border-opacity-25 rounded-pill px-3 py-2 text-white" style="font-size: 0.92rem; font-weight: 500;">
                        <i class="fa-solid fa-list-ul me-2"></i>
                        <span id="stat-total" class="fw-semibold"><?= count($instruments) ?> Available Instruments</span>
                    </div>
                    <?php if (!$is_public): ?>
                        <div class="lib-stat-badge bg-white bg-opacity-10 border border-white border-opacity-25 rounded-pill px-3 py-2 text-white" style="font-size: 0.92rem; font-weight: 500;">
                            <i class="fa-solid fa-circle-check me-2"></i>
                            <span id="stat-installed" class="fw-semibold"><?= count($installed_instruments) ?> Installed in Project</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Toolbar: Search and Category Filter -->
            <div class="d-flex gap-3 align-items-center mb-4 bg-white p-3 rounded-3 border shadow-sm flex-wrap flex-md-nowrap">
                <div class="flex-grow-1" style="min-width: 250px;">
                    <div class="position-relative">
                        <i class="fa-solid fa-magnifying-glass position-absolute start-0 top-50 translate-middle-y ms-3 text-muted"></i>
                        <input type="text" id="lib-search" class="form-control ps-5 py-2 border-secondary border-opacity-25 bg-light" placeholder="Search instruments by name or description...">
                    </div>
                </div>
                <div class="flex-shrink-0" style="width: 260px; min-width: 180px;">
                    <div class="category-select-wrapper position-relative text-start">
                        <!-- Trigger Button -->
                        <div id="category-dropdown-trigger" class="form-select py-2 border-secondary border-opacity-25 bg-light fw-semibold text-secondary d-flex align-items-center justify-content-between cursor-pointer" style="cursor: pointer;">
                            <span id="selected-category-text">All Categories</span>
                        </div>

                        <!-- Hidden Input -->
                        <input type="hidden" id="lib-category-select" value="All">

                        <!-- Dropdown Menu -->
                        <div id="category-dropdown-menu" class="position-absolute start-0 w-100 bg-white border rounded-3 shadow-lg mt-1 d-none" style="z-index: 1050; max-height: 280px; display: flex; flex-direction: column;">
                            <!-- Search Input -->
                            <div class="p-2 border-bottom">
                                <div class="position-relative">
                                    <i class="fa-solid fa-magnifying-glass position-absolute start-0 top-50 translate-middle-y ms-2 text-muted" style="font-size: 0.8rem;"></i>
                                    <input type="text" id="category-search" class="form-control form-control-sm ps-4" placeholder="Search categories..." style="font-size: 0.85rem;" autocomplete="off">
                                </div>
                            </div>
                            <!-- Options List -->
                            <div class="options-list overflow-y-auto" style="max-height: 220px; overflow-y: auto;">
                                <div class="dropdown-item py-2 px-3 fw-semibold text-secondary cursor-pointer d-flex align-items-center justify-content-between selected" data-value="All" style="font-size: 0.88rem; cursor: pointer;">
                                    <span>All Categories</span>
                                    <i class="fa-solid fa-check check-icon text-primary"></i>
                                </div>
                                <?php foreach ($categories_list as $cat): ?>
                                    <div class="dropdown-item py-2 px-3 fw-semibold text-secondary cursor-pointer d-flex align-items-center justify-content-between" data-value="<?= htmlspecialchars($cat['name']); ?>" style="font-size: 0.88rem; cursor: pointer;">
                                        <span><?= htmlspecialchars($cat['name']); ?></span>
                                        <i class="fa-solid fa-check check-icon text-primary d-none"></i>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php if (!$is_public): ?>
                    <div class="flex-shrink-0">
                        <button class="btn btn-primary py-2 px-3 fw-semibold d-inline-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#submit-modal" style="height: 40px;">
                            <i class="fa-solid fa-cloud-arrow-up"></i>
                            <span>Submit Instrument</span>
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Instruments Cards Grid -->
            <div class="row g-4" id="instruments-grid">
                <?php foreach ($instruments as $inst): ?>
                    <div class="col instrument-card-col"
                        data-id="<?= $inst['id']; ?>"
                        data-title="<?= htmlspecialchars($inst['title'], ENT_QUOTES); ?>"
                        data-categories="<?= htmlspecialchars(implode(',', $inst['categories']), ENT_QUOTES); ?>"
                        data-desc="<?= htmlspecialchars($inst['description'], ENT_QUOTES); ?>">
                        <div class="card h-100 border border-secondary border-opacity-25 rounded-3 shadow-sm position-relative instrument-card <?= $inst['installed'] ? 'installed' : ''; ?>">
                            <div class="card-body d-flex flex-column justify-content-between p-4">
                                <div>
                                    <div class="d-flex justify-content-between align-items-center mb-3 card-top">
                                        <div class="d-flex flex-wrap gap-1" style="max-width: 65%;">
                                            <?php foreach ($inst['categories'] as $cat): ?>
                                                <span class="category-tag badge <?= "category-" . strtolower(str_replace(' ', '', $cat)) ?> px-2.5 py-1.5 text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.05em;">
                                                    <?= htmlspecialchars($cat); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">

                                            <span class="version-tag badge bg-light text-secondary border border-secondary border-opacity-25 fw-semibold py-1 px-2">
                                                <?= htmlspecialchars($inst['version']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <h4 class="card-title h5 fw-bold text-dark mb-2"><?= htmlspecialchars($inst['title']); ?></h4>
                                    <p class="card-text text-muted mb-3" style="font-size: 0.88rem; line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; height: 4.5em;"><?= htmlspecialchars($inst['description']); ?></p>

                                    <div class="d-flex align-items-center mb-3 text-secondary" style="font-size: 0.82rem;">
                                        <i class="fa-solid fa-user-pen me-2 text-secondary opacity-75"></i>
                                        <span>Author: <strong class="text-dark"><?= htmlspecialchars($inst['author_name']); ?></strong></span>
                                    </div>
                                </div>

                                <div>
                                    <div class="card-meta d-flex justify-content-between border-top pt-3 mb-3 text-secondary" style="font-size: 0.8rem;">
                                        <div class="meta-item d-flex align-items-center gap-1">
                                            <i class="fa-solid fa-list-check opacity-75"></i>
                                            <span><?= count($inst['fields']); ?> Fields</span>
                                        </div>
                                        <div class="meta-item d-flex align-items-center gap-1">
                                            <i class="fa-solid fa-clock opacity-75"></i>
                                            <span><?= $inst['time']; ?></span>
                                        </div>
                                        <div class="meta-item d-flex align-items-center gap-1">
                                            <i class="fa-solid fa-arrow-down-long opacity-75"></i>
                                            <span><?= $inst['installs']; ?> Installs</span>
                                        </div>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-outline-secondary btn-sm py-2 fw-semibold d-inline-flex align-items-center justify-content-center" style="flex: 1;" onclick="openPreview('<?= $inst['id']; ?>')">
                                            <i class="fa-regular fa-eye me-2"></i>Preview
                                        </button>

                                        <?php if (!$is_public): ?>
                                            <?php if ($inst['installed']): ?>
                                                <button class="btn btn-success btn-sm py-2 fw-semibold d-inline-flex align-items-center justify-content-center btn-install is-installed" style="flex: 1;" disabled>
                                                    <i class="fa-solid fa-check me-2"></i>Installed
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-primary btn-sm py-2 fw-semibold d-inline-flex align-items-center justify-content-center btn-install" style="flex: 1;" onclick="installInstrument(this, '<?= $inst['id']; ?>')">
                                                    <i class="fa-solid fa-download me-2"></i>Install
                                                </button>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination Container -->
            <div class="d-flex justify-content-between align-items-center mt-4 bg-white p-3 rounded-3 border shadow-sm flex-wrap gap-2" id="lib-pagination-container">
                <div class="d-flex align-items-center gap-3">
                    <div class="text-muted" style="font-size: 0.88rem;" id="pagination-info">
                        <!-- Dynamic pagination info -->
                    </div>
                    <div class="d-flex align-items-center gap-2 border-start ps-3" id="rows-per-page-wrapper" style="font-size: 0.88rem;">
                        <span class="text-muted">Rows:</span>
                        <input type="number" id="rows-per-page-input" list="rows-per-page-list" class="form-control form-control-sm border-secondary border-opacity-25 text-center" style="width: 70px;" min="1" value="<?= (int)($defaults['default_rows_per_page'] ?? 2) ?>">
                        <datalist id="rows-per-page-list">
                            <option value="1"></option>
                            <option value="2"></option>
                            <option value="3"></option>
                            <option value="4"></option>
                        </datalist>
                    </div>
                </div>
                <nav aria-label="Page navigation">
                    <ul class="pagination mb-0" id="pagination-controls" style="cursor: pointer;">
                        <!-- Dynamically populated page list -->
                    </ul>
                </nav>
            </div>

            <!-- Empty State Container (Hidden initially) -->
            <div class="lib-empty-state text-center py-5 bg-white border border-dashed rounded-3 mt-4 text-secondary shadow-sm" id="lib-empty" style="display: none;">
                <i class="fa-solid fa-folder-open display-4 mb-3 text-muted opacity-50"></i>
                <h3 class="h5 text-dark fw-semibold mb-1">No instruments found</h3>
            </div>
        </div>

        <!-- Preview Modal (Bootstrap 5) -->
        <div class="modal fade" id="preview-modal" tabindex="-1" aria-labelledby="preview-modal-label" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg rounded-3">
                    <div class="modal-header bg-light border-bottom px-4 py-3">
                        <h3 class="modal-title h5 fw-bold text-dark m-0" id="preview-modal-label">
                            <i class="fa-solid fa-magnifying-glass-chart me-2 text-primary"></i>
                            Instrument Preview
                        </h3>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-0">
                        <div class="container-fluid px-0">
                            <div class="row g-0">
                                <!-- Sidebar Details -->
                                <div class="col-12 col-md-4 bg-light border-end p-4 d-flex flex-column gap-3 preview-sidebar">
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
                                        <p id="modal-author" class="fw-semibold text-dark mb-2" style="font-size: 0.9rem;"></p>
                                        <a id="modal-author-email" href="" class="btn btn-contact-author btn-sm d-inline-flex align-items-center fw-semibold py-2 px-3 mt-2">
                                            <i class="fa-regular fa-envelope me-2"></i>Contact Author
                                        </a>
                                    </div>
                                    <div>
                                        <h4 class="text-secondary text-uppercase fw-bold mb-1" style="font-size: 0.72rem; letter-spacing: 0.05em;">Description</h4>
                                        <p id="modal-desc" class="text-muted mb-0" style="font-size: 0.85rem; line-height: 1.5;"></p>
                                    </div>
                                    <div class="mt-3">
                                        <?php if (!$is_public): ?>
                                            <button class="btn btn-primary w-100 py-2.5 fw-semibold d-inline-flex align-items-center justify-content-center gap-2" id="modal-install-btn">
                                                <i class="fa-solid fa-download me-2"></i>Install to Project
                                            </button>
                                        <?php endif; ?>
                                        <a class="btn btn-outline-secondary w-100 py-2.5 fw-semibold d-inline-flex align-items-center justify-content-center gap-2 mt-2" id="modal-download-btn" href="#">
                                            <i class="fa-solid fa-file-zipper me-2"></i>Download ZIP Package
                                        </a>
                                        <?php if (!$is_public): ?>
                                            <div class="text-center mt-3">
                                                <button class="btn btn-link text-danger text-opacity-75 text-decoration-none btn-sm p-0 fw-semibold d-none" id="modal-remove-btn" style="font-size: 0.78rem;">
                                                    <i class="fa-solid fa-trash-can me-1"></i>Mark as Removed
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Fields List -->
                                <div class="col-12 col-md-8 p-4 d-flex flex-column preview-fields-container">
                                    <h4 class="text-dark fw-bold mb-3 h6" id="modal-fields-heading">Instrument Fields (Placeholder)</h4>
                                    <div class="border rounded-3 bg-white shadow-inner preview-fields-list">
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

        <!-- Submit Instrument Modal (Bootstrap 5) -->
        <div class="modal fade" id="submit-modal" tabindex="-1" aria-labelledby="submit-modal-label" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content border-0 shadow-lg rounded-3">
                    <div class="modal-header bg-light border-bottom px-4 py-3">
                        <h3 class="modal-title h5 fw-bold text-dark m-0" id="submit-modal-label">
                            <i class="fa-solid fa-cloud-arrow-up me-2 text-primary"></i>
                            Submit Instrument to Library
                        </h3>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <form id="submit-instrument-form" novalidate>
                            <!-- Submission Type Selection -->
                            <div class="mb-4">
                                <label class="form-label small fw-bold text-secondary mb-2 d-block">Submission Type</label>
                                <div class="row g-3">
                                    <div class="col-6">
                                        <input type="radio" class="btn-check" name="submission-type" id="type-new" value="new" checked autocomplete="off">
                                        <label class="btn btn-outline-primary w-100 py-3 d-flex flex-column align-items-center justify-content-center gap-2 border-2 rounded-3 cursor-pointer" for="type-new">
                                            <i class="fa-solid fa-circle-plus fs-4"></i>
                                            <span class="fw-bold small">New Library Instrument</span>
                                        </label>
                                    </div>
                                    <div class="col-6">
                                        <input type="radio" class="btn-check" name="submission-type" id="type-update" value="update" autocomplete="off">
                                        <label class="btn btn-outline-primary w-100 py-3 d-flex flex-column align-items-center justify-content-center gap-2 border-2 rounded-3 cursor-pointer" for="type-update">
                                            <i class="fa-solid fa-arrows-rotate fs-4"></i>
                                            <span class="fw-bold small">Update Existing Instrument</span>
                                        </label>
                                    </div>
                                </div>

                                <!-- Conditional Dropdown for Existing Instrument Selection -->
                                <div id="existing-instrument-wrapper" class="mt-3" style="display: none;">
                                    <label for="submit-existing-instrument" class="form-label small fw-bold text-secondary mb-1">Select Instrument to Update</label>
                                    <select class="form-select border-secondary border-opacity-25 bg-white py-2" id="submit-existing-instrument" disabled>
                                        <option value="" disabled selected>Choose from active library...</option>
                                        <?php foreach ($instruments as $inst): ?>
                                            <option value="<?= $inst['id']; ?>" data-version="<?= htmlspecialchars($inst['version']); ?>"><?= htmlspecialchars($inst['title']); ?> (<?= htmlspecialchars($inst['version']); ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Please select the instrument you are updating.</div>
                                </div>
                            </div>

                            <!-- Form Row 1: Title wrapper -->
                            <div class="mb-3" id="submit-title-wrapper">
                                <label for="submit-title" class="form-label small fw-bold text-secondary mb-1">Instrument Title</label>
                                <input type="text" class="form-control border-secondary border-opacity-25 py-2" id="submit-title" placeholder="Patient Health Questionnaire-9" required>
                                <div class="invalid-feedback">Please enter an instrument title.</div>
                            </div>

                            <!-- Form Row 2: Categories Multi-Select Checklist (tag card style) -->
                            <div class="mb-4">
                                <label class="form-label small fw-bold text-secondary mb-2 d-block">Categories</label>
                                <div class="p-3 border border-secondary border-opacity-25 rounded-3 bg-light bg-opacity-25">
                                    <div class="row g-2" id="submit-categories-list">
                                        <?php foreach ($categories_list as $cat): ?>
                                            <?php
                                            $cleanId = 'cat-' . strtolower(str_replace(' ', '', $cat['name']));
                                            ?>
                                            <div class="col-6 col-md-4">
                                                <input class="btn-check submit-cat-checkbox" type="checkbox" value="<?= htmlspecialchars($cat['name']); ?>" id="<?= $cleanId; ?>" autocomplete="off">
                                                <label class="btn btn-outline-secondary w-100 py-2 px-3 text-start d-flex align-items-center justify-content-between rounded-3 cursor-pointer" for="<?= $cleanId; ?>">
                                                    <span class="small fw-semibold text-dark"><?= htmlspecialchars($cat['name']); ?></span>
                                                    <span class="checkbox-box">
                                                        <i class="fa-regular fa-square text-muted unchecked-icon"></i>
                                                        <i class="fa-solid fa-square-check checked-icon d-none"></i>
                                                    </span>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="invalid-feedback d-none" id="categories-invalid-msg" style="margin-top: 0.5rem;">Please select at least one category.</div>
                                </div>
                            </div>

                            <!-- Form Row 2: Author Name, Author Email, Version -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-5">
                                    <label for="submit-author-name" class="form-label small fw-bold text-secondary mb-1">Author Name</label>
                                    <input type="text" class="form-control border-secondary border-opacity-25 py-2" id="submit-author-name" placeholder="Jane Doe" required>
                                    <div class="invalid-feedback">Please enter author name.</div>
                                </div>
                                <div class="col-md-4">
                                    <label for="submit-author-email" class="form-label small fw-bold text-secondary mb-1">Author Contact Email</label>
                                    <input type="email" class="form-control border-secondary border-opacity-25 py-2" id="submit-author-email" placeholder="jane.doe" <?= $module->getContactEmailDomain(); ?> required>
                                    <div class="invalid-feedback">Please enter a valid email address.</div>
                                </div>
                                <div class="col-md-3" id="submit-version-wrapper">
                                    <label for="submit-version" class="form-label small fw-bold text-secondary mb-1">Version</label>
                                    <input type="text" class="form-control border-secondary border-opacity-25 py-2" id="submit-version" value="1.0.0" required>
                                    <div class="invalid-feedback" id="version-invalid-feedback">Please specify a version.</div>
                                    <div class="form-text text-muted small d-none" id="version-current-info" style="margin-top: 0.25rem;"></div>
                                </div>
                            </div>

                            <!-- Form Row 3: Description -->
                            <div class="mb-4">
                                <label for="submit-description" class="form-label small fw-bold text-secondary mb-1">Description / Purpose</label>
                                <textarea class="form-control border-secondary border-opacity-25" id="submit-description" rows="3" placeholder="Provide a brief description of what this instrument collects and its clinical or research application..." required></textarea>
                                <div class="invalid-feedback">Please provide a brief description.</div>
                            </div>

                            <!-- Form Row 4: Method Toggle & Details -->
                            <div class="card border border-secondary border-opacity-25 rounded-3 mb-4">
                                <div class="card-header bg-light border-bottom-0 py-2">
                                    <!-- Nav tabs -->
                                    <ul class="nav nav-tabs card-header-tabs" id="submission-method-tabs" role="tablist">
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link active" id="zip-tab" data-bs-toggle="tab" data-bs-target="#zip-pane" type="button" role="tab" aria-controls="zip-pane" aria-selected="true">
                                                <i class="fa-solid fa-file-zipper me-1 text-primary opacity-75"></i>
                                                Upload Zip File
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="project-tab" data-bs-toggle="tab" data-bs-target="#project-pane" type="button" role="tab" aria-controls="project-pane" aria-selected="false">
                                                <i class="fa-solid fa-server me-1 text-primary opacity-75"></i>
                                                Select from Project
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                                <div class="card-body p-3">
                                    <!-- Tab Panes -->
                                    <div class="tab-content">
                                        <!-- Tab Pane 1: Zip file upload -->
                                        <div class="tab-pane fade show active" id="zip-pane" role="tabpanel" aria-labelledby="zip-tab">
                                            <div class="file-drop-zone p-4 text-center" id="file-drop-zone">
                                                <i class="fa-solid fa-cloud-arrow-up display-5"></i>
                                                <h5 class="h6 fw-bold text-dark mt-2 mb-1">Drag and drop your instrument zip package here</h5>
                                                <p class="small text-muted mb-2">or click to browse your local computer (ZIP package format)</p>
                                                <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-3 py-1.5 fw-semibold" style="font-size: 0.75rem;">Max file size: 10MB</span>
                                                <!-- Hidden Input -->
                                                <input type="file" id="submit-file-input" class="d-none" accept=".zip">
                                                <div id="file-upload-status" class="mt-2 text-primary fw-semibold small d-none"></div>
                                            </div>
                                            <div class="invalid-feedback d-none" id="file-invalid-msg">Please select or drop an instrument ZIP file.</div>
                                        </div>

                                        <!-- Tab Pane 2: Select from current project -->
                                        <div class="tab-pane fade" id="project-pane" role="tabpanel" aria-labelledby="project-tab">
                                            <label for="submit-project-instrument" class="form-label small fw-bold text-secondary mb-1">Existing Project Instruments</label>
                                            <select class="form-select border-secondary border-opacity-25" id="submit-project-instrument" disabled>
                                                <option value="" disabled selected>Choose an instrument from this active project...</option>
                                                <?php
                                                global $Proj;
                                                foreach ($Proj->forms as $form_name => $form_info)
                                                    echo '<option value="' . htmlspecialchars($form_name, ENT_QUOTES) . '">' . htmlspecialchars($form_info['menu'], ENT_QUOTES) . ' (' . count($form_info['fields']) . ' fields)</option>';
                                                ?>
                                            </select>
                                            <div class="invalid-feedback">Please select an instrument to submit.</div>
                                            <p class="small text-muted mt-2 mb-0"><i class="fa-solid fa-circle-info me-1"></i> Selecting this option will generate a zip package automatically using metadata from the active project's database.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Submit Footer Buttons -->
                            <div class="d-flex justify-content-end gap-2 border-top pt-3">
                                <button type="button" class="btn btn-light border fw-semibold py-2 px-3" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary fw-semibold py-2 px-4 d-inline-flex align-items-center justify-content-center gap-2" id="submit-form-btn" style="min-width: 130px;">
                                    <i class="fa-solid fa-paper-plane"></i>
                                    <span>Submit Request</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Notification Toast -->
        <div class="lib-toast" id="lib-toast">
            <i class="fa-solid fa-circle-check success-icon text-success"></i>
            <span id="toast-message">Instrument successfully installed!</span>
        </div>

        <script type="text/javascript">
            const module = <?= $module->getJavascriptModuleObjectName() ?>;
            const defaultRowsPerPage = <?= (int)($defaults['default_rows_per_page'] ?? 2) ?>;
            const isPublicView = <?= json_encode($is_public); ?>;
            const contactEmailDomain = <?= json_encode($module->getContactEmailDomain()) ?>;

            // Store instrument fields data in a global JS object for easy access in preview modal
            const instrumentFields = <?= json_encode(array_column($instruments, null, 'id')); ?>;
            let installedCount = <?= count(array_filter($instruments, function ($i) {
                                        return $i['installed'];
                                    })); ?>;
        </script>
        <script type="module" src="<?= $module->getUrl('index.js'); ?>"></script>

        <?php if ($is_public): ?>
        </div><?php endif; ?>
    <?php
    if (!$is_public) {
        require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
    } else {
        // Use REDCap's own footer. PrintFooter() prefixes two orphan </div> tags
        // that close its internal layout divs — capture and strip those, then force
        // #footer visible (it defaults to d-none in REDCap's layout JS).
        $htmlPage = new \HtmlPage();
        ob_start();
        $htmlPage->PrintFooter();
        $footer_html = ob_get_clean();
        echo preg_replace('/^(\s*<\/div>\s*){2}/i', '', $footer_html);
    ?>
        <style>
            #footer {
                display: block !important;
                padding: 8px 10px;
                border-top: 1px solid #ddd;
                margin-top: 2rem;
                font-size: 11px;
                color: #888;
                text-align: center;
            }
        </style>
    </body>

    </html>
<?php
    }
?>