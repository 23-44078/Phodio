<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'db.php';

// 1. GATEKEEPER
function checkLogin() {
    if (!isset($_SESSION['admin'])) {
        header("Location: login.php");
        exit();
    }
}

// 2. MAIN STATS & BREAKDOWNS
function getDashboardData($conn, $range = 'month') {
    // Determine the date condition
    $dateCondition = ($range == 'today') ? "CURDATE()" : "DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
    
    // Helper to generate the WHERE clause for different columns
    $whereBooking = "WHERE booking_date >= $dateCondition";
    $whereExpense = "WHERE expense_date >= $dateCondition";
    $whereLiab    = "WHERE created_at >= $dateCondition";

    // --- Totals ---
    $income = $conn->query("SELECT SUM(price) as total FROM bookings $whereBooking")->fetch_assoc()['total'] ?? 0;
    $expense = $conn->query("SELECT SUM(amount) as total FROM expenses $whereExpense")->fetch_assoc()['total'] ?? 0;
    $liabilities = $conn->query("SELECT SUM(amount) as total FROM liabilities $whereLiab")->fetch_assoc()['total'] ?? 0;
    $profit = $income - $expense;

    // --- Breakdowns (Now Filtered by Date!) ---
    $rental_breakdown = $conn->query("SELECT package_type, SUM(price) as amt FROM bookings $whereBooking GROUP BY package_type");
    $expense_breakdown = $conn->query("SELECT description, SUM(amount) as amt FROM expenses $whereExpense GROUP BY description");
    $liability_breakdown = $conn->query("SELECT creditor, SUM(amount) as amt FROM liabilities $whereLiab GROUP BY creditor");

    return [
        'stats' => [
            'income' => $income,
            'expense' => $expense,
            'liabilities' => $liabilities,
            'profit' => $profit
        ],
        'rentals' => $rental_breakdown,
        'expenses' => $expense_breakdown,
        'liabilities_list' => $liability_breakdown
    ];
}
?>