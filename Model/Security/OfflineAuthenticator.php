<?php

/*
 * This file is part of the Claroline Connect package.
 *
 * (c) Claroline Consortium <consortium@claroline.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Claroline\OfflineBundle\Model\Security;

use Symfony\Component\Security\Core\SecurityContextInterface;
use Claroline\CoreBundle\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;
use JMS\DiExtraBundle\Annotation as DI;

/**
 * @DI\Service("claroline.offline_authenticator")
 */
class OfflineAuthenticator
{
    private $sc;
    private $encodeFactory;
    private $userRepo;

    /**
     * @DI\InjectParams({
     *     "om"            = @DI\Inject("claroline.persistence.object_manager"),
     *     "sc"            = @DI\Inject("security.context"),
     *     "encodeFactory" = @DI\Inject("security.encoder_factory")
     * })
     */
    public function __construct(
        ObjectManager $om,
        SecurityContextInterface $sc,
        EncoderFactoryInterface $encodeFactory
    )
    {
        $this->userRepo = $om->getRepository('ClarolineCoreBundle:User');
        $this->sc = $sc;
        $this->encodeFactory = $encodeFactory;
    }

    public function authenticateWithToken($username, $exchangeToken)
    {
        try {
            $user = $this->userRepo->loadUserByUsername($username);
        } catch (\Exception $e) {
            return false;
        }

        if ($user->getExchangeToken()===$exchangeToken) {
            $token = new UserExchangeToken($user, $exchangeToken);
            $this->sc->setToken($token);

            return true;
        }

        return false;
    }

}
