import './bootstrap';
import Alpine from 'alpinejs';
import ApexCharts from 'apexcharts';

// flatpickr
import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';
// FullCalendar
import { Calendar } from '@fullcalendar/core';



window.Alpine = Alpine;
window.ApexCharts = ApexCharts;
window.flatpickr = flatpickr;
window.FullCalendar = Calendar;


function ancoraNormalizeWhitespace(value) {
    return String(value || '').replace(/\s+/g, ' ').trim();
}

function ancoraTitleCase(value) {
    const normalized = ancoraNormalizeWhitespace(value).toLowerCase();
    if (!normalized) return '';
    const minorWords = ['da', 'das', 'de', 'do', 'dos', 'e'];
    return normalized
        .split(/(\s+)/)
        .map((chunk, index) => {
            if (/^\s+$/.test(chunk)) return chunk;
            return chunk
                .split(/([\-/])/)
                .map((part) => {
                    if (part === '-' || part === '/') return part;
                    if (!part) return part;
                    if (index > 0 && minorWords.includes(part)) return part;
                    if (/^[ivxlcdm]+$/.test(part)) return part.toUpperCase();
                    return part.charAt(0).toUpperCase() + part.slice(1);
                })
                .join('');
        })
        .join('');
}

function ancoraApplyFieldNormalization(field) {
    if (!field || !field.name || !field.closest('form[data-clientes-form]')) return;

    const explicit = field.dataset.normalize || '';
    const name = String(field.name || '');
    let mode = explicit;

    if (!mode) {
        if (field.type === 'email' || /email/i.test(name)) {
            mode = 'lowercase';
        } else if (/(^|_)(state|uf)$/i.test(name) || /^(rg_ie|state_registration|municipal_registration|cnae|pis|ctps|unit_number)$/i.test(name)) {
            mode = 'uppercase';
        } else if (/(^|_)(name|city|street|neighborhood|profession|nationality|legal_representative|spouse_name|father_name|mother_name|role)$/i.test(name)) {
            mode = 'title';
        }
    }

    if (!mode) return;

    if (mode === 'lowercase') {
        field.value = ancoraNormalizeWhitespace(field.value).toLowerCase();
    } else if (mode === 'uppercase') {
        field.value = ancoraNormalizeWhitespace(field.value).toUpperCase();
    } else if (mode === 'title') {
        field.value = ancoraTitleCase(field.value);
    }
}

Alpine.start();

// Initialize components on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    // Map imports
    if (document.querySelector('#mapOne')) {
        import('./components/map').then(module => module.initMap());
    }

    // Chart imports
    if (document.querySelector('#chartOne')) {
        import('./components/chart/chart-1').then(module => module.initChartOne());
    }
    if (document.querySelector('#chartTwo')) {
        import('./components/chart/chart-2').then(module => module.initChartTwo());
    }
    if (document.querySelector('#chartThree')) {
        import('./components/chart/chart-3').then(module => module.initChartThree());
    }
    if (document.querySelector('#chartSix')) {
        import('./components/chart/chart-6').then(module => module.initChartSix());
    }
    if (document.querySelector('#chartEight')) {
        import('./components/chart/chart-8').then(module => module.initChartEight());
    }
    if (document.querySelector('#chartThirteen')) {
        import('./components/chart/chart-13').then(module => module.initChartThirteen());
    }

    // Calendar init
    if (document.querySelector('#calendar')) {
        import('./components/calendar-init').then(module => module.calendarInit());
    }

    document.addEventListener('blur', (event) => {
        if (!(event.target instanceof HTMLInputElement || event.target instanceof HTMLTextAreaElement)) return;
        ancoraApplyFieldNormalization(event.target);
    }, true);

    document.addEventListener('change', (event) => {
        if (!(event.target instanceof HTMLInputElement || event.target instanceof HTMLTextAreaElement || event.target instanceof HTMLSelectElement)) return;
        ancoraApplyFieldNormalization(event.target);
    });
});
