<?php

namespace App\Controller;

use App\Entity\Abonnement;
use App\Entity\UserAbonnement;


use App\Form\AbonnementType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/abonnement')]
final class AbonnementController extends AbstractController
{
    #[Route(name: 'app_abonnement_index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $abonnements = $entityManager
            ->getRepository(Abonnement::class)
            ->findAll();

        return $this->render('abonnement/index.html.twig', [
            'abonnements' => $abonnements,
        ]);
    }
    #[Route('/clientside',name: 'app_abonnement', methods: ['GET'])]
    public function call(EntityManagerInterface $entityManager): Response
    {
        $abonnements = $entityManager
            ->getRepository(Abonnement::class)
            ->findAll();

        return $this->render('abonnementFrontOffice/showabonnement.html.twig', [
            'abonnements' => $abonnements,
        ]);
    }

    #[Route('/new', name: 'app_abonnement_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $abonnement = new Abonnement();
        $form = $this->createForm(AbonnementType::class, $abonnement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($abonnement);
            $entityManager->flush();

            return $this->redirectToRoute('app_abonnement_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('abonnement/new.html.twig', [
            'abonnement' => $abonnement,
            'form' => $form,
        ]);
    }

    #[Route('/{idAbonnement}', name: 'app_abonnement_show', methods: ['GET'])]
    public function show(Abonnement $abonnement): Response
    {
        return $this->render('abonnement/show.html.twig', [
            'abonnement' => $abonnement,
        ]);
    }

    #[Route('/{idAbonnement}/edit', name: 'app_abonnement_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Abonnement $abonnement, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(AbonnementType::class, $abonnement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_abonnement_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('abonnement/edit.html.twig', [
            'abonnement' => $abonnement,
            'form' => $form,
        ]);
    }

    #[Route('/delete/{idAbonnement}', name: 'app_abonnement_delete', methods: ['POST'])]
    public function delete(Request $request, Abonnement $abonnement, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$abonnement->getIdAbonnement(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($abonnement);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_abonnement_index', [], Response::HTTP_SEE_OTHER);
    }
    #[Route('/create-user-abonnement', name: 'create_user_abonnement', methods: ['POST'])]
    public function createUserAbonnement(Request $request, EntityManagerInterface $em)
    {
        $abonnementId = $request->request->get('abonnement_id');
        dump($abonnementId); // Check if this matches what you expect
        $abonnement = $em->getRepository(Abonnement::class)->find($abonnementId);
        $utilisateur = $this->getUser();
    
        if (!$abonnement || !$utilisateur) {
            $this->addFlash('error', 'Abonnement ou utilisateur introuvable.');
            return$this->render('welcomeFront/index.html.twig', [
                'user' => $this->getUser()
            ]);
        }
        $existingAbonnement = $em->getRepository(UserAbonnement::class)->findOneBy([
            'utilisateur' => $utilisateur,
            'statut' => 'actif'
        ]);
        if ($existingAbonnement) {
            $this->addFlash('success', 'Vous avez déjà un abonnement actif.');
            return $this->render('welcomeFront/index.html.twig', [
                'user' => $this->getUser()
            ]);
        }
    
        $userAbonnement = new UserAbonnement();
        $userAbonnement->setAbonnement($abonnement);
        $userAbonnement->setUtilisateur($utilisateur);
        
        $dateDebut = new \DateTime();
        $userAbonnement->setDateDebut($dateDebut);
        $duree = $abonnement->getDuree(); // e.g. "3mois"

        $duree = str_replace(
            ['mois', 'jours', 'ans'], 
            ['months', 'days', 'years'], 
            strtolower($duree)
        );
        
        // Calculate end date (single calculation)
        $dateFin = (clone $dateDebut)->modify('+' . $duree);
        $userAbonnement->setDateFin($dateFin);
    
        $userAbonnement->setStatut('actif');
    
        $em->persist($userAbonnement);
        $em->flush();
    
        $this->addFlash('success', 'Abonnement activé avec succès !');
        return $this->render('welcomeFront/index.html.twig', [
            'user' => $this->getUser()
        ]);
    }







}    















