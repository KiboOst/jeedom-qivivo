<?php
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
if (!class_exists('splQivivoAPI')) {
    require_once dirname(__FILE__) . '/../../3rdparty/splQivivoAPI.php';
}
if (!class_exists('qivivoAPI')) {
    require_once dirname(__FILE__) . '/../../3rdparty/qivivoAPI.php';
}

class qivivo extends eqLogic {
    public static $_widgetPossibility = array('custom' => true, 'custom::layout' => false);

    public function logger($str = '', $level = 'debug') {
        if (is_array($str)) $str = json_encode($str);
        $function_name = debug_backtrace(false, 2)[1]['function'];
        $class_name = debug_backtrace(false, 2)[1]['class'];
        $msg = '['.$class_name.'] <'. $function_name .'> '.$str;
        log::add('qivivo', $level, $msg);
    }

    public static function getAPI($_typeCmd='info', $_action=null, $_options=null, $_msg=null) {
        $client_id = config::byKey('client_id', 'qivivo');
        $client_secret = config::byKey('client_secret', 'qivivo');
        $_qivivo = new splQivivoAPI($client_id, $client_secret);

        if (isset($_qivivo->error))
        {
            $_apiError = $_qivivo->error;
            qivivo::logger('Qivivo API error: '.$_apiError, 'warning');
            if ($_typeCmd == 'action')
            {
                if ($_msg) $_options['error'] = $_msg;
                if ($_action) qivivo::checkAndRetryAction($_action, $_options);
            }
            else
            {
                qivivo::logger('Erreur sur Info, tentatives: '.config::byKey('refreshFailed', 'qivivo', 0));
                if (config::byKey('refreshFailed', 'qivivo', 0) > 3)
                {
                    qivivo::logger('Erreur: la synchro Qivivo a échouée lors des 3 dernières tentatives.', 'error');
                    config::save('refreshFailed', 0, 'qivivo');
                }
                else
                {
                    config::save('refreshFailed', config::byKey('refreshFailed', 'qivivo', 0) + 1, 'qivivo');
                }
            }
            return False;
        }
        else
        {
            if (config::byKey('refreshFailed', 'qivivo', 0) > 0) config::save('refreshFailed', 0, 'qivivo');
        }
        return $_qivivo;
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
                if ($_msg) $_options['error'] = $_msg;
                if ($_action) qivivo::checkAndRetryAction($_action, $_options);
            }
            return False;
        }
        return $_customQivivo;
    }

    public static function checkAndRetryAction($_action, $_options) { //called from API getter if action, set either cron to retry or actionOnError
        $_doRepeat = config::byKey('repeatOnActionError', 'qivivo', 0);
        qivivo::logger('repeatOnActionError: '.$_doRepeat);

        if ($_doRepeat != 1)
        {
            if (isset($_options['error'])) $msg = $_options['error'];
            else $msg = $_action->getLogicalId();
            qivivo::doActionsOnError($msg);
            return;
        }

        $_retried = 0;
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
                if (isset($options['message']) AND isset($_msg))
                {
                    $options['message'] = str_replace('#message#', $_msg , $options['message']);
                }
                $cmdId = $cmdAr['cmd'];
                $cmd = cmd::byId($cmdId);
                $cmd->execCmd($options);
            }
        }
    }

    public static function retryAction($cronOption) { //called from cron engine to repeat an action
        qivivo::logger('doActionsOnError: exec: '.json_encode($cronOption));
        $cmd = cmd::byId($cronOption['id']);
        $_options = $cronOption['options'];

        $cmd->execCmd($_options);
    }

    public static function syncWithQivivo() { //ajax call from plugin configuration
        qivivo::logger('starting...');
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

        if ($_fullQivivo->_isMultizone) {
            $fullModules = $_fullQivivo->_fullDatas['multizone']['wirelessModules'];
        } else {
            $fullModules = array();
            foreach ($devices as $device) {
                if ($device['type'] == 'wireless-module') {
                    array_push($fullModules, $device);
                }
            }
        }
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

                //need to correlate API serial which is same as customAPI mac_address:
                $moduleInfos = $_qivivo->getModuleInfos($device['uuid']);
                $serial = $moduleInfos['serial'];
                $eqLogic->setConfiguration('zone_name', -1);
                foreach ($fullModules as $fullModule)
                {
                    if ($_fullQivivo->_isMultizone) {
                        $mac_address = $fullModule['mac_address'];
                        if ($serial == $mac_address)
                        {
                            $eqLogic->setConfiguration('zone_name', $fullModule['zone_name']);
                            $eqLogic->setName('Zone '.$fullModule['zone_name']);
                            $program_name = $fullCurrentPrograms['result'][$fullModule['zone_name']];
                            $eqLogic->setConfiguration('program_name', $program_name);
                            break;
                        }
                    } else {
                        $zone_name = 'Zone Thermostat';
                        $program_name = $fullCurrentPrograms['result']['Zone Thermostat'];
                        $eqLogic->setConfiguration('zone_name', $zone_name);
                        $eqLogic->setName($zone_name);
                        $eqLogic->setConfiguration('program_name', $program_name);
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
        qivivo::logger('done!');
    }

    public static function refreshQivivoInfos() { //called from cron5 or cron15 to refresh infos
        try {
            qivivo::logger('refresh');
            $_qivivo = qivivo::getAPI();
            if ($_qivivo == False) {
                qivivo::logger('could not get API, ending.');
                return;
            }

            $devices = $_qivivo->getDevices();
            qivivo::logger('devices: '.json_encode($devices));
            foreach ($devices as $device)
            {
                $_type = $device['type'];
                $_uuid = $device['uuid'];
                qivivo::logger('type: '.$_type.' uuid: '.$_uuid);
                $eqLogic = eqLogic::byLogicalId($_uuid, 'qivivo');
                if (!is_object($eqLogic)) continue;

                if ($_type == 'gateway')
                {
                    $gatewayInfos = $_qivivo->getGatewayInfos();
                    qivivo::logger('getGatewayInfos: '.json_encode($gatewayInfos));
                    $firmware_version = $gatewayInfos['softwareVersion'];
                    if (!is_null($firmware_version)) $eqLogic->checkAndUpdateCmd('firmware_version', $firmware_version);

                    $last_communication = $gatewayInfos['lastCommunicationDate'];
                    $last_communication = date("d-m-Y H:i", strtotime($last_communication));
                    if (!is_null($last_communication)) $eqLogic->checkAndUpdateCmd('last_communication', $last_communication);
                }

                if ($_type == 'thermostat')
                {
                    $eqLogic->getCmd(null, 'duree_temp')->event(120);

                    $thermostatInfos = $_qivivo->getThermostatInfos($_uuid);
                    qivivo::logger('getThermostatInfos: '.json_encode($thermostatInfos));
                    $last_communication = $thermostatInfos['lastCommunicationDate'];
                    $last_communication = date("d-m-Y H:i", strtotime($last_communication));
                    if (!is_null($last_communication)) $eqLogic->checkAndUpdateCmd('last_communication', $last_communication);
                    $firmware_version = $thermostatInfos['softwareVersion'];
                    if (!is_null($firmware_version)) $eqLogic->checkAndUpdateCmd('firmware_version', $firmware_version);
                    $battery_percent = $thermostatInfos['voltagePercentage'];
                    if (!is_null($battery_percent)) {
                        $eqLogic->checkAndUpdateCmd('battery', $battery_percent);
                        $eqLogic->batteryStatus($battery_percent);
                    }

                    $thermostatHumidity = $_qivivo->getThermostatHumidity($_uuid);
                    qivivo::logger('getThermostatHumidity: '.json_encode($thermostatHumidity));
                    $humidity = $thermostatHumidity['humidity'];
                    if (!is_null($humidity)) $eqLogic->checkAndUpdateCmd('humidity', $humidity);

                    $thermostatPresence = $_qivivo->getThermostatPresence($_uuid);
                    qivivo::logger('getThermostatPresence: '.json_encode($thermostatPresence));
                    $Pres = $thermostatPresence['presence_detected'];
                    $presence = 0;
                    if ($Pres) $presence = 1;
                    if (!is_null($presence)) $eqLogic->checkAndUpdateCmd('presence', $presence);

                    $lastPresence = $_qivivo->getLastPresence();
                    qivivo::logger('getLastPresence: '.json_encode($lastPresence));
                    $lastP = $lastPresence['last_presence_recorded_time'];
                    $lastP = date("d-m-Y H:i", strtotime($lastP));
                    if (!is_null($lastP)) $eqLogic->checkAndUpdateCmd('last_presence', $lastP);

                    $thermostatTemperature = $_qivivo->getThermostatTemperature($_uuid);
                    qivivo::logger('getThermostatTemperature: '.json_encode($thermostatTemperature));
                    $order = $thermostatTemperature['current_temperature_order'];
                    $temp = $thermostatTemperature['temperature'];
                    if (!is_null($order)) $eqLogic->checkAndUpdateCmd('temperature_order', (round($order * 2)/2));
                    if (!is_null($temp)) $eqLogic->checkAndUpdateCmd('temperature', $temp);

                    $heating = 0;
                    if ($temp < $order) $heating = $order;
                    if (!is_null($heating)) $eqLogic->checkAndUpdateCmd('heating', $heating);

                    $settings = $_qivivo->getSettings();
                    qivivo::logger('settings: '.json_encode($settings));
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
                    qivivo::logger('getModuleLastOrder: '.json_encode($moduleOrder));
                    $moduleInfos = $_qivivo->getModuleInfos($_uuid);
                    qivivo::logger('getModuleInfos: '.json_encode($moduleInfos));

                    $firmware_version = $moduleInfos['softwareVersion'];
                    if (!is_null($firmware_version)) $eqLogic->checkAndUpdateCmd('firmware_version', $firmware_version);

                    $last_communication = $moduleInfos['lastCommunicationDate'];
                    $last_communication = date("d-m-Y H:i", strtotime($last_communication));
                    if (!is_null($last_communication)) $eqLogic->checkAndUpdateCmd('last_communication', $last_communication);

                    $order = $moduleOrder['current_pilot_wire_order'];
                    $order_num = 0;
                    if ($order == 'off')
                    {
                        $order_num = 1;
                        $order = 'Arrêt';
                    }
                    if ($order == 'frost')
                    {
                        $order_num = 2;
                        $order = 'Hors-Gel';
                    }
                    if ($order == 'eco')
                    {
                        $order_num = 3;
                        $order = 'Eco';
                    }
                    if ($order == 'comfort_minus_two')
                    {
                        $order_num = 4;
                        $order = 'Confort -2';
                    }
                    if ($order == 'comfort_minus_one')
                    {
                        $order_num = 5;
                        $order = 'Confort -1';
                    }
                    if ($order == 'comfort')
                    {
                        $order_num = 6;
                        $order = 'Confort';
                    }
                    if ($order == 'monozone') $eqLogic->setConfiguration('isModuleThermostat', 1);
                    else $eqLogic->setConfiguration('isModuleThermostat', 0);

                    if (!is_null($order)) $eqLogic->checkAndUpdateCmd('module_order', $order);
                    if (!is_null($order_num)) $eqLogic->checkAndUpdateCmd('order_num', $order_num);
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

    public static function getDebugInfos() { //log both APIs data to debug user configuration
        //official API:
        $_qivivo = qivivo::getAPI();
        if ($_qivivo == False)
        {
            log::add('qivivo_debug', 'error', 'getAPI() error!');
            return;
        }

        $data = json_encode($_qivivo, JSON_PRETTY_PRINT);
        log::add('qivivo_debug', 'error', 'getAPI: '.$data);

        //custom API:
        $_fullQivivo = qivivo::getCustomAPI('action', null, $_options, $message);
        if ($_fullQivivo == False)
        {
            log::add('qivivo_debug', 'error', 'getCustomAPI() error!');
            return;
        }

        $data = json_encode($_fullQivivo, JSON_PRETTY_PRINT);
        log::add('qivivo_debug', 'error', 'getCustomAPI: '.$data);

        $getProducts = $_fullQivivo->getProducts();
        $data = json_encode($getProducts, JSON_PRETTY_PRINT);
        log::add('qivivo_debug', 'error', 'customAPI.getProducts: '.$data);

        $devices = $_qivivo->getDevices();
        log::add('qivivo_debug', 'error', 'API.getDevices: '.json_encode($devices));
        log::add('qivivo_debug', 'error', 'customAPI._isMultizone: '.$_fullQivivo->_isMultizone);

        $fullCurrentPrograms = $_fullQivivo->getCurrentProgram();
        log::add('qivivo_debug', 'error', 'customAPI.getCurrentProgram: '.json_encode($fullCurrentPrograms));

        if ($_fullQivivo->_isMultizone) {
            $fullModules = $_fullQivivo->_fullDatas['multizone']['wirelessModules'];
        } else {
            $fullModules = array();
            foreach ($devices as $device) {
                if ($device['type'] == 'wireless-module') {
                    array_push($fullModules, $device);
                }
            }
        }

        foreach ($devices as $device)
        {
            $type = $device['type'];
            if ($type == 'wireless-module')
            {
                $moduleInfos = $_qivivo->getModuleInfos($device['uuid']);
                $serial = $moduleInfos['serial'];
                foreach ($fullModules as $fullModule)
                    {
                        if ($_fullQivivo->_isMultizone) {
                            $mac_address = $fullModule['mac_address'];
                            if ($serial == $mac_address)
                            {
                                $zone_name = $fullModule['zone_name'];
                                $program_name = $fullCurrentPrograms['result'][$fullModule['zone_name']];
                                break;
                            }
                        } else {
                            $zone_name = 'Zone Thermostat';
                            $program_name = $fullCurrentPrograms['result']['Zone Thermostat'];
                        }

                    }
                log::add('qivivo_debug', 'error', 'Zone: '.$zone_name.' | program_name: '.$program_name);
            }
        }
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
        $_uuid = $this->getConfiguration('uuid');
        $eqLogic = eqLogic::byLogicalId($_uuid, 'qivivo');
        qivivo::logger($_thisType.' | '.$_uuid);

        if (in_array($_thisType, array('Thermostat')))
        {
            //infos:
            $qivivoCmd = $this->getCmd(null, 'temperature_order');
            if (!is_object($qivivoCmd)) {
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

            $qivivoCmd = $this->getCmd(null, 'temperature');
            if (!is_object($qivivoCmd)) {
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
            if (!is_object($qivivoCmd)) {
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
            if (!is_object($qivivoCmd)) {
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

            $qivivoCmd = $this->getCmd(null, 'presence');
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
            $qivivoCmd->setLogicalId('presence');
            $qivivoCmd->setType('info');
            $qivivoCmd->setSubType('binary');
            $qivivoCmd->save();

            $qivivoCmd = $this->getCmd(null, 'last_presence');
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
            $qivivoCmd->setLogicalId('last_presence');
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

            $qivivoCmd = $this->getCmd(null, 'battery');
            if (!is_object($qivivoCmd)) {
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
            if (!is_object($qivivoCmd)) {
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
            if (!is_object($qivivoCmd)) {
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
            if (!is_object($qivivoCmd)) {
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
            if (!is_object($qivivoCmd)) {
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
            if (!is_object($qivivoCmd)) {
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
            if (!is_object($qivivoCmd)) {
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
            if (!is_object($qivivoCmd)) {
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
            if (!is_object($qivivoCmd)) {
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
            if (!is_object($qivivoCmd)) {
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
            if (!is_object($qivivoCmd)) {
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
            if (!is_object($qivivoCmd)) {
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
            if (!is_object($qivivoCmd)) {
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
        }

        if (in_array($_thisType, array('Module Chauffage')))
        {
            //infos:
            $qivivoCmd = $this->getCmd(null, 'module_order');
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
            $qivivoCmd->setLogicalId('module_order');
            $qivivoCmd->setType('info');
            $qivivoCmd->setSubType('string');
            $qivivoCmd->save();

            $qivivoCmd = $this->getCmd(null, 'order_num');
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
            $qivivoCmd->setLogicalId('order_num');
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
                $qivivoCmd = $this->getCmd(null, 'set_order');
                if (!is_object($qivivoCmd)) {
                    $qivivoCmd = new qivivoCmd();
                    $qivivoCmd->setName(__('SetMode', __FILE__));
                    $qivivoCmd->setIsVisible(1);
                    $qivivoCmd->setOrder($order);
                    $order ++;
                }
                $qivivoCmd->setEqLogic_id($this->getId());
                $qivivoCmd->setLogicalId('set_order');
                $qivivoCmd->setType('action');
                $qivivoCmd->setSubType('select');
                $qivivoCmd->setConfiguration('listValue','5|Arrêt;6|Hors-Gel;4|Eco;8|Confort -2;7|Confort -1;3|Confort');
                $qivivoCmd->save();
            }

            $qivivoCmd = $this->getCmd(null, 'set_program');
            if (!is_object($qivivoCmd)) {
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
            $qivivoCmd = $this->getCmd(null, 'last_communication');
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
            $qivivoCmd->setLogicalId('last_communication');
            $qivivoCmd->setType('info');
            $qivivoCmd->setSubType('string');
            $qivivoCmd->save();

            $qivivoCmd = $this->getCmd(null, 'firmware_version');
            if (!is_object($qivivoCmd)) {
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
            if (!is_object($qivivoCmd)) {
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
        if (!is_object($refresh)) {
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
        if (!is_array($replace)) {
            return $replace;
        }

        //only custom template for thermostat dashboard:
        $_thisType = $this->getConfiguration('type');
        $replace['#category#'] = $this->getPrimaryCategory();

        if ($_thisType == 'Thermostat')
        {
            $refresh = $this->getCmd(null, 'refresh');
            $replace['#refresh_id#'] = $refresh->getId();

            $cmd = $this->getCmd(null, 'temperature_order');
            $tmpConsigne = $cmd->execCmd();
            $replace['#temperature_order#'] = $tmpConsigne;
            $replace['#temperature_order_id#'] = $cmd->getId();
            $replace['#temperature_order_collectDate#'] = 'Date de valeur : '.$cmd->getValueDate().'<br>Date de collecte : '.$cmd->getCollectDate();
            if ($cmd->getIsHistorized() == 1) {
                $replace['#temperature_order_history#'] = 'history cursor';
            }
            $cmd = $this->getCmd(null, 'set_plus_one');
            $replace['#set_plus_one_id#'] = $cmd->getId();
            $cmd = $this->getCmd(null, 'set_minus_one');
            $replace['#set_minus_one_id#'] = $cmd->getId();
            $cmd = $this->getCmd(null, 'cancel_time_order');
            $replace['#cancel_id#'] = $cmd->getId();

            $cmd = $this->getCmd(null, 'temperature');
            $tmpRoom = $cmd->execCmd();
            $replace['#temperature#'] = $tmpRoom;
            $replace['#temperature_id#'] = $cmd->getId();
            $replace['#temperature_collectDate#'] = 'Date de valeur : '.$cmd->getValueDate().'<br>Date de collecte : '.$cmd->getCollectDate();
            if ($cmd->getIsHistorized() == 1) {
                $replace['#temperature_history#'] = 'history cursor';
            }

            $cmd = $this->getCmd(null, 'humidity');
            $replace['#humidity#'] = $cmd->execCmd();
            $replace['#humidity_id#'] = $cmd->getId();
            $replace['#humidity_collectDate#'] = 'Date de valeur : '.$cmd->getValueDate().'<br>Date de collecte : '.$cmd->getCollectDate();
            if ($cmd->getIsHistorized() == 1) {
                $replace['#humidity_history#'] = 'history cursor';
            }

            $cmd = $this->getCmd(null, 'presence');
            $pres = $cmd->execCmd();
            $replace['#presence_id#'] = $cmd->getId();
            $replace['#presence_collectDate#'] = 'Date de valeur : '.$cmd->getValueDate().'<br>Date de collecte : '.$cmd->getCollectDate();
            if ($cmd->getIsHistorized() == 1) {
                $replace['#presence_history#'] = 'history cursor';
            }
            $replace['#pres_class#'] = 'fa fa-check';
            if ($pres == 0) {
                $replace['#pres_class#'] = 'fa fa-times';
            }

            $cmd = $this->getCmd(null, 'last_presence');
            $replace['#lastpres#'] = $cmd->execCmd();
            $replace['#lastpres_id#'] = $cmd->getId();
            $replace['#lastpres_collectDate#'] = 'Date de valeur : '.$cmd->getValueDate().'<br>Date de collecte : '.$cmd->getCollectDate();

            $cmd = $this->getCmd(null, 'heating');
            $heating = $cmd->execCmd();
            $replace['#heating_id#'] = $cmd->getId();
            $replace['#heating_collectDate#'] = 'Date de valeur : '.$cmd->getValueDate().'<br>Date de collecte : '.$cmd->getCollectDate();
            if ($cmd->getIsHistorized() == 1) {
                $replace['#heating_history#'] = 'history cursor';
            }

            if ($heating > 0) $replace['#imgheating#'] = '/plugins/qivivo/core/img/heating_on.png';
            else $replace['#imgheating#'] = '/plugins/qivivo/core/img/heating_off.png';

            $html = template_replace($replace, getTemplate('core', $version, 'thermostat', 'qivivo'));
        }

        if ($_thisType == 'Module Chauffage')
        {
            //infos
            $cmd = $this->getCmd(null, 'module_order');
            $order = $cmd->execCmd();
            $replace['#order#'] = $order;
            $replace['#order_id#'] = $cmd->getId();
            $replace['#order_collectDate#'] = 'Date de valeur : '.$cmd->getValueDate().'<br>Date de collecte : '.$cmd->getCollectDate();
            if ($cmd->getIsHistorized() == 1) {
                $replace['#order_history#'] = 'history cursor';
            }

            $cmd = $this->getCmd(null, 'last_communication');
            $replace['#last_communication#'] = $cmd->execCmd();
            $replace['#last_communication_id#'] = $cmd->getId();
            if ($cmd->getIsHistorized() == 1) {
                $replace['#last_communication_history#'] = 'history cursor';
            }

            //actions:
            if ($order != 'monozone')
            {
                $cmd = $this->getCmd(null, 'set_order');
                $replace['#set_order_id#'] = $cmd->getId();
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
                $replace['#set_order_listValue#'] = $options;
            }
            $cmd = $this->getCmd(null, 'current_program');
            $current_program = $cmd->execCmd();
            $replace['#program_collectDate#'] = 'Date de valeur : '.$cmd->getValueDate().'<br>Date de collecte : '.$cmd->getCollectDate();

            $cmd = $this->getCmd(null, 'set_program');
            $replace['#set_program_id#'] = $cmd->getId();
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
            $replace['#set_program_listValue#'] = $options;

            if ($order == 'monozone') $html = template_replace($replace, getTemplate('core', $version, 'module-t', 'qivivo'));
            else $html = template_replace($replace, getTemplate('core', $version, 'module', 'qivivo'));
        }

        if ($_thisType == 'Passerelle')
        {
            $cmd = $this->getCmd(null, 'last_communication');
            $replace['#last_communication#'] = $cmd->execCmd();
            $replace['#last_communication_id#'] = $cmd->getId();
            $replace['#last_communication_collectDate#'] = 'Date de valeur : '.$cmd->getValueDate().'<br>Date de collecte : '.$cmd->getCollectDate();
            if ($cmd->getIsHistorized() == 1) {
                $replace['#last_communication_history#'] = 'history cursor';
            }


            $cmd = $this->getCmd(null, 'firmware_version');
            $replace['#firmware_version#'] = $cmd->execCmd();
            $replace['#firmware_version_id#'] = $cmd->getId();
            $replace['#firmware_version_collectDate#'] = 'Date de valeur : '.$cmd->getValueDate().'<br>Date de collecte : '.$cmd->getCollectDate();
            if ($cmd->getIsHistorized() == 1) {
                $replace['#firmware_version_history#'] = 'history cursor';
            }

            $html = template_replace($replace, getTemplate('core', $version, 'gateway', 'qivivo'));
        }

        return $html;
    }

    public static function exportProgram($_name, $_program) {
        $folderPath = dirname(__FILE__) . '/../../exportedPrograms/';
        if (!is_dir($folderPath)) mkdir($folderPath, 0755, true);

        $now = date("dmY_His");
        $fileName = $now.'_'.$_name.'.json';
        qivivo::logger($fileName);
        $file = fopen($folderPath.$fileName, 'w');
        $res = fwrite($file, json_encode($_program));
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
                if ($cmdId != '') {
                    if (!cmd::byId($cmdId)) {
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
        $_uuid = $eqLogic->getConfiguration('uuid');
        qivivo::logger('action: '.$_action.' options: '.json_encode($_options));

        if ($_type == 'Module Chauffage') {
            if ($_action == 'set_order') {
                $modeNum = $_options['select'];
                if ($modeNum == '') return;

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
                $message = 'set_order '.$eqLogic->getName().' to '.$modeString;
                qivivo::logger($message);

                $_fullQivivo = qivivo::getCustomAPI('action', $this, $_options, $message);
                if ($_fullQivivo == False) {
                    qivivo::logger('set_order: could not get customAPI, ending.');
                    return;
                }

                $result = $_fullQivivo->setZoneMode($zone_name, $modeNum);

                if ($result['result']==True)
                {
                    qivivo::logger('set_order: success');
                    $eqLogic->checkAndUpdateCmd('module_order', $modeString);
                    $eqLogic->refreshWidget();
                } else {
                    qivivo::logger('set_order: error: '.json_encode($result['error']), 'warning');
                }
                return;
            }

            if ($_action == 'set_program') {
                $program = $_options['select'];
                if ($program == '') return;

                $program_name = $eqLogic->getConfiguration('program_name');
                $message = 'set_program '.$eqLogic->getName().' | '.$program_name.' to '.$program;
                qivivo::logger($message);

                if ($eqLogic->getConfiguration('isModuleThermostat') == 0)
                {
                    $mode_refs_array = ['Confort'=>'mz_comfort',
                                        'Confort-1'=>'mz_comfort_minus_one',
                                        'Confort-2'=>'mz_comfort_minus_two',
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

                        $_fullQivivo = qivivo::getCustomAPI('action', $this, $_options, $message);
                        if ($_fullQivivo == False) {
                            qivivo::logger('set_program: could not get customAPI, ending.');
                            return;
                        }
                        $result = $_fullQivivo->setProgram($program_name, $program_array);

                        if ($result['result']==True)
                        {
                            qivivo::logger('set_program: success');
                            $eqLogic->checkAndUpdateCmd('current_program', $program);
                            $eqLogic->refreshWidget();
                        } else {
                            qivivo::logger('set_program: error: '.json_encode($result['error']), 'warning');
                        }
                        return;
                    }
                }
                qivivo::logger('Unfound program!', 'warning');
                return;
            }
        }

        if ($_type == 'Thermostat') {
            if ($_action == 'set_time_order') {
                qivivo::logger('set_time_order: '.$_options['slider']);
                $duree_temp = $eqLogic->getCmd(null, 'duree_temp');
                $duree_temp->event($_options['slider']);
                return;
            }

            if ($_action == 'set_plus_one') {
                $info = $eqLogic->getCmd(null, 'temperature_order');
                $temp = $info->execCmd();
                $temp += 1;
                $message = 'set_plus_one to '.$temp.' Temp: '.$Temp;
                qivivo::logger('set_plus_one: '.$message);

                $_qivivo = qivivo::getAPI('action', $this, $_options, $message);
                if ($_qivivo == False) {
                    qivivo::logger('set_plus_one: could not get API, ending.');
                    return;
                }

                $result = $_qivivo->setThermostatTemperature($temp + 0.001, 120, $_uuid);

                if ($result['result']==True)
                {
                    qivivo::logger('set_plus_one: success');
                } else {
                    qivivo::logger('set_plus_one: error: '.json_encode($result['error']), 'warning');
                }
                return;
            }

            if ($_action == 'set_minus_one') {
                $info = $eqLogic->getCmd(null, 'temperature_order');
                $temp = $info->execCmd();
                $temp -= 1;
                $message = 'set_minus_one to '.$temp.' Temp: '.$Temp;
                qivivo::logger('set_minus_one: '.$message);

                $_qivivo = qivivo::getAPI('action', $this, $_options, $message);
                if ($_qivivo == False) {
                    qivivo::logger('set_minus_one: could not get API, ending.');
                    return;
                }

                $result = $_qivivo->setThermostatTemperature($temp + 0.001, 120, $_uuid);

                if ($result['result']==True)
                {
                    qivivo::logger('set_minus_one: success');
                } else {
                    qivivo::logger('set_minus_one: error: '.json_encode($result['error']), 'warning');
                }
                return;
            }

            if ($_action == 'set_temperature_order') {
                $order = $_options['slider'];
                $info = $eqLogic->getCmd(null, 'duree_temp');
                $duree_temp = intval($info->execCmd());
                $message = 'set_temperature_order to '.$order.' duree_temp: '.$duree_temp;
                qivivo::logger('set_temperature_order: '.$message);

                $_qivivo = qivivo::getAPI('action', $this, $_options, $message);
                if ($_qivivo == False) {
                    qivivo::logger('set_temperature_order: could not get API, ending.');
                    return;
                }

                $result = $_qivivo->setThermostatTemperature($order, $duree_temp, $_uuid);

                if ($result['result']==True)
                {
                    qivivo::logger('set_temperature_order: success');
                } else {
                    qivivo::logger('set_temperature_order: error: '.json_encode($result['error']), 'warning');
                }
                return;
            }

            if ($_action == 'cancel_time_order') {
                $message = 'cancel_time_order';
                qivivo::logger('cancel_time_order: '.$message);

                $_qivivo = qivivo::getAPI('action', $this, $_options, $message);
                if ($_qivivo == False) {
                    qivivo::logger('cancel_time_order: could not get API, ending.');
                    return;
                }

                $result = $_qivivo->cancelThermostatTemperature($_uuid);

                if ($result['result']==True)
                {
                    qivivo::logger('cancel_time_order: success');
                } else {
                    qivivo::logger('cancel_time_order: error: '.json_encode($result['error']), 'warning');
                }
                return;
            }

            if ($_action == 'set_absence_temperature') {
                $message = 'set_absence_temperature to '.$_options['slider'];
                qivivo::logger('set_absence_temperature: '.$message);

                $_qivivo = qivivo::getAPI('action', $this, $_options, $message);
                if ($_qivivo == False) {
                    qivivo::logger('set_absence_temperature: could not get API, ending.');
                    return;
                }

                $result = $_qivivo->setSetting('absence_temperature', $_options['slider']);

                if ($result['result']==True)
                {
                    qivivo::logger('set_absence_temperature: success');
                } else {
                    qivivo::logger('set_absence_temperature: error: '.json_encode($result['error']), 'warning');
                }
                return;
            }

            if ($_action == 'set_frost_temperature') {
                $message = 'set_frost_temperature to '.$_options['slider'];
                qivivo::logger('set_frost_temperature: '.$message);

                $_qivivo = qivivo::getAPI('action', $this, $_options, $message);
                if ($_qivivo == False) {
                    qivivo::logger('set_frost_temperature: could not get API, ending.');
                    return;
                }

                $result = $_qivivo->setSetting('frost_protection_temperature', $_options['slider']);

                if ($result['result']==True)
                {
                    qivivo::logger('set_frost_temperature: success');
                } else {
                    qivivo::logger('set_frost_temperature: error: '.json_encode($result['error']), 'warning');
                }
                return;
            }

            if ($_action == 'set_night_temperature') {
                $message = 'set_night_temperature to '.$_options['slider'];
                qivivo::logger('set_night_temperature: '.$message);

                $_qivivo = qivivo::getAPI('action', $this, $_options, $message);
                if ($_qivivo == False) {
                    qivivo::logger('set_night_temperature: could not get API, ending.');
                    return;
                }

                $result = $_qivivo->setSetting('night_temperature', $_options['slider']);

                if ($result['result']==True)
                {
                    qivivo::logger('set_night_temperature: success');
                } else {
                    qivivo::logger('set_night_temperature: error: '.json_encode($result['error']), 'warning');
                }
                return;
            }

            if ($_action == 'set_presence_temperature_1') {
                $message = 'set_presence_temperature_1 to '.$_options['slider'];
                qivivo::logger('set_presence_temperature_1: '.$message);

                $_qivivo = qivivo::getAPI('action', $this, $_options, $message);
                if ($_qivivo == False) {
                    qivivo::logger('set_presence_temperature_1: could not get API, ending.');
                    return;
                }

                $result = $_qivivo->setSetting('presence_temperature_1', $_options['slider']);

                if ($result['result']==True)
                {
                    qivivo::logger('set_presence_temperature_1: success');
                } else {
                    qivivo::logger('set_presence_temperature_1: error: '.json_encode($result['error']), 'warning');
                }
                return;
            }

            if ($_action == 'set_presence_temperature_2') {
                $message = 'set_presence_temperature_2 to '.$_options['slider'];
                qivivo::logger('set_presence_temperature_2: '.$message);

                $_qivivo = qivivo::getAPI('action', $this, $_options, $message);
                if ($_qivivo == False) {
                    qivivo::logger('set_presence_temperature_2: could not get API, ending.');
                    return;
                }

                $result = $_qivivo->setSetting('presence_temperature_2', $_options['slider']);

                if ($result['result']==True)
                {
                    qivivo::logger('set_presence_temperature_2: success');
                } else {
                    qivivo::logger('set_presence_temperature_2: error: '.json_encode($result['error']), 'warning');
                }
                return;
            }

            if ($_action == 'set_presence_temperature_3') {
                $message = 'set_presence_temperature_3 to '.$_options['slider'];
                qivivo::logger('set_presence_temperature_3: '.$message);

                $_qivivo = qivivo::getAPI('action', $this, $_options, $message);
                if ($_qivivo == False) {
                    qivivo::logger('set_presence_temperature_3: could not get API, ending.');
                    return;
                }

                $result = $_qivivo->setSetting('presence_temperature_3', $_options['slider']);

                if ($result['result']==True)
                {
                    qivivo::logger('set_presence_temperature_3: success');
                } else {
                    qivivo::logger('set_presence_temperature_3: error: '.json_encode($result['error']), 'warning');
                }
                return;
            }

            if ($_action == 'set_presence_temperature_4') {
                $message = 'set_presence_temperature_4 to '.$_options['slider'];
                qivivo::logger('set_presence_temperature_4: '.$message);

                $_qivivo = qivivo::getAPI('action', $this, $_options, $message);
                if ($_qivivo == False) {
                    qivivo::logger('set_presence_temperature_4: could not get API, ending.');
                    return;
                }

                $result = $_qivivo->setSetting('presence_temperature_4', $_options['slider']);

                if ($result['result']==True)
                {
                    qivivo::logger('set_presence_temperature_4: success');
                } else {
                    qivivo::logger('set_presence_temperature_4: error: '.json_encode($result['error']), 'warning');
                }
                return;
            }
        }

        if ($_type == 'Passerelle') {
            if ($_action == 'debug') {
                qivivo::logger();
                qivivo::getDebugInfos();
            }
        }
    }
}
