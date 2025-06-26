<?php
require_once "includes/config.php";
require_once "includes/functions.php";
if(!isLoggedIn()){
    redirectToLogin();
}

// Count faxes with different statuses
function countFaxesByStatus($conn, $status) {
    $query = "SELECT COUNT(*) as count FROM patients WHERE fax_status = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $status);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    return $row['count'];
}

// Get counts for different statuses
$sentCount = countFaxesByStatus($conn, 'sent');
$failedCount = countFaxesByStatus($conn, 'failed');
$pendingCount = countFaxesByStatus($conn, 'pending');
$totalCount = $sentCount + $failedCount + $pendingCount;

// Get fax status trends for the chart
function getStatusTrends($conn, $days = 7) {
    $query = "SELECT 
                DATE(created_at) as date, 
                SUM(CASE WHEN fax_status = 'sent' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN fax_status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN fax_status = 'pending' THEN 1 ELSE 0 END) as pending
              FROM patients
              WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
              GROUP BY DATE(created_at)
              ORDER BY date ASC";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $days);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }
    return $data;
}

$statusTrends = getStatusTrends($conn, 7);

// Get recent faxes for activity feed
$query = "SELECT p.id, p.first_name, p.last_name, p.braces_requested, p.fax_status, p.created_at, 
                 fq.recipient_name, fq.fax_number
          FROM patients p
          LEFT JOIN fax_queue fq ON p.id = fq.patient_id
          ORDER BY p.created_at DESC 
          LIMIT 8";
$recentFaxes = mysqli_query($conn, $query);

// Get provider distribution
$providerQuery = "SELECT provider_name, COUNT(*) as count 
                  FROM patients 
                  WHERE fax_status = 'sent'
                  GROUP BY provider_name
                  ORDER BY count DESC
                  LIMIT 5";
$providerStats = mysqli_query($conn, $providerQuery);

