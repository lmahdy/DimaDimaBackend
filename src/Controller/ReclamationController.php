<?php

namespace App\Controller;


use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/reclamation')]
final class ReclamationController extends AbstractController{
    #[Route(name: 'app_reclamation_index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        // Use a custom query to fetch reclamations
        $conn = $entityManager->getConnection();
        $sql = 'SELECT * FROM reclamation';
        $stmt = $conn->prepare($sql);
        $resultSet = $stmt->executeQuery();
        $reclamations = $resultSet->fetchAllAssociative();

        return $this->render('reclamation/index.html.twig', [
            'reclamations' => $reclamations,
        ]);
    }

    #[Route('/new', name: 'app_reclamation_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    #[IsGranted('!ROLE_ADMIN', message: 'Admins cannot create reclamations')]
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
            ->add('description', TextType::class)
            ->add('statut', TextType::class)
            ->add('date', DateType::class, [
                'widget' => 'single_text',
                'data' => new \DateTime()
            ])
            ->add('Utilisateur_id', ChoiceType::class, [
                'choices' => $userChoices,
                'placeholder' => 'Choose a user',
                'required' => true
            ])
            ->getForm();

        $form->handleRequest($request);

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
    #[IsGranted('ROLE_USER')]
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
            ->add('description', null, ['data' => $reclamation['description']])
            ->add('statut', null, ['data' => $reclamation['statut']])
            ->add('date', null, ['data' => $reclamation['date']])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            // Update the reclamation using a direct SQL query
            $sql = 'UPDATE reclamation SET description = :description, statut = :statut, date = :date WHERE id = :id';
            $stmt = $conn->prepare($sql);
            $stmt->executeStatement([
                'id' => $id,
                'description' => $data['description'],
                'statut' => $data['statut'],
                'date' => $data['date'],
            ]);

            return $this->redirectToRoute('app_reclamation_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('reclamation/edit.html.twig', [
            'reclamation' => $reclamation,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_reclamation_delete', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function delete(Request $request, EntityManagerInterface $entityManager, int $id): Response
    {
        if ($this->isCsrfTokenValid('delete'.$id, $request->getPayload()->getString('_token'))) {
            // Delete the reclamation using a direct SQL query
            $conn = $entityManager->getConnection();
            $sql = 'DELETE FROM reclamation WHERE id = :id';
            $stmt = $conn->prepare($sql);
            $stmt->executeStatement(['id' => $id]);
        }

        return $this->redirectToRoute('app_reclamation_index', [], Response::HTTP_SEE_OTHER);
    }
}
