<?php

namespace App\Controller;

use App\Entity\Projet;
use App\Form\ProjetType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/projet')]
final class ProjetController extends AbstractController
{
    #[Route(name: 'app_projet_index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $projets = $entityManager
            ->getRepository(Projet::class)
            ->findAll();

        return $this->render('projet/index.html.twig', [
            'projets' => $projets,
        ]);
    }

    #[Route('/new', name: 'app_projet_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $projet = new Projet();
        $form = $this->createForm(ProjetType::class, $projet);
        $form->handleRequest($request);
    
        if ($form->isSubmitted() && $form->isValid()) {
            // Manually resolve emplacement to a Terrain
            $emplacement = $form->get('emplacement')->getData();
            $terrain = $entityManager->getRepository(\App\Entity\Terrain::class)
                ->findOneBy(['emplacement' => $emplacement]);
    
            if (!$terrain) {
                $this->addFlash('error', 'No terrain found with the given emplacement.');
                return $this->redirectToRoute('app_projet_new');
            }
    
            $projet->setIdTerrain($terrain); // Set manually
    
            $entityManager->persist($projet);
            $entityManager->flush();
    
            return $this->redirectToRoute('app_projet_index', [], Response::HTTP_SEE_OTHER);
        }
    
        return $this->render('projet/new.html.twig', [
            'projet' => $projet,
            'form' => $form,
        ]);
    }

    #[Route('/{idProjet}', name: 'app_projet_show', methods: ['GET'])]
    public function show(Projet $projet): Response
    {
        return $this->render('projet/show.html.twig', [
            'projet' => $projet,
        ]);
    }

    #[Route('/{idProjet}/edit', name: 'app_projet_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Projet $projet, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ProjetType::class, $projet);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_projet_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('projet/edit.html.twig', [
            'projet' => $projet,
            'form' => $form,
        ]);
    }

    #[Route('/{idProjet}', name: 'app_projet_delete', methods: ['POST'])]
    public function delete(Request $request, Projet $projet, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$projet->getIdProjet(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($projet);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_projet_index', [], Response::HTTP_SEE_OTHER);
    }
}
