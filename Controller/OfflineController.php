<?php

namespace Claroline\OfflineBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration as EXT;
use JMS\DiExtraBundle\Annotation as DI;
use JMS\SecurityExtraBundle\Annotation as SEC;
use Claroline\CoreBundle\Entity\User;
use Claroline\CoreBundle\Repository;
use Claroline\CoreBundle\Entity\Workspace\AbstractWorkspace;
use Claroline\CoreBundle\Manager\ResourceManager;
use Claroline\CoreBundle\Entity\ResourceNode;
use Claroline\CoreBundle\Controller\FileController;
use Claroline\OfflineBundle\SyncConstant;
use \DateTime;
use \ZipArchive;
use \Buzz\Browser;
use \Buzz\Client\Curl;
use \Buzz\Client\FileGetContents;

/**
 * @DI\Tag("security.secure_service")
 * @SEC\PreAuthorize("hasRole('ROLE_USER')")
 */
class OfflineController extends Controller
{
    private $router;
    
    /**
    * @DI\InjectParams({
    *      "router"             = @DI\Inject("router")
    * })
    */
    public function __construct(
        UrlGeneratorInterface $router
    )
    {
       $this->router = $router;
    }
    
    
    /**
     * Get content by id
     *
     * @EXT\Route(
     *     "/sync",
     *     name="claro_sync"
     * )
     * @EXT\ParamConverter("user", options={"authenticatedUser" = true})
     * 
     * @param User $user
     *
     * @return Response
     */
    public function helloAction(User $user)
    {
        $em = $this->getDoctrine()->getManager();
        $userSynchroDate = $em->getRepository('ClarolineOfflineBundle:UserSynchronized')->findUserSynchronized($user);
         
        $username = $user->getFirstName() . ' ' . $user->getLastName();
        
        if ($userSynchroDate) {
        //TODO Liens vers la route de synchronisation
            return $this->render('ClarolineOfflineBundle:Offline:sync.html.twig', array(
                'user' => $username,
                'user_sync_date' => $userSynchroDate[0]->getLastSynchronization()
            ) );
        }else{
        //TODO Methode d'installation
            return $this->render('ClarolineOfflineBundle:Offline:first_sync.html.twig', array(
                'user' => $username
            ) );
        }
    }
    
    /**
    *   Create userSyncrhonized entity
    *   
    *   @EXT\Route(
    *       "/sync/load",
    *       name="claro_sync_load"
    *   )
    *
    * @EXT\ParamConverter("user", options={"authenticatedUser" = true})
    * @EXT\Template("ClarolineOfflineBundle:Offline:load.html.twig")
    *
    * @param User $user
    * @return Response
    */
    public function loadAction(User $user)
    {
        //$userSynchro = $this->get('claroline.manager.synchronize_manager')->createUserSynchronized($user);
         
        //$em = $this->getDoctrine()->getManager();
        //$userSynchroDate = $em->getRepository('ClarolineOfflineBundle:UserSynchronized')->findUserSynchronized($user);
         
        //$zip = $this->get('claroline.manager.loading_manager')->loadXML('manifest_test_3.xml');
        /*        
        $em = $this->getDoctrine()->getManager();
        $lol = $em->getEventManager();
        $tmp = array();
        $tmp = $lol->getListener();
        
        foreach($tmp as $listener)
        {
            echo 'Listener found';
        }*/
        /*
        $dispatcher = $this->get('event_dispatcher');
        $tmp = array();
        $tmp = $dispatcher->getListeners();
        foreach($tmp as $listener)
        {
            echo 'Boulou'.'<br/>';
        }
        */
       // $em = this->getDoctrine->
        
        //$bool = $dispatcher->hasListeners('Gedmo\Timestampable');
        
        //echo 'Bool : '.$bool.'<br/>';
        
        $zip = $this->get('claroline.manager.loading_manager')->loadZip('sync_097EC883-B9D8-4998-9A47-966791971A87.zip');
         
        $username = $user->getFirstName() . ' ' . $user->getLastName();
        return array(
            'user' => $username
         );
    }
    
