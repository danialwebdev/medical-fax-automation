<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirectToLogin();
}

// Pagination configuration
$recordsPerPage = 8;
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($currentPage - 1) * $recordsPerPage;

// Function to get paginated patient records
function getPaginatedPatients($conn, $offset, $recordsPerPage) {
    $query = "SELECT p.medicare_id, p.first_name, p.last_name, p.fax_status, 
                     p.provider_name, fq.fax_number, fq.recipient_name, fq.pdf_path
              FROM patients p
              LEFT JOIN fax_queue fq ON p.id = fq.patient_id
              ORDER BY p.id DESC
              LIMIT ?, ?";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ii", $offset, $recordsPerPage);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (!$result) {
        return ['error' => 'Database query error: ' . mysqli_error($conn)];
    }
    
    $patients = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $patients[] = $row;
    }
    
    return $patients;
}

// Function to count total patients
function countTotalPatients($conn) {
    $query = "SELECT COUNT(*) as total FROM patients";
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    return $row['total'];
}

// Get patients data
$patients = getPaginatedPatients($conn, $offset, $recordsPerPage);
$totalPatients = countTotalPatients($conn);
$totalPages = ceil($totalPatients / $recordsPerPage);

// Handle PDF preview request
if (isset($_GET['preview']) && !empty($_GET['filename'])) {
    $filename = basename($_GET['filename']);
    $filepath = __DIR__ . '/generated_pdfs/' . $filename;
    
    if (file_exists($filepath)) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    } else {
        $error_message = "PDF file not found.";
    }
}

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-2 sidebar-div">
            <?php include "includes/sidebar.php"; ?>
        </div>
        
        <div class="col-md-10 p-4 mt-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Patient Records</h2>
                <div>
                    <a href="send_data.php" class="btn btn-primary me-2">Upload New Data</a>
                    <!--<button class="btn btn-outline-secondary" id="exportBtn">-->
                    <!--    <i class="fas fa-download me-1"></i> Export-->
                    <!--</button>-->
                </div>
            </div>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($patients['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $patients['error']; ?>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-header">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h5 class="mb-0">All Patient Records</h5>
                            </div>
                            <div class="col-md-6">
                                <div class="input-group">
                                    <input type="text" id="searchInput" class="form-control" placeholder="Search patients...">
                                    <button class="btn btn-outline-secondary" type="button" id="searchBtn">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-hover" id="patientsTable">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Patient Name</th>
                                        <th>Provider</th>
                                        <th>Fax Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($patients)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-4">
                                                <i class="fas fa-user-injured fa-3x text-muted mb-3"></i>
                                                <p class="text-muted">No patient records found</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($patients as $patient): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div>
                                                            <h6 class="mb-0"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></h6>
                                                            <small class="text-muted">Medicare ID: <?php echo $patient['medicare_id']; ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($patient['provider_name']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $patient['fax_status'] === 'sent' ? 'success' : 
                                                             ($patient['fax_status'] === 'pending' ? 'warning' : 'danger'); 
                                                    ?>">
                                                        <i class="fas fa-<?php 
                                                            echo $patient['fax_status'] === 'sent' ? 'check' : 
                                                                 ($patient['fax_status'] === 'pending' ? 'clock' : 'times'); 
                                                        ?> me-1"></i>
                                                        <?php echo ucfirst(htmlspecialchars($patient['fax_status'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button type="button" class="btn btn-primary view-details" 
                                                                data-bs-toggle="modal" data-bs-target="#patientModal" 
                                                                data-patient='<?php echo htmlspecialchars(json_encode($patient), ENT_QUOTES, 'UTF-8'); ?>'>
                                                            <i class="fas fa-eye me-1"></i> Details
                                                        </button>
                                                        <?php if (!empty($patient['pdf_path'])): ?>
                                                            <a href="view_patients.php?preview=1&filename=<?php echo urlencode(basename($patient['pdf_path'])); ?>" 
                                                               class="btn btn-info" target="_blank">
                                                                <i class="fas fa-file-pdf me-1"></i> PDF
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                        <nav aria-label="Page navigation">
                            <ul class="pagination justify-content-center mt-4">
                                <li class="page-item <?php echo $currentPage <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $currentPage - 1; ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                                
                                <?php 
                                // Show page numbers
                                $startPage = max(1, $currentPage - 2);
                                $endPage = min($totalPages, $currentPage + 2);
                                
                                if ($startPage > 1) {
                                    echo '<li class="page-item"><a class="page-link" href="?page=1">1</a></li>';
                                    if ($startPage > 2) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                }
                                
                                for ($i = $startPage; $i <= $endPage; $i++) {
                                    echo '<li class="page-item ' . ($i == $currentPage ? 'active' : '') . '">';
                                    echo '<a class="page-link" href="?page=' . $i . '">' . $i . '</a>';
                                    echo '</li>';
                                }
                                
                                if ($endPage < $totalPages) {
                                    if ($endPage < $totalPages - 1) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                    echo '<li class="page-item"><a class="page-link" href="?page=' . $totalPages . '">' . $totalPages . '</a></li>';
                                }
                                ?>
                                
                                <li class="page-item <?php echo $currentPage >= $totalPages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $currentPage + 1; ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                        
                        <div class="text-center text-muted small">
                            Showing <?php echo ($offset + 1) . ' to ' . min($offset + $recordsPerPage, $totalPatients); ?> of <?php echo $totalPatients; ?> records
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Patient Details Modal -->
<div class="modal fade" id="patientModal" tabindex="-1" aria-labelledby="patientModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="patientModalLabel">Patient Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="modalBodyContent">
                <!-- Dynamic content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="printDetailsBtn">
                    <i class="fas fa-print me-1"></i> Print
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Export Modal -->
<!--<div class="modal fade" id="exportModal" tabindex="-1" aria-hidden="true">-->
<!--    <div class="modal-dialog">-->
<!--        <div class="modal-content">-->
<!--            <div class="modal-header">-->
<!--                <h5 class="modal-title">Export Patient Records</h5>-->
<!--                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>-->
<!--            </div>-->
<!--            <div class="modal-body">-->
<!--                <div class="mb-3">-->
<!--                    <label class="form-label">Export Format</label>-->
<!--                    <select class="form-select" id="exportFormat">-->
<!--                        <option value="csv">CSV</option>-->
<!--                        <option value="excel">Excel</option>-->
<!--                        <option value="pdf">PDF</option>-->
<!--                    </select>-->
<!--                </div>-->
<!--                <div class="mb-3">-->
<!--                    <label class="form-label">Date Range</label>-->
<!--                    <div class="input-daterange input-group">-->
<!--                        <input type="date" class="form-control" name="startDate">-->
<!--                        <span class="input-group-text">to</span>-->
<!--                        <input type="date" class="form-control" name="endDate">-->
<!--                    </div>-->
<!--                </div>-->
<!--            </div>-->
<!--            <div class="modal-footer">-->
<!--                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>-->
<!--                <button type="button" class="btn btn-primary" id="confirmExportBtn">-->
<!--                    <i class="fas fa-download me-1"></i> Export-->
<!--                </button>-->
<!--            </div>-->
<!--        </div>-->
<!--    </div>-->
<!--</div>-->

<script>
// Search functionality
document.getElementById('searchInput').addEventListener('keyup', function() {
    const searchValue = this.value.toLowerCase();
    const table = document.getElementById('patientsTable');
    const rows = table.getElementsByTagName('tr');
    
    for (let i = 1; i < rows.length; i++) {
        const row = rows[i];
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchValue) ? '' : 'none';
    }
});

// View patient details
document.querySelectorAll('.view-details').forEach(button => {
    button.addEventListener('click', function() {
        const patientData = JSON.parse(this.getAttribute('data-patient'));
        
        // Create the details HTML
        const detailsHtml = `
            <div class="row">
                <div class="col-md-6">
                    <h6 class="border-bottom pb-2 mb-3">Patient Information</h6>
                    <table class="table table-sm">
                        <tr><th width="40%">First Name:</th><td>${patientData.first_name || 'N/A'}</td></tr>
                        <tr><th>Last Name:</th><td>${patientData.last_name || 'N/A'}</td></tr>
                        <tr><th>Provider:</th><td>${patientData.provider_name || 'N/A'}</td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h6 class="border-bottom pb-2 mb-3">Fax Information</h6>
                    <table class="table table-sm">
                        <tr><th width="40%">Status:</th><td>
                            <span class="badge bg-${getStatusColor(patientData.fax_status)}">
                                <i class="fas ${getStatusIcon(patientData.fax_status)} me-1"></i>
                                ${patientData.fax_status ? patientData.fax_status.charAt(0).toUpperCase() + patientData.fax_status.slice(1) : 'N/A'}
                            </span>
                        </td></tr>
                        <tr><th>Recipient:</th><td>${patientData.recipient_name || 'N/A'}</td></tr>
                        <tr><th>Fax Number:</th><td>${formatPhoneNumber(patientData.fax_number)}</td></tr>
                    </table>
                </div>
            </div>
            <div class="alert alert-${getStatusAlertClass(patientData.fax_status)} mt-3">
                <i class="fas ${getStatusIcon(patientData.fax_status)} me-2"></i> 
                ${getStatusMessage(patientData)}
            </div>
        `;
        
        document.getElementById('modalBodyContent').innerHTML = detailsHtml;
    });
});



// Print button handler
document.getElementById('printDetailsBtn').addEventListener('click', function() {
    window.print();
});

// Helper functions
function getStatusColor(status) {
    return status === 'sent' ? 'success' : 
           (status === 'pending' ? 'warning' : 'danger');
}

function getStatusAlertClass(status) {
    return status === 'sent' ? 'success' : 
           (status === 'pending' ? 'info' : 'danger');
}

function getStatusIcon(status) {
    return status === 'sent' ? 'fa-check-circle' : 
           (status === 'pending' ? 'fa-clock' : 'fa-exclamation-triangle');
}

function getStatusMessage(patient) {
    if (patient.fax_status === 'sent') {
        return `Fax successfully sent to ${patient.recipient_name || 'provider'} at ${formatPhoneNumber(patient.fax_number)}`;
    } else if (patient.fax_status === 'pending') {
        return `Fax is pending to be sent to ${patient.recipient_name || 'provider'}`;
    } else {
        return 'Fax not yet sent';
    }
}

function formatPhoneNumber(phone) {
    if (!phone) return 'N/A';
    const cleaned = ('' + phone).replace(/\D/g, '');
    const match = cleaned.match(/^(\d{3})(\d{3})(\d{4})$/);
    return match ? `${match[1]}-${match[2]}-${match[3]}` : phone;
}
</script>

<style>
    /* Avatar styles */
    .avatar {
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    
    .avatar-title {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        height: 100%;
    }
    
    .avatar-sm {
        width: 36px;
        height: 36px;
        font-size: 0.875rem;
    }
    
    /* Pagination styles */
    .pagination .page-item.active .page-link {
        background-color: #0d6efd;
        border-color: #0d6efd;
    }
    
    /* Table styles */
    .table-hover tbody tr:hover {
        background-color: rgba(13, 110, 253, 0.05);
    }
    
    /* Modal styles */
    .modal-header.bg-primary {
        border-bottom: none;
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
        .btn-group .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }
        
        .avatar-sm {
            width: 28px;
            height: 28px;
            font-size: 0.75rem;
        }
    }
</style>

<?php include 'includes/footer.php'; ?>