<?php

/*
 * Copyright (c) 2025 Payfast (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace Opencart\Catalog\Controller\Extension\Paygate\Payment;

use DateTime;
use Exception;
use Opencart\System\Engine\Controller;
use Opencart\System\Library\Cart\Customer;
use Payfast\PayfastCommon\Gateway\Request\PaymentRequest;

require_once __DIR__ . '/../../../system/library/vendor/autoload.php';

/**
 *
 */
class Paygate extends Controller
{
	public const CHECKOUT_MODEL = 'checkout/order';
	public const INFORMATION_CONTACT = 'information/contact';
	public const PAYGATE_CODE        = 'paygate.paygate';

    protected string $testmode;
    private string $tableName = DB_PREFIX . 'paygate_transaction';

	/**
	 * @return array
	 */
	public function getPaymentMethods(): array
	{
        // Add enabled payment methods as checkout options
        $imgs       = 'extension/paygate/catalog/view/image/payment/';
        $paymethods = [
            'creditcardmethod'   => [
                'title' => 'Card',
                'img'   => $imgs . 'mastercard-visa.svg',
            ],
            'banktransfermethod' => [
                'title' => 'SiD Secure EFT',
                'img'   => $imgs . 'sid.svg',
            ],
            'zappermethod'       => [
                'title' => 'Zapper',
                'img'   => $imgs . 'zapper.svg',
            ],
            'snapscanmethod'     => [
                'title' => 'SnapScan',
                'img'   => $imgs . 'snapscan.svg',
            ],
            'paypalmethod'       => [
                'title' => 'PayPal',
                'img'   => $imgs . 'paypal.svg',
            ],
            'mobicredmethod'     => [
                'title' => 'Mobicred',
                'img'   => $imgs . 'mobicred.svg',
            ],
            'momopaymethod'      => [
                'title' => 'MoMoPay',
                'img'   => $imgs . 'momopay.svg',
            ],
            'scantopaymethod'    => [
                'title' => 'ScanToPay',
                'img'   => $imgs . 'scan-to-pay.svg',
            ],
            'rcsmethod'          => [
                'title' => 'RCS',
                'img'   => $imgs . 'rcs.svg',
            ],
            'applepaymethod'     => [
                'title' => 'Apple Pay',
                'img'   => $imgs . 'apple-pay.svg',
            ],
            'samsungpaymethod'   => [
                'title' => 'Samsung Pay',
                'img'   => $imgs . 'samsung-pay.svg',
            ],
        ];
        $pms        = [];
        foreach ($paymethods as $key => $paymethod) {
            $setting = 'payment_paygate_' . $key;
            if ($this->config->get($setting) === 'yes') {
                $pms[] = ['method' => $key, 'title' => $paymethod['title'], 'img' => $paymethod['img']];
            }
        }

        return $pms;
    }

	/**
	 * @return array
	 */
	public function getPayMethodDetails(): array
	{
        $data       = [];
        $PAY_METHOD = 'EW';
        switch ($_POST['paygate_pay_method']) {
            case 'creditcardmethod';
                $PAY_METHOD        = 'CC';
                $PAY_METHOD_DETAIL = 'pw3_credit_card';
                break;
            case 'banktransfermethod':
                $PAY_METHOD        = 'BT';
                $PAY_METHOD_DETAIL = 'SID';
                break;
            case 'zappermethod':
                $PAY_METHOD_DETAIL = 'Zapper';
                break;
            case 'snapscanmethod':
                $PAY_METHOD_DETAIL = 'SnapScan';
                break;
            case 'paypalmethod':
                $PAY_METHOD_DETAIL = 'PayPal';
                break;
            case 'mobicredmethod':
                $PAY_METHOD_DETAIL = 'Mobicred';
                break;
            case 'momopaymethod':
                $PAY_METHOD_DETAIL = 'Momopay';
                break;
            case 'scantopaymethod':
                $PAY_METHOD_DETAIL = 'MasterPass';
                break;
            case 'rcsmethod':
                $PAY_METHOD        = 'CC';
                $PAY_METHOD_DETAIL = 'RCS';
                break;
            case 'applepaymethod':
                $PAY_METHOD        = 'CC';
                $PAY_METHOD_DETAIL = 'Applepay';
                break;
            case 'samsungpaymethod':
                $PAY_METHOD_DETAIL = 'Samsungpay';
                break;
            default:
                $PAY_METHOD_DETAIL = $_POST['paygate_pay_method'];
                break;
        }
        $data['PAY_METHOD']        = $PAY_METHOD;
        $data['PAY_METHOD_DETAIL'] = $PAY_METHOD_DETAIL;

        return $data;
    }

