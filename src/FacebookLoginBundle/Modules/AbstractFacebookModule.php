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

use Contao\Controller;
use Contao\Input;
use Contao\Module;
use Contao\StringUtil;
use Contao\System;
use FacebookLoginBundle\Facebook\FacebookFactory;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use function deserialize;

abstract class AbstractFacebookModule extends Module
{
    protected function handleLoginForm() : void
    {
        if (Input::post('FORM_SUBMIT') !== 'tl_facebook_login_'.$this->id) {
            return;
        }

        $session = System::getContainer()->get('session');

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
}
