<?php

	class ControllerPaymentZibal extends Controller {
		public function index() {
			$this->load->language('payment/zibal');
			$this->load->model('checkout/order');

			$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

			$data['button_confirm'] = $this->language->get('button_confirm');

			$data['error_warning'] = false;

			if (extension_loaded('curl')) {

				$amount = $this->get_price_in_rail($order_info);
				$redirect = $this->url->link('payment/zibal/callback', '', '', 'SSL');


				$merchant = $this->config->get('zibal_merchant');
				$zibal_direct = $this->config->get('zibal_direct');



				$parameters = array(
						'merchant' => $merchant,
						'amount' => $amount,
						'orderId' => $order_info['order_id'],
						'mobile' => $order_info['telephone'],
						'description' => $order_info['email'],
						'callbackUrl' => $redirect,
				);

				$result = $this->post_to_zibal('request', $parameters);
				if ($result != false) {
					if ($result->result == 100) {
						$data['action'] = 'https://gateway.zibal.ir/start/' . $result->trackId;
						if($zibal_direct=="yes")
							$data['action'].="/direct";
					} else {
						$message = $this->language->get('error_head');
						$message .= $result->message;
						$data['error_warning'] = $message;

					}
				} else {
					$message = $this->language->get('error_request');
					$data['error_warning'] = $message;
				}


			} else {

				$data['error_warning'] = $this->language->get('error_curl');
			}

			if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/payment/zibal.tpl')) {

				return $this->load->view($this->config->get('config_template') . '/template/payment/zibal.tpl', $data);

			} else {

				return $this->load->view('default/template/payment/zibal.tpl', $data);
			}
		}

		public function callback() {
			$this->load->language('payment/zibal');
			$this->load->model('checkout/order');

			$this->document->setTitle($this->language->get('heading_title'));

			$order_id = isset($this->session->data['order_id']) ? $this->session->data['order_id'] : false;

			$order_info = $this->model_checkout_order->getOrder($order_id);

			$data['heading_title'] = $this->language->get('heading_title');

			$data['button_continue'] = $this->language->get('button_continue');
			$data['continue'] = $this->url->link('common/home', '', 'SSL');

			$data['error_warning'] = false;

			$data['continue'] = $this->url->link('checkout/cart', '', 'SSL');
			$merchant = $this->config->get('zibal_merchant');
			if ($this->request->post['orderId'] && $this->request->post['trackId']) {
				if ($this->request->post['success'] == "1") {
					$trackId = $_POST['trackId'];
					//verify payment
					$parameters = array(
							'trackId' => $trackId,
							'merchant' => $merchant,
					);

					$result = $this->post_to_zibal('verify', $parameters);
					if ($result != false) {
						if ($result->result == 100) {
							$amount = $this->get_price_in_rail($order_info);

							if ($amount == $result->amount) {

								$comment = $this->language->get('text_transaction') . $trackId;
								$comment .= '<br/>' . $this->language->get('text_transaction_reference') . $result->refNumber;

								$this->model_checkout_order->addOrderHistory($order_info['order_id'], $this->config->get('zibal_order_status_id'), $comment);

							} else {
								$data['error_warning'] = $this->language->get('error_amount');
							}

						} else {
							$data['error_warning'] = $this->language->get('error_payment');

						}
					} else {
						$data['error_warning'] = $this->language->get('error_payment');

					}


				} else {
					$data['error_warning'] = $this->language->get('error_payment');
				}

			} else {
				$data['error_warning'] = $this->language->get('error_data');
			}

			if ($data['error_warning']) {

				$data['breadcrumbs'] = array();

				$data['breadcrumbs'][] = array(
						'text' => $this->language->get('text_home'),
						'href' => $this->url->link('common/home', '', 'SSL'),
						'separator' => false
				);

				$data['breadcrumbs'][] = array(
						'text' => $this->language->get('text_basket'),
						'href' => $this->url->link('checkout/cart', '', 'SSL'),
						'separator' => ' » '
				);

				$data['breadcrumbs'][] = array(
						'text' => $this->language->get('text_checkout'),
						'href' => $this->url->link('checkout/checkout', '', 'SSL'),
						'separator' => ' » '
				);

				$data['header'] = $this->load->controller('common/header');
				$data['footer'] = $this->load->controller('common/footer');

				if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/payment/zibal_callback.tpl')) {

					$this->response->setOutput($this->load->view($this->config->get('config_template') . '/template/payment/zibal_callback.tpl', $data));

				} else {

					$this->response->setOutput($this->load->view('default/template/payment/zibal_callback.tpl', $data));
				}

			} else {

				$this->response->redirect($this->url->link('checkout/success', '', 'SSL'));
			}
		}

		private function post_to_zibal($url, $data = false) {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, "https://gateway.zibal.ir/".$url);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json; charset=utf-8'));
			curl_setopt($ch, CURLOPT_POST, 1);
			if ($data) {
				curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
			}
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
			$result = curl_exec($ch);
			curl_close($ch);
			return !empty($result) ? json_decode($result) : false;
		}

		private function get_price_in_rail($order_info) {
			$amount = $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false);
			$currency = $order_info['currency_code'];
			$rate = 0;
			if ($currency == 'RLS') {
				$rate = 1;
			} elseif ($currency == 'TOM') {
				$rate = 10;
			}
			return $amount * $rate;
		}


	}

?>
