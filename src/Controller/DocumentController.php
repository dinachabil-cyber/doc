<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\Document;
use App\Form\DocumentType;
use App\Repository\DocumentRepository;
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
     * List all documents for a given client.
     */
    #[Route('/client/{id}', name: 'app_document_list', methods: ['GET'])]
    public function list(Client $client, DocumentRepository $documentRepository): Response
    {
        $documents = $documentRepository->findBy(
            ['client' => $client],
            ['createdAt' => 'DESC']
        );

        return $this->render('document/list.html.twig', [
            'client' => $client,
            'documents' => $documents,
        ]);
    }

    /**
     * Upload a new document for a client.
     */
    #[Route('/client/{id}/new', name: 'app_document_new', methods: ['GET', 'POST'])]
    public function new(Request $request, Client $client, EntityManagerInterface $em): Response
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

            $this->addFlash('success', 'Document uploaded successfully.');

            return $this->redirectToRoute('app_document_list', ['id' => $client->getId()]);
        }

        return $this->render('document/new.html.twig', [
            'form' => $form,
            'client' => $client,
        ]);
    }

    /**
     * Show document details.
     */
    #[Route('/{id}', name: 'app_document_show', methods: ['GET'])]
    public function show(Document $document): Response
    {
        return $this->render('document/show.html.twig', [
            'document' => $document,
        ]);
    }

    /**
     * Download a document file.
     */
    #[Route('/{id}/download', name: 'app_document_download', methods: ['GET'])]
    public function download(Document $document, DownloadHandler $downloadHandler): Response
    {
        return $downloadHandler->downloadObject(
            $document,
            'file',
            null,
            $document->getOriginalName() ?? $document->getFileName(),
            false
        );
    }

    /**
     * Delete a document.
     */
    #[Route('/{id}/delete', name: 'app_document_delete', methods: ['POST'])]
    public function delete(Request $request, Document $document, EntityManagerInterface $em): Response
    {
        $clientId = $document->getClient()?->getId();

        if ($this->isCsrfTokenValid('delete-document-' . $document->getId(), $request->request->get('_token'))) {
            $em->remove($document);
            $em->flush();

            $this->addFlash('success', 'Document deleted successfully.');
        }

        return $this->redirectToRoute('app_document_list', ['id' => $clientId]);
    }
}
