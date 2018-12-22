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

class qivivo extends eqLogic {
    /*     * *************************Attributs****************************** */


    /*     * ***********************Methode static*************************** */

    public static function getAPI() {
        $client_id = config::byKey('client_id', 'qivivo');
        $client_secret = config::byKey('client_secret', 'qivivo');
        $_qivivo = new splQivivoAPI($client_id, $client_secret);
        return $_qivivo;
    }

    public static function syncWithQivivo() {
        $client_id = config::byKey('client_id', 'qivivo');
        $client_secret = config::byKey('client_secret', 'qivivo');
        $_qivivo = qivivo::getAPI();

        $devices = $_qivivo->getDevices();
        foreach ($devices as $device)
        {
            $eqLogic = eqLogic::byLogicalId($device['uuid'], 'qivivo');
            if (!is_object($eqLogic)) {
                $eqLogic = new qivivo();
                $eqLogic->setEqType_name('qivivo');
                $eqLogic->setIsVisible(1);
                $eqLogic->setIsEnable(1);

                $type = $device['type'];
                if ($type == 'thermostat') $type = 'Thermostat';
                if ($type == 'wireless-module') $type = 'Module Chauffage';
                if ($type == 'gateway')
                {
                    $type = 'Passerelle';
                    $eqLogic->setIsVisible(0);
                }

                $name = $type;
                if ($name == 'Module Chauffage') $name .= ' '.explode('-', $device['uuid'])[1];
                $eqLogic->setName($name);

                $eqLogic->setConfiguration('uuid', $device['uuid']);
                $eqLogic->setConfiguration('type', $type);
                $eqLogic->setCategory('heating', 1);
                $eqLogic->setLogicalId($device['uuid']);
                $eqLogic->save();
            }
        }
    }

