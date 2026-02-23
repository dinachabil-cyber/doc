<?php

namespace App\Controller;

use App\Repository\ClientRepository;
use App\Repository\DocumentRepository;
use App\Repository\ActivityLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard')]
#[IsGranted('ROLE_USER')]
final class DashboardController extends AbstractController
{
    /**
     * Main dashboard with statistics
     */
    #[Route('', name: 'app_dashboard', methods: ['GET'])]
    public function index(
        ClientRepository $clientRepository,
        DocumentRepository $documentRepository,
        ActivityLogRepository $activityLogRepository
    ): Response {
        $totalClients = count($clientRepository->findAll());
        $totalDocuments = $documentRepository->countAll();
        $recentUploads = $documentRepository->findRecent(5);
        $recentlyDeleted = $documentRepository->findRecentlyDeleted(5);
        $recentActivity = $activityLogRepository->findRecent(10);

        return $this->render('dashboard/index.html.twig', [
            'totalClients' => $totalClients,
            'totalDocuments' => $totalDocuments,
            'recentUploads' => $recentUploads,
            'recentlyDeleted' => $recentlyDeleted,
            'recentActivity' => $recentActivity,
        ]);
    }
}
