<?php
/**
 * This file is part of osCommerce ecommerce platform.
 * osCommerce the ecommerce
 *
 * @link https://www.oscommerce.com
 * @copyright Copyright (c) 2000-2022 osCommerce LTD
 *
 * Released under the GNU General Public License
 * For the full copyright and license information, please view the LICENSE.TXT file that was distributed with this source code.
 */

namespace backend\design;


use backend\controllers\DesignController;
use backend\design\Style;
use common\models\DesignBoxesSettingsTmp;
use common\models\DesignBoxesTmp;
use common\models\ThemesSettings;
use common\models\ThemesSteps;
use common\models\ThemesStyles;
use common\classes\design as DesignerHelper;

class Steps
{

  public static $elementsEvent = ['stepSave', 'boxAdd', 'blocksMove',
    'boxSave', 'boxDelete', 'importBlock', 'elementsSave', 'elementsCancel'];

  public static $stylesEvent = ['styleSave', 'themeSave', 'themeCancel'];

  public static function stepSave($event, $data, $theme_name, $change_active = true) {
    $before = tep_db_fetch_array(tep_db_query("select steps_id from " . TABLE_THEMES_STEPS . " where active='1' and theme_name='" . tep_db_input($theme_name) . "'"));

    if ($change_active) tep_db_perform(TABLE_THEMES_STEPS, array('active' => '0'), 'update', "active = '1' and theme_name='" . tep_db_input($theme_name) . "'");

    $sql_data_array = array(
      'parent_id' => $before['steps_id'],
      'event' => $event,
      'data' => json_encode($data),
      'theme_name' => $theme_name,
      'date_added' => 'now()',
      'active' => $change_active ? '1' : '',
      'admin_id' => $_SESSION['login_id'],
    );
    tep_db_perform(TABLE_THEMES_STEPS, $sql_data_array);
  }


    public static function undo($theme_name)
    {
        $step = ThemesSteps::find()->where([
            'active' => '1',
            'theme_name' => $theme_name
        ])->asArray()->one();

        $action = $step['event'] . 'Undo';
        if (!is_array($step['data'])) {
            $step['data'] = json_decode($step['data'], true);
        }
        if (method_exists(self::class, $action)){
            self::$action($step);
        }

        tep_db_perform(TABLE_THEMES_STEPS, array('active' => '0'), 'update', "active = '1' and theme_name='" . tep_db_input($theme_name) . "'");
        tep_db_perform(TABLE_THEMES_STEPS, array('active' => '1'), 'update', "steps_id = '" . (int)$step['parent_id'] . "' and theme_name='" . tep_db_input($theme_name) . "'");
        if (!method_exists(self::class, $action)){
            self::undo($theme_name);
        }
    }

  public static function redo($theme_name, $steps_id) {
    $step = tep_db_fetch_array(tep_db_query("select * from " . TABLE_THEMES_STEPS . " where steps_id='" . (int)$steps_id . "' and theme_name='" . tep_db_input($theme_name) . "'"));

    $action = $step['event'] . 'Redo';
      if (!is_array($step['data'])) {
          $step['data'] = json_decode($step['data'], true);
      }
    self::$action($step);

    tep_db_perform(TABLE_THEMES_STEPS, array('active' => '0'), 'update', "active = '1' and theme_name='" . tep_db_input($theme_name) . "'");
    tep_db_perform(TABLE_THEMES_STEPS, array('active' => '1'), 'update', "steps_id = '" . (int)$steps_id . "' and theme_name='" . tep_db_input($theme_name) . "'");

  }


    public static function createMigration($themeName, $stepsIDs)
    {
        if (!isset($themeName) || !isset($stepsIDs) || !is_array($stepsIDs)) {
            return 'error';
        }

        $steps = \common\models\ThemesSteps::find()
            ->where(['IN', 'steps_id', $stepsIDs])
            ->andWhere(['theme_name' => $themeName])
            ->asArray()->all();

        $migration = [];
        foreach ($steps as $step) {
            $step['data'] = json_decode($step['data'], true);
            $migration[] = $step;
        }

        return $migration;
    }

    public static function applyMigration($themeName, $migration)
    {
        if (!isset($migration) || !is_array($migration)) {
            return 'migration is empty';
        }

        $count = 0;
        foreach ($migration as $step) {
            if (!in_array($step['event'], ['cssSave', 'boxAdd', 'blocksMove', 'boxSave', 'boxDelete', 'settings', 'javascriptSave', 'addPage', 'removePageTemplate', 'addPageSettings', 'importBlock', 'stylesChange', 'copyPage'])) {
                continue;
            }

            $redo = $step['event'] . 'Redo';

            $step['theme_name'] = $themeName;
            self::$redo($step, $themeName);
            $count++;
        }

        self::stepSave('applyMigration', $migration, $themeName);

        if ($count > 0) {
            return 'applied';
        } else {
            return 'not applied';
        }
    }

    public static function applyMigrationUdo()
    {

    }

    public static function applyMigrationRedo()
    {

    }


    public static function blockNameToStep($blockName, $themeName)
    {
        $block = explode('-', $blockName);
        if (count($block) > 1) {
            $blockNameStep = DesignBoxesTmp::findOne(['id' => $block[1], 'theme_name' => $themeName])->microtime;
            if (isset($block[2])) {
                $blockNameStep = $blockNameStep . '-' . $block[2];
            }
        } else {
            $blockNameStep = $blockName;
        }
        return $blockNameStep;
    }

    public static function blockNameToDb($blockName, $themeName)
    {
        if (preg_match ('/^[0-9]{5}/', $blockName) > 0) {
            $blockSplit = explode('-', $blockName);
            $blockNameDb = DesignBoxesTmp::findOne([
                'microtime' => $blockSplit[0],
                'theme_name' => $themeName
            ])->id;
            $blockNameDb = 'block-' . $blockNameDb;
            if (isset($blockSplit[1])) {
                $blockNameDb = $blockNameDb . '-' . $blockSplit[1];
            }
        } else {
            $blockNameDb = $blockName;
        }
        return $blockNameDb;
    }


    public static function boxAdd($data)
    {
        $blockName = self::blockNameToStep($data['block_name'], $data['theme_name']);

        $data_s = array(
            'block_id' => $data['block_id'],
            'microtime' => $data['microtime'],
            'block_name' => $blockName,
            'page_name' => Theme::getPageName($data['box_id']),
            'widget_name' => $data['widget_name'],
            'sort_order' => $data['sort_order'],
        );
        if ($data['sort_arr']){
            $data_s = array_merge(
                $data_s, [
                'sort_arr' => $data['sort_arr'],
                'sort_arr_old' => $data['sort_arr_old']
            ]);
        }

        self::stepSave('boxAdd', $data_s, $data['theme_name']);
    }

