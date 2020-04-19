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

//reference module type for use in programs:
JSONCLIPBOARD = null
PROGRAM_MODE_LIST = null
PROGRAM_OPTIONS_THERMOSTAT = ['<option class="select-mode-frost" value="Hors-Gel">Hors-Gel</option>',
                                 '<option class="select-mode-abs" value="Absence">Absence</option>',
                                 '<option class="select-mode-nuit" value="Nuit">Nuit</option>',
                                 '<option class="select-mode-pres1" value="Pres 1">Pres 1</option>',
                                 '<option class="select-mode-pres2" value="Pres 2">Pres 2</option>',
                                 '<option class="select-mode-pres3" value="Pres 3">Pres 3</option>',
                                 '<option class="select-mode-pres4" value="Pres 4">Pres 4</option>'
                                 ]

PROGRAM_OPTIONS_ZONE = ['<option class="select-mode-off" value="Arrêt">Arrêt</option>',
                                 '<option class="select-mode-frost" value="Hors-Gel">Hors-Gel</option>',
                                 '<option class="select-mode-eco" value="Eco">Eco</option>',
                                 '<option class="select-mode-confort-2" value="Confort-2">Confort-2</option>',
                                 '<option class="select-mode-confort-1" value="Confort-1">Confort-1</option>',
                                 '<option class="select-mode-confort" value="Confort">Confort</option>'
                                 ]
WEEKDAYSREF = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche']
WEEKDAYS = ['{{Lundi}}', '{{Mardi}}', '{{Mercredi}}', '{{Jeudi}}', '{{Vendredi}}', '{{Samedi}}', '{{Dimanche}}']

//show module image:
$('.eqLogicAttr[data-l1key=configuration][data-l2key=type]').on('change',function(){
    $("#bt_tab_programs").hide()
    type = $(this).value()
    if (type == 'Thermostat') $('#img_qivivoModel').attr('src','plugins/qivivo/core/img/thermostat.png')
    if (type == 'Passerelle') $('#img_qivivoModel').attr('src','plugins/qivivo/core/img/gateway.png')
    if (type == 'Module Chauffage')
    {
        $('#img_qivivoModel').attr('src','plugins/qivivo/core/img/module.png')
        $("#bt_tab_programs").show()
    }
})

//command infos values:
$('.eqLogicAttr[data-l1key=configuration][data-l2key=uuid]').on('change',function(){
    uuid = $(this).value()
    if (uuid == null) return

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
        data: { action: "getTypeAndValues", _uuid: uuid},
        dataType: 'json',
        error: function (request, status, error) {
            handleAjaxError(request, status, error)
        },
        success: function (data) {
            if (data.result.type != undefined) uuid_callback(data)
        }
    })
})

