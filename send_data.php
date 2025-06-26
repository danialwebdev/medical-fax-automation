<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    redirectToLogin();
}

require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

// Initialize messages and data
$message = '';
$preview_data = [];
$process_results = [];
$has_pending_faxes = false;

// Check for session messages
if (!empty($_SESSION['process_results'])) {
    $process_results = $_SESSION['process_results'];
    unset($_SESSION['process_results']);
} elseif (!empty($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
} elseif (!empty($_SESSION['error_message'])) {
    $message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Get fax queue statistics
$queue_stats = ['pending' => 0, 'sent' => 0, 'failed' => 0];
$queue_query = "SELECT status, COUNT(*) as count FROM fax_queue GROUP BY status";
$queue_result = mysqli_query($conn, $queue_query);
while ($row = mysqli_fetch_assoc($queue_result)) {
    $queue_stats[$row['status']] = $row['count'];
}
$has_pending_faxes = $queue_stats['pending'] > 0;

// Get recent faxes with more details
$recent_faxes = [];
$recent_query = "
    SELECT q.*, p.first_name, p.last_name, p.braces_requested, p.medicare_id,
           p.date_of_birth, p.provider_name, p.provider_phone
    FROM fax_queue q 
    JOIN patients p ON q.patient_id = p.id 
    ORDER BY q.updated_at DESC 
    LIMIT 5
";
$recent_result = mysqli_query($conn, $recent_query);
while ($row = mysqli_fetch_assoc($recent_result)) {
    $recent_faxes[] = $row;
}

include 'includes/header.php';
?>


<div class="container-fluid">
    <div class="row">
        <div class="col-md-2 sidebar-div">
            <?php include "includes/sidebar.php"; ?>
        </div>
        
        <div class="col-md-10 p-4 mt-4">
            <!-- Page Header with Stats -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2><i class="fas fa-paper-plane me-2"></i> Send Data</h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Send Data</li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex">
                    <button class="btn btn-outline-secondary me-2" id="refreshBtn">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                    <a href="view_patients.php" class="btn btn-secondary">
                        <i class="fas fa-users me-1"></i> View Patients
                    </a>
                </div>
            </div>
            
            <!-- System Alerts -->
            <?php if (!empty($message)): ?>
                <div class="alert <?php echo strpos($message, 'successfully') !== false ? 'alert-success' : 'alert-danger'; ?> alert-dismissible fade show">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Fax Queue Dashboard -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card card-status pending">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title text-uppercase text-muted mb-0">Pending</h6>
                                    <h2 class="mb-0"><?php echo $queue_stats['pending']; ?></h2>
                                </div>
                                <div class="icon-circle bg-warning">
                                    <i class="fas fa-clock text-white"></i>
                                </div>
                            </div>
                            <p class="mt-3 mb-0 text-sm">
                                <span class="text-nowrap">Processing at 9/min</span>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card card-status success">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title text-uppercase text-muted mb-0">Sent</h6>
                                    <h2 class="mb-0"><?php echo $queue_stats['sent']; ?></h2>
                                </div>
                                <div class="icon-circle bg-success">
                                    <i class="fas fa-check text-white"></i>
                                </div>
                            </div>
                            <p class="mt-3 mb-0 text-sm">
                                <span class="text-success me-2">+<?php echo round($queue_stats['sent']/($queue_stats['sent']+$queue_stats['failed'])*100, 1); ?>%</span>
                                <span class="text-nowrap">Success rate</span>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card card-status failed">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title text-uppercase text-muted mb-0">Failed</h6>
                                    <h2 class="mb-0"><?php echo $queue_stats['failed']; ?></h2>
                                </div>
                                <div class="icon-circle bg-danger">
                                    <i class="fas fa-times text-white"></i>
                                </div>
                            </div>
                            <p class="mt-3 mb-0 text-sm">
                                <button class="btn btn-sm btn-link p-0" id="retryFailedBtn">
                                    <i class="fas fa-redo me-1"></i> Retry failed
                                </button>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Data Upload Card -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-file-import me-2"></i> Import Patient Data</h5>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="importOptions" data-bs-toggle="dropdown">
                            <i class="fas fa-cog"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#importHelpModal"><i class="fas fa-question-circle me-2"></i> Help</a></li>
                            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#sampleFormatModal"><i class="fas fa-file-excel me-2"></i> Sample Format</a></li>
                        </ul>
                    </div>
                </div>
                <div class="card-body">
                    <form method="POST" action="process_form.php" enctype="multipart/form-data" id="uploadForm">
                        <div class="mb-3">
                            <label for="excel_file" class="form-label">Google Sheet URL</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-link"></i></span>
                                <input type="url" class="form-control" id="excel_file" name="excel_file" 
                                       placeholder="https://docs.google.com/spreadsheets/d/..." required>
                            </div>
                            <div class="alert alert-info mt-2 mb-0">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <small>Make sure the Google Sheet is publicly accessible or set to "Anyone with the link can view"</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between mt-4">
                            <div>
                                <button type="submit" class="btn btn-primary" id="submitBtn">
                                    <i class="fas fa-cloud-upload-alt me-1"></i> Fetch & Process
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Process Results Section -->
            <?php if (!empty($process_results)): ?>
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-tasks me-2"></i> Processing Results</h5>
                        <div>
                            <span class="badge bg-primary"><?php echo count($process_results); ?> records</span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Patient</th>
                                        <th>Brace</th>
                                        <th>Status</th>
                                        <th>Message</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($process_results as $result): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar avatar-sm me-3">
                                                        <span class="avatar-title rounded-circle bg-primary">
                                                            <?php echo strtoupper(substr($result['name'], 0, 1)); ?>
                                                        </span>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-0"><?php echo htmlspecialchars($result['name']); ?></h6>
                                                        <small class="text-muted"><?php echo $result['medicare_id'] ?? 'N/A'; ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($result['brace']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $result['status'] === 'sent' ? 'success' : 
                                                         ($result['status'] === 'failed' ? 'danger' : 
                                                         ($result['status'] === 'queued' ? 'warning' : 'secondary')); 
                                                ?>">
                                                    <i class="fas fa-<?php 
                                                        echo $result['status'] === 'sent' ? 'check' : 
                                                             ($result['status'] === 'failed' ? 'times' : 
                                                             ($result['status'] === 'queued' ? 'clock' : 'question')); 
                                                    ?> me-1"></i>
                                                    <?php echo ucfirst(htmlspecialchars($result['status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small><?php echo htmlspecialchars($result['message']); ?></small>
                                            </td>
                                            <td>
                                                <?php if ($result['status'] === 'failed'): ?>
                                                    <button class="btn btn-sm btn-outline-danger">
                                                        <i class="fas fa-redo"></i> Retry
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
   <!-- Recent Faxes Section -->
            <?php if (!empty($recent_faxes)): ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Recent Faxes</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Patient</th>
                                        <th>Brace</th>
                                        <th>Status</th>
                                        <th>Recipient</th>
                                        <th>Updated</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_faxes as $fax): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($fax['first_name'] . ' ' . $fax['last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($fax['braces_requested']); ?></td>
                                            <td>
                                                <?php
                                                $badge_class = 'bg-secondary';
                                                if ($fax['status'] === 'sent') {
                                                    $badge_class = 'bg-success';
                                                } elseif ($fax['status'] === 'failed') {
                                                    $badge_class = 'bg-danger';
                                                } elseif ($fax['status'] === 'pending') {
                                                    $badge_class = 'bg-warning';
                                                }
                                                ?>
                                                <span class="badge <?php echo $badge_class; ?>">
                                                    <?php echo ucfirst(htmlspecialchars($fax['status'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($fax['recipient_name']); ?></td>
                                            <td><?php echo date('Y-m-d H:i:s', strtotime($fax['updated_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Import Help Modal -->
<div class="modal fade" id="importHelpModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-question-circle me-2"></i> Import Help</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="accordion" id="helpAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingOne">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne">
                                Google Sheet Requirements
                            </button>
                        </h2>
                        <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne">
                            <div class="accordion-body">
                                <ul>
                                    <li>Sheet must be shared with "Anyone with the link can view" permission</li>
                                    <li>First row should contain column headers</li>
                                    <li>Required columns: First Name, Last Name, Medicare ID, Braces Requested</li>
                                    <li>Supported formats: .xlsx, .xls, .csv</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingTwo">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo">
                                Troubleshooting
                            </button>
                        </h2>
                        <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo">
                            <div class="accordion-body">
                                <h6>Common Issues:</h6>
                                <ul>
                                    <li><strong>Permission denied:</strong> Verify sharing settings on Google Sheet</li>
                                    <li><strong>Invalid format:</strong> Check column headers match expected format</li>
                                    <li><strong>Empty data:</strong> Ensure all required fields are populated</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Sample Format Modal -->
<div class="modal fade" id="sampleFormatModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-file-excel me-2"></i> Sample Data Format</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>First Name</th>
                                <th>Last Name</th>
                                <th>Shipping Address</th>
                                <th>City</th>
                                <th>State</th>
                                <th>Zip Code</th>
                                <th>Medicare ID</th>
                                <th>Date of Birth</th>
                                <th>Gender</th>
                                <th>Braces Requested</th>
                                <th>L Codes</th>
                                <th>Phone Number</th>
                                <th>Waist size</th>
                                <th>Knee Size</th>
                                <th>Wrist</th>
                                <th>Ankle</th>
                                <th>Height</th>
                                <th>Weight</th>
                                <th>Provider Name</th>
                                <th>Provider Address</th>
                                <th>Provider Phone</th>
                                <th>Provider NPI</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>John</td>
                                <td>Doe</td>
                                <td>109 SHAMROCK AVE</td>
                                <td>YORKTOWN</td>
                                <td>VA</td>
                                <td>23693</td>
                                <td>3Hx7Wy6Ph33</td>
                                <td>20/07/1940</td>
                                <td>FEMALE</td>
                                <td>Both Wrists</td>
                                <td>L3916</td>
                                <td>7575961142</td>
                                <td></td>
                                <td></td>
                                <td>L</td>
                                <td></td>
                                <td>6'1</td>
                                <td>195</td>
                                <td>LAURA E MCALEER LEAVEY, MD</td>
                                <td>858 J Clyde Morris Blvd Newport News VA 23601</td>
                                <td>7062301136</td>
                                <td>1063476513</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button class="btn btn-primary" onclick="downloadSamplePDF()">
                    <i class="fas fa-download me-1"></i> Download as PDF
                </button>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<style>
    /* Custom Card Styles */
    .card.card-status {
        border: none;
        border-radius: 0.5rem;
        transition: all 0.3s ease;
        overflow: hidden;
    }
    
    .card.card-status:hover {
        transform: translateY(-3px);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }
    
    .card.card-status.pending {
        border-left: 4px solid #ffc107;
    }
    
    .card.card-status.success {
        border-left: 4px solid #28a745;
    }
    
    .card.card-status.failed {
        border-left: 4px solid #dc3545;
    }
    
    .icon-circle {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
    }
    
    /* Avatar Styles */
    .avatar {
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    
    .avatar-sm {
        width: 36px;
        height: 36px;
    }
    
    .avatar-title {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        height: 100%;
        color: white;
        font-weight: 600;
    }
    
    /* Table Styles */
    .table-hover tbody tr:hover {
        background-color: rgba(13, 110, 253, 0.05);
    }
    
    /* Responsive Adjustments */
    @media (max-width: 768px) {
        .card-header h5 {
            font-size: 1.1rem;
        }
        
        .icon-circle {
            width: 40px;
            height: 40px;
            font-size: 1rem;
        }
    }
</style>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
<script>
// Make jsPDF available globally
window.jsPDF = window.jspdf.jsPDF;

function downloadSamplePDF() {
    try {
        // Create a new jsPDF instance in landscape mode with specific dimensions
        const doc = new jsPDF({
            orientation: 'landscape',
            unit: 'mm',
            format: 'a3' // Using A3 size to accommodate all columns comfortably
        });

        // Add professional header with logo and title
        doc.setDrawColor(41, 128, 185);
        doc.setFillColor(41, 128, 185);
        doc.rect(0, 0, doc.internal.pageSize.getWidth(), 15, 'F');
        
        // Add title in header
        doc.setTextColor(255, 255, 255);
        doc.setFontSize(16);
        doc.setFont('helvetica', 'bold');
        doc.text('SAMPLE PATIENT DATA FORMAT', 140, 10, { align: 'center' });

        // Add footer
        const footerY = doc.internal.pageSize.getHeight() - 10;
        doc.setFontSize(8);
        doc.setTextColor(100, 100, 100);
        doc.text('Confidential - © ' + new Date().getFullYear() + ' Rightway110', 140, footerY, { align: 'center' });
        doc.text('Page 1 of 1', 275, footerY, { align: 'right' });

        // Reset text color for content
        doc.setTextColor(0, 0, 0);

        // Add generation date
        doc.setFontSize(10);
        doc.text('Generated on: ' + new Date().toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        }), 20, 25);

        // Define columns with optimized widths
        const columns = [
            { header: "First Name", dataKey: "firstName", cellWidth: 12 },
            { header: "Last Name", dataKey: "lastName", cellWidth: 12 },
            { header: "Shipping Address", dataKey: "address", cellWidth: 20 },
            { header: "City", dataKey: "city", cellWidth: 12 },
            { header: "State", dataKey: "state", cellWidth: 8 },
            { header: "Zip", dataKey: "zip", cellWidth: 8 },
            { header: "Medicare ID", dataKey: "medicareId", cellWidth: 15 },
            { header: "DOB", dataKey: "dob", cellWidth: 12 },
            { header: "Gender", dataKey: "gender", cellWidth: 8 },
            { header: "Braces", dataKey: "braces", cellWidth: 12 },
            { header: "L Codes", dataKey: "lCodes", cellWidth: 10 },
            { header: "Phone", dataKey: "phone", cellWidth: 12 },
            { header: "Waist", dataKey: "waist", cellWidth: 8 },
            { header: "Knee", dataKey: "knee", cellWidth: 8 },
            { header: "Wrist", dataKey: "wrist", cellWidth: 8 },
            { header: "Ankle", dataKey: "ankle", cellWidth: 8 },
            { header: "Height", dataKey: "height", cellWidth: 8 },
            { header: "Weight", dataKey: "weight", cellWidth: 8 },
            { header: "Provider Name", dataKey: "providerName", cellWidth: 20 },
            { header: "Provider Address", dataKey: "providerAddress", cellWidth: 25 },
            { header: "Provider Phone", dataKey: "providerPhone", cellWidth: 15 },
            { header: "Provider NPI", dataKey: "providerNpi", cellWidth: 12 }
        ];

        const rows = [{
            firstName: "John",
            lastName: "Doe",
            address: "109 SHAMROCK AVE",
            city: "YORKTOWN",
            state: "VA",
            zip: "23693",
            medicareId: "3Hx7Wy6Ph33",
            dob: "07/20/1940",
            gender: "FEMALE",
            braces: "Both Wrists",
            lCodes: "L3916",
            phone: "(757) 596-1142",
            waist: "N/A",
            knee: "N/A",
            wrist: "L",
            ankle: "N/A",
            height: "6'1\"",
            weight: "195 lbs",
            providerName: "LAURA E MCALEER LEAVEY, MD",
            providerAddress: "858 J Clyde Morris Blvd\nNewport News, VA 23601",
            providerPhone: "(706) 230-1136",
            providerNpi: "1063476513"
        }];

        // Add table with professional styling
        doc.autoTable({
            columns: columns,
            body: rows,
            startY: 30,
            styles: {
                fontSize: 7,
                cellPadding: 1.5,
                overflow: 'linebreak',
                valign: 'middle',
                minCellHeight: 6,
                lineColor: [200, 200, 200],
                lineWidth: 0.1
            },
            headerStyles: {
                fillColor: [41, 128, 185],
                textColor: 255,
                fontStyle: 'bold',
                fontSize: 7,
                cellPadding: 2
            },
            bodyStyles: {
                fontSize: 7,
                cellPadding: 1.5
            },
            alternateRowStyles: {
                fillColor: [245, 245, 245]
            },
            margin: {
                top: 30,
                left: 10,
                right: 10
            },
            tableWidth: 'auto',
            showHead: 'everyPage',
            pageBreak: 'avoid',
            horizontalPageBreak: false,
            columnStyles: {
                // Special styling for specific columns
                address: { cellWidth: 'wrap' },
                providerAddress: { cellWidth: 'wrap' }
            }
        });

       
        // Reset text color
        doc.setTextColor(0, 0, 0);

        // Save the PDF
        doc.save('Patient_Data_Sample_' + new Date().toISOString().slice(0, 10) + '.pdf');
    } catch (error) {
        console.error('Error generating PDF:', error);
        alert('Error generating PDF. Please check console for details.');
    }
}

// Document ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Refresh button
    document.getElementById('refreshBtn').addEventListener('click', function() {
        window.location.reload();
    });
    
    // Form submission loading state
    document.getElementById('uploadForm').addEventListener('submit', function() {
        const submitBtn = document.getElementById('submitBtn');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Processing...';
    });
    
    // Helper function to format phone numbers
    function formatPhoneNumber(phone) {
        if (!phone) return 'N/A';
        const cleaned = ('' + phone).replace(/\D/g, '');
        const match = cleaned.match(/^(\d{3})(\d{3})(\d{4})$/);
        return match ? `${match[1]}-${match[2]}-${match[3]}` : phone;
    }
    
    // Helper function for time elapsed
    function timeElapsedString(date) {
        const now = new Date();
        const then = new Date(date);
        const seconds = Math.floor((now - then) / 1000);
        
        let interval = Math.floor(seconds / 31536000);
        if (interval >= 1) return interval + " year" + (interval === 1 ? "" : "s") + " ago";
        
        interval = Math.floor(seconds / 2592000);
        if (interval >= 1) return interval + " month" + (interval === 1 ? "" : "s") + " ago";
        
        interval = Math.floor(seconds / 86400);
        if (interval >= 1) return interval + " day" + (interval === 1 ? "" : "s") + " ago";
        
        interval = Math.floor(seconds / 3600);
        if (interval >= 1) return interval + " hour" + (interval === 1 ? "" : "s") + " ago";
        
        interval = Math.floor(seconds / 60);
        if (interval >= 1) return interval + " minute" + (interval === 1 ? "" : "s") + " ago";
        
        return Math.floor(seconds) + " second" + (seconds === 1 ? "" : "s") + " ago";
    }
});
</script>