	/**
	 * @return string
	 */
	public function getCurrency(): string
	{
        if ($this->config->get('config_currency') != '') {
            $currency = htmlspecialchars($this->config->get('config_currency'));
        } else {
            $currency = htmlspecialchars($this->currency->getCode());
        }

        return $currency;
    }

    /**
     * @return string
     */
    private function getNotifyUrl(): string
    {
        $notifyUrl = '';
        if ($this->config->get('payment_paygate_notifyredirect') === 'notify') {
            $notifyUrl = filter_var(
                $this->url->link('extension/paygate/payment/paygate|notify_handler', '', true),
                FILTER_SANITIZE_URL
            );
        }

        return $notifyUrl;
    }

	/**
	 * @param $order_info
	 * @param $pay_method_data
	 *
	 * @return array
	 */
	public function initiate_data($order_info, $pay_method_data): array
	{
        $doVault        = '';
        $vaultID        = '';

        if (isset($pay_method_data['PAY_METHOD'])) {
            $PAY_METHOD        = $pay_method_data['PAY_METHOD'];
            $PAY_METHOD_DETAIL = $pay_method_data['PAY_METHOD_DETAIL'];
        }

        /* getting order info ********/

        $preAmount = number_format($order_info['total'], 2, '', '');
        $reference = htmlspecialchars($order_info['order_id']);
        $amount    = filter_var($preAmount, FILTER_SANITIZE_NUMBER_INT);
        $currency  = $this->getCurrency();

        $returnUrl = filter_var(
            $this->url->link('extension/paygate/payment/paygate|paygate_return', '', true),
            FILTER_SANITIZE_URL
        );
        $transDate = date('Y-m-d H:i:s');
        $locale    = 'en';
        $country   = !$order_info['payment_iso_code_3']
            ? $order_info['shipping_iso_code_3'] : $order_info['payment_iso_code_3'];
        $email     = filter_var($order_info['email'], FILTER_SANITIZE_EMAIL);

        // Check if email empty due to some custom themes displaying this on the same page
        $email           = empty($email) ? $this->config->get('config_email') : $email;
        $payMethod       = $PAY_METHOD ?? '';
        $payMethodDetail = $PAY_METHOD_DETAIL ?? '';

        // Add notify if enabled
        $notifyUrl  = $this->getNotifyUrl();
        $userField1 = $order_info['customer_id'];
        $firstName  = !$order_info['payment_firstname']
            ? $order_info['shipping_firstname'] : $order_info['payment_firstname'];
        $lastName   = !$order_info['payment_lastname']
            ? $order_info['shipping_lastname'] : $order_info['payment_lastname'];
        $userField2 = "$firstName $lastName";
        $userField3 = 'opencart-v4.x';

        $initiateData = [
            'REFERENCE'         => $reference,
            'AMOUNT'            => $amount,
            'CURRENCY'          => $currency,
            'RETURN_URL'        => $returnUrl,
            'TRANSACTION_DATE'  => $transDate,
            'LOCALE'            => $locale,
            'COUNTRY'           => $country,
            'EMAIL'             => $email,
            'PAY_METHOD'        => $payMethod,
            'PAY_METHOD_DETAIL' => $payMethodDetail,
        ];
        if ($this->config->get('payment_paygate_notifyredirect') === 'notify') {
            $initiateData['NOTIFY_URL'] = $notifyUrl;
        }
        $initiateData['USER1']    = $userField1; // Used for customer id
        $initiateData['USER2']    = $userField2;
        $initiateData['USER3']    = $userField3;
        $initiateData['VAULT']    = $doVault;
        $initiateData['VAULT_ID'] = $vaultID;
		// Filter out empty values
		$initiateData = array_filter($initiateData, fn($value) => $value !== null && $value !== '');

        return $initiateData;
    }

