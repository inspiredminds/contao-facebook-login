<?php

declare(strict_types=1);

/*
 * This file is part of the FacebookLogin Bundle.
 *
 * (c) inspiredminds
 *
 * @license LGPL-3.0-or-later
 */

namespace FacebookLoginBundle\Controller;

use Contao\CoreBundle\Framework\FrameworkAwareInterface;
use Contao\CoreBundle\Framework\FrameworkAwareTrait;
use Contao\CoreBundle\Routing\UrlGenerator;
use Contao\CoreBundle\Security\User\UserChecker;
use Contao\FrontendUser;
use Contao\MemberGroupModel;
use Contao\MemberModel;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\System;
use Facebook\GraphNodes\GraphUser;
use FacebookLoginBundle\Facebook\FacebookFactory;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

/**
 * This controller handles the login response from Facebook.
 */
class CallbackController implements FrameworkAwareInterface
{
    use FrameworkAwareTrait;

    protected $userProvider;
    protected $tokenStorage;
    protected $router;
    protected $dispatcher;
    protected $session;
    protected $request;
    protected $environment;
    protected $userChecker;

    public function __construct(
        UserProviderInterface $userProvider,
        TokenStorageInterface $tokenStorage,
        UrlGenerator $router,
        EventDispatcherInterface $dispatcher,
        Session $session,
        string $environment,
        UserChecker $userChecker
    ) {
        $this->userProvider = $userProvider;
        $this->tokenStorage = $tokenStorage;
        $this->router = $router;
        $this->dispatcher = $dispatcher;
        $this->session = $session;
        $this->environment = $environment;
        $this->userChecker = $userChecker;
    }

    public function fblogincallback(Request $request): RedirectResponse
    {
        // save the request
        $this->request = $request;

        // initialize the Contao framework
        $this->framework->initialize();

        // check for session variables
        if (!$this->session->has('facebook_login_page') || !$this->session->has('facebook_login_module')) {
            System::log('No session data for page and module.', __METHOD__, TL_ERROR);

            return $this->getDefaultRedirect();
        }

        // Load the global page object.
        // This is necessary so that FacebookJSSDK recognizes the correct App ID and App Secret.
        global $objPage;
        $objPage = PageModel::findWithDetails($this->session->get('facebook_login_page'));
        $this->session->remove('facebook_login_page');

        // check for valid page
        if (null === $objPage) {
            System::log('Invalid referring page.', __METHOD__, TL_ERROR);

            return $this->getDefaultRedirect();
        }

        // check for valid Facebook App config
        if (!\FacebookJSSDK::hasValidConfig()) {
            System::log('Facebook login callback called without valid app configuration.', __METHOD__, TL_ERROR);

            return $this->getDefaultRedirect();
        }

        // get the module
        $module = ModuleModel::findById($this->session->get('facebook_login_module'));
        $this->session->remove('facebook_login_module');

        // check for valid module
        if (null === $module) {
            System::log('Invalid referring module.', __METHOD__, TL_ERROR);

            return $this->getDefaultRedirect();
        }

        // initialize Facebook SDK
        $fb = FacebookFactory::create();

        $helper = $fb->getRedirectLoginHelper();

        try {
            $accessToken = $helper->getAccessToken();
        } catch (\Exception $e) {
            System::log($e->getMessage(), __METHOD__, TL_ERROR);

            return $this->getDefaultRedirect();
        }

        if (!isset($accessToken)) {
            if ($helper->getError()) {
                System::log('Facebook login error: '.$helper->getError().'. Code: '.$helper->getErrorCode().'. Reason: '.$helper->getErrorReason().'. Description: '.$helper->getErrorDescription(), __METHOD__, TL_ERROR);
            }

            return $this->getDefaultRedirect();
        }

        // The OAuth 2.0 client handler helps us manage access tokens
        $oAuth2Client = $fb->getOAuth2Client();

        // Get the access token metadata from /debug_token
        $tokenMetadata = $oAuth2Client->debugToken($accessToken);

        // Validation (these will throw FacebookSDKException's when they fail)
        try {
            $tokenMetadata->validateAppId(\FacebookJSSDK::getAppId());
            $tokenMetadata->validateExpiration();
        } catch (\Facebook\Exceptions\FacebookSDKException $e) {
            System::log($e->getMessage(), __METHOD__, TL_ERROR);

            return $this->getDefaultRedirect();
        }

        try {
            $response = $fb->get('/me?fields=id,name,email,first_name,middle_name,last_name,gender,locale', $accessToken->getValue());
        } catch (\Exception $e) {
            System::log($e->getMessage(), __METHOD__, TL_ERROR);

            return $this->getDefaultRedirect();
        }

        $graphUser = $response->getGraphUser();

        if (!$graphUser) {
            return $this->getDefaultRedirect();
        }

        // save the access token in the session
        $this->session->set('facebook_login_access_token', $accessToken->getValue());

        // log in the user
        $user = $this->loginUser($graphUser, $module);

        // success redirect
        return $this->getSuccessRedirect($user, $module);
    }

