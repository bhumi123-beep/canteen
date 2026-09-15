<?php
require_once __DIR__ . '/../models/Transaction.php';

class EsewaController {
    private $transactionModel;
    private $config;

    public function __construct($db) {
        requireCashierPayments();
        $this->transactionModel = new Transaction($db);
        $this->config = esewaConfig();
    }

    public function begin($orderId, $cashierId) {
        requireCashierPayments();
        if ($this->config['secret_key'] === '') {
            return ['success' => false, 'message' => 'eSewa is not configured. Set CANTEEN_ESEWA_SECRET_KEY first.'];
        }
        $transactionUuid = 'CT-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(5)));
        $transaction = $this->transactionModel->createPending($orderId, $cashierId, $transactionUuid);
        if (!$transaction) {
            return ['success' => false, 'message' => 'This order is no longer available for payment.'];
        }

        $signedFieldNames = 'total_amount,transaction_uuid,product_code';
        $fields = [
            'amount' => $transaction['amount'],
            'tax_amount' => '0',
            'total_amount' => $transaction['amount'],
            'transaction_uuid' => $transaction['transaction_uuid'],
            'product_code' => $this->config['merchant_code'],
            'product_service_charge' => '0',
            'product_delivery_charge' => '0',
            'success_url' => $this->callbackUrl('esewa_success.php'),
            'failure_url' => $this->callbackUrl('esewa_failure.php'),
            'signed_field_names' => $signedFieldNames,
        ];
        $signingMessage = 'total_amount=' . $fields['total_amount'] . ',transaction_uuid=' . $fields['transaction_uuid'] . ',product_code=' . $fields['product_code'];
        $fields['signature'] = base64_encode(hash_hmac('sha256', $signingMessage, $this->config['secret_key'], true));
        return ['success' => true, 'url' => $this->config['payment_url'], 'fields' => $fields];
    }

    public function complete($encodedResponse) {
        requireCashierPayments();
        $payload = $this->decodeResponse($encodedResponse);
        $uuid = trim((string)($payload['transaction_uuid'] ?? ''));
        if ($uuid === '') {
            return ['success' => false, 'message' => 'eSewa returned no transaction reference.'];
        }
        $transaction = $this->transactionModel->findByUuid($uuid);
        if (!$transaction) {
            return ['success' => false, 'message' => 'The eSewa transaction could not be found.'];
        }
        $status = $this->checkStatus($transaction);
        $isConfirmed = $status && strtoupper((string)($status['status'] ?? '')) === 'COMPLETE'
            && (string)($status['transaction_uuid'] ?? '') === $transaction['transaction_uuid']
            && (string)($status['product_code'] ?? '') === $this->config['merchant_code']
            && abs((float)($status['total_amount'] ?? 0) - (float)$transaction['amount']) < 0.01;
        if (!$isConfirmed || !$this->transactionModel->markPaid($uuid, (string)($status['ref_id'] ?? $payload['transaction_code'] ?? ''))) {
            $this->transactionModel->markFailed($uuid, (string)($status['ref_id'] ?? $payload['transaction_code'] ?? ''));
            return ['success' => false, 'message' => 'eSewa could not confirm this payment.'];
        }
        return ['success' => true, 'transaction' => $this->transactionModel->findByUuid($uuid)];
    }

    public function fail($encodedResponse) {
        requireCashierPayments();
        $payload = $this->decodeResponse($encodedResponse);
        $uuid = trim((string)($payload['transaction_uuid'] ?? ''));
        if ($uuid !== '') {
            $transaction = $this->transactionModel->findByUuid($uuid);
            $status = $transaction ? $this->checkStatus($transaction) : false;
            $isConfirmed = $transaction && $status && strtoupper((string)($status['status'] ?? '')) === 'COMPLETE'
                && (string)($status['transaction_uuid'] ?? '') === $transaction['transaction_uuid']
                && (string)($status['product_code'] ?? '') === $this->config['merchant_code']
                && abs((float)($status['total_amount'] ?? 0) - (float)$transaction['amount']) < 0.01;
            if ($isConfirmed && $this->transactionModel->markPaid($uuid, (string)($status['ref_id'] ?? $payload['transaction_code'] ?? ''))) {
                return ['success' => true, 'transaction' => $this->transactionModel->findByUuid($uuid)];
            }
            $this->transactionModel->markFailed($uuid, (string)($status['ref_id'] ?? $payload['transaction_code'] ?? ''));
        }
        return ['success' => false, 'uuid' => $uuid];
    }

    public function transaction($uuid) {
        requireCashierPayments();
        return $this->transactionModel->findByUuid($uuid);
    }

    public function renderRedirectForm($payment) {
        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Redirecting to eSewa</title></head><body onload="document.getElementById(\'esewa-payment\').submit()">';
        $html .= '<form id="esewa-payment" method="POST" action="' . e($payment['url']) . '">';
        foreach ($payment['fields'] as $name => $value) {
            $html .= '<input type="hidden" name="' . e($name) . '" value="' . e($value) . '">';
        }
        $html .= '<noscript><button type="submit">Continue to eSewa</button></noscript></form></body></html>';
        return $html;
    }

    private function callbackUrl($file) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE_URL . '/' . $file;
    }

    private function decodeResponse($encodedResponse) {
        if (!$encodedResponse) {
            return [];
        }
        $decoded = base64_decode((string)$encodedResponse, true);
        if ($decoded === false) {
            return [];
        }
        $payload = json_decode($decoded, true);
        return is_array($payload) ? $payload : [];
    }

    private function checkStatus($transaction) {
        if (!function_exists('curl_init')) {
            return false;
        }
        $body = json_encode([
            'product_code' => $this->config['merchant_code'],
            'total_amount' => $transaction['amount'],
            'transaction_uuid' => $transaction['transaction_uuid'],
        ]);
        $curl = curl_init($this->config['status_url']);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
        ]);
        $response = curl_exec($curl);
        $httpCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        if ($response === false || $httpCode < 200 || $httpCode >= 300) {
            return false;
        }
        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : false;
    }
}
