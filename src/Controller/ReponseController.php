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

#[Route('/reponse')]
final class ReponseController extends AbstractController{
    #[Route(name: 'app_reponse_index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        // Use a custom query to fetch all responses with their associated reclamations
        $conn = $entityManager->getConnection();
        $sql = 'SELECT r.*, rec.description as reclamation_description, rec.id as reclamation_id
                FROM reponse r
                LEFT JOIN reclamation rec ON r.id_Reclamation = rec.id';
        $stmt = $conn->prepare($sql);
        $resultSet = $stmt->executeQuery();
        $reponses = $resultSet->fetchAllAssociative();

        return $this->render('reponse/index.html.twig', [
            'reponses' => $reponses,
        ]);
    }

    #[Route('/new', name: 'app_reponse_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        // Get reclamations for the dropdown
        $conn = $entityManager->getConnection();
        $sql = 'SELECT id, description FROM reclamation';
        $stmt = $conn->prepare($sql);
        $resultSet = $stmt->executeQuery();
        $reclamations = $resultSet->fetchAllAssociative();

        // Create reclamation choices for the form
        $reclamationChoices = [];
        foreach ($reclamations as $reclamation) {
            $shortDesc = strlen($reclamation['description']) > 30
                ? substr($reclamation['description'], 0, 30) . '...'
                : $reclamation['description'];
            $reclamationChoices['#' . $reclamation['id'] . ' - ' . $shortDesc] = $reclamation['id'];
        }

        // Create a form without binding to an entity
        $form = $this->createFormBuilder()
            ->add('description', TextareaType::class, [
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
                'choices' => [
                    'Pending' => 'Pending',
                    'In Progress' => 'In Progress',
                    'Resolved' => 'Resolved',
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
            ->add('id_Reclamation', ChoiceType::class, [
                'choices' => $reclamationChoices,
                'label' => 'Réclamation associée',
                'placeholder' => 'Choisir une réclamation',
                'required' => false,
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez sélectionner une réclamation'
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

            // Validate reclamation
            if (empty($data['id_Reclamation'])) {
                $form->get('id_Reclamation')->addError(new FormError('Veuillez sélectionner une réclamation'));
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            // Insert the response using a direct SQL query
            $sql = 'INSERT INTO reponse (description, statut, date, id_Reclamation) VALUES (:description, :statut, :date, :id_Reclamation)';
            $stmt = $conn->prepare($sql);
            $stmt->executeStatement([
                'description' => $data['description'],
                'statut' => $data['statut'],
                'date' => $data['date']->format('Y-m-d H:i:s'),
                'id_Reclamation' => $data['id_Reclamation']
            ]);

            return $this->redirectToRoute('app_reponse_index', [], Response::HTTP_SEE_OTHER);
        }

        // Create an empty response array for the template
        $reponse = [
            'id' => null,
            'description' => '',
            'statut' => '',
            'date' => ''
        ];

        return $this->render('reponse/new.html.twig', [
            'reponse' => $reponse,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_reponse_show', methods: ['GET'])]
    public function show(EntityManagerInterface $entityManager, int $id): Response
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
            throw $this->createNotFoundException('Response not found');
        }

        return $this->render('reponse/show.html.twig', [
            'reponse' => $reponse,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_reponse_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, EntityManagerInterface $entityManager, int $id): Response
    {
        // Use a custom query to fetch a single response
        $conn = $entityManager->getConnection();
        $sql = 'SELECT * FROM reponse WHERE id = :id';
        $stmt = $conn->prepare($sql);
        $resultSet = $stmt->executeQuery(['id' => $id]);
        $reponse = $resultSet->fetchAssociative();

        if (!$reponse) {
            throw $this->createNotFoundException('Response not found');
        }

        // Get reclamations for the dropdown
        $sql = 'SELECT id, description FROM reclamation';
        $stmt = $conn->prepare($sql);
        $resultSet = $stmt->executeQuery();
        $reclamations = $resultSet->fetchAllAssociative();

        // Create reclamation choices for the form
        $reclamationChoices = [];
        foreach ($reclamations as $reclamation) {
            $shortDesc = strlen($reclamation['description']) > 30
                ? substr($reclamation['description'], 0, 30) . '...'
                : $reclamation['description'];
            $reclamationChoices['#' . $reclamation['id'] . ' - ' . $shortDesc] = $reclamation['id'];
        }

        // Create a form without binding to an entity
        $form = $this->createFormBuilder()
            ->add('description', TextareaType::class, [
                'data' => $reponse['description'],
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
                'data' => $reponse['statut'],
                'choices' => [
                    'Pending' => 'Pending',
                    'In Progress' => 'In Progress',
                    'Resolved' => 'Resolved',
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
                'data' => new \DateTime($reponse['date']),
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
            ->add('id_Reclamation', ChoiceType::class, [
                'data' => $reponse['id_Reclamation'],
                'choices' => $reclamationChoices,
                'label' => 'Réclamation associée',
                'placeholder' => 'Choisir une réclamation',
                'required' => false,
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez sélectionner une réclamation'
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

            // Validate reclamation
            if (empty($data['id_Reclamation'])) {
                $form->get('id_Reclamation')->addError(new FormError('Veuillez sélectionner une réclamation'));
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            // Update the response using a direct SQL query
            $sql = 'UPDATE reponse SET description = :description, statut = :statut, date = :date, id_Reclamation = :id_Reclamation WHERE id = :id';
            $stmt = $conn->prepare($sql);
            // Format the date as a string
            $formattedDate = $data['date'] instanceof \DateTime ? $data['date']->format('Y-m-d H:i:s') : $data['date'];

            $stmt->executeStatement([
                'id' => $id,
                'description' => $data['description'],
                'statut' => $data['statut'],
                'date' => $formattedDate,
                'id_Reclamation' => $data['id_Reclamation']
            ]);

            return $this->redirectToRoute('app_reponse_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('reponse/edit.html.twig', [
            'reponse' => $reponse,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_reponse_delete', methods: ['POST'])]
    public function delete(Request $request, EntityManagerInterface $entityManager, int $id): Response
    {
        if ($this->isCsrfTokenValid('delete'.$id, $request->getPayload()->getString('_token'))) {
            // Delete the response using a direct SQL query
            $conn = $entityManager->getConnection();
            $sql = 'DELETE FROM reponse WHERE id = :id';
            $stmt = $conn->prepare($sql);
            $stmt->executeStatement(['id' => $id]);
        }

        return $this->redirectToRoute('app_reponse_index', [], Response::HTTP_SEE_OTHER);
    }
}
