/*
 * Dashboard charts (plain SVG/HTML, no chart library). Same helpers and .viz-* styles as lokal-pos.
 * Every string that comes from the data is written with textContent, never innerHTML.
 * Without view-costs the server sends no rupiah values, so charts fall back to counts.
 */

const data = JSON.parse(document.getElementById('dashboard-data').textContent);
const tooltip = document.getElementById('viz-tooltip');
const SVG_NS = 'http://www.w3.org/2000/svg';
const costs = data.show_costs;

const number = (value) => Math.round(value).toLocaleString('id-ID');
const rp = (value) => `${value < 0 ? '−' : ''}Rp ${number(Math.abs(value))}`;
const trimmed = (value) => value.toLocaleString('id-ID', { maximumFractionDigits: 1 });
const compact = (value) => {
    if (Math.abs(value) >= 1_000_000) return `${trimmed(value / 1_000_000)} jt`;
    if (Math.abs(value) >= 1_000) return `${trimmed(value / 1_000)} rb`;
    return String(value);
};

const toDate = (iso) => new Date(`${iso}T00:00:00`);
const shortDate = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short' });
const longDate = new Intl.DateTimeFormat('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

// Colour follows the entity, never its rank.
const FLOW_COLORS = {
    purchase: 'var(--viz-series-3)',
    usage: 'var(--viz-series-1)',
    waste: 'var(--viz-series-2)',
    adjustment: 'var(--viz-series-4)',
};

/* ---------- small DOM helpers ---------- */

const el = (tag, className, text) => {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
};

const svgEl = (tag, attrs = {}, parent) => {
    const node = document.createElementNS(SVG_NS, tag);
    Object.entries(attrs).forEach(([key, value]) => node.setAttribute(key, value));
    parent?.appendChild(node);
    return node;
};

const emptyState = (body, message) => body.replaceChildren(el('p', 'viz-empty', message));

/* ---------- tooltip ---------- */

function showTooltip(event, title, rows) {
    tooltip.replaceChildren(el('div', 'viz-tooltip-title', title));
    rows.forEach(({ label, value, color }) => {
        const row = el('div', 'viz-tooltip-row');
        if (color) {
            const key = el('span', 'viz-tooltip-key');
            key.style.background = color;
            row.appendChild(key);
        }
        row.appendChild(el('strong', 'viz-tooltip-value', value));
        row.appendChild(el('span', 'viz-tooltip-label', label));
        tooltip.appendChild(row);
    });
    tooltip.classList.remove('hidden');

    const { width, height } = tooltip.getBoundingClientRect();
    const left = Math.min(event.clientX + 14, window.innerWidth - width - 8);
    const top = event.clientY - height - 14 < 8 ? event.clientY + 18 : event.clientY - height - 14;
    tooltip.style.left = `${Math.max(8, left)}px`;
    tooltip.style.top = `${top}px`;
}

const hideTooltip = () => tooltip.classList.add('hidden');

/** Show the tooltip for an element on hover, and on keyboard focus (anchored to the element). */
function attachTooltip(node, build) {
    node.addEventListener('pointermove', (event) => showTooltip(event, ...build()));
    node.addEventListener('pointerleave', hideTooltip);
    node.addEventListener('focus', () => {
        const rect = node.getBoundingClientRect();
        showTooltip({ clientX: rect.left + rect.width / 2, clientY: rect.top }, ...build());
    });
    node.addEventListener('blur', hideTooltip);
}

/* ---------- line chart ---------- */

/** Round up to a "nice" axis maximum and return evenly spaced ticks. */
function niceScale(max, divisions = 4) {
    const rough = Math.max(max, 1) / divisions;
    const magnitude = 10 ** Math.floor(Math.log10(rough));
    const step = [1, 2, 2.5, 5, 10].map((m) => m * magnitude).find((candidate) => candidate >= rough);
    const top = step * Math.ceil(Math.max(max, 1) / step);
    const ticks = [];
    for (let value = 0; value <= top + step / 1000; value += step) ticks.push(Math.round(value * 100) / 100);
    return { top, ticks };
}

function renderTrend(body) {
    const points = data.trend;
    const metric = costs ? 'usage_cost' : 'orders';
    const format = costs ? rp : number;
    if (points.every((point) => point[metric] === 0)) {
        return emptyState(body, 'Belum ada penjualan pada periode ini.');
    }

    const width = Math.max(body.clientWidth, 320);
    const height = 240;
    const margin = { top: 20, right: 20, bottom: 28, left: 52 };
    const plotWidth = width - margin.left - margin.right;
    const plotHeight = height - margin.top - margin.bottom;
    const { top, ticks } = niceScale(Math.max(...points.map((point) => point[metric])));
    const x = (index) => margin.left + (points.length === 1 ? plotWidth / 2 : (index / (points.length - 1)) * plotWidth);
    const y = (value) => margin.top + plotHeight - (value / top) * plotHeight;

    const svg = svgEl('svg', {
        viewBox: `0 0 ${width} ${height}`, class: 'viz-svg', role: 'img', tabindex: 0,
        'aria-label': `Grafik garis ${costs ? 'biaya bahan terpakai' : 'pesanan diproses'} per hari, ${points.length} hari terakhir. Gunakan tombol panah untuk membaca nilai, atau buka tampilan tabel.`,
    });

    ticks.forEach((tick) => {
        svgEl('line', { x1: margin.left, x2: width - margin.right, y1: y(tick), y2: y(tick), class: tick === 0 ? 'viz-baseline' : 'viz-grid' }, svg);
        svgEl('text', { x: margin.left - 8, y: y(tick) + 4, 'text-anchor': 'end', class: 'viz-axis' }, svg).textContent = compact(tick);
    });

    const labelEvery = Math.ceil(points.length / 6);
    points.forEach((point, index) => {
        if (index % labelEvery !== 0 && index !== points.length - 1) return;
        if (index !== points.length - 1 && points.length - 1 - index < labelEvery / 2) return;
        svgEl('text', { x: x(index), y: height - 8, 'text-anchor': 'middle', class: 'viz-axis' }, svg).textContent = shortDate.format(toDate(point.date));
    });

    const linePath = points.map((point, index) => `${index === 0 ? 'M' : 'L'}${x(index)},${y(point[metric])}`).join(' ');
    svgEl('path', { d: `${linePath} L${x(points.length - 1)},${y(0)} L${x(0)},${y(0)} Z`, class: 'viz-area' }, svg);
    svgEl('path', { d: linePath, class: 'viz-line' }, svg);

    // Selective label: only the latest value sits on the chart; everything else is in the tooltip/table.
    const last = points[points.length - 1];
    svgEl('circle', { cx: x(points.length - 1), cy: y(last[metric]), r: 4, class: 'viz-dot' }, svg);
    svgEl('text', { x: x(points.length - 1), y: y(last[metric]) - 10, 'text-anchor': 'end', class: 'viz-value' }, svg).textContent = format(last[metric]);

    const crosshair = svgEl('line', { y1: margin.top, y2: y(0), class: 'viz-crosshair', visibility: 'hidden' }, svg);
    const hoverDot = svgEl('circle', { r: 4, class: 'viz-dot', visibility: 'hidden' }, svg);
    const hit = svgEl('rect', { x: margin.left, y: margin.top, width: plotWidth, height: plotHeight, fill: 'transparent' }, svg);

    let active = points.length - 1;
    const select = (index, event) => {
        active = Math.min(points.length - 1, Math.max(0, index));
        const point = points[active];
        crosshair.setAttribute('x1', x(active));
        crosshair.setAttribute('x2', x(active));
        hoverDot.setAttribute('cx', x(active));
        hoverDot.setAttribute('cy', y(point[metric]));
        [crosshair, hoverDot].forEach((node) => node.setAttribute('visibility', 'visible'));

        const rect = svg.getBoundingClientRect();
        const anchor = event ?? {
            clientX: rect.left + (x(active) / width) * rect.width,
            clientY: rect.top + (y(point[metric]) / height) * rect.height,
        };
        const rows = costs
            ? [
                { label: 'Bahan terpakai', value: rp(point.usage_cost), color: 'var(--viz-series-1)' },
                { label: 'Pesanan', value: number(point.orders) },
                { label: 'Waste', value: rp(point.waste_cost) },
            ]
            : [{ label: 'Pesanan', value: number(point.orders), color: 'var(--viz-series-1)' }];
        showTooltip(anchor, longDate.format(toDate(point.date)), rows);
    };
    const clear = () => {
        [crosshair, hoverDot].forEach((node) => node.setAttribute('visibility', 'hidden'));
        hideTooltip();
    };

    hit.addEventListener('pointermove', (event) => {
        const rect = svg.getBoundingClientRect();
        const localX = ((event.clientX - rect.left) / rect.width) * width;
        select(points.length === 1 ? 0 : Math.round(((localX - margin.left) / plotWidth) * (points.length - 1)), event);
    });
    hit.addEventListener('pointerleave', clear);
    svg.addEventListener('blur', clear);
    svg.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowRight') select(active + 1);
        else if (event.key === 'ArrowLeft') select(active - 1);
        else if (event.key === 'Home') select(0);
        else if (event.key === 'End') select(points.length - 1);
        else return;
        event.preventDefault();
    });

    body.replaceChildren(svg);
}

