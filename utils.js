export const compareSemanticVersions = (v1, v2) => {
    const clean1 = String(v1 || '').replace(/^v/i, '').split('.').map(Number);
    const clean2 = String(v2 || '').replace(/^v/i, '').split('.').map(Number);
    for (let i = 0; i < Math.max(clean1.length, clean2.length); i++) {
        const p1 = clean1[i] || 0, p2 = clean2[i] || 0;
        if (p1 > p2) return 1;
        if (p1 < p2) return -1;
    }
    return 0;
};

export const escapeHtml = (str) => {
    if (typeof str !== 'string') return '';
    return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
};

export const getBadgeTypeClass = (type) => type ? 'type-' + String(type).toLowerCase().replace(/[_ ]/g, '').trim() : 'type-default';

export const renderFieldListItem = (field) => `
    <li class="list-group-item d-flex justify-content-between align-items-center py-3 border-bottom px-4">
        <div style="max-width: 75%;">
            <div class="fw-semibold text-dark mb-1" style="font-size:0.9rem;">${escapeHtml(field.label)}</div>
            <div class="text-secondary small font-monospace" style="font-size:0.75rem;">Variable Name: ${escapeHtml(field.variable)}</div>
        </div>
        <span class="badge ${getBadgeTypeClass(field.type)} border py-1.5 px-2.5 text-uppercase fw-bold" style="font-size:0.68rem;">${escapeHtml(field.type)}</span>
    </li>
`;
