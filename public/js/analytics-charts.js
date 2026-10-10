(function () {
	'use strict';

	var configElement = document.getElementById('analytics-chart-config');
	if (!configElement || !window.ApexCharts) { return; }

	var config = JSON.parse(configElement.textContent);
	var labels = config.labels || {};
	var locale = config.locale || 'en-TZ';
	var currency = config.currency || 'TZS';

	function formatMoney(minor) {
		return currency + ' ' + (Number(minor || 0) / 100).toLocaleString(locale, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
	}

	function formatDate(value) {
		return new Date(value + 'T00:00:00').toLocaleDateString(locale, { month: 'short', day: 'numeric' });
	}

	function render(id, options) {
		var element = document.getElementById(id);
		if (!element || element.dataset.chartReady || element.getBoundingClientRect().width === 0) { return; }
		element.dataset.chartReady = 'true';
		new window.ApexCharts(element, options).render();
	}

	function baseChart(type, height) {
		return {
			chart: { type: type, height: height, toolbar: { show: false }, fontFamily: 'inherit', animations: { enabled: true, easing: 'easeinout', speed: 450 } },
			colors: ['#FE9F43', '#0E9384', '#155EEF', '#3EB780', '#E04F16'],
			grid: { borderColor: '#E6EAED', strokeDashArray: 4, padding: { left: 8, right: 8 } },
			dataLabels: { enabled: false },
			legend: { fontSize: '13px', markers: { radius: 10 } },
			noData: { text: labels.no_data || 'No data' }
		};
	}

	function initDashboardChart() {
		var dataElement = document.getElementById('dashboard-chart-data');
		if (!dataElement) { return; }
		var days = JSON.parse(dataElement.textContent);
		var options = baseChart('area', 320);
		options.series = [{ name: labels.sales || 'Sales', data: days.map(function (day) { return day.total; }) }];
		options.stroke = { curve: 'smooth', width: 3 };
		options.fill = { type: 'gradient', gradient: { shadeIntensity: 0.2, opacityFrom: 0.34, opacityTo: 0.04, stops: [0, 90, 100] } };
		options.markers = { size: 4, strokeWidth: 2, hover: { size: 6 } };
		options.xaxis = { categories: days.map(function (day) { return day.date; }), labels: { formatter: formatDate, rotate: 0, hideOverlappingLabels: true } };
		options.yaxis = { min: 0, forceNiceScale: true, labels: { formatter: formatMoney } };
		options.tooltip = { y: { formatter: formatMoney } };
		render('dashboard-sales-chart', options);
	}

	function initReportCharts() {
		var dataElement = document.getElementById('reports-chart-data');
		if (!dataElement) { return; }
		var data = JSON.parse(dataElement.textContent);
		var days = data.days || [];
		var dateLabelStep = Math.max(1, Math.ceil(days.length / 8));

		var sales = baseChart('line', 320);
		sales.series = [
			{ name: labels.sales || 'Sales', type: 'column', data: days.map(function (day) { return day.total; }) },
			{ name: labels.orders || 'Transactions', type: 'line', data: days.map(function (day) { return day.count; }) }
		];
		sales.stroke = { curve: 'smooth', width: [0, 3] };
		sales.plotOptions = { bar: { columnWidth: '48%', borderRadius: 3 } };
		sales.markers = { size: [0, 3], hover: { size: 5 } };
		sales.xaxis = {
			categories: days.map(function (day) { return day.date; }),
			labels: {
				formatter: function (value, timestamp, options) {
					var index = options && Number.isInteger(options.dataPointIndex) ? options.dataPointIndex : days.findIndex(function (day) { return day.date === value; });
					return index >= 0 && (index % dateLabelStep === 0 || index === days.length - 1) ? formatDate(value) : '';
				},
				rotate: 0,
				hideOverlappingLabels: true
			}
		};
		sales.yaxis = [
			{ min: 0, forceNiceScale: true, labels: { formatter: formatMoney } },
			{ opposite: true, min: 0, forceNiceScale: true, labels: { formatter: function (value) { return Math.round(value).toLocaleString(locale); } } }
		];
		sales.tooltip = { shared: true, y: { formatter: function (value, context) { return context.seriesIndex === 0 ? formatMoney(value) : Math.round(value).toLocaleString(locale); } } };
		render('report-sales-chart', sales);

		var methods = data.methods || [];
		if (methods.length) {
			var payments = baseChart('donut', 320);
			payments.series = methods.map(function (method) { return method.amount; });
			payments.labels = methods.map(function (method) { return method.label; });
			payments.stroke = { width: 2, colors: ['#ffffff'] };
			payments.plotOptions = { pie: { donut: { size: '68%', labels: { show: true, total: { show: true, label: labels.payments || 'Payments', formatter: function (chart) { return formatMoney(chart.globals.seriesTotals.reduce(function (total, value) { return total + value; }, 0)); } } } } } };
			payments.legend = { position: 'bottom', fontSize: '12px', markers: { radius: 10 }, itemMargin: { horizontal: 8, vertical: 4 } };
			payments.tooltip = { y: { formatter: formatMoney } };
			render('report-payments-chart', payments);
		}

		if (data.profit) {
			var profit = baseChart('bar', 300);
			profit.series = [{ name: labels.revenue || 'Revenue', data: [data.profit.revenue, data.profit.cost, data.profit.expenses, data.profit.gross_profit, data.profit.net_profit] }];
			profit.xaxis = { categories: [labels.revenue, labels.cost, labels.expenses, labels.gross_profit, labels.net_profit], min: 0, forceNiceScale: true, labels: { formatter: formatMoney } };
			profit.yaxis = { labels: { maxWidth: 150, formatter: function (value) { return value; } } };
			profit.plotOptions = { bar: { horizontal: true, distributed: true, borderRadius: 4, barHeight: '52%' } };
			profit.colors = ['#155EEF', '#E04F16', '#FE9F43', '#0E9384', '#3EB780'];
			profit.tooltip = { y: { formatter: formatMoney } };
			render('report-profit-chart', profit);
		}
	}

	function initVisibleCharts() {
		initDashboardChart();
		initReportCharts();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initVisibleCharts, { once: true });
	} else {
		window.requestAnimationFrame(initVisibleCharts);
	}
	document.addEventListener('shown.bs.tab', initVisibleCharts);
})();