<?php
session_start();

if (isset($_SESSION['staff_discount'])) {
    unset($_SESSION['staff_discount']);
}
if (isset($_SESSION['discount_applied_booking'])) {
    unset($_SESSION['discount_applied_booking']);
}
if (isset($_SESSION['discount_applied_payment'])) {
    unset($_SESSION['discount_applied_payment']);
}

header("Location: " . $_SERVER['HTTP_REFERER']);
exit();
?>