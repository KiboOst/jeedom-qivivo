<?php
if (!isConnect('admin')) {
  throw new Exception('{{401 - Accès non autorisé}}');
}

$plugin = plugin::byId('qivivo');
sendVarToJS('eqType', $plugin->getId());
$eqLogics = eqLogic::byType($plugin->getId());
?>

<div class="row row-overflow">
  <div class="col-xs-12 eqLogicThumbnailDisplay">
    <legend><i class="fa fa-cog"></i>  {{Gestion}}</legend>
    <div class="eqLogicThumbnailContainer">
      <div class="cursor eqLogicAction" data-action="gotoPluginConf">
          <i class="fas fa-wrench"></i>
      <span>{{Configuration}}</span>
      </div>
    </div>

    <legend><i class="fa fa-table"></i> {{Mes Modules}}</legend>
    <div class="input-group" style="margin-bottom:5px;">
      <input class="form-control roundedLeft" placeholder="{{Rechercher}}" id="in_searchEqlogic"/>
      <div class="input-group-btn">
          <a id="bt_resetObjectSearch" class="btn" style="width:30px"><i class="fas fa-times"></i>
          </a><a class="btn roundedRight hidden" id="bt_pluginDisplayAsTable" data-coreSupport="1" data-state="0"><i class="fas fa-grip-lines"></i></a>
      </div>
    </div>

    <div class="eqLogicThumbnailContainer">
        <?php
          foreach ($eqLogics as $eqLogic) {
            $div = '';
            $opacity = ($eqLogic->getIsEnable()) ? '' : 'disableCard';
            $div .= '<div class="eqLogicDisplayCard cursor '.$opacity.'" data-eqLogic_id="' . $eqLogic->getId() . '">';

            $imgPath = $plugin->getPathImgIcon();
            if ($eqLogic->getConfiguration('type', '') == 'Thermostat') $imgPath = 'plugins/qivivo/core/img/thermostat.png';
            if ($eqLogic->getConfiguration('type', '') == 'Module Chauffage') $imgPath = 'plugins/qivivo/core/img/module.png';
            if ($eqLogic->getConfiguration('type', '') == 'Passerelle') $imgPath = 'plugins/qivivo/core/img/gateway.png';
            $div .= '<img src="' . $imgPath . '"/>';

            $div .= '<br>';
            $div .= '<span class="name">' . $eqLogic->getHumanName(true, true) . '</span>';

            $div .= '<span class="hidden hiddenAsCard displayTableRight"><span>'.$eqLogic->getConfiguration('zone_name') . '</span>';
            $cats = $eqLogic->getCategory();
            unset($cats['default']);
            $div .= '<span> ' . implode(array_keys($cats, 1), ', ') . '</span>';
            if ($eqLogic->getIsVisible() == 1) {
              $div .= ' <i class="fas fa-eye"></i>';
            } else {
              $div .= ' <i class="fas fa-eye-slash"></i>';
            }
            $div .= '</span>';

            $div .= '</div>';
            echo $div;
          }
        ?>
    </div>
  </div>

<!--Equipement page-->
<div class="col-xs-12 eqLogic" style="display: none;">
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
                        $options = '';
                        foreach ((jeeObject::buildTree(null, false)) as $object) {
                          $options .= '<option value="' . $object->getId() . '">' . str_repeat('&nbsp;&nbsp;', $object->getConfiguration('parentNumber')) . $object->getName() . '</option>';
                        }
                      echo $options;
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
          <img id="img_qivivoModel" style="width:200px;" />
        </div>
      </fieldset>
    </form>

    <hr>

    <form class="form-horizontal col-sm-12">
      <fieldset>
        <div class="form-group">
          <label class="col-sm-3 control-label">{{Type}}</label>
          <div class="col-sm-5">
           <span class="eqLogicAttr label label-info" data-l1key="configuration" data-l2key="type" style="display:none;"></span>
           <span id="spanEqType" class="label label-info"></span>
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
          <label class="col-sm-3 control-label">{{serial}}</label>
          <div class="col-sm-5">
           <span class="eqLogicAttr label label-info" data-l1key="configuration" data-l2key="serial"></span>
          </div>
        </div>
      </fieldset>
    </form>
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

<?php
  include_file('desktop', 'qivivo', 'js', 'qivivo');
  include_file('desktop', 'qivivo', 'css', 'qivivo');
  include_file('core', 'plugin.template', 'js');
?>