    public static function boxAddUndo($step)
    {
        $data = $step['data'];
        $blockId = DesignBoxesTmp::findOne(['microtime' => $data['microtime'], 'theme_name' => $step['theme_name']])->id;

        DesignBoxesTmp::deleteAll(['microtime' => $data['microtime'], 'theme_name' => $step['theme_name']]);
        DesignBoxesSettingsTmp::deleteAll(['microtime' => $data['microtime'], 'theme_name' => $step['theme_name']]);

        DesignController::deleteBlock($blockId);
    }

    public static function boxAddRedo($step)
    {
        $data = $step['data'];
        $themeName = $step['theme_name'];

        if (DesignBoxesTmp::findOne(['microtime' => $data['microtime'], 'theme_name' => $themeName])) {
            return 'box already exist';
        }

        $blockName = self::blockNameToDb($data['block_name'], $themeName);

        $designBoxes = new DesignBoxesTmp();
        $designBoxes->setAttributes([
            'microtime' => $data['microtime'],
            'theme_name' => $themeName,
            'block_name' => $blockName,
            'widget_name' => $data['widget_name'],
            'sort_order' => $data['sort_order'],
        ]);
        $designBoxes->save();

        if (count($designBoxes->errors) > 0) {
            return 'box not added';
        }

        if (!isset($data['sort_arr']) || !is_array($data['sort_arr'])){
            return 'box added';
        }

        foreach ($data['sort_arr'] as $microtime => $order){
            $designBoxesSibling = DesignBoxesTmp::findOne([
                'microtime' => $microtime,
                'theme_name' => $themeName
            ]);
            if (!$designBoxesSibling) continue;
            $designBoxesSibling->sort_order = (int)$order;
            $designBoxesSibling->save();
        }
        return 'box added';
    }


    public static function blocksMove($data)
    {
        $positions = [];
        foreach ($data['positions'] as $position) {
            $position['block_name'] = self::blockNameToStep($position['block_name'], $data['theme_name']);
            $positions[] = $position;
        }
        $positionsOld = [];
        foreach ($data['positions_old'] as $position) {
            $position['block_name'] = self::blockNameToStep($position['block_name'], $data['theme_name']);
            $positionsOld[] = $position;
        }
        $data_s = [
            'positions' => $positions,
            'positions_old' => $positionsOld,
        ];

        self::stepSave('blocksMove', $data_s, $data['theme_name']);
    }

    public static function blocksMoveUndo($step){
        $data = $step['data'];

        if (!isset($data['positions_old']) || !is_array($data['positions_old'])){
            return '';
        }

        foreach ($data['positions_old'] as $item){
            $designBoxes = DesignBoxesTmp::findOne([
                'microtime' => $item['microtime'],
                'theme_name' => $step['theme_name'],
            ]);
            if ($designBoxes) {
                $designBoxes->sort_order = $item['sort_order'];
                $designBoxes->block_name = self::blockNameToDb($item['block_name'], $step['theme_name']);
                $designBoxes->save();
            }
        }
    }

    public static function blocksMoveRedo($step){
        $data = $step['data'];
        $themeName = $step['theme_name'];

        if (!isset($data['positions']) || !is_array($data['positions'])){
            return '';
        }

        foreach ($data['positions'] as $item){
            $designBoxes = DesignBoxesTmp::findOne([
                'microtime' => $item['microtime'],
                'theme_name' => $themeName,
            ]);
            if ($designBoxes) {
                $designBoxes->sort_order = $item['sort_order'];
                $designBoxes->block_name = self::blockNameToDb($item['block_name'], $themeName);
                $designBoxes->save();
            }
        }
    }


    public static function settingVisibilityToStep($settings, $themeName)
    {
        $themeMedia = Style::getThemeMedia($themeName);
        foreach ($settings as $key => $setting) {
            if ($setting['visibility'] > 10) {
                $settings[$key]['visibility'] = $themeMedia[$setting['visibility']];
            }
        }
        return $settings;
    }

    public static function settingVisibilityToDb($settings, $themeName)
    {
        $themeMedia = Style::getThemeMedia($themeName, false);
        foreach ($settings as $key => $setting) {
            if (strlen($setting['visibility']) > 1) {
                if (!$themeMedia[$setting['visibility']]) {
                    $themesSetting = new ThemesSettings();
                    $themesSetting->theme_name = $themeName;
                    $themesSetting->setting_group = 'extend';
                    $themesSetting->setting_name = 'media_query';
                    $themesSetting->setting_value = $setting['visibility'];
                    $themesSetting->save();
                    $settings[$key]['visibility'] = $themesSetting->id;
                } else {
                    $settings[$key]['visibility'] = $themeMedia[$setting['visibility']];
                }
            }
        }
        return $settings;
    }

    public static function boxSave($data)
    {
        $data_s = [
            'microtime' => $data['microtime'],
            'box_id' => $data['box_id'],
            'page_name' => Theme::getPageName($data['box_id']),
            'box_settings' => self::settingVisibilityToStep($data['box_settings'], $data['theme_name']),
            'box_settings_old' => self::settingVisibilityToStep($data['box_settings_old'], $data['theme_name']),
        ];

        self::stepSave('boxSave', $data_s, $data['theme_name']);
    }

    public static function boxSaveUndo($step){
        $data = $step['data'];

        if (!isset($data['box_settings_old']) || !is_array($data['box_settings_old'])) {
            return '';
        }
        $themeName = $data['box_settings_old'][0]['theme_name'];
        if (!$themeName) {
            return '';
        }
        $boxId = DesignBoxesTmp::findOne(['microtime' => $data['microtime'], 'theme_name' => $themeName])->id;
        if (!$boxId) {
            return '';
        }
        DesignBoxesSettingsTmp::deleteAll([
            'microtime' => $data['microtime'],
            'theme_name' => $themeName
        ]);

        $data['box_settings_old'] = self::settingVisibilityToDb($data['box_settings_old'], $themeName);
        foreach ($data['box_settings_old'] as $item){
            $designBoxesSettings = new DesignBoxesSettingsTmp();
            $designBoxesSettings->setAttributes([
                'box_id' => $boxId,
                'microtime' => $item['microtime'],
                'theme_name' => $themeName,
                'setting_name' => $item['setting_name'],
                'setting_value' => $item['setting_value'],
                'language_id' => $item['language_id'],
                'visibility' => $item['visibility']
            ]);
            $designBoxesSettings->save();
        }
    }

