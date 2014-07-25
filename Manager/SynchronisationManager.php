<?php

/*
 * This file is part of the Claroline Connect package.
 *
 * (c) Claroline Consortium <consortium@claroline.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Claroline\OfflineBundle\Manager;

use Claroline\CoreBundle\Entity\User;
use Claroline\CoreBundle\Persistence\ObjectManager;
use Claroline\OfflineBundle\SyncConstant;
use Claroline\OfflineBundle\Entity\UserSynchronized;
use JMS\DiExtraBundle\Annotation as DI;
use \DateTime;

/**
 * @DI\Service("claroline.manager.synchronisation_manager")
 */

// This manager implements the global interaction between the online and the offline plateform
// It allows the synchronisation process to restart from where it stops
// For more documentation on the global process see our thesis "Chapter 5 : le processus global"
class SynchronisationManager
{
    private $om;
    private $creationManager;
    private $transferManager;
    private $userSyncManager;
    private $loadingManager;
    private $userSynchronizedRepo;

    /**
     * Constructor.
     * @DI\InjectParams({
     *     "om"             = @DI\Inject("claroline.persistence.object_manager"),
     *     "creationManager"    = @DI\Inject("claroline.manager.creation_manager"),
     *     "loadingManager" = @DI\Inject("claroline.manager.loading_manager"),
     *     "userSyncManager" = @DI\Inject("claroline.manager.user_sync_manager"),
     *     "transferManager" = @DI\Inject("claroline.manager.transfer_manager")
     * })
     */
    public function __construct(
        ObjectManager $om,
        CreationManager $creationManager,
        LoadingManager $loadingManager,
        UserSyncManager $userSyncManager,
        TransferManager $transferManager
    )
    {
        $this->om = $om;
        $this->creationManager = $creationManager;
        $this->transferManager = $transferManager;
        $this->userSyncManager = $userSyncManager;
        $this->loadingManager = $loadingManager;
        $this->userSynchronizedRepo = $om->getRepository('ClarolineOfflineBundle:UserSynchronized');
    }

    /*
    *   @param User $user
    *   @param UserSynchronized $userSync
    *   
    *   This method determine where the execution has to restart.
    *   This is based on the status from the UserSynchronized entity.
    */
    public function synchroniseUser(User $user, UserSynchronized $userSync)
    {
        $status = $userSync->getStatus();
        switch ($status) {
            // Last synchronisation was well ended.
            case UserSynchronized::SUCCESS_SYNC :
                // restart from the begining
                $this->step1Create($user, $userSync);
                break;
            // Has a synchronisation archive
            case UserSynchronized::STARTED_UPLOAD :
                // Where did we stopped the transmission ?
                $packetNum = $this->transferManager->getLastPacketUploaded($userSync->getFilename(), $user);
                // Restart uploading from the last stop
                $this->step2Upload($user, $userSync, $userSync->getFilename(), $packetNum+1);
                break;
            // Uploading failed
            case UserSynchronized::FAIL_UPLOAD :
                // Restart all the upload
                $this->step2Upload($user, $userSync, $userSync->getFilename());
                break;
            // Upload finished
            case UserSynchronized::SUCCESS_UPLOAD :
                // Let's download from the online
                $this->step3Download($user, $userSync, $userSync->getFilename());
                break;
            // Download fail
            case UserSynchronized::FAIL_DOWNLOAD :
                // Restart download
                $packetNum = $this->getDownloadStop($userSync->getFilename(), $user);
                $this->step3Download($user, $userSync, $userSync->getFilename(), null, $packetNum);
                break;
            // Download finished
            case UserSynchronized::SUCCESS_DOWNLOAD :
                $toLoad = SyncConstant::SYNCHRO_UP_DIR.$user->getId().'/sync_'.$userSync->getFilename().'.zip';
                // Load the online synchronisation archive on the plateform
                $this->step4Load($user, $userSync, $toLoad);
                break;
        }
    }

