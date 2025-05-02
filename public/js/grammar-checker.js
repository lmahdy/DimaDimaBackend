/**
 * French Grammar Checker
 * A client-side grammar checker for French text
 */

// Common French grammar mistakes and their corrections
const commonFrenchMistakes = {
    // Words missing accents
    'different': 'différent',
    'probleme': 'problème',
    'reclamation': 'réclamation',
    'reponse': 'réponse',
    'tres': 'très',
    'apres': 'après',
    'etre': 'être',
    'meme': 'même',
    'deja': 'déjà',
    'telephone': 'téléphone',
    'numero': 'numéro',
    'qualite': 'qualité',
    'securite': 'sécurité',
    'experience': 'expérience',
    'desole': 'désolé',
    'interesse': 'intéressé',
    
    // Common verb conjugation errors
    'je peut': 'je peux',
    'tu peut': 'tu peux',
    'je va': 'je vais',
    'tu va': 'tu vas',
    'je fait': 'je fais',
    'tu fait': 'tu fais',
    'je est': 'je suis',
    'tu est': 'tu es',
    
    // Missing negation
    'je suis pas': 'je ne suis pas',
    'tu es pas': 'tu n\'es pas',
    'il est pas': 'il n\'est pas',
    'elle est pas': 'elle n\'est pas',
    'nous sommes pas': 'nous ne sommes pas',
    'vous etes pas': 'vous n\'êtes pas',
    'ils sont pas': 'ils ne sont pas',
    'elles sont pas': 'elles ne sont pas',
    'je sais pas': 'je ne sais pas',
    'c\'est pas': 'ce n\'est pas',
    'il y a pas': 'il n\'y a pas',
    'y a pas': 'il n\'y a pas'
};

/**
 * Check grammar in a French text
 * @param {string} text - The text to check
 * @return {object} - Results with original text, corrected text, and changes
 */
function checkFrenchGrammar(text) {
    let correctedText = text;
    const changes = [];
    
    // First check for exact matches (case-insensitive)
    Object.keys(commonFrenchMistakes).forEach(mistake => {
        const regex = new RegExp('\\b' + mistake + '\\b', 'gi');
        const matches = correctedText.match(regex);
        
        if (matches) {
            matches.forEach(match => {
                const original = match;
                // Preserve case if possible
                let replacement = commonFrenchMistakes[mistake.toLowerCase()];
                if (original === original.toUpperCase()) {
                    replacement = replacement.toUpperCase();
                } else if (original[0] === original[0].toUpperCase()) {
                    replacement = replacement[0].toUpperCase() + replacement.slice(1);
                }
                
                changes.push({
                    original: original,
                    replacement: replacement,
                    type: 'grammar'
                });
            });
            
            correctedText = correctedText.replace(regex, function(match) {
                // Preserve case
                const replacement = commonFrenchMistakes[mistake.toLowerCase()];
                if (match === match.toUpperCase()) {
                    return replacement.toUpperCase();
                } else if (match[0] === match[0].toUpperCase()) {
                    return replacement[0].toUpperCase() + replacement.slice(1);
                }
                return replacement;
            });
        }
    });
    
    // Special handling for "different" -> "différent"
    if (correctedText.toLowerCase().includes('different')) {
        const originalText = correctedText;
        correctedText = correctedText.replace(/\bdifferent\b/gi, function(match) {
            // Preserve case
            if (match === match.toUpperCase()) {
                return 'DIFFÉRENT';
            } else if (match[0] === match[0].toUpperCase()) {
                return 'Différent';
            }
            return 'différent';
        });
        
        if (originalText !== correctedText) {
            changes.push({
                original: 'different',
                replacement: 'différent',
                type: 'special_handling'
            });
        }
    }
    
    return {
        original: text,
        corrected: correctedText,
        changes: changes,
        hasChanges: changes.length > 0
    };
}

/**
 * Highlight grammar mistakes in text
 * @param {string} text - The text to highlight
 * @param {Array} changes - The changes to highlight
 * @return {string} - HTML with highlighted mistakes
 */
function highlightGrammarMistakes(text, changes) {
    if (!changes || changes.length === 0) {
        return text;
    }
    
    let highlightedText = text;
    
    // Sort changes by position in reverse order to avoid offset issues
    const sortedChanges = [...changes].sort((a, b) => {
        const posA = highlightedText.toLowerCase().indexOf(a.original.toLowerCase());
        const posB = highlightedText.toLowerCase().indexOf(b.original.toLowerCase());
        return posB - posA;
    });
    
    // Apply highlighting for each change
    sortedChanges.forEach(change => {
        const regex = new RegExp('\\b' + change.original + '\\b', 'gi');
        highlightedText = highlightedText.replace(regex, 
            `<span class="grammar-mistake" data-original="${change.original}" data-replacement="${change.replacement}" title="Suggestion: ${change.replacement}">${change.original}</span>`
        );
    });
    
    return highlightedText;
}

/**
 * Create a grammar correction UI
 * @param {object} results - The grammar check results
 * @return {string} - HTML for the correction UI
 */
function createGrammarCorrectionUI(results) {
    if (!results.hasChanges) {
        return `<div class="alert alert-success">
            <i class="fa fa-check-circle"></i> Aucune erreur grammaticale détectée.
        </div>`;
    }
    
    let html = `
        <div class="grammar-correction-ui">
            <div class="alert alert-warning">
                <i class="fa fa-exclamation-triangle"></i> 
                <strong>${results.changes.length} erreur(s) grammaticale(s) détectée(s).</strong>
            </div>
            
            <div class="card mb-3">
                <div class="card-header bg-light">
                    <h6 class="mb-0">Texte avec erreurs</h6>
                </div>
                <div class="card-body">
                    <p class="grammar-highlighted-text">${highlightGrammarMistakes(results.original, results.changes)}</p>
                </div>
            </div>
            
            <div class="card mb-3">
                <div class="card-header bg-light">
                    <h6 class="mb-0">Texte corrigé</h6>
                </div>
                <div class="card-body">
                    <p>${results.corrected}</p>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header bg-light">
                    <h6 class="mb-0">Détails des corrections</h6>
                </div>
                <div class="card-body">
                    <ul class="list-group">`;
    
    results.changes.forEach(change => {
        html += `
            <li class="list-group-item">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <span class="text-danger">${change.original}</span> → 
                        <span class="text-success">${change.replacement}</span>
                    </div>
                </div>
            </li>`;
    });
    
    html += `
                    </ul>
                </div>
            </div>
        </div>
    `;
    
    return html;
}