    public static function boxSaveRedo($step)
    {
        $data = $step['data'];
        $themeName = $step['theme_name'];

        if (!isset($data['box_settings']) || !is_array($data['box_settings'])) {
            return '';
        }
        $boxId = DesignBoxesTmp::findOne(['microtime' => $data['microtime'], 'theme_name' => $themeName])->id;
        if (!$boxId) {
            return '';
        }
        DesignBoxesSettingsTmp::deleteAll([
            'microtime' => $data['microtime'],
            'theme_name' => $themeName
        ]);
        $data['box_settings'] = self::settingVisibilityToDb($data['box_settings'], $themeName);
        foreach ($data['box_settings'] as $item){
            $designBoxesSettings = new DesignBoxesSettingsTmp();
            $designBoxesSettings->setAttributes([
                'box_id' => $boxId,
                'microtime' => $item['microtime'],
                'theme_name' => $themeName,
                'setting_name' => $item['setting_name'],
                'setting_value' => $item['setting_value'],
                'language_id' => $item['language_id'],
                'visibility' => $item['visibility']
            ]);
            $designBoxesSettings->save();
        }
    }


    public static function boxDelete($data)
    {
        $data_s = \backend\design\Theme::blocksTree($data['id']);
        $siblings = DesignBoxesTmp::find()->where(['block_name' => $data_s['block_name']])->asArray()->all();
        $data_s['block_name'] = self::blockNameToStep($data_s['block_name'], $data['theme_name']);
        $data_s['box_id'] = $data['id'];
        $data_s['siblings'] = $siblings;

        self::stepSave('boxDelete', $data_s, $data['theme_name']);
    }

    public static function boxDeleteUndo($step){
        $data = $step['data'];

        $blockName = self::blockNameToDb($data['block_name'], $step['theme_name']);
        Theme::blocksTreeImport($data, $step['theme_name'], $blockName, $data['sort_order']);
    }

    public static function boxDeleteRedo($step)
    {
        $data = $step['data'];
        $themeName = $step['theme_name'];

        $boxId = DesignBoxesTmp::findOne(['microtime' => $data['microtime'], 'theme_name' => $themeName])->id;
        DesignBoxesTmp::deleteAll(['microtime' => $data['microtime'], 'theme_name' => $themeName]);
        DesignBoxesSettingsTmp::deleteAll(['microtime' => $data['microtime'], 'theme_name' => $themeName]);

        DesignController::deleteBlock($boxId);
    }