    // Method implementing the first step of the global process
    // Creates the synchronisation archive and transfer it to the second step
    public function step1Create(User $user, UserSynchronized $userSync)
    {
        // $toUpload will be the filename of the synchronisation archive created
        $toUpload = $this->creationManager->createSyncZip($user, $userSync->getLastSynchronization()->getTimestamp());
        // Save it in UserSync in case of restart needed
        $userSync->setFilename($toUpload);
        $userSync->setStatus(UserSynchronized::STARTED_UPLOAD);
        // Save the datetime of the end of the creation
        $now = new DateTime();
        $userSync->setSentTime($now);
        $this->userSyncManager->updateUserSync($userSync);
        // Go to step 2
        $this->step2Upload($user, $userSync, $toUpload);
    }

    // Method implementing the second step of the global process
    // Upload the synchronisation archive to the online plateform
    public function step2Upload(User $user, UserSynchronized $userSync, $filename, $packetNum = 0)
    {
        if ($filename == null) {
            $this->step1Create($user, $userSync);
        } else {
            // $toDownload will be the synchronisation archive of the online plateform.
            // this information is received when the upload is finished.
            $toDownload = $this->transferManager->uploadZip($filename, $user, $packetNum);
            //Saves informations and update status
            $userSync->setFilename($toDownload['hashname']);
            $userSync->setStatus(UserSynchronized::SUCCESS_UPLOAD);
            $this->userSyncManager->updateUserSync($userSync);
            //Go to step 3
            $this->step3Download($user, $userSync, $toDownload['hashname'], $toDownload['nPackets']);
            // Clean the directory when done (online the offline)
            $this->transferManager->deleteFile($user,substr($filename, strlen($filename)-40, 36), SyncConstant::SYNCHRO_UP_DIR);
            unlink($filename);
        }
    }

    // Method implementing the third step of the global process
    // Download the synchronisation archive of the online plateform
    public function step3Download(User $user, UserSynchronized $userSync, $filename, $nPackets = null, $packetNum = 0)
    {
        if ($nPackets == null) {
            echo "testons le nombre de frangments <br/>";
            $nPackets = $this->transferManager->getOnlineNumberOfPackets($filename, $user);
        }
        // The file doesn't exist online
        if ($nPackets == -1) {
            echo "j'en ai -1 <br/>";
            // Erase filename, set status and restart
            $userSync->setFilename(null);
            $userSync->setStatus(UserSynchronized::FAIL_UPLOAD);
            $this->userSyncManager->updateUserSync($userSync);
            $this->synchroniseUser($user, $userSync);
        } else {
            // $toLoad will be the downloaded from the online plateform
            $toLoad = $this->transferManager->getSyncZip($filename, $nPackets, $packetNum, $user);
            // Update userSync status
            $userSync->setStatus(UserSynchronized::SUCCESS_DOWNLOAD);
            $this->userSyncManager->updateUserSync($userSync);
            // Go to step 4
            $this->step4Load($user, $userSync, $toLoad);
            // Clean the files when done
            $this->transferManager->deleteFile($user, $filename, SyncConstant::SYNCHRO_DOWN_DIR);
            unlink($toLoad);
        }
    }

    // Method implementing the fourth step of the global process
    // It will load the downloaded from the archive into 
    public function step4Load(User $user, UserSynchronized $userSync, $filename)
    {
        // Load synchronisation archive ($filename) in offline database
        $this->loadingManager->loadZip($filename, $user);
        $userSync->setStatus(UserSynchronized::SUCCESS_SYNC);
        $userSync->setLastSynchronization($userSync->getSentTime());
        $this->userSyncManager->updateUserSync($userSync);
    }

    // This method has to return the last fragment uploaded on the online plateform
    // If there is any, return -1
    public function getDownloadStop($filename, $user)
    {
        $stop = true;
        $index = -1;
        while ($stop) {
            $file = SyncConstant::SYNCHRO_UP_DIR.$user->getId().'/'.$filename.'_'.($index + 1);
            if (! file_exists($file)) {
                $stop=false;
            } else {
                $index++;
            }
        }

        return $index;
    }
}
