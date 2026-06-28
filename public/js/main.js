function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
  document.getElementById('overlay').classList.toggle('show');
}

function konfirmasi(pesan) {
  return confirm(pesan);
}

// Inisialisasi chart dashboard — data di-embed dari server sebagai variabel global
if (typeof chartData7 !== 'undefined') {
  new Chart(document.getElementById('chart7'), {
    type: 'bar',
    data: {
      labels: chartData7.labels,
      datasets: [{
        label: 'Net stok (balok)',
        data: chartData7.values,
        backgroundColor: '#2563eb',
        borderRadius: 4,
      }]
    },
    options: { responsive: true, plugins: { legend: { display: false } } }
  });
}

if (typeof chartData30 !== 'undefined') {
  new Chart(document.getElementById('chart30'), {
    type: 'line',
    data: {
      labels: chartData30.labels,
      datasets: [{
        label: 'Net stok (balok)',
        data: chartData30.values,
        borderColor: '#2563eb',
        backgroundColor: 'rgba(37,99,235,.1)',
        fill: true,
        tension: .35,
        pointRadius: 2,
      }]
    },
    options: { responsive: true, plugins: { legend: { display: false } } }
  });
}