	/**
	 * @return string
	 */
	public function getPaygateId(): string
	{
        $this->testmode = $this->config->get('payment_paygate_testmode') === 'test';

        return $this->testmode ? '10011072130' : htmlspecialchars(
            $this->config->get('payment_paygate_merchant_id')
        );
    }

	/**
	 * @return string
	 */
	public function getEncryptionkey(): string
	{
        $this->testmode = $this->config->get('payment_paygate_testmode') === 'test';

        return $this->testmode ? 'secret' : $this->config->get('payment_paygate_merchant_key');
    }

    /**
     * Entry point from OC checkout
     *
     */
	public function index()
	{
		unset($this->session->data['REFERENCE']);

		$dateTime = new DateTime();
		$time     = $dateTime->format('YmdHis');

		$data['text_loading']   = $this->language->get('text_loading');
		$data['button_confirm'] = $this->language->get('button_confirm');
		$data['continue']       = $this->language->get('payment_url');

		$pay_method_data = [];
		$this->load->model(self::CHECKOUT_MODEL);

		if (isset($this->session->data['order_id']) && is_numeric($this->session->data['order_id'])) {
			$order_info = $this->model_checkout_order->getOrder((int)$this->session->data['order_id']);
		} else {
			// Handle missing or invalid order_id
			$order_info = null;
			// Log or throw an error
			$this->log->write('Warning: Missing or invalid order_id in session data.');
		}

		// Handle payment methods list
		if (empty($_POST) && $order_info['payment_method']['code'] === self::PAYGATE_CODE) {
			$pms = $this->getPaymentMethods();
			if (!empty($pms)) {
				return $this->load->view(
					'extension/paygate/payment/paygate_payment_method',
					[
						'pay_methods' => $pms,
						'action'      => $this->url->link(
							'extension/paygate/payment/paygate|index',
							'',
							true
						),
					]
				);
			}
		} elseif (isset($_POST['paygate_pay_method']) && $_POST['paygate_pay_method'] !== '') {
			$pay_method_data = $this->getPayMethodDetails();
		}

		// Handle order and payment initiation
		if ($order_info) {
			$initiateData = $this->initiate_data($order_info, $pay_method_data);
			$paygateID      = $this->getPaygateId();
			$encryption_key = $this->getEncryptionkey();

			$paymentRequest = new PaymentRequest($paygateID, $encryption_key);
			$response       = $paymentRequest->initiate($initiateData);

			$result = [];
			parse_str($response, $result);

			if (isset($result['ERROR'])) {
				return $this->displayError(
					'Error trying to initiate a transaction, paygate error code: ' .
					$result['ERROR']
				);
			}

			$data['CHECKSUM']       = $result['CHECKSUM'];
			$data['PAY_REQUEST_ID'] = $result['PAY_REQUEST_ID'];

			$this->session->data['REFERENCE'] = $time;

			// Handle Paygate processing
			if ($order_info['payment_method']['code'] === self::PAYGATE_CODE) {
				$this->savePaygateTransaction($order_info, $result);
				$htmlForm = $paymentRequest->getRedirectHTML($result['PAY_REQUEST_ID'], $result['CHECKSUM']);
				$this->cart->clear();

				$this->renderHtmlForm($htmlForm);
			}
		} else {
			return $this->displayError('Order could not be found, order_id: ' . $this->session->data['order_id']);
		}

		// Default view
		return $this->load->view('extension/paygate/payment/paygate_redirect', $data);
	}

	/**
	 * Display error message and stop further execution.
	 */
	private function displayError($message): string
	{
		return '<p>' . $message . '. Log support ticket to <a href="' . $this->url->link(self::INFORMATION_CONTACT) . '">shop owner</a></p>';
	}

	/**
	 * Save Paygate transaction data.
	 */
	private function savePaygateTransaction($order_info, $result): void
	{
		$paygateData    = serialize($order_info);
		$paygateSession = [
			'customer'   => $this->customer,
			'customerId' => $order_info['customer_id'],
		];
		$paygateSession = base64_encode(serialize($paygateSession));
		$createDate     = date('Y-m-d H:i:s');
		$query          = <<<QUERY
INSERT INTO $this->tableName (customer_id, order_id, paygate_reference, paygate_data, paygate_session, date_created, date_modified)
VALUES (
    '{$order_info['customer_id']}',
    '{$order_info['order_id']}',
    '{$result['PAY_REQUEST_ID']}',
    '$paygateData',
    '$paygateSession',
    '$createDate',
    '$createDate'
)
QUERY;

		$this->db->query($query);
	}

