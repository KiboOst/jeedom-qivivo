<?php
if (!isConnect('admin')) {
  throw new Exception('{{401 - Accès non autorisé}}');
}

$plugin = plugin::byId('qivivo');
sendVarToJS('eqType', $plugin->getId());
$eqLogics = eqLogic::byType($plugin->getId());
?>

<style type="text/css">
  .select-mode-off {
    background-color: rgb(157,249,254) !important;
    color: rgb(60,60,60) !important;
  }
  .select-mode-frost {
    background-color: rgb(141,237,253) !important;
    color: rgb(60,60,60) !important;
  }
  .select-mode-abs {
    background-color: rgb(126,218,253) !important;
    color: rgb(60,60,60) !important;
  }
  .select-mode-eco, .select-mode-nuit {
    background-color: rgb(145,195,252) !important;
    color: rgb(60,60,60) !important;
  }
  .select-mode-pres1, .select-mode-confort-2 {
    background-color: rgb(144,175,251) !important;
    color: rgb(60,60,60) !important;
  }
  .select-mode-pres2, .select-mode-confort-1 {
    background-color: rgb(253,233,114) !important;
    color: rgb(60,60,60) !important;
  }
  .select-mode-pres3, .select-mode-confort {
    background-color: rgb(253,212,93) !important;
    color: rgb(60,60,60) !important;
  }
  .select-mode-pres4 {
    background-color: rgb(252,198,79) !important;
    color: rgb(60,60,60) !important;
  }
</style>

<div class="row row-overflow">
  <div class="col-xs-12 eqLogicThumbnailDisplay" style="border-left: solid 1px #EEE; padding-left: 25px;">
    <legend><i class="fa fa-cog"></i>  {{Gestion}}</legend>
    <div class="eqLogicThumbnailContainer">
      <div class="cursor eqLogicAction" data-action="gotoPluginConf">
        <center>
          <i class="fas fa-wrench"></i>
        </center>
      <span><center>{{Configuration}}</center></span>
      </div>
    </div>

    <legend><i class="fa fa-table"></i> {{Mes Modules}}</legend>
      <div class="eqLogicThumbnailContainer">
          <?php
            foreach ($eqLogics as $eqLogic) {
              $opacity = ($eqLogic->getIsEnable()) ? '' : 'disableCard';
              echo '<div class="eqLogicDisplayCard cursor '.$opacity.'" data-eqLogic_id="' . $eqLogic->getId() . '">';

              $imgPath = $plugin->getPathImgIcon();
              if ($eqLogic->getConfiguration('type', '') == 'Thermostat') $imgPath = 'plugins/qivivo/core/img/thermostat.png';
              if ($eqLogic->getConfiguration('type', '') == 'Module Chauffage') $imgPath = 'plugins/qivivo/core/img/module.png';
              if ($eqLogic->getConfiguration('type', '') == 'Passerelle') $imgPath = 'plugins/qivivo/core/img/gateway.png';
              echo '<img src="' . $imgPath . '"/>';

              echo '<br>';
              echo '<span class="name">' . $eqLogic->getHumanName(true, true) . '</span>';
              echo '</div>';
            }
          ?>
      </div>
  </div>

