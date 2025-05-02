<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Form\FormError;

#[Route('/reclamation')]
class ReclamationController extends AbstractController
{
    #[Route('/', name: 'app_reclamation_index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        // Use a custom query to fetch all reclamations
        $conn = $entityManager->getConnection();
        $sql = 'SELECT * FROM reclamation';
        $stmt = $conn->prepare($sql);
        $resultSet = $stmt->executeQuery();
        $reclamations = $resultSet->fetchAllAssociative();

        return $this->render('reclamation/index.html.twig', [
            'reclamations' => $reclamations,
        ]);
    }

    #[Route('/test-search', name: 'app_reclamation_test_search')]
    public function testSearch(EntityManagerInterface $entityManager): Response
    {
        // Simple test endpoint to verify search functionality
        $conn = $entityManager->getConnection();
        $sql = 'SELECT * FROM reclamation LIMIT 5';
        $stmt = $conn->prepare($sql);
        $resultSet = $stmt->executeQuery();
        $reclamations = $resultSet->fetchAllAssociative();

        return $this->json([
            'reclamations' => $reclamations,
            'count' => count($reclamations),
            'success' => true
        ]);
    }

    #[Route('/search', name: 'app_reclamation_search', methods: ['GET'])]
    public function search(Request $request, EntityManagerInterface $entityManager): Response
    {
        $searchTerm = $request->query->get('q', '');

        // Use a custom query to search reclamations by description
        $conn = $entityManager->getConnection();

        try {
            // If search term is empty, return all reclamations
            if (empty($searchTerm)) {
                $sql = 'SELECT * FROM reclamation ORDER BY date DESC';
                $stmt = $conn->prepare($sql);
                $resultSet = $stmt->executeQuery();
            } else {
                // Search by description
                $searchPattern = '%' . $searchTerm . '%';
                $sql = 'SELECT * FROM reclamation WHERE description LIKE ? ORDER BY date DESC';
                $stmt = $conn->prepare($sql);
                $resultSet = $stmt->executeQuery([$searchPattern]);
            }

            $reclamations = $resultSet->fetchAllAssociative();

            // Return JSON response for AJAX
            return $this->json([
                'reclamations' => $reclamations,
                'count' => count($reclamations),
                'searchTerm' => $searchTerm,
                'success' => true
            ]);
        } catch (\Exception $e) {
            // Return error response
            return $this->json([
                'reclamations' => [],
                'count' => 0,
                'searchTerm' => $searchTerm,
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    #[Route('/new', name: 'app_reclamation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        // Check if the user is logged in
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        // Check if the user is an admin
        if ($this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Admins cannot create reclamations');
        }

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

            return $this->redirectToRoute('app_reclamation_index', [], Response::HTTP_SEE_OTHER);
        }

        // Create an empty reclamation array for the template
        $reclamation = [
            'id' => null,
            'description' => '',
            'statut' => '',
            'date' => ''
        ];

        return $this->render('reclamation/new.html.twig', [
            'reclamation' => $reclamation,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_reclamation_show', methods: ['GET'])]
    public function show(EntityManagerInterface $entityManager, int $id): Response
    {
        // Use a custom query to fetch a single reclamation
        $conn = $entityManager->getConnection();
        $sql = 'SELECT * FROM reclamation WHERE id = :id';
        $stmt = $conn->prepare($sql);
        $resultSet = $stmt->executeQuery(['id' => $id]);
        $reclamation = $resultSet->fetchAssociative();

        if (!$reclamation) {
            throw $this->createNotFoundException('Reclamation not found');
        }

        return $this->render('reclamation/show.html.twig', [
            'reclamation' => $reclamation,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_reclamation_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, EntityManagerInterface $entityManager, int $id): Response
    {
        // Use a custom query to fetch a single reclamation
        $conn = $entityManager->getConnection();
        $sql = 'SELECT * FROM reclamation WHERE id = :id';
        $stmt = $conn->prepare($sql);
        $resultSet = $stmt->executeQuery(['id' => $id]);
        $reclamation = $resultSet->fetchAssociative();

        if (!$reclamation) {
            throw $this->createNotFoundException('Reclamation not found');
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
            $data = $form->getData();

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

            return $this->redirectToRoute('app_reclamation_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('reclamation/edit.html.twig', [
            'reclamation' => $reclamation,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_reclamation_delete', methods: ['POST'])]
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

        return $this->redirectToRoute('app_reclamation_index', [], Response::HTTP_SEE_OTHER);
    }
}
