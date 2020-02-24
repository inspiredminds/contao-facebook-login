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
use Contao\MemberModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Contao\Versions;
use Contao\Widget;
use Facebook\Exceptions\FacebookSDKException;
use FacebookLoginBundle\Facebook\FacebookFactory;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use function ampersand;
use function sprintf;
use function time;
use const TL_ERROR;

class ModuleFacebookConnect extends AbstractFacebookModule
{
    protected $strTemplate = 'mod_facebook_connect';

    /** @var Widget[] */
    protected $widgets = [];

    protected function handleSubmit() : void
    {
        $this->handleLoginForm();
        $this->handleDisconnectForm();
    }

    protected function compile(): void
    {
        /** @var AuthenticationException|null $exception */
        $container = self::getContainer();
        $authorizationChecker = $container->get('security.authorization_checker');

        if (!$authorizationChecker->isGranted('ROLE_MEMBER')) {
            $connected = false;
        } else {
            $objMember = FrontendUser::getInstance();
            $connected = $objMember->facebookId !== '';
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

        $strName = \implode(' ', \array_filter([$objMember->firstname, $objMember->lastname])) ?: $objMember->username;
        $this->Template->loggedInAs = sprintf($GLOBALS['TL_LANG']['MSC']['loggedInAs'], $strName);
        $this->Template->action = ampersand(Environment::get('indexFreeRequest'));

        if ($connected) {
            $this->Template->disconnect = true;
            $this->Template->formId = 'tl_facebook_disconnect_'.$this->id;
            $this->Template->slabel = StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['facebookDisconnect']);

            if ($objMember->lastLogin > 0) {
                /* @var PageModel $objPage */
                global $objPage;

                $this->Template->lastLogin = sprintf($GLOBALS['TL_LANG']['MSC']['lastLogin'][1], \Date::parse($objPage->datimFormat, $objMember->lastLogin));
            }

            return;
        }

        $this->Template->widgets = $this->widgets;
        $this->Template->slabel = StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['facebookLogin']);
        $this->Template->value = StringUtil::specialchars(Input::post('username'));
        $this->Template->formId = 'tl_facebook_login_'.$this->id;
        $this->Template->autologin = ($this->autologin && Config::get('autologin') > 0);
        $this->Template->autoLabel = $GLOBALS['TL_LANG']['MSC']['autologin'];
    }

    private function handleDisconnectForm() : void
    {
        if (Input::post('FORM_SUBMIT') !== 'tl_facebook_disconnect_'.$this->id) {
            return;
        }

        $objMember = FrontendUser::getInstance();
        if ($objMember->facebookId === '') {
            return;
        }

        $this->createDisconnectWidgets($objMember);

        $doNotSubmit = false;
        foreach ($this->widgets as $widget) {
            $widget->validate();

            if ($widget->hasErrors()) {
                $doNotSubmit = true;
            }
        }

        if ($doNotSubmit) {
            return;
        }

        // Get the session
        $session  = System::getContainer()->get('session');
        $endpoint = sprintf('%s/permissions', $objMember->facebookId);
        $facebook = (new FacebookFactory())->create();

        // Ignore if it was successful. Assume that the user revoked access manually
        try {
            $facebook->delete($endpoint, [], $session->get('facebook_login_access_token'));
        } catch (FacebookSDKException $e) {
            System::log(
                sprintf(
                    'Revoke remote facebook connection for user "%s" failed with message "%s (%s)"',
                    $objMember->username,
                    $objMember->id,
                    $e->getMessage()
                ),
                __METHOD__,
                TL_ERROR
            );
        }

        // Remove access token
        $session->remove('facebook_login_access_token');

        // Initialize the versioning (see #8301)
        $objVersions = new Versions('tl_member', $objMember->id);
        $objVersions->setUsername($objMember->username);
        $objVersions->setUserId(0);
        $objVersions->setEditUrl('contao?do=member&act=edit&id=%s&rt=1');
        $objVersions->initialize();

        $memberModel = MemberModel::findByPk($objMember->id);
        $memberModel->tstamp = time();
        $memberModel->facebookId = '';
        $memberModel->save();

        $objVersions->create(true);

        // TODO: trigger an event

        $strRedirect = Environment::get('request');

        // Redirect to last page visited
        if ($this->redirectBack && \strlen($_SESSION['LAST_PAGE_VISITED'])) {
            $strRedirect = $_SESSION['LAST_PAGE_VISITED'];
        }

        Controller::redirect($strRedirect);
    }

    private function createDisconnectWidgets(FrontendUser $objMember): void
    {
        if ($objMember->password === '') {
            Controller::loadDataContainer('tl_member');

            /** @var Widget $strClass */
            $strClass = $GLOBALS['TL_FFL']['password'];

            $passwordWidget = new $strClass(
                $strClass::getAttributesFromDca($GLOBALS['TL_DCA']['tl_member']['fields']['password'], 'password')
            );

            $passwordWidget->rowClass = 'row_0 row_first even';
            $passwordWidget->rowClassConfirm = 'row_1 odd';

            $this->widgets['password'] = $passwordWidget;

            // Captcha widget
            if (!$this->disableCaptcha)
            {
                /** @var Widget $strClass */
                $strClass = $GLOBALS['TL_FFL']['captcha'];

                $this->widgets['captcha'] = new $strClass($strClass::getAttributesFromDca(
                    [
                        'name' => 'lost_password',
                        'label' => $GLOBALS['TL_LANG']['MSC']['securityQuestion'],
                        'inputType' => 'captcha',
                        'eval' => ['mandatory' =>true]
                    ],
                    'captcha'
                ));
            }
        }
    }
}
