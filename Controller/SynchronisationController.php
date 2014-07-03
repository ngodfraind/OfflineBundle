<?php

namespace Claroline\OfflineBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Sensio\Bundle\FrameworkExtraBundle\Configuration as EXT;
use JMS\DiExtraBundle\Annotation as DI;
use JMS\SecurityExtraBundle\Annotation as SEC;
use Claroline\CoreBundle\Entity\User;
use Claroline\CoreBundle\Library\Security\Authenticator;
use Claroline\CoreBundle\Manager\ResourceManager;
use Claroline\CoreBundle\Manager\UserManager;
use Claroline\CoreBundle\Repository\UserRepository;
use Claroline\OfflineBundle\SyncConstant;
use Claroline\OfflineBundle\Entity\Credential;
use Claroline\OfflineBundle\Form\OfflineFormType;
use Claroline\CoreBundle\Persistence\ObjectManager;
use \DateTime;
use \ZipArchive;


class SynchronisationController extends Controller
{    
    private $om;
    private $authenticator;
    private $request;
    private $userRepository;
    private $userManager;
    
     /**
     * @DI\InjectParams({
     *     "om"             = @DI\Inject("claroline.persistence.object_manager"),
     *     "authenticator"  = @DI\Inject("claroline.authenticator"),
     *     "userManager"   = @DI\Inject("claroline.manager.user_manager"),
     *     "request"            = @DI\Inject("request")
     * })
     */
    public function __construct(
        ObjectManager $om,
        Authenticator $authenticator,       
        UserManager $userManager,
        Request $request
    )
    {
        $this->om = $om;
        $this->authenticator = $authenticator;
        $this->userManager = $userManager;
        $this->request = $request;
        $this->userRepository = $om->getRepository('ClarolineCoreBundle:User');
    }
    // TODO Security voir workspace controller.

    private function getUserFromID($user)
    {
        
        $arrayRepo = $em->getRepository('ClarolineOfflineBundle:UserSynchronized')->findById($user);
        return $arrayRepo[0];
    }

    /**
    *   @EXT\Route(
    *       "/transfer/uploadzip",
    *       name="claro_sync_upload_zip",
    *   )
    *
    *   @EXT\Method("POST")    
    *
    *   @return Response
    */
    public function getUploadAction()
    {   /*
        *   A adapter ici. Au sein de la requete qui appelle on est maintenant sur du POST et non plus sur du GET
        *   la methode recevra avec la requete le zip de l'utilisateur offline
        *   Il faut donc commencer par recevoir le zip du offline
        *   Ensuite le traiter
        *   Generer le zip descendant et le retourner dans la stream reponse
        */
        
        //TODO verifier l'authentification via token
        // echo "je suis sur cette route !!!!!<br/>";
        $content = $this->getRequest()->getContent();
        // echo "CONTENT received : ".$content."<br/>";
        $informationsArray = (array)json_decode($content);
        // echo "Packet Number : ".$informationsArray['packetNum'].'<br/>';
 
        $user = $this->userRepository->findOneBy(array('exchangeToken' => $informationsArray['token']));
        $status = $this->authenticator->authenticateWithToken($user->getUsername(), $informationsArray['token']) ? 200 : 401;

        // echo "STATUS : ".$status."<br/>";
        $content = array();
        if ($status == 200){
            $content = $this->get('claroline.manager.transfer_manager')->processSyncRequest($informationsArray, true);
            // echo "what s generate by process request? : ".json_encode($content).'<br/>';
            $status = $content['status'];
        }
        return new JsonResponse($content, $status);
        // return new JsonResponse($content, 200);
    }
    
    
    /**
    *   @EXT\Route(
    *       "/transfer/getzip",
    *       name="claro_sync_get_zip",
    *   )
    *
    *   @EXT\Method("POST")    
    *
    *   @return Response
    */
    public function getZipAction()
    {
        $content = $this->getRequest()->getContent();
        $informationsArray = (array)json_decode($content);
        // echo "Ask Packet Number : ".$informationsArray['packetNum'].'<br/>';
        $user = $this->userRepository->findOneBy(array('exchangeToken' => $informationsArray['token']));
        $status = $this->authenticator->authenticateWithToken($user->getUsername(), $informationsArray['token']) ? 200 : 401;
        // echo "STATUS : ".$status."<br/>";
        $content = array();
        if($status == 200){
            $fileName = SyncConstant::SYNCHRO_DOWN_DIR.$user->getId().'/sync_'.$informationsArray['hashname'].'.zip';
            $em = $this->getDoctrine()->getManager();
            $content = $this->get('claroline.manager.transfer_manager')->getMetadataArray($user, $fileName);
            $content['packetNum']=$informationsArray['packetNum'];
            $data = $this->get('claroline.manager.transfer_manager')->getPacket($informationsArray['packetNum'], $fileName);
            if($data == null){
                $status = 424;
            }else{
                $content['file'] = base64_encode($data);
            }
        }
        return new JsonResponse($content, $status);
    }

    /**
    *   @EXT\Route(
    *       "/sync/user",
    *       name="claro_sync_user",
    *   )
    *
    *   @EXT\Method("POST")    
    *
    *   @return Response
    */
    public function getUserIformations()
    {
        $content = $this->getRequest()->getContent();
        // echo "receive content <br/>";
        $informationsArray = (array)json_decode($content);
        $status = $this->authenticator->authenticate($informationsArray['username'], $informationsArray['password']) ? 200 : 401;        
        // echo "STATUS : ".$status."<br/>";
        $returnContent = array(); 

        if($status == 200){
            // Get User informations and return them
            $em = $this->getDoctrine()->getManager();
            $user = $em->getRepository('ClarolineCoreBundle:User')->loadUserByUsername($informationsArray['username']);
            //TODO ajout du token
            $returnContent = $user->getUserAsTab();
        }
        return new JsonResponse($returnContent, $status);
    }
    
