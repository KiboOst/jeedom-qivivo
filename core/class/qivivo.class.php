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

/* * ***************************Includes********************************* */
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
if (!class_exists('splQivivoAPI')) {
    require_once dirname(__FILE__) . '/../../3rdparty/splQivivoAPI.php';
}
if (!class_exists('qivivoAPI')) {
    require_once dirname(__FILE__) . '/../../3rdparty/qivivoAPI.php';
}

class qivivo extends eqLogic {
    public static function getAPI($typeCmd='info', $msg='') {
        $client_id = config::byKey('client_id', 'qivivo');
        $client_secret = config::byKey('client_secret', 'qivivo');
        $_qivivo = new splQivivoAPI($client_id, $client_secret);
        if (isset($_qivivo->error))
        {
            if ($typeCmd == 'action')
            {
                $_apiError = $_qivivo->error;
                if ($msg != '') $_apiError .= ' : '.$msg;
                log::add('qivivo', 'error', 'API error: '.$_apiError);
            }
            return False;
        }
        return $_qivivo;
    }

    public static function getCustomAPI($typeCmd='info', $msg='') {
        $login = config::byKey('login', 'qivivo');
        $pass = config::byKey('pass', 'qivivo');
        $_customQivivo = new qivivoAPI($login, $pass);
        if (isset($_customQivivo->error))
        {
            if ($typeCmd == 'action')
            {
                $_apiError = $_customQivivo->error;
                if ($msg != '') $_apiError .= ' : '.$msg;
                log::add('qivivo', 'error', 'custom API error: '.$_apiError);
            }
            return False;
        }
        return $_customQivivo;
    }

    public static function syncWithQivivo() {
        $_qivivo = qivivo::getAPI();
        if ($_qivivo == False)
        {
            return;
        }
        $devices = $_qivivo->getDevices();

        $_fullQivivo = qivivo::getCustomAPI();
        if ($_fullQivivo == False)
        {
            return;
        }
        $fullModules = $_fullQivivo->_fullDatas['multizone']['wirelessModules'];
        $fullCurrentPrograms = $_fullQivivo->getCurrentProgram();

        foreach ($devices as $device)
        {
            $eqLogic = eqLogic::byLogicalId($device['uuid'], 'qivivo');
            if (!is_object($eqLogic))
            {
                $eqLogic = new qivivo();
                $eqLogic->setEqType_name('qivivo');
                $eqLogic->setIsVisible(1);
                $eqLogic->setIsEnable(1);
            }

            $type = $device['type'];
            if ($type == 'thermostat')
            {
                $type = 'Thermostat';
                $eqLogic->setName($type);
            }
            if ($type == 'wireless-module')
            {
                $type = 'Module Chauffage';

                //need to correlate API serial which is same as customAPI max_adresse:
                $moduleInfos = $_qivivo->getModuleInfos($device['uuid']);
                $serial = $moduleInfos['serial'];
                $eqLogic->setConfiguration('zone_name', -1);
                foreach ($fullModules as $fullModule)
                {
                    $mac_address = $fullModule['mac_address'];
                    if ($serial == $mac_address)
                    {
                        $eqLogic->setConfiguration('zone_name', $fullModule['zone_name']);
                        $eqLogic->setName('Zone '.$fullModule['zone_name']);
                        $program_name = $fullCurrentPrograms['result'][$fullModule['zone_name']];
                        $eqLogic->setConfiguration('program_name', $program_name);
                        break;
                    }
                }

                $moduleOrder = $_qivivo->getModuleLastOrder($device['uuid']);
                $order = $moduleOrder['current_pilot_wire_order'];
                if ($order == 'monozone')
                {
                    $eqLogic->setConfiguration('isModuleThermostat', 1);
                    $eqLogic->setName('Zone Thermostat');
                }
                else
                {
                    $eqLogic->setConfiguration('isModuleThermostat', 0);
                }
            }
            if ($type == 'gateway')
            {
                $type = 'Passerelle';
                $eqLogic->setIsVisible(0);
                $eqLogic->setName($type);
            }

            $eqLogic->setConfiguration('uuid', $device['uuid']);
            $eqLogic->setConfiguration('type', $type);
            $eqLogic->setCategory('heating', 1);
            $eqLogic->setLogicalId($device['uuid']);
            $eqLogic->save();
        }
    }

