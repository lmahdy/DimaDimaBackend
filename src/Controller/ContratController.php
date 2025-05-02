<?php

namespace App\Controller;

use App\Entity\Contrat;
use App\Entity\Utilisateur;
use App\Entity\Projet;
use App\Entity\Equipe;
use App\Entity\Terrain;
use App\Form\ContratType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Form\editContartType;
use SebastianBergmann\Environment\Console;
use Jenssegers\ImageHash\ImageHash;
use Jenssegers\ImageHash\Implementations\DifferenceHash;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface; // Add this import

use App\Service\HtmlToPdfService;
use PhpParser\Node\Stmt\Else_;
use Proxies\__CG__\App\Entity\Utilisateur as EntityUtilisateur;

#[Route('/contrat')]
final class ContratController extends AbstractController
{
    
    
    private HtmlToPdfService $htmlToPdfService;

    public function __construct(HtmlToPdfService $htmlToPdfService)
    {
        $this->htmlToPdfService = $htmlToPdfService;
    }
    #[Route(name: 'app_contrat_index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        
        $contrats = $entityManager
            ->getRepository(Contrat::class)
            ->findAll();

        return $this->render('contrat/index.html.twig', [
            'contrats' => $contrats,
        ]);
    }

    #[Route('/new', name: 'app_contrat_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, ParameterBagInterface $params): Response
    {
        $contrat = new Contrat();
        $form = $this->createForm(ContratType::class, $contrat);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $signatureData = $request->request->get('signatureData');
            
            if ($signatureData) {
                // Process new signature
                $data = explode(',', $signatureData)[1];
                $decodedData = base64_decode($data);
                $filename = uniqid() . '.png';
                $path = $this->getParameter('kernel.project_dir') . '/public/signatures/' . $filename;
                file_put_contents($path, $decodedData);
                $contrat->setSignatureElectronique('/signatures/' . $filename);

                /** @var Utilisateur $user */
                $user = $this->getUser();
                
                if ($user->getSignature()) {
                    // Compare signatures
                    $projectDir = $params->get('kernel.project_dir');
                    $file1 = $projectDir.'/public/signatures/'.$filename;
                    $file2 = $projectDir.'/public'.$user->getSignature();
                    
                    if (!file_exists($file1)) {
                        $this->addFlash('error', "Signature file not found: $file1");
                    }
                    
                    if (!file_exists($file2)) {
                        $this->addFlash('error', "User signature file not found: $file2");
                    }
                    
                    $hasher = new ImageHash;
                    $hash1 = $hasher->hash($file1);
                    $hash2 = $hasher->hash($file2);
                    $distance = $hasher->distance($hash1, $hash2);
                    
                    if ($distance <= 7) {
                        $entityManager->persist($contrat);
                        $entityManager->flush();
                        $this->addFlash('success', 'Contract created successfully!');
                        return $this->redirectToRoute('app_contrat_index');
                    } else {
                        $this->addFlash('error', 'Signatures do not match (Difference: '.$distance.')');
                        return $this->render('contrat/new.html.twig', [
                            'contrat' => $contrat,
                            'form' => $form,
                        ]);
                    }
                } else {
                    // First-time signature setup
                    $user->setSignature('/signatures/' . $filename);
                    $entityManager->persist($user);
                    $entityManager->persist($contrat);


                    $entityManager->flush();
                    $this->addFlash('success', 'Signature saved successfully!');
                    return $this->redirectToRoute('app_contrat_index');
                }
            } else {
                $this->addFlash('error', 'No signature data provided');
            }
        }

        return $this->render('contrat/new.html.twig', [
            'contrat' => $contrat,
            'form' => $form,
        ]);
    }

    #[Route('/{idContrat}', name: 'app_contrat_show', methods: ['GET'])]
    public function show(Contrat $contrat): Response
    {
        return $this->render('contrat/show.html.twig', [
            'contrat' => $contrat,
        ]);
    }

    #[Route('/{idContrat}/edit', name: 'app_contrat_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Contrat $contrat, EntityManagerInterface $entityManager,
        ParameterBagInterface $params
    ): Response {
        // Store old signature path in case we need to revert
        $oldSignature = $contrat->getSignatureElectronique();

        $form = $this->createForm(ContratType::class, $contrat);

        $form->handleRequest($request);
    
        if ($form->isSubmitted()) {
            $signatureData = $request->request->get('signatureData');
            
            if ($signatureData) {
                // Process signature exactly like in new action
                $data = explode(',', $signatureData)[1];
                $decodedData = base64_decode($data);
                $filename = uniqid().'.png';
                $path = $this->getParameter('kernel.project_dir').'/public/signatures/'.$filename;
                file_put_contents($path, $decodedData);
                $newSignaturePath = '/signatures/'.$filename;
    
                /** @var Utilisateur $user */
                $user = $this->getUser();
                
                if ($user->getSignature()) {
                    // Compare signatures
                    $projectDir = $params->get('kernel.project_dir');
                    $file1 = $projectDir.'/public'.$newSignaturePath;
                    $file2 = $projectDir.'/public'.$user->getSignature();
                    
                    $hasher = new ImageHash;
                    $hash1 = $hasher->hash($file1);
                    $hash2 = $hasher->hash($file2);
                    $distance = $hasher->distance($hash1, $hash2);
                    
                    if ($distance <= 5) {
                        // Delete old signature file if exists
                        if ($oldSignature && file_exists($projectDir.'/public'.$oldSignature)) {
                            unlink($projectDir.'/public'.$oldSignature);
                        }
                        $contrat->setSignatureElectronique($newSignaturePath);
                    } else {
                        unlink($file1); // Delete temp file
                        $this->addFlash('error', 'Signatures do not match (Difference: '.$distance.')');
                        return $this->render('contrat/edit.html.twig', [
                            'contrat' => $contrat,
                            'form' => $form->createView(),
                        ]);
                    }
                } else {
                    // First-time signature setup
                    $user->setSignature($newSignaturePath);
                    $contrat->setSignatureElectronique($newSignaturePath);
                }
            } else {
                // Keep old signature if no new one provided
                $contrat->setSignatureElectronique(null);
            }
    
            $entityManager->flush();
            $this->addFlash('success', 'Contract updated successfully!');
            return $this->redirectToRoute('app_contrat_index');
        }
    
        return $this->render('contrat/edit.html.twig', [
            'contrat' => $contrat,
            'form' => $form->createView(),
        ]);
    }
    
    #[Route('/{idContrat}', name: 'app_contrat_delete', methods: ['POST'])]
    public function delete(Request $request, Contrat $contrat, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$contrat->getIdContrat(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($contrat);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_contrat_index', [], Response::HTTP_SEE_OTHER);
    }


    #[Route('/contrat/{idContrat}/form', name: 'app_contrat_showForm', methods: ['GET'])]
    public function showontrat(Contrat $contrat, EntityManagerInterface $entityManager): Response
    {
        $projet = $entityManager->getRepository(Projet::class)->find($contrat->getIdProjet());
        $terrain = $entityManager->getRepository(Terrain::class)->find($projet->getIdTerrain());
        
        $client = null;
        $constructeur = null;
    
        if ($projet) {
            if ($contrat->getTypeContrat() == "client") {
                $client = $entityManager->getRepository(Utilisateur::class)->find($projet->getIdClient());
            } elseif ($contrat->getTypeContrat() == "constructeur") {
                $equipe = $entityManager->getRepository(Equipe::class)->find($projet->getIdEquipe());
                
                if ($equipe) {
                    $constructeur = $entityManager->getRepository(Utilisateur::class)
                        ->find($equipe->getIdConstructeur());
                }
            }
        }
    
        return $this->render('contrat/showContrat.html.twig', [
            'contrat' => $contrat,
            'projet' => $projet, 
            'client' => $client,
            'constructeur' => $constructeur,
            'terrain'=>$terrain,
        ]);
    }

    #[Route('/generate-pdf/{idContrat}', name: 'generate_pdf', methods: ['GET'])]
    public function generatePDF(
        EntityManagerInterface $em,
        int $idContrat
    ): Response {
            $contrat = $em->getRepository(Contrat::class)->find($idContrat);
            if (!$contrat) {
                throw $this->createNotFoundException('Contrat non trouvé');
            }
         
            

            $projet = $em->getRepository(Projet::class)->find($contrat->getIdProjet());
            $terrain = $projet ? $em->getRepository(Terrain::class)->find($projet->getIdTerrain()) : null;

            $client = null;
            $constructeur = null;

            if ($projet) {
                if ($contrat->getTypeContrat() == "client") {
                    $client = $em->getRepository(Utilisateur::class)->find($projet->getIdClient());
                } elseif ($contrat->getTypeContrat() == "constructeur") {
                    $equipe = $em->getRepository(Equipe::class)->find($projet->getIdEquipe());
                    if ($equipe) {
                        $constructeur = $em->getRepository(Utilisateur::class)
                            ->find($equipe->getIdConstructeur());
                    }
                }
            }

            $htmlContent = $this->renderView('contrat/pdf_template.html.twig', [
                'contrat' => $contrat,
                'projet' => $projet,
                'client' => $client,
                'constructeur' => $constructeur,
                'terrain' => $terrain,
                'date_generation' => new \DateTime()
            ]);
            $pdfContent = $this->htmlToPdfService->generatePdf($htmlContent);


            return new Response(
                $pdfContent,
                200,
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => sprintf('attachment; filename="contrat_%d.pdf"', $contrat->getIdContrat())
                ]
            );

     
    }

    #[Route('/client/contrats', name: 'contrat_client')]
    public function getCONTRATCLIENT (EntityManagerInterface $entityManager) : Response
    {
        $user =new Utilisateur();

         $user = $this->getUser();
         $clientId = $user->getId();
         dump(  $clientId);
         $contrats = $entityManager->getRepository(Contrat::class)
        ->createQueryBuilder('c')
        
        ->join('c.idProjet', 'p')
        ->where('p.idClient = :clientId')
        ->setParameter('clientId', $clientId)
        ->getQuery()
        ->getResult();
    return $this->render('clientfrontoffice/contrats.html.twig', [
        'contrats' => $contrats,
    ]);





    }




    #[Route('/contrat/{idContrat}/Client', name: 'app_contrat_Client', methods: ['GET'])]
    public function showontratForclient(Contrat $contrat, EntityManagerInterface $entityManager): Response
    {
        $projet = $entityManager->getRepository(Projet::class)->find($contrat->getIdProjet());
        $terrain = $entityManager->getRepository(Terrain::class)->find($projet->getIdTerrain());
        
        $client = null;
        $constructeur = null;
    
        if ($projet) {
            if ($contrat->getTypeContrat() == "client") {
                $client = $entityManager->getRepository(Utilisateur::class)->find($projet->getIdClient());
            } elseif ($contrat->getTypeContrat() == "constructeur") {
                $equipe = $entityManager->getRepository(Equipe::class)->find($projet->getIdEquipe());
                
                if ($equipe) {
                    $constructeur = $entityManager->getRepository(Utilisateur::class)
                        ->find($equipe->getIdConstructeur());
                }
            }
        }
    
        return $this->render('contrat/showContratClient.html.twig', [
            'contrat' => $contrat,
            'projet' => $projet, 
            'client' => $client,
            'constructeur' => $constructeur,
            'terrain'=>$terrain,
        ]);
    }


    #[Route('/contrat/{idContrat}/addsignature', name: 'addsignature', methods: ['POST','GET'])]
    public function addSignatureClient(
        Request $request,
        EntityManagerInterface $entityManager,
        Contrat $contrat,
        ParameterBagInterface $params
    ): Response {
        $projet = $entityManager->getRepository(Projet::class)->find($contrat->getIdProjet());
        $terrain = $entityManager->getRepository(Terrain::class)->find($projet->getIdTerrain());
        
        $client = null;
        $constructeur = null;

        if ($projet) {
            if ($contrat->getTypeContrat() == "client") {
                $client = $entityManager->getRepository(Utilisateur::class)->find($projet->getIdClient());
            } elseif ($contrat->getTypeContrat() == "constructeur") {
                $equipe = $entityManager->getRepository(Equipe::class)->find($projet->getIdEquipe());
                if ($equipe) {
                    $constructeur = $entityManager->getRepository(Utilisateur::class)
                        ->find($equipe->getIdConstructeur());
                }
            }
        }

        $signatureData = $request->request->get('signatureData');

        if (!$signatureData) {
            $this->addFlash('error', 'Aucune signature fournie');
            return $this->render('contrat/showContratClient.html.twig', [
                'contrat' => $contrat,
                'projet' => $projet,
                'client' => $client,
                'constructeur' => $constructeur,
                'terrain' => $terrain,
            ]);
        }

        try {
            // Process new signature
            $data = explode(',', $signatureData)[1];
            $decodedData = base64_decode($data);
            $filename = uniqid() . '.png';
            $signatureDir = $this->getParameter('kernel.project_dir') . '/public/signatures/';
            
            // Create signatures directory if it doesn't exist
            if (!file_exists($signatureDir)) {
                mkdir($signatureDir, 0777, true);
            }
            
            $path = $signatureDir . $filename;
            file_put_contents($path, $decodedData);
            
            $user = $this->getUser();
            
            if ($user->getSignature()) {
                // Compare signatures
                $projectDir = $params->get('kernel.project_dir');
                $file1 = $projectDir.'/public/signatures/'.$filename;
                $file2 = $projectDir.'/public'.$user->getSignature();
                
                if (!file_exists($file1) || !file_exists($file2)) {
                    throw new \Exception('Signature files not found');
                }
                
                $hasher = new ImageHash();
                $hash1 = $hasher->hash($file1);
                $hash2 = $hasher->hash($file2);
                $distance = $hasher->distance($hash1, $hash2);
                
                if ($distance <= 5) {
                    $contrat->setSignatureClient('/signatures/' . $filename);
                    $contrat->setDateSignatureClient(new \DateTime());
                    $entityManager->persist($contrat);
                    $entityManager->flush();
                    $this->addFlash('success', 'Contrat signé avec succès!');
                    return $this->redirectToRoute('contrat_client');
                } else {
                    unlink($file1); // Clean up the temporary file
                    $this->addFlash('error', 'Les signatures ne correspondent pas (Différence: '.$distance.')');
                }
            } else {
                // First-time signature setup
                $user->setSignature('/signatures/' . $filename);
                $contrat->setSignatureClient('/signatures/' . $filename);
                $contrat->setDateSignatureClient(new \DateTime());
                $entityManager->persist($user);
                $entityManager->persist($contrat);
                $entityManager->flush();
                $this->addFlash('success', 'Signature enregistrée avec succès!');
                return $this->redirectToRoute('contrat_client');
            }
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors du traitement de la signature: '.$e->getMessage());
        }

        return $this->render('contrat/showContratClient.html.twig', [
            'contrat' => $contrat,
            'projet' => $projet,
            'client' => $client,
            'constructeur' => $constructeur,
            'terrain' => $terrain,
        ]);
    }

}