   /**
    *   @EXT\Route(
    *       "/sync/lastUploaded",
    *       name="claro_sync_last_uploaded",
    *   )
    *
    *   @EXT\Method("POST")    
    *
    *   @return Response
    */
    public function getLastUploaded()
    {
        $content = $this->getRequest()->getContent();
        $informationsArray = (array)json_decode($content);
        $user = $this->userRepository->findOneBy(array('exchangeToken' => $informationsArray['token']));
        $status = $this->authenticator->authenticateWithToken($user->getUsername(), $informationsArray['token']) ? 200 : 401;
        $content = array();
        if($status == 200)
        {
            $filename = SyncConstant::SYNCHRO_UP_DIR.$informationsArray['id'].'/'.$informationsArray['hashname'];
            $em = $this->getDoctrine()->getManager();
            // $user = $em->getRepository('ClarolineCoreBundle:User')->loadUserByUsername($informationsArray['username']);
            $lastUp = $this->get('claroline.manager.synchronisation_manager')->getDownloadStop($filename, $user);
            $content = array(
                'hashname' => $informationsArray['hashname'],
                'lastUpload' => $lastUp
            );
        }
        return new JsonResponse($content, $status);
    }
    
   /**
    *   @EXT\Route(
    *       "/sync/numberOfPacketsToDownload",
    *       name="claro_sync_number_of_packets_to_download",
    *   )
    *
    *   @EXT\Method("POST")    
    *
    *   @return Response
    */
    public function getNumberOfPacketsToDownload()
    {
        $content = $this->getRequest()->getContent();
        $informationsArray = (array)json_decode($content);
        $user = $this->userRepository->findOneBy(array('exchangeToken' => $informationsArray['token']));
        $status = $this->authenticator->authenticateWithToken($user->getUsername(), $informationsArray['token']) ? 200 : 401;
        $content = array();
        if($status == 200)
        {
            $filename = SyncConstant::SYNCHRO_DOWN_DIR.$informationsArray['id'].'/sync_'.$informationsArray['hashname'].".zip";
            $nPackets = $this->get('claroline.manager.transfer_manager')->getNumberOfParts($filename);
            $content = array(
                'hashname' => $informationsArray['hashname'],
                'nPackets' => $nPackets
            );
        }
        return new JsonResponse($content, $status);
    }
          
    /**
    *   First Connection of the user
    *
    *   @EXT\Route(
    *       "/sync/config",
    *       name="claro_sync_config"
    *   )
    *
    * @EXT\Template("ClarolineOfflineBundle:Offline:config.html.twig")
    */
    public function firstConnectionAction()
    {
        $cred = new Credential();
        $form = $this->createForm(new OfflineFormType(), $cred);
        
        $form->handleRequest($this->request);
        if($form->isValid()) {
            /*
            *   Check if the user exists on the distant database
            */
            $profil = $this->get('claroline.manager.transfer_manager')->getUserInfo($cred->getName(), $cred->getPassword());
            if($profil !== false)
            {
                // The array contains informations, meaning that the user exist in the database
                // We need to recreate the user in the local database then start the first synchronisation.
                // return $this->redirect($this->generateUrl('claro_sync_config_ok'));
            }
            else
            {
                // return $this->redirect($this->generateUrl('claro_sync_config_nok'));
            }
        }
        return array(
           'form' => $form->createView()
        );
    }

    /**
    *   User found online.
    *
    *   @EXT\Route(
    *       "/sync/config/ok",
    *       name="claro_sync_config_ok"
    *   )
    *
    * @EXT\Template("ClarolineOfflineBundle:Offline:config.html.twig")
    */
    public function firstConnectionOkAction()
    {
        echo 'It works!';
        return array(
        );
    }

    /**
    *   User doesn't exist online.
    *
    *   @EXT\Route(
    *       "/sync/config/nok",
    *       name="claro_sync_config_nok"
    *   )
    *
    * @EXT\Template("ClarolineOfflineBundle:Offline:config.html.twig")
    */
    public function firstConnectionNokAction()
    {
        echo '404 not found';
        return array(
        );
    }
    
    /**
    *   @EXT\Route(
    *       "/transfer/confirm",
    *       name="claro_confirm_sync",
    *   )
    *
    *   @EXT\Method("GET")    
    */
    public function confirmAction()
    {
    //DEPRECATED DO NOT USE
        /*$em = $this->getDoctrine()->getManager();
        $arrayRepo = $em->getRepository('ClarolineOfflineBundle:UserSynchronized')->findById($user);
        $authUser = $arrayRepo[0];*/
        // $authUser = $this->getUserFromID($user);

        //TODO verifier authentification !!!  => SHOULD return false if fails
        $this->get('claroline.manager.user_sync_manager')->updateUserSynchronized($authUser);
        return true;
    }

    /**
    *  Transfert workspace list
    *   
    *   @EXT\Route(
    *       "/transfer/workspace/{user}",
    *       name="claro_sync_transfer"
    *   )
    *
    * @EXT\Method("GET")
    *
    * @return Response
    */
    public function workspaceAction($user)
    {
        // Deprecated, not used anymore
        //TODO Authentification User
        $authUser = $this->getUserFromID($user);
        $toSend = $this->get('claroline.manager.creation_manager')->writeWorspaceList($authUser);
        
        //Send back the online sync zip
        $response = new StreamedResponse();
        //SetCallBack voir Symfony/Bundle/Controller/Controller pour les parametres de set callback
        $response->setCallBack(
            function () use ($toSend) {                
                readfile($toSend);
            }
        );

        return $response;
    }

    //TODO Route pour supprimer les fichiers de synchro
}
