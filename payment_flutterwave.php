<?php
defined('_JEXEC') or die('Restricted access');

class plgJ2StorePayment_flutterwave extends JPlugin
{
    public function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);
        $this->loadLanguage();
    }

    public function onJ2StoreGetPaymentOptions($order)
    {
        $options = array();
        $option = new JObject();
        $option->element = 'payment_flutterwave';
        $option->title = $this->params->get('display_name', 'Flutterwave');
        $option->description = JText::_('PLG_J2STORE_PAYMENT_FLUTTERWAVE_DESC');
        $options[] = $option;
        return $options;
    }

    public function onJ2StoreProcessPayment($order)
    {
        $app = JFactory::getApplication();
        $input = $app->input;
        $order_id = $input->getInt('order_id');
        $order = F0FTable::getInstance('Order', 'J2StoreTable')->getClone();
        $order->load($order_id);

        $api_key = $this->params->get('secret_key');
        $public_key = $this->params->get('public_key');
        $live_mode = $this->params->get('live_mode');
        $currency = $this->params->get('currency');

        $amount = $order->order_total;
        $tx_ref = 'TXREF-' . uniqid();

        $callback_url = JRoute::_(JUri::root() . 'index.php?option=com_j2store&view=checkout&task=confirmPayment&orderpayment_type=payment_flutterwave&order_id=' . $order_id, false);

        $payload = array(
            'tx_ref' => $tx_ref,
            'amount' => $amount,
            'currency' => $currency,
            'redirect_url' => $callback_url,
            'payment_options' => 'card,banktransfer',
            'customer' => array(
                'email' => $order->user_email,
                'name' => $order->billing_first_name . ' ' . $order->billing_last_name
            ),
            'customizations' => array(
                'title' => $app->get('sitename'),
                'description' => 'Order Payment'
            )
        );

        $url = $live_mode ? 'https://api.flutterwave.com/v3/payments' : 'https://ravesandboxapi.flutterwave.com/v3/payments';

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $api_key,
            'Content-Type: application/json'
        ));

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
            $app->enqueueMessage($error_msg, 'error');
            return false;
        }

        curl_close($ch);

        $result = json_decode($response, true);

        if ($result['status'] == 'success') {
            $link = $result['data']['link'];
            $app->redirect($link);
        } else {
            $app->enqueueMessage($result['message'], 'error');
            return false;
        }
    }

    public function onJ2StoreConfirmPayment($order_id)
    {
        $app = JFactory::getApplication();
        $input = $app->input;
        $tx_ref = $input->getString('tx_ref');
        $status = $input->getString('status');

        if ($status == 'successful') {
            $order = F0FTable::getInstance('Order', 'J2StoreTable')->getClone();
            $order->load($order_id);
            $order->payment_complete();
            $order->order_state_id = J2Store::config()->get('order_state_paid', 4);
            $order->save();
            $app->enqueueMessage(JText::_('PLG_J2STORE_PAYMENT_FLUTTERWAVE_SUCCESS'), 'message');
        } else {
            $app->enqueueMessage(JText::_('PLG_J2STORE_PAYMENT_FLUTTERWAVE_FAILED'), 'error');
        }

        $app->redirect(JRoute::_(JUri::root() . 'index.php?option=com_j2store&view=checkout', false));
    }
}
