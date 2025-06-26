<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    redirectToLogin();
}

// Get form template ID from URL
$template_id = $_GET['template_id'] ?? null;

try {
    // Fetch form template details
    $stmt = $pdo->prepare("SELECT * FROM form_templates WHERE id = ?");
    $stmt->execute([$template_id]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$template) {
        throw new Exception("Form template not found");
    }

    // Fetch form submissions for this template
    $stmt = $pdo->prepare("
        SELECT fs.*, 
               CONCAT(pd->>'$.first_name', ' ', pd->>'$.last_name') as patient_name,
               pd->>'$.birthdate' as date_of_birth,
               pd->>'$.provider_name' as provider_name
        FROM form_submissions fs
        WHERE form_template_id = ?
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$template_id]);
    $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $_SESSION['error_message'] = "Error: " . $e->getMessage();
    header("Location: send_data.php");
    exit;
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
                <h2>Preview Forms - <?php echo htmlspecialchars($template['name']); ?></h2>
                <a href="send_data.php" class="btn btn-primary">Upload New Data</a>
            </div>
            
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger"><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    Recently Generated Forms
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Patient Name</th>
                                    <th>Date of Birth</th>
                                    <th>Provider</th>
                                    <th>Created At</th>
                                    <th>Fax Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($submissions as $submission): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($submission['patient_name']); ?></td>
                                        <td><?php echo htmlspecialchars($submission['date_of_birth']); ?></td>
                                        <td><?php echo htmlspecialchars($submission['provider_name']); ?></td>
                                        <td><?php echo date('M d, Y H:i', strtotime($submission['created_at'])); ?></td>
                                        <td>
                                            <span class="badge <?php echo $submission['fax_status'] === 'success' ? 'bg-success' : 'bg-warning'; ?>">
                                                <?php echo ucfirst(htmlspecialchars($submission['fax_status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="<?php echo htmlspecialchars($submission['pdf_path']); ?>" 
                                                   class="btn btn-sm btn-info" 
                                                   target="_blank">View PDF</a>
                                                <button class="btn btn-sm btn-primary" 
                                                        onclick="resendFax(<?php echo $submission['id']; ?>)">
                                                    Resend Fax
                                                </button>
                                                <button class="btn btn-sm btn-danger" 
                                                        onclick="deleteSubmission(<?php echo $submission['id']; ?>)">
                                                    Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                
                                <?php if (empty($submissions)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center">No forms have been generated yet.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function resendFax(submissionId) {
    if (confirm('Are you sure you want to resend this fax?')) {
        fetch('resend_fax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'submission_id=' + submissionId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                alert('Error resending fax: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error resending fax');
        });
    }
}

function deleteSubmission(submissionId) {
    if (confirm('Are you sure you want to delete this submission? This action cannot be undone.')) {
        fetch('delete_submission.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'submission_id=' + submissionId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                alert('Error deleting submission: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error deleting submission');
        });
    }
}
</script>

<?php include 'includes/footer.php'; ?>