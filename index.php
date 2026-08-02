<?php

/** @var \UWMadison\LocalInstrumentLibrary\LocalInstrumentLibrary $module */

$is_public = (PAGE === 'api/index.php');
$project_id = $_GET['pid'] ?? $module->getProjectId();

if ($is_public) {
    if (!(bool)($module->getSystemSetting('enable_public_page') ?? true)) {
        header('HTTP/1.1 403 Forbidden');
        echo "Public access to the Instrument Library is disabled.";
        exit;
    }

    $public_title = (string)$module->getSystemSetting('public_library_title');
    $public_blurb = (string)$module->getSystemSetting('public_library_blurb');
    $library_title = !empty($public_title) ? $public_title : "Local Instrument Library";
    $library_blurb = !empty($public_blurb) ? $public_blurb : "Browse and discover standardized data collection instruments available across the institution.";
} else {
    $internal_title = (string)$module->getSystemSetting('internal_library_title');
    $internal_blurb = (string)$module->getSystemSetting('internal_library_blurb');
    $library_title = !empty($internal_title) ? $internal_title : "Local Instrument Library";
    $library_blurb = !empty($internal_blurb) ? $internal_blurb : "Browse and discover standardized data collection instruments available for import into your REDCap project.";
}

$defaults = $module->getDefaults();
$categories_list = $module->getCategoriesList();

if ($is_public) {
    $htmlPage = new HtmlPage();
    if (method_exists($htmlPage, 'setPageTitle')) $htmlPage->setPageTitle($library_title);
    elseif (method_exists($htmlPage, 'setTitle')) $htmlPage->setTitle($library_title);

    ob_start();
    $htmlPage->PrintHeader();
    $header_html = ob_get_clean();

    $module_css_url = $module->getUrl('css/style.css');
    $custom_head = '<link rel="stylesheet" href="' . $module_css_url . '">';
    $header_html = str_replace('</head>', $custom_head . '</head>', $header_html);
    echo $header_html;
} else {
    require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
?>
    <link rel="stylesheet" href="<?= $module->getUrl('css/style.css'); ?>">
<?php
}

$dynamic_category_styles = $module->renderCategoryCSS($categories_list, ['#local-instrument-lib-container', '#preview-modal', '#submit-modal']);
$instruments_all = $module->getInstruments();
$dynamic_field_type_styles = $module->renderFieldTypeCSS($instruments_all, ['#local-instrument-lib-container', '#preview-modal', '#submit-modal']);

if ($is_public) {
    $module->loadREDCapCSS();
    $module->loadREDCapJS();
    $module->loadBootstrap();
}

$instruments = $module->getInstruments();
$module->initializeJavascriptModuleObject();
$installed_instruments = (!$is_public && !empty($project_id)) ? ($module->getProjectSetting('installed_instruments', $project_id) ?? []) : [];

foreach ($instruments as &$inst) $inst['installed'] = in_array($inst['id'], $installed_instruments);
unset($inst);

$categories = array_map(fn($c) => $c['name'], $categories_list);
$popular_method = (string)($module->getSystemSetting('popular_calculation_method') ?: 'relative_60');
$popular_ids = [];

if ($popular_method !== 'disabled' && !empty($instruments)) {
    $max_installs = max(array_map(fn($i) => (int)($i['installs'] ?? 0), $instruments));
    foreach ($instruments as $inst) {
        $installs = (int)($inst['installs'] ?? 0);
        if ($popular_method === 'relative_60' && $max_installs >= 5 && $installs >= ($max_installs * 0.6)) $popular_ids[] = $inst['id'];
        elseif ($popular_method === 'min_5' && $installs >= 5) $popular_ids[] = $inst['id'];
        elseif ($popular_method === 'min_10' && $installs >= 10) $popular_ids[] = $inst['id'];
    }
    if ($popular_method === 'top_3') {
        $sorted = $instruments;
        usort($sorted, fn($a, $b) => ($b['installs'] ?? 0) <=> ($a['installs'] ?? 0));
        $top = array_slice($sorted, 0, 3);
        foreach ($top as $t) if (($t['installs'] ?? 0) > 0) $popular_ids[] = $t['id'];
    }
}
?>

<style id="dynamic-category-styles"><?= $dynamic_category_styles ?><?= $dynamic_field_type_styles ?></style>

