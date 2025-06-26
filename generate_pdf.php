<?php
require 'vendor/autoload.php';
use Dompdf\Dompdf;

class PDFGenerator {
    private $html;
    private $filename;
    private $outputDir;
    
    public function __construct($html, $filename, $outputDir = null) {
        $this->html = $html;
        $this->filename = $this->sanitizeFilename($filename);
        $this->outputDir = $outputDir ?: __DIR__ . '/generated_pdfs';
    }
    
    private function sanitizeFilename($filename) {
        // Remove special characters and spaces
        $clean = preg_replace('/[^a-zA-Z0-9\-\.]/', '_', $filename);
        // Ensure .pdf extension
        return pathinfo($clean, PATHINFO_EXTENSION) === 'pdf' ? $clean : $clean . '.pdf';
    }
    
    public function generatePDF() {
        // Ensure directory exists with secure permissions
        if (!file_exists($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
        }
        
        $dompdf = new Dompdf();
        $dompdf->loadHtml($this->html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        $output_path = $this->outputDir . '/' . $this->filename;
        file_put_contents($output_path, $dompdf->output());
        
        return $output_path; // Returns absolute path
    }
}

class FaxSender {
    private $api_key;
    
    public function __construct($api_key) {
        $this->api_key = $api_key;
    }
    
    public function sendFax($pdf_path, $fax_number) {
        $real_path = realpath($pdf_path);
        
        if (!$real_path || !file_exists($real_path)) {
            throw new Exception("PDF file does not exist: " . $pdf_path . 
                             " (Resolved to: " . $real_path . ")");
        }
        
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => "https://api.humbletitan.com/v1/fax/send",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => [
                "fax_number" => $this->formatFaxNumber($fax_number),
                "file" => new CURLFile($real_path)
            ],
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer " . $this->api_key,
                "Content-Type: multipart/form-data"
            ],
        ]);
        
        $response = curl_exec($curl);
        $err = curl_error($curl);
        
        curl_close($curl);
        
        if ($err) {
            throw new Exception("Fax API Error: " . $err);
        }
        
        return json_decode($response, true);
    }
    
    private function formatFaxNumber($number) {
        $clean = preg_replace('/[^0-9]/', '', $number);
        return (strlen($clean) === 10) ? '1' . $clean : $clean;
    }
}