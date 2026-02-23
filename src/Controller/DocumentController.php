<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\Document;
use App\Form\DocumentType;
use App\Repository\ClientRepository;
use App\Repository\CategoryRepository;
use App\Repository\DocumentRepository;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Vich\UploaderBundle\Handler\DownloadHandler;

#[Route('/documents')]
#[IsGranted('ROLE_USER')]
final class DocumentController extends AbstractController
{
    /**
     * List all documents with search and filters
     */
    #[Route('', name: 'app_document_index', methods: ['GET'])]
    public function index(
        Request $request,
        DocumentRepository $documentRepository,
        ClientRepository $clientRepository,
        CategoryRepository $categoryRepository
    ): Response {
        $search = $request->query->get('search');
        $clientId = $request->query->get('client');
        $categoryId = $request->query->get('category');
        $fileType = $request->query->get('fileType');
        $dateFrom = $request->query->get('dateFrom');
        $dateTo = $request->query->get('dateTo');
        $page = (int) $request->query->get('page', 1);

        $client = $clientId ? $clientRepository->find($clientId) : null;
        $dateFromObj = $dateFrom ? new \DateTime($dateFrom) : null;
        $dateToObj = $dateTo ? new \DateTime($dateTo) : null;

        $result = $documentRepository->findWithFilters(
            search: $search,
            client: $client,
            categoryId: $categoryId,
            fileType: $fileType,
            dateFrom: $dateFromObj,
            dateTo: $dateToObj,
            page: $page,
            limit: 20
        );

        return $this->render('document/index.html.twig', [
            'documents' => $result['items'],
            'pagination' => [
                'page' => $result['page'],
                'totalPages' => $result['totalPages'],
                'total' => $result['total'],
            ],
            'filters' => [
                'search' => $search,
                'client' => $client,
                'categoryId' => $categoryId,
                'fileType' => $fileType,
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo,
            ],
            'clients' => $clientRepository->findAll(),
            'categories' => $categoryRepository->findAll(),
        ]);
    }

    /**
     * Trash - view deleted documents
     */
    #[Route('/trash', name: 'app_document_trash', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function trash(DocumentRepository $documentRepository): Response
    {
        $deletedDocuments = $documentRepository->findDeleted();

        return $this->render('document/trash.html.twig', [
            'documents' => $deletedDocuments,
        ]);
    }

    /**
     * Restore document from trash
     */
    #[Route('/{id}/restore', name: 'app_document_restore', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function restore(Request $request, Document $document, EntityManagerInterface $em, ActivityLogger $activityLogger): Response
    {
        if ($this->isCsrfTokenValid('restore-document-' . $document->getId(), $request->request->get('_token'))) {
            $document->restore();
            $em->flush();

            $activityLogger->logRestore($this->getUser(), $document);

            $this->addFlash('success', 'Document restored from trash.');
        }

        return $this->redirectToRoute('app_document_trash');
    }

    /**
     * Permanently delete document
     */
    #[Route('/{id}/permanent-delete', name: 'app_document_permanent_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function permanentDelete(Request $request, Document $document, EntityManagerInterface $em, ActivityLogger $activityLogger): Response
    {
        if ($this->isCsrfTokenValid('permanent-delete-document-' . $document->getId(), $request->request->get('_token'))) {
            // Get file path before removing
            $filePath = null;
            if ($document->getFileName()) {
                $uploadDir = $this->getParameter('vich_uploader.document_file');
                $filePath = $uploadDir . '/' . $document->getFileName();
            }

            $activityLogger->logPermanentDelete($this->getUser(), $document);

            // Remove from database
            $em->remove($document);
            $em->flush();

            // Optionally delete the physical file
            if ($filePath && file_exists($filePath)) {
                unlink($filePath);
            }

            $this->addFlash('success', 'Document permanently deleted.');
        }

        return $this->redirectToRoute('app_document_trash');
    }

    /**
     * List all documents for a given client (legacy route).
     */
    #[Route('/client/{id}', name: 'app_document_list', methods: ['GET'])]
    public function list(Client $client, DocumentRepository $documentRepository): Response
    {
        $documents = $documentRepository->findByClient($client);

        return $this->render('document/list.html.twig', [
            'client' => $client,
            'documents' => $documents,
        ]);
    }

