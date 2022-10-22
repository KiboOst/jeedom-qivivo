<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';

function qivivo_install() {
	config::save('functionality::cron5::enable', 0, 'qivivo');
	config::save('functionality::cron15::enable', 1, 'qivivo');
	config::save('pluginversion', 2.2, 'qivivo');
}

function qivivo_update() {
	//New v2 version:
	$pluginVersion = config::byKey('pluginversion', 'qivivo');
	if ($pluginVersion == '') {
		$pluginVersion = 1.9;
	}

	if ($pluginVersion < 2.0) {
		//new custom API for new Comap interface:
		$folderPath = dirname(__FILE__) . '/../../qivivo/exportedPrograms/';
		if (is_dir($folderPath)) unlink($folderPath);
		$eqs = eqLogic::byType('qivivo');
		foreach ($eqs as $eq) {
			$eq->remove();
		}
	}

	if ($pluginVersion < 2.2) {
		//new custom API with multi home:
		$currentProgram = config::byKey('currentProgram', 'qivivo');
		if (is_string($currentProgram)) {
			$currentProgram = array($currentProgram);
			config::save('currentProgram', $currentProgram, 'qivivo');
		}
		$isMultizone = config::byKey('isMultizone', 'qivivo');
		if (is_string($isMultizone)) {
			$isMultizone = array($isMultizone);
			config::save('isMultizone', $isMultizone, 'qivivo');
		}
		$programList = config::byKey('programList', 'qivivo');
		$newProgList = array($programList);
		config::save('programList', $newProgList, 'qivivo');

		$eqLogics = eqLogic::byType('qivivo');
		foreach ($eqLogics as $eqLogic) {
			$eqLogic->setConfiguration('houseId', 0);
			$eqLogic->save();
		}
	}

	//clean old stuff:
	config::remove('client_id', 'qivivo');
	config::remove('client_secret', 'qivivo');
	config::remove('username', 'qivivo');
	config::remove('password', 'qivivo');

	//resave eqs for new cmd:
	try
	{
		$eqs = eqLogic::byType('qivivo');
		foreach ($eqs as $eq)
		{
			$eq->save();
		}
	}
	catch (Exception $e)
	{
		$e = print_r($e, 1);
		log::add('qivivo', 'error', 'qivivo_update ERROR: '.$e);
	}

	config::save('pluginversion', 2.2, 'qivivo');
}

function qivivo_remove() {

}

?>