    public static function refreshQivivoInfos() {
        try
        {
            log::add('qivivo', 'debug', '___refreshQivivoInfos starting');
            $_qivivo = qivivo::getAPI();
            if ($_qivivo == False) return;

            $devices = $_qivivo->getDevices();
            log::add('qivivo', 'debug', 'devices: '.print_r($devices, true));
            foreach ($devices as $device)
            {
                $_type = $device['type'];
                $_uuid = $device['uuid'];
                log::add('qivivo', 'debug', '________type: '.$_type.' uuid: '.$_uuid);
                $eqLogic = eqLogic::byLogicalId($_uuid, 'qivivo');
                if (!is_object($eqLogic)) continue;

                if ($_type == 'gateway')
                {
                    $gatewayInfos = $_qivivo->getGatewayInfos();
                    log::add('qivivo', 'debug', 'getGatewayInfos: '.print_r($gatewayInfos, true));
                    $firmware = $gatewayInfos['softwareVersion'];
                    $eqLogic->checkAndUpdateCmd('Firmware', $firmware);

                    $lastMsg = $gatewayInfos['lastCommunicationDate'];
                    $lastMsg = date("d-m-Y H:i", strtotime($lastMsg));
                    $eqLogic->checkAndUpdateCmd('LastMsg', $lastMsg);
                }

                if ($_type == 'thermostat')
                {
                    $eqLogic->getCmd(null, 'duree_temp')->event(120);

                    $thermostatInfos = $_qivivo->getThermostatInfos();
                    log::add('qivivo', 'debug', 'getThermostatInfos: '.print_r($thermostatInfos, true));
                    $lastMsg = $thermostatInfos['lastCommunicationDate'];
                    $lastMsg = date("d-m-Y H:i", strtotime($lastMsg));
                    $eqLogic->checkAndUpdateCmd('LastMsg', $lastMsg);
                    $firmware = $thermostatInfos['softwareVersion'];
                    $eqLogic->checkAndUpdateCmd('Firmware', $firmware);

                    $thermostatHumidity = $_qivivo->getThermostatHumidity();
                    log::add('qivivo', 'debug', 'getThermostatHumidity: '.print_r($thermostatHumidity, true));
                    $humidity = $thermostatHumidity['humidity'];
                    $eqLogic->checkAndUpdateCmd('Humidité', $humidity);

                    $thermostatPresence = $_qivivo->getThermostatPresence();
                    log::add('qivivo', 'debug', 'getThermostatPresence: '.print_r($thermostatPresence, true));
                    $Pres = $thermostatPresence['presence_detected'];
                    $presence = 0;
                    if ($Pres) $presence = 1;
                    $eqLogic->checkAndUpdateCmd('Presence', $presence);

                    $lastPresence = $_qivivo->getLastPresence();
                    log::add('qivivo', 'debug', 'getLastPresence: '.print_r($lastPresence, true));
                    $lastP = $lastPresence['last_presence_recorded_time'];
                    $lastP = date("d-m-Y H:i", strtotime($lastP));
                    $eqLogic->checkAndUpdateCmd('DernierePresence', $lastP);

                    $thermostatTemperature = $_qivivo->getThermostatTemperature();
                    log::add('qivivo', 'debug', 'getThermostatTemperature: '.print_r($thermostatTemperature, true));
                    $order = $thermostatTemperature['current_temperature_order'];
                    $temp = $thermostatTemperature['temperature'];
                    $eqLogic->checkAndUpdateCmd('Consigne', (round($order * 2)/2));
                    $eqLogic->checkAndUpdateCmd('Temperature', $temp);

                    $heating = 0;
                    if ($temp < $order) $heating = $order;
                    $eqLogic->checkAndUpdateCmd('Chauffe', $heating);

                    $settings = $_qivivo->getSettings();
                    log::add('qivivo', 'debug', 'getSettings: '.print_r($settings, true));
                    $eqLogic->getCmd(null, 'frost_protection_temperature')->event($settings['settings']['frost_protection_temperature']);
                    $eqLogic->getCmd(null, 'absence_temperature')->event($settings['settings']['absence_temperature']);
                    $eqLogic->getCmd(null, 'night_temperature')->event($settings['settings']['night_temperature']);
                    $eqLogic->getCmd(null, 'presence_temperature_1')->event($settings['settings']['presence_temperature_1']);
                    $eqLogic->getCmd(null, 'presence_temperature_2')->event($settings['settings']['presence_temperature_2']);
                    $eqLogic->getCmd(null, 'presence_temperature_3')->event($settings['settings']['presence_temperature_3']);
                    $eqLogic->getCmd(null, 'presence_temperature_4')->event($settings['settings']['presence_temperature_4']);
                }

                if ($_type == 'wireless-module')
                {
                    $moduleOrder = $_qivivo->getModuleLastOrder($_uuid);
                    log::add('qivivo', 'debug', 'getModuleLastOrder: '.print_r($moduleOrder, true));
                    $moduleInfos = $_qivivo->getModuleInfos($_uuid);
                    log::add('qivivo', 'debug', 'getModuleInfos: '.print_r($moduleInfos, true));

                    $firmware = $moduleInfos['softwareVersion'];
                    $eqLogic->checkAndUpdateCmd('Firmware', $firmware);

                    $lastMsg = $moduleInfos['lastCommunicationDate'];
                    $lastMsg = date("d-m-Y H:i", strtotime($lastMsg));
                    $eqLogic->checkAndUpdateCmd('LastMsg', $lastMsg);

                    $order = $moduleOrder['current_pilot_wire_order'];
                    $ordernum = 0;
                    if ($order == 'off')
                    {
                        $ordernum = 1;
                        $order = 'Arrêt';
                    }
                    if ($order == 'frost')
                    {
                        $ordernum = 2;
                        $order = 'Hors-Gel';
                    }
                    if ($order == 'eco')
                    {
                        $ordernum = 3;
                        $order = 'Eco';
                    }
                    if ($order == 'comfort_minus_two')
                    {
                        $ordernum = 4;
                        $order = 'Confort -2';
                    }
                    if ($order == 'comfort_minus_one')
                    {
                        $ordernum = 5;
                        $order = 'Confort -1';
                    }
                    if ($order == 'comfort')
                    {
                        $ordernum = 6;
                        $order = 'Confort';
                    }
                    if ($order == 'monozone') $eqLogic->setConfiguration('isModuleThermostat', 1);
                    else $eqLogic->setConfiguration('isModuleThermostat', 0);

                    $eqLogic->checkAndUpdateCmd('Ordre', $order);
                    $eqLogic->checkAndUpdateCmd('OrdreNum', $ordernum);
                }
                $eqLogic->save();
                $eqLogic->refreshWidget();
            }
            log::add('qivivo', 'debug', '___refreshQivivoInfos ending');
        } catch (Exception $e) {
            log::add('qivivo', 'warning', '___refreshQivivoInfos Exception: '.$e->getMessage());
            return;
        }
    }

    public static function cron5($_eqlogic_id = null) {
        log::add('qivivo', 'debug', '___cron5()');
        qivivo::refreshQivivoInfos();
    }

    public static function cron15($_eqlogic_id = null) {
        log::add('qivivo', 'debug', '___cron15()');
        qivivo::refreshQivivoInfos();
    }

