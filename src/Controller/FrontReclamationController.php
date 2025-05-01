<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;
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
    #[Route('/', name: 'front_reclamation_index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        // Use a custom query to fetch all reclamations
        $conn = $entityManager->getConnection();
        $sql = 'SELECT * FROM reclamation ORDER BY date DESC';
        $stmt = $conn->prepare($sql);
        $resultSet = $stmt->executeQuery();
        $reclamations = $resultSet->fetchAllAssociative();

        return $this->render('front_reclamation/index.html.twig', [
            'reclamations' => $reclamations,
        ]);
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

    #[Route('/{id}/pdf', name: 'front_reclamation_pdf', methods: ['GET'])]
    public function exportPdf(EntityManagerInterface $entityManager, int $id): Response
    {
        // Get the reclamation data
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

        // Render the PDF template
        $html = $this->renderView('front_reclamation/pdf.html.twig', [
            'reclamation' => $reclamation,
            'reponses' => $reponses,
        ]);

        // Configure Dompdf
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Generate the PDF file name
        $filename = sprintf('reclamation-%d.pdf', $id);

        // Return the PDF as a response
        return new Response(
            $dompdf->output(),
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
            ]
        );
    }
}
