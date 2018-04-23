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


namespace FacebookLoginBundle\Facebook;


/**
 * Factory class for the Facebook SDK
 *
 * @author Fritz Michael Gschwantner <https://github.com/fritzmg>
 */
class FacebookFactory
{
    /**
     * Creates a Facebook object from the Facebook SDK
     *
     * @return \Facebook\Facebook
     */
    public static function create()
    {
        return new \Facebook\Facebook([
            'app_id' => \FacebookJSSDK::getAppId(),
            'app_secret' => \FacebookJSSDK::getAppSecret(),
            'default_graph_version' => \FacebookJSSDK::getAppVersion(),
        ]);
    }
}
