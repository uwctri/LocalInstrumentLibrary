/**
 * Local Instrument Library - Shared Utilities Module
 */

export const compareSemanticVersions = (v1, v2) => {
    const clean1 = String(v1 || '').replace(/^v/i, '');
    const clean2 = String(v2 || '').replace(/^v/i, '');
    const parts1 = clean1.split('.').map(Number);
    const parts2 = clean2.split('.').map(Number);
    for (let i = 0; i < Math.max(parts1.length, parts2.length); i++) {
        const p1 = parts1[i] || 0;
        const p2 = parts2[i] || 0;
        if (p1 > p2) return 1;
        if (p1 < p2) return -1;
    }
    return 0;
};

export const escapeHtml = (str) => {
    if (typeof str !== 'string') return '';
    return str
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
};

export const getBadgeTypeClass = (type) => {
    if (!type) return 'type-default';
    const cleanType = String(type).toLowerCase().replace(/[_ ]/g, '').trim();
    return 'type-' + cleanType;
};

export const renderCategoryBadges = (categories, categoryMapping = []) => {
    if (!Array.isArray(categories)) return '';
    let html = '';
    categories.forEach(cat => {
        const catName = (typeof cat === 'object' && cat !== null) ? cat.name : cat;
        const map = Array.isArray(categoryMapping) ? categoryMapping.find(c => c.name === catName) : null;
        const cls = map ? map.class : 'category-' + String(catName).toLowerCase().replace(/\s+/g, '');
        html += `<span class="badge ${cls} me-1 mb-1 px-2.5 py-1.5 text-uppercase fw-bold" style="font-size: 0.68rem; letter-spacing: 0.05em;">${escapeHtml(catName)}</span>`;
    });
    return html;
};

export const getBadgeForType = (type) => {
    const badgeTypeClass = getBadgeTypeClass(type);
    return `<span class="badge ${badgeTypeClass} text-uppercase" style="font-size: 0.68rem; padding: 4px 8px;">${escapeHtml(type)}</span>`;
};

export const renderFieldListItem = (field) => {
    const badgeTypeClass = getBadgeTypeClass(field.type);
    return `
        <li class="list-group-item d-flex justify-content-between align-items-center py-3 border-bottom px-4">
            <div style="max-width: 75%;">
                <div class="fw-semibold text-dark mb-1" style="font-size:0.9rem;">${escapeHtml(field.label)}</div>
                <div class="text-secondary small font-monospace" style="font-size:0.75rem;">Variable Name: ${escapeHtml(field.variable)}</div>
            </div>
            <span class="badge ${badgeTypeClass} border py-1.5 px-2.5 text-uppercase fw-bold" style="font-size:0.68rem;">${escapeHtml(field.type)}</span>
        </li>
    `;
};
