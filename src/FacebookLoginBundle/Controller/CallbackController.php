<?php

/**
 * This file is part of the FacebookLogin Bundle.
 *
 * (c) inspiredminds <https://github.com/inspiredminds>
 *
 * @package   FacebookLoginBundle
 * @author    Fritz Michael Gschwantner <https://github.com/fritzmg>
 * @license   LGPL-3.0+
 * @copyright inspiredminds 2017
 */


namespace FacebookLoginBundle\Controller;

use Contao\Config;
use Contao\Date;
use Contao\Environment;
use Contao\FrontendUser;
use Contao\MemberGroupModel;
use Contao\MemberModel;
use Contao\Message;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use FacebookLoginBundle\Facebook\FacebookFactory;
use Facebook\GraphNodes\GraphUser;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Session\SessionAuthenticationStrategy;

/**
 * This controller handles the login response from Facebook
 *
 * @author Fritz Michael Gschwantner <https://github.com/fritzmg>
 *
 * @Route(defaults={"_scope" = "frontend", "_token_check" = true})
 */
class CallbackController extends Controller
{
    /**
     * @Route("/fblogincallback", name="fblogincallback")
     * @return RedirectResponse
     */
    public function fblogincallbackAction(Request $request)
    {
        // initialize the Contao framework
        $this->get('contao.framework')->initialize();

        // check for valid Facebook App config
        if (!\FacebookJSSDK::hasValidConfig())
        {
            System::log('Facebook login callback called without valid app configuration.', __METHOD__, TL_ERROR);
            return $this->getDefaultRedirect();
        }

        // get the session
        $session = $this->get('session');

        // check for session variables
        if (!$session->has('facebook_login_page') || !$session->has('facebook_login_module'))
        {
            System::log('No session data for for page and module.', __METHOD__, TL_ERROR);
            return $this->getDefaultRedirect();
        }

        // load the global page object
        global $objPage;
        $objPage = PageModel::findWithDetails($session->get('facebook_login_page'));
        $session->remove('facebook_login_page');

        // check for valid page
        if (null === $objPage)
        {
            System::log('Invalid referring page.', __METHOD__, TL_ERROR);
            return $this->getDefaultRedirect();
        }

        // get the module
        $objModule = ModuleModel::findById($session->get('facebook_login_module'));
        $session->remove('facebook_login_module');

        // check for valid module
        if (null === $objModule)
        {
            System::log('Invalid referring module.', __METHOD__, TL_ERROR);
            return $this->getDefaultRedirect();
        }

        // initialize Facebook SDK
        $fb = FacebookFactory::create();

        $helper = $fb->getRedirectLoginHelper();

        try
        {
            $accessToken = $helper->getAccessToken();
        }
        catch(\Exception $e)
        {
            System::log($e->getMessage(), __METHOD__, TL_ERROR);
            return $this->getDefaultRedirect();
        }

        if (!isset($accessToken))
        {
            if ($helper->getError())
            {
                System::log('Facebook login error: ' . $helper->getError() . '. Code: ' . $helper->getErrorCode() . '. Reason: ' . $helper->getErrorReason() . '. Description: ' . $helper->getErrorDescription(), __METHOD__, TL_ERROR);
            }

            return $this->getDefaultRedirect();
        }

        // The OAuth 2.0 client handler helps us manage access tokens
        $oAuth2Client = $fb->getOAuth2Client();

        // Get the access token metadata from /debug_token
        $tokenMetadata = $oAuth2Client->debugToken($accessToken);

        // Validation (these will throw FacebookSDKException's when they fail)
        try
        {
            $tokenMetadata->validateAppId(\FacebookJSSDK::getAppId());
            $tokenMetadata->validateExpiration();
        }
        catch(\Facebook\Exceptions\FacebookSDKException $e)
        {
            System::log($e->getMessage(), __METHOD__, TL_ERROR);
            return $this->getDefaultRedirect();
        }

        try
        {
            $response = $fb->get('/me?fields=id,name,email,first_name,middle_name,last_name,gender,locale', $accessToken->getValue());
        }
        catch(\Exception $e)
        {
            System::log($e->getMessage(), __METHOD__, TL_ERROR);
            return $this->getDefaultRedirect();
        }

        $user = $response->getGraphUser();

        if (!$user)
        {
            return $this->getDefaultRedirect();
        }

        // save the access token in the session
        $session->set('facebook_login_access_token', $accessToken->getValue());

        // log in the user
        $blnSuccess = $this->loginUser($user, $objModule);

        // redirect in case of success
        return $this->getSuccessRedirect($objModule);
    }