function uuid_callback(data) {
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


//Programmes:
$('#bt_addProgram').off('click').on('click', function () {
    bootbox.prompt("{{Nom du programme ?}}", function (result) {
        if (result !== null && result != '') addProgram({name: result, isNew: true})
    })
})

function addProgram(_program, _updateProgram) {
    if (init(_program.name) == '') return
    var random = Math.floor((Math.random() * 1000000) + 1)
    var div = '<div class="program panel panel-default">'
    div += '<div class="panel-heading">'
    div += '<h3 class="panel-title">'
    div += '<a class="accordion-toggle collapsed" data-toggle="collapse" data-parent="" href="#collapse' + random + '">'
    div += '<span class="name">' + _program.name + '</span>'
    div += '</a>'
    div += '</h3>'
    div += '</div>'
    div += '<div id="collapse' + random + '" class="panel-collapse collapse" style="height: 0px;">'
    div += '<div class="panel-body">'
    div += '<div>'
        div += '<form class="form-horizontal" role="form">'
        div += '<div class="form-group">'
            div += '<div class="col-sm-2">'
            div += '<span class="programAttr label label-info rename cursor" data-l1key="name" style="font-size : 1em" ></span>'
            div += '</div>'
            div += '<div class="col-sm-10">'
            div += '<div class="input-group pull-right" style="display:inline-flex">'
                div += '<span class="input-group-btn">'
                    div += '<a class="btn btn-sm bt_duplicateProgram btn-primary roundedLeft"><i class="fas fa-copy"></i> {{Dupliquer}}</a>'
                    div += '<a class="btn btn-sm bt_exportProgram btn-default"><i class="fas fa-sign-out-alt"></i> {{Exporter}}</a>'
                    div += '<a class="btn btn-sm bt_importProgram btn-default"><i class="fas fa-sign-in-alt"></i> {{Importer}}</a>'
                    div += '<a class="btn btn-sm bt_applyProgram btn-danger" title="Appliquez le programme maintenant"><i class="fas fa-check-circle"></i> {{Appliquer}}</a>'
                    div += '<a class="btn btn-sm bt_removeProgram btn-danger roundedRight"><i class="fas fa-minus-circle"></i> {{Supprimer}}</a>'
                div += '</span>'
            div += '</div>'
        div += '</div>'
        div += '</div>'
        div += '<div class="div_programDays">'
            WEEKDAYS.forEach(function(day) {
                div += createDayDiv(day)
            })
            //graphs:
            div += '<div class="graphDays" style="width:100%; clear:left">'
            div += '<hr>'
            //markets:
            div += '<div style="width:80px; display:inline-block;"></div>'
            div += '<div style="width:calc(100% - 80px); display:inline-block;">'
                div += '<div style="width: 25%; height:18px; display:inline-block;">00:00</div>'
                div += '<div style="width: 25%; height:18px; display:inline-block; position:inherit;">06:00</div>'
                div += '<div style="width: 25%; height:18px; display:inline-block; position:inherit;">12:00</div>'
                div += '<div style="width: 25%; height:18px; display:inline-block;">18:00</div>'
            div += '</div>'
                WEEKDAYS.forEach(function(day) {
                    div += '<div class="graphDayTitle" style="width:80px; display:inline-block;">'
                    div += day
                    div += '</div>'
                    div += '<div class="graphDayGraph_'+day+'" style="width:calc(100% - 80px); display:inline-block;">'
                    div += '</div>'
                })
            div += '</div>'
            div += '</div>'
        div += '</div>'
        div += '</form>'
    div += '</div>'
    div += '</div>'
    div += '</div>'
    div += '</div>'

    $('#div_programs').append(div)
    $('#div_programs .program:last').setValues(_program, '.programAttr')

    //init days:
    if (_program.isNew) {
        $('#div_programs .program:last .weekDay').each(function () {
            day = $(this).closest('.weekDay')
            addPeriod(day)
        })
    }
}

$("#div_programs").off('click','.bt_removeProgram').on('click', '.bt_removeProgram',function () {
    thisProgram = $(this).closest('.program')
    bootbox.confirm({
        message: "Voulez vous vraiment supprimer ce programme ?",
        buttons: {
            confirm: {
                label: 'Yes',
                className: 'btn-success'
            },
            cancel: {
                label: 'No',
                className: 'btn-danger'
            }
        },
        callback: function (result) {
            if (result === true) {
                thisProgram.remove()
            }
        }
    })
})

$('#div_programs').off('click','.bt_duplicateProgram').on('click','.bt_duplicateProgram',  function () {
    var program = $(this).closest('.program').clone()
    bootbox.prompt("{{Nom du programme ?}}", function (result) {
        if (result !== null) {
            var random = Math.floor((Math.random() * 1000000) + 1)
            program.find('a[data-toggle=collapse]').attr('href', '#collapse' + random)
            program.find('.panel-collapse.collapse').attr('id', 'collapse' + random)
            program.find('.programAttr[data-l1key=name]').html(result)
            program.find('.name').html(result)
            $('#div_programs').append(program)
            $('.collapse').collapse()
        }
    })
})

$('#div_programs').off('click','.bt_applyProgram').on('click','.bt_applyProgram',  function () {
    program = $(this).closest('.program')
    programName = program.find('.programAttr[data-l1key=name]').html()
    bootbox.confirm({
        message: "Voulez vous vraiment appliquer le programme "+programName+" maintenant ?",
        buttons: {
            confirm: {
                label: 'Yes',
                className: 'btn-success'
            },
            cancel: {
                label: 'No',
                className: 'btn-danger'
            }
        },
        callback: function (result) {
            if (result === true) {
                jeedom.cmd.execute( {id: _setProgramId_, value: {select: programName} })
            }
        }
    })
})


$('body').off('click','.rename').on('click','.rename',  function () {
    var el = $(this)
    bootbox.prompt("{{Nouveau nom ?}}", function (result) {
        if (result !== null && result != '') {
            var previousName = el.text()
            el.text(result)
            el.closest('.panel.panel-default').find('span.name').text(result)
        }
    })
})

$("body").off('click', '.bt_removePeriod').on( 'click', '.bt_removePeriod',function () {
    dayDiv = $(this).closest('.weekDay')
    $(this).closest('.dayPeriod').remove()
    updateGraphDay(dayDiv)
});

$('body').off('click','.bt_addPeriod').on('click','.bt_addPeriod',  function () {
    dayDiv = $(this).closest('.weekDay')
    addPeriod(dayDiv)
})

$('body').off('click','.bt_copyDay').on('click','.bt_copyDay',  function () {
    var day = $(this).closest('.weekDay')
    copyDay(day)
})

$('body').off('click','.bt_pasteDay').on('click','.bt_pasteDay',  function () {
    dayDiv = $(this).closest('.weekDay')
    pasteDay(dayDiv)
})

function definePeriodMode(selectObject){
    selectedOption = selectObject.options[selectObject.selectedIndex]
    optionClass = selectedOption.className
    selectObject.className = selectObject.className.replace(/(^| )select-mode-[^ ]*/g, '')
    selectObject.classList.add(optionClass)
}

function createDayDiv(dayName){
    dayDiv = '<div class="weekDay '+dayName+'" style="width:14%; float:left" onchange="updateGraphDay(this)">'
    dayDiv += '<center>'
    dayDiv += '<strong class="dayName">'+dayName+'</strong>'
    dayDiv += '</br>'

    dayDiv += '<div class="input-group" style="display:inline-flex">'
        dayDiv += '<span class="input-group-btn">'
            dayDiv += '<span><i class="fa fa-plus-circle cursor bt_addPeriod" title="{{Ajouter une période}}"></i> </span>'
            dayDiv += '<span><i class="fas fa-sign-out-alt cursor bt_copyDay" title="{{Copier le jour}}"></i> </span>'
            dayDiv += '<span><i class="fas fa-sign-in-alt cursor bt_pasteDay" title="{{Coller le jour}}"></i> </span>'
        dayDiv += '</span></br>'
    dayDiv += '</div>'

    dayDiv += '</center></div>'
    return dayDiv
}

function updateGraphDay(dayDiv){
    classNames = $(dayDiv).attr("class")
    dayName = classNames.split(' ')[1]
    graphDiv = $(dayDiv).closest('.div_programDays').find('.graphDayGraph_' + dayName)
    graphDiv.empty()

    dayPeriods = $(dayDiv).find('.dayPeriod')
    l = dayPeriods.length
    for (i=0; i<l; i++)
    {
        isFirst = (i == 0) ? true : false
        isLast = (i == l-1) ? true : false
        period = dayPeriods[i]
        period_start = $(period).find('.timePicker').val()
        hours = parseInt(period_start.split(':')[0])
        minutes = parseInt(period_start.split(':')[1])
        timeStart = (hours * 60) + minutes
        if (isLast){
            period_end = '23:59'
            timeEnd = 1439
        }
        else {
            period_end = $(dayPeriods[i+1]).find('.timePicker').val()
            hours = parseInt(period_end.split(':')[0])
            minutes = parseInt(period_end.split(':')[1])
            timeEnd = (hours * 60) + minutes
            timeEnd -= 1
        }
        delta = timeEnd - timeStart
        width = (delta*100) / 1440
        period_class = $(period).find('.selectPeriodMode :selected').attr('class').split(' ').pop()
        temperature_setting = $(period).find('.selectPeriodMode :selected').text()
        newGraph = '<div class="'+period_class+'" style="width:'+width+'%; height:20px; border-right:1px solid gray; display:inline-block;" title="'+temperature_setting+'">'
        div += '</div>'
        graphDiv.append(newGraph)
    }
}

function checkTimePicker(picker){
    val = $(picker).val()
    dayDiv = $(picker).closest('.weekDay')
    dayPeriods = $(dayDiv).find('.dayPeriod')
    l = dayPeriods.length
    if (l > 0)
    {
        for (i=0; i<l; i++)
        {
            this_Period = dayPeriods[i]
            this_start = $(this_Period).find('.timePicker').val()
            if (this_start == val)
            {
                prev_Period = dayPeriods[i-1]
                prev_start = $(prev_Period).find('.timePicker').val()

                prev_hours = parseInt(prev_start.split(':')[0])
                prev_minutes = parseInt(prev_start.split(':')[1])
                prev_timeStart = (prev_hours * 60) + prev_minutes

                hours = parseInt(val.split(':')[0])
                minutes = parseInt(val.split(':')[1])
                timeVal = (hours * 60) + minutes

                if (timeVal <= prev_timeStart)
                {
                    newVal = last_start.split(':')[0] + ':' + (parseInt(last_start.split(':')[1]) + 1)
                    $(picker).clockTimePicker('value', newVal)
                }
            }
            else continue
        }
    }
}

function addPeriod(dayDiv, time=null, periodMode=null){
    //check previous time:
    dayPeriods = $(dayDiv).find('.dayPeriod')
    l = dayPeriods.length
    if (l > 0)
    {
        lastPeriod = dayPeriods[l-1]
        last_start = $(lastPeriod).find('.timePicker').val()
        if (time == null)
        {
            time = last_start.split(':')[0] + ':' + (parseInt(last_start.split(':')[1]) + 1)
        }
        else if (periodMode == null)
        {
            last_hours = parseInt(last_start.split(':')[0])
            last_minutes = parseInt(last_start.split(':')[1])
            last_timeStart = (last_hours * 60) + last_minutes

            hours = parseInt(start.split(':')[0])
            minutes = parseInt(start.split(':')[1])
            timeStart = (hours * 60) + minutes

            if (timeStart <= last_timeStart) time = last_start.split(':')[0] + ':' + (parseInt(last_start.split(':')[1]) + 1)
        }
    }

    //write new period:
    if (time == null) time = '00:00'
    div = '<div class="dayPeriod">'
        div += '<div class="input-group" style="width:100% !important; line-height:1.4px !important;">'
            div += '<input class="timePicker form-control input-sm cursor" type="text" value="'+time+'" style="width:60px; min-width:60px;" onchange="checkTimePicker(this)">'
            div += '<select class="expressionAttr form-control input-sm selectPeriodMode select-mode-off" data-l2key="graphColor" onchange="definePeriodMode(this)" style="width:calc(100% - 93px);display:inline-block" title="{{Mode de chauffage}}">'
                l = PROGRAM_MODE_LIST.length
                for (var i = 0; i < l; i++) {
                    div += PROGRAM_MODE_LIST[i]
                }
            div += '</select>'
            div += '<a class="btn btn-default bt_removePeriod btn-sm" title="Supprimer cette période"><i class="fa fa-minus-circle"></i></a>'
        div += '</div>'
    div += '</div>'

    //initialize timePicker:
    newdiv = $(div)
    if (time != '00:00'){
        newdiv.find('.timePicker').clockTimePicker()
        newdiv.find('.clock-timepicker').attr('style','display: inline')
    }
    else newdiv.find('.timePicker').prop('readonly', true)

    /*
    if (time != null && time != '00:00') {
        newdiv.find('.timePicker').clockTimePicker('value', time)
        newdiv.find('.clock-timepicker').attr('style','display: inline')
    }
    */
    if (periodMode)
    {
        select = newdiv.find('.selectPeriodMode')
        select.val(periodMode)
        definePeriodMode(select[0])
    }

    dayDiv.append(newdiv)

    //update graphs:
    updateGraphDay(dayDiv)
}

function clearDay(day){
    day.find('.dayPeriod').each(function  () {
        $(this).remove()
    })
}

function copyDay(day){
    JSONCLIPBOARD = { data : []}
    day.find('.dayPeriod').each(function  () {
        period_start = $(this).find('.timePicker').val()
        temperature_setting = $(this).find('.selectPeriodMode :selected').text()
        JSONCLIPBOARD.data.push({period_start, temperature_setting})
    })
}

function pasteDay(day){
    if (JSONCLIPBOARD == null) return
    clearDay(day)
    JSONCLIPBOARD.data.forEach(function(item) {
        addPeriod(day, item.period_start, item.temperature_setting)
    })
    updateGraphDay(dayDiv)
}

$('#div_programs').off('click','.bt_exportProgram').on('click','.bt_exportProgram',  function () {
    program = $(this).closest('.program')
    exportProgram(program)
})

function exportProgram(_program) {
    _uuid = $('.eqLogicAttr[data-l1key=configuration][data-l2key=uuid]').html()
    _thisProgram = {}
    _thisProgram.name = _program.find('.name').html()
    _thisProgram.origin = _uuid

    if ($('#div_programs').hasClass('isThermostat')) _thisProgram.isThermostat = 1
    else _thisProgram.isThermostat = 0

    days = []
    _program.find('.weekDay').each(function () {
        day = {}
        day.name = $(this).find('.dayName').html()
        //get each period:
        periods = []
        $(this).find('.dayPeriod').each(function () {
            period = {}
            period_start = $(this).find('.timePicker').val()
            temperature_setting = $(this).find('.selectPeriodMode :selected').text()
            periods.push({'period_start':period_start, 'temperature_setting':temperature_setting})
        })
        day.periods = periods
        days.push(day)
    })
    _thisProgram.days = days

    bootbox.prompt({
        title: '<i class="fa fa-download"></i> {{Export program}}',
        inputType: 'text',
        buttons: {
            confirm: {label: '{{Export}}', className: 'btn-success'},
            cancel: {label: '{{Cancel}}', className: 'btn-danger'}
        },
        callback: function (result) {
            if (result !== null && result != '') {
                $.ajax({
                    type: "POST",
                    url: "plugins/qivivo/core/ajax/qivivo.ajax.php",
                    data: {
                        action: "exportProgram",
                        name: result,
                        program: _thisProgram
                    },
                    dataType: 'json',
                    global: false,
                    error: function (request, status, error) {handleAjaxError(request, status, error)},
                    success: function (data) {
                        if (data.state != 'ok') {
                            $('#div_alert').showAlert({
                                message: data.result,
                                level: 'danger'
                            })
                            return
                        }
                        $('#div_alert').showAlert({
                            message: '{{Successfully exported!}}',
                            level: 'success'
                        })
                    }
                })
            } else if (result = '') {
                $('#div_alert').showAlert({
                    message: '{{Please specify a name!}}',
                    level: 'warning'
                })
            }
        }
    })
}

$('#div_programs').off('click','.bt_importProgram').on('click','.bt_importProgram',  function () {
    program = $(this).closest('.program')
    importProgram(program)
})

function importProgram(_program) {
    if ($('#div_programs').hasClass('isThermostat')) isThermostat = 1
    else isThermostat = 0
    _importProgramTo = _program.find('.name').html()
    $('#md_modal').dialog({title: "{{Importation de programme}}"});
    $('#md_modal').load('index.php?v=d&plugin=qivivo&modal=importProgram&isThermostat=' + isThermostat + '&programName=' + _importProgramTo).dialog('open');
}

$('.bt_importProgram').on('click',function(){ //called from modal!
    _filename = $(this).attr("filename")
    programFilePath = "/plugins/qivivo/exportedPrograms/" + _filename
    $.getJSON(programFilePath, function(data)
    {
        jsonDatas = data
        PROGRAM_MODE_LIST = []
        isModuleThermostat = jsonDatas.isThermostat
        if (isModuleThermostat == 1) PROGRAM_MODE_LIST = PROGRAM_OPTIONS_THERMOSTAT
        else PROGRAM_MODE_LIST = PROGRAM_OPTIONS_ZONE
        $('#div_programs .program').each(function () {
            programName = $(this).find('.name').html()
            if (programName != importProgramTo_) return
            divDays = $(this).find(".div_programDays")
            divDays.find('.weekDay').each(function () {
                clearDay($(this))
                dayElName = $(this).find('.dayName').html()
                dayElNameRef = WEEKDAYSREF[WEEKDAYS.indexOf(dayElName)]
                for (j in jsonDatas.days) {
                    day = jsonDatas.days[j]
                    dayNamefr = day.name
                    dayName = WEEKDAYS[WEEKDAYSREF.indexOf(dayElName)]
                    if (dayNamefr == dayElNameRef){
                        periods = day.periods
                        for (k in periods) {
                            period = periods[k]
                            addPeriod($(this), period.period_start, period.temperature_setting)
                        }
                    }
                }
            })
        })
    });
    $('#md_modal').dialog("close")
})

$('.bt_deleteProgram').on('click',function(){ //called from modal!
    _mayDeleteProgramDiv = $(this).closest(".mayImportProgram")
    _filename = $(this).closest(".bt_deleteProgram").attr("filename")
    bootbox.confirm({
        message: "Voulez vous vraiment supprimer ce fichier de programme ?",
        buttons: {
            confirm: {label: 'Yes', className: 'btn-success'},
            cancel: {label: 'No', className: 'btn-danger'}
        },
        callback: function (result) {
            if (result === true) {
                $.ajax({
                    type: "POST",
                    url: "plugins/qivivo/core/ajax/qivivo.ajax.php",
                    data: { action: "deleteProgramFile", fileName: _filename},
                    dataType: 'json',
                    error: function (request, status, error) {
                        handleAjaxError(request, status, error)
                    },
                    success: function (data) {
                        if (data.state == 'ok') {
                            _mayDeleteProgramDiv.remove()
                        }
                    }
                })
            }
        }
    })
})

$('.bt_downloadProgram').on('click',function(){ //called from modal!
    _filename = $(this).attr("filename")
    programFilePath = "/plugins/qivivo/exportedPrograms/" + _filename
    $.getJSON(programFilePath, function(data)
    {
        dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(data))
        downloadAnchorNode = document.createElement('a')
        downloadAnchorNode.setAttribute("href",     dataStr)
        downloadAnchorNode.setAttribute("target", "_blank")
        downloadAnchorNode.setAttribute("download", _filename)
        document.body.appendChild(downloadAnchorNode)
        downloadAnchorNode.click()
        downloadAnchorNode.remove()
    })
})