	/**
	 * Render the Paygate HTML form.
	 */
	private function renderHtmlForm($htmlForm): void
	{
		echo <<<HTML
        $htmlForm
        <p style="text-align:center;">Redirecting you to Paygate...</p>
        <script type="text/javascript">document.getElementById("paygate_payment_form").submit();</script>
HTML;
	}


	/**
	 * @return int
	 */
	public function getOrderIdFromSession(): int
	{
        // Get order Id from query string as backup if session fails
        $m       = [];
        $orderId = 0;
        preg_match('/^.*\/(\d+)$/', $_GET['route'], $m);
        if (count($m) > 1) {
            $orderId = (int)$m[1];
        } elseif (isset($this->session->data['order_id'])) {
            $orderId = (int)$this->session->data['order_id'];
        }

        return $orderId;
    }

	/**
	 * @param $order
	 * @param $orderId
	 *
	 * @return void
	 */
	public function setActivityData($order, $orderId): void
	{
        if ($this->customer->isLogged()) {
            $activityData = [
                'customer_id' => $this->customer->getId(),
                'name'        => $this->customer->getFirstName() . ' ' . $this->customer->getLastName(),
                'order_id'    => $orderId,
            ];
            $this->model_account_activity->addActivity('order_account', $activityData);
        } else {
            $activityData = [
                'name'     => $order['firstname'] . ' ' . $order['lastname'],
                'order_id' => $orderId,
            ];
            $this->model_account_activity->addActivity('order_guest', $activityData);
        }
    }

	/**
	 * @param $result
	 * @param $useRedirect
	 * @param $payMethodDesc
	 *
	 * @return array
	 */
	public function mapPGData($result, $useRedirect, $payMethodDesc): array
	{
        $pgData         = [];
        $orderStatusId  = '7';
        $resultsComment = '';
        $status         = '';

        if (isset($result['TRANSACTION_STATUS'])) {
            $status = 'ok';

            if ($result['TRANSACTION_STATUS'] == 0) {
                $orderStatusId  = 1;
                $statusDesc     = 'pending';
                $resultsComment = 'Transaction status verification failed. No transaction status.
                 Please contact the shop owner to confirm transaction status.';
            } elseif ($result['TRANSACTION_STATUS'] == 1) {
                $orderStatusId  = $this->config->get('payment_paygate_success_order_status_id');
                $statusDesc     = 'approved';
                $resultsComment = 'Transaction Approved.';
            } elseif ($result['TRANSACTION_STATUS'] == 2) {
                $orderStatusId  = $this->config->get('payment_paygate_failed_order_status_id');
                $statusDesc     = 'declined';
                $resultsComment = 'Transaction Declined by PayWeb.';
            } elseif ($result['TRANSACTION_STATUS'] == 4) {
                $orderStatusId  = $this->config->get('payment_paygate_cancelled_order_status_id');
                $statusDesc     = 'cancelled';
                $resultsComment = 'Transaction Cancelled by User.';
            }
            if ($useRedirect) {
                $resultsComment = 'Redirect response from Paygate with a status of ' . $statusDesc . $payMethodDesc;
            }
        } else {
            $orderStatusId  = 1;
            $statusDesc     = 'pending';
            $resultsComment = 'Transaction status verification failed. No transaction status.
             Please contact the shop owner to confirm transaction status.';
        }

        $pgData['orderStatusId']  = $orderStatusId;
        $pgData['statusDesc']     = $statusDesc;
        $pgData['resultsComment'] = $resultsComment;
        $pgData['status']         = $status;

        return $pgData;
    }