/* ---------- horizontal bars (HTML) ---------- */

/** label | bar | value. Bars are 10px thick with a rounded data end; length uses |value|. */
function renderHorizontalBars(body, rows, emptyMessage) {
    if (rows.length === 0 || rows.every((row) => row.value === 0)) return emptyState(body, emptyMessage);

    const max = Math.max(...rows.map((row) => Math.abs(row.value)));
    const list = el('ul', 'viz-hbars');

    rows.forEach((row) => {
        const item = el('li', 'viz-hbar');
        item.tabIndex = 0;
        item.appendChild(el('span', 'viz-hbar-label', row.label)).title = row.label;

        const track = el('span', 'viz-hbar-track');
        const bar = el('span', 'viz-hbar-fill');
        bar.style.width = `${Math.max(2, (Math.abs(row.value) / max) * 100)}%`;
        if (row.color) bar.style.background = row.color;
        track.appendChild(bar);
        item.appendChild(track);

        item.appendChild(el('span', 'viz-hbar-value', row.display));
        attachTooltip(item, () => [row.label, row.tooltip]);
        list.appendChild(item);
    });

    body.replaceChildren(list);
}

/* ---------- table views (the accessible twin of every chart) ---------- */

function buildTable(columns, rows) {
    const table = el('table', 'table table-sm w-full');
    const head = el('tr', 'text-xs text-base-content/60');
    columns.forEach((column, index) => {
        const th = el('th', index === 0 ? '' : 'text-right', column);
        th.scope = 'col';
        head.appendChild(th);
    });
    table.appendChild(el('thead')).appendChild(head);

    const body = el('tbody');
    rows.forEach((cells) => {
        const tr = el('tr');
        cells.forEach((cell, index) => tr.appendChild(el('td', index === 0 ? 'font-medium' : 'text-right tabular-nums', cell)));
        body.appendChild(tr);
    });
    table.appendChild(body);
    return table;
}

