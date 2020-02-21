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

use Contao\Config;
use Contao\Controller;
use Contao\Environment;
use Contao\FrontendUser;
use Contao\Input;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;

class ModuleFacebookLogin extends AbstractFacebookModule
{
    /**
     * Template.
     *
     * @var string
     */
    protected $strTemplate = 'mod_facebook_login';

    protected function handleSubmit() : void
    {
        // Login
        $this->handleLoginForm();

        // Logout and redirect to the website root if the current page is protected
        if (Input::post('FORM_SUBMIT') === 'tl_facebook_logout_'.$this->id) {
            // Get the session
            $session = System::getContainer()->get('session');

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
            $this->Template->formId = 'tl_facebook_logout_'.$this->id;
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
        $this->Template->formId = 'tl_facebook_login_'.$this->id;
        $this->Template->autologin = ($this->autologin && Config::get('autologin') > 0);
        $this->Template->autoLabel = $GLOBALS['TL_LANG']['MSC']['autologin'];
    }
}
