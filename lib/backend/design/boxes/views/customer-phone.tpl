{use class="Yii"}
<form action="{$app->request->baseUrl}/design/box-save" method="post" id="box-save">
  <input type="hidden" name="id" value="{$id}"/>
  <div class="popup-heading">
    {$smarty.const.ENTRY_TELEPHONE_NUMBER}
  </div>
  <div class="popup-content box-img">


    <div class="tabbable tabbable-custom">
      <ul class="nav nav-tabs">

        <li class="active"><a href="#upload" data-toggle="tab">{$smarty.const.TEXT_CONTAINER}</a></li>
        <li><a href="#style" data-toggle="tab">{$smarty.const.HEADING_STYLE}</a></li>
        <li><a href="#align" data-toggle="tab">{$smarty.const.HEADING_WIDGET_ALIGN}</a></li>
        <li><a href="#visibility" data-toggle="tab">{$smarty.const.TEXT_VISIBILITY_ON_PAGES}</a></li>

      </ul>
      <div class="tab-content">


        <div class="tab-pane active" id="upload">


          <div class="setting-row">
            <label for="">{$smarty.const.SHOW_HEADING}</label>
            <select name="setting[0][show_heading]" id="" class="form-control">
              <option value=""{if $settings[0].show_heading == ''} selected{/if}>{$smarty.const.TEXT_BTN_NO}</option>
              <option value="1"{if $settings[0].show_heading == '1'} selected{/if}>{$smarty.const.TEXT_BTN_YES}</option>
            </select>
          </div>

        </div>
        <div class="tab-pane" id="style">
          {include 'include/style.tpl'}
        </div>
        <div class="tab-pane" id="align">
          {include 'include/align.tpl'}
        </div>
        <div class="tab-pane" id="visibility">
          {include 'include/visibility.tpl'}
        </div>

      </div>
    </div>



  </div>
  <div class="popup-buttons">
    <button type="submit" class="btn btn-primary btn-save">{$smarty.const.IMAGE_SAVE}</button>
    <span class="btn btn-cancel">{$smarty.const.IMAGE_CANCEL}</span>
  </div>
</form>