<?php

/**
 * This file is part of the FacebookLogin Bundle.
 *
 * (c) inspiredminds <https://github.com/inspiredminds>
 *
 * @package   FacebookLoginBundle
 * @author    Fritz Michael Gschwantner <https://github.com/fritzmg>
 * @license   LGPL-3.0-or-later
 * @copyright inspiredminds 2017
 */


/**
 * Front end modules
 */
$GLOBALS['FE_MOD']['user']['facebook_login'] = 'FacebookLoginBundle\Modules\ModuleFacebookLogin';


/**
 * Remove #_=_
 */
if (TL_MODE == 'FE')
{
	$GLOBALS['TL_HEAD'][] = "<script>if (window.location.hash == '#_=_'){ history.replaceState ? history.replaceState(null, null, window.location.href.split('#')[0]) : window.location.hash = '';}</script>";
}
