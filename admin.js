document.addEventListener('DOMContentLoaded', function () {
    const table = document.getElementById('scd-preview');
    if (!table) return;
    const rows = Array.from(table.querySelectorAll('tbody tr'));
    const box = row => row.querySelector('input[type="checkbox"]');
    const choice = row => row.querySelector('select');
    const refresh = () => {
        const selected = rows.filter(row => box(row).checked);
        const missing = selected.filter(row => choice(row).value === '0');
        document.getElementById('scd-count').textContent = selected.length + ' selected' + (missing.length ? ' — choose a category for ' + missing.length + ' post(s)' : '');
        const counts = {};
        selected.forEach(row => {
            const select = choice(row);
            const name = select.value === '0' ? 'Needs a category' : select.selectedOptions[0].textContent;
            counts[name] = (counts[name] || 0) + 1;
        });
        document.getElementById('scd-live-distribution').textContent = 'Selected distribution: ' + (Object.entries(counts).map(([name, count]) => name + ': ' + count).join(' · ') || 'No posts selected');
        const submit = document.getElementById('scd-apply');
        submit.disabled = !selected.length || !!missing.length;
        submit.value = 'Apply to ' + selected.length + ' selected posts';
    };
    document.getElementById('scd-all').onclick = () => { rows.forEach(row => box(row).checked = true); refresh(); };
    document.getElementById('scd-none').onclick = () => { rows.forEach(row => box(row).checked = false); refresh(); };
    document.getElementById('scd-ready').onclick = () => { rows.forEach(row => box(row).checked = choice(row).value !== '0'); refresh(); };
    document.getElementById('scd-visible').onclick = () => { rows.filter(row => !row.hidden).forEach(row => box(row).checked = true); refresh(); };
    document.getElementById('scd-search').oninput = event => {
        const query = event.target.value.toLocaleLowerCase().trim();
        rows.forEach(row => row.hidden = !row.cells[1].textContent.toLocaleLowerCase().includes(query));
    };
    document.getElementById('scd-set').onclick = () => {
        const value = document.getElementById('scd-bulk').value;
        if (value) rows.filter(row => box(row).checked).forEach(row => choice(row).value = value);
        refresh();
    };
    table.addEventListener('change', refresh);
    table.closest('form').addEventListener('submit', event => {
        if (!rows.some(row => box(row).checked) || rows.some(row => box(row).checked && choice(row).value === '0')) {
            event.preventDefault(); refresh();
        }
    });
    refresh();
});
