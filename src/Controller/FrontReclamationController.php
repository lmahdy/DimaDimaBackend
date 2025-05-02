<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Form\FormError;
use Dompdf\Dompdf;
use Dompdf\Options;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/front/reclamation')]
class FrontReclamationController extends AbstractController
{
    #[Route('/', name: 'front_reclamation_index', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $entityManager, PaginatorInterface $paginator): Response
    {
        // Use a custom query to fetch all reclamations
        $conn = $entityManager->getConnection();
        $sql = 'SELECT * FROM reclamation ORDER BY date DESC';
        $stmt = $conn->prepare($sql);
        $resultSet = $stmt->executeQuery();
        $allReclamations = $resultSet->fetchAllAssociative();

        // Paginate the results
        $pagination = $paginator->paginate(
            $allReclamations, // Data to paginate
            $request->query->getInt('page', 1), // Current page number, default to 1
            10 // Items per page
        );

        return $this->render('front_reclamation/index.html.twig', [
            'pagination' => $pagination,
        ]);
    }

    #[Route('/check-grammar', name: 'front_reclamation_check_grammar', methods: ['POST'])]
    public function checkGrammar(Request $request, HttpClientInterface $httpClient): JsonResponse
    {
        // Get the text to check from the request
        $text = $request->request->get('text');

        if (empty($text)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Aucun texte fourni'
            ]);
        }

        try {
            // Use RapidAPI Grammar Checker API
            $apiUrl = 'https://ai-grammar-checker-i-gpt.p.rapidapi.com/api/v1/correctAndRephrase';

            // Make the API request with RapidAPI headers
            $response = $httpClient->request('GET', $apiUrl, [
                'query' => [
                    'text' => $text
                ],
                'headers' => [
                    'X-RapidAPI-Key' => '785640fb0emsh4c5ac04753793dp1c7232jsnf8323dfb6bbd',
                    'X-RapidAPI-Host' => 'ai-grammar-checker-i-gpt.p.rapidapi.com'
                ],
                'timeout' => 15 // 15 seconds timeout
            ]);

            // Get the response data
            $statusCode = $response->getStatusCode();

            // Log the raw response for debugging
            $rawResponse = $response->getContent();
            file_put_contents(__DIR__ . '/../../var/log/rapidapi_response.log', date('Y-m-d H:i:s') . " - Raw response: " . $rawResponse . PHP_EOL, FILE_APPEND);

            if ($statusCode === 200) {
                $data = $response->toArray();

                // Log the parsed data
                file_put_contents(__DIR__ . '/../../var/log/rapidapi_parsed.log', date('Y-m-d H:i:s') . " - Parsed data: " . json_encode($data, JSON_PRETTY_PRINT) . PHP_EOL, FILE_APPEND);

                // Check if the API returned a corrected text
                if (isset($data['correctedText'])) {
                    $correctedText = $data['correctedText'];

                    // If the text was changed, create details about the changes
                    if ($correctedText !== $text) {
                        // Create a simple diff to show what changed
                        $details = [];

                        // Compare original and corrected text to find differences
                        similar_text($text, $correctedText, $similarity);

                        // Add a general correction entry
                        $details[] = [
                            'id' => 'rapidapi_correction',
                            'offset' => 0,
                            'length' => strlen($text),
                            'description' => 'Correction grammaticale et reformulation',
                            'bad' => $text,
                            'better' => [$correctedText]
                        ];

                        return new JsonResponse([
                            'success' => true,
                            'original' => $text,
                            'corrected' => $correctedText,
                            'errors' => 1, // We don't get specific error count from this API
                            'details' => $details,
                            'similarity' => round($similarity, 2) . '%'
                        ]);
                    } else {
                        // No changes needed
                        return new JsonResponse([
                            'success' => true,
                            'original' => $text,
                            'corrected' => $text,
                            'errors' => 0,
                            'details' => []
                        ]);
                    }
                } else {
                    // API returned unexpected format
                    return new JsonResponse([
                        'success' => false,
                        'message' => "Format de réponse inattendu de l'API"
                    ]);
                }
            } else {
                return new JsonResponse([
                    'success' => false,
                    'message' => "Erreur lors de la vérification (Code: $statusCode)"
                ]);
            }
        } catch (\Exception $e) {
            // Log the error
            $errorMessage = $e->getMessage();

            // Use our improved fallback grammar checker
            $correctedText = $this->fallbackGrammarCheck($text);

            // If the fallback made changes
            if ($correctedText !== $text) {
                // Create a structured response with details
                $details = [];

                // Compare the original and corrected text to identify changes
                $originalWords = preg_split('/\s+/', $text);
                $correctedWords = preg_split('/\s+/', $correctedText);

                // Find differences between original and corrected text
                similar_text($text, $correctedText, $similarity);

                if (count($originalWords) === count($correctedWords)) {
                    // Word by word comparison
                    foreach ($originalWords as $i => $word) {
                        if (isset($correctedWords[$i]) && $word !== $correctedWords[$i]) {
                            $details[] = [
                                'id' => 'fallback_' . $i,
                                'offset' => strpos($text, $word),
                                'length' => strlen($word),
                                'description' => 'Correction automatique (mode hors ligne)',
                                'bad' => $word,
                                'better' => [$correctedWords[$i]]
                            ];
                        }
                    }
                }

                // If no specific details were found, provide a general correction
                if (empty($details)) {
                    $details[] = [
                        'id' => 'fallback_general',
                        'offset' => 0,
                        'length' => strlen($text),
                        'description' => 'Correction automatique (mode hors ligne)',
                        'bad' => $text,
                        'better' => [$correctedText]
                    ];
                }

                return new JsonResponse([
                    'success' => true,
                    'original' => $text,
                    'corrected' => $correctedText,
                    'errors' => count($details),
                    'details' => $details,
                    'fallback' => true,
                    'message' => 'API indisponible. Utilisation du correcteur hors ligne. Similarité: ' . round($similarity) . '%'
                ]);
            } else {
                return new JsonResponse([
                    'success' => false,
                    'message' => "Erreur lors de la vérification: $errorMessage. Le correcteur hors ligne n'a pas trouvé d'erreurs."
                ]);
            }
        }
    }

    /**
     * Fallback grammar and spelling checker
     * This is a simple implementation that corrects common French mistakes
     */
    private function fallbackGrammarCheck(string $text): string
    {
        // If text is empty, return as is
        if (empty(trim($text))) {
            return $text;
        }

        // Create a copy of the original text
        $correctedText = $text;
        $details = [];

        // Function to apply a correction and track the change
        $applyCorrection = function($pattern, $replacement, $subject, $isRegex = false) use (&$details) {
            $original = $subject;

            if ($isRegex) {
                $result = preg_replace($pattern, $replacement, $subject);
            } else {
                $result = str_ireplace($pattern, $replacement, $subject);
            }

            if ($result !== $original) {
                $details[] = [
                    'pattern' => $pattern,
                    'replacement' => $replacement,
                    'before' => $original,
                    'after' => $result
                ];
            }

            return $result;
        };

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
            $correctedText = $applyCorrection($pattern, $correction, $correctedText, true);
        }

        // Adverbs: ou -> où when it means "where"
        $adverbs = [
            'ou est' => 'où est',
            'ou sont' => 'où sont',
            'ou se trouve' => 'où se trouve',
            'ou vas-tu' => 'où vas-tu',
            'ou allez-vous' => 'où allez-vous',
        ];

        foreach ($adverbs as $mistake => $correction) {
            $pattern = '/\b' . preg_quote($mistake, '/') . '\b/i';
            $correctedText = $applyCorrection($pattern, $correction, $correctedText, true);
        }

        // 2. Common spelling mistakes with accents
        $accentedWords = [
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
            'different' => 'différent',
            'experience' => 'expérience',
            'desole' => 'désolé',
            'interesse' => 'intéressé',
        ];

        // First, do a direct string replacement for the most common issues
        foreach ($accentedWords as $mistake => $correction) {
            // Simple string replacement first (more reliable for exact matches)
            $correctedText = $applyCorrection($mistake, $correction, $correctedText, false);

            // Also try with capitalized first letter
            $capitalMistake = ucfirst($mistake);
            $capitalCorrection = ucfirst($correction);
            $correctedText = $applyCorrection($capitalMistake, $capitalCorrection, $correctedText, false);
        }

        // Then try with word boundaries for any remaining issues
        foreach ($accentedWords as $mistake => $correction) {
            $pattern = '/\b' . preg_quote($mistake, '/') . '\b/i';
            $correctedText = $applyCorrection($pattern, $correction, $correctedText, true);
        }

        // Special handling for "different" -> "différent" which might be missed
        if (stripos($correctedText, 'different') !== false) {
            $originalText = $correctedText;
            $correctedText = str_ireplace('different', 'différent', $correctedText);

            // Add to details if a change was made
            if ($originalText !== $correctedText) {
                $details[] = [
                    'pattern' => 'different',
                    'replacement' => 'différent',
                    'before' => $originalText,
                    'after' => $correctedText
                ];
            }
        }

        // Also check for "Different" with capital D
        if (stripos($correctedText, 'Different') !== false) {
            $originalText = $correctedText;
            $correctedText = str_ireplace('Different', 'Différent', $correctedText);

            // Add to details if a change was made
            if ($originalText !== $correctedText) {
                $details[] = [
                    'pattern' => 'Different',
                    'replacement' => 'Différent',
                    'before' => $originalText,
                    'after' => $correctedText
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
            $correctedText = $applyCorrection($pattern, $replacement, $correctedText, true);
        }

        // 4. Punctuation
        $punctuationPatterns = [
            '/ +,/' => ',',
            '/ +\\./' => '.',
            '/ +!/' => '!',
            '/ +\\?/' => '?',
            '/\\( +/' => '(',
            '/ +\\)/' => ')',
            '/ +;/' => ';',
            '/ +:/' => ':',
            '/,(?! )/' => ', ', // Add space after comma if missing
            '\\.(?! |$)/' => '. ', // Add space after period if missing
        ];

        foreach ($punctuationPatterns as $pattern => $replacement) {
            $correctedText = $applyCorrection('/' . $pattern . '/', $replacement, $correctedText, true);
        }

        // 5. Capitalization at the beginning of sentences
        $correctedText = $applyCorrection('/^([a-zàáâäæçèéêëìíîïòóôöùúûüÿ])/', strtoupper('$1'), $correctedText, true);
        $correctedText = $applyCorrection('/\\. ([a-zàáâäæçèéêëìíîïòóôöùúûüÿ])/', '. ' . strtoupper('$1'), $correctedText, true);

        return $correctedText;
    }

    #[Route('/new', name: 'front_reclamation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        // Get users for the dropdown without using Doctrine entities
        $conn = $entityManager->getConnection();
        $sql = 'SELECT id, nom, prenom FROM utilisateur';
        $stmt = $conn->prepare($sql);
        $resultSet = $stmt->executeQuery();
        $users = $resultSet->fetchAllAssociative();

        // Create user choices for the form
        $userChoices = [];
        foreach ($users as $user) {
            $userChoices[$user['nom'] . ' ' . $user['prenom']] = $user['id'];
        }

        // Create a form without binding to an entity
        $form = $this->createFormBuilder()
            ->add('description', TextareaType::class, [
                'constraints' => [
                    new NotBlank([
                        'message' => 'Ce champ est obligatoire',
                    ]),
                    new Length([
                        'min' => 10,
                        'minMessage' => 'Votre description doit contenir au moins {{ limit }} caractères',
                        'max' => 1000,
                        'maxMessage' => 'Votre description ne peut pas dépasser {{ limit }} caractères'
                    ])
                ],
                'attr' => [
                    'rows' => 5,
                    'class' => 'form-control'
                ],
                'label' => 'Description'
            ])
            ->add('statut', ChoiceType::class, [
                'choices' => [
                    'New' => 'New',
                    'In Progress' => 'In Progress',
                    'Waiting for Response' => 'Waiting for Response',
                    'Closed' => 'Closed'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez sélectionner un statut'
                    ])
                ],
                'attr' => [
                    'class' => 'form-control'
                ],
                'label' => 'Statut'
            ])
            ->add('date', DateType::class, [
                'widget' => 'single_text',
                'data' => new \DateTime(),
                'constraints' => [
                    new NotNull([
                        'message' => 'Veuillez sélectionner une date'
                    ])
                ],
                'attr' => [
                    'class' => 'form-control'
                ],
                'label' => 'Date'
            ])
            ->add('Utilisateur_id', ChoiceType::class, [
                'label' => 'Utilisateur',
                'choices' => $userChoices,
                'placeholder' => 'Choisir un utilisateur',
                'required' => false,
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez sélectionner un utilisateur'
                    ])
                ],
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->getForm();

        $form->handleRequest($request);

        // Custom validation
        if ($form->isSubmitted()) {
            $data = $form->getData();

            // Validate description
            if (empty($data['description'])) {
                $form->get('description')->addError(new FormError('Ce champ est obligatoire'));
            } elseif (strlen($data['description']) < 10) {
                $form->get('description')->addError(new FormError('Votre description doit contenir au moins 10 caractères'));
            }

            // Validate status
            if (empty($data['statut'])) {
                $form->get('statut')->addError(new FormError('Veuillez sélectionner un statut'));
            }

            // Validate date
            if (empty($data['date'])) {
                $form->get('date')->addError(new FormError('Veuillez sélectionner une date'));
            }

            // Validate user
            if (empty($data['Utilisateur_id'])) {
                $form->get('Utilisateur_id')->addError(new FormError('Veuillez sélectionner un utilisateur'));
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            // Insert the reclamation using a direct SQL query
            $sql = 'INSERT INTO reclamation (description, statut, date, Utilisateur_id) VALUES (:description, :statut, :date, :Utilisateur_id)';
            $stmt = $conn->prepare($sql);
            $stmt->executeStatement([
                'description' => $data['description'],
                'statut' => $data['statut'],
                'date' => $data['date']->format('Y-m-d'),
                'Utilisateur_id' => $data['Utilisateur_id']
            ]);

            return $this->redirectToRoute('front_reclamation_index', [], Response::HTTP_SEE_OTHER);
        }

        // Create an empty reclamation array for the template
        $reclamation = [
            'id' => null,
            'description' => '',
            'statut' => '',
            'date' => ''
        ];

        return $this->render('front_reclamation/new.html.twig', [
            'reclamation' => $reclamation,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'front_reclamation_show', methods: ['GET'])]
    public function show(EntityManagerInterface $entityManager, int $id): Response
    {
        // Use a custom query to fetch a single reclamation
        $conn = $entityManager->getConnection();
        $sql = 'SELECT * FROM reclamation WHERE id = :id';
        $stmt = $conn->prepare($sql);
        $resultSet = $stmt->executeQuery(['id' => $id]);
        $reclamation = $resultSet->fetchAssociative();

        if (!$reclamation) {
            throw $this->createNotFoundException('Réclamation non trouvée');
        }

        // Get responses for this reclamation
        $sql = 'SELECT * FROM reponse WHERE id_Reclamation = :id ORDER BY date DESC';
        $stmt = $conn->prepare($sql);
        $resultSet = $stmt->executeQuery(['id' => $id]);
        $reponses = $resultSet->fetchAllAssociative();

        return $this->render('front_reclamation/show.html.twig', [
            'reclamation' => $reclamation,
            'reponses' => $reponses,
        ]);
    }

    #[Route('/{id}/edit', name: 'front_reclamation_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, EntityManagerInterface $entityManager, int $id): Response
    {
        // Use a custom query to fetch a single reclamation
        $conn = $entityManager->getConnection();
        $sql = 'SELECT * FROM reclamation WHERE id = :id';
        $stmt = $conn->prepare($sql);
        $resultSet = $stmt->executeQuery(['id' => $id]);
        $reclamation = $resultSet->fetchAssociative();

        if (!$reclamation) {
            throw $this->createNotFoundException('Réclamation non trouvée');
        }

        // Create a form without binding to an entity
        $form = $this->createFormBuilder()
            ->add('description', TextareaType::class, [
                'data' => $reclamation['description'],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Ce champ est obligatoire'
                    ]),
                    new Length([
                        'min' => 10,
                        'minMessage' => 'Votre description doit contenir au moins {{ limit }} caractères',
                        'max' => 1000,
                        'maxMessage' => 'Votre description ne peut pas dépasser {{ limit }} caractères'
                    ])
                ],
                'attr' => [
                    'rows' => 5,
                    'class' => 'form-control'
                ],
                'label' => 'Description'
            ])
            ->add('statut', ChoiceType::class, [
                'data' => $reclamation['statut'],
                'choices' => [
                    'New' => 'New',
                    'In Progress' => 'In Progress',
                    'Waiting for Response' => 'Waiting for Response',
                    'Closed' => 'Closed'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez sélectionner un statut'
                    ])
                ],
                'attr' => [
                    'class' => 'form-control'
                ],
                'label' => 'Statut'
            ])
            ->add('date', DateType::class, [
                'data' => new \DateTime($reclamation['date']),
                'widget' => 'single_text',
                'constraints' => [
                    new NotNull([
                        'message' => 'Veuillez sélectionner une date'
                    ])
                ],
                'attr' => [
                    'class' => 'form-control'
                ],
                'label' => 'Date'
            ])
            ->getForm();

        $form->handleRequest($request);

        // Custom validation
        if ($form->isSubmitted()) {
            $data = $form->getData();

            // Validate description
            if (empty($data['description'])) {
                $form->get('description')->addError(new FormError('Ce champ est obligatoire'));
            } elseif (strlen($data['description']) < 10) {
                $form->get('description')->addError(new FormError('Votre description doit contenir au moins 10 caractères'));
            }

            // Validate status
            if (empty($data['statut'])) {
                $form->get('statut')->addError(new FormError('Veuillez sélectionner un statut'));
            }

            // Validate date
            if (empty($data['date'])) {
                $form->get('date')->addError(new FormError('Veuillez sélectionner une date'));
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $data = $form->getData();

                // Start a transaction
                $conn->beginTransaction();

                // Update the reclamation using a direct SQL query
                $sql = 'UPDATE reclamation SET description = :description, statut = :statut, date = :date WHERE id = :id';
                $stmt = $conn->prepare($sql);
                // Format the date as a string
                $formattedDate = $data['date'] instanceof \DateTime ? $data['date']->format('Y-m-d') : $data['date'];

                $stmt->executeStatement([
                    'id' => $id,
                    'description' => $data['description'],
                    'statut' => $data['statut'],
                    'date' => $formattedDate,
                ]);

                // Commit the transaction
                $conn->commit();

                $this->addFlash('success', 'La réclamation a été mise à jour avec succès.');
                return $this->redirectToRoute('front_reclamation_show', ['id' => $id], Response::HTTP_SEE_OTHER);
            } catch (\Exception $e) {
                // Rollback the transaction in case of error
                if ($conn->isTransactionActive()) {
                    $conn->rollBack();
                }

                $this->addFlash('error', 'Une erreur est survenue lors de la mise à jour: ' . $e->getMessage());
            }
        }

        return $this->render('front_reclamation/edit.html.twig', [
            'reclamation' => $reclamation,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/pdf', name: 'front_reclamation_pdf', methods: ['GET'])]
    public function generatePdf(EntityManagerInterface $entityManager, int $id): Response
    {
        // Use a custom query to fetch a single reclamation
        $conn = $entityManager->getConnection();
        $sql = 'SELECT * FROM reclamation WHERE id = :id';
        $stmt = $conn->prepare($sql);
        $resultSet = $stmt->executeQuery(['id' => $id]);
        $reclamation = $resultSet->fetchAssociative();

        if (!$reclamation) {
            throw $this->createNotFoundException('Réclamation non trouvée');
        }

        // Get responses for this reclamation
        $sql = 'SELECT * FROM reponse WHERE id_Reclamation = :id ORDER BY date DESC';
        $stmt = $conn->prepare($sql);
        $resultSet = $stmt->executeQuery(['id' => $id]);
        $reponses = $resultSet->fetchAllAssociative();

        // Configure Dompdf
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Arial');

        // Instantiate Dompdf
        $dompdf = new Dompdf($options);

        // Render the template to HTML
        $html = $this->renderView('front_reclamation/pdf.html.twig', [
            'reclamation' => $reclamation,
            'reponses' => $reponses,
        ]);

        // Load HTML to Dompdf
        $dompdf->loadHtml($html);

        // Set paper size and orientation
        $dompdf->setPaper('A4', 'portrait');

        // Render the PDF
        $dompdf->render();

        // Generate a filename
        $filename = 'reclamation_' . $id . '_' . date('Y-m-d') . '.pdf';

        // Return the PDF as a response
        return new Response(
            $dompdf->output(),
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $filename . '"'
            ]
        );
    }

    #[Route('/{id}/delete', name: 'front_reclamation_delete', methods: ['POST'])]
    public function delete(Request $request, EntityManagerInterface $entityManager, int $id): Response
    {
        if ($this->isCsrfTokenValid('delete'.$id, $request->getPayload()->getString('_token'))) {
            try {
                // Get the database connection
                $conn = $entityManager->getConnection();

                // Start a transaction
                $conn->beginTransaction();

                // First, delete any associated responses
                $sql = 'DELETE FROM reponse WHERE id_Reclamation = :id';
                $stmt = $conn->prepare($sql);
                $stmt->executeStatement(['id' => $id]);

                // Then, delete the reclamation
                $sql = 'DELETE FROM reclamation WHERE id = :id';
                $stmt = $conn->prepare($sql);
                $stmt->executeStatement(['id' => $id]);

                // Commit the transaction
                $conn->commit();

                $this->addFlash('success', 'La réclamation a été supprimée avec succès.');
            } catch (\Exception $e) {
                // Rollback the transaction in case of error
                if ($conn->isTransactionActive()) {
                    $conn->rollBack();
                }

                $this->addFlash('error', 'Une erreur est survenue lors de la suppression: ' . $e->getMessage());
            }
        }

        return $this->redirectToRoute('front_reclamation_index', [], Response::HTTP_SEE_OTHER);
    }
}
