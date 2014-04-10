<?php

/*
 * This class contains all Constants used 
 * in the synchronisation process.
 */
 
namespace Claroline\OfflineBundle;


class SyncConstant
{

    //Plateform Constants
    const PLATEFORM_URL = 'http://127.0.0.1:14580/Claroline2/web/app_dev.php';
    const SYNCHRO_UP_DIR = './synchronize_up/';
    const SYNCHRO_DOWN_DIR = './synchronize_down/';

    // ResourceType Constant
    const FILE = 1;
    const DIR = 2;
    const TEXT = 3;
    const FORUM = 9;
    
    // Zip Constant
    const DIRZIP = './extractedZip';
    const MANIFEST = 'manifest';
    const ZIPFILEDIR = '/files/';
    
    //Forum Content Type Constant
    const CATE = "Claroline\ForumBundle\Entity\Category";
    const SUB = "Claroline\ForumBundle\Entity\Subject";
    const MSG = "Claroline\ForumBundle\Entity\Message";
}