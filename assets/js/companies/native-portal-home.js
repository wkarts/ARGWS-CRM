(function () {
  'use strict';
  var source = document.getElementById('native-portal-home-chart');
  var canvas = document.getElementById('client-home-chart');
  if (!source || !canvas || typeof window.Chart !== 'function') return;
  var config = JSON.parse(source.textContent);
  var year = document.getElementById('payments_year');
  var currency = document.getElementById('currency');
  var chart;
  var statuses = [1, 2, 4, 3];
  var colors = {1: '#fc142b', 2: '#84c529', 3: '#ff6f00', 4: '#ff6f00'};
  function draw() {
    var selectedYear = year ? year.value : '';
    var selectedCurrency = currency ? currency.value : '';
    var selected = ((config.data || {})[selectedYear] || {})[selectedCurrency] || {};
    var datasets = statuses.map(function (status) {
      var values = selected[status] || {};
      return {
        label: config.labels[status],
        backgroundColor: colors[status],
        borderColor: colors[status],
        borderWidth: 1,
        data: Array.from({length: 12}, function (_, index) {
          var amount = Number(values[index + 1] || 0);
          return Number.isFinite(amount) ? amount : 0;
        })
      };
    });
    if (chart) chart.destroy();
    chart = new window.Chart(canvas, {type: 'bar', data: {labels: config.months, datasets: datasets}, options: {responsive: true, maintainAspectRatio: false}});
  }
  if (year) year.addEventListener('change', draw);
  if (currency) currency.addEventListener('change', draw);
  draw();
}());