    /**
     * Upload a new document for a client.
     */
    #[Route('/client/{id}/new', name: 'app_document_new', methods: ['GET', 'POST'])]
    public function new(Request $request, Client $client, EntityManagerInterface $em, ActivityLogger $activityLogger): Response
    {
        $document = new Document();
        $document->setClient($client);
        $document->setOwner($this->getUser());

        $form = $this->createForm(DocumentType::class, $document, ['is_new' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Capture original filename and metadata before Vich renames
            $uploadedFile = $form->get('file')->getData();
            if ($uploadedFile) {
                $document->setOriginalName($uploadedFile->getClientOriginalName());
                $document->setMimeType($uploadedFile->getMimeType());
                $document->setSize($uploadedFile->getSize());
            }

            $em->persist($document);
            $em->flush();

            $activityLogger->logUpload($this->getUser(), $document);

            $this->addFlash('success', 'Document uploaded successfully.');

            return $this->redirectToRoute('app_document_list', ['id' => $client->getId()]);
        }

        return $this->render('document/new.html.twig', [
            'form' => $form,
            'client' => $client,
        ]);
    }

    /**
     * Show document details with preview support.
     */
    #[Route('/{id}/show', name: 'app_document_show', methods: ['GET'])]
    public function show(Document $document): Response
    {
        return $this->render('document/show.html.twig', [
            'document' => $document,
        ]);
    }

    /**
     * Preview document inline (PDF or Image).
     */
    #[Route('/{id}/preview', name: 'app_document_preview', methods: ['GET'])]
    public function preview(Document $document, DownloadHandler $downloadHandler): Response
    {
        // Check if document can be previewed
        if (!$document->isPdf() && !$document->isImage()) {
            $this->addFlash('error', 'This file type cannot be previewed.');
            return $this->redirectToRoute('app_document_show', ['id' => $document->getId()]);
        }

        // For PDFs and images, return inline response using Vich's download handler
        return $downloadHandler->downloadObject(
            $document,
            'file',
            null,
            null,
            false // inline (not attachment)
        );
    }

    /**
     * Download a document file.
     */
    #[Route('/{id}/download', name: 'app_document_download', methods: ['GET'])]
    public function download(Document $document, DownloadHandler $downloadHandler, ActivityLogger $activityLogger): Response
    {
        $activityLogger->logDownload($this->getUser(), $document);

        return $downloadHandler->downloadObject(
            $document,
            'file',
            null,
            $document->getOriginalName() ?? $document->getFileName(),
            true // attachment (force download)
        );
    }

    /**
     * Soft delete a document (move to trash).
     */
    #[Route('/{id}/delete', name: 'app_document_delete', methods: ['POST'])]
    public function delete(Request $request, Document $document, EntityManagerInterface $em, ActivityLogger $activityLogger): Response
    {
        $clientId = $document->getClient()?->getId();

        if ($this->isCsrfTokenValid('delete-document-' . $document->getId(), $request->request->get('_token'))) {
            // Soft delete instead of hard delete
            $document->softDelete();
            $em->flush();

            $activityLogger->logDelete($this->getUser(), $document);

            $this->addFlash('success', 'Document moved to trash.');
        }

        if ($clientId) {
            return $this->redirectToRoute('app_document_list', ['id' => $clientId]);
        }

        return $this->redirectToRoute('app_document_index');
    }

    /**
     * Edit document metadata (not the file).
     */
    #[Route('/{id}/edit', name: 'app_document_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Document $document, EntityManagerInterface $em, ActivityLogger $activityLogger): Response
    {
        $form = $this->createForm(DocumentType::class, $document, ['is_new' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $activityLogger->logEdit($this->getUser(), $document);

            $this->addFlash('success', 'Document updated successfully.');

            return $this->redirectToRoute('app_document_show', ['id' => $document->getId()]);
        }

        return $this->render('document/edit.html.twig', [
            'form' => $form,
            'document' => $document,
        ]);
    }
}
