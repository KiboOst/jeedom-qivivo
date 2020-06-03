<?php
/*

https://github.com/KiboOst/php-simpleQivivoAPI

*/

class splQivivoAPI {

    public $_version = '0.17';

    //USER FUNCTIONS======================================================

    //GET FUNCTIONS:
    public function getGatewayInfos()
    {
        $url = $this->_apiRoot.'/devices/gateways/'.$this->_gatewayUUID.'/info';
        $answer = $this->_request('GET', $url);
        return json_decode($answer, true);
    }

    public function getDevices()
    {
        return $this->_devices;
    }

    public function getModuleInfos($uuid)
    {
        $url = $this->_apiRoot.'/devices/wireless-modules/'.$uuid.'/info';
        $answer = $this->_request('GET', $url);
        return json_decode($answer, true);
    }

    public function getModuleTemperature($uuid)
    {
        $url = $this->_apiRoot.'/devices/wireless-modules/'.$uuid.'/temperature';
        $answer = $this->_request('GET', $url);
        return json_decode($answer, true);
    }

    public function getModuleHumidity($uuid)
    {
        $url = $this->_apiRoot.'/devices/wireless-modules/'.$uuid.'/humidity';
        $answer = $this->_request('GET', $url);
        return json_decode($answer, true);
    }

    public function getModulePrograms($uuid)
    {
        $url = $this->_apiRoot.'/devices/wireless-modules/'.$uuid.'/programs';
        $answer = $this->_request('GET', $url);
        return json_decode($answer, true);
    }

    public function getModuleLastOrder($uuid)
    {
        $url = $this->_apiRoot.'/devices/wireless-modules/'.$uuid.'/pilot-wire-order';
        $answer = $this->_request('GET', $url);
        return json_decode($answer, true);
    }

    public function getThermostatInfos($uuid=null)
    {
        if (!$uuid) $uuid = $this->_thermostatsUUID[0];
        $url = $this->_apiRoot.'/devices/thermostats/'.$uuid.'/info';
        $answer = $this->_request('GET', $url);
        return json_decode($answer, true);
    }

    public function getThermostatTemperature($uuid=null)
    {
        if (!$uuid) $uuid = $this->_thermostatsUUID[0];
        $url = $this->_apiRoot.'/devices/thermostats/'.$uuid.'/temperature';
        $answer = $this->_request('GET', $url);
        return json_decode($answer, true);
    }

    public function getThermostatHumidity($uuid=null)
    {
        if (!$uuid) $uuid = $this->_thermostatsUUID[0];
        $url = $this->_apiRoot.'/devices/thermostats/'.$uuid.'/humidity';
        $answer = $this->_request('GET', $url);
        return json_decode($answer, true);
    }

    public function getThermostatPresence($uuid=null)
    {
        if (!$uuid) $uuid = $this->_thermostatsUUID[0];
        $url = $this->_apiRoot.'/devices/thermostats/'.$uuid.'/presence';
        $answer = $this->_request('GET', $url);
        return json_decode($answer, true);
    }

    public function getThermostatPrograms($uuid=null)
    {
        if (!$uuid) $uuid = $this->_thermostatsUUID[0];
        $url = $this->_apiRoot.'/devices/thermostats/'.$uuid.'/programs';
        $answer = $this->_request('GET', $url);
        return json_decode($answer, true);
    }

    public function getLastPresence()
    {
        $url = $this->_apiRoot.'/habitation/data/last-presence';
        $answer = $this->_request('GET', $url);
        return json_decode($answer, true);
    }

    public function getSettings()
    {
        $url = $this->_apiRoot.'/habitation/data/settings';
        $answer = $this->_request('GET', $url);
        return json_decode($answer, true);
    }

    public function getEvents()
    {
        $url = $this->_apiRoot.'/habitation/data/events';
        $answer = $this->_request('GET', $url);
        return json_decode($answer, true);
    }


