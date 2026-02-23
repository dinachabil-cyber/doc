<?php

namespace App\Service;

use App\Entity\ActivityLog;
use App\Entity\Client;
use App\Entity\Document;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Service to log user activities (audit log)
 */
class ActivityLogger
{
    public function __construct(
        private readonly EntityManagerInterface $em
    ) {}

    /**
     * Log an activity
     */
    public function log(
        UserInterface $user,
        string $action,
        ?Document $document = null,
        ?Client $client = null,
        ?string $details = null
    ): ActivityLog {
        $activityLog = new ActivityLog();
        $activityLog->setUser($user instanceof User ? $user : null);
        $activityLog->setAction($action);
        $activityLog->setDocument($document);
        $activityLog->setClient($client);
        $activityLog->setDetails($details);

        $this->em->persist($activityLog);
        $this->em->flush();

        return $activityLog;
    }

    /**
     * Log document upload
     */
    public function logUpload(UserInterface $user, Document $document): ActivityLog
    {
        return $this->log(
            $user,
            ActivityLog::ACTION_UPLOAD,
            $document,
            $document->getClient(),
            sprintf('Uploaded: %s', $document->getTitle())
        );
    }

    /**
     * Log document soft delete (move to trash)
     */
    public function logDelete(UserInterface $user, Document $document): ActivityLog
    {
        return $this->log(
            $user,
            ActivityLog::ACTION_DELETE,
            $document,
            $document->getClient(),
            sprintf('Moved to trash: %s', $document->getTitle())
        );
    }

    /**
     * Log document permanent delete
     */
    public function logPermanentDelete(UserInterface $user, Document $document): ActivityLog
    {
        return $this->log(
            $user,
            ActivityLog::ACTION_PERMANENT_DELETE,
            $document,
            $document->getClient(),
            sprintf('Permanently deleted: %s', $document->getTitle())
        );
    }

    /**
     * Log document download
     */
    public function logDownload(UserInterface $user, Document $document): ActivityLog
    {
        return $this->log(
            $user,
            ActivityLog::ACTION_DOWNLOAD,
            $document,
            $document->getClient(),
            sprintf('Downloaded: %s', $document->getTitle())
        );
    }

    /**
     * Log document restore from trash
     */
    public function logRestore(UserInterface $user, Document $document): ActivityLog
    {
        return $this->log(
            $user,
            ActivityLog::ACTION_RESTORE,
            $document,
            $document->getClient(),
            sprintf('Restored from trash: %s', $document->getTitle())
        );
    }

    /**
     * Log document edit
     */
    public function logEdit(UserInterface $user, Document $document): ActivityLog
    {
        return $this->log(
            $user,
            ActivityLog::ACTION_EDIT,
            $document,
            $document->getClient(),
            sprintf('Edited: %s', $document->getTitle())
        );
    }

    /**
     * Log client create
     */
    public function logClientCreate(UserInterface $user, Client $client): ActivityLog
    {
        return $this->log(
            $user,
            ActivityLog::ACTION_CLIENT_CREATE,
            null,
            $client,
            sprintf('Created client: %s', $client->__toString())
        );
    }

    /**
     * Log client edit
     */
    public function logClientEdit(UserInterface $user, Client $client): ActivityLog
    {
        return $this->log(
            $user,
            ActivityLog::ACTION_CLIENT_EDIT,
            null,
            $client,
            sprintf('Edited client: %s', $client->__toString())
        );
    }

    /**
     * Log client delete
     */
    public function logClientDelete(UserInterface $user, Client $client): ActivityLog
    {
        return $this->log(
            $user,
            ActivityLog::ACTION_CLIENT_DELETE,
            null,
            $client,
            sprintf('Deleted client: %s', $client->__toString())
        );
    }
}
