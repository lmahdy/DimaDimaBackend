<?php
// Direct test script for RapidAPI Grammar Checker
// This is a completely standalone script that doesn't rely on any existing code

// Set error reporting to maximum
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Test text with common French errors
$text = "Je suis different des autres personnes. Je peut faire beaucoup de choses.";

echo "<h1>Direct RapidAPI Grammar Checker Test</h1>";
echo "<p>Testing with text: <strong>" . htmlspecialchars($text) . "</strong></p>";

// URL encode the text for the API request
$encodedText = urlencode($text);

// RapidAPI details from your screenshot
$apiKey = "785640fb0emsh4c5ac04753793dp1c7232jsnf8323dfb6bbd";
$apiHost = "ai-grammar-checker-i-gpt.p.rapidapi.com";
$apiUrl = "https://ai-grammar-checker-i-gpt.p.rapidapi.com/api/v1/correctAndRephrase?text=$encodedText";

echo "<h2>API Request Details:</h2>";
echo "<ul>";
echo "<li>URL: " . htmlspecialchars($apiUrl) . "</li>";
echo "<li>API Key: " . htmlspecialchars($apiKey) . "</li>";
echo "<li>API Host: " . htmlspecialchars($apiHost) . "</li>";
echo "</ul>";

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
        "X-RapidAPI-Host: $apiHost",
        "X-RapidAPI-Key: $apiKey"
    ],
]);

// Execute the request
$response = curl_exec($curl);
$err = curl_error($curl);
$info = curl_getinfo($curl);

// Close cURL session
curl_close($curl);

// Display cURL info
echo "<h2>cURL Info:</h2>";
echo "<pre>" . print_r($info, true) . "</pre>";

// Check for errors
if ($err) {
    echo "<h2>cURL Error:</h2>";
    echo "<div style='color: red; font-weight: bold;'>" . htmlspecialchars($err) . "</div>";
} else {
    echo "<h2>Raw API Response:</h2>";
    echo "<pre style='background-color: #f5f5f5; padding: 10px; border-radius: 5px;'>" . htmlspecialchars($response) . "</pre>";
    
    // Try to decode the JSON response
    $data = json_decode($response, true);
    
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "<h2>Decoded JSON Response:</h2>";
        echo "<pre style='background-color: #f5f5f5; padding: 10px; border-radius: 5px;'>" . print_r($data, true) . "</pre>";
        
        // Check if the API returned a corrected text
        if (isset($data['correctedText'])) {
            echo "<h2>Corrected Text:</h2>";
            echo "<div style='background-color: #e6ffe6; padding: 10px; border-radius: 5px;'>" . htmlspecialchars($data['correctedText']) . "</div>";
            
            // Show differences
            echo "<h2>Differences:</h2>";
            if ($data['correctedText'] !== $text) {
                echo "<ul>";
                echo "<li>Original: <span style='color: red;'>" . htmlspecialchars($text) . "</span></li>";
                echo "<li>Corrected: <span style='color: green;'>" . htmlspecialchars($data['correctedText']) . "</span></li>";
                echo "</ul>";
            } else {
                echo "<p>No differences found.</p>";
            }
        } else {
            echo "<h2>Error:</h2>";
            echo "<div style='color: red;'>The API response does not contain a 'correctedText' field.</div>";
        }
    } else {
        echo "<h2>JSON Decoding Error:</h2>";
        echo "<div style='color: red;'>" . htmlspecialchars(json_last_error_msg()) . "</div>";
    }
}