    /**
     * Handles redirect response from Paygate
     * Is always received
     * Handle according to config setting for Notify/Redirect
     *
     * Must use part of this to get to correct checkout page in notify case,
     * but don't process the order
     */
    public function paygate_return(): void
    {
        $this->load->language('extension/paygate/checkout/paygate');
        $payRequestId      = htmlspecialchars($_POST['PAY_REQUEST_ID']);
        $transactionStatus = (int)$_POST['TRANSACTION_STATUS'];
        $checksum          = htmlspecialchars($_POST['CHECKSUM']);

        // Retrieve transaction record
        $record  = $this->db->query(
            "select * from $this->tableName where paygate_reference = '$payRequestId';"
        );
        $record  = $record?->rows[0];
        $orderId = $record['order_id'] ?? 0;
        $ps      = $record['paygate_session'];
        $pas     = base64_decode($ps);

        // Verify checksum
        $checkString = $this->getPaygateId() . $payRequestId . $transactionStatus . $orderId . $this->getEncryptionkey(
            );
        $ourChecksum = md5($checkString);

        $statusDesc = '';
        $status     = '';
        $result     = '';
        $r          = '';
        $error      = '';

        if (!hash_equals($checksum, $ourChecksum)) {
            $status = 'checksum_failed';
        }

        $useRedirect = $this->config->get('payment_paygate_notifyredirect') === 'redirect';

        $sessionOrderId = $this->session->data['order_id'] ?? 'Session data not set';
        if ($orderId !== 0) {
            // Add to activity log
            $this->load->model('account/activity');
            $this->load->model(self::CHECKOUT_MODEL);
            $order    = $this->model_checkout_order->getOrder($orderId);
            $products = $this->model_checkout_order->getProducts($orderId);

            $this->setActivityData($order, $orderId);
            $payMethodDesc = '';
            $respData      = $this->sendClientRequest($record);
            $result        = $respData['result'] ?? '';
            $r             = $respData['r'] ?? '';
            $error         = $respData['error'] ?? '';

            if (isset($result['PAY_METHOD_DETAIL']) && $result['PAY_METHOD_DETAIL'] != '') {
                $payMethodDesc = ', using a payment method of ' . $result['PAY_METHOD_DETAIL'];
            }

            // Mapping pg transactions status with open card statuses
            $pgData         = $this->mapPGData($result, $useRedirect, $payMethodDesc);
            $orderStatusId  = $pgData['orderStatusId'];
            $statusDesc     = $pgData['statusDesc'];
            $resultsComment = $pgData['resultsComment'];
            $status         = $pgData['status'];

            if ($statusDesc !== 'approved') {
                $this->restoreCart($products, $statusDesc, $orderId);
            }

            $this->model_checkout_order->addHistory(
                $orderId,
                $orderStatusId,
                $resultsComment,
                true
            );

            if ($useRedirect) {
                unset($this->session->data['shipping_method']);
                unset($this->session->data['shipping_methods']);
                unset($this->session->data['payment_method']);
                unset($this->session->data['payment_methods']);
                unset($this->session->data['guest']);
                unset($this->session->data['comment']);
                unset($this->session->data['order_id']);
                unset($this->session->data['coupon']);
                unset($this->session->data['reward']);
                unset($this->session->data['voucher']);
                unset($this->session->data['vouchers']);
                unset($this->session->data['totals']);
            }
        }

	    $this->setHeadingValues([
		                            'result'         => $result,
		                            'status'         => $status,
		                            'error'          => $error,
		                            'response'       => $r,
		                            'sessionOrderId' => $sessionOrderId,
		                            'statusDesc'     => $statusDesc,
	                            ]);
    }

	/**
	 * @param $products
	 * @param $statusDesc
	 * @param $orderId
	 *
	 * @return void
	 */
	public function restoreCart($products, $statusDesc, $orderId): void
	{
        if ($statusDesc !== 'approved' && is_array($products)) {
            // Restore the cart which has already been cleared
            foreach ($products as $product) {
                $options = $this->model_checkout_order->getOptions($orderId, $product['order_product_id']);
                $option  = [];
                if (is_array($options) && count($options) > 0) {
                    $option = $options;
                }
                $this->cart->add($product['product_id'], $product['quantity'], $option);
            }
        }
    }

