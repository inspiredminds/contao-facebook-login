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
use Contao\Controller;
use Contao\Environment;
use Contao\Input;
use Contao\Module;
use Contao\StringUtil;
use Contao\System;
use FacebookLoginBundle\Facebook\FacebookFactory;
use Patchwork\Utf8;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use const TL_MODE;

abstract class AbstractFacebookModule extends Module
{
    /**
     * Flash type.
     *
     * @var string
     */
    protected $strFlashType = 'contao.'.TL_MODE.'.error';

    public function generate(): string
    {
        if (TL_MODE === 'BE') {
            $objTemplate = new BackendTemplate('be_wildcard');

            $objTemplate->wildcard = '### '.Utf8::strtoupper($GLOBALS['TL_LANG']['FMD'][$this->type][0]).' ###';
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

        $this->handleSubmit();

        return parent::generate();
    }

    protected function handleLoginForm(): void
    {
        if (Input::post('FORM_SUBMIT') !== 'tl_facebook_login_'.$this->id) {
            return;
        }

        $session = System::getContainer()->get('session');

        // Get the login data to be retrieved
        $this->fbLoginData = StringUtil::deserialize($this->fbLoginData, true);

        // Prepare the facebook permissions
        $permissions = ['public_profile'];

        // Add email permission
        if (\in_array('email', $this->fbLoginData, true)) {
            $permissions[] = 'email';
        }

        // Get the custom permissions
        $permissions = array_filter(array_unique(array_merge($permissions, StringUtil::splitCsv($this->fbLoginPerms))));

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

    abstract protected function handleSubmit(): void;
}
