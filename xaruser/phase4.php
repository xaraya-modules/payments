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
    define('MODULE_PAYMENT_IPAYMENT_CC_ERROR_HEADING', 'There has been an error processing your credit card');
    define('MODULE_PAYMENT_IPAYMENT_CC_ERROR_MESSAGE', 'Please check your credit card details!');
    function payments_user_phase4()
    {
        //Psspl:Added the Read privilages.
        if (!xarSecurityCheck('ReadPayments')) return;
        
        //Psspl:Implemented the code for return url.
        //if(!xarVarFetch('return_url', 'array', $data['return_url'],  NULL, XARVAR_DONT_SET)) {return;}
        if(!xarVarFetch('allowEdit_Payment', 'int', $data['allowEdit_Payment'],   null,    XARVAR_DONT_SET)) {return;}
        
        /*
        //Psspl:Implemented the code for return url.
        $return_url_property = DataPropertyMaster::getProperty(array('name' => 'array'));       
        $return_url_property->initialization_associative_array = 1;         
        $return_url_property->checkInput('return_url');
        $data['return_url'] = $return_url_property->value;
        */
        $return_url = xarSession::getVar('return_url');
        try {
            $data['return_url'] = unserialize($return_url);
        } catch (Exception $e) {
            $data['return_url'] = array();
        }
        
        
        //Psspl: modified the code for return url.
        $data['return_url'] = unserialize(xarSession::GetVar('return_url'));
        xarSession::delVar('return_url'); 
        
        // Check for demo mode 
        $demousers = unserialize(xarModVars::get('payments','demousers'));
        if (xarModVars::get('payments','enable_demomode') && in_array(xarUserGetVar('uname'),$demousers)) {
            $data['status'] = xarML('A simulated payment has been completed');
            if(!empty($data['return_url']['success_return_link'])){
                xarController::redirect($data['return_url']['success_return_link']);          
                return true;
            } 
            return $data;
        }

        $gateway = xarSession::getVar('gateway');
        if($gateway == "iPayment_cc") {
            if (!xarVarFetch('trx_currency', 'str', $trx_currency, "", XARVAR_DONT_SET)) {return;}
            if (!xarVarFetch('trx_paymenttyp', 'str', $trx_paymenttyp, "", XARVAR_DONT_SET)) {return;}
            if (!xarVarFetch('addr_name', 'str', $addr_name, "", XARVAR_DONT_SET)) {return;}
            if (!xarVarFetch('trx_typ', 'str', $trx_typ, "", XARVAR_DONT_SET)) {return;}
            if (!xarVarFetch('trx_amount', 'str', $trx_amount, "", XARVAR_DONT_SET)) {return;}
            if (!xarVarFetch('ret_errorcode', 'str', $ret_errorcode, "", XARVAR_DONT_SET)) {return;}
            if (!xarVarFetch('ret_fatalerror', 'str', $ret_fatalerror, "", XARVAR_DONT_SET)) {return;}          
            if (!xarVarFetch('ret_errormsg', 'str', $ret_errormsg, "", XARVAR_DONT_SET)) {return;}
            if (!xarVarFetch('ret_additionalmsg', 'str', $ret_additionalmsg, "", XARVAR_DONT_SET)) {return;}                                                                                            
            if (!xarVarFetch('ret_ip', 'str', $ret_ip, "", XARVAR_DONT_SET)) {return;}
            if (!xarVarFetch('redirect_needed', 'str', $redirect_needed, "", XARVAR_DONT_SET)) {return;}                                                                                            
            if (!xarVarFetch('ret_status', 'str', $ret_status, "", XARVAR_DONT_SET)) {return;}      
            if (!xarVarFetch('trxuser_id', 'str', $trxuser_id, "", XARVAR_DONT_SET)) {return;}
            if (!xarVarFetch('trx_currency', 'str', $trx_currency, "", XARVAR_DONT_SET)) {return;}          
            if (!xarVarFetch('ret_transdate', 'str', $ret_transdate, "", XARVAR_DONT_SET)) {return;}
            if (!xarVarFetch('ret_transtime', 'str', $ret_transtime, "", XARVAR_DONT_SET)) {return;}                                                                                            
            if (!xarVarFetch('ret_authcode', 'str', $ret_authcode, "", XARVAR_DONT_SET)) {return;}
            if (!xarVarFetch('ret_booknr', 'str', $ret_booknr, "", XARVAR_DONT_SET)) {return;}                                                                                          
            if (!xarVarFetch('ret_trx_number', 'str', $ret_trx_number, "", XARVAR_DONT_SET)) {return;}
            if (!xarVarFetch('trx_paymentmethod', 'str', $trx_paymentmethod, "", XARVAR_DONT_SET)) {return;}                                                                                            
            if (!xarVarFetch('trx_paymentdata_country', 'str', $trx_paymentdata_country, "", XARVAR_DONT_SET)) {return;}
            if (!xarVarFetch('trx_remoteip_country', 'str', $trx_remoteip_country, "", XARVAR_DONT_SET)) {return;}                                                                                                          
            //Psspl:Added the code for Error handling.
            if($ret_errorcode!='0'){
                $error_message ="<B>".MODULE_PAYMENT_IPAYMENT_CC_ERROR_HEADING;
                $error_message.="</B><br /><table border='0.5' width='100%' bgcolor='#160'><tr><td width=100%'>";
                $error_message.=MODULE_PAYMENT_IPAYMENT_CC_ERROR_MESSAGE."<br>";
                //$error_message.=  $ret_errormsg."<br>";
                $error_message.=  $ret_additionalmsg."</td></tr></table>";
                xarSession::setVar('error_message' , $error_message);
                //Psspl: modified the code for allowEdit_payment.
                xarController::redirect(xarModURL('payments', 'user', 'onestep',array('paymentmethod'=>$trx_paymentmethod,'MakeChanges'=>1,'errorFlag'=>1 , 'allowEdit_Payment' => $data['allowEdit_Payment'])));
                return true;
            } else { 
                $output = '<table cellpadding=\"5\" cellspacing=\"0\" border=\"1\">';
                $output .= "<tr>";
                $output .= "<td class=\"e\">trx_currency: </td>";
                $output .= "<td class=\"v\">" . $trx_currency. "</td>";
                $output .= "</tr>";
                $output .= "<tr>";
                $output .= "<td class=\"e\">trx_paymenttyp: </td>";
                $output .= "<td class=\"v\">" . $trx_paymenttyp. "</td>";
                $output .= "</tr>";
                $output .= "<tr>";
                $output .= "<td class=\"e\">addr_name: </td>";
                $output .= "<td class=\"v\">" . $addr_name. "</td>";
                $output .= "</tr>";
                $output .= "<tr>";
                $output .= "<td class=\"e\">trx_typ: </td>";
                $output .= "<td class=\"v\">" . $trx_typ. "</td>";
                $output .= "</tr>";
                $output .= "<tr>";
                $output .= "<td class=\"e\">trx_amount: </td>";
                $output .= "<td class=\"v\">" . $trx_amount. "</td>";
                $output .= "</tr>";
                $output .= "<tr>";
                $output .= "<td class=\"e\">ret_errorcode: </td>";
                $output .= "<td class=\"v\">" . $ret_errorcode. "</td>";
                $output .= "</tr>";
                $output .= "<tr>";
                $output .= "<td class=\"e\">ret_fatalerror: </td>";
                $output .= "<td class=\"v\">" . $ret_fatalerror. "</td>";
                $output .= "</tr>";
                $output .= "<tr>";
                $output .= "<td class=\"e\">ret_errormsg: </td>";
                $output .= "<td class=\"v\">" . $ret_errormsg. "</td>";
                $output .= "</tr>";
                $output .= "<tr>";
                $output .= "<td class=\"e\">ret_additionalmsg: </td>";
                $output .= "<td class=\"v\">" . $ret_additionalmsg. "</td>";
                $output .= "</tr>";
                $output .= "<tr>";
                $output .= "<td class=\"e\">ret_ip: </td>";
                $output .= "<td class=\"v\">" . $ret_ip. "</td>";
                $output .= "</tr>";
                $output .= "<tr>";
                $output .= "<td class=\"e\">redirect_needed: </td>";
                $output .= "<td class=\"v\">" . $redirect_needed. "</td>";
                $output .= "</tr>";
                $output .= "<tr>";
                $output .= "<td class=\"e\">ret_status: </td>";
                $output .= "<td class=\"v\">" . $ret_status. "</td>";
                $output .= "</tr>";
                $output .= "<tr>";
                $output .= "<td class=\"e\">trxuser_id: </td>";
                $output .= "<td class=\"v\">" . $trxuser_id. "</td>";
                $output .= "</tr>";
                /*Psspl:Removed the already defineded statement.
                $output .= "<tr>";
                $output .= "<td class=\"e\">trx_currency: </td>";
                $output .= "<td class=\"v\">" . $trx_currency. "</td>";
                $output .= "</tr>";
                */
                $output .= "<tr>";
                $output .= "<td class=\"e\">ret_transdate: </td>";
                $output .= "<td class=\"v\">" . $ret_transdate. "</td>";
                $output .= "</tr>";
                $output .= "<tr>";
                $output .= "<td class=\"e\">ret_transtime: </td>";
                $output .= "<td class=\"v\">" . $ret_transtime. "</td>";
                $output .= "</tr>";
                $output .= "<tr>";
                $output .= "<td class=\"e\">ret_authcode: </td>";
                $output .= "<td class=\"v\">" . $ret_authcode. "</td>";
                $output .= "</tr>";
                $output .= "<tr>";
                $output .= "<td class=\"e\">ret_booknr: </td>";
                $output .= "<td class=\"v\">" . $ret_booknr. "</td>";
                $output .= "</tr>";
                $output .= "<tr>";
                $output .= "<td class=\"e\">ret_trx_number: </td>";
                $output .= "<td class=\"v\">" . $ret_trx_number. "</td>";
                $output .= "</tr>";
                $output .= "<tr>";
                $output .= "<td class=\"e\">trx_paymentmethod: </td>";
                $output .= "<td class=\"v\">" . $trx_paymentmethod. "</td>";
                $output .= "</tr>";
                $output .= "<tr>";
                $output .= "<td class=\"e\">trx_paymentdata_country: </td>";
                $output .= "<td class=\"v\">" . $trx_paymentdata_country. "</td>";
                $output .= "</tr>";
                $output .= "<tr>";
                $output .= "<td class=\"e\">trx_remoteip_country: </td>";
                $output .= "<td class=\"v\">" . $trx_remoteip_country. "</td>";
                $output .= "</tr>";
                $output .= "</table>";
                $data['status'] = $output;
            }
        } else {
            
            $data['status'] = "No gateway found" . $gateway;
        }
        
         // Update the order information
        $data['orderobject'] = null;
        $orderobjectname = xarModVars::get('payments','orderobject');
        $orderobject = DataObjectMaster::getObject(array('name' => $orderobjectname));
        $fields = unserialize(xarSession::GetVar('orderfields'));
        $orderobject->setFieldValues($fields);
        $orderobject->updateItem(array('itemid' => $fields['id']));
        
        // Remove the session vars we used
        xarSession::setVar('orderfields',serialize(array()));
        xarSession::setVar('paymentfields',serialize(array()));
            
       if(!empty($data['return_url']['success_return'])){
            
            //Psspl:Implemented the code for calling success return API function.
            $success_return = explode("," , $data['return_url']['success_return']);
             
            xarModAPIFunc($success_return[0],$success_return[1],$success_return[2] ,array('status' => $data['status'] , 'success_return_link' => $data['return_url']['success_return_link']));
            
            return true;
        }
        //Psspl : Added code for success return link.
        if(!empty($data['return_url']['success_return_link'])){
            xarController::redirect($data['return_url']['success_return_link']);          
            return true;
        } 
        return $data;
    }
?>
