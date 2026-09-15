<?php

class Validator {
    public static function text($value, $required = true, $maxLength = 255) {
        $value = trim((string)$value);
        if ($required && $value === '') {
            return false;
        }
        if (strlen($value) > $maxLength || preg_match('/[[:cntrl:]]/', $value)) {
            return false;
        }
        return $value;
    }

    public static function name($value, $required = true) {
        $value = trim((string)$value);
        $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
        if (($required && $value === '') || $length < 2 || $length > 100) {
            return false;
        }
        return preg_match('/\A[\p{L}\p{M}]+(?:[ .\'\-][\p{L}\p{M}]+)*\z/u', $value) ? $value : false;
    }

    public static function email($value) {
        $value = strtolower(trim((string)$value));
        return strlen($value) <= 254 && filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : false;
    }

    public static function phone($value, $required = false) {
        $value = trim((string)$value);
        if ($value === '' && !$required) {
            return null;
        }
        $normalized = preg_replace('/[\s\-()]/', '', $value);
        if (!preg_match('/^(?:\+977)?9[678]\d{8}$/', $normalized)) {
            return false;
        }
        return $normalized;
    }

    public static function password($value) {
        $length = strlen((string)$value);
        return $length >= 8 && $length <= 128 ? (string)$value : false;
    }

    public static function decimal($value, $min = 0, $max = 1000000) {
        $value = trim((string)$value);
        if ($value === '' || !preg_match('/\A(?:0|[1-9]\d*)(?:\.\d{1,2})?\z/', $value)) {
            return false;
        }
        $number = (float)$value;
        return is_finite($number) && $number >= $min && $number <= $max ? $number : false;
    }

    public static function price($value) { return self::decimal($value, 0, 1000000); }

    public static function integer($value, $min = null, $max = null) {
        $value = trim((string)$value);
        if ($value === '' || !preg_match('/\A(?:0|[1-9]\d*)\z/', $value)) {
            return false;
        }
        $number = filter_var($value, FILTER_VALIDATE_INT);
        if ($number === false || ($min !== null && $number < $min) || ($max !== null && $number > $max)) {
            return false;
        }
        return $number;
    }

    public static function quantity($value) { return self::integer($value, 1, 1000); }
    public static function stock($value) { return self::integer($value, 0, 1000000); }
    public static function positiveInteger($value) { return self::integer($value, 1, 2147483647); }
    public static function nonNegativeInteger($value) { return self::integer($value, 0, 2147483647); }
    public static function signedInteger($value, $min = -1000000, $max = 1000000) {
        $value = trim((string)$value);
        if ($value === '' || !preg_match('/\A-?(?:0|[1-9]\d*)\z/', $value)) {
            return false;
        }
        $number = filter_var($value, FILTER_VALIDATE_INT);
        return $number !== false && $number >= $min && $number <= $max ? $number : false;
    }
    public static function description($value) { return self::text($value, false, 2000); }
    public static function role($value) { return in_array($value, ['customer', 'staff', 'admin'], true) ? $value : false; }
    public static function paymentMethod($value) { return in_array($value, ['esewa', 'khalti', 'bank_transfer', 'cash'], true) ? $value : false; }
    public static function orderType($value) { return in_array($value, ['dine-in', 'takeaway'], true) ? $value : false; }
    public static function date($value) {
        $date = DateTime::createFromFormat('!Y-m-d', trim((string)$value));
        return $date && $date->format('Y-m-d') === trim((string)$value) ? $date->format('Y-m-d') : false;
    }
    public static function time($value) {
        $time = DateTime::createFromFormat('!H:i', trim((string)$value)) ?: DateTime::createFromFormat('!H:i:s', trim((string)$value));
        return $time ? $time->format('H:i:s') : false;
    }
    public static function tableId($value) { return self::positiveInteger($value); }
    public static function categoryId($value) { return self::positiveInteger($value); }
    public static function menuItemId($value) { return self::positiveInteger($value); }
}