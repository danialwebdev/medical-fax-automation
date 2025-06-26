<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'generate_pdf.php';
require_once 'templates/medical-form.php';
require_once 'includes/form_factory.php';

ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');
error_reporting(E_ALL);

// Initialize array to track processing results
$_SESSION['process_results'] = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Check if excel_file is set and not empty
        if (!isset($_POST['excel_file']) || empty($_POST['excel_file'])) {
            throw new Exception("No Google Sheet URL provided.");
        }

        // Extract the Spreadsheet ID using a regular expression
        preg_match('/\/d\/([a-zA-Z0-9-_]+)/', $_POST['excel_file'], $matches);

        if (isset($matches[1])) {
            $spreadsheetId = $matches[1];
        } else {
            throw new Exception("Spreadsheet ID not found in the URL.");
        }
        
        /**
         * Read data from a public Google Sheet using CSV export
         * 
         * @param string $spreadsheetId The ID of the Google Sheets document
         * @return array The processed data
         */
        function readPublicGoogleSheet($spreadsheetId) {
            // Construct the export URL for CSV format
            $csvUrl = "https://docs.google.com/spreadsheets/d/{$spreadsheetId}/export?format=csv";
            
            // Get the CSV data
            $csvData = @file_get_contents($csvUrl);
            
            if (empty($csvData)) {
                return ['error' => 'No data found or sheet is not publicly accessible.'];
            }
            
            // Parse the CSV data
            $rows = array_map('str_getcsv', explode("\n", $csvData));
            
            // First row contains headers
            $headers = array_map('trim', $rows[0]);
            
            // Process the data rows
            $data = [];
            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                
                // Skip empty rows
                if (empty($row) || count($row) <= 1) continue;
                
                // Create a record with keys from headers and values from the row
                $record = [];
                foreach ($headers as $index => $header) {
                    if ($header && $index < count($row)) {
                        $record[$header] = trim($row[$index]);
                    }
                }
                
                // Only add non-empty records
                if (!empty($record)) {
                    $data[] = $record;
                }
            }
            
            return $data;
        }

        function formatData($data) {
            // Same function as before - no changes needed
            $formatted = [];
            
            // Define all expected column headers based on the sheet
            $expectedColumns = [
                'First Name', 'Last Name', 'Shipping Address', 'City', 'State', 'Zip Code', 
                'Medicare ID', 'Date of Birth', 'Gender', 'Braces Requested', 'L Codes', 
                'Phone Number', 'Waist size', 'KNEE SIZE', 'Wrist', 'Ankle', 'Height', 'Weight', 
                'Provider Name', 'Provider Address', 'Provider Phone', 'Provider NPI'
            ];
            
            foreach ($data as $record) {
                $newRecord = [];
                
                // Handle the case where columns might be combined in the raw data
                if (count($record) < count($expectedColumns)) {
                    // Attempt to split data that might be combined
                    $expandedRecord = [];
                    
                    foreach ($record as $key => $value) {
                        // Check if this key contains multiple expected columns
                        $containsMultipleColumns = false;
                        foreach ($expectedColumns as $column) {
                            if (strpos($key, $column) !== false) {
                                $cleanKey = $column;
                                $containsMultipleColumns = true;
                                
                                // Extract the value that corresponds to this column
                                // This is a simplified approach - might need more complex parsing
                                if ($containsMultipleColumns) {
                                    $pattern = '/' . preg_quote($column, '/') . '([^' . implode('|', array_map('preg_quote', $expectedColumns)) . ']+)/';
                                    if (preg_match($pattern, $key . $value, $matches)) {
                                        $expandedRecord[$cleanKey] = trim($matches[1]);
                                    } else {
                                        $expandedRecord[$cleanKey] = '';
                                    }
                                }
                            }
                        }
                        
                        // If not a combined column, just add as is
                        if (!$containsMultipleColumns) {
                            $cleanKey = str_replace('*', '', $key);
                            $expandedRecord[$cleanKey] = $value;
                        }
                    }
                    
                    $record = $expandedRecord;
                }
                
                // Process each column with specific formatting rules
                foreach ($record as $key => $value) {
                    // Remove any ** characters that might be in column headers
                    $cleanKey = str_replace('*', '', $key);
                    
                    // Format specific fields
                    switch (trim($cleanKey)) {
                        case 'Date of Birth':
                            // Handle various date formats and convert to MM/DD/YYYY
                            if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $value, $matches)) {
                                // Already in MM/DD/YYYY format
                                $newRecord['Date of Birth'] = $value;
                            } elseif (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value, $matches)) {
                                // Convert from YYYY-MM-DD to MM/DD/YYYY
                                $newRecord['Date of Birth'] = $matches[2] . '/' . $matches[3] . '/' . $matches[1];
                            } else {
                                // Attempt to parse with strtotime if it's a valid date string
                                $timestamp = strtotime($value);
                                if ($timestamp !== false) {
                                    $newRecord['Date of Birth'] = date('m/d/Y', $timestamp);
                                } else {
                                    $newRecord['Date of Birth'] = $value; // Keep original if can't parse
                                }
                            }
                            break;
                            
                        case 'Zip Code':
                            // Format zip code - extract only numbers
                            $newRecord['Zip Code'] = preg_replace('/[^0-9]/', '', $value);
                            break;
                            
                        case 'Medicare ID':
                            // Ensure proper format for Medicare ID
                            $newRecord['Medicare ID'] = trim($value);
                            break;
                        
                        case 'Phone Number':
                            // Format phone number - extract only numbers
                            $cleanPhone = preg_replace('/[^0-9]/', '', $value);
                            // Format as XXX-XXX-XXXX if 10 digits
                            if (strlen($cleanPhone) == 10) {
                                $newRecord['Phone Number'] = substr($cleanPhone, 0, 3) . '-' . 
                                                            substr($cleanPhone, 3, 3) . '-' . 
                                                            substr($cleanPhone, 6);
                            } else {
                                $newRecord['Phone Number'] = $cleanPhone;
                            }
                            break;
                        
                        case 'Gender':
                            // Standardize gender to uppercase
                            $newRecord['Gender'] = strtoupper($value);
                            break;
                        
                        case 'First Name':
                        case 'Last Name':
                            // Proper case names
                            $newRecord[$cleanKey] = ucwords(strtolower($value));
                            break;
                        
                        case 'Height':
                            // Format height consistently (assuming formats like 5'2, 5.2, 5'2")
                            $height = $value;
                            // Remove any double quotes and standardize format to X'Y
                            $height = str_replace('"', '', $height);
                            if (preg_match('/(\d+)[\'\.](\d+)/', $height, $matches)) {
                                $newRecord['Height'] = $matches[1] . "'" . $matches[2];
                            } else {
                                $newRecord['Height'] = $value;
                            }
                            break;
                            
                        default:
                            $newRecord[$cleanKey] = $value;
                    }
                }
                
                // Ensure all expected columns exist in the record
                foreach ($expectedColumns as $column) {
                    if (!isset($newRecord[$column])) {
                        $newRecord[$column] = '';
                    }
                }
                
                $formatted[] = $newRecord;
            }
            
            return $formatted;
        }
        
        // Read the data
        $data = readPublicGoogleSheet($spreadsheetId);

        // Format the data
        if (isset($data['error'])) {
            throw new Exception($data['error']);
        } else {
            $data = formatData($data);
        }
        
        // Check if we have data to process
        if (empty($data)) {
            throw new Exception("No valid data found in the Google Sheet.");
        }

        // Initialize form factory
        $formFactory = new FormFactory($conn);

        // Assuming $data contains the rows fetched from Google Sheets
        foreach ($data as $row) {
            try {
                // Ensure row is an array
                if (!is_array($row)) {
                    continue; // Skip non-array rows
                }

                // Extract data from the row into variables
                $first_name = $row['First Name'] ?? '';
                $last_name = $row['Last Name'] ?? '';
                $shipping_address = $row['Shipping Address'] ?? '';
                $city = $row['City'] ?? '';
                $state = $row['State'] ?? '';
                $zip_code = $row['Zip Code'] ?? '';
                $medicare_id = $row['Medicare ID'] ?? '';
                $date_of_birth = $row['Date of Birth'] ?? '';
                $gender = $row['Gender'] ?? '';
                $braces_requested = $row['Braces Requested'] ?? '';
                $l_codes = $row['L Codes'] ?? '';
                $phone_number = $row['Phone Number'] ?? '';
                $waist_size = $row['Waist size'] ?? '';
                $knee_size = $row['KNEE SIZE'] ?? '';
                $wrist = $row['Wrist'] ?? '';
                $ankle = $row['Ankle'] ?? '';
                $height = $row['Height'] ?? '';
                $weight = $row['Weight'] ?? '';
                $provider_name = $row['Provider Name'] ?? '';
                $provider_address = $row['Provider Address'] ?? '';
                $provider_phone = $row['Provider Phone'] ?? '';
                $provider_npi = $row['Provider NPI'] ?? '';

                // Initialize result for this patient
                $patient_result = [
                    'name' => $first_name . ' ' . $last_name,
                    'brace' => $braces_requested,
                    'status' => 'pending',
                    'message' => ''
                ];

                // Insert the patient data into the database
                $insert_query = "
                    INSERT INTO patients (
                        first_name, last_name, shipping_address, city, state, zip_code, medicare_id, date_of_birth, gender,
                        braces_requested, l_codes, phone_number, waist_size, knee_size, wrist, ankle, height, weight,
                        provider_name, provider_address, provider_phone, provider_npi, fax_status
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending'
                    )
                ";

                $stmt = mysqli_prepare($conn, $insert_query);
                mysqli_stmt_bind_param(
                    $stmt,
                    "ssssssssssssssssssssss",
                    $first_name,
                    $last_name,
                    $shipping_address,
                    $city,
                    $state,
                    $zip_code,
                    $medicare_id,
                    $date_of_birth,
                    $gender,
                    $braces_requested,
                    $l_codes,
                    $phone_number,
                    $waist_size,
                    $knee_size,
                    $wrist,
                    $ankle,
                    $height,
                    $weight,
                    $provider_name,
                    $provider_address,
                    $provider_phone,
                    $provider_npi
                );

                // Process form
                if (mysqli_stmt_execute($stmt)) {
                    $patient_id = mysqli_insert_id($conn);
                    
                    // Generate PDF
                    $form = $formFactory->createForm($row);
                    $html = $form->generateHTML();
                    $filename = $first_name . '-' . $last_name . '-' . $row['Braces Requested'] . '-' . time() . '.pdf';
                    $pdf_generator = new PDFGenerator($html, $filename);
                    $pdf_path = $pdf_generator->generatePDF();
                    
                    // Format phone number for fax
                    $faxNumber = preg_replace('/[^0-9]/', '', $provider_phone);
                    
                    // Instead of sending fax directly, add to queue
                    $insert_queue_query = "
                        INSERT INTO fax_queue (
                            patient_id, pdf_path, fax_number, recipient_name, status
                        ) VALUES (
                            ?, ?, ?, ?, 'pending'
                        )
                    ";

                    $stmt_queue = mysqli_prepare($conn, $insert_queue_query);
                    mysqli_stmt_bind_param(
                        $stmt_queue,
                        "isss",
                        $patient_id,
                        $pdf_path,
                        $faxNumber,
                        $provider_name
                    );

                    if (mysqli_stmt_execute($stmt_queue)) {
                        $patient_result['status'] = 'queued';
                        $patient_result['message'] = 'Fax queued for sending';
                    } else {
                        $patient_result['status'] = 'failed';
                        $patient_result['message'] = 'Failed to queue fax: ' . mysqli_error($conn);
                    }
                    
                } else {
                    $patient_result['status'] = 'failed';
                    $patient_result['message'] = 'Database insert failed: ' . mysqli_error($conn);
                }
                
                // Add result to session
                $_SESSION['process_results'][] = $patient_result;

            } catch (Exception $e) {
                // Add error to session
                $_SESSION['process_results'][] = [
                    'name' => ($row['First Name'] ?? '') . ' ' . ($row['Last Name'] ?? ''),
                    'brace' => $row['Braces Requested'] ?? '',
                    'status' => 'failed',
                    'message' => 'Error: ' . $e->getMessage()
                ];
                
                // Log the error
                error_log("Error processing patient: " . $e->getMessage());
            }
        }

        // Start the fax queue processor if this is the first batch
        $queue_check_query = "SELECT COUNT(*) as count FROM fax_queue WHERE status = 'pending'";
        $queue_check_result = mysqli_query($conn, $queue_check_query);
        $queue_count = mysqli_fetch_assoc($queue_check_result)['count'];
        
        // If we have 10 or fewer pending faxes, we can process them immediately
        // This gives instant feedback for small batches
        if ($queue_count <= 10) {
            // Execute queue processor in the background (non-blocking)
            $cmd = "php " . __DIR__ . "/process_fax_queue.php > /dev/null 2>&1 &";
            if (function_exists('exec')) {
                exec($cmd);
            }
        }

        // Close connection
        mysqli_close($conn);
        
        // Redirect back to the send_data.php page
        header("Location: send_data.php");
        exit;
          
    } catch (Exception $e) {
        // Set error message in session
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
        
        // Redirect back to the send_data.php page
        header("Location: send_data.php");
        exit;
    }
} else {
  // If not a POST request, redirect to the form page
  header("Location: send_data.php");
  exit;
}