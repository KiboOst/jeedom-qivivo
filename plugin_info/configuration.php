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

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';
include_file('core', 'authentification', 'php');
if (!isConnect()) {
	include_file('desktop', '404', 'php');
	die();
}
?>

<form class="form-horizontal">
<fieldset>
    <div class="form-group">
        <label class="col-sm-2 control-label">{{Login}}</label>
        <div class="col-sm-3">
            <input type="text" class="configKey form-control" data-l1key="login" placeholder="Account login"/>
        </div>
    </div>
    <div class="form-group">
        <label class="col-sm-2 control-label">{{Password}}</label>
        <div class="col-sm-3">
            <input type="text" class="configKey form-control" data-l1key="pass" placeholder="Account password"/>
        </div>
    </div>
    <div class="form-group">
        <label class="col-sm-2 control-label">{{Client ID}}</label>
        <div class="col-sm-3">
            <input type="text" class="configKey form-control" data-l1key="client_id" placeholder="Client ID"/>
        </div>
    </div>
    <div class="form-group">
        <label class="col-sm-2 control-label">{{Client secret}}</label>
        <div class="col-sm-3">
            <input type="text" class="configKey form-control" data-l1key="client_secret" placeholder="Client Secret"/>
        </div>
    </div>
    <div class="form-group">
        <label class="col-lg-2 control-label">{{Synchroniser}}</label>
        <div class="col-lg-2">
        <a class="btn btn-warning" id="bt_syncWithQivivo"><i class='fa fa-refresh'></i> {{Synchroniser mes équipements}}</a>
        </div>
    </div>

    <hr>

    <div class="form-group">
        <label class="col-lg-3 control-label">Répéter l'action sur échec (90sec plus tard)</label>
        <div class="col-sm-1">
            <input type="checkbox" class="configKey" data-l1key="repeatOnActionError" checked="">
        </div>
    </div>

    <div class="form-group">
        <label class="col-lg-3 control-label">Actions sur erreur (#message#) :</label>
        <div class="col-lg-4">
            <a class="btn btn-success" id="bt_addActionOnError"><i class="fa fa-plus-circle"></i> Ajouter</a>
        </div>
    </div>

    <div id="div_actionsOnError">

    </div>

</fieldset>
</form>

<script>
    $('#bt_syncWithQivivo').on('click', function () {
        $.ajax({
            type: "POST",
            url: "plugins/qivivo/core/ajax/qivivo.ajax.php",
            data: {
                action: "syncWithQivivo",
            },
            dataType: 'json',
            error: function (request, status, error) {
                handleAjaxError(request, status, error);
            },
            success: function (data) {
                if (data.state != 'ok') {
                    $('#div_alert').showAlert({message: data.result, level: 'danger'});
                    return;
                }
                $('#div_alert').showAlert({message: '{{Synchronisation réussie}}', level: 'success'});
            }
        })
    })

    $("input[data-l1key='functionality::cron5::enable']").on('change',function(){
        if ($(this).is(':checked')) $("input[data-l1key='functionality::cron15::enable']").prop("checked", false)
    });

    $("input[data-l1key='functionality::cron15::enable']").on('change',function(){
        if ($(this).is(':checked')) $("input[data-l1key='functionality::cron5::enable']").prop("checked", false)
    });



    //Actions sur erreur:
    $("#bt_savePluginConfig").on('click',function(){
        actionsOnError = json_encode($('#div_actionsOnError .actionOnError').getValues('.expressionAttr'))
        saveActionsOnError(actionsOnError)
    });

    function saveActionsOnError(_actions){
        $.ajax({
            type: "POST",
            url: "plugins/qivivo/core/ajax/qivivo.ajax.php",
            data: { action: "saveActionsOnError", actionsOnError: _actions},
            dataType: 'json',
            error: function (request, status, error) {
                handleAjaxError(request, status, error);
            },
            success: function (data) {
                return
            }
        })
    }


    $("#bt_addActionOnError").on('click',function(){
        addActionOnError()
    });

    $("body").delegate('.bt_removeAction', 'click', function () {
        $(this).closest('.actionOnError').remove();
    });

    function addActionOnError(_action) {
        if (!isset(_action)) {
            _action = {};
        }
        if (!isset(_action.options)) {
            _action.options = {};
        }
        var div = '<div class="actionOnError">'
        div += '<div class="form-group ">';
        div += '<label class="col-sm-1 control-label">Action</label>'
        div += '<div class="col-sm-2">'
        div += '<input type="checkbox" class="expressionAttr" data-l1key="options" data-l2key="enable" checked title="Décocher pour desactiver l\'action" />'
        div += '<input type="checkbox" class="expressionAttr" data-l1key="options" data-l2key="background" title="Cocher pour que la commande s\'éxecute en parrallele des autres actions" />'
        div += '</div>'
        div += '<div class="col-sm-4">'
        div += '<div class="input-group">'
        div += '<span class="input-group-btn">'
        div += '<a class="btn btn-default bt_removeAction btn-sm"><i class="fa fa-minus-circle"></i></a>'
        div += '</span>'
        div += '<input class="expressionAttr form-control input-sm cmdAction" data-l1key="cmd" />'
        div += '<span class="input-group-btn">';
        div += '<a class="btn btn-default btn-sm listAction" title="Sélectionner un mot-clé"><i class="fa fa-tasks"></i></a>'
        div += '<a class="btn btn-default btn-sm listCmdAction"><i class="fa fa-list-alt"></i></a>'
        div += '</span>'
        div += '</div>'
        div += '</div>'
        var actionOption_id = uniqId()
        div += '<div class="col-sm-5 actionOptions" id="'+actionOption_id+'">'
        div += '</div>'
        div += '</div>'
        $('#div_actionsOnError').append(div)
        $('#div_actionsOnError .actionOnError:last').setValues(_action, '.expressionAttr')
        actionOptions.push({
            expression : init(_action.cmd, ''),
            options : _action.options,
            id : actionOption_id
        })
    }

    $('body').delegate('.cmdAction.expressionAttr[data-l1key=cmd]', 'focusout', function (event) {
        var expression = $(this).closest('.actionOnError').getValues('.expressionAttr');
        var el = $(this);
        jeedom.cmd.displayActionOption($(this).value(), init(expression[0].options), function (html) {
            el.closest('.actionOnError').find('.actionOptions').html(html);
            taAutosize();
        })
    });

    $("body").delegate(".listCmdAction", 'click', function () {
        var el = $(this).closest('.actionOnError').find('.expressionAttr[data-l1key=cmd]');
        jeedom.cmd.getSelectModal({cmd: {type: 'action'}}, function (result) {
            el.value(result.human);
            jeedom.cmd.displayActionOption(el.value(), '', function (html) {
                el.closest('.actionOnError').find('.actionOptions').html(html);
                taAutosize();
            });
        });
    });

    $("body").delegate(".listAction", 'click', function () {
        var el = $(this).closest('.actionOnError').find('.expressionAttr[data-l1key=cmd]');
        jeedom.getSelectActionModal({}, function (result) {
            el.value(result.human);
            jeedom.cmd.displayActionOption(el.value(), '', function (html) {
                el.closest('.actionOnError').find('.actionOptions').html(html);
                taAutosize();
            });
        });
    });


    actionOptions = []
    loadActionsOnError()

    function loadActionsOnError(){
        $('#div_actionsOnError').empty()

        $.ajax({
            type: "POST",
            url: "plugins/qivivo/core/ajax/qivivo.ajax.php",
            data: {action: "getActionsOnError"},
            dataType: 'json',
            error: function (request, status, error) {
                handleAjaxError(request, status, error);
            },
            success: function (data) {
                if(data.result == '') return
                actionOptions = []
                for (var i in data.result) {
                    addActionOnError(data.result[i])
                }
                jeedom.cmd.displayActionsOption({
                    params : actionOptions,
                    async : false,
                    error: function (error) {
                      $('#div_alert').showAlert({message: error.message, level: 'danger'})
                    },
                    success : function(data){
                        for(var i in data){
                            $('#'+data[i].id).append(data[i].html.html)
                        }
                        taAutosize()
                    }
                })
            }
        })
    }

</script>
