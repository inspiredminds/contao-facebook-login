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

use Contao\StringUtil;


$GLOBALS['TL_DCA']['tl_module']['palettes']['facebook_login'] = $GLOBALS['TL_DCA']['tl_module']['palettes']['login'];
$GLOBALS['TL_DCA']['tl_module']['palettes']['facebook_login'] = str_replace('{redirect_legend', '{account_legend},reg_groups;{redirect_legend', $GLOBALS['TL_DCA']['tl_module']['palettes']['facebook_login']);
$GLOBALS['TL_DCA']['tl_module']['palettes']['facebook_login'] = str_replace(',autologin', ',fbLoginData,fbLoginPerms', $GLOBALS['TL_DCA']['tl_module']['palettes']['facebook_login']);
$GLOBALS['TL_DCA']['tl_module']['palettes']['facebook_login'] = str_replace(',cols,', ',', $GLOBALS['TL_DCA']['tl_module']['palettes']['facebook_login']);


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
