<?php

/*
 * This file is part of the Claroline Connect package.
 *
 * (c) Claroline Consortium <consortium@claroline.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Claroline\OfflineBundle\Model\Resource;

use Claroline\CoreBundle\Entity\User;
use Claroline\CoreBundle\Entity\Resource\ResourceType;
use Claroline\CoreBundle\Entity\Resource\ResourceNode;
use Claroline\CoreBundle\Entity\Resource\Directory;
use Claroline\CoreBundle\Entity\Workspace\Workspace;
use Claroline\CoreBundle\Library\Utilities\ClaroUtilities;
use Claroline\CoreBundle\Manager\ResourceManager;
use Claroline\CoreBundle\Persistence\ObjectManager;
use Claroline\OfflineBundle\Entity\UserSynchronized;
use Claroline\OfflineBundle\Model\SyncConstant;
use Claroline\OfflineBundle\Model\SyncInfo;
use JMS\DiExtraBundle\Annotation as DI;
use Symfony\Component\Translation\TranslatorInterface;
use \DOMDocument;
use \DateTime;
use \ZipArchive;

/**
 * @DI\Service("claroline_offline.offline.directory")
 * @DI\Tag("claroline_offline.offline")
 */
class OfflineDirectory extends OfflineResource
{
    private $om;
    private $resourceManager;
    private $userRepo;
    private $resourceNodeRepo;
    
    /**
     * Constructor.
     *
     * @DI\InjectParams({
     *     "om"             = @DI\Inject("claroline.persistence.object_manager"),
     *     "resourceManager"= @DI\Inject("claroline.manager.resource_manager"),
     * })
     */
    public function __construct(
        ObjectManager $om,
        ResourceManager $resourceManager
    )
    {
        $this->om = $om;
        $this->resourceNodeRepo = $om->getRepository('ClarolineCoreBundle:Resource\ResourceNode');
        $this->userRepo = $om->getRepository('ClarolineCoreBundle:User');
        $this->resourceManager = $resourceManager;
    }

   // Return the type of resource supported by this service
   public function getType(){
        return 'directory';
    }
 
    /**
     * Add informations required to check and recreated a resource if necessary.
     *
     * @param \Claroline\CoreBundle\Entity\Resource\ResourceNode $resToAdd
     * @param \ZipArchive $archive
     */ 
    public function addResourceToManifest($domManifest, $domWorkspace, ResourceNode $resToAdd, ZipArchive $archive, $date)
    {
        parent::addNodeToManifest($domManifest, $this->getType(), $domWorkspace, $resToAdd);
        return $domManifest;
    }

    /**
     * Create a resource of the type supported by the service based on the XML file.
     *
     * @param \Claroline\CoreBundle\Entity\Workspace\Workspace $workspace
     * @param \Claroline\CoreBundle\Entity\User $user
     * @param \Claroline\OfflineBundle\Model\SyncInfo $wsInfo
     * @param string $path
     *
     * @return \Claroline\OfflineBundle\Model\SyncInfo
     */    
    public function createResource($resource, Workspace $workspace, User $user, SyncInfo $wsInfo, $path)
    { 
        $newResource = new Directory();
        $creation_date = new DateTime();
        $modification_date = new DateTime();
        $creation_date->setTimestamp($resource->getAttribute('creation_date'));
        $modification_date->setTimestamp($resource->getAttribute('modification_date'));

        $type = $this->resourceManager->getResourceTypeByName($resource->getAttribute('type'));
        $creator = $this->userRepo->findOneBy(array('exchangeToken' => $resource->getAttribute('creator')));
        $parent_node = $this->resourceNodeRepo->findOneBy(array('hashName' => $resource->getAttribute('hashname_parent')));

        if (count($parent_node) < 1) {
            // If the parent node doesn't exist anymore, workspace will be the parent.
            $parent_node  = $this->resourceNodeRepo->findOneBy(array('workspace' => $workspace));
        }
        
        $newResource->setName($resource->getAttribute('name'));
        $newResource->setMimeType($resource->getAttribute('mimetype'));

        $this->resourceManager->create($newResource, $type, $creator, $workspace, $parent_node, null, array(), $resource->getAttribute('hashname_node'));
        $wsInfo->addToCreate($resource->getAttribute('name'));
        
        $node = $newResource->getResourceNode();
        $this->changeDate($node, $creation_date, $modification_date, $this->om, $this->resourceManager);
        return $wsInfo;
    }
    
    /**
     * Update a resource of the type supported by the service based on the XML file.
     *
     * @param \Claroline\CoreBundle\Entity\Resource\ResourceNode $node
     * @param \Claroline\CoreBundle\Entity\Workspace\Workspace $workspace
     * @param \Claroline\CoreBundle\Entity\User $user
     * @param \Claroline\OfflineBundle\Model\SyncInfo $wsInfo
     * @param string $path
     *
     * @return \Claroline\OfflineBundle\Model\SyncInfo
     *
     */   
    public function updateResource($resource, ResourceNode $node, Workspace $workspace, User $user, SyncInfo $wsInfo, $path)
    {
        $type = $this->resourceManager->getResourceTypeByName($resource->getAttribute('type'));
        $modif_date = $resource->getAttribute('modification_date');
        $creation_date = $resource->getAttribute('creation_date'); //USELESS?
        $node_modif_date = $node->getModificationDate()->getTimestamp();
        $user_sync = $this->om->getRepository('ClarolineOfflineBundle:UserSynchronized')->findUserSynchronized($user);
                
        if ($node_modif_date < $modif_date) {
            $this->resourceManager->rename($node, $resource->getAttribute('name'));
            $wsInfo->addToUpdate($resource->getAttribute('name'));
        }
        return $wsInfo;
    }
   
    /**
     * Create a copy of the resource in case of conflict (e.g. if a ressource has been modified both offline
     * and online)
     *
     * @param \Claroline\CoreBundle\Entity\Workspace\Workspace $workspace
     * @param \Claroline\CoreBundle\Entity\Resource\ResourceNode $node     
     * @param string $path
     */
    public function createDoublon($resource, Workspace $workspace, ResourceNode $node, $path)
    {
        return;
    }
}
