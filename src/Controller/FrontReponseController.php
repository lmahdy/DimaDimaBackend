<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/front/reponse')]
class FrontReponseController extends AbstractController
{
    #[Route('/', name: 'front_reponse_index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        // Use a custom query to fetch all responses with their associated reclamations
        $conn = $entityManager->getConnection();
        $sql = 'SELECT r.*, rec.description as reclamation_description, rec.id as reclamation_id
                FROM reponse r
                LEFT JOIN reclamation rec ON r.id_Reclamation = rec.id
                ORDER BY r.date DESC';
        $stmt = $conn->prepare($sql);
        $resultSet = $stmt->executeQuery();
        $reponses = $resultSet->fetchAllAssociative();

        return $this->render('front_reponse/index.html.twig', [
            'reponses' => $reponses,
        ]);
    }

    #[Route('/{id}', name: 'front_reponse_show', methods: ['GET'])]
    public function show(Request $request, EntityManagerInterface $entityManager, HttpClientInterface $httpClient, int $id): Response
    {
        // Use a custom query to fetch a single response with its associated reclamation
        $conn = $entityManager->getConnection();
        $sql = 'SELECT r.*, rec.description as reclamation_description, rec.id as reclamation_id
                FROM reponse r
                LEFT JOIN reclamation rec ON r.id_Reclamation = rec.id
                WHERE r.id = :id';
        $stmt = $conn->prepare($sql);
        $resultSet = $stmt->executeQuery(['id' => $id]);
        $reponse = $resultSet->fetchAssociative();

        if (!$reponse) {
            throw $this->createNotFoundException('Réponse non trouvée');
        }

        // Get the requested target language from the query parameter, default to original (no translation)
        $targetLang = $request->query->get('lang', 'original');

        // Store the original description
        $originalDescription = $reponse['description'];
        $translatedDescription = null;
        $translationError = null;

        // If a translation is requested
        if ($targetLang !== 'original' && in_array($targetLang, ['en', 'ar'])) {
            try {
                // Use MyMemory Translation API (free for limited usage)
                // Map our language codes to MyMemory format
                $sourceLang = 'fr'; // French
                $targetLanguage = $targetLang;
                $langPair = $sourceLang . '|' . $targetLanguage;

                // Prepare the API URL with query parameters
                $apiUrl = 'https://api.mymemory.translated.net/get';
                $queryParams = [
                    'q' => $originalDescription,
                    'langpair' => $langPair,
                    'de' => 'anonymous@user.com', // Generic email for API usage
                ];

                // Make the API request
                $response = $httpClient->request('GET', $apiUrl, [
                    'query' => $queryParams,
                    'timeout' => 10, // 10 seconds timeout
                ]);

                // Get the response data
                $statusCode = $response->getStatusCode();
                if ($statusCode === 200) {
                    $data = $response->toArray();

                    if (isset($data['responseData']) && isset($data['responseData']['translatedText'])) {
                        $translatedDescription = $data['responseData']['translatedText'];

                        // Check if there's a match quality indicator
                        if (isset($data['responseData']['match']) && $data['responseData']['match'] < 0.5) {
                            // If match quality is low, add a note
                            $translatedDescription .= "\n\n(Note: Cette traduction peut ne pas être précise à 100%)";
                        }
                    } else {
                        $translationError = 'La traduction n\'a pas pu être récupérée.';
                    }
                } else {
                    $translationError = 'Erreur lors de la traduction (Code: ' . $statusCode . ')';
                }
            } catch (\Exception $e) {
                // Log the error
                $errorMessage = $e->getMessage();

                // Fallback to a simple dictionary-based translation if the API fails
                $fallbackTranslations = [
                    'en' => [
                        'Bonjour' => 'Hello',
                        'Merci' => 'Thank you',
                        'Problème' => 'Problem',
                        'Réclamation' => 'Claim',
                        'Réponse' => 'Response',
                        'Nous avons bien reçu votre réclamation' => 'We have received your claim',
                        'Nous sommes désolés pour le désagrément' => 'We are sorry for the inconvenience',
                        'Votre problème sera résolu sous peu' => 'Your issue will be resolved shortly',
                        'Merci pour votre patience' => 'Thank you for your patience',
                        'Cordialement' => 'Best regards',
                    ],
                    'ar' => [
                        'Bonjour' => 'مرحبا',
                        'Merci' => 'شكرا',
                        'Problème' => 'مشكلة',
                        'Réclamation' => 'شكوى',
                        'Réponse' => 'رد',
                        'Nous avons bien reçu votre réclamation' => 'لقد تلقينا شكواك',
                        'Nous sommes désolés pour le désagrément' => 'نأسف للإزعاج',
                        'Votre problème sera résolu sous peu' => 'سيتم حل مشكلتك قريبًا',
                        'Merci pour votre patience' => 'شكرا لصبرك',
                        'Cordialement' => 'مع أطيب التحيات',
                    ]
                ];

                // Try to do a simple word-by-word translation
                if (isset($fallbackTranslations[$targetLang])) {
                    $text = $originalDescription;
                    foreach ($fallbackTranslations[$targetLang] as $fr => $trans) {
                        $text = str_ireplace($fr, $trans, $text);
                    }

                    // If we made at least some replacements, use this as a fallback
                    if ($text !== $originalDescription) {
                        $translatedDescription = $text;
                        $translationError = 'Note: La traduction automatique a échoué. Ceci est une traduction partielle basée sur un dictionnaire simple.';
                    } else {
                        $translationError = 'Erreur lors de la traduction: ' . $errorMessage . '. Aucune traduction de secours disponible.';
                    }
                } else {
                    $translationError = 'Erreur lors de la traduction: ' . $errorMessage;
                }
            }
        }

        return $this->render('front_reponse/show.html.twig', [
            'reponse' => $reponse,
            'originalDescription' => $originalDescription,
            'translatedDescription' => $translatedDescription,
            'translationError' => $translationError,
            'targetLang' => $targetLang
        ]);
    }

    #[Route('/{id}/translate/{lang}', name: 'front_reponse_translate', methods: ['GET'])]
    public function translate(Request $request, int $id, string $lang): Response
    {
        // Validate language
        if (!in_array($lang, ['original', 'en', 'ar'])) {
            throw $this->createNotFoundException('Language not supported');
        }

        // Redirect to the show page with the language parameter
        return $this->redirectToRoute('front_reponse_show', [
            'id' => $id,
            'lang' => $lang
        ]);
    }
}
