<?php
require_once 'includes/config.php';
require_once 'includes/form_template_manager.php';

try {
    $templateManager = new FormTemplateManager($conn);
    
    // Get the medical form template content
    $medicalFormClass = file_get_contents(__DIR__ . '/templates/medical-form.php');
    preg_match('/return <<<HTML(.*?)HTML;/s', $medicalFormClass, $matches);
    $medicalFormContent = isset($matches[1]) ? $matches[1] : '';
    
    // Get the PV form template content
    $pvFormContent = file_get_contents(__DIR__ . '/pv-form.php');
    
    // Store the templates
    $templateManager->storeTemplate('Medical Form', 'medical-form', $medicalFormContent);
    $templateManager->storeTemplate('Patient Verification Form', 'pv-form', $pvFormContent);
    
    echo "Templates stored successfully!";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}