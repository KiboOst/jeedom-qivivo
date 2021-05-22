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

require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
if (!class_exists('qivivoAPI')) {
    require_once dirname(__FILE__) . '/../../3rdparty/comapAPI.php';
}

class qivivo extends eqLogic {
    public static $_widgetPossibility = array('custom' => true, 'custom::layout' => false);

    public static function logger($str = '', $level = 'debug') {
        if (is_array($str)) $str = json_encode($str);
        $function_name = debug_backtrace(false, 2)[1]['function'];
        $class_name = debug_backtrace(false, 2)[1]['class'];
        $msg = '['.$class_name.'] <'. $function_name .'> '.$str;
        log::add('qivivo', $level, $msg);
    }

    public static function getCustomAPI($_typeCmd='info', $_action=null, $_options=null, $_msg=null) {
        $login = config::byKey('login', 'qivivo');
        $pass = config::byKey('pass', 'qivivo');
        $_customQivivo = new qivivoAPI($login, $pass);

        if (isset($_customQivivo->error))
        {
            $_apiError = $_customQivivo->error;
            qivivo::logger('custom Qivivo API error: '.$_apiError, 'warning');
            if ($_typeCmd == 'action')
            {
                if ($_msg)
                {
                    $_options['error'] = $_msg;
                }
                if ($_action)
                {
                    qivivo::checkAndRetryAction($_action, $_options);
                }
            }
            return False;
        }
        return $_customQivivo;
    }

    //called from API getter if action, set either cron to retry or actionOnError
    public static function checkAndRetryAction($_action, $_options) {
        $_doRepeat = config::byKey('repeatOnActionError', 'qivivo', 0);
        qivivo::logger('repeatOnActionError: '.$_doRepeat);

        if ($_doRepeat != 1)
        {
            if (isset($_options['error'])) $msg = $_options['error'];
            else $msg = $_action->getLogicalId();
            qivivo::doActionsOnError($msg);
            return;
        }

        if (!isset($_options['retried']))
        {
            $_options['retried'] = 1;
            $cron = new cron();
            $cron->setClass(__CLASS__);
            $cron->setFunction('retryAction');
            $cron->setOption(array('id' => $_action->getId(), 'options' => $_options));
            $cron->setSchedule(cron::convertDateToCron(strtotime('now')+90));
            $cron->setOnce(1);
            $cron->save();
            qivivo::logger('cron created!');
        }
        else
        {
            if (isset($_options['error'])) $msg = $_options['error'];
            else $msg = $_action->getLogicalId();
            qivivo::doActionsOnError($msg);
        }
    }

    public static function doActionsOnError($_msg = null) {
        qivivo::logger('Echec de commande action: '.$_msg, 'error');

        $actionsOnError = config::byKey('actionsOnError', 'qivivo');
        qivivo::logger('doActionsOnError: '.json_encode($actionsOnError));

        foreach ($actionsOnError as $cmdAr) {
            qivivo::logger('doActionsOnError: exec: '.json_encode($cmdAr));
            $options = $cmdAr['options'];
            if ($options['enable'] == 1)
            {
                if (isset($options['message']) && isset($_msg))
                {
                    $options['message'] = str_replace('#message#', $_msg , $options['message']);
                }
                $cmdId = $cmdAr['cmd'];
                $cmd = cmd::byId($cmdId);
                $cmd->execCmd($options);
            }
        }
    }

    //called from cron engine to repeat an action
    public static function retryAction($cronOption) {
        qivivo::logger('doActionsOnError: exec: '.json_encode($cronOption));
        $cmd = cmd::byId($cronOption['id']);
        $_options = $cronOption['options'];

        $cmd->execCmd($_options);
    }

    //ajax call from plugin configuration
    public static function syncWithQivivo() {
        qivivo::logger('starting...');
        $_fullQivivo = qivivo::getCustomAPI();
        if ($_fullQivivo === false)
        {
            qivivo::logger('could not get customAPI, ending.');
            return;
        }

        //store multizone or not:
        $result = $_fullQivivo->isMultizone()['result'];
        $isMultizone = $result ? 1 : 0;
        config::save('isMultizone', $isMultizone, 'qivivo');
        qivivo::logger('isMultizone: '.config::byKey('isMultizone', 'qivivo', -1));

        //store devices:
        $devices = $_fullQivivo->getFullDevices()['result'];
        foreach ($devices as $serial => $device) {
            //do not support radiator_valve yet:
            if (!in_array($device['model'], array('thermostat', 'heating_module', 'gateway')))
            {
                continue;
            }

            $eqLogic = eqLogic::byLogicalId($serial, 'qivivo');
            if (!is_object($eqLogic))
            {
                $eqLogic = new qivivo();
                $eqLogic->setEqType_name('qivivo');
                $eqLogic->setIsVisible(1);
                $eqLogic->setIsEnable(1);
            }

            $type = $device['model'];
            if ($type == 'thermostat')
            {
                if (!isset($device['zone']))
                {
                    continue;
                }
                $type = 'Thermostat';
                $eqLogic->setName($type);
            }
            if ($type == 'heating_module')
            {
                if (!isset($device['zone']) && $device['main_heating_module'] === false)
                {
                    continue;
                }
                $type = 'Module Chauffage';
                if ($device['main_heating_module'])
                {
                    $eqLogic->setConfiguration('zone_name', 'Thermostat');
                    $eqLogic->setConfiguration('isModuleThermostat', 1);
                    $eqLogic->setName('Zone Thermostat');
                }
                else
                {
                    $eqLogic->setConfiguration('zone_name', $device['zone']);
                    $eqLogic->setConfiguration('isModuleThermostat', 0);
                    $eqLogic->setName('Zone '.$device['zone']);
                }
            }
            if ($type == 'gateway')
            {
                $type = 'Passerelle';
                $eqLogic->setIsVisible(0);
                $eqLogic->setName($type);
            }

            $eqLogic->setConfiguration('serial', $serial);
            $eqLogic->setConfiguration('type', $type);
            $eqLogic->setCategory('heating', 1);
            $eqLogic->setLogicalId($serial);
            $eqLogic->save();
        }
        qivivo::logger('done!');
    }

