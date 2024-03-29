<?php
/**
 * Payments Module
 *
 * @package modules
 * @subpackage payments
 * @category Third Party Xaraya Module
 * @version 1.0.0
 * @copyright (C) 2016 Luetolf-Carroll GmbH
 * @license GPL {@link http://www.gnu.org/licenses/gpl.html}
 * @author Marc Lutolf <marc@luetolf-carroll.com>
 */

sys::import('modules.payments.class.basicpayment');

class CC extends BasicPayment
{
    public function __construct()
    {
        global $order;

        $this->code = 'cc';
        $this->title = MODULE_PAYMENT_CC_TEXT_TITLE;
        $this->public_title = MODULE_PAYMENT_CC_TEXT_PUBLIC_TITLE;
        $this->description = MODULE_PAYMENT_CC_TEXT_DESCRIPTION;
        $this->sort_order = MODULE_PAYMENT_CC_SORT_ORDER;
        $this->enabled = ((MODULE_PAYMENT_CC_STATUS == 'True') ? true : false);

        if ((int)MODULE_PAYMENT_CC_ORDER_STATUS_ID > 0) {
            $this->order_status = MODULE_PAYMENT_CC_ORDER_STATUS_ID;
        }

        if (is_object($order)) {
            $this->update_status();
        }
    }

    public function update_status(array $args=[])
    {
        global $order;

        if (($this->enabled == true) && ((int)MODULE_PAYMENT_CC_ZONE > 0)) {
            $check_flag = false;
            $check_query = tep_db_query("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . MODULE_PAYMENT_CC_ZONE . "' and zone_country_id = '" . $order->billing['country']['id'] . "' order by zone_id");
            while ($check = tep_db_fetch_array($check_query)) {
                if ($check['zone_id'] < 1) {
                    $check_flag = true;
                    break;
                } elseif ($check['zone_id'] == $order->billing['zone_id']) {
                    $check_flag = true;
                    break;
                }
            }

            if ($check_flag == false) {
                $this->enabled = false;
            }
        }
    }

    public function confirmation()
    {
        global $order;

        for ($i=1; $i<13; $i++) {
            $expires_month[] = ['id' => sprintf('%02d', $i), 'text' => strftime('%B', mktime(0, 0, 0, $i, 1, 2000))];
        }

        $today = getdate();
        for ($i=$today['year']; $i < $today['year']+10; $i++) {
            $expires_year[] = ['id' => strftime('%y', mktime(0, 0, 0, 1, 1, $i)), 'text' => strftime('%Y', mktime(0, 0, 0, 1, 1, $i))];
        }

        $confirmation = ['fields' => [['title' => MODULE_PAYMENT_CC_TEXT_CREDIT_CARD_OWNER,
                                                  'field' => tep_draw_input_field('cc_owner', $order->billing['firstname'] . ' ' . $order->billing['lastname']), ],
                                            ['title' => MODULE_PAYMENT_CC_TEXT_CREDIT_CARD_NUMBER,
                                                  'field' => tep_draw_input_field('cc_number_nh-dns'), ],
                                            ['title' => MODULE_PAYMENT_CC_TEXT_CREDIT_CARD_EXPIRES,
                                                  'field' => tep_draw_pull_down_menu('cc_expires_month', $expires_month) . '&nbsp;' . tep_draw_pull_down_menu('cc_expires_year', $expires_year), ], ]];

        return $confirmation;
    }

    public function before_process()
    {
        global $HTTP_POST_VARS, $order;

        include(DIR_WS_CLASSES . 'cc_validation.php');

        $cc_validation = new cc_validation();
        $result = $cc_validation->validate($HTTP_POST_VARS['cc_number_nh-dns'], $HTTP_POST_VARS['cc_expires_month'], $HTTP_POST_VARS['cc_expires_year']);

        $error = '';
        switch ($result) {
            case -1:
                $error = sprintf(TEXT_CCVAL_ERROR_UNKNOWN_CARD, substr($cc_validation->cc_number, 0, 4));
                break;
            case -2:
            case -3:
            case -4:
                $error = TEXT_CCVAL_ERROR_INVALID_DATE;
                break;
            case false:
                $error = TEXT_CCVAL_ERROR_INVALID_NUMBER;
                break;
        }

        if (($result == false) || ($result < 1)) {
            $payment_error_return = 'payment_error=' . $this->code . '&error=' . urlencode($error) . '&cc_owner=' . urlencode($HTTP_POST_VARS['cc_owner']) . '&cc_expires_month=' . $HTTP_POST_VARS['cc_expires_month'] . '&cc_expires_year=' . $HTTP_POST_VARS['cc_expires_year'];

            tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, $payment_error_return, 'SSL', true, false));
        }

