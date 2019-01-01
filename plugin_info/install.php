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

}

function qivivo_update() {
	$cron = cron::byClassAndFunction('qivivo', 'pull');
	if (is_object($cron)) {
		$cron->remove();
	}

	if (config::byKey('client_id', 'qivivo') == '') {
		config::save('client_id', $eqLogic->getConfiguration('client_id'), 'qivivo');
		config::save('client_secret', $eqLogic->getConfiguration('client_secret'), 'qivivo');
		config::save('username', $eqLogic->getConfiguration('username'), 'qivivo');
		config::save('password', $eqLogic->getConfiguration('password'), 'qivivo');
	}

	$plugin = plugin::byId('qivivo');
	$eqLogics = eqLogic::byType($plugin->getId());
	foreach ($eqLogics as $eqLogic)
	{
		updateLogicalId($eqLogic, 'LastMsg', 'last_communication');
		updateLogicalId($eqLogic, 'Firmware', 'firmware_version');

		//module:
		updateLogicalId($eqLogic, 'Ordre', 'module_order');
		updateLogicalId($eqLogic, 'OrdreNum', 'order_num');
		updateLogicalId($eqLogic, 'SetMode', 'set_order');
		updateLogicalId($eqLogic, 'SetProgram', 'set_program');

		//thermostat:
		updateLogicalId($eqLogic, 'Consigne', 'temperature_order');
		updateLogicalId($eqLogic, 'Chauffe', 'heating');
		updateLogicalId($eqLogic, 'Temperature', 'temperature');
		updateLogicalId($eqLogic, 'Humidité', 'humidity');
		updateLogicalId($eqLogic, 'Presence', 'presence');
		updateLogicalId($eqLogic, 'DernierePresence', 'last_presence');
		updateLogicalId($eqLogic, 'IncOne', 'set_plus_one');
		updateLogicalId($eqLogic, 'DecOne', 'set_minus_one');
		updateLogicalId($eqLogic, 'SetDuréeOrdre ', 'set_time_order');
		updateLogicalId($eqLogic, 'SetTemperature', 'set_temperature_order');
		updateLogicalId($eqLogic, 'Annule_Ordre_Temp', 'cancel_time_order');
		updateLogicalId($eqLogic, 'SetTempAbsence', 'set_absence_temperature');
		updateLogicalId($eqLogic, 'SetTempNuit', 'set_night_temperature');
		updateLogicalId($eqLogic, 'SetTempHorsGel', 'set_frost_temperature');
		updateLogicalId($eqLogic, 'SetTempPres1', 'set_presence_temperature_1');
		updateLogicalId($eqLogic, 'SetTempPres2', 'set_presence_temperature_2');
		updateLogicalId($eqLogic, 'SetTempPres3', 'set_presence_temperature_3');
		updateLogicalId($eqLogic, 'SetTempPres4', 'set_presence_temperature_4');
	}

}

function updateLogicalId($eqLogic, $from, $to) {
	$qivivoCmd = $eqLogic->getCmd(null, $from);
	if (is_object($qivivoCmd)) {
		$qivivoCmd->setLogicalId($to);
		$qivivoCmd->save();
	}
}


function qivivo_remove() {

}

?>
