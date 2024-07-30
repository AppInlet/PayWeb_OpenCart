<?php
/*
 * Copyright (c) 2024 Payfast (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace Opencart\Catalog\Model\Extension\Paygate\Payment;

use Opencart\System\Engine\Model;

class Paygate extends Model
{

    public function getMethods($address, $total = null)
    {
        $this->load->language('extension/paygate/payment/paygate');

        if ($this->config->get('payment_paygate_title') == "") {
            $title = $this->language->get('text_title');
        } else {
            $title = $this->config->get('payment_paygate_title');
        }

        $method_data = array();

        $option_data['paygate'] = [
            'code' => 'paygate.paygate',
            'name' => $this->language->get('text_title')
        ];


        $method_data = array();


        $method_data = array(
            'code'       => 'paygate',
            'name'       => $title,
            'sort_order' => $this->config->get('payment_paygate_sort_order'),
            'option'     => $option_data,

        );


        // Add enabled payment methods as checkout options
        $paymethods = [
            'creditcardmethod'   => 'Card',
            'banktransfermethod' => 'SiD Secure EFT',
            'zappermethod'       => 'Zapper',
            'snapscanmethod'     => 'SnapScan',
            'paypalmethod'       => 'PayPal',
            'mobicredmethod'     => 'Mobicred',
            'momopaymethod'      => 'MoMoPay',
            'scantopaymethod'    => 'ScanToPay',
        ];
        $pm         = [];
        foreach ($paymethods as $key => $paymethod) {
            $setting = 'payment_paygate_' . $key;
            if ($this->config->get($setting) === 'yes') {
                $pm[] = ['method' => $key, 'title' => $paymethod];
            }
        }
        $method_data['pay_methods'] = $pm;

        return $method_data;
    }
}
