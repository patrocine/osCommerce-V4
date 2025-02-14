<?php
 /**
 * Transactional Midle Ware for Paypal modules
 * This file is part of osCommerce ecommerce platform.
 * osCommerce the ecommerce
 *
 * @link https://www.oscommerce.com
 * @copyright Copyright (c) 2000-2022 osCommerce LTD
 *
 * Released under the GNU General Public License
 * For the full copyright and license information, please view the LICENSE.TXT file that was distributed with this source code.
 */
namespace common\modules\orderPayment\lib\PaypalPartner\api;

use PayPal\Common\PayPalResourceModel;
use PayPal\Validation\ArgumentValidator;
use PayPal\Rest\ApiContext;


class Name extends PayPalResourceModel {
    
    public function __construct($data = null){
        parent::__construct($data);        
        $this->type = PartnerConstants::NAME_TYPE;
    }
    
    public function setGivenName($givenName)
    {
        $this->given_name = $givenName;
        return $this;
    }

    /**
     * Transactional details including the amount and item details.
     *
     * @return \PayPal\Api\Transaction[]
     */
    public function getGivenName()
    {
        return $this->given_name;
    }
    
    public function setSurname($surname)
    {
        $this->surname = $surname;
        return $this;
    }

    /**
     * Transactional details including the amount and item details.
     *
     * @return \PayPal\Api\Transaction[]
     */
    public function getSurname()
    {
        return $this->surname;
    }
}