<!--Equipement page-->
<div class="col-xs-12 eqLogic" style="border-left: solid 1px #EEE; padding-left: 25px;display: none;">
  <div class="input-group pull-right" style="display:inline-flex">
    <span class="input-group-btn">
      <a class="btn btn-sm btn-default eqLogicAction roundedLeft" data-action="configure"><i class="fa fa-cogs"></i> {{Configuration avancée}}
      </a><a class="btn btn-sm btn-success eqLogicAction" data-action="save"><i class="fa fa-check-circle"></i> {{Sauvegarder}}
      </a><a class="btn btn-sm btn-danger eqLogicAction roundedRight" data-action="remove"><i class="fa fa-minus-circle"></i> {{Supprimer}}</a>
    </span>
  </div>

  <ul class="nav nav-tabs" role="tablist">
    <li role="presentation"><a href="#" class="eqLogicAction" aria-controls="home" role="tab" data-toggle="tab" data-action="returnToThumbnailDisplay"><i class="fa fa-arrow-circle-left"></i></a></li>
    <li role="presentation" class="active"><a href="#eqlogictab" aria-controls="home" role="tab" data-toggle="tab"><i class="fa fa-tachometer"></i> {{Equipement}}</a></li>
    <li id="bt_tab_programs" role="presentation"  style="display: none;"><a href="#tab_programs" aria-controls="profile" role="tab" data-toggle="tab"><i class="fa fa-list-alt"></i> {{Programmes}}</a></li>
    <li role="presentation"><a href="#tab_cmds" aria-controls="profile" role="tab" data-toggle="tab"><i class="fa fa-list-alt"></i> {{Commandes}}</a></li>
  </ul>
  <div class="tab-content" style="height:calc(100% - 50px);overflow:auto;overflow-x: hidden;">


  <div role="tabpanel" class="tab-pane active" id="eqlogictab">
    <br/>
    <form class="form-horizontal col-sm-9">
      <fieldset>
          <div class="form-group">
              <label class="col-sm-3 control-label">{{Nom de l'équipement Qivivo}}</label>
              <div class="col-sm-3">
                  <input type="text" class="eqLogicAttr form-control" data-l1key="id" style="display : none;" />
                  <input type="text" class="eqLogicAttr form-control" data-l1key="name" placeholder="{{Nom de l\'équipement Qivivo}}"/>
              </div>
          </div>
          <div class="form-group">
              <label class="col-sm-3 control-label" >{{Objet parent}}</label>
              <div class="col-sm-3">
                  <select id="sel_object" class="eqLogicAttr form-control" data-l1key="object_id">
                      <option value="">{{Aucun}}</option>
                      <?php
                        foreach (object::all() as $object) {
                         echo '<option value="' . $object->getId() . '">' . $object->getName() . '</option>';
                        }
                      ?>
                 </select>
             </div>
         </div>
         <div class="form-group">
              <label class="col-sm-3 control-label">{{Catégorie}}</label>
              <div class="col-sm-9">
               <?php
                  foreach (jeedom::getConfiguration('eqLogic:category') as $key => $value) {
                  echo '<label class="checkbox-inline">';
                  echo '<input type="checkbox" class="eqLogicAttr" data-l1key="category" data-l2key="' . $key . '" />' . $value['name'];
                  echo '</label>';
                  }
                ?>
             </div>
         </div>

        <div class="form-group">
          <label class="col-sm-3 control-label"></label>
          <div class="col-sm-9">
            <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isEnable" checked/>{{Activer}}</label>
            <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isVisible" checked/>{{Visible}}</label>
          </div>
        </div>

        </fieldset>
    </form>

    <form class="form-horizontal col-sm-3">
      <fieldset>
        <div class="form-group">
          <img src="' . $plugin->getPathImgIcon() . '" id="img_qivivoModel" style="width:200px;" />
        </div>
      </fieldset>
    </form>

    <hr>

    <form class="form-horizontal col-sm-12">
      <fieldset>
        <div class="form-group">
          <label class="col-sm-3 control-label">{{Type}}</label>
          <div class="col-sm-5">
           <span class="eqLogicAttr label label-info" data-l1key="configuration" data-l2key="type"></span>
          </div>
        </div>

        <div class="form-group" style="display: none;" data-cmd_id="moduleZone">
          <label class="col-sm-3 control-label">{{Zone}}</label>
          <div class="col-sm-5">
           <span class="eqLogicAttr label label-info" data-l1key="configuration" data-l2key="zone_name"></span>
          </div>
        </div>

        <!--common infos but Passerelle-->
        <div class="form-group" style="display: none;" data-cmd_id="module_order">
          <label class="col-sm-3 control-label">{{Ordre}}</label>
          <div class="col-sm-5">
           <span class="eqLogicAttr label label-info" data-cmd_id="module_order"></span>
          </div>
        </div>
        <div class="form-group" style="display: none;" data-cmd_id="last_communication">
          <label class="col-sm-3 control-label">{{Dernière communication}}</label>
          <div class="col-sm-5">
           <span class="eqLogicAttr label label-info" data-cmd_id="last_communication"></span>
          </div>
        </div>

        <!--thermostat infos-->
        <div class="form-group" style="display: none;" data-cmd_id="temperature_order">
          <label class="col-sm-3 control-label">{{Consigne}}</label>
          <div class="col-sm-5">
           <span class="eqLogicAttr label label-info" data-cmd_id="temperature_order"></span>
          </div>
        </div>
        <div class="form-group" style="display: none;" data-cmd_id="dureeordre">
          <label class="col-sm-3 control-label">{{Durée Ordre}}</label>
          <div class="col-sm-5">
           <span class="eqLogicAttr label label-info" data-cmd_id="dureeordre"></span>
          </div>
        </div>

        <div class="form-group" style="display: none;" data-cmd_id="paramTempAbsence">
          <label class="col-sm-3 control-label">{{Paramètre Température Absence}}</label>
          <div class="col-sm-5">
           <span class="eqLogicAttr label label-info" data-cmd_id="paramTempAbsence"></span>
          </div>
        </div>
        <div class="form-group" style="display: none;" data-cmd_id="paramTempHG">
          <label class="col-sm-3 control-label">{{Paramètre Température Hors-gel}}</label>
          <div class="col-sm-5">
           <span class="eqLogicAttr label label-info" data-cmd_id="paramTempHG"></span>
          </div>
        </div>
        <div class="form-group" style="display: none;" data-cmd_id="paramTempNuit">
          <label class="col-sm-3 control-label">{{Paramètre Température Nuit}}</label>
          <div class="col-sm-5">
           <span class="eqLogicAttr label label-info" data-cmd_id="paramTempNuit"></span>
          </div>
        </div>
        <div class="form-group" style="display: none;" data-cmd_id="paramTempPres1">
          <label class="col-sm-3 control-label">{{Paramètre Température Présence 1}}</label>
          <div class="col-sm-5">
           <span class="eqLogicAttr label label-info" data-cmd_id="paramTempPres1"></span>
          </div>
        </div>
        <div class="form-group" style="display: none;" data-cmd_id="paramTempPres2">
          <label class="col-sm-3 control-label">{{Paramètre Température Présence 2}}</label>
          <div class="col-sm-5">
           <span class="eqLogicAttr label label-info" data-cmd_id="paramTempPres2"></span>
          </div>
        </div>
        <div class="form-group" style="display: none;" data-cmd_id="paramTempPres3">
          <label class="col-sm-3 control-label">{{Paramètre Température Présence 3}}</label>
          <div class="col-sm-5">
           <span class="eqLogicAttr label label-info" data-cmd_id="paramTempPres3"></span>
          </div>
        </div>
        <div class="form-group" style="display: none;" data-cmd_id="paramTempPres4">
          <label class="col-sm-3 control-label">{{Paramètre Température Présence 4}}</label>
          <div class="col-sm-5">
           <span class="eqLogicAttr label label-info" data-cmd_id="paramTempPres4"></span>
          </div>
        </div>
        <div class="form-group" style="display: none;" data-cmd_id="battery">
          <label class="col-sm-3 control-label">{{Batterie}}</label>
          <div class="col-sm-5">
           <span class="eqLogicAttr label label-info" data-cmd_id="battery"></span>
          </div>
        </div>


        <!--common infos but Passerelle-->
        <div class="form-group" style="display: none;" data-cmd_id="firmware_version">
          <label class="col-sm-3 control-label">{{Firmware}}</label>
          <div class="col-sm-5">
           <span class="eqLogicAttr label label-info" data-cmd_id="firmware_version"></span>
          </div>
        </div>
        <!--common info-->
        <div class="form-group">
          <label class="col-sm-3 control-label">{{uuid}}</label>
          <div class="col-sm-5">
           <span class="eqLogicAttr label label-info" data-l1key="configuration" data-l2key="uuid"></span>
          </div>
        </div>
      </fieldset>
    </form>
    </div>

    <!--Programs Tab-->
    <div role="tabpanel" class="tab-pane" id="tab_programs">
      <a class="btn btn-success pull-right" id="bt_addProgram" style="margin-top: 5px;"><i class="fa fa-plus-circle"></i> {{Ajouter Programme}}</a><br/><br/>
      <div id="div_programs"></div>
    </div>

    <!--Commands Tab-->
    <div role="tabpanel" class="tab-pane" id="tab_cmds">
      <div id="div_cmds"></div>
      <legend><i class="fa fa-list-alt"></i>  {{Commandes Infos}}</legend>
      <table id="table_infos" class="table table-bordered table-condensed">
        <thead>
          <tr>
            <th width="65%">{{Nom}}</th><th width="25%" align="center">{{Options}}</th><th width="10%" align="right">{{Action}}</th>
          </tr>
        </thead>
      <tbody>
      </tbody>
      </table>

      <legend><i class="fa fa-list-alt"></i>  {{Commandes Actions}}</legend>
      <table id="table_actions" class="table table-bordered table-condensed">
        <thead>
          <tr>
            <th width="65%">{{Nom}}</th><th width="25%" align="center">{{Options}}</th><th width="10%" align="right">{{Action}}</th>
          </tr>
        </thead>
      <tbody>
      </tbody>
      </table>
    </div>

  </div>
</div>

<?php include_file('3rdparty', 'jquery-clock-timepicker.min', 'js', 'qivivo');?>
<?php include_file('desktop', 'qivivo', 'js', 'qivivo');?>
<?php include_file('core', 'plugin.template', 'js');?>
