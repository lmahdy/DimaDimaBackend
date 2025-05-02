<?php
// Simple test script for the fallback grammar checker

// Get text from POST request or use sample text
$text = isset($_POST['text']) ? $_POST['text'] : "Je suis different des autres personnes. Je peut faire beaucoup de choses que les autres ne peut pas faire.";

// Set content type to JSON if this is an AJAX request
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
}

// Function to apply a correction and track the change
function applyCorrection($pattern, $replacement, $subject, $isRegex = false) {
    $original = $subject;

    if ($isRegex) {
        $result = preg_replace($pattern, $replacement, $subject);
    } else {
        $result = str_ireplace($pattern, $replacement, $subject);
    }

    if ($result !== $original) {
        return [
            'result' => $result,
            'changed' => true,
            'pattern' => $pattern,
            'replacement' => $replacement
        ];
    }

    return [
        'result' => $result,
        'changed' => false
    ];
}

// Function to perform fallback grammar check
function fallbackGrammarCheck($text) {
    // If text is empty, return as is
    if (empty(trim($text))) {
        return ['correctedText' => $text, 'changes' => []];
    }

    // Create a copy of the original text
    $correctedText = $text;
    $changes = [];

    // 1. Fix common accent issues

    // Prepositions: a -> à
    $prepositions = [
        'a la' => 'à la',
        'a l\'' => 'à l\'',
        'a cause' => 'à cause',
        'a propos' => 'à propos',
        'a cote' => 'à côté',
        'a partir' => 'à partir',
        'a travers' => 'à travers',
    ];

    foreach ($prepositions as $mistake => $correction) {
        // Match the preposition with word boundaries
        $pattern = '/\b' . preg_quote($mistake, '/') . '\b/i';
        $result = applyCorrection($pattern, $correction, $correctedText, true);
        if ($result['changed']) {
            $correctedText = $result['result'];
            $changes[] = [
                'type' => 'preposition',
                'pattern' => $result['pattern'],
                'replacement' => $result['replacement']
            ];
        }
    }

    // 2. Common spelling mistakes with accents
    $accentedWords = [
        'different' => 'différent',
        'probleme' => 'problème',
        'reclamation' => 'réclamation',
        'reponse' => 'réponse',
        'tres' => 'très',
        'apres' => 'après',
        'etre' => 'être',
        'meme' => 'même',
        'deja' => 'déjà',
        'telephone' => 'téléphone',
        'numero' => 'numéro',
        'qualite' => 'qualité',
        'securite' => 'sécurité',
        'experience' => 'expérience',
        'desole' => 'désolé',
        'interesse' => 'intéressé',
    ];

    // First, do a direct string replacement for the most common issues
    foreach ($accentedWords as $mistake => $correction) {
        // Simple string replacement first (more reliable for exact matches)
        $result = applyCorrection($mistake, $correction, $correctedText, false);
        if ($result['changed']) {
            $correctedText = $result['result'];
            $changes[] = [
                'type' => 'accent_direct',
                'pattern' => $result['pattern'],
                'replacement' => $result['replacement']
            ];
        }

        // Also try with capitalized first letter
        $capitalMistake = ucfirst($mistake);
        $capitalCorrection = ucfirst($correction);
        $result = applyCorrection($capitalMistake, $capitalCorrection, $correctedText, false);
        if ($result['changed']) {
            $correctedText = $result['result'];
            $changes[] = [
                'type' => 'accent_capital',
                'pattern' => $result['pattern'],
                'replacement' => $result['replacement']
            ];
        }
    }

    // Then try with word boundaries for any remaining issues
    foreach ($accentedWords as $mistake => $correction) {
        $pattern = '/\b' . preg_quote($mistake, '/') . '\b/i';
        $result = applyCorrection($pattern, $correction, $correctedText, true);
        if ($result['changed']) {
            $correctedText = $result['result'];
            $changes[] = [
                'type' => 'accent_regex',
                'pattern' => $result['pattern'],
                'replacement' => $result['replacement']
            ];
        }
    }

    // 3. Grammar patterns
    $grammarPatterns = [
        // Negation
        '/\b(je|tu|il|elle|on|nous|vous|ils|elles) (suis|es|est|sommes|êtes|sont|ai|as|a|avons|avez|ont|vais|vas|va|allons|allez|vont|peux|peut|pouvons|pouvez|peuvent|fais|fait|faisons|faites|font|dois|doit|devons|devez|doivent|sais|sait|savons|savez|savent|veux|veut|voulons|voulez|veulent) pas\b/i' =>
        '$1 ne $2 pas',

        // Common verb conjugation errors
        '/\bje peut\b/i' => 'je peux',
        '/\btu peut\b/i' => 'tu peux',
        '/\bje va\b/i' => 'je vais',
        '/\btu va\b/i' => 'tu vas',
        '/\bje fait\b/i' => 'je fais',
        '/\btu fait\b/i' => 'tu fais',
        '/\bje est\b/i' => 'je suis',
        '/\btu est\b/i' => 'tu es',

        // Common expressions
        '/\bc\'est pas\b/i' => 'ce n\'est pas',
        '/\by\'a pas\b/i' => 'il n\'y a pas',
        '/\by a pas\b/i' => 'il n\'y a pas',
    ];

    foreach ($grammarPatterns as $pattern => $replacement) {
        $result = applyCorrection($pattern, $replacement, $correctedText, true);
        if ($result['changed']) {
            $correctedText = $result['result'];
            $changes[] = [
                'type' => 'grammar',
                'pattern' => $result['pattern'],
                'replacement' => $result['replacement']
            ];
        }
    }

    return [
        'correctedText' => $correctedText,
        'changes' => $changes
    ];
}

// Run the fallback grammar check
$fallbackResult = fallbackGrammarCheck($text);

// Prepare the result for JSON
$result = [
    'success' => true,
    'original' => $text,
    'correctedText' => $fallbackResult['correctedText'],
    'changes' => $fallbackResult['changes']
];

// If this is an AJAX request, return JSON
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    echo json_encode($result);
    exit;
}

// Otherwise, output HTML
echo "<h1>Fallback Grammar Checker Test</h1>";
echo "<h2>Original Text:</h2>";
echo "<pre>" . htmlspecialchars($text) . "</pre>";

echo "<h2>Corrected Text:</h2>";
echo "<pre>" . htmlspecialchars($fallbackResult['correctedText']) . "</pre>";

echo "<h2>Changes Made:</h2>";
if (empty($fallbackResult['changes'])) {
    echo "<p>No changes were made.</p>";
} else {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Type</th><th>Pattern</th><th>Replacement</th></tr>";

    foreach ($fallbackResult['changes'] as $change) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($change['type']) . "</td>";
        echo "<td>" . htmlspecialchars($change['pattern']) . "</td>";
        echo "<td>" . htmlspecialchars($change['replacement']) . "</td>";
        echo "</tr>";
    }

    echo "</table>";
}

// Show the difference
echo "<h2>Differences:</h2>";
echo "<ul>";
if ($fallbackResult['correctedText'] !== $text) {
    echo "<li>Original: <span style='color:red'>" . htmlspecialchars($text) . "</span></li>";
    echo "<li>Corrected: <span style='color:green'>" . htmlspecialchars($fallbackResult['correctedText']) . "</span></li>";
} else {
    echo "<li>No differences found.</li>";
}
echo "</ul>";