    /**
     * Logs the user into the Contao frontend.
     * @param GraphUser $user
     * @param ModuleModel $objModel
     * @return boolean
     */
    protected function loginUser(GraphUser $user, ModuleModel $objModule)
    {
        $session = $this->get('session');
        $time    = time();
        $db      = $this->get('database_connection');

        // get the data to be saved
        $arrSaveData = deserialize($objModule->fbLoginData, true);

        // check if users exists
        $objMember = MemberModel::findByFacebookId($user['id']);
        if (null === $objMember)
        {
            // create username
            $strUsername = 'fb_'.$user['id'];

            // create a new user
            $objMember = new MemberModel();
            $objMember->tstamp = $time;
            $objMember->dateAdded = $time;
            $objMember->firstname = \in_array('firstname', $arrSaveData) ? $user['first_name'] . ($user['middle_name'] ? ' ' . $user['middle_name'] : '') : '';
            $objMember->lastname = \in_array('lastname', $arrSaveData) ? $user['last_name'] : '';
            $objMember->gender = \in_array('gender', $arrSaveData) ? $user['gender'] : '';
            $objMember->email = ($user['email'] && \in_array('email', $arrSaveData)) ? $user['email'] : '';
            $objMember->login = 1;
            $objMember->username = $strUsername;
            $objMember->facebookId = $user['id'];
            $objMember->language = \in_array('locale', $arrSaveData) ? $user['locale'] : '';
            $objMember->groups = $objModule->reg_groups;
            $objMember->save();
        }

        // load the FrontendUser by facebookId
        $objFrontendUser = FrontendUser::getInstance();
        $objFrontendUser->findBy('facebookId', $user['id']);

        // check if a frontend user was found
        if (!$objFrontendUser->id)
        {
            throw new \RuntimeException('Error during Facebook login: no FrontendUser instance available.');
        }

        // check the account status
        if (!$this->checkAccountStatus())
        {
            return false;
        }

        // Regenerate the session ID to harden against session fixation attacks
        $strategy = $this->getParameter('security.authentication.session_strategy.strategy');
        switch ($strategy)
        {
            case SessionAuthenticationStrategy::NONE:
                break;

            case SessionAuthenticationStrategy::MIGRATE:
                $session->migrate(false); // do not destroy the old session
                break;

            case SessionAuthenticationStrategy::INVALIDATE:
                $session->invalidate();
                break;

            default:
                throw new \RuntimeException(sprintf('Invalid session authentication strategy "%s"', $strategy));
        }

        // Update the last login records
        $objFrontendUser->lastLogin = $objFrontendUser->currentLogin;
        $objFrontendUser->currentLogin = time();
        $objFrontendUser->loginCount = Config::get('loginCount');
        $objFrontendUser->save();

        // get session hash
        $hash = System::getSessionHash('FE_USER_AUTH');

        // delete old hash
        $db->delete('tl_session', ['hash' => $hash]);

        // insert the new session
        $db->insert('tl_session', [
            'pid' => $objMember->id,
            'tstamp' => $time,
            'name' => 'FE_USER_AUTH',
            'sessionID' => $session->getId(),
            'ip' => Environment::get('ip'),
            'hash' => $hash,
        ]);

        // Set the authentication cookie
        System::setCookie('FE_USER_AUTH', $hash, ($time + Config::get('sessionTimeout')), null, null, Environment::get('ssl'), true);

        // log
        System::log('User "' . $objFrontendUser->username . '" has logged in', __METHOD__, TL_ACCESS);

        // Set the auto login data
        if ($session->get('facebook_login_autologin') && Config::get('autologin') > 0)
        {
            $strToken = md5(uniqid(mt_rand(), true));

            $objFrontendUser->createdOn = $time;
            $objFrontendUser->autologin = $strToken;
            $objFrontendUser->save();

            System::setCookie('FE_AUTO_LOGIN', $strToken, ($time + Config::get('autologin')), null, null, Environment::get('ssl'), true);
        }

        $session->remove('facebook_login_autologin');

        // login successful
        return true;
    }


