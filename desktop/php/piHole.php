<?php
if (!isConnect('admin')) {
  throw new Exception('{{401 - Accès non autorisé}}');
}

sendVarToJS('eqType', 'piHole');
$eqLogics = eqLogic::byType('piHole');
?>
<div class="row row-overflow">
  <div class="col-xs-12 eqLogicThumbnailDisplay">
    <legend><i class="fas fa-cog"></i>  {{Gestion}}</legend>
    <div class="eqLogicThumbnailContainer">
      <div class="cursor eqLogicAction logoPrimary" data-action="add">
        <i class="fas fa-plus-circle"></i>
        <br>
        <span>{{Ajouter}}</span>
      </div>
      <div class="cursor eqLogicAction logoSecondary" data-action="gotoPluginConf">
        <i class="fas fa-wrench"></i>
        <br>
        <span>{{Configuration}}</span>
      </div>
      <div class="cursor logoSecondary" id="bt_healthpiHole">
        <i class="fas fa-medkit"></i>
        <br>
        <span>{{Santé}}</span>
      </div>
    </div>
    <legend><i class="fas fa-table"></i>  {{Mes serveurs piHole}}</legend>
    <div class="eqLogicThumbnailContainer">
      <?php
        foreach ($eqLogics as $eqLogic) {
          $opacity = ($eqLogic->getIsEnable()) ? '' : 'disableCard';
          echo '<div class="eqLogicDisplayCard cursor '.$opacity.'" data-eqLogic_id="' . $eqLogic->getId() . '">';
          echo '<img src="plugins/piHole/plugin_info/piHole_icon.png" />';
          echo '<br>';
          echo '<span class="name">' . $eqLogic->getHumanName(true, true) . '</span>';
          echo '</div>';
        }
      ?>
    </div>
  </div>
  <div class="col-xs-12 eqLogic" style="display: none;">
    <div class="input-group pull-right" style="display:inline-flex">
      <span class="input-group-btn">
        <a class="btn btn-sm btn-default eqLogicAction roundedLeft" data-action="configure"><i class="fas fa-cogs"></i> {{Configuration avancée}}
        </a><a class="btn btn-sm btn-success eqLogicAction" data-action="save"><i class="fas fa-check-circle"></i> {{Sauvegarder}}
        </a><a class="btn btn-sm btn-danger eqLogicAction roundedRight" data-action="remove"><i class="fas fa-minus-circle"></i> {{Supprimer}}</a>
      </span>
    </div>

  <ul class="nav nav-tabs" role="tablist">
    <li role="presentation"><a class="eqLogicAction cursor" aria-controls="home" role="tab" data-action="returnToThumbnailDisplay"><i class="fas fa-arrow-circle-left"></i></a></li>
    <li role="presentation" class="active"><a href="#eqlogictab" aria-controls="home" role="tab" data-toggle="tab"><i class="fas fa-tachometer-alt"></i> {{Equipement}}</a></li>
    <li role="presentation"><a href="#commandtab" aria-controls="profile" role="tab" data-toggle="tab"><i class="fas fa-list-alt"></i> {{Commandes}}</a></li>
  </ul>

  <div class="tab-content" style="height:calc(100% - 50px);overflow:auto;overflow-x: hidden;">
    <div id="eqlogictab" role="tabpanel" class="tab-pane active">
      <br>
      <div class="row">
        <div class="col-sm-9">
          <form class="form-horizontal">
            <fieldset>
              <div class="form-group">
                <label class="col-lg-3 control-label">{{Nom de l'équipement}}</label>
                <div class="col-lg-4">
                  <input type="text" class="eqLogicAttr form-control" data-l1key="id" style="display : none;" />
                  <input type="text" class="eqLogicAttr form-control" data-l1key="name" placeholder="{{Nom de l'équipement}}"/>
                </div>
              </div>
              <div class="form-group">
                <label class="col-lg-3 control-label" >{{Objet parent}}</label>
                <div class="col-lg-4">
                  <select id="sel_object" class="eqLogicAttr form-control" data-l1key="object_id">
                    <option value="">{{Aucun}}</option>
                      <?php
                      foreach ((jeeObject::buildTree(null, false)) as $object) {
											  echo '<option value="' . $object->getId() . '">' . str_repeat('&nbsp;&nbsp;', $object->getConfiguration('parentNumber')) . $object->getName() . '</option>';
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
              <div class="form-group">
                <label class="col-sm-3 control-label">{{Ip du serveur}}</label>
                <div class="col-sm-4">
				  <div class="input-group">
				  <select class="eqLogicAttr form-control roundedLeft" data-l1key="configuration" data-l2key="proto">
					<option value="http" selected>HTTP</option>
					<option value="https">HTTPS</option>
				  </select>
				  <span class="input-group-addon">://</span>
                  <input type="text" class="eqLogicAttr form-control roundedRight" data-l1key="configuration" data-l2key="ip" placeholder="{{Ip du serveur piHole}}"/>
				  </div>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-3 control-label">{{Apikey}}</label>
                <div class="col-sm-6">
                  <input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="apikey" placeholder="{{Apikey de votre serveur}}"/>
                </div>
              </div>
              <div class="form-group expertModeVisible">
                <label class="col-sm-3 control-label">{{Auto-actualisation (cron)}}</label>
                  <div class="col-sm-4">
                    <input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="autorefresh" placeholder="*/5 * * * *"/>
                  </div>
                  <div class="col-sm-1">
                    <i class="fas fa-question-circle cursor floatright" id="bt_cronGenerator"></i>
                  </div>
              </div>
            </fieldset>
          </form>
        </div>

        <form class="form-horizontal col-sm-3">
          <fieldset>
            <div class="form-group">
              <img src="plugins/piHole/plugin_info/piHole_icon.png" style="height: 200px;" />
            </div>
          </fieldset>
        </form>
      </div>
    </div>
    <div role="tabpanel" class="tab-pane" id="commandtab">
      <legend><i class="fas fa-list-alt"></i>  {{Tableau de commandes}}</legend>
      <table id="table_cmd" class="table table-bordered table-condensed">
        <thead>
          <tr>
            <th>{{Nom}}</th><th>{{Valeur}}</th><th>{{Action}}</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
  </div>
</div>

<?php
  include_file('desktop', 'piHole', 'js', 'piHole');
  include_file('core', 'plugin.template', 'js');
?>
