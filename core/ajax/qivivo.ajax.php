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
                        $firmware = $eqLogic->getCmd(null, 'Firmware')->execCmd();
                        $lastmsg = $eqLogic->getCmd(null, 'LastMsg')->execCmd();
                        $result['firmware'] = $firmware;
                        $result['lastmsg'] = $lastmsg;
                    }
                    if ($type == 'Module Chauffage')
                    {
                        $ordre = $eqLogic->getCmd(null, 'Ordre')->execCmd();
                        $result['ordre'] = $ordre;
                    }
                    if ($type == 'Thermostat')
                    {
                        $consigne = $eqLogic->getCmd(null, 'Consigne')->execCmd();
                        $result['consigne'] = $consigne;
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
        ajax::success($actionsOnError);
    }

    if (init('action') == 'saveActionsOnError') {
        $actionsOnError = init('actionsOnError');
        //log::add('qivivo', 'debug', 'ajax saveActionsOnError: '.print_r($actionsOnError, 1));
        config::save('actionsOnError', $actionsOnError, 'qivivo');
        ajax::success();
    }

    throw new Exception(__('Aucune méthode correspondante à : ', __FILE__) . init('action'));
    /*     * *********Catch exeption*************** */
} catch (Exception $e) {
    ajax::error(displayExeption($e), $e->getCode());
}

