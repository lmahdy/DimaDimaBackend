<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;

class DefaultController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        // Get recent reclamations
        $conn = $entityManager->getConnection();
        $sql = 'SELECT * FROM reclamation ORDER BY date DESC LIMIT 5';
        $stmt = $conn->prepare($sql);
        $resultSet = $stmt->executeQuery();
        $reclamations = $resultSet->fetchAllAssociative();

        // Get recent responses
        $sql = 'SELECT r.*, rec.description as reclamation_description, rec.id as reclamation_id
                FROM reponse r
                LEFT JOIN reclamation rec ON r.id_Reclamation = rec.id
                ORDER BY r.date DESC LIMIT 5';
        $stmt = $conn->prepare($sql);
        $resultSet = $stmt->executeQuery();
        $reponses = $resultSet->fetchAllAssociative();

        return $this->render('home/index.html.twig', [
            'reclamations' => $reclamations,
            'reponses' => $reponses,
            'front_reclamation_path' => $this->generateUrl('front_reclamation_index'),
            'front_reponse_path' => $this->generateUrl('front_reponse_index'),
        ]);
    }
}
