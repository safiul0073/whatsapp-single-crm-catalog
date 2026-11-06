import Alpine from 'alpinejs';
import ApexCharts from 'apexcharts';
import AirDatepicker from 'air-datepicker';
import 'air-datepicker/air-datepicker.css';
import { getChartTheme } from './charts.js';

const pickerLocale = {
    days: ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
    daysShort: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
    daysMin: ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'],
    months: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
    monthsShort: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
    today: 'Today',
    clear: 'Clear',
    dateFormat: 'MMM d, yyyy',
    firstDay: 6,
};

const isoDate = (date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
const parseIso = (value) => {
    const [y, m, d] = String(value).split('-').map(Number);
    return new Date(y, m - 1, d);
};

const jsonHeaders = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };

const readJson = (el, name) => {
    try {
        return JSON.parse(el.dataset[name] || 'null');
    } catch {
        return null;
    }
};

// Live visitors page: polls the live endpoint and opens a visitor's journey.
Alpine.data('visitorLive', (liveUrl, journeyUrl, initialCount = 0) => ({
    live: { count: initialCount, visitors: [], cities: [] },
    loaded: false,
    timer: null,
    refresh() {
        'use strict';
        fetch(liveUrl, { headers: jsonHeaders })
            .then((res) => res.json())
            .then((data) => {
                this.live = data;
                this.loaded = true;
            })
            .catch(() => {});
    },
    init() {
        'use strict';
        this.refresh();
        this.timer = setInterval(() => this.refresh(), 15000);
    },
    destroy() {
        clearInterval(this.timer);
    },
    ...visitorJourney(journeyUrl),
}));

// Visitor list page: only the journey modal.
Alpine.data('visitorJourneyModal', (journeyUrl) => visitorJourney(journeyUrl));

function visitorJourney(journeyUrl) {
    return {
        timeline: null,
        timelineVisitor: null,
        openTimeline(id, label) {
            this.timelineVisitor = label;
            this.timeline = [];
            fetch(`${journeyUrl}/${id}`, { headers: jsonHeaders })
                .then((res) => res.json())
                .then((data) => {
                    this.timeline = data.views;
                })
                .catch(() => {});
        },
    };
}

document.addEventListener('DOMContentLoaded', () => {
    'use strict';

    document.querySelectorAll('[data-visitor-bar]').forEach((bar) => {
        bar.style.width = `${Math.min(100, Math.max(0, Number(bar.dataset.width || 0)))}%`;
    });

    document.querySelectorAll('[data-visitor-range-picker]').forEach((input) => {
        const form = input.closest('form');
        const fromField = form.querySelector('[data-range-from]');
        const toField = form.querySelector('[data-range-to]');
        new AirDatepicker(input, {
            locale: pickerLocale,
            range: true,
            toggleSelected: false,
            multipleDatesSeparator: ' – ',
            maxDate: parseIso(input.dataset.max),
            selectedDates: [parseIso(fromField.value), parseIso(toField.value)],
            isRtl: document.documentElement.dir === 'rtl',
            position: 'bottom left',
            buttons: ['clear'],
            onSelect: ({ date }) => {
                if (Array.isArray(date) && date.length === 2) {
                    fromField.value = isoDate(date[0]);
                    toField.value = isoDate(date[1]);
                    form.submit();
                }
            },
        });
    });

    const theme = getChartTheme();
    const base = {
        chart: { fontFamily: 'Inter, sans-serif', toolbar: { show: false }, background: 'transparent', zoom: { enabled: false } },
        dataLabels: { enabled: false },
        grid: { borderColor: theme.grid, strokeDashArray: 4 },
        legend: { labels: { colors: theme.text } },
        tooltip: { theme: document.documentElement.classList.contains('dark') ? 'dark' : 'light' },
    };

    const trend = document.querySelector('[data-visitor-trend]');
    if (trend) {
        const daily = readJson(trend, 'series') || { labels: [], views: [], visitors: [] };
        new ApexCharts(trend, {
            ...base,
            chart: { ...base.chart, type: 'area', height: 260 },
            series: [
                { name: trend.dataset.viewsLabel || 'Page views', data: daily.views },
                { name: trend.dataset.visitorsLabel || 'Visitors', data: daily.visitors },
            ],
            colors: ['#1faa53', '#3b82f6'],
            stroke: { curve: 'smooth', width: 2 },
            fill: { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0.02 } },
            xaxis: {
                categories: daily.labels,
                axisBorder: { show: false },
                axisTicks: { show: false },
                tickAmount: Math.min(10, daily.labels.length),
                labels: { rotate: 0, hideOverlappingLabels: true, style: { colors: theme.text, fontSize: '11px' } },
            },
            yaxis: { min: 0, forceNiceScale: true, labels: { formatter: (v) => Math.round(v), style: { colors: theme.text } } },
        }).render();
    }

});
