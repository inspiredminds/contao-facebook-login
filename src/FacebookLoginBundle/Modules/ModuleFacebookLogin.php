<?php

declare(strict_types=1);

/*
 * This file is part of the FacebookLogin Bundle.
 *
 * (c) inspiredminds
 *
 * @license LGPL-3.0-or-later
 */

namespace FacebookLoginBundle\Modules;

use Contao\BackendTemplate;
use Contao\Config;
use Contao\Controller;
use Contao\Environment;
use Contao\FrontendUser;
use Contao\Input;
use Contao\Module;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use FacebookLoginBundle\Facebook\FacebookFactory;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ModuleFacebookLogin extends Module
{
    /**
     * Template.
     *
     * @var string
     */
    protected $strTemplate = 'mod_facebook_login';

    /**
     * Flash type.
     *
     * @var string
     */
    protected $strFlashType = 'contao.'.TL_MODE.'.error';

    /**
     * Display a wildcard in the back end.
     *
     * @return string
     */
    public function generate()
    {
        if (TL_MODE === 'BE') {
            $objTemplate = new BackendTemplate('be_wildcard');

            $objTemplate->wildcard = '### '.utf8_strtoupper($GLOBALS['TL_LANG']['FMD']['facebook_login'][0]).' ###';
            $objTemplate->title = $this->headline;
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id='.$this->id;

            return $objTemplate->parse();
        }

        // Return if a front end user is logged in or there is no valid Facebook APP config
        if (!\FacebookJSSDK::hasValidConfig()) {
            return '';
        }

        // Set the last page visited (see #8632)
        if (!$_POST && $this->redirectBack && ($strReferer = System::getReferer()) !== Environment::get('request')) {
            $_SESSION['LAST_PAGE_VISITED'] = $strReferer;
        }

        // Get the session
        $session = System::getContainer()->get('session');

        // Login
        if (Input::post('FORM_SUBMIT') === 'tl_login_'.$this->id) {
            // Get the login data to be retrieved
            $this->fbLoginData = deserialize($this->fbLoginData, true);

            // Prepare the facebook permissions
            $permissions = ['public_profile'];

            // Add email permission
            if (\in_array('email', $this->fbLoginData, true)) {
                $permissions[] = 'email';
            }

            // Get the custom permissions
            $permissions = \array_filter(\array_unique(\array_merge($permissions, StringUtil::splitCsv($this->fbLoginPerms))));

            // Auto login
            if (isset($_POST['autologin']) && $this->autologin) {
                $session->set('facebook_login_autologin', (bool) $_POST['autologin']);
            }

            // get the Facebook SDK
            $fb = FacebookFactory::create();

            $helper = $fb->getRedirectLoginHelper();
            $router = System::getContainer()->get('router');
            $callbackUrl = $router->generate('facebook_login.controller.callback', [], UrlGeneratorInterface::ABSOLUTE_URL);
            $loginUrl = $helper->getLoginUrl($callbackUrl, $permissions);

            // set some session variables
            global $objPage;
            $session->set('facebook_login_module', $this->id);
            $session->set('facebook_login_page', $objPage->id);

            Controller::redirect($loginUrl);
        }

        // Logout and redirect to the website root if the current page is protected
        if (Input::post('FORM_SUBMIT') === 'tl_logout_'.$this->id) {
            // Remove access token
            $session->remove('facebook_login_access_token');

            /* @var PageModel $objPage */
            global $objPage;

            $objMember = FrontendUser::getInstance();
            $strRedirect = Environment::get('request');

            // Redirect to last page visited
            if ($this->redirectBack && \strlen($_SESSION['LAST_PAGE_VISITED'])) {
                $strRedirect = $_SESSION['LAST_PAGE_VISITED'];
            }

            // Redirect home if the page is protected
            elseif ($objPage->protected) {
                $strRedirect = Environment::get('base');
            }

            // Logout and redirect
            if ($objMember->logout()) {
                Controller::redirect($strRedirect);
            }
        }

        return parent::generate();
    }

    /**
     * Generate the module.
     */
    protected function compile(): void
    {
        // Show logout form
        if (FE_USER_LOGGED_IN) {
            $objMember = FrontendUser::getInstance();

            $strName = \implode(' ', \array_filter([$objMember->firstname, $objMember->lastname])) ?: $objMember->username;

            $this->Template->logout = true;
            $this->Template->formId = 'tl_logout_'.$this->id;
            $this->Template->slabel = StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['logout']);
            $this->Template->loggedInAs = sprintf($GLOBALS['TL_LANG']['MSC']['loggedInAs'], $strName);
            $this->Template->action = ampersand(Environment::get('indexFreeRequest'));

            if ($objMember->lastLogin > 0) {
                /* @var PageModel $objPage */
                global $objPage;

                $this->Template->lastLogin = sprintf($GLOBALS['TL_LANG']['MSC']['lastLogin'][1], \Date::parse($objPage->datimFormat, $objMember->lastLogin));
            }

            return;
        }

        $session = System::getContainer()->get('session');
        $session->set('facebook_login_referrer', Environment::get('uri'));

        if ($session->isStarted()) {
            $flashBag = $session->getFlashBag();

            if ($flashBag->has($this->strFlashType)) {
                $this->Template->hasError = true;
                $this->Template->message = $flashBag->get($this->strFlashType)[0];
            }
        }

        $this->Template->action = ampersand(Environment::get('indexFreeRequest'));
        $this->Template->slabel = StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['facebookLogin']);
        $this->Template->value = StringUtil::specialchars(Input::post('username'));
        $this->Template->formId = 'tl_login_'.$this->id;
        $this->Template->autologin = ($this->autologin && Config::get('autologin') > 0);
        $this->Template->autoLabel = $GLOBALS['TL_LANG']['MSC']['autologin'];
    }
}
