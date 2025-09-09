<?php
// Headers pentru JSON și CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Gestionăm requesturile OPTIONS pentru CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Debug logging
error_log("=== CHARGE.PHP DEBUG ===");
error_log("Method: " . $_SERVER['REQUEST_METHOD']);

try {
    // Verificăm metoda
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Metoda nu este permisă');
    }
    
    // Preluăm datele
    $input = file_get_contents('php://input');
    error_log("Input received: " . $input);
    
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Date JSON invalide: ' . json_last_error_msg());
    }
    
    // Validări
    if (!isset($data['amount']) || !isset($data['paymentMethodId'])) {
        throw new Exception('Lipsesc datele necesare: amount sau paymentMethodId');
    }
    
    // CONFIGURARE STRIPE - înlocuiește cu cheia ta reală
    $stripe_secret_key = 'sk_test_...'; // ⚠️ ÎNLOCUIEȘTE CU CHEIA TA!
    
    if (empty($stripe_secret_key) || $stripe_secret_key === 'sk_test_...') {
        throw new Exception('Cheia Stripe nu este configurată corect');
    }
    
    // Inițializăm Stripe fără autoload - folosim cURL direct
    $amount = intval($data['amount']);
    $paymentMethodId = $data['paymentMethodId'];
    $currency = $data['currency'] ?? 'gbp';
    
    error_log("Processing payment: Amount=$amount, Currency=$currency, PaymentMethod=$paymentMethodId");
    
    // Creăm PaymentIntent prin API direct
    $payment_data = [
        'amount' => $amount,
        'currency' => $currency,
        'payment_method' => $paymentMethodId,
        'confirmation_method' => 'manual',
        'confirm' => 'true'
    ];
    
    // cURL către Stripe API
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.stripe.com/v1/payment_intents');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payment_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $stripe_secret_key,
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    error_log("Stripe API Response Code: $http_code");
    error_log("Stripe API Response: $response");
    
    if ($http_code !== 200) {
        $error_data = json_decode($response, true);
        $error_message = $error_data['error']['message'] ?? 'Eroare necunoscută la procesarea plății';
        throw new Exception("Stripe Error: $error_message");
    }
    
    $payment_result = json_decode($response, true);
    
    if (!$payment_result || !isset($payment_result['status'])) {
        throw new Exception('Răspuns invalid de la Stripe');
    }
    
    // Verificăm statusul plății
    if ($payment_result['status'] === 'succeeded') {
        echo json_encode([
            'success' => true,
            'status' => $payment_result['status'],
            'payment_intent_id' => $payment_result['id'],
            'message' => 'Plata a fost procesată cu succes'
        ]);
    } else if ($payment_result['status'] === 'requires_action') {
        echo json_encode([
            'success' => false,
            'status' => $payment_result['status'],
            'message' => 'Plata necesită autentificare suplimentară',
            'client_secret' => $payment_result['client_secret']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'status' => $payment_result['status'],
            'message' => 'Plata nu a putut fi procesată'
        ]);
    }
    
} catch (Exception $e) {
    error_log("CHARGE ERROR: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>