<?php
require_once 'admin/send_confirmation_email.php';

$testData = [
    'firstName' => 'Test',
    'lastName' => 'User', 
    'email' => 'macociug@gmail.com', // Schimbă cu email-ul tău pentru test
    'phone' => '+40 712 345 678',
    'serviceName' => 'Consultație Test',
    'servicePrice' => 150.00,
    'date' => '2025-02-15',
    'hour' => '10:00'
];

$result = sendBookingConfirmationEmail($testData);
echo "<pre>";
print_r($result);
echo "</pre>";
?>