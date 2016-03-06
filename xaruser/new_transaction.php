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
/**
 * Create a new item of the paymnts_transaction object
 *
 */

function payments_user_new_transaction()
{
    if (!xarSecurityCheck('AddPayments')) return;

    if (!xarVarFetch('confirm',      'bool',   $data['confirm'], false,     XARVAR_NOT_REQUIRED)) return;
    if (!xarVarFetch('payment_type', 'str',    $data['payment_type'],'827',     XARVAR_NOT_REQUIRED)) return;
    
# --------------------------------------------------------
#
# Get the payment transactions object
#
    if (!xarVarFetch('name',         'str',    $name,            'payments_transactions', XARVAR_NOT_REQUIRED)) return;

    sys::import('modules.dynamicdata.class.objects.master');
    $data['object'] = DataObjectMaster::getObject(array('name' => $name));
    $data['object']->properties['payment_type']->setValue($data['payment_type']);
    $data['tplmodule'] = 'payments';
    $data['authid'] = xarSecGenAuthKey('payments');

# --------------------------------------------------------
#
# Check if we are passing an api item
#
    if (!xarVarFetch('api',          'str',    $api,            '', XARVAR_NOT_REQUIRED)) return;
    
    if (!empty($api)) {
        $function = rawurldecode($api);
        eval("\$info = $function;");
        
        foreach ($info as $key => $value) {
            if (isset($data['object']->properties[$key]))
                $data['object']->properties[$key]->value = $value;
        }
    }

# --------------------------------------------------------
#
# Get the debit account information
#
    $data['debit_account'] = DataObjectMaster::getObject(array('name' => 'payments_debit_account'));
    $q = $data['debit_account']->dataquery;
    $q->eq('sender_object', $info['sender_object']);
    $q->eq('sender_itemid', $info['sender_itemid']);
    $items = $data['debit_account']->getItems();

    if(empty($items)) {
        return xarTpl::module('payments','user','errors',array('layout' => 'no_sender'));
    }
    
    $item = current($items);
    $data['object']->properties['sender_account']->value = $item['account_holder'];
    $data['object']->properties['sender_line_1']->value  = $item['address_1'];
    $data['object']->properties['sender_line_2']->value  = $item['address_2'];
    $data['object']->properties['sender_line_3']->value  = $item['address_3'];
    $data['object']->properties['sender_line_4']->value  = $item['address_4'];

# --------------------------------------------------------
#
# The create button was clicked
#
    if ($data['confirm']) {
    
        // we only retrieve 'preview' from the input here - the rest is handled by checkInput()
        if(!xarVarFetch('preview', 'str', $preview,  NULL, XARVAR_DONT_SET)) {return;}

        // Check for a valid confirmation key
        if(!xarSecConfirmAuthKey()) return;
        
        // Get the data from the form
        $isvalid = $data['object']->checkInput();
        
        if (!$isvalid) {
            // Bad data: redisplay the form with error messages
            return xarTplModule('payments','user','new_transaction', $data);        
        } else {
            // Good data: create the item
            $itemid = $data['object']->createItem();
            
            // Jump to the next page
            xarController::redirect(xarModURL('payments','user','view_transaction'));
            return true;
        }
    }
    return $data;
}
?>