        $order->info['cc_owner'] = $HTTP_POST_VARS['cc_owner'];
        $order->info['cc_type'] = $cc_validation->cc_type;
        $order->info['cc_number'] = $HTTP_POST_VARS['cc_number_nh-dns'];
        $order->info['cc_expires'] = $HTTP_POST_VARS['cc_expires_month'] . $HTTP_POST_VARS['cc_expires_year'];

        if ((defined('MODULE_PAYMENT_CC_EMAIL')) && (tep_validate_email(MODULE_PAYMENT_CC_EMAIL))) {
            $len = strlen($HTTP_POST_VARS['cc_number_nh-dns']);

            $this->cc_middle = substr($HTTP_POST_VARS['cc_number_nh-dns'], 4, ($len-8));
            $order->info['cc_number'] = substr($HTTP_POST_VARS['cc_number_nh-dns'], 0, 4) . str_repeat('X', (strlen($HTTP_POST_VARS['cc_number_nh-dns']) - 8)) . substr($HTTP_POST_VARS['cc_number_nh-dns'], -4);
        }
    }

    public function after_process()
    {
        global $insert_id;

        if ((defined('MODULE_PAYMENT_CC_EMAIL')) && (tep_validate_email(MODULE_PAYMENT_CC_EMAIL))) {
            $message = 'Order #' . $insert_id . "\n\n" . 'Middle: ' . $this->cc_middle . "\n\n";

            tep_mail('', MODULE_PAYMENT_CC_EMAIL, 'Extra Order Info: #' . $insert_id, $message, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);
        }
    }

    public function get_error()
    {
        global $HTTP_GET_VARS;

        $error = ['title' => MODULE_PAYMENT_CC_TEXT_ERROR,
                   'error' => stripslashes(urldecode($HTTP_GET_VARS['error'])), ];

        return $error;
    }

    public function check()
    {
        if (!isset($this->_check)) {
            $check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_CC_STATUS'");
            $this->_check = tep_db_num_rows($check_query);
        }
        return $this->_check;
    }

    public function install()
    {
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable Credit Card Module', 'MODULE_PAYMENT_CC_STATUS', 'True', 'Do you want to accept credit card payments?', '6', '0', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Split Credit Card E-Mail Address', 'MODULE_PAYMENT_CC_EMAIL', '', 'If an e-mail address is entered, the middle digits of the credit card number will be sent to the e-mail address (the outside digits are stored in the database with the middle digits censored)', '6', '0', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort order of display.', 'MODULE_PAYMENT_CC_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '0' , now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Payment Zone', 'MODULE_PAYMENT_CC_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', '6', '2', 'tep_get_zone_class_title', 'tep_cfg_pull_down_zone_classes(', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Order Status', 'MODULE_PAYMENT_CC_ORDER_STATUS_ID', '0', 'Set the status of orders made with this payment module to this value', '6', '0', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");
    }

    public function remove()
    {
        tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }

    public function keys()
    {
        return ['MODULE_PAYMENT_CC_STATUS', 'MODULE_PAYMENT_CC_EMAIL', 'MODULE_PAYMENT_CC_ZONE', 'MODULE_PAYMENT_CC_ORDER_STATUS_ID', 'MODULE_PAYMENT_CC_SORT_ORDER'];
    }
}