    public static function removePageTemplate($data)
    {
        $page_name = DesignerHelper::pageName($data['page_title']);

        $designBoxes = DesignBoxesTmp::find()->where([
            'block_name' => $page_name,
            'theme_name' => $data['theme_name']
        ])->asArray()->all();

        $content = [];
        foreach ($designBoxes as $box) {
            $content[] = \backend\design\Theme::blocksTree($box['id']);
        }

        $data_s['content'] = $content;

        $themes_settings = tep_db_query("
                select * 
                from " . TABLE_THEMES_SETTINGS . " 
                where 
                    theme_name = '" . tep_db_input($data['theme_name']) . "' and 
                    ((setting_group = 'added_page' and setting_value = '" . tep_db_input($data['page_title']) . "') or 
                     (setting_group = 'added_page_settings' and setting_name = '" . tep_db_input($data['page_title']) . "'))
        ");
        while ($setting = tep_db_fetch_array($themes_settings)) {
            $data_s['themes_settings'][] = $setting;
        }
        $data_s['page_title'] = $data['page_title'];

        self::stepSave('removePageTemplate', $data_s, $data['theme_name']);
    }

    public static function removePageTemplateUndo($step){
        $data = $step['data'];

        $addedPage = ThemesSettings::findOne([
            'theme_name' => $step['theme_name'],
            'setting_group' => 'added_page',
            'setting_value' => $data['page_title']
        ]);
        if ($addedPage) {
            return '';
        }

        foreach ($data['themes_settings'] as $setting){
            $addedPage = new ThemesSettings();
            $addedPage->theme_name = $step['theme_name'];
            $addedPage->setting_group = $setting['setting_group'];
            $addedPage->setting_name = $setting['setting_name'];
            $addedPage->setting_value = $setting['setting_value'];
            $addedPage->save();
        }

        foreach ($data['content'] as $box) {
            Theme::blocksTreeImport($box, $step['theme_name'], DesignerHelper::pageName($data['page_title']));
        }
    }

    public static function removePageTemplateRedo($step)
    {
        $data = $step['data'];
        $themeName = $step['theme_name'];

        ThemesSettings::deleteAll([
            'theme_name' => $themeName,
            'setting_group' => 'added_page',
            'setting_value' => $data['page_title'],
        ]);
        ThemesSettings::deleteAll([
            'theme_name' => $themeName,
            'setting_group' => 'added_page_settings',
            'setting_value' => $data['page_title'],
        ]);
        self::deletePage($data['page_title'], $themeName);
    }

    public static function deletePage($pageName, $themeName)
    {
        $designBoxes = DesignBoxesTmp::find()->where([
            'block_name' => DesignerHelper::pageName($pageName),
            'theme_name' => $themeName
        ])->asArray()->all();
        if (is_array($designBoxes)) {
            foreach ($designBoxes as $designBox) {
                DesignBoxesTmp::deleteAll([
                    'microtime' => $designBox['microtime'],
                    'theme_name' => $themeName
                ]);
                DesignBoxesSettingsTmp::deleteAll([
                    'microtime' => $designBox['microtime'],
                    'theme_name' => $themeName
                ]);
                DesignController::deleteBlock($designBox['id']);
            }
        }
    }


    public static function importBlock($data)
    {
        $newBox = DesignBoxesTmp::findOne(['id' => $data['id']]);
        $data['microtime'] = $newBox->microtime;
        $data['siblings'] = DesignBoxesTmp::find()->where([
            'block_name' => $newBox->block_name,
            'theme_name' => $data['theme_name'],
        ])->asArray()->all();
        $data['block_name'] = self::blockNameToStep($newBox->block_name, $data['theme_name']);

        self::stepSave('importBlock', $data, $data['theme_name']);
    }

    public static function importBlockUndo($step)
    {
        $data = $step['data'];

        $box = DesignBoxesTmp::findOne([
            'microtime' => $data['content']['microtime'],
            'theme_name' => $step['theme_name'],
        ]);

        DesignBoxesSettingsTmp::deleteAll([
            'microtime' => $data['content']['microtime'],
            'theme_name' => $step['theme_name'],
        ]);

        DesignController::deleteBlock($box->id);

        $box->widget_name = 'Import';
        $box->save();
    }

    public static function importBlockRedo($step)
    {
        $data = $step['data'];
        $themeName = $step['theme_name'];

        $blockName = self::blockNameToDb($data['block_name'], $themeName);
        Theme::blocksTreeImport($data['content'], $themeName, $blockName, $data['content']['sort_order']);

        $siblings = DesignBoxesTmp::find()->where([
            'block_name' => $blockName,
            'theme_name' => $themeName,
        ])->all();

        if (!$siblings) {
            return '';
        }

        foreach ($siblings as $sibling) {
            if ($sibling->widget_name == 'Import') {
                $sibling->delete();
                continue;
            }
            if (!isset($data['siblings']) || !is_array($data['siblings'])) {
                continue;
            }
            foreach ($data['siblings'] as $dataSibling){
                if ($sibling->microtime == $dataSibling['microtime']){
                    $sibling->sort_order = $dataSibling['sort_order'];
                    $sibling->save();
                }
            }
        }
    }


  public static function elementsSave($theme_name){

    $data_s = [];
    self::stepSave('elementsSave', $data_s, $theme_name);

  }

  public static function elementsSaveUndo($step){
  }

  public static function elementsSaveRedo($step){
  }


  public static function elementsCancel($theme_name){

    $current = tep_db_fetch_array(tep_db_query("select * from " . TABLE_THEMES_STEPS . " where active='1' and theme_name='" . tep_db_input($theme_name) . "'"));

    $query = tep_db_fetch_array(tep_db_query("
        select * 
        from " . TABLE_THEMES_STEPS . " 
        where 
          event='elementsSave' and 
          theme_name='" . tep_db_input($theme_name) . "' and
          date_added < '" . $current['date_added'] . "'
        order by	date_added desc limit 1"));

    $data_s = [];
    self::stepSave('elementsCancel', $data_s, $theme_name, false);

    $c = 1;
    $parent_id = $current['parent_id'];
    $chain = array();
    $chain[] = $current;
    while ($c){
      $chain_query = tep_db_query("select * from " . TABLE_THEMES_STEPS . " where steps_id='" . (int)$parent_id . "' and steps_id != '" . (int)$query['steps_id'] . "' and theme_name='" . tep_db_input($theme_name) . "'");
      $c = tep_db_num_rows($chain_query);
      if ($c) {
        $chain_arr = tep_db_fetch_array($chain_query);
        $parent_id = $chain_arr['parent_id'];
        $chain[] = $chain_arr;
      }
    }
    $chain[] = $query;
    $new_parent = $query['steps_id'];

    tep_db_perform(TABLE_THEMES_STEPS, array('active' => '0'), 'update', "active = '1' and theme_name='" . tep_db_input($theme_name) . "'");
    tep_db_perform(TABLE_THEMES_STEPS, array('active' => '1'), 'update', "steps_id = '" . (int)$query['steps_id'] . "' and theme_name='" . tep_db_input($theme_name) . "'");

    for ($i = count($chain)-1; $i >= 0; $i--){
      if (!in_array($chain[$i]['event'], self::$elementsEvent)) {
        tep_db_perform(TABLE_THEMES_STEPS, array('active' => '0'), 'update', "active = '1' and theme_name='" . tep_db_input($theme_name) . "'");
        tep_db_perform(TABLE_THEMES_STEPS, array(
          'parent_id' => $new_parent,
          'event' => $chain[$i]['event'],
          'data' => $chain[$i]['data'],
          'theme_name' => $chain[$i]['theme_name'],
          'date_added' => $chain[$i]['date_added'],
          'active' => '1',
          'admin_id' => $chain[$i]['admin_id'],
        ));
        $new_parent = tep_db_insert_id();
      }
    }

  }

  public static function elementsCancelUndo($step){
  }

  public static function elementsCancelRedo($step){
  }


  public static function styleSave($data){

    $data_s = [
      'styles_old' => $data['styles_old'],
      'styles' => $data['styles'],
    ];
    
    self::stepSave('styleSave', $data_s, $data['theme_name']);

  }

  public static function styleSaveUndo($step){
    $data = $step['data'];
    foreach ($data['styles'] as $item){
      tep_db_query("delete from " . TABLE_THEMES_STYLES . " where id = '" . (int)$item['id'] . "'");
    }
    foreach ($data['styles_old'] as $item){
      tep_db_perform(TABLE_THEMES_STYLES, $item);
    }
  }

  public static function styleSaveRedo($step){
    $data = $step['data'];
    foreach ($data['styles_old'] as $item){
      tep_db_query("delete from " . TABLE_THEMES_STYLES . " where id = '" . (int)$item['id'] . "'");
    }
    foreach ($data['styles'] as $item){
      tep_db_perform(TABLE_THEMES_STYLES, $item);
    }
  }


    public static function settings($data)
    {
        if (!isset($data['them_settings']) || !is_array($data['them_settings']) ||
            !isset($data['them_settings_old']) || !is_array($data['them_settings_old'])
        ) {
            return '';
        }

        foreach ($data['them_settings'] as $key => $setting) {
            foreach ($data['them_settings_old'] as $keyOld => $settingOld) {
                if ($setting['setting_group'] == $settingOld['setting_group'] &&
                    $setting['setting_name'] == $settingOld['setting_name'] &&
                    $setting['setting_value'] == $settingOld['setting_value']
                ) {
                    unset($data['them_settings'][$key]);
                    unset($data['them_settings_old'][$keyOld]);
                }
            }
        }
        $data_s = [
            'them_settings_old' => $data['them_settings_old'],
            'them_settings' => $data['them_settings'],
        ];

        self::stepSave('settings', $data_s, $data['theme_name']);
    }

    public static function settingsUndo($step)
    {
        $data = $step['data'];
        self::settingsChange($data['them_settings_old'], $data['them_settings'], $step['theme_name']);
    }

    public static function settingsRedo($step)
    {
        $data = $step['data'];
        $themeName = $step['theme_name'];
        self::settingsChange($data['them_settings'], $data['them_settings_old'], $themeName);
    }

    public static function settingsChange($new, $old, $themeName)
    {
        foreach ($old as $item){
            $setting = ThemesSettings::findOne([
                'theme_name' => $themeName,
                'setting_group' => $item['setting_group'],
                'setting_name' => $item['setting_name'],
                'setting_value' => $item['setting_value'],
            ]);
            if ($setting) {
                $setting->delete();
            }
        }
        foreach ($new as $item){
            if ($item['setting_group'] == 'extend') {
                $setting = ThemesSettings::findOne([
                    'theme_name' => $themeName,
                    'setting_group' => $item['setting_group'],
                    'setting_name' => $item['setting_name'],
                    'setting_value' => $item['setting_value'],
                ]);
                if ($setting) {
                    continue;
                }
            } else {
                ThemesSettings::deleteAll([
                    'theme_name' => $themeName,
                    'setting_group' => $item['setting_group'],
                    'setting_name' => $item['setting_name'],
                ]);
            }

            $setting = new ThemesSettings();
            $setting->theme_name = $themeName;
            $setting->setting_group = $item['setting_group'];
            $setting->setting_name = $item['setting_name'];
            $setting->setting_value = $item['setting_value'];
            $setting->save();
        }
    }


    public static function cssSave($data)
    {
        $data['attributes_delete'] = self::settingVisibilityToStep($data['attributes_delete'], $data['theme_name']);
        $data['attributes_changed'] = self::settingVisibilityToStep($data['attributes_changed'], $data['theme_name']);
        $data['attributes_new'] = self::settingVisibilityToStep($data['attributes_new'], $data['theme_name']);
        self::stepSave('cssSave', $data, $data['theme_name']);
    }

  public static function cssSaveUndo($step){
    $data = $step['data'];

      $data['attributes_delete'] = self::settingVisibilityToDb($data['attributes_delete'], $step['theme_name']);
      $data['attributes_changed'] = self::settingVisibilityToDb($data['attributes_changed'], $step['theme_name']);
      $data['attributes_new'] = self::settingVisibilityToDb($data['attributes_new'], $step['theme_name']);

    foreach ($data['attributes_changed'] as $item) {
        tep_db_perform(TABLE_THEMES_STYLES, [
            'value' => $item['value_old']
        ], 'update', "
                theme_name = '" . tep_db_input($data['theme_name']) . "' and
                selector = '" . tep_db_input($item['selector']) . "' and
                attribute = '" . tep_db_input($item['attribute']) . "' and
                visibility = '" . tep_db_input($item['visibility']) . "' and
                media = '" . tep_db_input($item['media']) . "' and
                accessibility = '" . tep_db_input($item['accessibility']) . "'
        ");
    }

    foreach ($data['attributes_delete'] as $item) {
        tep_db_perform(TABLE_THEMES_STYLES, [
                'theme_name' => $data['theme_name'],
                'selector' => $item['selector'],
                'attribute' => $item['attribute'],
                'value' => $item['value_old'],
                'visibility' => $item['visibility'],
                'media' => $item['media'],
                'accessibility' => $item['accessibility']
        ]);
    }

    foreach ($data['attributes_new'] as $item) {
        tep_db_query("delete from " . TABLE_THEMES_STYLES . " where
                theme_name = '" . tep_db_input($data['theme_name']) . "' and
                selector = '" . tep_db_input($item['selector']) . "' and
                attribute = '" . tep_db_input($item['attribute']) . "' and
                visibility = '" . tep_db_input($item['visibility']) . "' and
                media = '" . tep_db_input($item['media']) . "' and
                accessibility = '" . tep_db_input($item['accessibility']) . "'
        ");
    }

      Style::createCache($data['theme_name'], self::getAccessibility($data));
  }

    public static function cssSaveRedo($step)
    {
        $data = $step['data'];
        $themeName = $step['theme_name'];

        $data['attributes_delete'] = self::settingVisibilityToDb($data['attributes_delete'], $themeName);
        $data['attributes_changed'] = self::settingVisibilityToDb($data['attributes_changed'], $themeName);
        $data['attributes_new'] = self::settingVisibilityToDb($data['attributes_new'], $themeName);

        self::cssSaveAttributes($data['attributes_changed'], $themeName);
        self::cssSaveAttributes($data['attributes_new'], $themeName);

        if (isset($data['attributes_delete']) && is_array($data['attributes_delete'])) {
            foreach ($data['attributes_delete'] as $item) {
                ThemesStyles::deleteAll([
                    'theme_name' => $themeName,
                    'selector' => $item['selector'],
                    'attribute' => $item['attribute'],
                    'visibility' => $item['visibility'],
                    'media' => $item['media'],
                    'accessibility' => $item['accessibility'],
                ]);
            }
        }
        Style::createCache($themeName, self::getAccessibility($data));
    }

    public static function cssSaveAttributes($data, $themeName)
    {
        if (isset($data) && is_array($data)){
            foreach ($data as $item) {
                $styleSet = [
                    'theme_name' => $themeName,
                    'selector' => $item['selector'],
                    'attribute' => $item['attribute'],
                    'visibility' => $item['visibility'],
                    'media' => $item['media'],
                    'accessibility' => $item['accessibility'],
                ];
                $style = ThemesStyles::findOne($styleSet);

                if (!$style) {
                    $style = new ThemesStyles();
                    $style->setAttributes($styleSet);
                }
                $style->setAttributes(['value' => $item['value']]);
                $style->save();
            }
        }
    }

    public static function getAccessibility($data)
    {
        if (!isset($data) || !is_array($data)){
            return false;
        }
        foreach (['attributes_delete', 'attributes_changed', 'attributes_new'] as $item) {
            if (!is_array($data[$item]) || !count($data[$item])) continue;
            $firstItem = reset($data[$item]);
            return $firstItem['accessibility'];
        }
    }


  public static function javascriptSave($data){

    $query = tep_db_fetch_array(tep_db_query("select steps_id, data, event, admin_id from " . TABLE_THEMES_STEPS . " where active='1' and theme_name='" . tep_db_input($data['theme_name']) . "'"));

    if ($query['event'] == 'javascriptSave' && $query['admin_id'] == $_SESSION['login_id']){

      $data_s = json_decode($query['data'], true);
      $data_s['javascript'] = $data['javascript'];

      $sql_data_array = array(
        'data' => json_encode($data_s),
        'date_added' => 'now()',
      );
      tep_db_perform(TABLE_THEMES_STEPS, $sql_data_array, 'update', "steps_id='" . (int)$query['steps_id'] . "'");

    } else {

      $data_s = [
        'javascript_old' => $data['javascript_old'],
        'javascript' => $data['javascript'],
      ];
      self::stepSave('javascriptSave', $data_s, $data['theme_name']);

    }

  }

    public static function javascriptSaveUndo($step){
        $data = $step['data'];

        $themesSettings = ThemesSettings::findOne([
            'theme_name' => $step['theme_name'],
            'setting_group' => 'javascript',
            'setting_name' => 'javascript',
        ]);
        if (!$themesSettings){
            $themesSettings = new ThemesSettings();
        }
        $themesSettings->setting_value = $data['javascript_old'];
        $themesSettings->save();
    }

    public static function javascriptSaveRedo($step)
    {
        $data = $step['data'];
        $themeName = $step['theme_name'];

        $themesSettings = ThemesSettings::findOne([
            'theme_name' => $themeName,
            'setting_group' => 'javascript',
            'setting_name' => 'javascript',
        ]);
        if (!$themesSettings){
            $themesSettings = new ThemesSettings();
        }
        $themesSettings->setting_value = $data['javascript'];
        $themesSettings->save();
    }


  public static function backupSubmit($data){

    $data_s = [
      'backup_id' => (int)$data['backup_id']
    ];

    self::stepSave('backupSubmit', $data_s, $data['theme_name']);
  }

  public static function backupSubmitUndo($step){
  }

  public static function backupSubmitRedo($step){
  }


  public static function backupRestore($data){

    $data_s = [
      'backup_id' => $data['backup_id']
    ];

    tep_db_perform(TABLE_THEMES_STEPS, array('active' => '0'), 'update', "active = '1' and theme_name='" . tep_db_input($data['theme_name']) . "'");
    tep_db_perform(TABLE_THEMES_STEPS, array('active' => '1'), 'update', "data = '" . tep_db_input(json_encode(['backup_id' => (int)$data['backup_id']])) . "' and theme_name='" . tep_db_input($data['theme_name']) . "'");

    self::stepSave('backupRestore', $data_s, $data['theme_name'], false);
  }

  public static function backupRestoreUndo($step){
  }

  public static function backupRestoreRedo($step){
  }


  public static function themeSave($theme_name){

    $data_s = [];
    self::stepSave('themeSave', $data_s, $theme_name);

  }

  public static function themeSaveUndo($step){
  }

  public static function themeSaveRedo($step){
  }


  public static function themeCancel($theme_name){

    $current = tep_db_fetch_array(tep_db_query("select * from " . TABLE_THEMES_STEPS . " where active='1' and theme_name='" . tep_db_input($theme_name) . "'"));

    $query = tep_db_fetch_array(tep_db_query("
        select * 
        from " . TABLE_THEMES_STEPS . " 
        where 
          event='themeSave' and 
          theme_name='" . tep_db_input($theme_name) . "' and
          date_added < '" . $current['date_added'] . "'
        order by	date_added desc limit 1"));

    $data_s = [];
    self::stepSave('themeCancel', $data_s, $theme_name, false);

    $c = 1;
    $parent_id = $current['parent_id'];
    $chain = array();
    $chain[] = $current;
    while ($c){
      $chain_query = tep_db_query("select * from " . TABLE_THEMES_STEPS . " where steps_id='" . (int)$parent_id . "' and steps_id != '" . (int)$query['steps_id'] . "' and theme_name='" . tep_db_input($theme_name) . "'");
      $c = tep_db_num_rows($chain_query);
      if ($c) {
        $chain_arr = tep_db_fetch_array($chain_query);
        $parent_id = $chain_arr['parent_id'];
        $chain[] = $chain_arr;
      }
    }
    $chain[] = $query;
    $new_parent = $query['steps_id'];

    tep_db_perform(TABLE_THEMES_STEPS, array('active' => '0'), 'update', "active = '1' and theme_name='" . tep_db_input($theme_name) . "'");
    tep_db_perform(TABLE_THEMES_STEPS, array('active' => '1'), 'update', "steps_id = '" . (int)$query['steps_id'] . "' and theme_name='" . tep_db_input($theme_name) . "'");

    for ($i = count($chain)-1; $i >= 0; $i--){
      if (!in_array($chain[$i]['event'], self::$stylesEvent)) {
        tep_db_perform(TABLE_THEMES_STEPS, array('active' => '0'), 'update', "active = '1' and theme_name='" . tep_db_input($theme_name) . "'");
        tep_db_perform(TABLE_THEMES_STEPS, array(
          'parent_id' => $new_parent,
          'event' => $chain[$i]['event'],
          'data' => $chain[$i]['data'],
          'theme_name' => $chain[$i]['theme_name'],
          'date_added' => $chain[$i]['date_added'],
          'active' => '1',
          'admin_id' => $chain[$i]['admin_id'],
        ));
        $new_parent = tep_db_insert_id();
      }
    }

  }

  public static function themeCancelUndo($step){
  }

  public static function themeCancelRedo($step){
  }


    public static function addPage($data)
    {
        $data_s = [
            'page_type' => $data['setting_name'],
            'page_name' => $data['setting_value'],
            'content' => $data['content']
        ];
        self::stepSave('addPage', $data_s, $data['theme_name']);
    }

    public static function addPageUndo($step)
    {
        $data = $step['data'];

        ThemesSettings::deleteAll([
            'theme_name' => $step['theme_name'],
            'setting_group' => 'added_page',
            'setting_name' => $data['page_type'],
            'setting_value' => $data['page_name']
        ]);

        self::deletePage($data['page_name'], $step['theme_name']);
    }

    public static function addPageRedo($step)
    {
        $data = $step['data'];
        $themeName = $step['theme_name'];

        $addedPage = ThemesSettings::findOne([
            'theme_name' => $themeName,
            'setting_group' => 'added_page',
            'setting_value' => $data['page_name']
        ]);
        if ($addedPage) {
            return '';
        }

        $addedPage = new ThemesSettings();
        $addedPage->theme_name = $themeName;
        $addedPage->setting_group = 'added_page';
        $addedPage->setting_name = $data['page_type'];
        $addedPage->setting_value = $data['page_name'];
        $addedPage->save();

        foreach ($data['content'] as $box) {
            Theme::blocksTreeImport($box, $themeName, DesignerHelper::pageName($data['page_name']));
        }
    }


    public static function addPageSettings($data)
    {
        $data_s = [
            'page_name' => $data['page_name'],
            'settings_old' => $data['settings_old'],
            'settings' => $data['settings']
        ];
        self::stepSave('addPageSettings', $data_s, $data['theme_name']);
    }

    public static function addPageSettingsUndo($step){
        $data = $step['data'];

        ThemesSettings::deleteAll([
            'theme_name' => $step['theme_name'],
            'setting_group' => 'added_page_settings',
            'setting_name' => $data['page_name'],
        ]);
        echo '<pre>';
        var_dump($step['theme_name']);
        var_dump($data['page_name']);
        echo '</pre>';

        if (!isset($data['settings_old']) || !is_array($data['settings_old'])) {
            return '';
        }
        foreach ($data['settings_old'] as $item){
            $themesSettings = new ThemesSettings();
            $themesSettings->theme_name = $step['theme_name'];
            $themesSettings->setting_group = $item['setting_group'];
            $themesSettings->setting_name = $item['setting_name'];
            $themesSettings->setting_value = $item['setting_value'];
            $themesSettings->save();
        }
    }

    public static function addPageSettingsRedo($step)
    {
        $data = $step['data'];
        $themeName = $step['theme_name'];

        ThemesSettings::deleteAll([
            'theme_name' => $themeName,
            'setting_group' => 'added_page_settings',
            'setting_name' => $data['page_name'],
        ]);

        if (!isset($data['settings']) || !is_array($data['settings_old'])) {
            return '';
        }
        foreach ($data['settings'] as $item){
            $themesSettings = new ThemesSettings();
            $themesSettings->theme_name = $themeName;
            $themesSettings->setting_group = $item['setting_group'];
            $themesSettings->setting_name = $item['setting_name'];
            $themesSettings->setting_value = $item['setting_value'];
            $themesSettings->save();
        }
    }


    public static function log($theme_name, $output = [])
    {

        $active = tep_db_fetch_array(tep_db_query("select steps_id from " . TABLE_THEMES_STEPS . " where theme_name='" . tep_db_input($theme_name) . "' and active='1'"));
        $log = array();

        $filter = '';
        if (tep_not_null($output['from'])) {
            $from = tep_db_prepare_input($output['from']);
            $filter .= " and to_days(date_added) >= to_days('" . \common\helpers\Date::prepareInputDate($from) . "')";
        }
        if (tep_not_null($output['to'])) {
            $to = tep_db_prepare_input($output['to']);
            $filter .= " and to_days(date_added) <= to_days('" . \common\helpers\Date::prepareInputDate($to) . "')";
        }

        $limit = '';
        if (!$filter) {
            $limit = ' limit 500';
        }

        $query = tep_db_query("select steps_id, parent_id, event, date_added, admin_id from " . TABLE_THEMES_STEPS . " where theme_name='" . tep_db_input($theme_name) . "'" . $filter . " order by date_added desc " . $limit);

        $current = $active['steps_id'];
        $count = 0;
        while ($item = tep_db_fetch_array($query)){
            if (!$count && $output['to']){
                $current = $item['steps_id'];
                $count++;
            }
            $log[$item['steps_id']] = [
                'steps_id' => $item['steps_id'],
                'parent_id' => $item['parent_id'],
                'event' => $item['event'],
                'date_added' => $item['date_added'],
                'admin_id' => $item['admin_id'],
            ];
        }

        $trunk = array();
        $tree = array();
        while (is_array($log[$current])){
            $trunk[] = $current;
            $tree[$current] = $log[$current];
            $tree[$current]['branches'] = 1;
            $tree[$current]['branch_id'] = 0;
            $current = $log[$current]['parent_id'];
        }

        $branches = array();
        foreach ($log as $id => $item){
            if(!in_array($id, $trunk)){
                $branches[$item['steps_id']] = $item;
            }
        }

        $count_error = 0;

        while (count($branches) > 0) {
            foreach ($branches as $item) {
                if (is_array($tree[$item['parent_id']])) {
                    $tree[$item['parent_id']]['branches']++;

                    $tree[$item['steps_id']] = $item;
                    if ($tree[$item['parent_id']]['branches'] == 1){
                        $tree[$item['steps_id']]['branch_id'] = $tree[$item['parent_id']]['branch_id'];
                    } else {
                        $tree[$item['steps_id']]['branch_id'] = $item['parent_id'];
                    }

                    $tree[$item['steps_id']]['branches'] = 0;
                }
                unset($branches[$item['steps_id']]);
            }

            $count_error++;
            if ($count_error > 1000000) return 'Error, too many steps. 2';
        }

        foreach ($tree as $key => $item){
            $tree[$key]['text'] = self::logNames($item['event']);
            $tree[$key]['date_added'] = \common\helpers\Date::date_long($tree[$key]['date_added'], "%d %b %Y / %H:%M:%S");
        }

        return $tree;

    }

    public static function logDetails($id)
    {
        $details = tep_db_fetch_array(tep_db_query("select * from " . TABLE_THEMES_STEPS . " where steps_id = '" . (int)$id . "'"));

        $details['name'] = self::logNames($details['event']);
        $details['date_added'] = \common\helpers\Date::date_long($details['date_added'], "%d %b %Y / %H:%M:%S");

        $admin = tep_db_fetch_array(tep_db_query("
            select admin_id, admin_firstname, admin_lastname, admin_email_address 
            from " . TABLE_ADMIN . " 
            where admin_id = '" . (int)$details['admin_id'] . "'"));

        $data = json_decode($details['data'], true);

        $details['admin'] = $admin['admin_firstname'] . ' ' . $admin['admin_lastname'];

        if ($details['event'] == 'boxAdd') {
            $details['widget_name'] = $data['widget_name'];
            $details['page_name'] = $data['page_name'];
        }
        if ($details['event'] == 'boxSave') {
            $details['page_name'] = $data['page_name'];

            $widget = tep_db_fetch_array(tep_db_query("
                select widget_name 
                from " . TABLE_DESIGN_BOXES_TMP . " 
                where id = '" . (int)$data['box_id'] . "'"));

            $details['widget_name'] = $widget['widget_name'];


            $details['widgetSettings'] = [];
            foreach ($data['box_settings'] as $key => $setting) {
                $details['widgetSettings'][$key]['new'] = $setting;
                foreach ($data['box_settings_old'] as $settingOld) {
                    if (
                        $setting['setting_name'] == $settingOld['setting_name'] &&
                        $setting['visibility'] == $settingOld['visibility']
                    ){
                        $details['widgetSettings'][$key]['old'] = $settingOld;
                    }
                }
            }
        }
        if ($details['event'] == 'cssSave') {
            $mediaSizesArr = ThemesSettings::find()
                ->where([
                    'theme_name' => $details['theme_name'],
                    'setting_name' => 'media_query',
                ])
                ->asArray()->all();
            $data['attributes_delete'] = self::settingVisibilityToDb($data['attributes_delete'], $details['theme_name']);
            $data['attributes_changed'] = self::settingVisibilityToDb($data['attributes_changed'], $details['theme_name']);
            $data['attributes_new'] = self::settingVisibilityToDb($data['attributes_new'], $details['theme_name']);
            $details['css']['delete'] = Style::getCreateCss($data['attributes_delete'], $mediaSizesArr);
            $details['css']['new'] = Style::getCreateCss($data['attributes_new'], $mediaSizesArr);
            $details['css']['changed'] = Style::getCreateCss($data['attributes_changed'], $mediaSizesArr);
        }

        return $details;
    }


  public static function logNames($event){
    $text = '';
    switch ($event) {
      case 'boxAdd': $text = LOG_ADDED_NEW_BLOCK; break;
      case 'blocksMove': $text = LOG_CHANGED_BLOCK_POSITION; break;
      case 'boxSave': $text = LOG_CHANGED_BLOCK_SETTINGS; break;
      case 'boxDelete': $text = LOG_REMOVED_BLOCK; break;
      case 'importBlock': $text = LOG_IMPORTED_BLOCK; break;
      case 'elementsSave': $text = LOG_SAVED_EDIT_ELEMENTS_PAGE; break;
      case 'elementsCancel': $text = LOG_CANCELED_EDIT_ELEMENTS_PAGE; break;
      case 'styleSave': $text = LOG_CANCELED_THEME_STYLES; break;
      case 'settings': $text = LOG_CHANGED_THEME_SETTINGS; break;
      //case 'extendRemove': $text = LOG_REMOVED_EXTEND_FIELD; break;
      //case 'extendAdd': $text = LOG_ADDED_EXTEND_FIELD; break;
      case 'cssSave': $text = LOG_SAVED_CSS; break;
      case 'javascriptSave': $text = LOG_SAVED_JAVASCRIPT; break;
      case 'backupSubmit': $text = LOG_DID_BACKU; break;
      case 'backupRestore': $text = LOG_RESTORED_BACKUP; break;
      case 'themeSave': $text = LOG_SAVED_CUSTOMIZE_THEME_STYLES; break;
      case 'themeCancel': $text = LOG_CANCELED_CUSTOMIZE_THEME_STYLES; break;
      case 'addPage': $text = LOG_ADDED_NEW_PAGE; break;
      case 'removePageTemplate': $text = 'removed page template'; break;
      case 'addPageSettings': $text = LOG_CHANGED_ADDED_PAGE; break;
      case 'stylesChange': $text = 'changed styles'; break;
      case 'copyPage': $text = 'copied page'; break;
      case 'importTheme': $text = 'imported theme'; break;
      case 'applyMigration': $text = 'applied migration'; break;
    }

    return $text;
  }
  
  
  public static function restore($id){

    $chain = array();
    $event = 1;
    $theme_name = 0;


    while (!$theme_name){

      while ($event && $event != 'backupSubmit' && $event != 'backupRestore') {
        $item = tep_db_fetch_array(tep_db_query("select steps_id, parent_id, event, data from " . TABLE_THEMES_STEPS . " where steps_id = '" . (int)$id . "'"));
        $event = $item['event'];
        $chain[] = $item['steps_id'];
        $id = $item['parent_id'];
      }

      if (!$event){
        return LOG_NO_BACKUPS;
      }

      $data = json_decode($item['data'], true);

      $query = tep_db_fetch_array(tep_db_query("select theme_name from " . TABLE_DESIGN_BACKUPS . " where backup_id = '" . (int)$data['backup_id'] . "' limit 1"));
      $theme_name = $query['theme_name'];
      if ($theme_name && $data['backup_id']){
        \backend\design\Backups::backupRestore($data['backup_id'], $theme_name);
        tep_db_perform(TABLE_THEMES_STEPS, array('active' => '0'), 'update', "active = '1' and theme_name='" . tep_db_input($theme_name) . "'");
        tep_db_perform(TABLE_THEMES_STEPS, array('active' => '1'), 'update', "steps_id = '" . $item['steps_id'] . "' and theme_name='" . tep_db_input($theme_name) . "'");
      }

      $event = 1;
    }

    for ($i = count($chain)-1; $i >= 0; $i--){
      self::redo($theme_name, $chain[$i]);
    }




    return '';
  }


    public static function stylesChange($data)
    {
        if ($data['style'] == 'border_color') {
            $style = ['border_top_color', 'border_left_color', 'border_right_color', 'border_bottom_color'];
        } else {
            $style = $data['style'];
        }

        $themesStyles = ThemesStyles::find()
            ->where([
                'theme_name' => $data['theme_name'],
                'value' => $data['from']
            ])
            ->andWhere(['in', 'attribute', $style])
            ->asArray()->all();

        $themesStyles = self::settingVisibilityToStep($themesStyles, $data['theme_name']);

        $designBoxesSettings = DesignBoxesSettingsTmp::find()
            ->select(['microtime', 'setting_name', 'visibility'])
            ->where([
                'theme_name' => $data['theme_name'],
                'setting_value' => $data['from']
            ])
            ->andWhere(['in', 'setting_name', $style])
            ->asArray()->all();

        $data_s = [
            'themesStyles' => $themesStyles,
            'designBoxesSettings' => $designBoxesSettings,
            'from' => $data['from'],
            'to' => $data['to'],
            'style' => $data['style'],
        ];

        self::stepSave('stylesChange', $data_s, $data['theme_name']);
    }

    public static function stylesChangeUndo($step)
    {
        self::stylesChangeEvent($step, false);
    }

    public static function stylesChangeRedo($step)
    {
        self::stylesChangeEvent($step, true);
    }

    public static function stylesChangeEvent($step, $redo)
    {
        $data = $step['data'];

        $data['themesStyles'] = self::settingVisibilityToDb($data['themesStyles'], $step['theme_name']);

        foreach ($data['themesStyles'] as $themesStyle) {
            $themesStyles = ThemesStyles::findOne([
                'theme_name' => $step['theme_name'],
                'selector' => $themesStyle['selector'],
                'attribute' => $themesStyle['attribute'],
                'visibility' => $themesStyle['visibility'],
                'media' => $themesStyle['media'],
                'accessibility' => $themesStyle['accessibility'],
                'value' => $data[($redo ? 'from' : 'to')],
            ]);
            if (!$themesStyles) continue;
            $themesStyles->value = $data[($redo ? 'to' : 'from')];
            $themesStyles->save();
        }

        foreach ($data['designBoxesSettings'] as $designBoxesSetting) {
            $designBoxesSettings = DesignBoxesSettingsTmp::findOne([
                'theme_name' => $step['theme_name'],
                'microtime' => $designBoxesSetting['microtime'],
                'setting_name' => $designBoxesSetting['setting_name'],
                'visibility' => $designBoxesSetting['visibility'],
                'setting_value' => $data[($redo ? 'from' : 'to')],
            ]);
            if (!$designBoxesSettings) continue;
            $designBoxesSettings->setting_value = $data[($redo ? 'to' : 'from')];
            $designBoxesSettings->save();
        }
    }


  public static function removeClass($data)
  {
    $styles = array();
    $query = tep_db_query("select * from " . TABLE_THEMES_STYLES . " where theme_name = '" . tep_db_input($data['theme_name']) . "' and selector = '" . tep_db_input($data['class']) . "'");
    while ($item = tep_db_fetch_array($query)) {
      $styles[] = $item;
    }

    $data_s = [
        'styles' => $styles,
        'class' => $data['class'],
    ];

    self::stepSave('removeClass', $data_s, $data['theme_name']);
  }

  public static function removeClassUndo($step)
  {
    $data = $step['data'];

    foreach ($data['styles'] as $item){
      tep_db_perform(TABLE_THEMES_STYLES, $item);
    }
  }

  public static function removeClassRedo($step)
  {
    $data = $step['data'];

    tep_db_query("delete from " . TABLE_THEMES_STYLES . " where theme_name = '" .  tep_db_input($step['theme_name']) . "' and selector = '" . tep_db_input($data['class']) . "'");
  }


    public static function copyPage($data)
    {
        self::stepSave('copyPage', $data, $data['theme_name']);
    }

    public static function copyPageUndo($step)
    {
        $data = $step['data'];
        self::deletePage($data['page_to'], $step['theme_name']);

        foreach ($data['content_old'] as $box) {
            Theme::blocksTreeImport($box, $step['theme_name'], DesignerHelper::pageName($data['page_to']));
        }
    }

    public static function copyPageRedo($step)
    {
        $data = $step['data'];
        self::deletePage($data['page_to'], $step['theme_name']);

        foreach ($data['content'] as $box) {
            Theme::blocksTreeImport($box, $step['theme_name'], DesignerHelper::pageName($data['page_to']));
        }
    }


    public static function importTheme($data)
    {
        self::stepSave('importTheme', $data, $data['theme_name']);
    }

    public static function importThemeUndo($step)
    {
    }

    public static function importThemeRedo($step)
    {
    }


}