    public function postSave()
    {
        log::add('qivivo', 'debug', 'postSave()');

        $order = 1;
        $_thisType = $this->getConfiguration('type');
        $_uuid = $this->getConfiguration('uuid');
        $eqLogic = eqLogic::byLogicalId($_uuid, 'qivivo');

        if (in_array($_thisType, array('Thermostat')))
        {
            //infos:
            $qivivoCmd = $this->getCmd(null, 'Consigne');
            if (!is_object($qivivoCmd)) {
                $qivivoCmd = new qivivoCmd();
                $qivivoCmd->setName(__('Consigne', __FILE__));
                $qivivoCmd->setIsVisible(1);
                $qivivoCmd->setIsHistorized(1);
                $qivivoCmd->setConfiguration('historizeMode', 'none');
                $qivivoCmd->setConfiguration('historyPurge', '-1 year');
                $qivivoCmd->setConfiguration('minValue', 0);
                $qivivoCmd->setConfiguration('maxValue', 35);
                $qivivoCmd->setOrder($order);
                $order ++;
            }
            $qivivoCmd->setEqLogic_id($this->getId());
            $qivivoCmd->setUnite('°C');
            $qivivoCmd->setLogicalId('Consigne');
            $qivivoCmd->setType('info');
            $qivivoCmd->setSubType('numeric');
            $qivivoCmd->save();

            $qivivoCmd = $this->getCmd(null, 'Temperature');
            if (!is_object($qivivoCmd)) {
                $qivivoCmd = new qivivoCmd();
                $qivivoCmd->setName(__('Temperature', __FILE__));
                $qivivoCmd->setIsVisible(1);
                $qivivoCmd->setIsHistorized(1);
                $qivivoCmd->setConfiguration('historizeMode', 'none');
                $qivivoCmd->setConfiguration('historyPurge', '-1 year');
                $qivivoCmd->setConfiguration('minValue', 0);
                $qivivoCmd->setConfiguration('maxValue', 35);
                $qivivoCmd->setOrder($order);
                $order ++;
            }
            $qivivoCmd->setEqLogic_id($this->getId());
            $qivivoCmd->setUnite('°C');
            $qivivoCmd->setLogicalId('Temperature');
            $qivivoCmd->setType('info');
            $qivivoCmd->setSubType('numeric');
            $qivivoCmd->save();

            $qivivoCmd = $this->getCmd(null, 'Chauffe');
            if (!is_object($qivivoCmd)) {
                $qivivoCmd = new qivivoCmd();
                $qivivoCmd->setName(__('Chauffe', __FILE__));
                $qivivoCmd->setIsVisible(0);
                $qivivoCmd->setIsHistorized(1);
                $qivivoCmd->setConfiguration('historizeMode', 'none');
                $qivivoCmd->setConfiguration('historyPurge', '-1 year');
                $qivivoCmd->setConfiguration('minValue', 0);
                $qivivoCmd->setConfiguration('maxValue', 35);
                $qivivoCmd->setConfiguration('repeatEventManagement', 'always');
                $qivivoCmd->setOrder($order);
                $order ++;
            }
            $qivivoCmd->setEqLogic_id($this->getId());
            $qivivoCmd->setLogicalId('Chauffe');
            $qivivoCmd->setType('info');
            $qivivoCmd->setSubType('numeric');
            $qivivoCmd->save();

            $qivivoCmd = $this->getCmd(null, 'Humidité');
            if (!is_object($qivivoCmd)) {
                $qivivoCmd = new qivivoCmd();
                $qivivoCmd->setName(__('Humidité', __FILE__));
                $qivivoCmd->setIsVisible(1);
                $qivivoCmd->setIsHistorized(1);
                $qivivoCmd->setConfiguration('historizeMode', 'none');
                $qivivoCmd->setConfiguration('historyPurge', '-1 year');
                $qivivoCmd->setConfiguration('minValue', 0);
                $qivivoCmd->setConfiguration('maxValue', 100);
                $qivivoCmd->setOrder($order);
                $order ++;
            }
            $qivivoCmd->setEqLogic_id($this->getId());
            $qivivoCmd->setUnite('%');
            $qivivoCmd->setLogicalId('Humidité');
            $qivivoCmd->setType('info');
            $qivivoCmd->setSubType('numeric');
            $qivivoCmd->save();

            $qivivoCmd = $this->getCmd(null, 'Presence');
            if (!is_object($qivivoCmd)) {
                $qivivoCmd = new qivivoCmd();
                $qivivoCmd->setName(__('Presence', __FILE__));
                $qivivoCmd->setIsVisible(1);
                $qivivoCmd->setIsHistorized(1);
                $qivivoCmd->setConfiguration('historizeMode', 'none');
                $qivivoCmd->setConfiguration('historyPurge', '-1 year');
                $qivivoCmd->setOrder($order);
                $order ++;
            }
            $qivivoCmd->setEqLogic_id($this->getId());
            $qivivoCmd->setLogicalId('Presence');
            $qivivoCmd->setType('info');
            $qivivoCmd->setSubType('binary');
            $qivivoCmd->save();

            $qivivoCmd = $this->getCmd(null, 'DernierePresence');
            if (!is_object($qivivoCmd)) {
                $qivivoCmd = new qivivoCmd();
                $qivivoCmd->setName(__('DernierePresence', __FILE__));
                $qivivoCmd->setIsVisible(1);
                $qivivoCmd->setIsHistorized(0);
                $qivivoCmd->setDisplay('icon', '<i class="fa fa-smile-o"></i>');
                $qivivoCmd->setOrder($order);
                $order ++;
            }
            $qivivoCmd->setEqLogic_id($this->getId());
            $qivivoCmd->setLogicalId('DernierePresence');
            $qivivoCmd->setType('info');
            $qivivoCmd->setSubType('string');
            $qivivoCmd->save();

            $qivivoCmd = $this->getCmd(null, 'frost_protection_temperature');
            if (!is_object($qivivoCmd)) {
                $qivivoCmd = new qivivoCmd();
                $qivivoCmd->setName(__('T°_Hors-gel', __FILE__));
                $qivivoCmd->setIsVisible(0);
                $qivivoCmd->setIsHistorized(0);
                $qivivoCmd->setOrder($order);
                $order ++;
            }
            $qivivoCmd->setEqLogic_id($this->getId());
            $qivivoCmd->setUnite('°C');
            $qivivoCmd->setLogicalId('frost_protection_temperature');
            $qivivoCmd->setType('info');
            $qivivoCmd->setSubType('numeric');
            $qivivoCmd->save();

            $qivivoCmd = $this->getCmd(null, 'absence_temperature');
            if (!is_object($qivivoCmd)) {
                $qivivoCmd = new qivivoCmd();
                $qivivoCmd->setName(__('T°_Absence', __FILE__));
                $qivivoCmd->setIsVisible(0);
                $qivivoCmd->setIsHistorized(0);
                $qivivoCmd->setOrder($order);
                $order ++;
            }
            $qivivoCmd->setEqLogic_id($this->getId());
            $qivivoCmd->setUnite('°C');
            $qivivoCmd->setLogicalId('absence_temperature');
            $qivivoCmd->setType('info');
            $qivivoCmd->setSubType('numeric');
            $qivivoCmd->save();

            $qivivoCmd = $this->getCmd(null, 'night_temperature');
            if (!is_object($qivivoCmd)) {
                $qivivoCmd = new qivivoCmd();
                $qivivoCmd->setName(__('T°_Nuit', __FILE__));
                $qivivoCmd->setIsVisible(0);
                $qivivoCmd->setIsHistorized(0);
                $qivivoCmd->setOrder($order);
                $order ++;
            }
            $qivivoCmd->setEqLogic_id($this->getId());
            $qivivoCmd->setUnite('°C');
            $qivivoCmd->setLogicalId('night_temperature');
            $qivivoCmd->setType('info');
            $qivivoCmd->setSubType('numeric');
            $qivivoCmd->save();

            $qivivoCmd = $this->getCmd(null, 'presence_temperature_1');
            if (!is_object($qivivoCmd)) {
                $qivivoCmd = new qivivoCmd();
                $qivivoCmd->setName(__('T°_Présence 1', __FILE__));
                $qivivoCmd->setIsVisible(0);
                $qivivoCmd->setIsHistorized(0);
                $qivivoCmd->setOrder($order);
                $order ++;
            }
            $qivivoCmd->setEqLogic_id($this->getId());
            $qivivoCmd->setUnite('°C');
            $qivivoCmd->setLogicalId('presence_temperature_1');
            $qivivoCmd->setType('info');
            $qivivoCmd->setSubType('numeric');
            $qivivoCmd->save();

            $qivivoCmd = $this->getCmd(null, 'presence_temperature_2');
            if (!is_object($qivivoCmd)) {
                $qivivoCmd = new qivivoCmd();
                $qivivoCmd->setName(__('T°_Présence 2', __FILE__));
                $qivivoCmd->setIsVisible(0);
                $qivivoCmd->setIsHistorized(0);
                $qivivoCmd->setOrder($order);
                $order ++;
            }
            $qivivoCmd->setEqLogic_id($this->getId());
            $qivivoCmd->setUnite('°C');
            $qivivoCmd->setLogicalId('presence_temperature_2');
            $qivivoCmd->setType('info');
            $qivivoCmd->setSubType('numeric');
            $qivivoCmd->save();

            $qivivoCmd = $this->getCmd(null, 'presence_temperature_3');
            if (!is_object($qivivoCmd)) {
                $qivivoCmd = new qivivoCmd();
                $qivivoCmd->setName(__('T°_Présence 3', __FILE__));
                $qivivoCmd->setIsVisible(0);
                $qivivoCmd->setIsHistorized(0);
                $qivivoCmd->setOrder($order);
                $order ++;
            }
            $qivivoCmd->setEqLogic_id($this->getId());
            $qivivoCmd->setUnite('°C');
            $qivivoCmd->setLogicalId('presence_temperature_3');
            $qivivoCmd->setType('info');
            $qivivoCmd->setSubType('numeric');
            $qivivoCmd->save();

            $qivivoCmd = $this->getCmd(null, 'presence_temperature_4');
            if (!is_object($qivivoCmd)) {
                $qivivoCmd = new qivivoCmd();
                $qivivoCmd->setName(__('T°_Présence 4', __FILE__));
                $qivivoCmd->setIsVisible(0);
                $qivivoCmd->setIsHistorized(0);
                $qivivoCmd->setOrder($order);
                $order ++;
            }
            $qivivoCmd->setEqLogic_id($this->getId());
            $qivivoCmd->setUnite('°C');
            $qivivoCmd->setLogicalId('presence_temperature_4');
            $qivivoCmd->setType('info');
            $qivivoCmd->setSubType('numeric');
            $qivivoCmd->save();

            $qivivoCmd = $this->getCmd(null, 'duree_temp');
            if (!is_object($qivivoCmd)) {
                $qivivoCmd = new qivivoCmd();
                $qivivoCmd->setName(__('DuréeOrdre', __FILE__));
                $qivivoCmd->setIsVisible(0);
                $qivivoCmd->setIsHistorized(0);
                $qivivoCmd->setOrder($order);
                $order ++;
            }
            $qivivoCmd->setEqLogic_id($this->getId());
            $qivivoCmd->setUnite('mins');
            $qivivoCmd->setLogicalId('duree_temp');
            $qivivoCmd->setType('info');
            $qivivoCmd->setSubType('numeric');
            $qivivoCmd->save();

            //actions:
            $qivivoCmd = $this->getCmd(null, 'SetDuréeOrdre');
            if (!is_object($qivivoCmd)) {
                $qivivoCmd = new qivivoCmd();
                $qivivoCmd->setName(__('SetDuréeOrdre', __FILE__));
                $qivivoCmd->setIsVisible(0);
                $qivivoCmd->setOrder($order);
                $order ++;
            }
            $qivivoCmd->setEqLogic_id($this->getId());
            $qivivoCmd->setLogicalId('SetDuréeOrdre');
            $qivivoCmd->setType('action');
            $qivivoCmd->setSubType('slider');
            $qivivoCmd->save();

            $qivivoCmd = $this->getCmd(null, 'SetTemperature');
            if (!is_object($qivivoCmd)) {
                $qivivoCmd = new qivivoCmd();
                $qivivoCmd->setName(__('SetTempérature', __FILE__));
                $qivivoCmd->setIsVisible(0);
                $qivivoCmd->setOrder($order);
                $order ++;
            }
            $qivivoCmd->setEqLogic_id($this->getId());
            $qivivoCmd->setLogicalId('SetTemperature');
            $qivivoCmd->setType('action');
            $qivivoCmd->setSubType('slider');
            $qivivoCmd->save();

            $qivivoCmd = $this->getCmd(null, 'Annule_Ordre_Temp');
            if (!is_object($qivivoCmd)) {
                $qivivoCmd = new qivivoCmd();
                $qivivoCmd->setName(__('Annule_Ordre_Temp', __FILE__));
                $qivivoCmd->setIsVisible(0);
                $qivivoCmd->setOrder($order);
                $order ++;
            }
            $qivivoCmd->setEqLogic_id($this->getId());
            $qivivoCmd->setLogicalId('Annule_Ordre_Temp');
            $qivivoCmd->setType('action');
            $qivivoCmd->setSubType('other');
            $qivivoCmd->save();

            $qivivoCmd = $this->getCmd(null, 'SetTempAbsence');
            if (!is_object($qivivoCmd)) {
                $qivivoCmd = new qivivoCmd();
                $qivivoCmd->setName(__('SetTempAbsence', __FILE__));
                $qivivoCmd->setIsVisible(0);
                $qivivoCmd->setOrder($order);
                $order ++;
            }
            $qivivoCmd->setEqLogic_id($this->getId());
            $qivivoCmd->setLogicalId('SetTempAbsence');
            $qivivoCmd->setType('action');
            $qivivoCmd->setSubType('other');
            $qivivoCmd->save();

            $qivivoCmd = $this->getCmd(null, 'SetTempHorsGel');
            if (!is_object($qivivoCmd)) {
                $qivivoCmd = new qivivoCmd();
                $qivivoCmd->setName(__('SetTempHorsGel', __FILE__));
                $qivivoCmd->setIsVisible(0);
                $qivivoCmd->setOrder($order);
                $order ++;
            }
            $qivivoCmd->setEqLogic_id($this->getId());
            $qivivoCmd->setLogicalId('SetTempHorsGel');
            $qivivoCmd->setType('action');
            $qivivoCmd->setSubType('other');
            $qivivoCmd->save();

            $qivivoCmd = $this->getCmd(null, 'SetTempNuit');
            if (!is_object($qivivoCmd)) {
                $qivivoCmd = new qivivoCmd();
                $qivivoCmd->setName(__('SetTempNuit', __FILE__));
                $qivivoCmd->setIsVisible(0);
                $qivivoCmd->setOrder($order);
                $order ++;
            }
            $qivivoCmd->setEqLogic_id($this->getId());
            $qivivoCmd->setLogicalId('SetTempNuit');
            $qivivoCmd->setType('action');
            $qivivoCmd->setSubType('other');
            $qivivoCmd->save();

            $qivivoCmd = $this->getCmd(null, 'SetTempPres1');
            if (!is_object($qivivoCmd)) {
                $qivivoCmd = new qivivoCmd();
                $qivivoCmd->setName(__('SetTempPres1', __FILE__));
                $qivivoCmd->setIsVisible(0);
                $qivivoCmd->setOrder($order);
                $order ++;
            }
            $qivivoCmd->setEqLogic_id($this->getId());
            $qivivoCmd->setLogicalId('SetTempPres1');
            $qivivoCmd->setType('action');
            $qivivoCmd->setSubType('other');
            $qivivoCmd->save();

            $qivivoCmd = $this->getCmd(null, 'SetTempPres2');
            if (!is_object($qivivoCmd)) {
                $qivivoCmd = new qivivoCmd();
                $qivivoCmd->setName(__('SetTempPres2', __FILE__));
                $qivivoCmd->setIsVisible(0);
                $qivivoCmd->setOrder($order);
                $order ++;
            }
            $qivivoCmd->setEqLogic_id($this->getId());
            $qivivoCmd->setLogicalId('SetTempPres2');
            $qivivoCmd->setType('action');
            $qivivoCmd->setSubType('other');
            $qivivoCmd->save();

            $qivivoCmd = $this->getCmd(null, 'SetTempPres3');
            if (!is_object($qivivoCmd)) {
                $qivivoCmd = new qivivoCmd();
                $qivivoCmd->setName(__('SetTempPres3', __FILE__));
                $qivivoCmd->setIsVisible(0);
                $qivivoCmd->setOrder($order);
                $order ++;
            }
            $qivivoCmd->setEqLogic_id($this->getId());
            $qivivoCmd->setLogicalId('SetTempPres3');
            $qivivoCmd->setType('action');
            $qivivoCmd->setSubType('other');
            $qivivoCmd->save();

            $qivivoCmd = $this->getCmd(null, 'SetTempPres4');
            if (!is_object($qivivoCmd)) {
                $qivivoCmd = new qivivoCmd();
                $qivivoCmd->setName(__('SetTempPres4', __FILE__));
                $qivivoCmd->setIsVisible(0);
                $qivivoCmd->setOrder($order);
                $order ++;
            }
            $qivivoCmd->setEqLogic_id($this->getId());
            $qivivoCmd->setLogicalId('SetTempPres4');
            $qivivoCmd->setType('action');
            $qivivoCmd->setSubType('other');
            $qivivoCmd->save();

            $qivivoCmd = $this->getCmd(null, 'IncOne');
            if (!is_object($qivivoCmd)) {
                $qivivoCmd = new qivivoCmd();
                $qivivoCmd->setName(__('+1', __FILE__));
                $qivivoCmd->setIsVisible(1);
                $qivivoCmd->setDisplay('icon', '<i class="fas fa-chevron-up";></i>');
                $qivivoCmd->setOrder($order);
                $order ++;
            }
            $qivivoCmd->setEqLogic_id($this->getId());
            $qivivoCmd->setLogicalId('IncOne');
            $qivivoCmd->setType('action');
            $qivivoCmd->setSubType('other');
            $qivivoCmd->save();

            $qivivoCmd = $this->getCmd(null, 'DecOne');
            if (!is_object($qivivoCmd)) {
                $qivivoCmd = new qivivoCmd();
                $qivivoCmd->setName(__('-1', __FILE__));
                $qivivoCmd->setIsVisible(1);
                $qivivoCmd->setDisplay('icon', '<i class="fas fa-chevron-down"></i>');
                $qivivoCmd->setOrder($order);
                $order ++;
            }
            $qivivoCmd->setEqLogic_id($this->getId());
            $qivivoCmd->setLogicalId('DecOne');
            $qivivoCmd->setType('action');
            $qivivoCmd->setSubType('other');
            $qivivoCmd->save();
        }

        if (in_array($_thisType, array('Module Chauffage')))
        {
            //infos:
            $qivivoCmd = $this->getCmd(null, 'Ordre');
            if (!is_object($qivivoCmd)) {
                $qivivoCmd = new qivivoCmd();
                $qivivoCmd->setName(__('Ordre', __FILE__));
                $qivivoCmd->setIsVisible(1);
                $qivivoCmd->setIsHistorized(0);
                $qivivoCmd->setDisplay('icon', '<i class="divers-thermometer31"></i>');
                $qivivoCmd->setDisplay('forceReturnLineBefore', True);
                $qivivoCmd->setOrder($order);
                $order ++;
            }
            $qivivoCmd->setEqLogic_id($this->getId());
            $qivivoCmd->setLogicalId('Ordre');
            $qivivoCmd->setType('info');
            $qivivoCmd->setSubType('string');
            $qivivoCmd->save();

            $qivivoCmd = $this->getCmd(null, 'OrdreNum');
            if (!is_object($qivivoCmd)) {
                $qivivoCmd = new qivivoCmd();
                $qivivoCmd->setName(__('OrdreNum', __FILE__));
                $qivivoCmd->setIsVisible(0);
                $qivivoCmd->setIsHistorized(1);
                $qivivoCmd->setConfiguration('historizeMode', 'none');
                $qivivoCmd->setConfiguration('historyPurge', '-1 year');
                $qivivoCmd->setOrder($order);
                $order ++;

            }
            $qivivoCmd->setEqLogic_id($this->getId());
            $qivivoCmd->setLogicalId('OrdreNum');
            $qivivoCmd->setType('info');
            $qivivoCmd->setSubType('numeric');
            $qivivoCmd->save();

            $qivivoCmd = $this->getCmd(null, 'current_program');
            if (!is_object($qivivoCmd)) {
                $qivivoCmd = new qivivoCmd();
                $qivivoCmd->setName(__('Programme', __FILE__));
                $qivivoCmd->setIsVisible(1);
                $qivivoCmd->setIsHistorized(0);
                $qivivoCmd->setOrder($order);
                $order ++;

            }
            $qivivoCmd->setEqLogic_id($this->getId());
            $qivivoCmd->setLogicalId('current_program');
            $qivivoCmd->setType('info');
            $qivivoCmd->setSubType('string');
            $qivivoCmd->save();

            //actions:
            if ($this->getConfiguration('isModuleThermostat') == 0)
            {
                $qivivoCmd = $this->getCmd(null, 'SetMode');
                if (!is_object($qivivoCmd)) {
                    $qivivoCmd = new qivivoCmd();
                    $qivivoCmd->setName(__('SetMode', __FILE__));
                    $qivivoCmd->setIsVisible(1);
                    $qivivoCmd->setOrder($order);
                    $order ++;
                }
                $qivivoCmd->setEqLogic_id($this->getId());
                $qivivoCmd->setLogicalId('SetMode');
                $qivivoCmd->setType('action');
                $qivivoCmd->setSubType('select');
                $qivivoCmd->setConfiguration('listValue','5|Arrêt;6|Hors-Gel;4|Eco;8|Confort -2;7|Confort -1;3|Confort');
                $qivivoCmd->save();
            }

            $qivivoCmd = $this->getCmd(null, 'SetProgram');
            if (!is_object($qivivoCmd)) {
                $qivivoCmd = new qivivoCmd();
                $qivivoCmd->setName(__('SetProgram', __FILE__));
                $qivivoCmd->setIsVisible(1);
                $qivivoCmd->setOrder($order);
                $order ++;
            }
            $qivivoCmd->setEqLogic_id($this->getId());
            $qivivoCmd->setLogicalId('SetProgram');
            $qivivoCmd->setType('action');
            $qivivoCmd->setSubType('select');

            $programs = $this->getConfiguration('programs');
            if (count($programs) == 0)
            {
                $listValue = '0|Aucun programme;';
            }
            else
            {
                $listValue = '';
                foreach ($programs as $program)
                {
                    $pName = $program['name'];
                    $listValue .= $pName.'|'.$pName.';';
                }
            }
            $qivivoCmd->setConfiguration('listValue', $listValue);
            $qivivoCmd->save();
        }

        if (in_array($_thisType, array('Module Chauffage', 'Thermostat', 'Passerelle')))
        {
            //common infos:
            $qivivoCmd = $this->getCmd(null, 'LastMsg');
            if (!is_object($qivivoCmd)) {
                $qivivoCmd = new qivivoCmd();
                $qivivoCmd->setName(__('LastMsg', __FILE__));
                $qivivoCmd->setIsVisible(0);
                if ($_thisType == 'Module Chauffage') $qivivoCmd->setIsVisible(1);
                $qivivoCmd->setIsHistorized(0);
                $qivivoCmd->setDisplay('icon', '<i class="nature-leaf37"></i>');
                $qivivoCmd->setOrder($order);
                $order ++;
            }
            $qivivoCmd->setEqLogic_id($this->getId());
            $qivivoCmd->setLogicalId('LastMsg');
            $qivivoCmd->setType('info');
            $qivivoCmd->setSubType('string');
            $qivivoCmd->save();

            $qivivoCmd = $this->getCmd(null, 'Firmware');
            if (!is_object($qivivoCmd)) {
                $qivivoCmd = new qivivoCmd();
                $qivivoCmd->setName(__('Firmware', __FILE__));
                $qivivoCmd->setIsVisible(0);
                $qivivoCmd->setIsHistorized(0);
                $qivivoCmd->setOrder($order);
                $order ++;
            }
            $qivivoCmd->setEqLogic_id($this->getId());
            $qivivoCmd->setLogicalId('Firmware');
            $qivivoCmd->setType('info');
            $qivivoCmd->setSubType('string');
            $qivivoCmd->save();
        }

        $refresh = $this->getCmd(null, 'refresh');
        if (!is_object($refresh)) {
            $refresh = new qivivoCmd();
            $refresh->setLogicalId('refresh');
            $refresh->setIsVisible(1);
            $refresh->setName(__('Rafraichir', __FILE__));
            $qivivoCmd->setOrder($order);
        }
        $refresh->setType('action');
        $refresh->setSubType('other');
        $refresh->setEqLogic_id($this->getId());
        $refresh->save();
        $eqLogic->refreshWidget();
    }