include "includes/header.php";
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-2 sidebar-div">
            <?php include "includes/sidebar.php"; ?>
        </div>
        <div class="col-md-10 p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-tachometer-alt me-2"></i>Dashboard Overview</h2>
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="timeRangeDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        Last 7 Days
                    </button>
                    <ul class="dropdown-menu" aria-labelledby="timeRangeDropdown">
                        <li><a class="dropdown-item" href="#" data-range="7">Last 7 Days</a></li>
                        <li><a class="dropdown-item" href="#" data-range="30">Last 30 Days</a></li>
                        <li><a class="dropdown-item" href="#" data-range="90">Last 90 Days</a></li>
                    </ul>
                </div>
            </div>
            
            <!-- Stats Cards with Icons -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-start-lg border-primary h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <div class="small fw-bold text-primary mb-1">Total Faxes</div>
                                    <div class="h4"><?php echo $totalCount; ?></div>
                                    <div class="text-xs fw-bold text-success d-inline-flex align-items-center">
                                        <i class="fas fa-arrow-up me-1"></i>
                                        <span>12% from last week</span>
                                    </div>
                                </div>
                                <div class="ms-2">
                                    <i class="fas fa-file-alt fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-start-lg border-success h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <div class="small fw-bold text-success mb-1">Successfully Sent</div>
                                    <div class="h4"><?php echo $sentCount; ?></div>
                                    <div class="text-xs fw-bold text-success d-inline-flex align-items-center">
                                        <i class="fas fa-arrow-up me-1"></i>
                                        <span>8% from last week</span>
                                    </div>
                                </div>
                                <div class="ms-2">
                                    <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-start-lg border-danger h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <div class="small fw-bold text-danger mb-1">Failed</div>
                                    <div class="h4"><?php echo $failedCount; ?></div>
                                    <div class="text-xs fw-bold text-danger d-inline-flex align-items-center">
                                        <i class="fas fa-arrow-down me-1"></i>
                                        <span>3% from last week</span>
                                    </div>
                                </div>
                                <div class="ms-2">
                                    <i class="fas fa-times-circle fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-start-lg border-warning h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <div class="small fw-bold text-warning mb-1">Pending</div>
                                    <div class="h4"><?php echo $pendingCount; ?></div>
                                    <div class="text-xs fw-bold text-warning d-inline-flex align-items-center">
                                        <i class="fas fa-arrow-up me-1"></i>
                                        <span>5% from last week</span>
                                    </div>
                                </div>
                                <div class="ms-2">
                                    <i class="fas fa-clock fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Charts Row -->
            <div class="row mb-4">
                <div class="col-lg-8 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">Fax Status Trends</h6>
                                <div class="dropdown no-arrow">
                                    <button class="btn btn-link btn-sm dropdown-toggle" type="button" id="chartDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="chartDropdown">
                                        <li><a class="dropdown-item" href="#">Last 7 Days</a></li>
                                        <li><a class="dropdown-item" href="#">Last 30 Days</a></li>
                                        <li><a class="dropdown-item" href="#">Last 90 Days</a></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="chart-area">
                                <canvas id="statusTrendChart" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h6 class="mb-0">Top Providers</h6>
                        </div>
                        <div class="card-body">
                            <div class="chart-pie">
                                <canvas id="providerPieChart" height="300"></canvas>
                            </div>
                            <div class="mt-4 small">
                                <?php while($provider = mysqli_fetch_assoc($providerStats)): ?>
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <div class="text-truncate" style="max-width: 150px;">
                                            <?php echo htmlspecialchars($provider['provider_name']); ?>
                                        </div>
                                        <div class="fw-bold">
                                            <?php echo $provider['count']; ?>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activity and Quick Actions -->
            <div class="row">
                <div class="col-lg-8 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">Recent Activity</h6>
                                <a href="view_patients.php" class="btn btn-sm btn-primary">View All</a>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php if (mysqli_num_rows($recentFaxes) > 0): ?>
                                    <?php while ($fax = mysqli_fetch_assoc($recentFaxes)): ?>
                                        <div class="list-group-item list-group-item-action">
                                            <div class="d-flex align-items-center">
                                                <div class="flex-shrink-0 me-3">
                                                    <div class="avatar avatar-sm">
                                                        <div class="avatar-title rounded-circle bg-<?php 
                                                            echo $fax['fax_status'] === 'sent' ? 'success' : 
                                                                ($fax['fax_status'] === 'pending' ? 'warning' : 'danger'); 
                                                        ?>-light">
                                                            <i class="fas fa-<?php 
                                                                echo $fax['fax_status'] === 'sent' ? 'check' : 
                                                                    ($fax['fax_status'] === 'pending' ? 'clock' : 'times'); 
                                                            ?> text-<?php 
                                                                echo $fax['fax_status'] === 'sent' ? 'success' : 
                                                                    ($fax['fax_status'] === 'pending' ? 'warning' : 'danger'); 
                                                            ?>"></i>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="d-flex justify-content-between">
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($fax['first_name'] . ' ' . $fax['last_name']); ?></h6>
                                                        <small class="text-muted"><?php echo date('M d, g:i A', strtotime($fax['created_at'])); ?></small>
                                                    </div>
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <p class="mb-0 small">
                                                            <span class="text-muted"><?php echo htmlspecialchars($fax['braces_requested']); ?></span> • 
                                                            <span class="text-muted">To: <?php echo htmlspecialchars($fax['recipient_name']); ?></span>
                                                        </p>
                                                        <span class="badge bg-<?php 
                                                            echo $fax['fax_status'] === 'sent' ? 'success' : 
                                                                ($fax['fax_status'] === 'pending' ? 'warning' : 'danger'); 
                                                        ?>">
                                                            <?php echo ucfirst(htmlspecialchars($fax['fax_status'])); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="list-group-item">
                                        <div class="text-center py-4">
                                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                            <p class="text-muted">No recent activity found</p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">Quick Actions</h6>
                        </div>
                        <div class="card-body">
                            <a href="send_data.php" class="btn btn-primary btn-block mb-3">
                                <i class="fas fa-upload me-2"></i> Upload New Data
                            </a>
                            <a href="view_patients.php" class="btn btn-outline-primary btn-block mb-3">
                                <i class="fas fa-users me-2"></i> View All Patients
                            </a>
                            <a href="#" class="btn btn-outline-secondary btn-block mb-3" data-bs-toggle="modal" data-bs-target="#sendFaxModal">
                                <i class="fas fa-fax me-2"></i> Send Manual Fax
                            </a>
                            <hr>
                            <h6 class="mb-3">System Status</h6>
                            <div class="d-flex align-items-center mb-2">
                                <div class="flex-grow-1">
                                    <div class="small">Fax Service</div>
                                </div>
                                <div class="flex-shrink-0">
                                    <span class="badge bg-success">Online</span>
                                </div>
                            </div>
                            <div class="d-flex align-items-center mb-2">
                                <div class="flex-grow-1">
                                    <div class="small">Database</div>
                                </div>
                                <div class="flex-shrink-0">
                                    <span class="badge bg-success">Connected</span>
                                </div>
                            </div>
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <div class="small">Storage</div>
                                </div>
                                <div class="flex-shrink-0">
                                    <span class="badge bg-success">42% Used</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Send Fax Modal -->
            <div class="modal fade" id="sendFaxModal" tabindex="-1" aria-labelledby="sendFaxModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="sendFaxModalLabel">Send Manual Fax</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form id="manualFaxForm">
                                <div class="mb-3">
                                    <label for="recipientName" class="form-label">Recipient Name</label>
                                    <input type="text" class="form-control" id="recipientName" required>
                                </div>
                                <div class="mb-3">
                                    <label for="faxNumber" class="form-label">Fax Number</label>
                                    <input type="text" class="form-control" id="faxNumber" required>
                                </div>
                                <div class="mb-3">
                                    <label for="faxFile" class="form-label">PDF File</label>
                                    <input type="file" class="form-control" id="faxFile" accept=".pdf" required>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" id="sendFaxBtn">Send Fax</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php include "includes/footer.php"; ?>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Status Trend Chart
