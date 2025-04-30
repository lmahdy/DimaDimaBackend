<?php

namespace App\Controller;

use App\Entity\Reclamation;
use App\Entity\Utilisateur;
use App\Form\ReclamationType;
use App\Repository\ReclamationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Form\FormError;

#[Route('/front/reclamation')]
class FrontReclamationController extends AbstractController
{
    #[Route('/', name: 'app_front_reclamation_index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        // Use a custom query to fetch all reclamations
        $conn = $entityManager->getConnection();
        $sql = 'SELECT r.*, u.nom, u.prenom FROM reclamation r
                LEFT JOIN utilisateur u ON r.Utilisateur_id = u.id
                ORDER BY r.date DESC';
        $stmt = $conn->prepare($sql);
        $resultSet = $stmt->executeQuery();
        $reclamations = $resultSet->fetchAllAssociative();

        return $this->render('front_reclamation/index.html.twig', [
            'reclamations' => $reclamations,
        ]);
    }

    #[Route('/new', name: 'app_front_reclamation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        // We'll use a fixed user ID (1) instead of showing a dropdown
        $conn = $entityManager->getConnection();

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

            // Insert the reclamation using a direct SQL query with fixed user ID (1)
            $sql = 'INSERT INTO reclamation (description, statut, date, Utilisateur_id) VALUES (:description, :statut, :date, :Utilisateur_id)';
            $stmt = $conn->prepare($sql);
            $stmt->executeStatement([
                'description' => $data['description'],
                'statut' => $data['statut'],
                'date' => $data['date']->format('Y-m-d'),
                'Utilisateur_id' => 1 // Fixed user ID
            ]);

            return $this->redirectToRoute('app_front_reclamation_index', [], Response::HTTP_SEE_OTHER);
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

    #[Route('/{id}', name: 'app_front_reclamation_show', methods: ['GET'])]
    public function show(EntityManagerInterface $entityManager, int $id): Response
    {
        // Use a custom query to fetch a single reclamation with user details
        $conn = $entityManager->getConnection();
        $sql = 'SELECT r.*, u.nom, u.prenom FROM reclamation r
                LEFT JOIN utilisateur u ON r.Utilisateur_id = u.id
                WHERE r.id = :id';
        $stmt = $conn->prepare($sql);
        $resultSet = $stmt->executeQuery(['id' => $id]);
        $reclamation = $resultSet->fetchAssociative();

        if (!$reclamation) {
            throw $this->createNotFoundException('Reclamation not found');
        }

        return $this->render('front_reclamation/show.html.twig', [
            'reclamation' => $reclamation,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_front_reclamation_edit', methods: ['GET', 'POST'])]
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

            return $this->redirectToRoute('app_front_reclamation_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('front_reclamation/edit.html.twig', [
            'reclamation' => $reclamation,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_front_reclamation_delete', methods: ['POST'])]
    public function delete(Request $request, EntityManagerInterface $entityManager, int $id): Response
    {
        if ($this->isCsrfTokenValid('delete'.$id, $request->getPayload()->getString('_token'))) {
            // Delete the reclamation using a direct SQL query
            $conn = $entityManager->getConnection();
            $sql = 'DELETE FROM reclamation WHERE id = :id';
            $stmt = $conn->prepare($sql);
            $stmt->executeStatement(['id' => $id]);
        }

        return $this->redirectToRoute('app_front_reclamation_index', [], Response::HTTP_SEE_OTHER);
    }
}