    /**
    *   Create userSyncrhonized entity
    *   
    *   @EXT\Route(
    *       "/sync/exchange/{user}",
    *       name="claro_sync_exchange"
    *   )
    *
    * @EXT\ParamConverter("authUser", options={"authenticatedUser" = true})
    * @EXT\Template("ClarolineOfflineBundle:Offline:load.html.twig")
    *
    * @param User $authUser
    * @return Response
    */
    public function syncAction($user, User $authUser)
    {   /**
        *   TODO CLEAN UNUSED FUNCTIONS
        *   TODO MODIFY return with render different twig donc redirect plutot que le boolean true false
        */
        
        if($user != $authUser->getId())
        {
            $username = $authUser->getFirstName() . ' ' . $authUser->getLastName();
            return array(
                'user' => $username,
                'succeed' => false
            );
        }
        else
        {
            //CREATE THE SYNC_ZIP
            $archive = $this->get('claroline.manager.synchronize_manager')->createSyncZip($authUser);
            
            //TRANSFERT THE ZIP
            $response = $this->get('claroline.manager.transfer_manager')->transferZip($archive, $authUser);
            echo 'I received  : '.$response.'<br/>';
            
            //LOAD RECEIVED SYNC_ZIP 
            $this->get('claroline.manager.loading_manager')->loadZip($response, $authUser);
            
            //echo 'SUCCEED';
            
            //UPDATE SYNCHRONIZE DATE
            $userSynchro = $this->get('claroline.manager.synchronize_manager')->updateUserSynchronized($authUser);
            
            //clean directory
            unlink($archive);
            unlink($response);
            
            //Format the view
            $username = $authUser->getFirstName() . ' ' . $authUser->getLastName();
            return array(
                'user' => $username,
                'succeed' => true
             );
        }
    }

    /**
    * USELESS NOW !!!!!!!!!!!
    *
    *
    *   Seek and show all the modified courses and ressources
    *   
    *   @EXT\Route(
    *       "/sync/seek",
    *       name="claro_sync_seek"
    *   )
    *
    * @EXT\ParamConverter("user", options={"authenticatedUser" = true})
    * @EXT\Template("ClarolineOfflineBundle:Offline:courses.html.twig")
    *
    * @param User $user
    * @return Response
    */
    public function seekAction(User $user)
    {
        $test = $this->get('claroline.manager.synchronize_manager')->createSyncZip($user);
        $username = $user->getFirstName() . ' ' . $user->getLastName(); 
        echo 'Congratulations '.$username.'! '."<br/>".'You are now synchronized!';
           
        return array(
            'user' => $username,
            //'user_courses' => $test['user_courses'],
            //'user_res' => $test['user_res']
        );
    }
    
    /**
    *   UNUSED !!!!!!
    *
    *  Transfer a file (sync archive) from a computer to another
    *   
    *   @EXT\Route(
    *       "/sync/transfer/{user}",
    *       name="claro_sync_transfer"
    *   )
    *
    * @EXT\ParamConverter("authUser", options={"authenticatedUser" = true})
    * @EXT\Template("ClarolineOfflineBundle:Offline:transfer.html.twig")
    *
    * @param User $user
    * @return Reponse
    */
    public function transferAction($user, User $authUser)
    {
        $transfer = true;
        if($user == $authUser->getId()){
            $test = $this->get('claroline.manager.transfer_manager')->getSyncZip($authUser);
            //$test = $this->get('claroline.manager.transfer_manager')->transferZip($authUser);
        }else{
            $transfer = false;
        }
        
        $username = $authUser->getFirstName() . ' ' . $authUser->getLastName(); 
        return array(
            'user' => $username,
            'transfer' => $transfer
        );
    }
    
    /**
    *   @EXT\Route(
    *       "/sync/getzip/{user}",
    *       name="claro_sync_get_zip",
    *   )
    *
    *   @EXT\Method("POST")    
    *
    *   @return Response
    */
     public function getZipAction($user)
    {   /*
        *   A adapter ici. Au sein de la requete qui appelle on est maintenant sur du POST et non plus sur du GET
        *   la methode recevra avec la requete le zip de l'utilisateur offline
        *   Il faut donc commencer par recevoir le zip du offline
        *   Ensuite le traiter
        *   Generer le zip descendant et le retourner dans la stream reponse
        */
        
        $request = $this->getRequest();
        //TODO verifier l'authentification
        //Catch the sync zip sent via POST request
        $uploadedSync = $this->get('claroline.manager.transfer_manager')->processSyncRequest($request, $user);
        //TODO verfier securite? => dans FileController il fait un checkAccess....
        
        //TODO GET AUTH USER BASED ON ID
        //Load the archive
        //$this->get('claroline.manager.loading_manager')->loadZip($uploadedSync, $authUser);
        
        //Compute the answer
        //$toSend = $this->get('claroline.manager.synchronize_manager')->createSyncZip($authUser);
        
        //Send back the online sync zip
        $response = new StreamedResponse();
        //SetCallBack voir Symfony/Bundle/Controller/Controller pour les parametres de set callback
        $response->setCallBack(
            function () use ($user) {
                readfile(SyncConstant::SYNCHRO_DOWN_DIR.$user.'/sync_2CCDD72F-C788-41B8-8AA4-B407E8FD9193.zip');
                //readfile($toSend);
            }
        );
        
        return $response;
    }
}
