<?php
/* <one line to give the program's name and a brief idea of what it does.>
 * Copyright (C) <year>  <name of author>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * 	\file		core/boxes/mybox.php
 * 	\ingroup	personnalworkingboard
 * 	\brief		This file is a sample box definition file
 * 				Put some comments here
 */
include_once DOL_DOCUMENT_ROOT . '/core/boxes/modules_boxes.php';


define('INC_FROM_DOLIBARR', true);
dol_include_once('/personnalworkingboard/config.php');
dol_include_once('/personnalworkingboard/class/personnalworkingboard.class.php');


/**
 * Class to manage the box
 */
class box_personnalworkingboard_filtered extends ModeleBoxes
{

    public $boxcode = "box_personnalworkingboard_filtered";
    public $boximg = "personnalworkingboard@personnalworkingboard";
    public $boxlabel;
    public $depends = array("personnalworkingboard");
    public $db;
    public $param;
    public $info_box_head = array();
    public $info_box_contents = array();

    /**
     * Constructor
     */
    public function __construct(DoliDB $db, $param = '')
    {
        global $langs;
        $langs->load('boxes');
        $langs->load('personnalworkingboard@personnalworkingboard');
		$langs->load('commercial');
		$langs->load('bills');
		$langs->load('orders');
		$langs->load('contracts');

		parent::__construct($db, $param);
		
        $this->boxlabel = $langs->transnoentitiesnoconv("PersonnalWorkingBoardFilteredWidget");
		
		$this->param = $param;
    }

    /**
     * Load data into info_box_contents array to show array later.
     *
     * 	@param		int		$max		Maximum number of records to load
     * 	@return		void
     */
    public function loadBox()
    {
        global $langs;

        $text = $langs->trans("PersonnalWorkingBoardFilteredBoxDescription");
        $this->info_box_head = array(
            'text' => $text,
            'limit' => dol_strlen($text)
        );
		
		$i = 0;
		
		$TPersonnalWorkingBoard = new TPersonnalWorkingBoard;
		$output = $TPersonnalWorkingBoard->getContentDolibarrStyle('filtered');
		
		$meto_output = $TPersonnalWorkingBoard->getMeteoContent();
		if (!empty($meto_output))
		{
			$this->info_box_contents[$i][0] = array(
				'tr' => 'class="nohover"'
				,'td' => 'class="nohover hideonsmartphone center valignmiddle"'
				,'text' => $meto_output
			);
			$i++;
		}
		
        $this->info_box_contents[$i][0] = array(
			'tr' => 'class="nohover"'
			,'td' => 'class="tdboxstats nohover flexcontainer centpercent"'
            ,'text' => $output
		);
    }

    /**
     * 	Method to show box
     *
     * 	@param	array	$head       Array with properties of box title
     * 	@param  array	$contents   Array with properties of box lines
     * 	@return	void
     */
    public function showBox($head = null, $contents = null, $nooutput=0)
    {
        parent::showBox($this->info_box_head, $this->info_box_contents, $nooutput);
    }
}