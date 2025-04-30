<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/front/reponse')]
final class FrontReponseController extends AbstractController {
    #[Route('/', name: 'app_front_reponse_index', methods: ['GET'])]
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

    #[Route('/{id}', name: 'app_front_reponse_show', methods: ['GET'])]
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

        return $this->render('front_reponse/show.html.twig', [
            'reponse' => $reponse,
        ]);
    }
}