    /**
     * Checks the account status.
     * @return boolean
     */
    protected function checkAccountStatus()
    {
        $time = time();
        $objFrontendUser = FrontendUser::getInstance();

        // Check whether the account is locked
        if (($objFrontendUser->locked + Config::get('lockPeriod')) > $time)
        {
            Message::addError(sprintf($GLOBALS['TL_LANG']['ERR']['accountLocked'], ceil((($objFrontendUser->locked + Config::get('lockPeriod')) - $time) / 60)));

            return false;
        }

        // Check whether the account is disabled
        elseif ($objFrontendUser->disable)
        {
            Message::addError($GLOBALS['TL_LANG']['ERR']['invalidLogin']);
            System::log('The account has been disabled', __METHOD__, TL_ACCESS);

            return false;
        }

        // Check wether login is allowed (front end only)
        elseif ($objFrontendUser instanceof FrontendUser && !$objFrontendUser->login)
        {
            Message::addError($GLOBALS['TL_LANG']['ERR']['invalidLogin']);
            System::log('User "' . $objFrontendUser->username . '" is not allowed to log in', __METHOD__, TL_ACCESS);

            return false;
        }

        // Check whether account is not active yet or anymore
        elseif ($objFrontendUser->start != '' || $objFrontendUser->stop != '')
        {
            $time = Date::floorToMinute($time);

            if ($objFrontendUser->start != '' && $objFrontendUser->start > $time)
            {
                Message::addError($GLOBALS['TL_LANG']['ERR']['invalidLogin']);
                System::log('The account was not active yet (activation date: ' . Date::parse(Config::get('dateFormat'), $objFrontendUser->start) . ')', __METHOD__, TL_ACCESS);

                return false;
            }

            if ($objFrontendUser->stop != '' && $objFrontendUser->stop <= ($time + 60))
            {
                Message::addError($GLOBALS['TL_LANG']['ERR']['invalidLogin']);
                System::log('The account was not active anymore (deactivation date: ' . Date::parse(Config::get('dateFormat'), $objFrontendUser->stop) . ')', __METHOD__, TL_ACCESS);

                return false;
            }
        }

        return true;
    }


    /**
     * Returns the Redirect object in case of a successful login
     * @param ModuleModel $objModule
     * @return RedirectResponse
     */
    protected function getSuccessRedirect(ModuleModel $objModule)
    {
        $strRedirect = '';
        $objFrontendUser = FrontendUser::getInstance();

        // Make sure that groups is an array
        if (!is_array($objFrontendUser->groups))
        {
            $objFrontendUser->groups = ($objFrontendUser->groups != '') ? array($objFrontendUser->groups) : array();
        }

        // Skip inactive groups
        if (($objGroups = MemberGroupModel::findAllActive()) !== null)
        {
            $objFrontendUser->groups = array_intersect($objFrontendUser->groups, $objGroups->fetchEach('id'));
        }

        if (!empty($objFrontendUser->groups) && is_array($objFrontendUser->groups))
        {
            $objGroupPage = PageModel::findFirstActiveByMemberGroups($objFrontendUser->groups);

            if (null !== $objGroupPage)
            {
                $strRedirect = $objGroupPage->getAbsoluteUrl();
            }
        }

        if (!$strRedirect && null !== $objModule)
        {
            if ($objModule->redirectBack && $_SESSION['LAST_PAGE_VISITED'] != '')
            {
                $strRedirect = $_SESSION['LAST_PAGE_VISITED'];
            }
            elseif ($objModule->jumpTo && null !== ($objRedirect = PageModel::findById($objModule->jumpTo)))
            {
                $strRedirect = $objRedirect->getAbsoluteUrl();
            }
        }

        if ($strRedirect)
        {
            return new RedirectResponse($strRedirect);
        }
        else
        {
            return $this->getDefaultRedirect();
        }
    }


    /**
     * Returns the default Redirect object, depending on session variables
     * @return RedirectResponse
     */
    protected function getDefaultRedirect()
    {
        $session = $this->get('session');
        $request = $this->get('request_stack')->getCurrentRequest();

        if ($session->has('facebook_login_referrer'))
        {
            $url = $session->get('facebook_login_referrer');
            $session->remove('facebook_login_referrer');
            return new RedirectResponse($url);
        }
        else
        {
            return new RedirectResponse($request->getScheme() . '://' . $request->getHost() . ($this->get('kernel')->isDebug() ? '/app_dev.php/' : ''));
        }
    }
}
