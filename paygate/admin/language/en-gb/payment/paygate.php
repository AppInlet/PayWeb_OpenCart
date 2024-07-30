<?php
/*
 * Copyright (c) 2024 Payfast (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

// Heading
$_['heading_title'] = 'Paygate';

// Text
$_['text_payment']   = 'Payment';
$_['text_success']   = 'You have successfully configured the Paygate payment module.';
$_['text_edit']      = 'Edit Paygate';
$_['text_extension'] = 'Extensions';
$_['text_notify']    = 'Disabled';
$_['text_redirect']  = 'Enabled';
$_['text_testmode']  = 'Enabled';
$_['text_livemode']  = 'Disabled';
$_['text_methodyes'] = 'Enable Payment Type';
$_['text_methodno']  = 'Disable Payment Type';

// Entry
$_['entry_total']              = 'Total';
$_['entry_order_status']       = 'Initial';
$_['entry_geo_zone']           = 'Geo Zone';
$_['entry_status']             = 'Status';
$_['entry_sort_order']         = 'Sort Order';
$_['text_paygate']             = '<a onclick="window.open(\'https://www.paygate.co.za/\');">
<img src="/extension/paygate/admin/view/image/payment/paygate.png"
alt="Paygate" title="Paygate" style="border: 1px solid #EEEEEE;" /></a>';
$_['entry_merchant_id']        = 'Paygate ID';
$_['entry_merchant_key']       = 'Encryption Key';
$_['entry_success_status']     = 'Successful';
$_['entry_failed_status']      = 'Failed';
$_['entry_cancelled_status']   = 'Cancelled';
$_['entry_notify_redirect']    = 'Disable IPN';
$_['entry_testmode']           = 'Test Mode';
$_['entry_creditcardmethod']   = 'Enable Card on Checkout';
$_['entry_banktransfermethod'] = 'Enable SiD Secure EFT on Checkout';
$_['entry_zappermethod']       = 'Enable Zapper on Checkout';
$_['entry_snapscanmethod']     = 'Enable SnapScan on Checkout';
$_['entry_paypalmethod']       = 'Enable PayPal on Checkout';
$_['entry_mobicredmethod']     = 'Enable Mobicred on Checkout';
$_['entry_momopaymethod']      = 'Enable MoMoPay on Checkout';
$_['entry_scantopaymethod']    = 'Enable ScanToPay on Checkout';
$_['entry_rcsmethod']          = 'Enable RCS on Checkout';
$_['entry_applepaymethod']     = 'Enable Apple Pay on Checkout';
$_['entry_samsungpaymethod']   = 'Enable Samsung Pay on Checkout';

// Tab
$_['tab_general']      = 'General';
$_['tab_order_status'] = 'Order Status';
$_['tab_pay_methods']  = 'Payment Types';

// Help
$_['help_total'] = 'The checkout total the order must reach before this payment method becomes active.';

// Error
$_['error_permission'] = 'Warning: You do not have permission to modify the Paygate payment method!';
