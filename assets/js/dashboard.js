/** Dashboard sales analytics charts. Data is rendered server-side in index.php. */
(function () {
    'use strict';

    const analytics = window.DASHBOARD_ANALYTICS || { trend: [], payments: [] };
    const accent = '#E07A2C';
    const navy = '#16233F';
    const muted = '#6B7280';

    function peso(value) {
        return '₱' + Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function renderRevenue() {
        const canvas = document.getElementById('dashboardRevenueChart');
        if (!canvas) return;
        if (!analytics.trend.length) {
            canvas.classList.add('d-none');
            document.getElementById('dashboardRevenueEmpty').classList.remove('d-none');
            return;
        }
        new Chart(canvas.getContext('2d'), {
            type: 'line',
            data: {
                labels: analytics.trend.map(row => row.date.slice(5)),
                datasets: [{ label: 'Revenue', data: analytics.trend.map(row => row.revenue), borderColor: accent, backgroundColor: 'rgba(224,122,44,.12)', fill: true, tension: .28, pointRadius: 3, pointBackgroundColor: accent }],
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false }, tooltip: { callbacks: { label: context => peso(context.raw) } } },
                scales: { x: { grid: { display: false }, ticks: { color: muted } }, y: { beginAtZero: true, ticks: { color: muted, callback: value => '₱' + Number(value).toLocaleString() }, grid: { color: 'rgba(22,35,63,.08)' } } },
            },
        });
    }

    function renderPayments() {
        const canvas = document.getElementById('dashboardPaymentChart');
        if (!canvas) return;
        if (!analytics.payments.length) {
            canvas.classList.add('d-none');
            document.getElementById('dashboardPaymentEmpty').classList.remove('d-none');
            return;
        }
        new Chart(canvas.getContext('2d'), {
            type: 'doughnut',
            data: { labels: analytics.payments.map(row => row.payment_method.toUpperCase()), datasets: [{ data: analytics.payments.map(row => row.revenue), backgroundColor: [navy, accent, '#2F9E64', '#F2A65A'], borderWidth: 0 }] },
            options: { responsive: true, cutout: '65%', plugins: { legend: { position: 'bottom', labels: { color: muted, usePointStyle: true, boxWidth: 8 } }, tooltip: { callbacks: { label: context => context.label + ': ' + peso(context.raw) } } } },
        });
    }

    document.addEventListener('DOMContentLoaded', function () { renderRevenue(); renderPayments(); });
})();