    //SET FUNCTIONS:
    public function setAbsence($startDate, $EndDate, $uuid=null)
    {
        if (!$uuid) $uuid = $this->_thermostatsUUID[0];
        $url = $this->_apiRoot.'/devices/thermostats/'.$uuid.'/absence';
        $post = array('start_date'=> $startDate, 'end_date'=> $EndDate);
        $answer = $this->_request('POST', $url, json_encode($post));
        return json_decode($answer, true);
    }

    public function cancelAbsence($uuid=null)
    {
        if (!$uuid) $uuid = $this->_thermostatsUUID[0];
        $url = $this->_apiRoot.'/devices/thermostats/'.$uuid.'/absence';
        $answer = $this->_request('DELETE', $url);
        return json_decode($answer, true);
    }

    public function setModuleProgram($uuid, $program)
    {
        $program = intval($program);
        $url = $this->_apiRoot.'/devices/wireless-modules/'.$uuid.'/programs/'.$program.'/active';
        $answer = $this->_request('PUT', $url);
        return json_decode($answer, true);
    }

    public function setSetting($setting, $value)
    {
        //presence_temperature_1 presence_temperature_2 presence_temperature_3 presence_temperature_4 night_temperature absence_temperature frost_protection_temperature
        $value = floatval(number_format($value+0.01, 2, '.', '')); //add 0.01 so php can handle 0.00 as float!!
        $url = $this->_apiRoot.'/habitation/settings/define_temperature';
        $post = array('temperature_setting_name'=> $setting, 'temperature'=> $value);
        $answer = $this->_request('PUT', $url, json_encode($post));
        return json_decode($answer, true);
    }

    public function setAbsenceAlertDays($days)
    {
        //bug in official API, number must be int, so 1.5 ...
        $url = $this->_apiRoot.'/habitation/settings/absence/alert';
        $post = array('new_nb_day'=> $days);
        $answer = $this->_request('PUT', $url, json_encode($post));
        return json_decode($answer, true);
    }

    public function setThermostatTemperature($temperature, $minutes, $uuid=null)
    {
        if (!$uuid) $uuid = $this->_thermostatsUUID[0];
        $temperature = floatval(number_format($temperature+0.01, 2, '.', '')); //add 0.01 so php can handle 0.00 as float!!
        $url = $this->_apiRoot.'/devices/thermostats/'.$uuid.'/temperature/temporary-instruction';
        $post = array('temperature'=> $temperature, 'duration'=> $minutes);
        $answer = $this->_request('POST', $url, json_encode($post));
        return json_decode($answer, true);
    }

    public function cancelThermostatTemperature($uuid=null)
    {
        if (!$uuid) $uuid = $this->_thermostatsUUID[0];
        $url = $this->_apiRoot.'/devices/thermostats/'.$uuid.'/temperature/temporary-instruction';
        $answer = $this->_request('DELETE', $url);
        return json_decode($answer, true);
    }

    public function createThermostatProgram($program, $uuid=null)
    {
        if (!$uuid) $uuid = $this->_thermostatsUUID[0];
        $url = $this->_apiRoot.'/devices/thermostats/'.$uuid.'/programs';
        $answer = $this->_request('POST', $url, json_encode($program));
        return json_decode($answer, true);
    }

    public function setActiveThermostatProgram($name, $uuid=null)
    {
        if (!$uuid) $uuid = $this->_thermostatsUUID[0];
        $progID = $this->getProgramIDbyName($name);

        $url = $this->_apiRoot.'/devices/thermostats/'.$uuid.'/programs/'.$progID.'/active';
        $answer = $this->_request('PUT', $url);
        return json_decode($answer, true);
    }

    public function renameThermostatProgram($name, $newName, $uuid=null)
    {
        if (!$uuid) $uuid = $this->_thermostatsUUID[0];
        $progID = $this->getProgramIDbyName($name);

        $url = $this->_apiRoot.'/devices/thermostats/'.$uuid.'/programs/'.$progID.'/name';
        $post = array('new_name'=> $newName);
        $answer = $this->_request('PUT', $url, json_encode($post));
        return json_decode($answer, true);
    }

