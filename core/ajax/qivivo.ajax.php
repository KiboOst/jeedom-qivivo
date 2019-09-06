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

try {
    require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
    include_file('core', 'authentification', 'php');

    if (!isConnect('admin')) {
        throw new Exception(__('401 - Accès non autorisé', __FILE__));
    }

    ajax::init();

	if (init('action') == 'syncWithQivivo') {
		qivivo::syncWithQivivo();
		qivivo::refreshQivivoInfos();
		ajax::success();
	}

    if (init('action') == 'getTypeAndValues') {
        try
        {
            $_uuid = init('_uuid');
            $plugin = plugin::byId('qivivo');
            $eqLogics = eqLogic::byType($plugin->getId());
            foreach ($eqLogics as $eqLogic)
            {
                $uuid = $eqLogic->getConfiguration('uuid', '');
                if ($uuid == $_uuid)
                {
                    $type = $eqLogic->getConfiguration('type', '');
                    $result = array('type' => $type);
                    if (in_array($type, array('Module Chauffage', 'Thermostat', 'Passerelle')))
                    {
                        $firmware_version = $eqLogic->getCmd(null, 'firmware_version')->execCmd();
                        $last_communication = $eqLogic->getCmd(null, 'last_communication')->execCmd();
                        $result['firmware_version'] = $firmware_version;
                        $result['last_communication'] = $last_communication;
                    }
                    if ($type == 'Module Chauffage')
                    {
                        $module_order = $eqLogic->getCmd(null, 'module_order')->execCmd();
                        $result['module_order'] = $module_order;
                    }
                    if ($type == 'Thermostat')
                    {
                        $consigne = $eqLogic->getCmd(null, 'temperature_order')->execCmd();
                        $result['temperature_order'] = $consigne;
                        $dureeordre = $eqLogic->getCmd(null, 'duree_temp')->execCmd();
                        $result['dureeordre'] = $dureeordre;

                        $paramTempAbsence = $eqLogic->getCmd(null, 'absence_temperature')->execCmd();
                        $result['paramTempAbsence'] = $paramTempAbsence;
                        $paramTempHG = $eqLogic->getCmd(null, 'frost_protection_temperature')->execCmd();
                        $result['paramTempHG'] = $paramTempHG;
                        $paramTempNuit = $eqLogic->getCmd(null, 'night_temperature')->execCmd();
                        $result['paramTempNuit'] = $paramTempNuit;
                        $paramTempPres1 = $eqLogic->getCmd(null, 'presence_temperature_1')->execCmd();
                        $result['paramTempPres1'] = $paramTempPres1;
                        $paramTempPres2 = $eqLogic->getCmd(null, 'presence_temperature_2')->execCmd();
                        $result['paramTempPres2'] = $paramTempPres2;
                        $paramTempPres3 = $eqLogic->getCmd(null, 'presence_temperature_3')->execCmd();
                        $result['paramTempPres3'] = $paramTempPres3;
                        $paramTempPres4 = $eqLogic->getCmd(null, 'presence_temperature_4')->execCmd();
                        $result['paramTempPres4'] = $paramTempPres4;
                        $battery = $eqLogic->getCmd(null, 'battery')->execCmd();
                        $result['battery'] = $battery;
                    }
                    ajax::success($result);
                }
            }
        } catch (Exception $e) {
            log::add('qivivo', 'debug', 'ajax getTypeAndValues ERROR'.print_r($e, true));
            return '';
        }
    }

    if (init('action') == 'getActionsOnError') {
        $actionsOnError = config::byKey('actionsOnError', 'qivivo');
        //log::add('qivivo', 'debug', 'ajax getActionsOnError: '.print_r($actionsOnError, 1));
        for ($i=0; $i<count($actionsOnError); $i++) {
            $cmdId = $actionsOnError[$i]['cmd'];
            $cmd = cmd::byId($cmdId);
            $cmdName = '#'.$cmd->getHumanName().'#';
            $actionsOnError[$i]['cmd'] = $cmdName;
        }
        ajax::success($actionsOnError);
    }

    if (init('action') == 'saveActionsOnError') {
        $actionsOnError = init('actionsOnError');
        //log::add('qivivo', 'debug', 'ajax saveActionsOnError: '.print_r($actionsOnError, 1));

        $actionsOnError = json_decode($actionsOnError, true);
        for ($i=0; $i<count($actionsOnError); $i++) {
            $cmdName = $actionsOnError[$i]['cmd'];
            $cmd = cmd::byString($cmdName);
            $cmdId = $cmd->getId();
            $actionsOnError[$i]['cmd'] = $cmdId;
        }
        $actionsOnError = json_encode($actionsOnError);
        config::save('actionsOnError', $actionsOnError, 'qivivo');
        ajax::success();
    }

    if (init('action') == 'exportProgram') {
        qivivo::exportProgram(init('name'), init('program'));
        ajax::success();
    }

    if (init('action') == 'deleteProgramFile') {
        $folderPath = dirname(__FILE__) . '/../../exportedPrograms/';
        $fileName = init('fileName');
        log::add('qivivo', 'debug', 'ajax deleteProgramFile: '.$folderPath.$fileName);
        @unlink($folderPath.$fileName);
        ajax::success();
    }

    throw new Exception(__('Aucune méthode correspondante à : ', __FILE__) . init('action'));
    /*     * *********Catch exeption*************** */
} catch (Exception $e) {
    ajax::error(displayExeption($e), $e->getCode());
}