    public function toHtml($_version = 'dashboard')
    {
        $version = jeedom::versionAlias($_version);
        $replace = $this->preToHtml($_version);
        if (!is_array($replace)) {
            return $replace;
        }

        //only custom template for thermostat dashboard:
        $_thisType = $this->getConfiguration('type');
        //log::add('qivivo', 'debug', 'toHtml version: '.$_version.' type: '.$_thisType.' '.print_r($replace, 1));

        if ($_thisType == 'Thermostat')
        {
            $refresh = $this->getCmd(null, 'refresh');
            $replace['#refresh_id#'] = $refresh->getId();

            $cmd = $this->getCmd(null, 'Consigne');
            $tmpConsigne = $cmd->execCmd();
            $replace['#consigne#'] = $tmpConsigne;
            $replace['#consigne_id#'] = $cmd->getId();
            $replace['#consigne_collectDate#'] = $cmd->getCollectDate();
            if ($cmd->getIsHistorized() == 1) {
                $replace['#consigne_history#'] = 'history cursor';
            }
            $cmd = $this->getCmd(null, 'IncOne');
            $replace['#IncOne_id#'] = $cmd->getId();
            $cmd = $this->getCmd(null, 'DecOne');
            $replace['#DecOne_id#'] = $cmd->getId();
            $cmd = $this->getCmd(null, 'Annule_Ordre_Temp');
            $replace['#cancel_id#'] = $cmd->getId();

            $cmd = $this->getCmd(null, 'Temperature');
            $tmpRoom = $cmd->execCmd();
            $replace['#temperature#'] = $tmpRoom;
            $replace['#temperature_id#'] = $cmd->getId();
            $replace['#temperature_collectDate#'] = $cmd->getCollectDate();
            if ($cmd->getIsHistorized() == 1) {
                $replace['#temperature_history#'] = 'history cursor';
            }

            $cmd = $this->getCmd(null, 'Humidité');
            $replace['#humidity#'] = $cmd->execCmd();
            $replace['#humidity_id#'] = $cmd->getId();
            $replace['#humidity_collectDate#'] = $cmd->getCollectDate();
            if ($cmd->getIsHistorized() == 1) {
                $replace['#humidity_history#'] = 'history cursor';
            }

            $cmd = $this->getCmd(null, 'Presence');
            $pres = $cmd->execCmd();
            $replace['#presence_id#'] = $cmd->getId();
            $replace['#presence_collectDate#'] = $cmd->getCollectDate();
            if ($cmd->getIsHistorized() == 1) {
                $replace['#presence_history#'] = 'history cursor';
            }
            $replace['#pres_class#'] = 'fa fa-check';
            if ($pres == 0) {
                $replace['#pres_class#'] = 'fa fa-times';
            }

            $cmd = $this->getCmd(null, 'DernierePresence');
            $replace['#lastpres#'] = $cmd->execCmd();
            $replace['#lastpres_id#'] = $cmd->getId();
            $replace['#lastpres_collectDate#'] = $cmd->getCollectDate();

            $heating = $this->getCmd(null, 'Chauffe')->execCmd();

            if ($heating > 0) $replace['#imgheating#'] = '/plugins/qivivo/core/img/heating_on.png';
            else $replace['#imgheating#'] = '/plugins/qivivo/core/img/heating_off.png';

            $html = template_replace($replace, getTemplate('core', $version, 'thermostat', 'qivivo'));
        }

        if ($_thisType == 'Module Chauffage')
        {
            //infos
            $cmd = $this->getCmd(null, 'Ordre');
            $order = $cmd->execCmd();
            $replace['#order#'] = $order;
            $replace['#order_id#'] = $cmd->getId();
            $replace['#order_collectDate#'] = $cmd->getCollectDate();
            if ($cmd->getIsHistorized() == 1) {
                $replace['#order_history#'] = 'history cursor';
            }

            $cmd = $this->getCmd(null, 'LastMsg');
            $replace['#lastmsg#'] = $cmd->execCmd();
            $replace['#lastmsg_id#'] = $cmd->getId();
            $replace['#lastmsg_collectDate#'] = $cmd->getCollectDate();
            if ($cmd->getIsHistorized() == 1) {
                $replace['#lastmsg_history#'] = 'history cursor';
            }

            //actions:
            if ($order != 'monozone')
            {
                $cmd = $this->getCmd(null, 'SetMode');
                $replace['#SetMode_id#'] = $cmd->getId();
                $modes = $cmd->getConfiguration('listValue');
                $modes = explode(';', $modes);
                $options = '';
                foreach ($modes as $mode)
                {
                    $value = explode('|', $mode)[0];
                    $display = explode('|', $mode)[1];
                    if ($order == $display) $options .= '<option value="'.$value.'" selected>'.$display.'</option>';
                    else $options .= '<option value="'.$value.'">'.$display.'</option>';
                }
                $replace['#SetMode_listValue#'] = $options;
            }
            $current_program = $this->getCmd(null, 'current_program')->execCmd();
            $cmd = $this->getCmd(null, 'SetProgram');
            $replace['#SetProgram_id#'] = $cmd->getId();
            $programs = $cmd->getConfiguration('listValue');
            $programs = explode(';', $programs);
            $options = '';
            foreach ($programs as $program)
            {
                $value = explode('|', $program)[0];
                $display = explode('|', $program)[1];
                if ($current_program == $display) $options .= '<option value="'.$value.'" selected>'.$display.'</option>';
                else $options .= '<option value="'.$value.'">'.$display.'</option>';
            }
            $replace['#SetProgram_listValue#'] = $options;

            if ($order == 'monozone') $html = template_replace($replace, getTemplate('core', $version, 'module-t', 'qivivo'));
            else $html = template_replace($replace, getTemplate('core', $version, 'module', 'qivivo'));
        }

        if ($_thisType == 'Passerelle')
        {
            $cmd = $this->getCmd(null, 'LastMsg');
            $replace['#lastmsg#'] = $cmd->execCmd();
            $replace['#lastmsg_id#'] = $cmd->getId();
            $replace['#lastmsg_collectDate#'] = $cmd->getCollectDate();
            if ($cmd->getIsHistorized() == 1) {
                $replace['#lastmsg_history#'] = 'history cursor';
            }


            $cmd = $this->getCmd(null, 'Firmware');
            $replace['#firmware#'] = $cmd->execCmd();
            $replace['#firmware_id#'] = $cmd->getId();
            $replace['#firmware_collectDate#'] = $cmd->getCollectDate();
            if ($cmd->getIsHistorized() == 1) {
                $replace['#firmware_history#'] = 'history cursor';
            }

            $html = template_replace($replace, getTemplate('core', $version, 'gateway', 'qivivo'));
        }

        return $html;
    }
}