    public function deleteThermostatProgram($name, $uuid=null)
    {
        if (!$uuid) $uuid = $this->_thermostatsUUID[0];
        $progID = $this->getProgramIDbyName($name);

        $url = $this->_apiRoot.'/devices/thermostats/'.$uuid.'/programs/'.$progID ;
        $answer = $this->_request('DELETE', $url);
        return json_decode($answer, true);
    }

    //INTERNAL FUNCTIONS==================================================
    public function getProgramIDbyName($name)
    {
        $thermoPrograms = $this->getThermostatPrograms();
        $progs = $thermoPrograms['user_programs'];
        foreach ($progs as $prog)
        {
            if ($prog['name'] == $name) return $prog['id'];
        }
        return false;
    }

    //CALLING FUNCTIONS===================================================
    protected function getDatas() //request qivivo devices
    {
        $url = $this->_apiRoot.'/devices';
        $answer = $this->_request('GET', $url);
        $jsonAnswer = json_decode($answer, true);

        $this->_devices = $jsonAnswer['devices'];
        $this->_thermostatsUUID = array();
        foreach ($this->_devices as $device)
        {
            if ($device['type'] == 'thermostat')
            {
                array_push($this->_thermostatsUUID, $device['uuid']);
            }
            if ($device['type'] == 'gateway')
            {
                $this->_gatewayUUID = $device['uuid'];
            }
        }

        return array('result'=>true);
    }

    protected function _request($method, $url, $post=null)
    {
        if (!isset($this->_curlHdl))
        {
            $this->_curlHdl = curl_init();
            curl_setopt($this->_curlHdl, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($this->_curlHdl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($this->_curlHdl, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($this->_curlHdl, CURLOPT_SSL_VERIFYPEER, 0);
        }

        curl_setopt($this->_curlHdl, CURLOPT_URL, $url);
        if (isset($this->_token))
        {
            curl_setopt($this->_curlHdl, CURLOPT_HTTPHEADER, array(
                                        'authorization: Bearer '.$this->_token,
                                        'content-type: application/json'
                                        ));
        }

        curl_setopt($this->_curlHdl, CURLOPT_CUSTOMREQUEST, $method);

        curl_setopt($this->_curlHdl, CURLOPT_POSTFIELDS, '');
        if (isset($post)) curl_setopt($this->_curlHdl, CURLOPT_POSTFIELDS, $post);

        $response = curl_exec($this->_curlHdl);
        return $response;

        /*
        if(curl_errno($this->_curlHdl))
        {
            echo 'Curl error: '.curl_error($this->_curlHdl);
        }

        if ($response === false)
        {
            echo 'cURL error: '.curl_error($this->_curlHdl);
        }
        else
        {
            return $response;
        }
        */
    }

    //AUTHORIZATION=======================================================
    public $error = null;
    public $_token = null;
    public $_tokenType = null;
    public $_scope = null;
    public $_grantType = 'client_credentials';

    public $_devices = null;
    public $_thermostatsUUID = null;
    public $_gatewayUUID = null;

    public $_isMultizone = false;

    protected $_clientID;
    protected $_secretID;
    protected $_access_token_url = 'https://account.qivivo.com/oauth/token';
    protected $_apiRoot = 'https://data.qivivo.com/api/v2/';
    protected $_curlHdl = null;

    protected function connect()
    {
        $url = $this->_access_token_url;
        $post = 'grant_type='.$this->_grantType.'&client_id='.$this->_clientID.'&client_secret='.$this->_secretID;
        $answer = $this->_request('POST', $url, $post);
        $jsonAnswer = json_decode($answer, true);

        if (isset($jsonAnswer['access_token']))
        {
            $this->_token = $jsonAnswer['access_token'];
            $this->_tokenType = $jsonAnswer['token_type'];
            $this->_scope = $jsonAnswer['scope'];
            return true;

        }
        return false;
    }

    function __construct($clientID, $secretID)
    {
        $this->_clientID = urlencode($clientID);
        $this->_secretID = urlencode($secretID);

        if ($this->connect() == true)
        {
            $this->getDatas();
        }
        else
        {
            $this->error = 'Could not connect to official Qivivo API!';
        }
    }
} //splQivivoAPI end
?>