/* ---------- chart registry ---------- */

const qty = (value, unit) => `${trimmed(value)} ${unit}`;
const days = (value) => (value < 1 ? '< 1 hari' : `${trimmed(value)} hari`);

const charts = {
    trend: {
        render: renderTrend,
        responsive: true,
        table: () => buildTable(
            costs ? ['Tanggal', 'Bahan terpakai', 'Pesanan', 'Waste'] : ['Tanggal', 'Pesanan'],
            data.trend.map((p) => (costs
                ? [longDate.format(toDate(p.date)), rp(p.usage_cost), number(p.orders), rp(p.waste_cost)]
                : [longDate.format(toDate(p.date)), number(p.orders)]))
        ),
    },
    flow: {
        render: (body) => renderHorizontalBars(body, data.flow.map((row) => ({
            label: row.label,
            value: costs ? row.value : row.count,
            color: FLOW_COLORS[row.key],
            display: costs ? `${row.value < 0 ? '−' : row.key === 'adjustment' && row.value > 0 ? '+' : ''}Rp ${compact(Math.abs(row.value))}` : number(row.count),
            tooltip: [
                ...(costs ? [{ label: 'Nilai', value: rp(row.value), color: FLOW_COLORS[row.key] }] : []),
                { label: 'Pergerakan', value: number(row.count), color: costs ? undefined : FLOW_COLORS[row.key] },
            ],
        })), 'Belum ada pergerakan stok pada periode ini.'),
        table: () => buildTable(
            costs ? ['Jenis', 'Nilai', 'Pergerakan'] : ['Jenis', 'Pergerakan'],
            data.flow.map((row) => (costs ? [row.label, rp(row.value), number(row.count)] : [row.label, number(row.count)]))
        ),
    },
    cover: {
        render: (body) => renderHorizontalBars(body, data.cover.map((row) => ({
            label: row.name,
            value: Math.max(row.days, 0.05),
            display: days(row.days),
            tooltip: [
                { label: 'Perkiraan cukup', value: days(row.days), color: 'var(--viz-series-1)' },
                { label: 'Stok saat ini', value: qty(row.stock, row.unit) },
                { label: 'Pemakaian per hari', value: qty(row.daily, row.unit) },
                { label: 'Stok minimum', value: qty(row.min, row.unit) },
            ],
        })), 'Belum ada pemakaian bahan 7 hari terakhir.'),
        table: () => buildTable(
            ['Bahan', 'Perkiraan cukup', 'Stok', 'Pemakaian/hari'],
            data.cover.map((row) => [row.name, days(row.days), qty(row.stock, row.unit), qty(row.daily, row.unit)])
        ),
    },
    waste: {
        render: (body) => renderHorizontalBars(body, data.waste.map((row) => ({
            label: row.label,
            value: costs ? row.value : row.count,
            display: costs ? `Rp ${compact(row.value)}` : `${number(row.count)}×`,
            tooltip: [
                ...(costs ? [{ label: 'Nilai', value: rp(row.value), color: 'var(--viz-series-1)' }] : []),
                { label: 'Catatan', value: number(row.count), color: costs ? undefined : 'var(--viz-series-1)' },
            ],
        })), 'Tidak ada waste pada periode ini.'),
        table: () => buildTable(
            costs ? ['Alasan', 'Nilai', 'Catatan'] : ['Alasan', 'Catatan'],
            data.waste.map((row) => (costs ? [row.label, rp(row.value), number(row.count)] : [row.label, number(row.count)]))
        ),
    },
};

document.querySelectorAll('[data-chart]').forEach((figure) => {
    const chart = charts[figure.dataset.chart];
    if (!chart) return;

    const body = figure.querySelector('[data-chart-body]');
    const tableBody = figure.querySelector('[data-table-body]');
    const toggle = figure.querySelector('[data-table-toggle]');
    const draw = () => chart.render(body);

    draw();
    tableBody.replaceChildren(chart.table());

    toggle.addEventListener('click', () => {
        const showTable = toggle.getAttribute('aria-pressed') !== 'true';
        toggle.setAttribute('aria-pressed', String(showTable));
        toggle.querySelector('span').textContent = showTable ? 'Grafik' : 'Tabel';
        body.classList.toggle('hidden', showTable);
        tableBody.classList.toggle('hidden', !showTable);
        hideTooltip();
        if (!showTable && chart.responsive) draw();
    });

    if (chart.responsive) {
        let width = body.clientWidth;
        new ResizeObserver(() => {
            if (body.classList.contains('hidden') || Math.abs(body.clientWidth - width) < 2) return;
            width = body.clientWidth;
            requestAnimationFrame(draw);
        }).observe(body);
    }
});

window.lucide?.createIcons();