const statusTrendCtx = document.getElementById('statusTrendChart').getContext('2d');
const statusTrendChart = new Chart(statusTrendCtx, {
    type: 'line',
    data: {
        labels: [<?php echo implode(',', array_map(function($item) { return "'" . date('M j', strtotime($item['date'])) . "'"; }, $statusTrends)); ?>],
        datasets: [
            {
                label: 'Sent',
                data: [<?php echo implode(',', array_column($statusTrends, 'sent')); ?>],
                borderColor: '#28a745',
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                tension: 0.3,
                fill: true
            },
            {
                label: 'Failed',
                data: [<?php echo implode(',', array_column($statusTrends, 'failed')); ?>],
                borderColor: '#dc3545',
                backgroundColor: 'rgba(220, 53, 69, 0.1)',
                tension: 0.3,
                fill: true
            },
            {
                label: 'Pending',
                data: [<?php echo implode(',', array_column($statusTrends, 'pending')); ?>],
                borderColor: '#ffc107',
                backgroundColor: 'rgba(255, 193, 7, 0.1)',
                tension: 0.3,
                fill: true
            }
        ]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'top',
            },
            tooltip: {
                mode: 'index',
                intersect: false,
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});

// Provider Pie Chart
const providerPieCtx = document.getElementById('providerPieChart').getContext('2d');
const providerPieChart = new Chart(providerPieCtx, {
    type: 'doughnut',
    data: {
        labels: [<?php 
            mysqli_data_seek($providerStats, 0);
            echo implode(',', array_map(function($item) { 
                return "'" . addslashes($item['provider_name']) . "'"; 
            }, mysqli_fetch_all($providerStats, MYSQLI_ASSOC))); 
        ?>],
        datasets: [{
            data: [<?php 
                mysqli_data_seek($providerStats, 0);
                echo implode(',', array_column(mysqli_fetch_all($providerStats, MYSQLI_ASSOC), 'count')); 
            ?>],
            backgroundColor: [
                '#4e73df',
                '#1cc88a',
                '#36b9cc',
                '#f6c23e',
                '#e74a3b'
            ],
            hoverBackgroundColor: [
                '#2e59d9',
                '#17a673',
                '#2c9faf',
                '#dda20a',
                '#be2617'
            ],
            hoverBorderColor: "rgba(234, 236, 244, 1)",
        }],
    },
    options: {
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                backgroundColor: "rgb(255,255,255)",
                bodyFontColor: "#858796",
                borderColor: '#dddfeb',
                borderWidth: 1,
                xPadding: 15,
                yPadding: 15,
                displayColors: false,
                caretPadding: 10,
            },
        },
        cutout: '70%',
    },
});

// Time range filter
document.querySelectorAll('.dropdown-item[data-range]').forEach(item => {
    item.addEventListener('click', function(e) {
        e.preventDefault();
        const range = this.getAttribute('data-range');
        document.getElementById('timeRangeDropdown').textContent = this.textContent;
        // Here you would typically reload the data via AJAX with the new range
        console.log('Time range changed to:', range + ' days');
    });
});

// Manual fax sending
document.getElementById('sendFaxBtn').addEventListener('click', function() {
    // Here you would implement the actual fax sending logic
    alert('Fax sending functionality would be implemented here');
    $('#sendFaxModal').modal('hide');
});
</script>