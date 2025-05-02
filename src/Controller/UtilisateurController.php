<?php

namespace App\Controller;

use App\Entity\GestionnaireStock;
use App\Entity\Utilisateur;
use App\Entity\Artisan;
use App\Entity\Client;


use App\Entity\Constructeur;
use App\Form\UtilisateurCreateType;
use App\Form\UtilisateurType;

use App\Form\userType;

use App\Repository\UtilisateurRepository;
use App\Repository\ArtisanRepository;
use App\Repository\ClientRepository;
use App\Repository\GestionnairestockRepository;


use App\Repository\ConstructeurRepository;
use App\Service\RecommendationService;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use Doctrine\ORM\EntityManagerInterface;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class UtilisateurController extends AbstractController
{
    #[Route('/tables', name: 'app_tables')]
    public function tables(UtilisateurRepository $userRepo): Response
    {
        $users = $userRepo->findBy([
            'role' => 'Client',
            
        ], ['id' => 'DESC'], 30);
    
        return $this->render('tables/index.html.twig', [
            'users' => $users,
            'role' => 'Client'
        ]);
    }
    #[Route('/tables/role/{role}', name: 'app_users_by_role', methods: ['GET'])]
public function getUsersByRole(
    string $role,
    Request $request,
    UtilisateurRepository $userRepo,
    ArtisanRepository $artisanRepo,
    ConstructeurRepository $constructeurRepo
): Response {
    // Paramètres de pagination fixés à 4 éléments par page
    $page = $request->query->getInt('page', 1);
    $limit = 4; // Fixé à 4 éléments par page
    $page = max(1, $page);
    $offset = ($page - 1) * $limit;

    $users = $userRepo->findBy(
        ['role' => $role],
        ['id' => 'DESC'],
        $limit,
        $offset
    );

    $totalUsers = $userRepo->count(['role' => $role]);
    $totalPages = ceil($totalUsers / $limit);

    return $this->render('tables/_user_table.html.twig', [
        'users' => $users,
        'role' => $role,
        'artisanInfos' => $role === 'Artisan' ? $artisanRepo->findAll() : [],
        'constructeurInfos' => $role === 'Constructeur' ? $constructeurRepo->findAll() : [],
        'currentPage' => $page,
        'totalPages' => $totalPages,
        'limit' => $limit,
    ]);
}
    #[Route('/users', name: 'app_users', methods: ['GET'])]
    public function index(UtilisateurRepository $userRepository): JsonResponse
    {
        $users = $userRepository->findAll();
        return $this->json($users, 200, [], ['groups' => ['user:list']]);
    }

    #[Route('/utilisateur/{id}/show', name: 'utilisateur_show', methods: ['GET'])]
public function show(Utilisateur $utilisateur): Response
{
    return $this->render('user/_show_modal.html.twig', [
        'user' => $utilisateur,
        'now' => new \DateTime()

    ]);
}
#[Route('/utilisateur/{id}/edit', name: 'app_user_edit', methods: ['GET', 'POST'])]
public function edit(
    Request $request,
    Utilisateur $user,
    EntityManagerInterface $entityManager,
    ArtisanRepository $artisanRepo,
    ConstructeurRepository $constructeurRepo,
    ClientRepository $clientRepo,
    GestionnairestockRepository $gestionnaireStockRepo
): Response {
    // Récupérer les entités liées actuelles
    $artisanInfo = $user->getArtisan();
    $constructeurInfo = $user->getConstructeur();
    $clientInfo = $user->getClient();
    $gestionnaireStockInfo = $user->getGestionnaireStock();
    $originalRole = $user->getRole();

    $form = $this->createForm(UtilisateurType::class, $user, [
        'artisan_info' => $artisanInfo,
        'constructeur_info' => $constructeurInfo,
    ]);

    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $newRole = $user->getRole();

        // Sauvegarder IMMÉDIATEMENT le nouveau rôle
        $entityManager->persist($user);
        $entityManager->flush(); // ⚠️ Force la sauvegarde du rôle ici

        // Si le rôle a changé, supprimer l'ancienne entité
        if ($originalRole !== $newRole) {
            switch ($originalRole) {
                case 'Client':
                    if ($clientInfo) {
                        $entityManager->remove($clientInfo);
                    }
                    break;
                case 'Artisan':
                    if ($artisanInfo) {
                        $entityManager->remove($artisanInfo);
                    }
                    break;
                case 'Constructeur':
                    if ($constructeurInfo) {
                        $entityManager->remove($constructeurInfo);
                    }
                    break;
                case 'GestionnaireStock':
                    if ($gestionnaireStockInfo) {
                        $entityManager->remove($gestionnaireStockInfo);
                    }
                    break;
            }

            // Créer la nouvelle entité selon le nouveau rôle
            switch ($newRole) {
                case 'Client':
                    $client = new Client();
                    $client->setClient($user);
                    $entityManager->persist($client);
                    break;
                case 'Artisan':
                    $artisan = new Artisan();
                    $artisan->setArtisan($user);
                    $artisan->setSpecialite($form->get('specialiteArtisan')->getData());
                    $artisan->setSalaireHeure($form->get('salaireHeureArtisan')->getData());
                    $entityManager->persist($artisan);
                    break;
                case 'Constructeur':
                    $constructeur = new Constructeur();
                    $constructeur->setConstructeur($user);
                    $constructeur->setSpecialite($form->get('specialiteConstructeur')->getData());
                    $constructeur->setSalaireHeure($form->get('salaireHeureConstructeur')->getData());
                    $entityManager->persist($constructeur);
                    break;
                case 'GestionnaireStock':
                    $gestionnaire = new GestionnaireStock();
                    $gestionnaire->setGestionnairestock($user);
                    $entityManager->persist($gestionnaire);
                    break;
            }
        } else {
            // Mettre à jour les données spécifiques si le rôle est inchangé
            if ($newRole === 'Artisan' && $artisanInfo) {
                $artisanInfo->setSpecialite($form->get('specialiteArtisan')->getData());
                $artisanInfo->setSalaireHeure($form->get('salaireHeureArtisan')->getData());
                $entityManager->persist($artisanInfo);
            }
            if ($newRole === 'Constructeur' && $constructeurInfo) {
                $constructeurInfo->setSpecialite($form->get('specialiteConstructeur')->getData());
                $constructeurInfo->setSalaireHeure($form->get('salaireHeureConstructeur')->getData());
                $entityManager->persist($constructeurInfo);
            }
        }

        $entityManager->flush();
        $this->addFlash('success', 'Utilisateur mis à jour avec succès.');
        return $this->redirectToRoute('app_tables');
    }

    return $this->render('user/edit.html.twig', [
        'form' => $form->createView(),
        'user' => $user,
    ]);
}
#[Route('/utilisateur/{id}/delete', name: 'app_utilisateur_delete', methods: ['POST'])]
public function delete(Request $request, Utilisateur $utilisateur , EntityManagerInterface $entityManager): Response
{
    if ($this->isCsrfTokenValid('delete'.$utilisateur->getId(), $request->getPayload()->getString('_token'))) {
        $entityManager->remove($utilisateur);
        $entityManager->flush();
    }

    return $this->redirectToRoute('app_tables', [], Response::HTTP_SEE_OTHER);
}


    #[Route('/utilisateur/nouveau', name: 'app_user_add', methods: ['GET', 'POST'])]
    public function add(
        Request $request, 
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher 

    ): Response {
        $user = new Utilisateur();
        $form = $this->createForm(UtilisateurCreateType::class, $user);
        $form->handleRequest($request);
    
        if ($form->isSubmitted() && $form->isValid()) {
            $role = $user->getRole();
            $plainPassword = $form->get('password')->getData();
            $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
            $user->setPassword($hashedPassword);
    
            try {
                // Création des entités selon le rôle
                switch ($role) {
                    case 'Artisan':
                        $specialite = $form->get('specialiteArtisan')->getData();
                        $salaire = $form->get('salaireHeureArtisan')->getData();
    
                        if (empty($specialite) || empty($salaire)) {
                            $this->addFlash('error', 'Les champs spécialité et salaire sont obligatoires pour les artisans');
                            return $this->redirectToRoute('app_user_add');
                        }
    
                        $artisan = new Artisan();
                        $artisan->setArtisan($user)
                                ->setSpecialite($specialite)
                                ->setSalaireHeure($salaire);
                        $entityManager->persist($artisan);
                        break;
    
                    case 'Constructeur':
                        $specialite = $form->get('specialiteConstructeur')->getData();
                        $salaire = $form->get('salaireHeureConstructeur')->getData();
    
                        if (empty($specialite) || empty($salaire)) {
                            $this->addFlash('error', 'Les champs spécialité et salaire sont obligatoires pour les constructeurs');
                            return $this->redirectToRoute('app_user_add');
                        }
    
                        $constructeur = new Constructeur();
                        $constructeur->setConstructeur($user)
                                    ->setSpecialite($specialite)
                                    ->setSalaireHeure($salaire);
                        $entityManager->persist($constructeur);
                        break;
    
                    case 'Client':
                        $client = new Client();
                        $client->setClient($user);
                        $entityManager->persist($client);
                        break;
    
                    case 'GestionnaireStock':
                        $gestionnaire = new GestionnaireStock();
                        $gestionnaire->setGestionnairestock($user);
                        $entityManager->persist($gestionnaire);
                        break;
    
                    case 'Admin':
                        // Aucune entité supplémentaire nécessaire
                        break;
    
                    default:
                        $this->addFlash('error', 'Rôle non reconnu');
                        return $this->redirectToRoute('app_user_add');
                }
    
                // Persist et flush de l'utilisateur
                $entityManager->persist($user);
                $entityManager->flush();
    
                $this->addFlash('success', 'Utilisateur créé avec succès');
                return $this->redirectToRoute('app_tables');
    
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la création : ' . $e->getMessage());
                return $this->redirectToRoute('app_user_add');
            }
        }
    
        return $this->render('user/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/utilisateur/profile', name: 'app_user_edit', methods: ['GET', 'POST'])]
    public function prifile( Request $request, EntityManagerInterface $entityManager,){


        $user = $this->getUser();
        $form = $this->createForm(userType::class, $user);
        $form->handleRequest($request);


        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

             return $this->render('dash/dashboard.html.twig', [
                'user' => $this->getUser()
            ]);
        }

        return $this->render('user/profile.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);


    }




}