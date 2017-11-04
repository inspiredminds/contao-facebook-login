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


$GLOBALS['TL_DCA']['tl_module']['palettes']['facebook_login'] = $GLOBALS['TL_DCA']['tl_module']['palettes']['login'];
$GLOBALS['TL_DCA']['tl_module']['palettes']['facebook_login'] = str_replace('{redirect_legend', '{account_legend},reg_groups;', $GLOBALS['TL_DCA']['tl_module']['palettes']['facebook_login']);
$GLOBALS['TL_DCA']['tl_module']['palettes']['facebook_login'] = str_replace(',cols,', ',', $GLOBALS['TL_DCA']['tl_module']['palettes']['facebook_login']);