    //called from cron5 or cron15 to refresh infos
    public static function refreshQivivoInfos() {
        try {
            qivivo::logger('refresh');
            $_fullQivivo = qivivo::getCustomAPI();
            if ($_fullQivivo === false)
            {
                qivivo::logger('could not get customAPI, ending.');
                return;
            }

            //store multizone or not. In monozone config, only one zone, get and change schedules!
            $result = $_fullQivivo->isMultizone()['result'];
            $isMultizone = $result ? 1 : 0;
            config::save('isMultizone', $isMultizone, 'qivivo');
            qivivo::logger('isMultizone: '.$isMultizone);

            //get program list:
            if ($isMultizone)
            {
                $currentProgram = $_fullQivivo->getCurrentProgram()['result']['title'];
                $programs = $_fullQivivo->getPrograms()['result'];
                $ProgramsList = [];
                foreach ($programs as $program) {
                    array_push($ProgramsList, ['id'=>$program['id'], 'title'=>$program['title']]);
                }
            }
            else
            {
                $currentProgram = $_fullQivivo->getCurrentSchedule()['result']['title'];
                $programs = $_fullQivivo->getSchedules()['result'];
                $ProgramsList = [];
                foreach ($programs as $program) {
                    array_push($ProgramsList, ['id'=>$program['id'], 'title'=>$program['title']]);
                }
            }
            config::save('programList', $ProgramsList, 'qivivo');
            qivivo::logger('ProgramsList: '.json_encode($ProgramsList));


            $devices = $_fullQivivo->getFullDevices()['result'];
            qivivo::logger('devices: '.json_encode($devices));
            foreach ($devices as $serial => $device) {
                $_type = $device['model'];
                $eqLogic = eqLogic::byLogicalId($serial, 'qivivo');
                if (!is_object($eqLogic))
                {
                    continue;
                }

                if (isset($device['zone']))
                {
                    qivivo::logger('type: '.$_type.' serial: '.$serial.' zone: '.$device['zone']);
                    $eqLogic->setConfiguration('zone_name', $device['zone']);
                }
                else
                {
                    qivivo::logger('type: '.$_type.' serial: '.$serial.' zone: Thermostat');
                    //$eqLogic->setConfiguration('zone_name', 'Thermostat');
                }

                if ($_type == 'gateway')
                {
                    $firmware_version = $device['firmware_version'];
                    if (!is_null($firmware_version))
                    {
                        $eqLogic->checkAndUpdateCmd('firmware_version', $firmware_version);
                    }

                    $last_communication = $device['last_communication_time'];
                    $last_communication = date("d-m-Y H:i", strtotime($last_communication));
                    if (!is_null($last_communication))
                    {
                        $eqLogic->checkAndUpdateCmd('last_communication', $last_communication);
                    }
                }

                if ($_type == 'thermostat')
                {
                    //default:
                    $eqLogic->getCmd(null, 'duree_temp')->event(120);

                    $last_communication = $device['last_communication_time'];
                    $last_communication = date("d-m-Y H:i", strtotime($last_communication));
                    if (!is_null($last_communication))
                    {
                        $eqLogic->checkAndUpdateCmd('last_communication', $last_communication);
                    }
                    $firmware_version = $device['firmware_version'];
                    if (!is_null($firmware_version))
                    {
                        $eqLogic->checkAndUpdateCmd('firmware_version', $firmware_version);
                    }
                    $battery_percent = $device['voltage_percent'];
                    if (!is_null($battery_percent))
                    {
                        $eqLogic->checkAndUpdateCmd('battery', $battery_percent);
                        $eqLogic->batteryStatus($battery_percent);
                    }

                    $eqLogic->checkAndUpdateCmd('current_program', $currentProgram);

                    $zoneName = $device['zone'];
                    $zones = $_fullQivivo->getZones();
                    foreach ($_fullQivivo->_houseData['heating']['zones'] as $zone) {
                        if ($zone['title'] == $zoneName)
                        {
                            qivivo::logger('zone: '.json_encode($zone));
                            $temperature = $zone['temperature'];
                            qivivo::logger('temperature: '.$temperature);
                            if (!is_null($temperature))
                            {
                                $eqLogic->checkAndUpdateCmd('temperature', round($temperature, 1));
                            }

                            $humidity = $zone['humidity'];
                            qivivo::logger('humidity: '.$humidity);
                            if (!is_null($humidity))
                            {
                                $eqLogic->checkAndUpdateCmd('humidity', $humidity);
                            }

                            $lastPresence = date("d-m-Y H:i", strtotime($zone['last_presence_detected']));
                            qivivo::logger('lastPresence: '.$lastPresence);
                            $eqLogic->checkAndUpdateCmd('last_presence', $lastPresence);

                            $heating = 0;
                            $status = $zone['heating_status'];
                            if ($status != 'cooling')
                            {
                                $heating = 1;
                            }
                            $eqLogic->checkAndUpdateCmd('heating', $heating);

                            $hasTimeOrder = $_fullQivivo->hasTimeOrder($zoneName)['result'];
                            $eqLogic->checkAndUpdateCmd('hasTimeOrder', $hasTimeOrder);
                            break;
                        }
                    }

                    $settings = $_fullQivivo->getTempSettings()['result'];
                    qivivo::logger('settings: '.json_encode($settings));
                    $eqLogic->getCmd(null, 'frost_protection_temperature')->event($settings['custom_temperatures']['frost_protection']);
                    $eqLogic->getCmd(null, 'absence_temperature')->event($settings['custom_temperatures']['away']);
                    $eqLogic->getCmd(null, 'night_temperature')->event($settings['custom_temperatures']['night']);
                    $eqLogic->getCmd(null, 'presence_temperature_1')->event($settings['custom_temperatures']['presence_1']);
                    $eqLogic->getCmd(null, 'presence_temperature_2')->event($settings['custom_temperatures']['presence_2']);
                    $eqLogic->getCmd(null, 'presence_temperature_3')->event($settings['custom_temperatures']['presence_3']);
                    $eqLogic->getCmd(null, 'presence_temperature_4')->event($settings['custom_temperatures']['presence_4']);

                    $order = $device['order'];
                    if ($order == 'frost_protection') $order = $settings['custom_temperatures']['frost_protection'];
                    if ($order == 'away') $order = $settings['custom_temperatures']['away'];
                    if ($order == 'night') $order = $settings['custom_temperatures']['night'];
                    if ($order == 'presence_1') $order = $settings['custom_temperatures']['presence_1'];
                    if ($order == 'presence_2') $order = $settings['custom_temperatures']['presence_2'];
                    if ($order == 'presence_3') $order = $settings['custom_temperatures']['presence_3'];
                    if ($order == 'presence_4') $order = $settings['custom_temperatures']['presence_4'];
                    $eqLogic->checkAndUpdateCmd('temperature_order', $order);

                    $qivivoCmd = $eqLogic->getCmd(null, 'set_program');
                    if (count($ProgramsList) == 0)
                    {
                        $listValue = 'Aucun|Aucun programme;';
                    }
                    else
                    {
                        $listValue = '';
                        foreach ($ProgramsList as $program) {
                            $pName = $program['title'];
                            $listValue .= $pName.'|'.$pName.';';
                        }
                    }
                    $qivivoCmd->setConfiguration('listValue', $listValue);
                    $qivivoCmd->save();
                }

                if ($_type == 'heating_module')
                {
                    $order = false;
                    $isMainModule = isset($device['order']) ? false : true;
                    if (!$isMainModule)
                    {
                        $order = $device['order'];
                        qivivo::logger('order: '.json_encode($order));
                    }

                    $firmware_version = $device['firmware_version'];
                    if (!is_null($firmware_version))
                    {
                        $eqLogic->checkAndUpdateCmd('firmware_version', $firmware_version);
                    }

                    $last_communication = $device['last_communication_time'];
                    $last_communication = date("d-m-Y H:i", strtotime($last_communication));
                    if (!is_null($last_communication))
                    {
                        $eqLogic->checkAndUpdateCmd('last_communication', $last_communication);
                    }

                    $order_num = 0;
                    if ($order == 'stop')
                    {
                        $order_num = 0;
                        $order = 'Arrêt';
                    }
                    if ($order == 'frost_protection')
                    {
                        $order_num = 1;
                        $order = 'Hors-Gel';
                    }
                    if ($order == 'eco')
                    {
                        $order_num = 2;
                        $order = 'Eco';
                    }
                    if ($order == 'comfort_minus2')
                    {
                        $order_num = 3;
                        $order = 'Confort -2';
                    }
                    if ($order == 'comfort_minus1')
                    {
                        $order_num = 4;
                        $order = 'Confort -1';
                    }
                    if ($order == 'comfort')
                    {
                        $order_num = 5;
                        $order = 'Confort';
                    }
                    if ($isMainModule)
                    {
                        $eqLogic->setConfiguration('isModuleThermostat', 1);
                    }
                    else
                    {
                        $eqLogic->setConfiguration('isModuleThermostat', 0);
                        if (!is_null($order)) $eqLogic->checkAndUpdateCmd('module_order', $order);
                        if (!is_null($order_num)) $eqLogic->checkAndUpdateCmd('order_num', $order_num);

                        $zoneName = $device['zone'];
                        $hasTimeOrder = $_fullQivivo->hasTimeOrder($zoneName)['result'];
                        $eqLogic->checkAndUpdateCmd('hasTimeOrder', $hasTimeOrder);
                    }
                }

                $eqLogic->save();
                $eqLogic->refreshWidget();
            }
            qivivo::logger('refresh end');
        } catch (Exception $e) {
            qivivo::logger('Exception: '.$e->getMessage(), 'warning');
            return;
        }
    }

    public static function cron5($_eqlogic_id = null) {
        qivivo::logger();
        qivivo::refreshQivivoInfos();
    }

    public static function cron15($_eqlogic_id = null) {
        qivivo::logger();

        //no both cron5 and cron15 enabled:
        if (config::byKey('functionality::cron5::enable', 'qivivo', 0) == 1)
        {
            config::save('functionality::cron15::enable', 0, 'qivivo');
            return;
        }
        qivivo::refreshQivivoInfos();
    }

    //log both APIs data to debug user configuration
    public static function getDebugInfos() {
        //custom API:
        $_fullQivivo = qivivo::getCustomAPI('action', null, $_options, $message);
        if ($_fullQivivo === false)
        {
            log::add('qivivo_debug', 'error', 'getCustomAPI() error!');
            return;
        }

        $data = json_encode($_fullQivivo, JSON_PRETTY_PRINT);
        log::add('qivivo_debug', 'error', 'getCustomAPI: '.$data);

        $getFullDevices = $_fullQivivo->getFullDevices();
        $data = json_encode($getFullDevices, JSON_PRETTY_PRINT);
        log::add('qivivo_debug', 'error', 'customAPI.getFullDevices: '.$data);

        $getPrograms = $_fullQivivo->getPrograms();
        log::add('qivivo_debug', 'error', 'customAPI.getPrograms: '.json_encode($getPrograms));
    }

    public function preSave() {
        if ($this->getConfiguration('type') == 'Thermostat')
        {
            $this->setConfiguration('battery_type', '3x1.5V AAA');
        }
    }

