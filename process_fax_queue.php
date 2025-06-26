<?php
/**
 * This script processes the fax queue with rate limiting
 * It should be run via cron job every minute:
 * * * * * * php /path/to/your/app/process_fax_queue.php
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/fax_queue.log');

// Ensure log directory exists
if (!is_dir(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
}

// Log script start
error_log("Fax queue processor started: " . date('Y-m-d H:i:s'));

// Prevent multiple instances from running simultaneously
$lockFile = __DIR__ . '/fax_queue.lock';
if (file_exists($lockFile)) {
    $lockTime = filemtime($lockFile);
    // If lock file is older than 10 minutes, it's probably stale
    if (time() - $lockTime < 600) {
        error_log("Process is already running. Lock file created at " . date('Y-m-d H:i:s', $lockTime));
        exit(0);
    } else {
        error_log("Stale lock file found. Created at " . date('Y-m-d H:i:s', $lockTime) . ". Removing.");
        unlink($lockFile);
    }
}

// Create lock file
file_put_contents($lockFile, date('Y-m-d H:i:s'));
error_log("Lock file created");

try {
    // Load required files
    require_once 'includes/config.php';
    require_once 'includes/functions.php';
    require_once 'includes/RingCentralFaxService.php';
    
    // Check database connection
    if (!isset($conn) || $conn === false) {
        throw new Exception("Database connection failed");
    }
    
    // Initialize RingCentral service
    if (!isset($ringcentralConfig) || !is_array($ringcentralConfig)) {
        throw new Exception("RingCentral configuration is missing or invalid");
    }
    
    error_log("Initializing RingCentral service with config: " . json_encode(array_keys($ringcentralConfig)));
    $rcHelper = new RingCentralFaxService($ringcentralConfig);
    
    // Configure rate limit
    $maxRequests = 8; // Set to 5 to be extra safe (less than the 10/min limit)
    $timeWindow = 60; // 60 seconds
    
    // Get pending faxes count
    $countQuery = "SELECT COUNT(*) AS pending_count FROM fax_queue WHERE status = 'pending' AND attempts < 3";
    $countResult = mysqli_query($conn, $countQuery);
    $pendingCount = mysqli_fetch_assoc($countResult)['pending_count'];
    error_log("Found $pendingCount pending faxes in queue");
    
    // Process queue
    $query = "
        SELECT q.*, p.first_name, p.last_name 
        FROM fax_queue q 
        JOIN patients p ON q.patient_id = p.id 
        WHERE q.status = 'pending' 
        AND q.attempts < 3 
        ORDER BY q.created_at ASC 
        LIMIT ?
    ";
    
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        throw new Exception("Failed to prepare query: " . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, "i", $maxRequests);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $processedCount = 0;
    
    while ($row = mysqli_fetch_assoc($result)) {
        error_log("Processing fax ID: {$row['id']} for patient: {$row['first_name']} {$row['last_name']}");
        
        // Add delay between requests to avoid overwhelming the API
        if ($processedCount > 0) {
            $delay = ceil($timeWindow / $maxRequests);
            error_log("Sleeping for $delay seconds before next fax");
            sleep($delay);
        }
        
        try {
            // Check if PDF file exists and log details
            $pdfPath = $row['pdf_path'];
            if (!file_exists($pdfPath)) {
                error_log("PDF file not found: $pdfPath");
                throw new Exception("PDF file not found: $pdfPath");
            }
            
            $fileSize = filesize($pdfPath);
            error_log("PDF file exists: $pdfPath (Size: $fileSize bytes)");
            
            // Send the fax
            error_log("Sending fax to {$row['fax_number']} for {$row['recipient_name']}");
            $faxResult = $rcHelper->sendFax($pdfPath, $row['fax_number'], $row['recipient_name']);
            
            error_log("Fax result: " . json_encode($faxResult));
            
            // Update queue entry
            $updateQuery = "
                UPDATE fax_queue 
                SET status = ?, error_message = ?, attempts = attempts + 1, updated_at = NOW()
                WHERE id = ?
            ";
            
            $updateStmt = mysqli_prepare($conn, $updateQuery);
            if (!$updateStmt) {
                throw new Exception("Failed to prepare update query: " . mysqli_error($conn));
            }
            
            $status = $faxResult['success'] ? 'sent' : 'failed';
            $message = $faxResult['success'] ? '' : ($faxResult['message'] ?? 'Unknown error');
            mysqli_stmt_bind_param($updateStmt, "ssi", $status, $message, $row['id']);
            mysqli_stmt_execute($updateStmt);
            
            // Also update the patients table
            $updatePatientQuery = "
                UPDATE patients 
                SET fax_status = ? 
                WHERE id = ?
            ";
            
            $updatePatientStmt = mysqli_prepare($conn, $updatePatientQuery);
            if (!$updatePatientStmt) {
                throw new Exception("Failed to prepare patient update query: " . mysqli_error($conn));
            }
            
            mysqli_stmt_bind_param($updatePatientStmt, "si", $status, $row['patient_id']);
            mysqli_stmt_execute($updatePatientStmt);
            
            // Log the result
            if ($faxResult['success']) {
                error_log("✓ Fax sent successfully for patient ID: {$row['patient_id']} ({$row['first_name']} {$row['last_name']})");
            } else {
                error_log("✗ Fax failed for patient ID: {$row['patient_id']} ({$row['first_name']} {$row['last_name']}): {$message}");
            }
            
            $processedCount++;
        } catch (Exception $e) {
            // Log the error
            error_log("Error sending fax for patient ID: {$row['patient_id']}: " . $e->getMessage());
            
            // Update queue entry
            $updateQuery = "
                UPDATE fax_queue 
                SET status = 'failed', error_message = ?, attempts = attempts + 1, updated_at = NOW()
                WHERE id = ?
            ";
            
            $updateStmt = mysqli_prepare($conn, $updateQuery);
            $errorMessage = $e->getMessage();
            mysqli_stmt_bind_param($updateStmt, "si", $errorMessage, $row['id']);
            mysqli_stmt_execute($updateStmt);
            
            // Update patient table
            $updatePatientQuery = "
                UPDATE patients 
                SET fax_status = 'failed' 
                WHERE id = ?
            ";
            
            $updatePatientStmt = mysqli_prepare($conn, $updatePatientQuery);
            mysqli_stmt_bind_param($updatePatientStmt, "i", $row['patient_id']);
            mysqli_stmt_execute($updatePatientStmt);
        }
    }
    
    // Log summary
    error_log("Processed {$processedCount} faxes from queue");
    
    // Check if there are more pending faxes
    $pendingQuery = "SELECT COUNT(*) as count FROM fax_queue WHERE status = 'pending' AND attempts < 3";
    $pendingResult = mysqli_query($conn, $pendingQuery);
    $pendingCount = mysqli_fetch_row($pendingResult)[0];
    
    error_log("Remaining pending faxes: {$pendingCount}");
    
} catch (Exception $e) {
    error_log("Critical error in fax queue processing: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
} finally {
    // Release lock file
    if (file_exists($lockFile)) {
        unlink($lockFile);
        error_log("Lock file removed");
    }
    
    // Close connection
    if (isset($conn) && $conn) {
        mysqli_close($conn);
    }
    
    error_log("Fax queue processor finished: " . date('Y-m-d H:i:s'));
}