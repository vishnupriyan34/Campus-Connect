// Placement Analytics Chart.js Rendering

document.addEventListener('DOMContentLoaded', () => {
  // Check if Chart.js is loaded
  if (typeof Chart === 'undefined') {
    console.error("Chart.js is not loaded.");
    return;
  }
  
  // Resolve prefix
  const inSubFolder = window.location.pathname.includes('/student/') || 
                      window.location.pathname.includes('/staff/') || 
                      window.location.pathname.includes('/admin/');
  const apiPath = inSubFolder ? '../api/analytics.php' : 'api/analytics.php';
  
  // Global chart instances (to allow destroying before redraw)
  let branchChartInst = null;
  let packageChartInst = null;
  let participationChartInst = null;
  let funnelChartInst = null;

  // Chart Styling Helper Options
  const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        labels: {
          color: '#94a3b8',
          font: { family: 'Outfit', size: 12 }
        }
      }
    },
    scales: {
      x: {
        grid: { color: 'rgba(255, 255, 255, 0.05)' },
        ticks: { color: '#94a3b8', font: { family: 'Outfit' } }
      },
      y: {
        grid: { color: 'rgba(255, 255, 255, 0.05)' },
        ticks: { color: '#94a3b8', font: { family: 'Outfit' } }
      }
    }
  };

  // 1. Render Branch-wise Selections (Bar Chart)
  function loadBranchSelections() {
    const ctx = document.getElementById('branchSelectionsChart');
    if (!ctx) return;
    
    fetch(`${apiPath}?type=branch_selections`)
      .then(r => r.json())
      .then(res => {
        if (!res.success) return;
        if (branchChartInst) branchChartInst.destroy();
        
        branchChartInst = new Chart(ctx, {
          type: 'bar',
          data: {
            labels: res.labels,
            datasets: [{
              label: 'Selected Students',
              data: res.data,
              backgroundColor: 'rgba(99, 102, 241, 0.65)',
              borderColor: '#6366f1',
              borderWidth: 1,
              borderRadius: 6
            }]
          },
          options: chartOptions
        });
      });
  }

  // 2. Render Average Packages (Line Chart)
  function loadAveragePackages() {
    const ctx = document.getElementById('avgPackageChart');
    if (!ctx) return;
    
    fetch(`${apiPath}?type=average_package`)
      .then(r => r.json())
      .then(res => {
        if (!res.success) return;
        if (packageChartInst) packageChartInst.destroy();
        
        packageChartInst = new Chart(ctx, {
          type: 'line',
          data: {
            labels: res.labels,
            datasets: [{
              label: 'Average Package (LPA)',
              data: res.data,
              backgroundColor: 'rgba(6, 182, 212, 0.15)',
              borderColor: '#06b6d4',
              borderWidth: 3,
              fill: true,
              tension: 0.3,
              pointBackgroundColor: '#06b6d4'
            }]
          },
          options: chartOptions
        });
      });
  }

  // 3. Render Participation Rate (Doughnut)
  function loadParticipationRate() {
    const ctx = document.getElementById('participationChart');
    if (!ctx) return;
    
    fetch(`${apiPath}?type=participation`)
      .then(r => r.json())
      .then(res => {
        if (!res.success) return;
        if (participationChartInst) participationChartInst.destroy();
        
        participationChartInst = new Chart(ctx, {
          type: 'doughnut',
          data: {
            labels: res.labels,
            datasets: [{
              data: res.data,
              backgroundColor: [
                'rgba(16, 185, 129, 0.7)', // emerald green
                'rgba(239, 68, 68, 0.65)'  // rose red
              ],
              borderColor: '#131b2e',
              borderWidth: 2
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              legend: {
                position: 'right',
                labels: {
                  color: '#94a3b8',
                  font: { family: 'Outfit', size: 12 }
                }
              }
            }
          }
        });
      });
  }

  // 4. Render Drive Conversion Funnel (Horizontal Bar)
  function loadDriveFunnel(driveId = 0) {
    const ctx = document.getElementById('driveFunnelChart');
    if (!ctx) return;
    
    fetch(`${apiPath}?type=drive_funnel&drive_id=${driveId}`)
      .then(r => r.json())
      .then(res => {
        if (!res.success) return;
        if (funnelChartInst) funnelChartInst.destroy();
        
        funnelChartInst = new Chart(ctx, {
          type: 'bar',
          data: {
            labels: res.labels,
            datasets: [{
              label: 'Candidates Count',
              data: res.data,
              backgroundColor: [
                'rgba(99, 102, 241, 0.6)', // Applied
                'rgba(245, 158, 11, 0.6)', // Shortlisted
                'rgba(16, 185, 129, 0.6)'  // Selected
              ],
              borderColor: [
                '#6366f1',
                '#f59e0b',
                '#10b981'
              ],
              borderWidth: 1,
              borderRadius: 6
            }]
          },
          options: {
            indexAxis: 'y', // Makes the bar chart horizontal
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              legend: { display: false }
            },
            scales: {
              x: {
                grid: { color: 'rgba(255, 255, 255, 0.05)' },
                ticks: { color: '#94a3b8', font: { family: 'Outfit' }, stepSize: 1 }
              },
              y: {
                grid: { display: false },
                ticks: { color: '#94a3b8', font: { family: 'Outfit' } }
              }
            }
          }
        });
      });
  }

  // Set up drive funnel dropdown trigger
  const funnelSelect = document.getElementById('funnelDriveSelect');
  if (funnelSelect) {
    funnelSelect.addEventListener('change', (e) => {
      loadDriveFunnel(e.target.value);
    });
  }

  // Initial load
  loadBranchSelections();
  loadAveragePackages();
  loadParticipationRate();
  
  const initialDriveId = funnelSelect ? funnelSelect.value : 0;
  loadDriveFunnel(initialDriveId);
});
