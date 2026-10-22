<?php
/**
 * Memora Booking CRM - Entry Point
 *
 * Tự động nạp các modules chức năng con của hệ thống CRM Đặt lịch:
 * 1. booking-crm-meta.php (Data model & Status dictionary)
 * 2. booking-crm-ajax.php (API & Backend processing)
 * 3. booking-crm-page.php (Admin page UI & Assets enqueue)
 *
 * @package Memora
 * @subpackage BookingCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$crm_dir = trailingslashit( dirname( __FILE__ ) );

// 1. Nạp metadata & trạng thái nghiệp vụ
if ( file_exists( $crm_dir . 'booking-crm-meta.php' ) ) {
    require_once $crm_dir . 'booking-crm-meta.php';
}

// 2. Nạp API & xử lý dữ liệu AJAX
if ( file_exists( $crm_dir . 'booking-crm-ajax.php' ) ) {
    require_once $crm_dir . 'booking-crm-ajax.php';
}

// 3. Nạp giao diện quản trị Admin / Frontend
if ( file_exists( $crm_dir . 'booking-crm-page.php' ) ) {
    require_once $crm_dir . 'booking-crm-page.php';
}