class qivivoCmd extends cmd {
    public function dontRemoveCmd()
    {
        return true;
    }

    public function execute($_options = array()) {
        log::add('qivivo', 'debug', 'execute options: '.print_r($_options, true));
        if ($this->getLogicalId() == 'refresh') {
            qivivo::refreshQivivoInfos();
            return;
        }

        $eqLogic = $this->getEqlogic();
        $_action = $this->getLogicalId();
        $_type = $eqLogic->getConfiguration('type');
        $_uuid = $eqLogic->getConfiguration('uuid');

        log::add('qivivo', 'debug', 'execute()->'.$_action);

        if ($_type == 'Module Chauffage') {
            if ($_action == 'SetMode') {
                $modeNum = $_options['select'];
                if ($modeNum == '') return;

                $_fullQivivo = qivivo::getCustomAPI('action', $_action);
                if ($_fullQivivo == False) return;

                $zone_name = $_type = $eqLogic->getConfiguration('zone_name');
                $modeString = '';
                switch($modeNum)
                {
                    case 8:
                        $modeString = 'Confort -2';
                        break;

                    case 7:
                        $modeString = 'Confort -1';
                        break;

                    case 6:
                        $modeString = 'Hors-Gel';
                        break;

                    case 5:
                        $modeString = 'Arrêt';
                        break;

                    case 4:
                        $modeString = 'Eco';
                        break;

                    case 3:
                        $modeString = 'Confort';
                        break;
                }
                log::add('qivivo', 'debug', 'SetMode '.$eqLogic->getName().' to '.$modeString);
                $result = $_fullQivivo->setZoneMode($zone_name, $modeNum);
                log::add('qivivo', 'debug', print_r($result, true));

                $eqLogic->checkAndUpdateCmd('Ordre', $modeString);
                $eqLogic->refreshWidget();
            }

            if ($_action == 'SetProgram') {
                $program = $_options['select'];
                if ($program == '') return;

                $_fullQivivo = qivivo::getCustomAPI('action', $_action);
                if ($_fullQivivo == False) return;

                log::add('qivivo', 'debug', 'SetProgram '.$eqLogic->getName().' to '.$program);
                $program_name = $eqLogic->getConfiguration('program_name');
                if ($eqLogic->getConfiguration('isModuleThermostat') == 0)
                {
                    $mode_refs_array = ['Confort'=>'mz_comfort',
                                        'Confort -1'=>'mz_comfort_minus_one',
                                        'Confort -2'=>'mz_comfort_minus_two',
                                        'Eco'=>'mz_eco',
                                        'Hors-Gel'=>'mz_frost',
                                        'Arrêt'=>'mz_off'
                                        ];
                } else {
                    $mode_refs_array = ['Pres 4'=>'pres_4',
                                        'Pres 3'=>'pres_3',
                                        'Pres 2'=>'pres_2',
                                        'Pres 1'=>'pres_1',
                                        'Nuit'=>'nuit',
                                        'Hors-Gel'=>'hg',
                                        'Absence'=>'absence'
                                        ];
                }
                $eqlogic_Programs = $eqLogic->getConfiguration('programs');
                foreach ($eqlogic_Programs as $eqlogic_program)
                {
                    $progName = $eqlogic_program['name'];
                    if ($eqlogic_program['name'] == $program)
                    {
                        $program_array = array();
                        $days = $eqlogic_program['days'];
                        foreach ($days as $day)
                        {
                            $day_array = array();
                            $periods = $day['periods'];
                            $c = count($periods);
                            for ($i = 0 ; $i < $c ; $i++)
                            {
                                $period = $periods[$i];
                                $periodStart = $period['period_start'];
                                $periodMode = $period['temperature_setting'];
                                if ($i == $c-1)
                                {
                                    $periodEnd = '23:59';
                                }
                                else
                                {
                                    $time = strtotime($periods[$i+1]['period_start'])  - 60;
                                    $periodEnd = date("H:i", $time);
                                }
                                $periodMode = $mode_refs_array[$periodMode];
                                array_push($day_array, array($periodStart, $periodEnd, $periodMode));
                            }
                            array_push($program_array, $day_array);
                        }

                        $result = $_fullQivivo->setProgram($program_name, $program_array);
                        log::add('qivivo', 'debug', print_r($result, true));

                        $eqLogic->checkAndUpdateCmd('current_program', $program);
                        $eqLogic->refreshWidget();
                        return;
                    }
                }
                log::add('qivivo', 'warning', 'Unfound program!');
                return;
            }
        }

        if ($_type == 'Thermostat') {
            if ($_action == 'SetDuréeOrdre') {
                log::add('qivivo', 'debug', 'SetDuréeOrdre to '.$_options['slider']);
                $duree_temp = $eqLogic->getCmd(null, 'duree_temp');
                $duree_temp->event($_options['slider']);
                return;
            }

            if ($_action == 'IncOne') {
                $_qivivo = $eqLogic->getAPI('action', $_action);
                $info = $eqLogic->getCmd(null, 'Consigne');
                $temp = $info->execCmd();
                $temp += 1;
                log::add('qivivo', 'debug', 'IncOne to '.$temp.' Temp: '.$Temp);
                $result = $_qivivo->setThermostatTemperature($temp + 0.001, 120);
                $eqLogic->checkAndUpdateCmd('Consigne', $temp);
                $eqLogic->refreshWidget();
                log::add('qivivo', 'debug', print_r($result, true));
                return;
            }

            if ($_action == 'DecOne') {
                $_qivivo = $eqLogic->getAPI('action', $_action);
                $info = $eqLogic->getCmd(null, 'Consigne');
                $temp = $info->execCmd();
                $temp -= 1;
                log::add('qivivo', 'debug', 'DecOne to '.$temp.' Temp: '.$Temp);
                $result = $_qivivo->setThermostatTemperature($temp + 0.001, 120);
                $eqLogic->checkAndUpdateCmd('Consigne', $temp);
                $eqLogic->refreshWidget();
                log::add('qivivo', 'debug', print_r($result, true));
                return;
            }

            if ($_action == 'SetTemperature') {
                $_qivivo = $eqLogic->getAPI('action', $_action);
                $temp = $_options['slider'];
                $info = $eqLogic->getCmd(null, 'duree_temp');
                $duree_temp = intval($info->execCmd());
                log::add('qivivo', 'debug', 'SetTemperature to '.$temp.' duree_temp: '.$duree_temp);
                $result = $_qivivo->setThermostatTemperature($temp, $duree_temp);
                $eqLogic->checkAndUpdateCmd('Consigne', $temp);
                $eqLogic->refreshWidget();
                log::add('qivivo', 'debug', print_r($result, true));
                return;
            }

            if ($_action == 'Annule_Ordre_Temp') {
                $_qivivo = $eqLogic->getAPI('action', $_action);
                log::add('qivivo', 'debug', 'Annule_Ordre_Temp');
                $result = $_qivivo->cancelThermostatTemperature();
                log::add('qivivo', 'debug', print_r($result, true));
                return;
            }

            if ($_action == 'SetTempAbsence') {
                $_qivivo = $eqLogic->getAPI('action', $_action);
                $result = $_qivivo->setSetting('absence_temperature', $_options['slider']);
                log::add('qivivo', 'debug', print_r($result, true));
                return;
            }

            if ($_action == 'SetTempHorsGel') {
                $_qivivo = $eqLogic->getAPI('action', $_action);
                $result = $_qivivo->setSetting('frost_protection_temperature', $_options['slider']);
                log::add('qivivo', 'debug', print_r($result, true));
                return;
            }

            if ($_action == 'SetTempNuit') {
                $_qivivo = $eqLogic->getAPI('action', $_action);
                $result = $_qivivo->setSetting('night_temperature', $_options['slider']);
                log::add('qivivo', 'debug', print_r($result, true));
                return;
            }

            if ($_action == 'SetTempPres1') {
                $_qivivo = $eqLogic->getAPI('action', $_action);
                $result = $_qivivo->setSetting('presence_temperature_1', $_options['slider']);
                log::add('qivivo', 'debug', print_r($result, true));
                return;
            }

            if ($_action == 'SetTempPres2') {
                $_qivivo = $eqLogic->getAPI('action', $_action);
                $result = $_qivivo->setSetting('presence_temperature_2', $_options['slider']);
                log::add('qivivo', 'debug', print_r($result, true));
                return;
            }

            if ($_action == 'SetTempPres3') {
                $_qivivo = $eqLogic->getAPI('action', $_action);
                $result = $_qivivo->setSetting('presence_temperature_3', $_options['slider']);
                log::add('qivivo', 'debug', print_r($result, true));
                return;
            }

            if ($_action == 'SetTempPres4') {
                $_qivivo = $eqLogic->getAPI('action', $_action);
                $result = $_qivivo->setSetting('presence_temperature_4', $_options['slider']);
                log::add('qivivo', 'debug', print_r($result, true));
                return;
            }
        }
    }
}


