public function create_doniapay_payment()
    {
        $identifier = 'doniapay';
        $payment_details = $this->session->userdata('payment_details');
        $payment_gateway = $this->db->get_where('payment_gateways', ['identifier' => $identifier])->row_array();

        if ($payment_details['is_instructor_payout_user_id'] > 0) {
            $instructor_details = $this->user_model->get_all_user($payment_details['is_instructor_payout_user_id'])->row_array();
            $keys = json_decode($instructor_details['payment_keys'], true);
            $keys = $keys[$payment_gateway['identifier']];
        } else {
            $keys = json_decode($payment_gateway['keys'], true);
        }

        $api_key = $keys['api_key'];
        $api_url = "https://api.doniapay.com/v2/order/synchronize/prepare";

        $user_details = $this->user_model->get_all_user($this->session->userdata('user_id'))->row_array();

        $raw_data = [
            "dn_su"  => $payment_details['success_url'] . '/' . $payment_gateway['identifier'],
            "dn_cu"  => $payment_details['cancel_url'],
            "dn_wu"  => $payment_details['success_url'] . '/' . $payment_gateway['identifier'],
            "dn_am"  => round($payment_details['total_payable_amount'], 2),
            "dn_cn"  => $user_details['first_name'] . ' ' . $user_details['last_name'],
            "dn_ce"  => $user_details['email'],
            "dn_mt"  => json_encode(["phone" => $user_details['phone'] ?? '']),
            "dn_rt"  => "GET"
        ];

        $payload   = base64_encode(json_encode($raw_data));
        $signature = hash_hmac('sha256', $payload, $api_key);

        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['dp_payload' => $payload]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "X-Signature-Key: $api_key",
            "donia-signature: $signature",
            "Content-Type: application/json"
        ]);

        $response_data = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            $response = array(
                'status' => 0,
                'error' => array(
                    'message' => 'CURL Error: ' . $err
                )
            );
        } else {
            $res_decoded = json_decode($response_data, true);
            
            if (isset($res_decoded['status']) && $res_decoded['status'] == 'success') {
                $response = array(
                    'status' => 1,
                    'message' => 'Checkout Session created successfully!',
                    'GatewayPageURL' => $res_decoded['payment_url'] ?? $res_decoded['url'] ?? ''
                );
            } else {
                $response = array(
                    'status' => 0,
                    'error' => array(
                        'message' => isset($res_decoded['message']) ? $res_decoded['message'] : 'Checkout Session creation failed!'
                    )
                );
            }
        }

        echo json_encode($response);
    }
