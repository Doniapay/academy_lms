public function check_doniapay_payment($identifier = "")
    {
        $payment_gateway = $this->db->get_where('payment_gateways', ['identifier' => $identifier])->row_array();
        $payment_details = $this->session->userdata('payment_details');

        if ($payment_details['is_instructor_payout_user_id'] > 0) {
            $instructor_details = $this->user_model->get_all_user($payment_details['is_instructor_payout_user_id'])->row_array();
            $keys = json_decode($instructor_details['payment_keys'], true);
            $keys = $keys[$payment_gateway['identifier']];
        } else {
            $keys = json_decode($payment_gateway['keys'], true);
        }

        $api_key = $keys['api_key'];
        $api_url = "https://api.doniapay.com/v2/order/synchronize/confirm";

        $transaction_id = $this->input->get('transaction_id') ? $this->input->get('transaction_id') : $this->input->post('transaction_id');

        if (empty($transaction_id)) {
            $transaction_id = isset($_GET['transaction_id']) ? $_GET['transaction_id'] : '';
        }

        if (empty($transaction_id)) {
            return false;
        }

        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(["transaction_id" => $transaction_id]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "X-Signature-Key: $api_key",
            "Content-Type: application/json"
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);

        if ($result && isset($result['status']) && ($result['status'] == 'success' || $result['status'] == 'COMPLETED' || $result['status'] == 'Successful')) {
            return true;
        }

        return false;
    }
