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

namespace common\classes\extended;

class Widget extends \yii\base\Widget {
    
    public function render($view, $params = [])
    {   
        $response = parent::render($view, $params);
        if (!empty($response) && \Yii::$app->id == 'app-backend') {
            if (defined('SHOW_EXTENSION_INFO') && SHOW_EXTENSION_INFO == 'True') {
                $module = \common\helpers\Output::mb_basename(trim(str_replace('Render', '', get_class($this)),'/\\'));
                $response .= '<a href="'.\Yii::$app->urlManager->createUrl(['modules/edit', 'platform_id' => 0, 'set' => 'extensions', 'module' => $module]).'" target="_blank"><i class="icon-info-circle"></i></a>';
            }
        }
        return $response;
    }
}