    public function postSave() {
        $order = 1;
        $_thisType = $this->getConfiguration('type');
        $_serial = $this->getConfiguration('serial');
        $eqLogic = eqLogic::byLogicalId($_serial, 'qivivo');
        qivivo::logger($_thisType.' | '.$_serial);

        if (in_array($_thisType, array('Thermostat')))
        {
            //infos:
            $qivivoCmd = $this->getCmd(null, 'current_program');
            if (!is_object($qivivoCmd))
            {
                $qivivoCmd = new qivivoCmd();
                $qivivoCmd->setName(__('Programme', __FILE__));
                $qivivoCmd->setIsVisible(1);
                $qivivoCmd->setIsHistorized(0);
                $qivivoCmd->setOrder($order);
                $order ++;
            }
            $qivivoCmd->setEqLogic_id($this->getId());
            $qivivoCmd->setUnite('°C');
            $qivivoCmd->setLogicalId('current_program');
            $qivivoCmd->setType('info');
            $qivivoCmd->setSubType('string');
            $qivivoCmd->save();

            $qivivoCmd = $this->getCmd(null, 'temperature_order');
            if (!is_object($qivivoCmd))
            {
                $qivivoCmd = new qivivoCmd();
                $qivivoCmd->setName(__('Consigne', __FILE__));
                $qivivoCmd->setIsVisible(1);
                $qivivoCmd->setIsHistorized(1);
                $qivivoCmd->setConfiguration('repeatEventManagement', 'always');
                $qivivoCmd->setConfiguration('historizeMode', 'none');
                $qivivoCmd->setConfiguration('historyPurge', '-1 year');
                $qivivoCmd->setConfiguration('minValue', 0);
                $qivivoCmd->setConfiguration('maxValue', 35);
                $qivivoCmd->setOrder($order);
                $order ++;
            }
            $qivivoCmd->setEqLogic_id($this->getId());
            $qivivoCmd->setUnite('°C');
            $qivivoCmd->setLogicalId('temperature_order');
            $qivivoCmd->setType('info');
            $qivivoCmd->setSubType('numeric');
            $qivivoCmd->save();

            $qivivoCmd = $this->getCmd(null, 'hasTimeOrder');
            if (!is_object($qivivoCmd))
            {
                $qivivoCmd = new qivivoCmd();
                $qivivoCmd->setName(__('Temporaire', __FILE__));
                $qivivoCmd->setIsVisible(0);
                $qivivoCmd->setIsHistorized(0);
                $qivivoCmd->setOrder($order);
                $order ++;
            }
            $qivivoCmd->setEqLogic_id($this->getId());
            $qivivoCmd->setLogicalId('hasTimeOrder');
            $qivivoCmd->setType('info');
            $qivivoCmd->setSubType('binary');
            $qivivoCmd->save();

            $qivivoCmd = $this->getCmd(null, 'temperature');
            if (!is_object($qivivoCmd))
            {
                $qivivoCmd = new qivivoCmd();
                $qivivoCmd->setName(__('Temperature', __FILE__));
                $qivivoCmd->setIsVisible(1);
                $qivivoCmd->setIsHistorized(1);
                $qivivoCmd->setConfiguration('repeatEventManagement', 'always');
                $qivivoCmd->setConfiguration('historizeMode', 'none');
                $qivivoCmd->setConfiguration('historyPurge', '-1 year');
                $qivivoCmd->setConfiguration('minValue', 0);
                $qivivoCmd->setConfiguration('maxValue', 35);
                $qivivoCmd->setOrder($order);
                $order ++;
            }
            $qivivoCmd->setEqLogic_id($this->getId());
            $qivivoCmd->setUnite('°C');
            $qivivoCmd->setLogicalId('temperature');
            $qivivoCmd->setType('info');
            $qivivoCmd->setSubType('numeric');
            $qivivoCmd->save();

            $qivivoCmd = $this->getCmd(null, 'heating');
            if (!is_object($qivivoCmd))
            {
                $qivivoCmd = new qivivoCmd();
                $qivivoCmd->setName(__('Chauffe', __FILE__));
                $qivivoCmd->setIsVisible(0);
                $qivivoCmd->setIsHistorized(1);
                $qivivoCmd->setConfiguration('repeatEventManagement', 'always');
                $qivivoCmd->setConfiguration('historizeMode', 'none');
                $qivivoCmd->setConfiguration('historyPurge', '-1 year');
                $qivivoCmd->setConfiguration('minValue', 0);
                $qivivoCmd->setConfiguration('maxValue', 35);
                $qivivoCmd->setOrder($order);
                $order ++;
            }
            $qivivoCmd->setEqLogic_id($this->getId());
            $qivivoCmd->setLogicalId('heating');
            $qivivoCmd->setType('info');
            $qivivoCmd->setSubType('numeric');
            $qivivoCmd->save();

            $qivivoCmd = $this->getCmd(null, 'humidity');
            if (!is_object($qivivoCmd))
            {
                $qivivoCmd = new qivivoCmd();
                $qivivoCmd->setName(__('Humidité', __FILE__));
                $qivivoCmd->setIsVisible(1);
                $qivivoCmd->setIsHistorized(1);
                $qivivoCmd->setConfiguration('repeatEventManagement', 'always');
                $qivivoCmd->setConfiguration('historizeMode', 'none');
                $qivivoCmd->setConfiguration('historyPurge', '-1 year');
                $qivivoCmd->setConfiguration('minValue', 0);
                $qivivoCmd->setConfiguration('maxValue', 100);
                $qivivoCmd->setOrder($order);
                $order ++;
            }
            $qivivoCmd->setEqLogic_id($this->getId());
            $qivivoCmd->setUnite('%');
            $qivivoCmd->setLogicalId('humidity');
            $qivivoCmd->setType('info');
            $qivivoCmd->setSubType('numeric');
            $qivivoCmd->save();

            $qivivoCmd = $this->getCmd(null, 'last_presence');
            if (!is_object($qivivoCmd))
            {
                $qivivoCmd = new qivivoCmd();
                $qivivoCmd->setName(__('DernierePresence', __FILE__));
                $qivivoCmd->setIsVisible(1);
                $qivivoCmd->setIsHistorized(0);
                $qivivoCmd->setDisplay('icon', '<i class="fa fa-smile-o"></i>');
                $qivivoCmd->setOrder($order);
                $order ++;
            }
            $qivivoCmd->setEqLogic_id($this->getId());
            $qivivoCmd->setLogicalId('last_presence');
            $qivivoCmd->setType('info');
            $qivivoCmd->setSubType('string');
            $qivivoCmd->save();

            $qivivoCmd = $this->getCmd(null, 'frost_protection_temperature');
            if (!is_object($qivivoCmd))
            {
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
            if (!is_object($qivivoCmd))
            {
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
            if (!is_object($qivivoCmd))
            {
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
            if (!is_object($qivivoCmd))
            {
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
            if (!is_object($qivivoCmd))
            {
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
            if (!is_object($qivivoCmd))
            {
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
            if (!is_object($qivivoCmd))
            {
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
            if (!is_object($qivivoCmd))
            {
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

            $qivivoCmd = $this->getCmd(null, 'battery');
            if (!is_object($qivivoCmd))
            {
                $qivivoCmd = new qivivoCmd();
                $qivivoCmd->setName(__('Batterie', __FILE__));
                $qivivoCmd->setIsVisible(1);
                $qivivoCmd->setIsHistorized(1);
                $qivivoCmd->setConfiguration('minValue', 0);
                $qivivoCmd->setConfiguration('maxValue', 100);
                $qivivoCmd->setOrder($order);
                $order ++;
            }
            $qivivoCmd->setEqLogic_id($this->getId());
            $qivivoCmd->setUnite('%');
            $qivivoCmd->setConfiguration('minValue', 0);
            $qivivoCmd->setConfiguration('maxValue', 100);
            $qivivoCmd->setLogicalId('battery');
            $qivivoCmd->setType('info');
            $qivivoCmd->setSubType('numeric');
            $qivivoCmd->save();

            //actions:
            $qivivoCmd = $this->getCmd(null, 'set_time_order');
            if (!is_object($qivivoCmd))
            {
                $qivivoCmd = new qivivoCmd();
                $qivivoCmd->setName(__('SetDuréeOrdre', __FILE__));
                $qivivoCmd->setIsVisible(0);
                $qivivoCmd->setOrder($order);
                $order ++;
            }
            $qivivoCmd->setEqLogic_id($this->getId());
            $qivivoCmd->setLogicalId('set_time_order');
            $qivivoCmd->setType('action');
            $qivivoCmd->setSubType('slider');
            $qivivoCmd->save();

            $qivivoCmd = $this->getCmd(null, 'set_temperature_order');
            if (!is_object($qivivoCmd))
            {
                $qivivoCmd = new qivivoCmd();
                $qivivoCmd->setName(__('SetTempérature', __FILE__));
                $qivivoCmd->setIsVisible(0);
                $qivivoCmd->setOrder($order);
                $order ++;
            }
            $qivivoCmd->setEqLogic_id($this->getId());
            $qivivoCmd->setLogicalId('set_temperature_order');
            $qivivoCmd->setType('action');
            $qivivoCmd->setSubType('slider');
            $qivivoCmd->save();

            $qivivoCmd = $this->getCmd(null, 'cancel_time_order');
            if (!is_object($qivivoCmd))
            {
                $qivivoCmd = new qivivoCmd();
                $qivivoCmd->setName(__('Annule_Ordre_Temp', __FILE__));
                $qivivoCmd->setIsVisible(0);
                $qivivoCmd->setOrder($order);
                $order ++;
            }
            $qivivoCmd->setEqLogic_id($this->getId());
            $qivivoCmd->setLogicalId('cancel_time_order');
            $qivivoCmd->setType('action');
            $qivivoCmd->setSubType('other');
            $qivivoCmd->save();

            $qivivoCmd = $this->getCmd(null, 'set_absence_temperature');
            if (!is_object($qivivoCmd))
            {
                $qivivoCmd = new qivivoCmd();
                $qivivoCmd->setName(__('SetTempAbsence', __FILE__));
                $qivivoCmd->setIsVisible(0);
                $qivivoCmd->setOrder($order);
                $order ++;
            }
            $qivivoCmd->setEqLogic_id($this->getId());
            $qivivoCmd->setLogicalId('set_absence_temperature');
            $qivivoCmd->setType('action');
            $qivivoCmd->setSubType('slider');
            $qivivoCmd->save();

            $qivivoCmd = $this->getCmd(null, 'set_frost_temperature');
            if (!is_object($qivivoCmd))
            {
                $qivivoCmd = new qivivoCmd();
                $qivivoCmd->setName(__('SetTempHorsGel', __FILE__));
                $qivivoCmd->setIsVisible(0);
                $qivivoCmd->setOrder($order);
                $order ++;
            }
            $qivivoCmd->setEqLogic_id($this->getId());
            $qivivoCmd->setLogicalId('set_frost_temperature');
            $qivivoCmd->setType('action');
            $qivivoCmd->setSubType('slider');
            $qivivoCmd->save();

            $qivivoCmd = $this->getCmd(null, 'set_night_temperature');
            if (!is_object($qivivoCmd))
            {
                $qivivoCmd = new qivivoCmd();
                $qivivoCmd->setName(__('SetTempNuit', __FILE__));
                $qivivoCmd->setIsVisible(0);
                $qivivoCmd->setOrder($order);
                $order ++;
            }
            $qivivoCmd->setEqLogic_id($this->getId());
            $qivivoCmd->setLogicalId('set_night_temperature');
            $qivivoCmd->setType('action');
            $qivivoCmd->setSubType('slider');
            $qivivoCmd->save();

            $qivivoCmd = $this->getCmd(null, 'set_presence_temperature_1');
            if (!is_object($qivivoCmd))
            {
                $qivivoCmd = new qivivoCmd();
                $qivivoCmd->setName(__('SetTempPres1', __FILE__));
                $qivivoCmd->setIsVisible(0);
                $qivivoCmd->setOrder($order);
                $order ++;
            }
            $qivivoCmd->setEqLogic_id($this->getId());
            $qivivoCmd->setLogicalId('set_presence_temperature_1');
            $qivivoCmd->setType('action');
            $qivivoCmd->setSubType('slider');
            $qivivoCmd->save();

            $qivivoCmd = $this->getCmd(null, 'set_presence_temperature_2');
            if (!is_object($qivivoCmd))
            {
                $qivivoCmd = new qivivoCmd();
                $qivivoCmd->setName(__('SetTempPres2', __FILE__));
                $qivivoCmd->setIsVisible(0);
                $qivivoCmd->setOrder($order);
                $order ++;
            }
            $qivivoCmd->setEqLogic_id($this->getId());
            $qivivoCmd->setLogicalId('set_presence_temperature_2');
            $qivivoCmd->setType('action');
            $qivivoCmd->setSubType('slider');
            $qivivoCmd->save();

            $qivivoCmd = $this->getCmd(null, 'set_presence_temperature_3');
            if (!is_object($qivivoCmd))
            {
                $qivivoCmd = new qivivoCmd();
                $qivivoCmd->setName(__('SetTempPres3', __FILE__));
                $qivivoCmd->setIsVisible(0);
                $qivivoCmd->setOrder($order);
                $order ++;
            }
            $qivivoCmd->setEqLogic_id($this->getId());
            $qivivoCmd->setLogicalId('set_presence_temperature_3');
            $qivivoCmd->setType('action');
            $qivivoCmd->setSubType('slider');
            $qivivoCmd->save();

            $qivivoCmd = $this->getCmd(null, 'set_presence_temperature_4');
            if (!is_object($qivivoCmd))
            {
                $qivivoCmd = new qivivoCmd();
                $qivivoCmd->setName(__('SetTempPres4', __FILE__));
                $qivivoCmd->setIsVisible(0);
                $qivivoCmd->setOrder($order);
                $order ++;
            }
            $qivivoCmd->setEqLogic_id($this->getId());
            $qivivoCmd->setLogicalId('set_presence_temperature_4');
            $qivivoCmd->setType('action');
            $qivivoCmd->setSubType('slider');
            $qivivoCmd->save();

            $qivivoCmd = $this->getCmd(null, 'set_plus_one');
            if (!is_object($qivivoCmd))
            {
                $qivivoCmd = new qivivoCmd();
                $qivivoCmd->setName(__('+1', __FILE__));
                $qivivoCmd->setIsVisible(1);
                $qivivoCmd->setDisplay('icon', '<i class="fa fa-chevron-up";></i>');
                $qivivoCmd->setOrder($order);
                $order ++;
            }
            $qivivoCmd->setEqLogic_id($this->getId());
            $qivivoCmd->setLogicalId('set_plus_one');
            $qivivoCmd->setType('action');
            $qivivoCmd->setSubType('other');
            $qivivoCmd->save();

            $qivivoCmd = $this->getCmd(null, 'set_minus_one');
            if (!is_object($qivivoCmd))
            {
                $qivivoCmd = new qivivoCmd();
                $qivivoCmd->setName(__('-1', __FILE__));
                $qivivoCmd->setIsVisible(1);
                $qivivoCmd->setDisplay('icon', '<i class="fa fa-chevron-down"></i>');
                $qivivoCmd->setOrder($order);
                $order ++;
            }
            $qivivoCmd->setEqLogic_id($this->getId());
            $qivivoCmd->setLogicalId('set_minus_one');
            $qivivoCmd->setType('action');
            $qivivoCmd->setSubType('other');
            $qivivoCmd->save();

            $qivivoCmd = $this->getCmd(null, 'set_program');
            if (!is_object($qivivoCmd))
            {
                $qivivoCmd = new qivivoCmd();
                $qivivoCmd->setName(__('SetProgram', __FILE__));
                $qivivoCmd->setIsVisible(1);
                $qivivoCmd->setOrder($order);
                $order ++;
            }
            $qivivoCmd->setEqLogic_id($this->getId());
            $qivivoCmd->setLogicalId('set_program');
            $qivivoCmd->setType('action');
            $qivivoCmd->setSubType('select');
            $qivivoCmd->save();
        }

        if (in_array($_thisType, array('Module Chauffage')))
        {
            //infos:
            if ($this->getConfiguration('isModuleThermostat') == 0)
            {
                $qivivoCmd = $this->getCmd(null, 'module_order');
                if (!is_object($qivivoCmd))
                {
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
                $qivivoCmd->setLogicalId('module_order');
                $qivivoCmd->setType('info');
                $qivivoCmd->setSubType('string');
                $qivivoCmd->save();

                $qivivoCmd = $this->getCmd(null, 'order_num');
                if (!is_object($qivivoCmd))
                {
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
                $qivivoCmd->setLogicalId('order_num');
                $qivivoCmd->setType('info');
                $qivivoCmd->setSubType('numeric');
                $qivivoCmd->save();

                $qivivoCmd = $this->getCmd(null, 'hasTimeOrder');
                if (!is_object($qivivoCmd))
                {
                    $qivivoCmd = new qivivoCmd();
                    $qivivoCmd->setName(__('Temporaire', __FILE__));
                    $qivivoCmd->setIsVisible(0);
                    $qivivoCmd->setIsHistorized(0);
                    $qivivoCmd->setOrder($order);
                    $order ++;
                }
                $qivivoCmd->setEqLogic_id($this->getId());
                $qivivoCmd->setLogicalId('hasTimeOrder');
                $qivivoCmd->setType('info');
                $qivivoCmd->setSubType('binary');
                $qivivoCmd->save();
            }

            //actions:
            if ($this->getConfiguration('isModuleThermostat') == 0)
            {
                $qivivoCmd = $this->getCmd(null, 'set_order');
                if (!is_object($qivivoCmd))
                {
                    $qivivoCmd = new qivivoCmd();
                    $qivivoCmd->setName(__('Set Ordre', __FILE__));
                    $qivivoCmd->setIsVisible(1);
                    $qivivoCmd->setOrder($order);
                    $order ++;
                }
                $qivivoCmd->setEqLogic_id($this->getId());
                $qivivoCmd->setLogicalId('set_order');
                $qivivoCmd->setType('action');
                $qivivoCmd->setSubType('select');
                $qivivoCmd->setConfiguration('listValue','0|Arrêt;1|Hors-Gel;2|Eco;3|Confort -2;4|Confort -1;5|Confort');
                $qivivoCmd->save();

                $qivivoCmd = $this->getCmd(null, 'cancel_time_order');
                if (!is_object($qivivoCmd))
                {
                    $qivivoCmd = new qivivoCmd();
                    $qivivoCmd->setName(__('Annule_Ordre_Temp', __FILE__));
                    $qivivoCmd->setIsVisible(0);
                    $qivivoCmd->setOrder($order);
                    $order ++;
                }
                $qivivoCmd->setEqLogic_id($this->getId());
                $qivivoCmd->setLogicalId('cancel_time_order');
                $qivivoCmd->setType('action');
                $qivivoCmd->setSubType('other');
                $qivivoCmd->save();
            }
        }

        if (in_array($_thisType, array('Module Chauffage', 'Thermostat', 'Passerelle')))
        {
            //common infos:
            $qivivoCmd = $this->getCmd(null, 'last_communication');
            if (!is_object($qivivoCmd))
            {
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
            $qivivoCmd->setLogicalId('last_communication');
            $qivivoCmd->setType('info');
            $qivivoCmd->setSubType('string');
            $qivivoCmd->save();

            $qivivoCmd = $this->getCmd(null, 'firmware_version');
            if (!is_object($qivivoCmd))
            {
                $qivivoCmd = new qivivoCmd();
                $qivivoCmd->setName(__('Firmware', __FILE__));
                $qivivoCmd->setIsVisible(0);
                $qivivoCmd->setIsHistorized(0);
                $qivivoCmd->setOrder($order);
                $order ++;
            }
            $qivivoCmd->setEqLogic_id($this->getId());
            $qivivoCmd->setLogicalId('firmware_version');
            $qivivoCmd->setType('info');
            $qivivoCmd->setSubType('string');
            $qivivoCmd->save();
        }

        if (in_array($_thisType, array('Passerelle')))
        {
            $qivivoCmd = $this->getCmd(null, 'debug');
            if (!is_object($qivivoCmd))
            {
                $qivivoCmd = new qivivoCmd();
                $qivivoCmd->setName(__('debug', __FILE__));
                $qivivoCmd->setIsVisible(0);
                $qivivoCmd->setIsHistorized(0);
                $qivivoCmd->setOrder($order);
                $order ++;
            }
            $qivivoCmd->setEqLogic_id($this->getId());
            $qivivoCmd->setLogicalId('debug');
            $qivivoCmd->setType('action');
            $qivivoCmd->setSubType('other');
            $qivivoCmd->save();
        }

        $refresh = $this->getCmd(null, 'refresh');
        if (!is_object($refresh))
        {
            $refresh = new qivivoCmd();
            $refresh->setLogicalId('refresh');
            $refresh->setIsVisible(1);
            $refresh->setName(__('Rafraichir', __FILE__));
            $refresh->setOrder($order);
        }
        $refresh->setType('action');
        $refresh->setSubType('other');
        $refresh->setEqLogic_id($this->getId());
        $refresh->save();
        $eqLogic->refreshWidget();
    }

    public function toHtml($_version = 'dashboard') {
        $version = jeedom::versionAlias($_version);
        $replace = $this->preToHtml($_version);
        if (!is_array($replace))
        {
            return $replace;
        }

        //only custom template for thermostat dashboard:
        $_thisType = $this->getConfiguration('type');
        $replace['#category#'] = $this->getPrimaryCategory();

        if ($_thisType == 'Thermostat')
        {
            $refresh = $this->getCmd(null, 'refresh');
            $replace['#refresh_id#'] = $refresh->getId();

            $replace['#temperature_name#'] = __('Température', __FILE__);
            $replace['#humidity_name#'] = __('Humidité', __FILE__);

            $cmd = $this->getCmd(null, 'temperature_order');
            $tmpConsigne = $cmd->execCmd();
            $replace['#temperature_order#'] = $tmpConsigne;
            $replace['#temperature_order_id#'] = $cmd->getId();
            $replace['#temperature_order_collectDate#'] = __('Date de valeur', __FILE__).' : '.$cmd->getValueDate().'<br>'.__('Date de collecte', __FILE__).' : '.$cmd->getCollectDate();
            if ($cmd->getIsHistorized() == 1)
            {
                $replace['#temperature_order_history#'] = 'history cursor';
            }
            $cmd = $this->getCmd(null, 'set_plus_one');
            $replace['#set_plus_one_id#'] = $cmd->getId();
            $cmd = $this->getCmd(null, 'set_minus_one');
            $replace['#set_minus_one_id#'] = $cmd->getId();
            $cmd = $this->getCmd(null, 'cancel_time_order');
            $replace['#cancel_id#'] = $cmd->getId();

            $zone_name = $this->getConfiguration('zone_name');
            $hasTimeOrder = $this->getCmd(null, 'hasTimeOrder')->execCmd();
            if ($hasTimeOrder)
            {
                $opacity = 1;
            }
            else
            {
                $opacity = 0.25;
            }
            $replace['#cancel_opacity#'] = $opacity;

            $cmd = $this->getCmd(null, 'temperature');
            $tmpRoom = $cmd->execCmd();
            $replace['#temperature#'] = $tmpRoom;
            $replace['#temperature_id#'] = $cmd->getId();
            $replace['#temperature_collectDate#'] = __('Date de valeur', __FILE__).' : '.$cmd->getValueDate().'<br>'.__('Date de collecte', __FILE__).' : '.$cmd->getCollectDate();
            if ($cmd->getIsHistorized() == 1)
            {
                $replace['#temperature_history#'] = 'history cursor';
            }

            $cmd = $this->getCmd(null, 'humidity');
            $replace['#humidity#'] = $cmd->execCmd();
            $replace['#humidity_id#'] = $cmd->getId();
            $replace['#humidity_collectDate#'] = __('Date de valeur', __FILE__).' : '.$cmd->getValueDate().'<br>'.__('Date de collecte', __FILE__).' : '.$cmd->getCollectDate();
            if ($cmd->getIsHistorized() == 1)
            {
                $replace['#humidity_history#'] = 'history cursor';
            }

            $cmd = $this->getCmd(null, 'last_presence');
            $replace['#lastpres#'] = $cmd->execCmd();
            $replace['#lastpres_id#'] = $cmd->getId();
            $replace['#lastpres_collectDate#'] = __('Date de valeur', __FILE__).' : '.$cmd->getValueDate().'<br>'.__('Date de collecte', __FILE__).' : '.$cmd->getCollectDate();

            $cmd = $this->getCmd(null, 'heating');
            $heating = $cmd->execCmd();
            $replace['#heating_id#'] = $cmd->getId();
            $replace['#heating_collectDate#'] = __('Date de valeur', __FILE__).' : '.$cmd->getValueDate().'<br>'.__('Date de collecte', __FILE__).' : '.$cmd->getCollectDate();
            if ($cmd->getIsHistorized() == 1)
            {
                $replace['#heating_history#'] = 'history cursor';
            }

            $cmd = $this->getCmd(null, 'current_program');
            $current_program = $cmd->execCmd();
            $replace['#program_collectDate#'] = __('Date de valeur', __FILE__).' : '.$cmd->getValueDate().'<br>'.__('Date de collecte', __FILE__).' : '.$cmd->getCollectDate();
            $ProgramsList = config::byKey('programList', 'qivivo');
            $cmd = $this->getCmd(null, 'set_program');
            $replace['#set_program_id#'] = $cmd->getId();
            $programs = $cmd->getConfiguration('listValue');

            $options = '';
            foreach ($ProgramsList as $program) {
                $display = $program['title'];
                if ($current_program == $display) $options .= '<option value="'.$display.'" selected>'.$display.'</option>';
                else $options .= '<option value="'.$display.'">'.$display.'</option>';
            }
            $replace['#set_program_listValue#'] = $options;


            if ($heating > 0) $replace['#imgheating#'] = '/plugins/qivivo/core/img/heating_on.png';
            else $replace['#imgheating#'] = '/plugins/qivivo/core/img/heating_off.png';

            $html = template_replace($replace, getTemplate('core', $version, 'thermostat', 'qivivo'));
        }

        if ($_thisType == 'Module Chauffage')
        {
            $isModuleThermostat = $this->getConfiguration('isModuleThermostat');
            //infos
            if ($isModuleThermostat == 0)
            {
                $cmd = $this->getCmd(null, 'module_order');
                $order = $cmd->execCmd();
                $replace['#order#'] = $order;
                $replace['#order_id#'] = $cmd->getId();
                $replace['#order_collectDate#'] = __('Date de valeur', __FILE__).' : '.$cmd->getValueDate().'<br>'.__('Date de collecte', __FILE__).' : '.$cmd->getCollectDate();
                if ($cmd->getIsHistorized() == 1)
                {
                    $replace['#order_history#'] = 'history cursor';
                }
            }

            $cmd = $this->getCmd(null, 'last_communication');
            $replace['#last_communication#'] = $cmd->execCmd();
            $replace['#last_communication_id#'] = $cmd->getId();
            if ($cmd->getIsHistorized() == 1)
            {
                $replace['#last_communication_history#'] = 'history cursor';
            }

            $cmd = $this->getCmd(null, 'firmware_version');
            $replace['#firmware_version#'] = $cmd->execCmd();
            $replace['#firmware_version_id#'] = $cmd->getId();
            $replace['#firmware_version_collectDate#'] = __('Date de valeur', __FILE__).' : '.$cmd->getValueDate().'<br>'.__('Date de collecte', __FILE__).' : '.$cmd->getCollectDate();

            //actions:
            if ($isModuleThermostat == 0)
            {
                $cmd = $this->getCmd(null, 'set_order');
                $replace['#set_order_id#'] = $cmd->getId();
                $modes = $cmd->getConfiguration('listValue');
                $modes = explode(';', $modes);
                $options = '';
                foreach ($modes as $mode) {
                    $value = explode('|', $mode)[0];
                    $display = explode('|', $mode)[1];
                    if ($order == $display) $options .= '<option value="'.$value.'" selected>'.$display.'</option>';
                    else $options .= '<option value="'.$value.'">'.$display.'</option>';
                }
                $replace['#set_order_listValue#'] = $options;

                $cmd = $this->getCmd(null, 'cancel_time_order');
                $replace['#cancel_id#'] = $cmd->getId();

                $zone_name = $this->getConfiguration('zone_name');
                $hasTimeOrder = $this->getCmd(null, 'hasTimeOrder')->execCmd();
                if ($hasTimeOrder) $opacity = 1;
                else $opacity = 0.25;
                $replace['#cancel_opacity#'] = $opacity;
            }

            if ($isModuleThermostat) $html = template_replace($replace, getTemplate('core', $version, 'module-t', 'qivivo'));
            else $html = template_replace($replace, getTemplate('core', $version, 'module', 'qivivo'));
        }

        if ($_thisType == 'Passerelle')
        {
            $cmd = $this->getCmd(null, 'last_communication');
            $replace['#last_communication#'] = $cmd->execCmd();
            $replace['#last_communication_id#'] = $cmd->getId();
            $replace['#last_communication_collectDate#'] = __('Date de valeur', __FILE__).' : '.$cmd->getValueDate().'<br>'.__('Date de collecte', __FILE__).' : '.$cmd->getCollectDate();
            if ($cmd->getIsHistorized() == 1)
            {
                $replace['#last_communication_history#'] = 'history cursor';
            }

            $cmd = $this->getCmd(null, 'firmware_version');
            $replace['#firmware_version#'] = $cmd->execCmd();
            $replace['#firmware_version_id#'] = $cmd->getId();
            $replace['#firmware_version_collectDate#'] = __('Date de valeur', __FILE__).' : '.$cmd->getValueDate().'<br>'.__('Date de collecte', __FILE__).' : '.$cmd->getCollectDate();
            if ($cmd->getIsHistorized() == 1)
            {
                $replace['#firmware_version_history#'] = 'history cursor';
            }

            $html = template_replace($replace, getTemplate('core', $version, 'gateway', 'qivivo'));
        }

        return $html;
    }

    public static function deadCmd() {
        qivivo::logger();
        $return = array();
        $actionsOnError = config::byKey('actionsOnError', 'qivivo');
        foreach ($actionsOnError as $cmdAr) {
            $options = $cmdAr['options'];
            if ($options['enable'] == 1)
            {
                $cmdId = $cmdAr['cmd'];
                if ($cmdId != '')
                {
                    if (!cmd::byId($cmdId))
                    {
                        $return[] = array('detail' => 'Configuration Qivivo', 'help' => 'Action sur erreur', 'who' => $cmdId);
                        qivivo::logger('deadCmd found: cmdId:'.$cmdId);
                    }
                }
            }
        }
        return $return;
    }
}

class qivivoCmd extends cmd {
    public static $_widgetPossibility = array('custom' => false);

    public function dontRemoveCmd() {
        return true;
    }

    public function execute($_options = array()) {
        if ($this->getLogicalId() == 'refresh') {
            qivivo::refreshQivivoInfos();
            return;
        }

        $eqLogic = $this->getEqlogic();
        $_action = $this->getLogicalId();
        $_type = $eqLogic->getConfiguration('type');
        qivivo::logger('action: '.$_action.' options: '.json_encode($_options));

        if ($_type == 'Module Chauffage')
        {
            if ($_action == 'set_order')
            {
                $orderNum = $_options['select'];
                if ($orderNum == '') return;

                $zone_name = $eqLogic->getConfiguration('zone_name');
                $orderString = '';
                $infoString = '';
                switch($orderNum)
                {
                    case 0:
                        $orderString = 'stop';
                        $infoString = 'Arrêt';
                        break;
                    case 1:
                        $orderString = 'frost_protection';
                        $infoString = 'Hors-Gel';
                        break;
                    case 2:
                        $orderString = 'eco';
                        $infoString = 'Eco';
                        break;
                    case 3:
                        $orderString = 'comfort_minus2';
                        $infoString = 'Confort -2';
                        break;
                    case 4:
                        $orderString = 'comfort_minus1';
                        $infoString = 'Confort -1';
                        break;
                    case 5:
                        $orderString = 'comfort';
                        $infoString = 'Confort';
                        break;
                }
                $message = $_action.' '.$eqLogic->getName().' to '.$orderString;
                qivivo::logger($message);

                $_fullQivivo = qivivo::getCustomAPI('action', $this, $_options, $message);
                if ($_fullQivivo === false)
                {
                    qivivo::logger($_action.': could not get customAPI, ending.');
                    return;
                }

                if ($_fullQivivo->hasTimeOrder($zone_name)['result'])
                {
                    qivivo::logger('Zone with temporary_instruction, canceling it.');
                    $_fullQivivo->cancelZoneOrder($zone_name);
                }

                $result = $_fullQivivo->setZoneMode($orderString, 120, $zone_name);

                if ($result['result']==True)
                {
                    qivivo::logger($_action.': success');
                    $eqLogic->checkAndUpdateCmd('module_order', $infoString);
                    $eqLogic->checkAndUpdateCmd('order_num', $orderNum);
                    $eqLogic->checkAndUpdateCmd('hasTimeOrder', 1);
                    $eqLogic->refreshWidget();
                }
                else
                {
                    qivivo::logger($_action.': error: '.json_encode($result['error']), 'warning');
                }
                return;
            }

            if ($_action == 'cancel_time_order')
            {
                $zone_name = $eqLogic->getConfiguration('zone_name');
                $message = $_action.' in '.$zone_name;
                qivivo::logger($message);

                $_fullQivivo = qivivo::getCustomAPI('action', $this, $_options, $message);
                if ($_fullQivivo === false)
                {
                    qivivo::logger($_action.': could not get customAPI, ending.');
                    return;
                }

                if ($_fullQivivo->hasTimeOrder($zone_name)['result'])
                {
                    qivivo::logger('Zone with temporary_instruction, canceling it.');
                    $result = $_fullQivivo->cancelZoneOrder($zone_name);
                    if (isset($result['result']))
                    {
                        $newOrder = $result['result'];
                        switch($newOrder)
                        {
                            case 'stop':
                                $orderString = 'Arrêt';
                                break;
                            case 'frost_protection':
                                $orderString = 'Hors-Gel';
                                break;
                            case 'eco':
                                $orderString = 'Eco';
                                break;
                            case 'comfort_minus2':
                                $orderString = 'Confort -2';
                                break;
                            case 'comfort_minus1':
                                $orderString = 'Confort -1';
                                break;
                            case 'comfort':
                                $orderString = 'Confort';
                                break;
                        }
                        $eqLogic->checkAndUpdateCmd('module_order', $orderString);
                        $eqLogic->checkAndUpdateCmd('hasTimeOrder', 0);
                        $eqLogic->refreshWidget();
                        qivivo::logger($_action.': success');
                    }
                    else
                    {
                        qivivo::logger($_action.': error: '.json_encode($result['error']), 'warning');
                    }
                }
                return;
            }
        }

        if ($_type == 'Thermostat') {
            if ($_action == 'set_program')
            {
                $program = $_options['select'];
                if ($program == '') return;

                $message = 'set_program '.$eqLogic->getName().' | '.$program;
                qivivo::logger($message);

                $_fullQivivo = qivivo::getCustomAPI('action', $this, $_options, $message);
                if ($_fullQivivo === false)
                {
                    qivivo::logger($_action.': could not get customAPI, ending.');
                    return;
                }

                $isMultizone = config::byKey('isMultizone', 'qivivo', -1);
                if ($isMultizone)
                {
                    $result = $_fullQivivo->setProgram($program);
                }
                else
                {
                    $result = $_fullQivivo->setSchedule($program);
                }


                if ($result['result']==True)
                {
                    qivivo::logger('set_program: success');
                    $eqLogic->checkAndUpdateCmd('current_program', $program);
                    $eqLogic->refreshWidget();
                }
                else
                {
                    qivivo::logger($_action.': error: '.json_encode($result['error']), 'warning');
                }
                qivivo::refreshQivivoInfos();
                return;
            }

            if ($_action == 'set_time_order')
            {
                qivivo::logger('set_time_order: '.$_options['slider']);
                $duree_temp = $eqLogic->getCmd(null, 'duree_temp');
                $duree_temp->event($_options['slider']);
                return;
            }

            if ($_action == 'set_plus_one')
            {
                $temp = $eqLogic->getCmd(null, 'temperature_order')->execCmd();
                $temp += 1;
                $duree_temp = $eqLogic->getCmd(null, 'duree_temp')->execCmd();
                $message = $_action.' to '.$temp.' during '.$duree_temp.' mins';
                qivivo::logger($message);

                $_fullQivivo = qivivo::getCustomAPI('action', $this, $_options, $message);
                if ($_fullQivivo === false)
                {
                    qivivo::logger($_action.': could not get customAPI, ending.');
                    return;
                }

                $zone_name = $eqLogic->getConfiguration('zone_name');
                if ($_fullQivivo->hasTimeOrder($zone_name)['result'])
                {
                    qivivo::logger('Zone with temporary_instruction, canceling it.');
                    $_fullQivivo->cancelZoneOrder($zone_name);
                }

                $result = $_fullQivivo->setTemperature($temp, $duree_temp, $zone_name);
                if ($result['result']==True)
                {
                    $eqLogic->checkAndUpdateCmd('hasTimeOrder', 1);
                    $eqLogic->refreshWidget();
                    qivivo::logger($_action.': success');
                }
                else
                {
                    qivivo::logger($_action.': error: '.json_encode($result['error']), 'warning');
                }
                return;
            }

            if ($_action == 'set_minus_one')
            {
                $temp = $eqLogic->getCmd(null, 'temperature_order')->execCmd();
                $temp -= 1;
                $duree_temp = $eqLogic->getCmd(null, 'duree_temp')->execCmd();
                $message = $_action.' to '.$temp.' during '.$duree_temp.' mins';
                qivivo::logger($message);

                $_fullQivivo = qivivo::getCustomAPI('action', $this, $_options, $message);
                if ($_fullQivivo === false)
                {
                    qivivo::logger($_action.': could not get customAPI, ending.');
                    return;
                }

                $zone_name = $eqLogic->getConfiguration('zone_name');
                if ($_fullQivivo->hasTimeOrder($zone_name)['result'])
                {
                    qivivo::logger('Zone with temporary_instruction, canceling it.');
                    $_fullQivivo->cancelZoneOrder($zone_name);
                }

                $result = $_fullQivivo->setTemperature($temp, $duree_temp, $zone_name);
                if ($result['result']==True)
                {
                    $eqLogic->checkAndUpdateCmd('hasTimeOrder', 1);
                    $eqLogic->refreshWidget();
                    qivivo::logger($_action.': success');
                }
                else
                {
                    qivivo::logger($_action.': error: '.json_encode($result['error']), 'warning');
                }
                return;
            }

            if ($_action == 'cancel_time_order')
            {
                $zone_name = $eqLogic->getConfiguration('zone_name');
                $message = $_action.' in '.$zone_name;
                qivivo::logger($message);

                $_fullQivivo = qivivo::getCustomAPI('action', $this, $_options, $message);
                if ($_fullQivivo === false)
                {
                    qivivo::logger('cancel_time_order: could not get customAPI, ending.');
                    return;
                }

                if ($_fullQivivo->hasTimeOrder($zone_name)['result'])
                {
                    qivivo::logger('Zone with temporary_instruction, canceling it.');
                    $result = $_fullQivivo->cancelZoneOrder($zone_name);
                    if (isset($result['result']))
                    {
                        $newOrder = $result['result'];
                        $orderValue = null;
                        switch($newOrder)
                        {
                            case 'away':
                                $orderValue = $eqLogic->getCmd(null, 'absence_temperature')->execCmd();
                                break;
                            case 'frost_protection':
                                $orderValue = $eqLogic->getCmd(null, 'frost_protection_temperature')->execCmd();
                                break;
                            case 'night':
                                $orderValue = $eqLogic->getCmd(null, 'night_temperature')->execCmd();
                                break;
                            case 'presence_1':
                                $orderValue = $eqLogic->getCmd(null, 'presence_temperature_1')->execCmd();
                                break;
                            case 'presence_2':
                                $orderValue = $eqLogic->getCmd(null, 'presence_temperature_2')->execCmd();
                                break;
                            case 'presence_3':
                                $orderValue = $eqLogic->getCmd(null, 'presence_temperature_3')->execCmd();
                                break;
                            case 'presence_4 ':
                                $orderValue = $eqLogic->getCmd(null, 'presence_temperature_4')->execCmd();
                                break;
                        }
                        if ($orderValue) $eqLogic->checkAndUpdateCmd('temperature_order', $orderValue);
                        $eqLogic->checkAndUpdateCmd('hasTimeOrder', 0);
                        $eqLogic->refreshWidget();
                        qivivo::logger($_action.': success');
                    }
                    else
                    {
                        qivivo::logger($_action.': error: '.json_encode($result['error']), 'warning');
                    }
                }
                return;
            }

            if ($_action == 'set_temperature_order')
            {
                $temp = $_options['slider'];
                $duree_temp = $eqLogic->getCmd(null, 'duree_temp')->execCmd();
                $zone_name = $eqLogic->getConfiguration('zone_name');
                $message = $_action.' to '.$temp.' duree_temp: '.$duree_temp.' in '.$zone_name;
                qivivo::logger($message);

                $_fullQivivo = qivivo::getCustomAPI('action', $this, $_options, $message);
                if ($_fullQivivo === false)
                {
                    qivivo::logger($_action.': could not get customAPI, ending.');
                    return;
                }

                $zoneEvents = $_fullQivivo->getZoneEvents($zone_name);
                if (isset($zoneEvents['result']['temporary_instruction']['set_point']))
                {
                    qivivo::logger('Zone with temporary_instruction, canceling it.');
                    $_fullQivivo->cancelZoneOrder($zone_name);
                }

                $result = $_fullQivivo->setTemperature($temp, $duree_temp, $zone_name);
                if ($result['result']==True)
                {
                    qivivo::logger($_action.': success');
                }
                else
                {
                    qivivo::logger($_action.': error: '.json_encode($result['error']), 'warning');
                }
                return;
            }

            //temperatures settings:
            if ($_action == 'set_absence_temperature')
            {
                $tempValue = $_options['slider'];
                $message = $_action.' to '.$tempValue;
                qivivo::logger($message);

                $_fullQivivo = qivivo::getCustomAPI('action', $this, $_options, $message);
                if ($_fullQivivo === false)
                {
                    qivivo::logger($_action.': could not get customAPI, ending.');
                    return;
                }

                $settingsAr = array(
                    "away"=>$tempValue,
                    "frost_protection"=>$eqLogic->getCmd(null, 'frost_protection_temperature')->execCmd(),
                    "night"=>$eqLogic->getCmd(null, 'night_temperature')->execCmd(),
                    "connected"=>array(
                                    "presence_1"=>$eqLogic->getCmd(null, 'presence_temperature_1')->execCmd(),
                                    "presence_2"=>$eqLogic->getCmd(null, 'presence_temperature_2')->execCmd(),
                                    "presence_3"=>$eqLogic->getCmd(null, 'presence_temperature_3')->execCmd(),
                                    "presence_4"=>$eqLogic->getCmd(null, 'presence_temperature_4')->execCmd()
                                )
                );

                $result = $_fullQivivo->setTempSettings($settingsAr);
                if ($result['result']==True)
                {
                    qivivo::logger($_action.': success');
                    $eqLogic->getCmd(null, 'absence_temperature')->event($tempValue);
                }
                else
                {
                    qivivo::logger($_action.': error: '.json_encode($result['error']), 'warning');
                }
                return;
            }

            if ($_action == 'set_frost_temperature')
            {
                $tempValue = $_options['slider'];
                $message = $_action.' to '.$tempValue;
                qivivo::logger($message);

                $_fullQivivo = qivivo::getCustomAPI('action', $this, $_options, $message);
                if ($_fullQivivo === false)
                {
                    qivivo::logger($_action.': could not get customAPI, ending.');
                    return;
                }

                $settingsAr = array(
                    "away"=>$eqLogic->getCmd(null, 'absence_temperature')->execCmd(),
                    "frost_protection"=>$tempValue,
                    "night"=>$eqLogic->getCmd(null, 'night_temperature')->execCmd(),
                    "connected"=>array(
                                    "presence_1"=>$eqLogic->getCmd(null, 'presence_temperature_1')->execCmd(),
                                    "presence_2"=>$eqLogic->getCmd(null, 'presence_temperature_2')->execCmd(),
                                    "presence_3"=>$eqLogic->getCmd(null, 'presence_temperature_3')->execCmd(),
                                    "presence_4"=>$eqLogic->getCmd(null, 'presence_temperature_4')->execCmd()
                                )
                );

                $result = $_fullQivivo->setTempSettings($settingsAr);
                if ($result['result']==True)
                {
                    qivivo::logger($_action.': success');
                    $eqLogic->getCmd(null, 'frost_protection_temperature')->event($tempValue);
                }
                else
                {
                    qivivo::logger($_action.': error: '.json_encode($result['error']), 'warning');
                }
                return;
            }

            if ($_action == 'set_night_temperature') {
                $tempValue = $_options['slider'];
                $message = $_action.' to '.$tempValue;
                qivivo::logger($message);

                $_fullQivivo = qivivo::getCustomAPI('action', $this, $_options, $message);
                if ($_fullQivivo === false)
                {
                    qivivo::logger($_action.': could not get customAPI, ending.');
                    return;
                }

                $settingsAr = array(
                    "away"=>$eqLogic->getCmd(null, 'absence_temperature')->execCmd(),
                    "frost_protection"=>$eqLogic->getCmd(null, 'frost_protection_temperature')->execCmd(),
                    "night"=>$tempValue,
                    "connected"=>array(
                                    "presence_1"=>$eqLogic->getCmd(null, 'presence_temperature_1')->execCmd(),
                                    "presence_2"=>$eqLogic->getCmd(null, 'presence_temperature_2')->execCmd(),
                                    "presence_3"=>$eqLogic->getCmd(null, 'presence_temperature_3')->execCmd(),
                                    "presence_4"=>$eqLogic->getCmd(null, 'presence_temperature_4')->execCmd()
                                )
                );

                $result = $_fullQivivo->setTempSettings($settingsAr);
                if ($result['result']==True)
                {
                    qivivo::logger($_action.': success');
                    $eqLogic->getCmd(null, 'night_temperature')->event($tempValue);
                }
                else
                {
                    qivivo::logger($_action.': error: '.json_encode($result['error']), 'warning');
                }
                return;
            }

            if ($_action == 'set_presence_temperature_1')
            {
                $tempValue = $_options['slider'];
                $message = $_action.' to '.$tempValue;
                qivivo::logger($message);

                $_fullQivivo = qivivo::getCustomAPI('action', $this, $_options, $message);
                if ($_fullQivivo === false)
                {
                    qivivo::logger($_action.': could not get customAPI, ending.');
                    return;
                }

                $settingsAr = array(
                    "away"=>$eqLogic->getCmd(null, 'absence_temperature')->execCmd(),
                    "frost_protection"=>$eqLogic->getCmd(null, 'frost_protection_temperature')->execCmd(),
                    "night"=>$eqLogic->getCmd(null, 'night_temperature')->execCmd(),
                    "connected"=>array(
                                    "presence_1"=>$tempValue,
                                    "presence_2"=>$eqLogic->getCmd(null, 'presence_temperature_2')->execCmd(),
                                    "presence_3"=>$eqLogic->getCmd(null, 'presence_temperature_3')->execCmd(),
                                    "presence_4"=>$eqLogic->getCmd(null, 'presence_temperature_4')->execCmd()
                                )
                );

                $result = $_fullQivivo->setTempSettings($settingsAr);
                if ($result['result']==True)
                {
                    qivivo::logger($_action.': success');
                    $eqLogic->getCmd(null, 'presence_temperature_1')->event($tempValue);
                }
                else
                {
                    qivivo::logger($_action.': error: '.json_encode($result['error']), 'warning');
                }
                return;
            }

            if ($_action == 'set_presence_temperature_2')
            {
                $tempValue = $_options['slider'];
                $message = $_action.' to '.$tempValue;
                qivivo::logger($message);

                $_fullQivivo = qivivo::getCustomAPI('action', $this, $_options, $message);
                if ($_fullQivivo === false)
                {
                    qivivo::logger($_action.': could not get customAPI, ending.');
                    return;
                }

                $settingsAr = array(
                    "away"=>$eqLogic->getCmd(null, 'absence_temperature')->execCmd(),
                    "frost_protection"=>$eqLogic->getCmd(null, 'frost_protection_temperature')->execCmd(),
                    "night"=>$eqLogic->getCmd(null, 'night_temperature')->execCmd(),
                    "connected"=>array(
                                    "presence_1"=>$eqLogic->getCmd(null, 'presence_temperature_1')->execCmd(),
                                    "presence_2"=>$tempValue,
                                    "presence_3"=>$eqLogic->getCmd(null, 'presence_temperature_3')->execCmd(),
                                    "presence_4"=>$eqLogic->getCmd(null, 'presence_temperature_4')->execCmd()
                                )
                );

                $result = $_fullQivivo->setTempSettings($settingsAr);
                if ($result['result']==True)
                {
                    qivivo::logger($_action.': success');
                    $eqLogic->getCmd(null, 'presence_temperature_2')->event($tempValue);
                }
                else
                {
                    qivivo::logger($_action.': error: '.json_encode($result['error']), 'warning');
                }
                return;
            }

            if ($_action == 'set_presence_temperature_3') {
                $tempValue = $_options['slider'];
                $message = $_action.' to '.$tempValue;
                qivivo::logger($message);

                $_fullQivivo = qivivo::getCustomAPI('action', $this, $_options, $message);
                if ($_fullQivivo === false)
                {
                    qivivo::logger($_action.': could not get customAPI, ending.');
                    return;
                }

                $settingsAr = array(
                    "away"=>$eqLogic->getCmd(null, 'absence_temperature')->execCmd(),
                    "frost_protection"=>$eqLogic->getCmd(null, 'frost_protection_temperature')->execCmd(),
                    "night"=>$eqLogic->getCmd(null, 'night_temperature')->execCmd(),
                    "connected"=>array(
                                    "presence_1"=>$eqLogic->getCmd(null, 'presence_temperature_1')->execCmd(),
                                    "presence_2"=>$eqLogic->getCmd(null, 'presence_temperature_2')->execCmd(),
                                    "presence_3"=>$tempValue,
                                    "presence_4"=>$eqLogic->getCmd(null, 'presence_temperature_4')->execCmd()
                                )
                );

                $result = $_fullQivivo->setTempSettings($settingsAr);
                if ($result['result']==True)
                {
                    qivivo::logger($_action.': success');
                    $eqLogic->getCmd(null, 'presence_temperature_3')->event($tempValue);
                }
                else
                {
                    qivivo::logger($_action.': error: '.json_encode($result['error']), 'warning');
                }
                return;
            }

            if ($_action == 'set_presence_temperature_4')
            {
                $tempValue = $_options['slider'];
                $message = $_action.' to '.$tempValue;
                qivivo::logger($message);

                $_fullQivivo = qivivo::getCustomAPI('action', $this, $_options, $message);
                if ($_fullQivivo === false)
                {
                    qivivo::logger($_action.': could not get customAPI, ending.');
                    return;
                }

                $settingsAr = array(
                    "away"=>$eqLogic->getCmd(null, 'absence_temperature')->execCmd(),
                    "frost_protection"=>$eqLogic->getCmd(null, 'frost_protection_temperature')->execCmd(),
                    "night"=>$eqLogic->getCmd(null, 'night_temperature')->execCmd(),
                    "connected"=>array(
                                    "presence_1"=>$eqLogic->getCmd(null, 'presence_temperature_1')->execCmd(),
                                    "presence_2"=>$eqLogic->getCmd(null, 'presence_temperature_2')->execCmd(),
                                    "presence_3"=>$eqLogic->getCmd(null, 'presence_temperature_3')->execCmd(),
                                    "presence_4"=>$tempValue
                                )
                );

                $result = $_fullQivivo->setTempSettings($settingsAr);
                if ($result['result']==True)
                {
                    qivivo::logger($_action.': success');
                    $eqLogic->getCmd(null, 'presence_temperature_4')->event($tempValue);
                }
                else
                {
                    qivivo::logger($_action.': error: '.json_encode($result['error']), 'warning');
                }
                return;
            }
        }

        if ($_type == 'Passerelle')
        {
            if ($_action == 'debug')
            {
                qivivo::logger();
                qivivo::getDebugInfos();
            }
        }
    }
}
