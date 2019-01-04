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

if (!isConnect('admin')) {
    throw new Exception('{{401 - Accès non autorisé}}');
}
    include_file('desktop', 'qivivo', 'js', 'qivivo');
    sendVarToJS('importProgramTo_', $_GET['programName']);
    $_isImporterThermostat = $_GET['isThermostat'];

    //get all exported programs:
    $folderPath = dirname(__FILE__) . '/../../exportedPrograms/';
    $command = 'ls '.$folderPath;
    $res = exec($command, $output, $return_var);

    $div = '<div class="col-sm-12" style="background-color:darkgrey;padding-top:5px;">';
        $div .= '<div class="form-group">';
            $div .= '<div class="col-sm-4">';
                $div .= '<label>Fichier</label>';
            $div .= '</div>';
            $div .= '<div class="col-sm-2">';
                $div .= '<label>Zone Thermostat</label>';
            $div .= '</div>';
            $div .= '<div class="col-sm-2">';
                $div .= '<label>Origine</label>';
            $div .= '</div>';
            $div .= '<div class="col-sm-1">';
                $div .= '<label>Programme</label>';
            $div .= '</div>';
            $div .= '<div class="col-sm-3">';
                $div .= '<label>Fonctions</label>';
            $div .= '</div>';
        $div .= '</div>';
    $div .= '</div>';
    echo $div;

    foreach ($output as $fileName)
    {
        $file = file_get_contents($folderPath.$fileName);
        $_json = json_decode($file);
        $isThermostat = $_json->isThermostat;
        if ($isThermostat == '0') $isThermostatString = 'Non';
        else $isThermostatString = 'Oui';

        $_uuid = $_json->origin;
        $origin = 'Unknown';
        $eqLogic = eqLogic::byLogicalId($_uuid, 'qivivo');
        if (is_object($eqLogic)) $origin = $eqLogic->getName();

        $_name = $_json->name;
        $downloadlink = str_replace('/var/www/html', '', $folderPath).$fileName;
        $downloadlink = '../../plugins/qivivo/exportedPrograms/'.$fileName;

        $div = '<div class="mayImportProgram col-sm-12" style="display:;padding-top:5px">';
            $div .= '<div class="form-group">';
                $div .= '<div class="col-sm-4">';
                    $div .= '<input type="text" class="form-control" value="'.$fileName.'" readonly />';
                $div .= '</div>';
                $div .= '<div class="col-sm-2">';
                    $div .= '<label>'.$isThermostatString.'</label>';
                $div .= '</div>';
                $div .= '<div class="col-sm-2">';
                    $div .= '<label>'.$origin.'</label>';
                $div .= '</div>';
                $div .= '<div class="col-sm-1">';
                    $div .= '<label>'.$_name.'</label>';
                $div .= '</div>';
                $div .= '<div class="col-sm-3">';
                if (intval($_isImporterThermostat) == intval($isThermostat)) {
                    $div .= '<a class="btn btn-success bt_importProgram" filename="'.$fileName.'"><i class="fa fa-plus-circle"></i> {{Importer}}</a>';
                }   else {
                    $div .= '<a disabled class="btn btn-success bt_importProgram" filename="'.$fileName.'"><i class="fa fa-plus-circle"></i> {{Importer}}</a>';
                }
                    $div .= '  ';
                    $div .= '<a class="btn btn-success bt_downloadProgram" filename="'.$fileName.'"><i class="fa fa-download"></i></a>';
                    $div .= '  ';
                    $div .= '<a class="btn btn-success bt_deleteProgram" filename="'.$fileName.'"><i class="fa divers-slightly"></i></a>';
                $div .= '</div>';
            $div .= '</div>';
        $div .= '</div>';
        echo $div;
    }
?>
