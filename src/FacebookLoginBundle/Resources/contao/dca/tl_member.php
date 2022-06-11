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

use Contao\CoreBundle\DataContainer\PaletteManipulator;

/**
 * Add palettes to tl_member
 */
PaletteManipulator::create()
	->addField('facebookId', 'username')
	->applyToSubpalette('login', 'tl_member')
;


/**
 * Add the "w50" class to the "username" field
 */
$GLOBALS['TL_DCA']['tl_member']['fields']['username']['eval']['tl_class'] = 'w50';


/**
 * Make the "password" field not mandatory
 */
if (TL_MODE == 'BE')
{
	$GLOBALS['TL_CONFIG']['minPasswordLength'] = 0;
	$GLOBALS['TL_DCA']['tl_member']['fields']['password']['eval']['minlength'] = 0;
	$GLOBALS['TL_DCA']['tl_member']['fields']['password']['eval']['mandatory'] = false;
}


/**
 * Add fields to tl_member
 */
$GLOBALS['TL_DCA']['tl_member']['fields']['facebookId'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_member']['facebookId'],
	'exclude'                 => true,
	'search'                  => true,
	'inputType'               => 'text',
	'eval'                    => array('unique'=>true, 'tl_class'=>'w50'),
	'sql'                     => "varchar(255) NOT NULL default ''"
);