//Standard
$("#div_programs").sortable({axis: "y", cursor: "move", items: ".program", handle: ".panel-heading", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true})

function printEqLogic(_eqLogic) {
    $('#div_programs').empty()
    if (_eqLogic.configuration.type != 'Module Chauffage') return

    //possible modes if thermostat zone module:
    PROGRAM_MODE_LIST = []
    $('#div_programs').removeClass('isThermostat')
    $('#div_programs').removeClass('isNotThermostat')
    isModuleThermostat = _eqLogic.configuration.isModuleThermostat
    if (isModuleThermostat == 1) {
        $('#div_programs').addClass('isThermostat')
        PROGRAM_MODE_LIST = PROGRAM_OPTIONS_THERMOSTAT
    }
    else {
        $('#div_programs').addClass('isNotThermostat')
        PROGRAM_MODE_LIST = PROGRAM_OPTIONS_ZONE
    }

    if (isset(_eqLogic.configuration) && isset(_eqLogic.configuration.programs)) {
        for (i in _eqLogic.configuration.programs) {
            thisProgram = _eqLogic.configuration.programs[i]
            addProgram({name: thisProgram.name, isNew: false})
            $('#div_programs .program:last .weekDay').each(function () {
                dayElName = $(this).find('.dayName').html()
                dayElNameRef = WEEKDAYSREF[WEEKDAYS.indexOf(dayElName)]
                for (j in thisProgram.days) {
                    day = thisProgram.days[j]
                    dayName = day.name
                    if (dayName == dayElNameRef){
                        periods = day.periods
                        for (k in periods) {
                            period = periods[k]
                            addPeriod($(this), period.period_start, period.temperature_setting)
                        }
                    }
                }
            })
        }
    }
}

function saveEqLogic(_eqLogic) {
    if (!isset(_eqLogic.configuration)) {
        _eqLogic.configuration = {}
    }

    _eqLogic.configuration.programs = []
    //get each program:
    $('#div_programs .program').each(function () {
        _thisProgram = {}
        _thisProgram.name = $(this).find('.name').html()
        //get each day:
        days = []
        $(this).find('.weekDay').each(function () {
            day = {}
            dayName = $(this).find('.dayName').html()
            idx = WEEKDAYS.indexOf(dayName)
            day.name = WEEKDAYSREF[idx]
            //get each period:
            periods = []
            $(this).find('.dayPeriod').each(function () {
                period = {}
                period_start = $(this).find('.timePicker').val()
                temperature_setting = $(this).find('.selectPeriodMode :selected').text()
                periods.push({'period_start':period_start, 'temperature_setting':temperature_setting})
            })
            day.periods = periods
            days.push(day)
        })
        _thisProgram.days = days
        _eqLogic.configuration.programs.push(_thisProgram)
    })
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