<?php if ($is_public): ?><div id="local-instrument-public-wrapper" class="px-4 px-md-5 py-4"><?php endif; ?>

    <div id="local-instrument-lib-container" class="container-fluid py-4 px-3 px-sm-4 bg-light border rounded-3 mt-3 shadow-sm <?= $is_public ? 'public-container' : ''; ?>">
        <div class="lib-header text-white rounded-3 p-4 p-md-5 mb-4 shadow-sm position-relative overflow-hidden">
            <div class="position-relative z-1">
                <h2 class="display-6 fw-bold mb-2 font-outfit"><?= htmlspecialchars($library_title) ?></h2>
                <p class="lead mb-4 max-w-750 fs-105"><?= htmlspecialchars($library_blurb) ?></p>

                <div class="d-flex flex-wrap gap-2 gap-md-3">
                    <div class="lib-stat-badge bg-white bg-opacity-10 border border-white border-opacity-25 rounded-pill px-3 py-2 text-white fs-92 fw-semibold">
                        <i class="fa-solid fa-list-ul me-2"></i>
                        <span><strong id="stat-count"><?= count($instruments); ?></strong> Available Instruments</span>
                    </div>

                    <?php if (!$is_public): ?>
                        <div class="lib-stat-badge bg-white bg-opacity-10 border border-white border-opacity-25 rounded-pill px-3 py-2 text-white fs-92 fw-semibold">
                            <i class="fa-solid fa-circle-check me-2"></i>
                            <span id="stat-installed"><?= count(array_filter($instruments, fn($i) => $i['installed'])); ?> Installed in Project</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-stretch align-items-md-center gap-3 mb-4">
            <div class="flex-grow-1 min-w-250">
                <div class="input-group shadow-sm rounded-3 overflow-hidden border">
                    <span class="input-group-text bg-white border-0 ps-3 pe-2 text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" id="lib-search" class="form-control border-0 py-2 ps-2 shadow-none" placeholder="Search instruments by name, category, or keywords...">
                </div>
            </div>

            <div class="d-flex flex-wrap align-items-center gap-2 flex-shrink-0">
                <div class="flex-shrink-0 w-260 min-w-180">
                    <div class="category-select-wrapper position-relative">
                        <div id="category-dropdown-trigger" class="form-control py-2 border-secondary border-opacity-25 bg-light fw-semibold text-secondary d-flex align-items-center justify-content-between cursor-pointer">
                            <span id="selected-category-text">All Categories</span>
                            <i class="fa-solid fa-chevron-down text-muted small ms-2"></i>
                        </div>

                        <div id="category-dropdown-menu" class="position-absolute start-0 w-100 bg-white border rounded-3 shadow-lg mt-1 d-none z-3 max-h-280 d-flex flex-column">
                            <div class="p-2 border-bottom bg-light">
                                <div class="position-relative">
                                    <i class="fa-solid fa-magnifying-glass position-absolute start-0 top-50 translate-middle-y ms-2 text-muted fs-8"></i>
                                    <input type="text" id="category-search" class="form-control form-control-sm ps-4 fs-85" placeholder="Search categories..." autocomplete="off">
                                </div>
                            </div>
                            <div class="options-list overflow-y-auto max-h-220">
                                <div class="dropdown-item py-2 px-3 fw-semibold text-secondary cursor-pointer d-flex align-items-center justify-content-between selected fs-88" data-value="All">
                                    <span>All Categories</span>
                                    <i class="fa-solid fa-check check-icon text-primary"></i>
                                </div>
                                <?php foreach ($categories_list as $cat): ?>
                                    <div class="dropdown-item py-2 px-3 fw-semibold text-secondary cursor-pointer d-flex align-items-center justify-content-between fs-88" data-value="<?= htmlspecialchars($cat['name']); ?>">
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
                        <button class="btn btn-primary py-2 px-3 fw-semibold d-inline-flex align-items-center gap-2 h-40" data-bs-toggle="modal" data-bs-target="#submit-modal">
                            <i class="fa-solid fa-cloud-arrow-up"></i><span>Submit Instrument</span>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="row g-4" id="instruments-grid">
            <?php foreach ($instruments as $inst): ?>
                <?php
                $inst_cats = is_array($inst['categories']) ? implode(',', $inst['categories']) : '';
                $is_popular = in_array($inst['id'], $popular_ids);
                ?>
                <div class="col-12 col-md-6 col-lg-4 instrument-card-col" data-id="<?= $inst['id']; ?>" data-title="<?= htmlspecialchars($inst['title']); ?>" data-desc="<?= htmlspecialchars($inst['description']); ?>" data-categories="<?= htmlspecialchars($inst_cats); ?>">
                    <div class="card h-100 border-0 shadow-sm rounded-3 overflow-hidden instrument-card <?= $inst['installed'] ? 'installed' : ''; ?>">
                        <div class="card-body p-4 d-flex flex-column justify-content-between">
                            <div>
                                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                    <div class="d-flex flex-wrap gap-1 max-w-65">
                                        <?php foreach ($inst['categories'] as $cat): ?>
                                            <span class="category-tag badge <?= "category-" . strtolower(str_replace(' ', '', $cat)) ?> px-2.5 py-1.5 text-uppercase fw-bold fs-7 tracking-wide">
                                                <?= htmlspecialchars($cat); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>

                                    <div class="d-flex align-items-center gap-1">
                                        <span class="badge bg-light text-secondary border fw-bold font-monospace fs-7">
                                            <?= htmlspecialchars($inst['version']); ?>
                                        </span>
                                    </div>
                                </div>

                                <h3 class="card-title h5 fw-bold text-dark mb-2"><?= htmlspecialchars($inst['title']); ?></h3>
                                <p class="card-text text-muted mb-3 fs-88 lh-base card-desc-clamp h-4-5em"><?= htmlspecialchars($inst['description']); ?></p>

                                <div class="d-flex align-items-center mb-3 text-secondary fs-82">
                                    <i class="fa-solid fa-user-pen me-2 text-primary opacity-75"></i>
                                    <span>Author: <strong><?= htmlspecialchars($inst['author_name']); ?></strong></span>
                                </div>
                            </div>

                            <div>
                                <div class="card-meta d-flex justify-content-between align-items-center border-top pt-3 mb-3 text-secondary fs-8">
                                    <div class="d-flex align-items-center gap-3">
                                        <span><i class="fa-solid fa-list-check me-1 text-secondary opacity-75"></i><?= count($inst['fields']); ?> Fields</span>
                                        <span><i class="fa-solid fa-arrow-down-long me-1 text-secondary opacity-75"></i><?= (int)($inst['installs'] ?? 0); ?> Installs</span>
                                    </div>

                                    <div>
                                        <?php if ($is_popular): ?>
                                            <span class="badge bg-warning text-dark fw-bold px-2 py-1 fs-xs tracking-sm">
                                                <i class="fa-solid fa-fire me-1"></i>Popular
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="d-flex gap-2">
                                    <button class="btn btn-outline-secondary btn-sm py-2 fw-semibold d-inline-flex align-items-center justify-content-center flex-1" onclick="openPreview('<?= $inst['id']; ?>')">
                                        <i class="fa-regular fa-eye me-2"></i>Preview
                                    </button>

                                    <?php if (!$is_public): ?>
                                        <?php if ($inst['installed']): ?>
                                            <button class="btn btn-success btn-sm py-2 fw-semibold d-inline-flex align-items-center justify-content-center btn-install is-installed flex-1" disabled>
                                                <i class="fa-solid fa-check me-2"></i>Installed
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-primary btn-sm py-2 fw-semibold d-inline-flex align-items-center justify-content-center btn-install flex-1" onclick="installInstrument(this, '<?= $inst['id']; ?>')">
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

        <div class="d-flex flex-column flex-md-row align-items-center justify-content-between gap-3 mt-4 pt-3 border-top" id="lib-pagination-container">
            <div class="d-flex align-items-center gap-3">
                <div class="text-muted fs-88" id="pagination-info"></div>
                <div class="d-flex align-items-center gap-2 border-start ps-3 fs-88" id="rows-per-page-wrapper">
                    <label for="rows-per-page-input" class="text-muted mb-0">Rows/page:</label>
                    <input type="number" id="rows-per-page-input" list="rows-per-page-list" class="form-control form-control-sm border-secondary border-opacity-25 text-center w-70" min="1" value="<?= (int)($defaults['default_rows_per_page'] ?? 2) ?>">
                    <datalist id="rows-per-page-list">
                        <option value="1"></option>
                        <option value="2"></option>
                        <option value="4"></option>
                        <option value="6"></option>
                    </datalist>
                </div>
            </div>
            <nav aria-label="Library pagination">
                <ul class="pagination mb-0 cursor-pointer" id="pagination-controls"></ul>
            </nav>
        </div>

        <div class="lib-empty-state text-center py-5 bg-white border border-dashed rounded-3 mt-4 text-secondary shadow-sm d-none" id="lib-empty">
            <i class="fa-solid fa-folder-open display-4 mb-3 text-muted opacity-50"></i>
            <h3 class="h5 text-dark fw-bold mb-1">No matching instruments found</h3>
            <p class="text-muted small mb-0 max-w-400">Try adjusting your search terms or clearing your selected category filters.</p>
        </div>
    </div>

    <div class="modal fade" id="preview-modal" tabindex="-1" aria-labelledby="preview-modal-label" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-3">
                <div class="modal-header bg-light border-bottom px-4 py-3">
                    <h3 class="modal-title m-0 h5 fw-bold text-dark" id="preview-modal-label">
                        <i class="fa-solid fa-magnifying-glass-chart me-2 text-primary"></i><span id="modal-title">Instrument Preview</span>
                    </h3>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="container-fluid px-0">
                        <div class="row g-0">
                            <div class="col-12 col-md-4 bg-light border-end p-4 d-flex flex-column gap-3 preview-sidebar">
                                <div>
                                    <h4 class="text-secondary text-uppercase fw-bold mb-1 fs-72 tracking-wide">Categories</h4>
                                    <div id="modal-categories" class="d-flex flex-wrap gap-1 mt-1"></div>
                                </div>
                                <div>
                                    <h4 class="text-secondary text-uppercase fw-bold mb-1 fs-72 tracking-wide">Version</h4>
                                    <span id="modal-version" class="badge bg-white border text-secondary fw-semibold">v1.0.0</span>
                                </div>
                                <div>
                                    <h4 class="text-secondary text-uppercase fw-bold mb-1 fs-72 tracking-wide">Author</h4>
                                    <p id="modal-author" class="fw-semibold text-dark mb-2 fs-9"></p>
                                    <a id="modal-author-email" href="#" class="btn btn-outline-secondary btn-sm py-1.5 px-3 fw-semibold d-inline-flex align-items-center gap-2 btn-contact-author">
                                        <i class="fa-regular fa-envelope"></i><span>Contact Author</span>
                                    </a>
                                </div>
                                <div>
                                    <h4 class="text-secondary text-uppercase fw-bold mb-1 fs-72 tracking-wide">Description</h4>
                                    <p id="modal-desc" class="text-muted mb-0 fs-85 lh-base"></p>
                                </div>
                                <div class="mt-3">
                                    <?php if (!$is_public): ?>
                                        <button class="btn btn-primary w-100 py-2 fw-semibold d-inline-flex align-items-center justify-content-center gap-2 mb-2 btn-install" id="modal-install-btn">
                                            <i class="fa-solid fa-download me-2"></i><span>Install to Project</span>
                                        </button>
                                    <?php endif; ?>
                                    <button class="btn btn-outline-secondary w-100 py-2 fw-semibold d-inline-flex align-items-center justify-content-center gap-2" id="modal-download-btn">
                                        <i class="fa-solid fa-file-zipper me-2"></i><span>Download Package</span>
                                    </button>
                                    <?php if (!$is_public): ?>
                                        <div class="text-center mt-2">
                                            <button class="btn btn-link text-danger text-opacity-75 text-decoration-none btn-sm p-0 fw-semibold d-none fs-75" id="modal-remove-btn">
                                                <i class="fa-solid fa-trash-can me-1"></i>Remove from Project
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="col-12 col-md-8 p-4 d-flex flex-column preview-fields-container">
                                <h4 class="text-dark fw-bold mb-3 h6" id="modal-fields-heading">Instrument Fields</h4>
                                <div class="border rounded-3 bg-white shadow-inner preview-fields-list">
                                    <ul class="list-group list-group-flush m-0 p-0" id="modal-field-list"></ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!$is_public): ?>
        <div class="modal fade" id="submit-modal" tabindex="-1" aria-labelledby="submit-modal-label" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content border-0 shadow-lg rounded-3">
                    <div class="modal-header bg-light border-bottom px-4 py-3">
                        <h3 class="modal-title m-0 h5 fw-bold text-dark" id="submit-modal-label">
                            <i class="fa-solid fa-cloud-arrow-up me-2 text-primary"></i>Submit Instrument to Library
                        </h3>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body p-4">
                        <form id="submit-instrument-form" class="needs-validation" novalidate>
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
                            </div>

                            <div class="mb-3" id="existing-instrument-wrapper" style="display: none;">
                                <label for="submit-existing-instrument" class="form-label small fw-bold text-secondary mb-1">Select Instrument to Update</label>
                                <select class="form-select border-secondary border-opacity-25 py-2" id="submit-existing-instrument">
                                    <option value="" selected disabled>Choose an instrument...</option>
                                    <?php foreach ($instruments as $inst): ?>
                                        <option value="<?= htmlspecialchars($inst['id']); ?>" data-version="<?= htmlspecialchars($inst['version']); ?>"><?= htmlspecialchars($inst['title']); ?> (<?= htmlspecialchars($inst['version']); ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Please select an existing instrument to update.</div>
                            </div>

                            <div class="mb-3" id="submit-title-wrapper">
                                <label for="submit-title" class="form-label small fw-bold text-secondary mb-1">Instrument Title</label>
                                <input type="text" class="form-control border-secondary border-opacity-25 py-2" id="submit-title" placeholder="e.g., PHQ-9 Depression Scale" required>
                                <div class="invalid-feedback">Please enter an instrument title.</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-secondary mb-1">Categories (Select at least one)</label>
                                <div class="p-3 border border-secondary border-opacity-25 rounded-3 bg-light bg-opacity-50">
                                    <div class="row g-2" id="submit-categories-list">
                                        <?php foreach ($categories as $index => $catName): ?>
                                            <?php $catId = 'submit-cat-' . $index; ?>
                                            <div class="col-6 col-md-4">
                                                <input class="btn-check submit-cat-checkbox" type="checkbox" value="<?= htmlspecialchars($catName); ?>" id="<?= $catId; ?>" autocomplete="off">
                                                <label class="btn btn-outline-secondary w-100 py-2 px-3 text-start d-flex align-items-center justify-content-between rounded-3 cursor-pointer" for="<?= $catId; ?>">
                                                    <span class="small fw-semibold text-dark"><?= htmlspecialchars($catName); ?></span>
                                                    <span class="checkbox-box">
                                                        <i class="fa-regular fa-square text-muted unchecked-icon"></i>
                                                        <i class="fa-solid fa-square-check text-primary checked-icon d-none"></i>
                                                    </span>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="invalid-feedback d-none mt-2" id="categories-invalid-msg">Please select at least one category.</div>
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-5">
                                    <label for="submit-author-name" class="form-label small fw-bold text-secondary mb-1">Author Name</label>
                                    <input type="text" class="form-control border-secondary border-opacity-25 py-2" id="submit-author-name" placeholder="Dr. Jane Doe" required>
                                    <div class="invalid-feedback">Please enter the author's name.</div>
                                </div>
                                <div class="col-md-4">
                                    <label for="submit-author-email" class="form-label small fw-bold text-secondary mb-1">Author Email</label>
                                    <input type="email" class="form-control border-secondary border-opacity-25 py-2" id="submit-author-email" placeholder="jane.doe@example.edu" required>
                                    <div class="invalid-feedback">Please enter a valid email address.</div>
                                </div>
                                <div class="col-md-3">
                                    <label for="submit-version" class="form-label small fw-bold text-secondary mb-1">Version Number</label>
                                    <input type="text" class="form-control border-secondary border-opacity-25 py-2" id="submit-version" value="1.0.0" placeholder="1.0.0" required>
                                    <div class="invalid-feedback" id="version-invalid-feedback">Please specify a version.</div>
                                    <div class="form-text text-muted small d-none mt-1" id="version-current-info"></div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="submit-description" class="form-label small fw-bold text-secondary mb-1">Description / Purpose</label>
                                <textarea class="form-control border-secondary border-opacity-25" id="submit-description" rows="3" placeholder="Provide a brief description of what this instrument measures and how it should be used..." required></textarea>
                                <div class="invalid-feedback">Please enter a description.</div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label small fw-bold text-secondary mb-2">Instrument Package Source</label>
                                <ul class="nav nav-tabs mb-3" id="submissionSourceTab" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link active" id="zip-tab" data-bs-toggle="tab" data-bs-target="#zip-pane" type="button" role="tab" aria-controls="zip-pane" aria-selected="true">
                                            <i class="fa-solid fa-file-zipper me-2"></i>Upload ZIP Package
                                        </button>
                                    </li>
                                    <?php if (!$is_public): ?>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="project-tab" data-bs-toggle="tab" data-bs-target="#project-pane" type="button" role="tab" aria-controls="project-pane" aria-selected="false">
                                                <i class="fa-solid fa-folder-minus me-2"></i>Select Current Project Instrument
                                            </button>
                                        </li>
                                    <?php endif; ?>
                                </ul>

                                <div class="tab-content" id="submissionSourceTabContent">
                                    <div class="tab-pane fade show active" id="zip-pane" role="tabpanel" aria-labelledby="zip-tab">
                                        <div class="file-drop-zone p-4" id="file-drop-zone">
                                            <i class="fa-solid fa-cloud-arrow-up fs-2 text-primary mb-2"></i>
                                            <h5 class="h6 text-dark fw-bold mb-1">Drag and drop ZIP file here</h5>
                                            <p class="text-muted small mb-3">ZIP must contain instrument.csv and OriginID.txt</p>
                                            <input type="file" id="submit-file-input" class="d-none" accept=".zip">
                                            <button type="button" class="btn btn-outline-primary btn-sm px-3 fw-semibold">Browse File</button>
                                        </div>
                                        <div class="form-text text-danger small d-none mt-2" id="file-invalid-msg">Please upload a valid ZIP package file.</div>
                                        <div class="form-text text-primary small d-none mt-2 fw-semibold" id="file-upload-status"></div>

                                    </div>

                                    <?php if (!$is_public): ?>
                                        <div class="tab-pane fade" id="project-pane" role="tabpanel" aria-labelledby="project-tab">
                                            <?php
                                            $metadata = (!empty($project_id)) ? $module->getMetadata($project_id) : [];
                                            $instruments_in_project = [];
                                            if (is_array($metadata)) {
                                                foreach ($metadata as $field) {
                                                    $form_name = $field['form_name'];
                                                    if (!isset($instruments_in_project[$form_name])) {
                                                        $instruments_in_project[$form_name] = [
                                                            'name' => $form_name,
                                                            'label' => ucwords(str_replace('_', ' ', $form_name))
                                                        ];
                                                    }
                                                }
                                            }
                                            ?>
                                            <div class="p-3 bg-light rounded-3 border">
                                                <label for="submit-project-instrument" class="form-label small fw-bold text-secondary mb-1">Select Instrument from Current Project</label>
                                                <select class="form-select border-secondary border-opacity-25 py-2" id="submit-project-instrument" disabled>
                                                    <option value="" selected disabled>Choose an instrument...</option>
                                                    <?php foreach ($instruments_in_project as $inst_proj): ?>
                                                        <option value="<?= htmlspecialchars($inst_proj['name']); ?>"><?= htmlspecialchars($inst_proj['label']); ?> (<?= htmlspecialchars($inst_proj['name']); ?>)</option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <div class="invalid-feedback">Please select an instrument from the project.</div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end gap-2 border-top pt-3">
                                <button type="button" class="btn btn-light border fw-semibold py-2 px-3" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary fw-semibold py-2 px-4 d-inline-flex align-items-center justify-content-center gap-2 min-w-130" id="submit-form-btn">
                                    <i class="fa-solid fa-paper-plane"></i><span>Submit Request</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="lib-toast" id="lib-toast">
        <i class="fa-solid fa-circle-check success-icon text-success"></i>
        <span id="toast-message">Instrument successfully installed!</span>
    </div>

    <script type="text/javascript">
        const module = <?= $module->getJavascriptModuleObjectName() ?>;
        const defaultRowsPerPage = <?= (int)($defaults['default_rows_per_page'] ?? 2) ?>;
        const isPublicView = <?= json_encode($is_public); ?>;
        const contactEmailDomain = <?= json_encode($module->getContactEmailDomain()) ?>;
        const instrumentFields = <?= json_encode(array_column($instruments, null, 'id')); ?>;
        let installedCount = <?= count(array_filter($instruments, fn($i) => $i['installed'])); ?>;
    </script>
    <script defer src="<?= $module->getUrl('js/alpine.min.js'); ?>"></script>
    <script type="module" src="<?= $module->getUrl('js/index.js'); ?>"></script>

    <?php if ($is_public): ?></div><?php endif; ?>
<?php
if (!$is_public) {
    require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
} else {
    $htmlPage = new HtmlPage();
    ob_start();
    $htmlPage->PrintFooter();
    echo preg_replace('/^(\s*<\/div>\s*){2}/i', '', ob_get_clean());
?>
    <style>#footer { display: block !important; padding: 8px 10px; border-top: 1px solid #ddd; margin-top: 2rem; font-size: 11px; color: #888; text-align: center; }</style>
</body>
</html>
<?php } ?>