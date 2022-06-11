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
use Contao\StringUtil;

// Copy existing "login" palette and adjust
$GLOBALS['TL_DCA']['tl_module']['palettes']['facebook_login'] = $GLOBALS['TL_DCA']['tl_module']['palettes']['login'];

PaletteManipulator::create()
    ->addLegend('account_legend', 'redirect_legend', PaletteManipulator::POSITION_BEFORE)
    ->addField('reg_groups', 'account_legend', PaletteManipulator::POSITION_APPEND)
    ->addField('fbLoginData', 'config_legend', PaletteManipulator::POSITION_APPEND)
    ->addField('fbLoginPerms', 'config_legend', PaletteManipulator::POSITION_APPEND)
    ->removeField('autologin')
    ->removeField('cols')
    ->applyToPalette('facebook_login', 'tl_module')
;

// Copy existing "facebook_login" palette and adjust
$GLOBALS['TL_DCA']['tl_module']['palettes']['facebook_connect'] = $GLOBALS['TL_DCA']['tl_module']['palettes']['facebook_login'];

PaletteManipulator::create()
    ->removeField('reg_groups')
    ->applyToPalette('facebook_connect', 'tl_module')
;

$GLOBALS['TL_DCA']['tl_module']['fields']['fbLoginData'] = array
(
    'label'     => &$GLOBALS['TL_LANG']['tl_module']['fbLoginData'],
    'exclude'   => true,
    'inputType' => 'checkbox',
    'options'   => ['firstname', 'lastname', 'gender', 'email', 'locale'],
    'reference' => &$GLOBALS['TL_LANG']['tl_module']['fbLoginDataOptions'],
    'eval'      => ['multiple' => true],
    'sql'       => 'blob NULL'
);

$GLOBALS['TL_DCA']['tl_module']['fields']['fbLoginPerms'] = array
(
    'label'         => &$GLOBALS['TL_LANG']['tl_module']['fbLoginPerms'],
    'exclude'       => true,
    'inputType'     => 'text',
    'save_callback' => [function($varValue, $dc) { return implode(',', StringUtil::splitCsv($varValue)); }],
    'eval'          => ['maxlength'=>255, 'tl_class'=>'w50', 'placeholder'=>'public_profile,email'],
    'sql'           => "varchar(255) NOT NULL default ''"
);