	/**
	 * @param $record
	 *
	 * @return array
	 */
	public function sendClientRequest($record): array
	{
        $paygateID     = $this->getPaygateId();
        $encryptionKey = $this->getEncryptionkey();
        $useRedirect   = $this->config->get('payment_paygate_notifyredirect') === 'redirect';
        $respData      = [];
        $orderId       = $record['order_id'];
		$response = '';
        $error         = false;
        if ($useRedirect) {
            // Query to verify response data
            $payRequestId = htmlspecialchars($_POST['PAY_REQUEST_ID']);
            $reference    = $orderId;

	        try {
	            $paymentRequest = new PaymentRequest($paygateID, $encryptionKey);
				$response = $paymentRequest->query($payRequestId, $reference);
	        } catch(Exception $exception) {
				error_log('Exception: ' . $exception->getMessage());
				$error = true;
	        }

            $result = [];
            if ($response != '') {
                parse_str($response, $result);
            }
        } else {
            // Use transaction status for redirecting in browser only
            $result = $_POST;
        }
        $respData['result'] = $result;
        $respData['r']      = $response;
        $respData['error']  = $error;

        return $respData;
    }

	/**
	 * @param array $params
	 *
	 * @return void
	 */
	public function setHeadingValues(array $params): void
	{
		$result         = $params['result'] ?? [];
		$status         = $params['status'] ?? '';
		$error          = $params['error'] ?? '';
		$response       = $params['response'] ?? '';
		$sessionOrderId = $params['sessionOrderId'] ?? '';
		$statusDesc     = $params['statusDesc'] ?? '';

        $customerId = (int)isset($result['USER1']) ? $result['USER1'] : 0;
        if ($status == 'ok') {
            $data['heading_title'] = sprintf($this->language->get('heading_title'), $statusDesc);
        } else {
            $data['heading_title'] = 'Transaction status verification failed. Status not ok.
                 Please contact the shop owner to confirm transaction status.';
            $data['heading_title'] .= json_encode($_POST);
            $data['heading_title'] .= json_encode($result);
            $data['heading_title'] .= 'Curl error: ' . $error;
            $data['heading_title'] .= 'Curl response: ' . $response;
            $data['heading_title'] .= 'Session data: ' . $sessionOrderId;
        }
	    $this->document->setTitle($data['heading_title']);

	    $data['breadcrumbs']   = [];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home'),
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_basket'),
            'href' => $this->url->link('checkout/cart'),
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_checkout'),
            'href' => $this->url->link('checkout/checkout', '', 'SSL'),
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_success'),
            'href' => $this->url->link('checkout/success'),
        ];

        if ($customerId > 0) {
            $data['text_message'] = sprintf(
                $this->language->get('text_customer'),
                $this->url->link('account/account', '', 'SSL'),
                $this->url->link('account/order', '', 'SSL'),
                $this->url->link('account/download', '', 'SSL'),
                $this->url->link(self::INFORMATION_CONTACT)
            );
        } else {
            $data['text_message'] = sprintf(
                $this->language->get('text_guest'),
                $this->url->link(self::INFORMATION_CONTACT)
            );
        }

        $data['button_continue'] = $this->language->get('button_continue');
        $data['continue']        = $this->url->link('common/home');
        $data['column_left']     = $this->load->controller('common/column_left');
        $data['column_right']    = $this->load->controller('common/column_right');
        $data['content_top']     = $this->load->controller('common/content_top');
        $data['content_bottom']  = $this->load->controller('common/content_bottom');
        $data['footer']          = $this->load->controller('common/footer');
        $data['header']          = $this->load->controller('common/header');

