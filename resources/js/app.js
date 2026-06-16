import * as bootstrap from 'bootstrap';
import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';
import TomSelect from 'tom-select';

window.bootstrap = bootstrap;
window.Chart = Chart;
window.TomSelect = TomSelect;

window.Alpine = Alpine;
Alpine.start();

// Toggle sidebar pada skrin kecil
document.getElementById('sidebarToggle')?.addEventListener('click', () => {
    document.getElementById('sidebar')?.classList.toggle('show');
});

// Aktifkan semua dropdown COA boleh-cari
document.querySelectorAll('select[data-searchable]').forEach((el) => {
    new TomSelect(el, {
        create: false,
        sortField: { field: 'text', direction: 'asc' },
        placeholder: el.dataset.placeholder || '-- Pilih --',
    });
});
