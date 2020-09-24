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

	config::save('pluginversion', 2.1, 'qivivo');
}

function qivivo_remove() {

}

?>