    public static function refreshQivivoInfos() {
        log::add('qivivo', 'debug', '___refreshQivivoInfos starting');
        try
        {
            $_qivivo = qivivo::getAPI();
            if (isset($_qivivo->error))
            {
                $_apiError = $_qivivo->error;
                log::add('qivivo', 'debug', 'API error: '.$_apiError);
                return False;
            }
            $devices = $_qivivo->getDevices();
            foreach ($devices as $device)
            {
                $_type = $device['type'];
                $_uuid = $device['uuid'];
                log::add('qivivo', 'debug', '________type: '.$_type.' uuid: '.$_uuid);
                $eqLogic = eqLogic::byLogicalId($_uuid, 'qivivo');
                if (!is_object($eqLogic)) continue;
                if ($_type == 'gateway') continue;

                if ($_type == 'thermostat')
                {
                    $eqLogic->getCmd(null, 'duree_temp')->event(120);

                    $thermostatInfos = $_qivivo->getThermostatInfos();
                    log::add('qivivo', 'debug', 'getThermostatInfos: '.print_r($thermostatInfos, true));
                    $lastMsg = $thermostatInfos['lastCommunicationDate'];
                    $lastMsg = date("d-m-Y H:i", strtotime($lastMsg));
                    $eqLogic->getCmd(null, 'LastMsg')->event($lastMsg);
                    $firmware = $thermostatInfos['softwareVersion'];
                    $eqLogic->getCmd(null, 'Firmware')->event($firmware);

                    $thermostatHumidity = $_qivivo->getThermostatHumidity();
                    log::add('qivivo', 'debug', 'getThermostatHumidity: '.print_r($thermostatHumidity, true));
                    $humidity = $thermostatHumidity['humidity'];
                    $eqLogic->getCmd(null, 'Humidité')->event($humidity);

                    $thermostatPresence = $_qivivo->getThermostatPresence();
                    log::add('qivivo', 'debug', 'getThermostatPresence: '.print_r($thermostatPresence, true));
                    $Pres = $thermostatPresence['presence_detected'];
                    $presence = 0;
                    if ($Pres) $presence = 1;
                    $eqLogic->getCmd(null, 'Presence')->event($presence);

                    $lastPresence = $_qivivo->getLastPresence();
                    log::add('qivivo', 'debug', 'getLastPresence: '.print_r($lastPresence, true));
                    $lastP = $lastPresence['last_presence_recorded_time'];
                    $lastP = date("d-m-Y H:i", strtotime($lastP));
                    $eqLogic->getCmd(null, 'DernierePresence')->event($lastP);

                    $thermostatTemperature = $_qivivo->getThermostatTemperature();
                    log::add('qivivo', 'debug', 'getThermostatTemperature: '.print_r($thermostatTemperature, true));
                    $order = $thermostatTemperature['current_temperature_order'];
                    $temp = $thermostatTemperature['temperature'];
                    $eqLogic->getCmd(null, 'Consigne')->event($order);
                    $eqLogic->getCmd(null, 'Temperature')->event($temp);

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
                    $order = $moduleOrder['current_pilot_wire_order'];
                    $lastMsg = $moduleInfos['lastCommunicationDate'];
                    $lastMsg = date("d-m-Y H:i", strtotime($lastMsg));
                    $firmware = $moduleInfos['softwareVersion'];

                    $eqLogic->getCmd(null, 'Ordre')->event($order);
                    $eqLogic->getCmd(null, 'Firmware')->event($firmware);
                    $eqLogic->getCmd(null, 'LastMsg')->event($lastMsg);

                    $ordernum = 0;
                    if ($order == 'off') $ordernum = 1;
                    if ($order == 'frost') $ordernum = 2;
                    if ($order == 'eco') $ordernum = 3;
                    if ($order == 'comfort_minus_two') $ordernum = 4;
                    if ($order == 'comfort_minus_one') $ordernum = 5;
                    if ($order == 'comfort') $ordernum = 6;
                    $eqLogic->getCmd(null, 'OrdreNum')->event($ordernum);
                }
            }
            log::add('qivivo', 'debug', '___refreshQivivoInfos ending');
        } catch (Exception $e) {
            log::add('qivivo', 'debug', '___refreshQivivoInfos Exception'.print_r($e, true));
            return '';
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
    /*     * *********************Méthodes d'instance************************* */
    public function postSave()
    {
        $order = 1;
        $_thisType = $this->getConfiguration('type');
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
                $qivivoCmd->setConfiguration('minValue', 5);
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
                $qivivoCmd->setConfiguration('minValue', 5);
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

            //actions:
            /*
            $qivivoCmd = $this->getCmd(null, 'SetOrdre');
            if (!is_object($qivivoCmd)) {
                $qivivoCmd = new qivivoCmd();
                $qivivoCmd->setName(__('SetOrdre', __FILE__));
                $qivivoCmd->setIsVisible(1);
                $qivivoCmd->setOrder($order);
                $order ++;
            }
            $qivivoCmd->setEqLogic_id($this->getId());
            $qivivoCmd->setLogicalId('SetOrdre');
            $qivivoCmd->setType('action');
            $qivivoCmd->setSubType('select');
            $qivivoCmd->setConfiguration('listValue','1|Arrêt;2|Hors-Gel;3|Eco;4|Confort -2;5|Confort -1;6|Confort');
            $qivivoCmd->save();
            */
        }

        if (in_array($_thisType, array('Module Chauffage', 'Thermostat')))
        {
            //common infos:
            $qivivoCmd = $this->getCmd(null, 'LastMsg');
            if (!is_object($qivivoCmd)) {
                $qivivoCmd = new qivivoCmd();
                $qivivoCmd->setName(__('LastMsg', __FILE__));
                $qivivoCmd->setIsVisible(0);
                if ($_thisType == 'Module Chauffage') $qivivoCmd->setIsVisible(1);
                $qivivoCmd->setIsHistorized(0);
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
    }

    public function refresh() {
        qivivo::refreshQivivoInfos();
    }

    public function preInsert() {

    }

    public function postInsert() {

    }

    public function preSave() {

    }

    public function preUpdate() {

    }

    public function postUpdate() {

    }

    public function preRemove() {

    }

    public function postRemove() {

    }

    /*
     * Non obligatoire mais ca permet de déclencher une action après modification de variable de configuration
    public static function postConfig_<Variable>() {
    }
     */

    /*
     * Non obligatoire mais ca permet de déclencher une action avant modification de variable de configuration
    public static function preConfig_<Variable>() {
    }
     */

    /*     * **********************Getteur Setteur*************************** */
}

class qivivoCmd extends cmd {
    /*     * *************************Attributs****************************** */


    /*     * ***********************Methode static*************************** */


    /*     * *********************Methode d'instance************************* */

    /*
     * Non obligatoire permet de demander de ne pas supprimer les commandes même si elles ne sont pas dans la nouvelle configuration de l'équipement envoyé en JS*/
    public function dontRemoveCmd()
    {
        return true;
    }

    public function execute($_options = array()) {
        log::add('qivivo', 'debug', 'execute()');
        if ($this->getLogicalId() == 'refresh') {
            qivivo::refreshQivivoInfos();
            return True;
        }

        $eqLogic = $this->getEqlogic();
        $_action = $this->getLogicalId();
        $_type = $eqLogic->getConfiguration('type');
        $_uuid = $eqLogic->getConfiguration('uuid');

        $_qivivo = $eqLogic->getAPI();

        if ($_type == 'Module Chauffage') {
            if ($_action == 'SetOrdre') {
                log::add('qivivo', 'debug', 'updateModule to '.$_options['select']);
                $result = $_qivivo->updateModule($_uuid, $_options['select']);
                log::add('qivivo', 'debug', print_r($result, true));
                return True;
            }
            return False;
        }

        if ($_type == 'Thermostat') {
            if ($_action == 'SetDuréeOrdre') {
                log::add('qivivo', 'debug', 'SetDuréeOrdre to '.$_options['slider']);
                $duree_temp = $eqLogic->getCmd(null, 'duree_temp');
                $duree_temp->event($_options['slider']);
                return True;
            }

            if ($_action == 'SetTemperature') {
                $temp = $_options['slider'];
                $info = $eqLogic->getCmd(null, 'duree_temp');
                $duree_temp = intval($info->execCmd());
                log::add('qivivo', 'debug', 'SetTemperature to '.$temp.' duree_temp: '.$duree_temp);
                $result = $_qivivo->setThermostatTemperature($temp, $duree_temp);
                log::add('qivivo', 'debug', print_r($result, true));
                return True;
            }

            if ($_action == 'Annule_Ordre_Temp') {
                log::add('qivivo', 'debug', 'Annule_Ordre_Temp');
                $result = $_qivivo->cancelThermostatTemperature();
                log::add('qivivo', 'debug', print_r($result, true));
                return True;
            }

            if ($_action == 'SetTempAbsence') {
                $result = $_qivivo->setSetting('absence_temperature', $_options['slider']);
                log::add('qivivo', 'debug', print_r($result, true));
                return True;
            }

            if ($_action == 'SetTempHorsGel') {
                $result = $_qivivo->setSetting('frost_protection_temperature', $_options['slider']);
                log::add('qivivo', 'debug', print_r($result, true));
                return True;
            }

            if ($_action == 'SetTempNuit') {
                $result = $_qivivo->setSetting('night_temperature', $_options['slider']);
                log::add('qivivo', 'debug', print_r($result, true));
                return True;
            }

            if ($_action == 'SetTempPres1') {
                $result = $_qivivo->setSetting('presence_temperature_1', $_options['slider']);
                log::add('qivivo', 'debug', print_r($result, true));
                return True;
            }

            if ($_action == 'SetTempPres2') {
                $result = $_qivivo->setSetting('presence_temperature_2', $_options['slider']);
                log::add('qivivo', 'debug', print_r($result, true));
                return True;
            }

            if ($_action == 'SetTempPres3') {
                $result = $_qivivo->setSetting('presence_temperature_3', $_options['slider']);
                log::add('qivivo', 'debug', print_r($result, true));
                return True;
            }

            if ($_action == 'SetTempPres4') {
                $result = $_qivivo->setSetting('presence_temperature_4', $_options['slider']);
                log::add('qivivo', 'debug', print_r($result, true));
                return True;
            }
            return False;
        }
    }

    /*     * **********************Getteur Setteur*************************** */
}