        $this->response->addHeader('Content-Type: text/html; charset=utf-8');
        $this->response->setOutput($this->load->view('extension/paygate/common/paygate_success', $data));
    }

    /**
     * Handles notify response from Paygate
     * Controlled by Redirect/Notify setting in config
     */
    public function notify_handler(): void
    {
        // Shouldn't be able to get here in redirect as notify url is not set in redirect mode
        if ($this->config->get('payment_paygate_notifyredirect') === 'notify') {
            // Notify Paygate that information has been received
            echo 'OK';

            $errors = isset($EERROR);

            if (!$errors) {
                $postData           = $this->prepareCheckSumParams();
                $checkSumParams     = $postData['checkSumParams'];
                $notify_checksum    = $postData['notify_checksum'];
                $transaction_status = $postData['transaction_status'];
                $order_id           = $postData['order_id'];
                $payMethodDesc      = $postData['pay_method_desc'];

                if ($checkSumParams != $notify_checksum) {
                    $errors = true;
                }

                if (!$errors) {
                    $txnData       = $this->getOrderStatusDesc($transaction_status);
                    $orderStatusId = $txnData['orderStatusId'];
                    $statusDesc    = $txnData['statusDesc'];

                    $resultsComment = 'Notify response from Paygate with a status of ' . $statusDesc . $payMethodDesc;
                    $this->load->model(self::CHECKOUT_MODEL);
                    if ($statusDesc == 'approved') {
                        $this->cart->clear();
                    }
                    $this->model_checkout_order->addOrderHistory($order_id, $orderStatusId, $resultsComment, true);
                }
            }
        }
    }

	/**
	 * @return array
	 */
	public function prepareCheckSumParams(): array
	{
        // Check for test / live modes
        $this->testmode = $this->config->get('payment_paygate_testmode') === 'test';
        $paygateID      = $this->getPaygateId();
        $encryptionKey  = $this->getEncryptionkey();

        $checkSumParams = '';

        $postData = [];
        foreach ($_POST as $key => $val) {
            if ($key == 'PAYGATE_ID') {
                $checkSumParams .= $paygateID;
            }

            if ($key != 'CHECKSUM' && $key != 'PAYGATE_ID') {
                $checkSumParams .= $val;
            }

            if ($key == 'CHECKSUM') {
                $notifyChecksum = $val;
            }

            if ($key == 'TRANSACTION_STATUS') {
                $transactionStatus = $val;
            }

            if ($key == 'USER1') {
                $orderId = $val;
            }

            if ($key == 'PAY_METHOD_DETAIL') {
                $payMethodDesc = ', using a payment method of ' . $val;
            }
        }

        $checkSumParams .= $encryptionKey;
        $checkSumParams = md5($checkSumParams);

        $postData['checkSumParams']     = $checkSumParams;
        $postData['notify_checksum']    = $notifyChecksum ?? '';
        $postData['transaction_status'] = $transactionStatus ?? '';
        $postData['order_id']           = $orderId ?? '';
        $postData['pay_method_desc']    = $payMethodDesc ?? '';

        return $postData;
    }

	/**
	 * @param $transactionStatus
	 *
	 * @return array
	 */
	public function getOrderStatusDesc($transactionStatus): array
	{
        $txnData = [];
        if ($transactionStatus == 0) {
            $orderStatusId = 1;
            $statusDesc    = 'pending';
        } elseif ($transactionStatus == 1) {
            $orderStatusId = $this->config->get('payment_paygate_success_order_status_id');
            $statusDesc    = 'approved';
        } elseif ($transactionStatus == 2) {
            $orderStatusId = $this->config->get('payment_paygate_failed_order_status_id');
            $statusDesc    = 'declined';
        } elseif ($transactionStatus == 4) {
            $orderStatusId = $this->config->get('payment_paygate_cancelled_order_status_id');
            $statusDesc    = 'cancelled';
        }

        $txnData['orderStatusId'] = $orderStatusId;
        $txnData['statusDesc']    = $statusDesc;

        return $txnData;
    }

	/**
	 * @return void
	 */
	public function confirm(): void
	{
        if ($this->session->data['payment_method']['code'] == self::PAYGATE_CODE) {
            $this->load->model(self::CHECKOUT_MODEL);
            $comment = 'Redirected to Paygate';
            $this->model_checkout_order->addOrderHistory(
                $this->session->data['order_id'],
                $this->config->get('payment_paygate_order_status_id'),
                $comment,
                true
            );
        }
    }

	/**
	 * @return void
	 */
	public function before_redirect(): void
	{
        $json = [];

        if ($this->session->data['payment_method']['code'] == self::PAYGATE_CODE) {
            $this->load->model(self::CHECKOUT_MODEL);
            /************** $comment = 'Before Redirect to Paygate'; ***********/
            $this->model_checkout_order->addOrderHistory($this->session->data['order_id'], 1);
            $json['answer'] = 'success';
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}
