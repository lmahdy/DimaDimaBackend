<?php
// Simple test script for RapidAPI Grammar Checker

// Get text from POST request or use sample text
$text = isset($_POST['text']) ? $_POST['text'] : "Je suis different des autres personnes. Je peut faire beaucoup de choses que les autres ne peut pas faire.";

// Set content type to JSON if this is an AJAX request
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
}

// URL encode the text
$encodedText = urlencode($text);

// RapidAPI details
$rapidApiKey = '785640fb0emsh4c5ac04753793dp1c7232jsnf8323dfb6bbd';
$rapidApiHost = 'ai-grammar-checker-i-gpt.p.rapidapi.com';
$apiUrl = "https://ai-grammar-checker-i-gpt.p.rapidapi.com/api/v1/correctAndRephrase?text={$encodedText}";

// Initialize cURL session
$curl = curl_init();

// Set cURL options
curl_setopt_array($curl, [
    CURLOPT_URL => $apiUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "GET",
    CURLOPT_HTTPHEADER => [
        "X-RapidAPI-Host: {$rapidApiHost}",
        "X-RapidAPI-Key: {$rapidApiKey}"
    ],
]);

// Execute the request
$response = curl_exec($curl);
$err = curl_error($curl);

// Close cURL session
curl_close($curl);

// Decode the JSON response
$data = json_decode($response, true);
$result = [];

if ($err) {
    $result = [
        'success' => false,
        'message' => "Error: $err"
    ];
} else {
    if (json_last_error() === JSON_ERROR_NONE && isset($data['correctedText'])) {
        $result = [
            'success' => true,
            'original' => $text,
            'correctedText' => $data['correctedText'],
            'rawResponse' => $response
        ];
    } else {
        $result = [
            'success' => false,
            'message' => 'Error parsing JSON response: ' . json_last_error_msg(),
            'rawResponse' => $response
        ];
    }
}

// If this is an AJAX request, return JSON
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    echo json_encode($result);
    exit;
}

// Otherwise, output HTML
echo "<h1>RapidAPI Grammar Checker Test</h1>";
echo "<h2>Original Text:</h2>";
echo "<pre>" . htmlspecialchars($text) . "</pre>";

if (!$result['success']) {
    echo "<h2>Error:</h2>";
    echo "<pre>" . htmlspecialchars($result['message']) . "</pre>";
} else {
    echo "<h2>API Response:</h2>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";

    echo "<h2>Corrected Text:</h2>";
    echo "<pre>" . htmlspecialchars($result['correctedText']) . "</pre>";

    // Show the difference
    echo "<h2>Differences:</h2>";
    echo "<ul>";
    if ($result['correctedText'] !== $text) {
        echo "<li>Original: <span style='color:red'>" . htmlspecialchars($text) . "</span></li>";
        echo "<li>Corrected: <span style='color:green'>" . htmlspecialchars($result['correctedText']) . "</span></li>";
    } else {
        echo "<li>No differences found.</li>";
    }
    echo "</ul>";
}
