<?php

declare(strict_types=1);

/*
 * This file is part of the FacebookLogin Bundle.
 *
 * (c) inspiredminds
 *
 * @license LGPL-3.0-or-later
 */

namespace FacebookLoginBundle\Facebook;

/**
 * Factory class for the Facebook SDK.
 */
class FacebookFactory
{
    /**
     * Creates a Facebook object from the Facebook SDK.
     *
     * @return \Facebook\Facebook
     */
    public function create()
    {
        return new \Facebook\Facebook([
            'app_id' => \FacebookJSSDK::getAppId(),
            'app_secret' => \FacebookJSSDK::getAppSecret(),
            'default_graph_version' => \FacebookJSSDK::getAppVersion(),
        ]);
    }
}
