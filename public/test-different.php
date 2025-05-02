<?php
// Direct test script specifically for the word "different"
// This is a completely standalone script that doesn't rely on any existing code

// Set error reporting to maximum
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Test text with just the word "different"
$text = "different";

echo "<h1>Test for the word 'different'</h1>";

// 1. Test with RapidAPI
echo "<h2>1. Testing with RapidAPI:</h2>";

// URL encode the text for the API request
$encodedText = urlencode($text);

// RapidAPI details from your screenshot
$apiKey = "785640fb0emsh4c5ac04753793dp1c7232jsnf8323dfb6bbd";
$apiHost = "ai-grammar-checker-i-gpt.p.rapidapi.com";
$apiUrl = "https://ai-grammar-checker-i-gpt.p.rapidapi.com/api/v1/correctAndRephrase?text=$encodedText";

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

// Close cURL session
curl_close($curl);

// Check for errors
if ($err) {
    echo "<p style='color: red;'>cURL Error: " . htmlspecialchars($err) . "</p>";
} else {
    echo "<p>Raw API Response: <pre>" . htmlspecialchars($response) . "</pre></p>";
    
    // Try to decode the JSON response
    $data = json_decode($response, true);
    
    if (json_last_error() === JSON_ERROR_NONE && isset($data['correctedText'])) {
        echo "<p>Corrected Text: <strong>" . htmlspecialchars($data['correctedText']) . "</strong></p>";
        
        if ($data['correctedText'] !== $text) {
            echo "<p style='color: green;'>Success! The word was corrected.</p>";
        } else {
            echo "<p style='color: red;'>The word was not corrected by the API.</p>";
        }
    } else {
        echo "<p style='color: red;'>Error parsing API response: " . json_last_error_msg() . "</p>";
    }
}

// 2. Test with direct string replacement
echo "<h2>2. Testing with direct string replacement:</h2>";

$corrected = str_ireplace("different", "différent", $text);

echo "<p>Original: <strong>" . htmlspecialchars($text) . "</strong></p>";
echo "<p>After str_ireplace: <strong>" . htmlspecialchars($corrected) . "</strong></p>";

if ($corrected !== $text) {
    echo "<p style='color: green;'>Success! The word was corrected with str_ireplace.</p>";
} else {
    echo "<p style='color: red;'>The word was not corrected with str_ireplace.</p>";
}

// 3. Test with preg_replace
echo "<h2>3. Testing with preg_replace:</h2>";

$pattern = '/\bdifferent\b/i';
$replacement = 'différent';
$corrected = preg_replace($pattern, $replacement, $text);

echo "<p>Original: <strong>" . htmlspecialchars($text) . "</strong></p>";
echo "<p>After preg_replace: <strong>" . htmlspecialchars($corrected) . "</strong></p>";

if ($corrected !== $text) {
    echo "<p style='color: green;'>Success! The word was corrected with preg_replace.</p>";
} else {
    echo "<p style='color: red;'>The word was not corrected with preg_replace.</p>";
}

// 4. Test with a sentence
echo "<h2>4. Testing with a sentence:</h2>";

$sentence = "Je suis different des autres.";

echo "<p>Original sentence: <strong>" . htmlspecialchars($sentence) . "</strong></p>";

// Direct replacement
$corrected1 = str_ireplace("different", "différent", $sentence);
echo "<p>After str_ireplace: <strong>" . htmlspecialchars($corrected1) . "</strong></p>";

// Regex replacement
$pattern = '/\bdifferent\b/i';
$replacement = 'différent';
$corrected2 = preg_replace($pattern, $replacement, $sentence);
echo "<p>After preg_replace: <strong>" . htmlspecialchars($corrected2) . "</strong></p>";

// Special handling
if (stripos($sentence, "different") !== false) {
    $corrected3 = str_ireplace("different", "différent", $sentence);
    echo "<p>After special handling: <strong>" . htmlspecialchars($corrected3) . "</strong></p>";
    
    if ($corrected3 !== $sentence) {
        echo "<p style='color: green;'>Success! The word was corrected with special handling.</p>";
    } else {
        echo "<p style='color: red;'>The word was not corrected with special handling.</p>";
    }
} else {
    echo "<p>The word 'different' was not found in the sentence.</p>";
}
