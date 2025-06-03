<?php

/*
 * Copyright (c) 2025 Payfast (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace Opencart\Admin\Controller\Extension\Paygate\Payment;

use Opencart\System\Engine\Controller;

/**
 *
 */
class Paygate extends Controller
{
	public const PAYMENT_URL      = 'marketplace/extension';
	public const PAYGATE_LANGUAGE = 'extension/paygate/payment/paygate';

    private array $error = [];
    private string $tableName = DB_PREFIX . 'paygate_transaction';

    /**
     * Setup db table for paygate transactions
     *
     * @return void
     */
    public function install(): void
    {
        $query = <<<QUERY
create table if not exists $this->tableName (
    paygate_transaction_id int auto_increment primary key,
    customer_id int not null,
    order_id int not null,
    paygate_reference varchar(255) not null,
    paygate_data text null,
    paygate_session text null,
    date_created datetime not null,
    date_modified datetime not null
)
QUERY;

        $this->db->query($query);

        $this->load->model('setting/setting');

        $this->model_setting_setting->editValue('config', 'config_session_samesite', 'Lax');
    }

    /**
     * Drop table
     *
     * @return void
     */
    public function uninstall(): void
    {
        $this->db->query("drop table if exists $this->tableName");
    }

	/**
	 * @return mixed
	 */
	public function getToken(): mixed
	{
        return $this->session->data['user_token'];
    }

	/**
	 * @param $path
	 *
	 * @return mixed
	 */
	public function formatPaymentUrl($path): mixed
	{
        $token = $this->getToken();

        return $this->url->link(
            $path,
            'user_token=' . $token . '&type=payment',
            true
        );
    }

	/**
	 * @param $path
	 *
	 * @return mixed
	 */
	public function formatUrl($path): mixed
	{
        $token = $this->getToken();

        return $this->url->link(
            $path,
            "user_token=$token",
            true
        );
    }

	/**
	 * @return void
	 */
	public function index(): void
	{
        $this->load->language(self::PAYGATE_LANGUAGE);
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('localisation/order_status');
        $this->load->model('localisation/geo_zone');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('payment_paygate', $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
            $url                            = $this->formatPaymentUrl(self::PAYMENT_URL);
            $this->response->redirect($url);
        }

        $data['heading_title']          = $this->language->get('heading_title');
        $data['text_edit']              = $this->language->get('text_edit');
        $data['text_enabled']           = $this->language->get('text_enabled');
        $data['text_disabled']          = $this->language->get('text_disabled');
        $data['text_all_zones']         = $this->language->get('text_all_zones');
        $data['entry_order_status']     = $this->language->get('entry_order_status');
        $data['entry_success_status']   = $this->language->get('entry_success_status');
        $data['entry_failed_status']    = $this->language->get('entry_failed_status');
        $data['entry_cancelled_status'] = $this->language->get('entry_cancelled_status');
        $data['entry_total']            = $this->language->get('entry_total');
        $data['entry_geo_zone']         = $this->language->get('entry_geo_zone');
        $data['entry_status']           = $this->language->get('entry_status');
        $data['entry_sort_order']       = $this->language->get('entry_sort_order');
        $data['entry_notify_redirect']  = $this->language->get('entry_notify_redirect');
        $data['tab_general']            = $this->language->get('tab_general');
        $data['tab_order_status']       = $this->language->get('tab_order_status');
        $data['entry_merchant_id']      = $this->language->get('entry_merchant_id');
        $data['entry_merchant_key']     = $this->language->get('entry_merchant_key');
        $data['help_total']             = $this->language->get('help_total');
        $data['button_save']            = $this->language->get('button_save');
        $data['button_cancel']          = $this->language->get('button_cancel');
        $data['error_warning']          = isset($this->error['warning']) ? $data['error_warning'] : '';
        $data['breadcrumbs']            = [];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->formatUrl('common/dashboard'),
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_extension'),
            'href' => $this->formatPaymentUrl(self::PAYMENT_URL),
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->formatUrl(self::PAYGATE_LANGUAGE),
        ];

        $data['action']                                    = $this->formatUrl(self::PAYGATE_LANGUAGE);
        $data['cancel']                                    = $this->formatPaymentUrl(self::PAYMENT_URL);
        $data['payment_paygate_total']                     = $this->checkPostValue('payment_paygate_total');
        $data['payment_paygate_order_status_id']           = $this->checkPostValue('payment_paygate_order_status_id');
        $data['payment_paygate_success_order_status_id']   = $this->checkPostValue(
	        'payment_paygate_success_order_status_id'
        );
        $data['payment_paygate_failed_order_status_id']    = $this->checkPostValue(
	        'payment_paygate_failed_order_status_id'
        );
        $data['payment_paygate_cancelled_order_status_id'] = $this->checkPostValue(
	        'payment_paygate_cancelled_order_status_id'
        );
        $data['order_statuses']                            = $this->model_localisation_order_status->getOrderStatuses();
        $data['geo_zones']                                 = $this->model_localisation_geo_zone->getGeoZones();
        $data['payment_paygate_status']                    = $this->checkPostValue('payment_paygate_status');
        $data['payment_paygate_sort_order']                = $this->checkPostValue('payment_paygate_sort_order');
        $data['payment_paygate_merchant_id']               = $this->checkPostValue('payment_paygate_merchant_id');
        $data['payment_paygate_merchant_key']              = $this->checkPostValue('payment_paygate_merchant_key');
        $data['payment_paygate_notifyredirect']            = $this->checkPostValue('payment_paygate_notifyredirect');
        $data['payment_paygate_creditcardmethod']          = $this->checkPostValue('payment_paygate_creditcardmethod');
        $data['payment_paygate_banktransfermethod']        = $this->checkPostValue(
	        'payment_paygate_banktransfermethod'
        );
        $data['payment_paygate_zappermethod']              = $this->checkPostValue('payment_paygate_zappermethod');
        $data['payment_paygate_snapscanmethod']            = $this->checkPostValue('payment_paygate_snapscanmethod');
        $data['payment_paygate_paypalmethod']              = $this->checkPostValue('payment_paygate_paypalmethod');
        $data['payment_paygate_mobicredmethod']            = $this->checkPostValue('payment_paygate_mobicredmethod');
        $data['payment_paygate_momopaymethod']             = $this->checkPostValue('payment_paygate_momopaymethod');
        $data['payment_paygate_geo_zone_id']               = $this->checkPostValue('payment_paygate_geo_zone_id');
        $data['payment_paygate_scantopaymethod']           = $this->checkPostValue('payment_paygate_scantopaymethod');
        $data['payment_paygate_rcsmethod']                 = $this->checkPostValue('payment_paygate_rcsmethod');
        $data['payment_paygate_applepaymethod']            = $this->checkPostValue('payment_paygate_applepaymethod');
        $data['payment_paygate_samsungpaymethod']          = $this->checkPostValue('payment_paygate_samsungpaymethod');
        $data['header']                                    = $this->load->controller('common/header');
        $data['column_left']                               = $this->load->controller('common/column_left');
        $data['footer']                                    = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view(self::PAYGATE_LANGUAGE, $data));
    }

	/**
	 * @param $var
	 *
	 * @return mixed
	 */
	public function checkPostValue($var): mixed
	{
        return isset($this->request->post["$var"]) ? $this->request->post["$var"] : $this->config->get("$var");
    }

	/**
	 * @return bool
	 */
	protected function validate(): bool
	{
        if (!$this->user->hasPermission('modify', self::PAYGATE_LANGUAGE)) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        return !$this->error;
    }
}