    /**
     * Logs the user into the Contao frontend.
     */
    protected function loginUser(GraphUser $graphUser, ModuleModel $module): FrontendUser
    {
        $time = time();

        // get the data to be saved
        $saveData = deserialize($module->fbLoginData, true);

        // check if users exists
        $member = MemberModel::findByFacebookId($graphUser['id']);
        if (null === $member) {
            // create username
            $username = 'fb_'.$graphUser['id'];

            // create a new user
            $member = new MemberModel();
            $member->tstamp = $time;
            $member->dateAdded = $time;
            $member->firstname = \in_array('firstname', $saveData, true) ? $graphUser['first_name'].($graphUser['middle_name'] ? ' '.$graphUser['middle_name'] : '') : '';
            $member->lastname = \in_array('lastname', $saveData, true) ? $graphUser['last_name'] : '';
            $member->gender = \in_array('gender', $saveData, true) ? $graphUser['gender'] : '';
            $member->email = ($graphUser['email'] && \in_array('email', $saveData, true)) ? $graphUser['email'] : '';
            $member->login = 1;
            $member->username = $username;
            $member->facebookId = $graphUser['id'];
            $member->language = \in_array('locale', $saveData, true) ? $graphUser['locale'] : '';
            $member->groups = $module->reg_groups;
            $member->save();
        }

        // Get the user
        $user = $this->userProvider->loadUserByUsername($member->username);

        // Check pre auth for the user
        $this->userChecker->checkPreAuth($user);

        // Authenticate the user
        $usernamePasswordToken = new UsernamePasswordToken($user, null, 'frontend', $user->getRoles());
        $this->tokenStorage->setToken($usernamePasswordToken);

        $event = new InteractiveLoginEvent($this->request, $usernamePasswordToken);
        $this->dispatcher->dispatch('security.interactive_login', $event);

        // TODO: set autologin
        $this->session->remove('facebook_login_autologin');

        // login successful
        return $user;
    }

    /**
     * Returns the Redirect object in case of a successful login.
     */
    protected function getSuccessRedirect(FrontendUser $user, ModuleModel $module): RedirectResponse
    {
        $redirectUrl = '';

        // Make sure that groups is an array
        if (!\is_array($user->groups)) {
            $user->groups = ('' !== $user->groups) ? [$user->groups] : [];
        }

        // Skip inactive groups
        if (null !== ($objGroups = MemberGroupModel::findAllActive())) {
            $user->groups = array_intersect($user->groups, $objGroups->fetchEach('id'));
        }

        if (!empty($user->groups) && \is_array($user->groups)) {
            if (null !== ($groupPage = PageModel::findFirstActiveByMemberGroups($user->groups))) {
                $redirectUrl = $groupPage->getAbsoluteUrl();
            }
        }

        if (!$redirectUrl && null !== $module) {
            if ($module->redirectBack && '' !== $_SESSION['LAST_PAGE_VISITED']) {
                $redirectUrl = $_SESSION['LAST_PAGE_VISITED'];
            } elseif ($module->jumpTo && null !== ($redirectPage = PageModel::findById($module->jumpTo))) {
                $redirectUrl = $redirectPage->getAbsoluteUrl();
            }
        }

        if ($redirectUrl) {
            return new RedirectResponse($redirectUrl);
        }

        return $this->getDefaultRedirect();
    }

    /**
     * Returns the default Redirect object, depending on session variables.
     */
    protected function getDefaultRedirect(): RedirectResponse
    {
        if ($this->session->has('facebook_login_referrer')) {
            $url = $this->session->get('facebook_login_referrer');
            $this->session->remove('facebook_login_referrer');

            return new RedirectResponse($url);
        }

        return new RedirectResponse($this->request->getScheme().'://'.$this->request->getHost().('dev' === $this->environment ? '/app_dev.php/' : ''));
    }
}
