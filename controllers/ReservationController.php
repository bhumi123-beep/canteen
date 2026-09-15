<?php
require_once __DIR__ . '/../models/Table.php';

class ReservationController {
    private $tableModel;
    private $reservationModel;

    public function __construct($db) {
        $this->tableModel = new TableModel($db);
        $this->reservationModel = new Reservation($db);
    }

    public function availableTables($date, $time) {
        requireCustomer();
        $date = Validator::date($date);
        $time = Validator::time($time);
        if ($date === false || $time === false) {
            return [];
        }
        $all = $this->tableModel->all();
        $reservedIds = $this->reservationModel->reservedTableIds($date, $time);
        foreach ($all as &$t) {
            $t['reserved'] = in_array($t['id'], $reservedIds);
        }
        return $all;
    }

    public function book($userId, $tableId, $date, $time, $guests) {
        requireCustomer();
        $date = Validator::date($date);
        $time = Validator::time($time);
        $tableId = Validator::tableId($tableId);
        $guests = Validator::integer($guests, 1, 20);
        if ($date === false || $time === false || $tableId === false || $guests === false) {
            return false;
        }
        $end = date('H:i:s', strtotime($time . ' +1 hour'));
        return $this->reservationModel->create((int)$_SESSION['user_id'], $tableId, $date, $time, $end, $guests);
    }

    public function myReservations($userId) {
        requireCustomer();
        return $this->reservationModel->findByUser($userId);
    }

    public function reservation($reservationId, $userId) {
        requireCustomer();
        return $this->reservationModel->findOwnedById($reservationId, $userId);
    }

    public function payDeposit($userId, $reservationId, $method) {
        requireCustomer();
        return $this->reservationModel->payDeposit($reservationId, $userId, $method);
    }

    public function cancel($userId, $reservationId) {
        requireCustomer();
        return $this->reservationModel->cancel($reservationId, $userId);
    }

    public function adminAll() {
        requireReservationManagement();
        return $this->reservationModel->all();
    }

    public function adminCancel($reservationId, $paymentOutcome) {
        requireReservationManagement();
        return $this->reservationModel->adminCancel($reservationId, $paymentOutcome);
    }

    public function adminUpdate($reservationId, $tableId, $date, $start, $end, $guests, $status) {
        requireReservationManagement();
        return $this->reservationModel->adminUpdate($reservationId, $tableId, $date, $start, $end, $guests, $status);
    }

    public function allTables() {
        requireReservationManagement();
        return $this->tableModel->all();
    }

    public function adminAllTablesWithStatus() {
        requireReservationManagement();
        return $this->tableModel->allWithInactive();
    }

    public function adminCreateTable($tableNumber, $capacity, $location) {
        requireReservationManagement();
        return $this->tableModel->create($tableNumber, $capacity, $location);
    }

    public function adminUpdateTable($id, $tableNumber, $capacity, $location, $isActive) {
        requireReservationManagement();
        return $this->tableModel->update($id, $tableNumber, $capacity, $location, $isActive);
    }

    public function adminToggleTable($id) {
        requireReservationManagement();
        return $this->tableModel->toggleStatus($id);
    }

    public function adminDeleteTable($id) {
        requireReservationManagement();
        return $this->tableModel->delete($id);
    }

    public function myUpcomingReservations($userId) {
        requireCustomer();
        return $this->reservationModel->findByUserUpcoming($userId);
    }

    public function updateStatus($id, $status) {
        requireReservationManagement();
        return $this->reservationModel->updateStatus($id, $status);
    }
}
