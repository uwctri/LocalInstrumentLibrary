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

export const getCookie = (name) => {
    const ca = document.cookie.split(';');
    for (let i = 0; i < ca.length; i++) {
        let c = ca[i].trim();
        if (c.indexOf(name + "=") === 0) return c.substring(name.length + 1);
    }
    return null;
};

export const setCookie = (name, value, days) => {
    let expires = days ? "; expires=" + new Date(Date.now() + days * 864e5).toUTCString() : "";
    document.cookie = name + "=" + (value || "") + expires + "; path=/; SameSite=Lax";
};

export const getCardsPerRow = () => {
    const w = window.innerWidth;
    return w >= 2000 ? 4 : w >= 1600 ? 3 : w >= 1100 ? 2 : 1;
};

export const renderCategoryBadgesHtml = (categories, categoryMapping) => {
    let html = "";
    categories.forEach(cat => {
        const map = categoryMapping.find(c => c.name === cat);
        html += `<span class="badge ${map ? map.class : "bg-secondary"} me-1 mb-1 px-2.5 py-1.5 text-uppercase fw-bold" style="font-size: 0.68rem; letter-spacing: 0.05em;">${escapeHtml(cat)}</span>`;
    });
    return html;
};

export const downloadMockFile = (id, version, title, items = []) => {
    const item = items.find(i => i.id === id);
    const fields = item ? item.fields : [];
    let csvContent = "Variable / Field Name,Form Name,Section Header,Field Type,Field Label,Choices, Field Notes,Text Validation Type OR Show Slider Number,Text Validation Min,Text Validation Max,Identifier?,Branching Logic,Required Field?,Custom Alignment,Question Number,Matrix Group Name,Matrix Ranking?,Field Annotation\n";
    if (fields && fields.length) {
        fields.forEach(f => {
            csvContent += `"${(f.variable || 'field').replace(/"/g, '""')}","instrument","","${(f.type || 'text').replace(/"/g, '""')}","${(f.label || 'Field Label').replace(/"/g, '""')}","","","","","","","","","","","","",""\n`;
        });
    } else {
        csvContent += `"demo_variable","instrument","","text","Demo Question Label","","","","","","","","","","","","",""\n`;
    }
    const url = URL.createObjectURL(new Blob([csvContent], { type: "text/csv;charset=utf-8;" })), a = document.createElement("a");
    a.href = url; a.download = `${id}_v${version}_instrument.csv`;
    document.body.appendChild(a); a.click(); document.body.removeChild(a);
    setTimeout(() => URL.revokeObjectURL(url), 1000);
};
