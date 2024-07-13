<?php
defined('_JEXEC') or die('Restricted access');

require_once(JPATH_ADMINISTRATOR . '/components/com_j2store/library/plugins/payment.php');

class plgJ2StorePayment_flutterwave extends J2StorePaymentPlugin
{
    var $_element = 'payment_flutterwave';

    function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);
        $this->loadLanguage('', JPATH_ADMINISTRATOR);
    }

    function onJ2StoreCalculateFees($order)
    {
        $success = false;
        if (isset($order->j2store_payment_method) && $order->j2store_payment_method == $this->_element) {
            $success = true;
        }
        return array($success, array());
    }

    function _prePayment($data)
    {
        $vars = new JObject();
        $app = JFactory::getApplication();
        $input = $app->input;

        $vars->order_id = $data['order_id'];
        $vars->user_email = isset($data['user_email']) ? $data['user_email'] : $this->getUserEmail($data['order_id']);
        $vars->amount = $data['orderpayment_amount'];
        $vars->currency_code = isset($data['currency_code']) ? $data['currency_code'] : $this->getCurrencyCode($data['order_id']);

        $vars->public_key = $this->params->get('public_key');
        $vars->callback_url = JRoute::_(JURI::root() . "index.php?option=com_j2store&view=checkout&task=confirmPayment&orderpayment_type=" . $this->_element . "&order_id=" . $data['order_id'], false);

        return $this->_getLayout('prepayment', $vars);
    }

    function _postPayment($data)
    {
        $app = JFactory::getApplication();
        $input = $app->input;
        $order_id = $input->getString('order_id');
        $transaction_id = $input->getString('flw_ref'); // Flutterwave returns 'flw_ref'

        $order = F0FTable::getAnInstance('Order', 'J2StoreTable')->getClone();
        $order->load(array('order_id' => $order_id));

        $flutterwave_secret_key = $this->params->get('secret_key');
        $payment_status = $this->_verifyTransaction($transaction_id, $flutterwave_secret_key);

        if ($payment_status == 'success') {
            $order->transaction_status = 'C';
            $order->order_state = 'C';
            $order->transaction_id = $transaction_id;
            $order->store();

            // Update order status
            $orderpayment = F0FTable::getAnInstance('Orderpayment', 'J2StoreTable')->getClone();
            $orderpayment->load(array('order_id' => $order_id));
            $orderpayment->order_state = 'payment_received';
            $orderpayment->payment_status = 'C';
            $orderpayment->transaction_id = $transaction_id;
            $orderpayment->save();
        } else {
            $order->transaction_status = 'F';
            $order->order_state = 'F';
            $order->transaction_id = $transaction_id;
            $order->store();
        }

        $app->redirect(JRoute::_('index.php?option=com_j2store&view=checkout', false));
    }

    function _verifyTransaction($transaction_id, $secret_key)
    {
        $url = "https://api.flutterwave.com/v3/transactions/" . $transaction_id . "/verify";
        $headers = array(
            "Authorization: Bearer " . $secret_key,
            "Content-Type: application/json"
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response);
        if ($result && $result->status == 'success') {
            return 'success';
        }
        return 'failed';
    }

    protected function getUserEmail($order_id)
    {
        $order = F0FTable::getAnInstance('Order', 'J2StoreTable')->getClone();
        $order->load(array('order_id' => $order_id));
        return $order->user_email;
    }

    protected function getCurrencyCode($order_id)
    {
        $order = F0FTable::getAnInstance('Order', 'J2StoreTable')->getClone();
        $order->load(array('order_id' => $order_id));
        return $order->currency_code;
    }
}
?>
