<?php
require_once __DIR__ . '/../models/Payment.php';
require_once __DIR__ . '/../models/Order.php';

class PaymentController {
    private $paymentModel;
    private $orderModel;

    public function __construct($db) {
        $this->paymentModel = new Payment($db);
        $this->orderModel = new Order($db);
    }

    public function initiate($orderId, $method, $amount) {
        requireCustomer();
        $orderId = Validator::positiveInteger($orderId);
        if ($orderId === false || !$this->orderModel->findForUser($orderId, (int)$_SESSION['user_id'])) {
            return false;
        }
        return $this->paymentModel->create($orderId, $method, $amount);
    }

    // Simulates OTP-verified payment confirmation (as in the base paper's OTP verification flow)
    public function confirm($paymentId, $orderId) {
        requireCustomer();
        $order = $this->orderModel->findForUser($orderId, (int)$_SESSION['user_id']);
        if (!$order || !$this->paymentModel->findByOrderForUser($orderId, (int)$_SESSION['user_id'])) {
            return false;
        }
        $ref = $this->paymentModel->markSuccess($paymentId, $orderId);
        if (!$ref) {
            return false;
        }
        $this->orderModel->updateStatus($orderId, 'pending');
        return $ref;
    }

    public function getForOrder($orderId) {
        requireCustomer();
        return $this->paymentModel->findByOrderForUser($orderId, (int)$_SESSION['user_id']);
    }
}
