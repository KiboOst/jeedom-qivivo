/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */


//show module image:
$('.eqLogicAttr[data-l1key=configuration][data-l2key=type]').on('change',function(){
    $("#bt_tab_programs").hide()
    type = $(this).value()
    if (type == 'Thermostat') $('#img_qivivoModel').attr('src','plugins/qivivo/core/img/thermostat.png')
    if (type == 'Passerelle') $('#img_qivivoModel').attr('src','plugins/qivivo/core/img/gateway.png')
    if (type == 'Module Chauffage') $('#img_qivivoModel').attr('src','plugins/qivivo/core/img/module.png')
})

//command infos values:
$('.eqLogicAttr[data-l1key=configuration][data-l2key=serial]').on('change',function(){
    serial = $(this).value()
    if (serial == null) return

    //hide all infos divs:
    $("div[data-cmd_id='moduleOrder']").hide()
    $("div[data-cmd_id='moduleZone']").hide()
    $("div[data-cmd_id='last_communication']").hide()
    $("div[data-cmd_id='firmware_version']").hide()
    $("div[data-cmd_id='temperature_order']").hide()
    $("div[data-cmd_id='dureeordre']").hide()

    $("div[data-cmd_id='paramTempAbsence']").hide()
    $("div[data-cmd_id='paramTempHG']").hide()
    $("div[data-cmd_id='paramTempNuit']").hide()
    $("div[data-cmd_id='paramTempPres1']").hide()
    $("div[data-cmd_id='paramTempPres2']").hide()
    $("div[data-cmd_id='paramTempPres3']").hide()
    $("div[data-cmd_id='paramTempPres4']").hide()
    $("div[data-cmd_id='battery']").hide()

    $.ajax({
        type: "POST",
        url: "plugins/qivivo/core/ajax/qivivo.ajax.php",
        data: { action: "getTypeAndValues", _serial: serial},
        dataType: 'json',
        error: function (request, status, error) {
            handleAjaxError(request, status, error)
        },
        success: function (data) {
            if (data.result.type != undefined) serial_callback(data)
        }
    })
})

function serial_callback(data) {
    _type = data.result.type

    //common infos:
    $("div[data-cmd_id='last_communication']").show()
    $("span[data-cmd_id='last_communication']").html(data.result.last_communication)

    $("div[data-cmd_id='firmware_version']").show()
    $("span[data-cmd_id='firmware_version']").html(data.result.firmware_version)

    //specific infos:
    if (_type == 'Thermostat')
    {
        $("div[data-cmd_id='temperature_order']").show()
        $("span[data-cmd_id='temperature_order']").html(data.result.temperature_order + ' °C')

        $("div[data-cmd_id='dureeordre']").show()
        $("span[data-cmd_id='dureeordre']").html(data.result.dureeordre + ' Mins')

        $("div[data-cmd_id='paramTempAbsence']").show()
        $("span[data-cmd_id='paramTempAbsence']").html(data.result.paramTempAbsence + ' °C')

        $("div[data-cmd_id='paramTempHG']").show()
        $("span[data-cmd_id='paramTempHG']").html(data.result.paramTempHG + ' °C')

        $("div[data-cmd_id='paramTempNuit']").show()
        $("span[data-cmd_id='paramTempNuit']").html(data.result.paramTempNuit + ' °C')

        $("div[data-cmd_id='paramTempPres1']").show()
        $("span[data-cmd_id='paramTempPres1']").html(data.result.paramTempPres1 + ' °C')

        $("div[data-cmd_id='paramTempPres2']").show()
        $("span[data-cmd_id='paramTempPres2']").html(data.result.paramTempPres2 + ' °C')

        $("div[data-cmd_id='paramTempPres3']").show()
        $("span[data-cmd_id='paramTempPres3']").html(data.result.paramTempPres3 + ' °C')

        $("div[data-cmd_id='paramTempPres4']").show()
        $("span[data-cmd_id='paramTempPres4']").html(data.result.paramTempPres4 + ' °C')

        $("div[data-cmd_id='battery']").show()
        $("span[data-cmd_id='battery']").html(data.result.battery + ' %')

        $('#spanEqType').html("{{Thermostat}}")
    }

    if (_type == 'Module Chauffage')
    {
        $("div[data-cmd_id='moduleOrder']").show()
        value = data.result.module_order
        if (value == 'monozone') value += ' [Zone Thermostat]'
        $("span[data-cmd_id='module_order']").html(value)

        $("div[data-cmd_id='moduleZone']").show()

        $('#spanEqType').html("{{Module Chauffage}}")
    }

    if (_type == 'Passerelle')
    {
        $('#spanEqType').html("{{Passerelle}}")
    }
}


function printEqLogic(_eqLogic) {

}

function saveEqLogic(_eqLogic) {
    if (!isset(_eqLogic.configuration)) {
        _eqLogic.configuration = {}
    }
    return _eqLogic
}

//Commandes:
function addCmdToTable(_cmd) {
    if (!isset(_cmd)) var _cmd = {configuration: {}}
    if (!isset(_cmd.configuration)) _cmd.configuration = {}
    var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">'
        tr += '<td>'
        tr += '<input class="cmdAttr form-control input-sm" data-l1key="id" style="display : none">'
        tr += '<input class="cmdAttr form-control input-sm" data-l1key="type" style="display : none">'
        tr += '<input class="cmdAttr form-control input-sm" data-l1key="subType" style="display : none">'
        tr += '<input class="cmdAttr form-control input-sm" data-l1key="name" style="width : 60%" placeholder="{{Nom}}"></td>'
        tr += '<input class="cmdAttr form-control type input-sm" data-l1key="type" value="info" disabled style="display : none" />'

        tr += '</td>'

        tr += '<td>'
        if (_cmd.subType == "numeric" || _cmd.subType == "binary") {
            tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isHistorized"/>{{Historiser}}</label></span> '
        }
        tr += '</td>'

        tr += '<td>'
        if (is_numeric(_cmd.id))
        {
            tr += '<a class="btn btn-default btn-xs cmdAction expertModeVisible" data-action="configure"><i class="fa fa-cogs"></i></a> '
            tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fa fa-rss"></i> {{Tester}}</a>'
        }
        tr += '</td>'
    tr += '</tr>'

    if (_cmd.type == 'info')
    {
        $('#table_infos tbody').append(tr)
        $('#table_infos tbody tr:last').setValues(_cmd, '.cmdAttr')
        if (isset(_cmd.type)) $('#table_infos tbody tr:last .cmdAttr[data-l1key=type]').value(init(_cmd.type))
        jeedom.cmd.changeType($('#table_infos tbody tr:last'), init(_cmd.subType))
    }
    else
    {
        $('#table_actions tbody').append(tr)
        $('#table_actions tbody tr:last').setValues(_cmd, '.cmdAttr')
        if (isset(_cmd.type)) $('#table_actions tbody tr:last .cmdAttr[data-l1key=type]').value(init(_cmd.type))
        jeedom.cmd.changeType($('#table_actions tbody tr:last'), init(_cmd.subType))
    }
    if (_cmd.name == 'SetProgram') _setProgramId_ = _cmd.id
}
