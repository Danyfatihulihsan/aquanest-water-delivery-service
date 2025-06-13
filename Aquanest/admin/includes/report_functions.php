<?php
/**
 * Fungsi untuk laporan penjualan, pelanggan, dan pengiriman
 */

/**
 * Mendapatkan data untuk laporan penjualan
 */
function getSalesReport($conn, $start_date, $end_date) {
    try {
        // Daily sales
        $stmt = $conn->prepare("SELECT DATE(order_date) as date, COUNT(*) as order_count, 
                              SUM(total_amount) as total_sales
                              FROM orders 
                              WHERE order_date BETWEEN :start_date AND :end_date
                              AND payment_status = 'paid'
                              GROUP BY DATE(order_date)
                              ORDER BY date");
        $stmt->bindParam(':start_date', $start_date);
        $stmt->bindParam(':end_date', $end_date);
        $stmt->execute();
        $dailySales = $stmt->fetchAll();
        
        // Total for period
        $stmt = $conn->prepare("SELECT COUNT(*) as order_count, 
                             SUM(total_amount) as total_sales
                             FROM orders 
                             WHERE order_date BETWEEN :start_date AND :end_date
                             AND payment_status = 'paid'");
        $stmt->bindParam(':start_date', $start_date);
        $stmt->bindParam(':end_date', $end_date);
        $stmt->execute();
        $totalSales = $stmt->fetch();
        
        // Product breakdown
        $stmt = $conn->prepare("SELECT p.name as product_name, SUM(oi.quantity) as total_qty,
                             SUM(oi.quantity * oi.price) as total_amount
                             FROM order_items oi
                             JOIN products p ON oi.product_id = p.product_id
                             JOIN orders o ON oi.order_id = o.order_id
                             WHERE o.order_date BETWEEN :start_date AND :end_date
                             AND o.payment_status = 'paid'
                             GROUP BY p.product_id
                             ORDER BY total_amount DESC");
        $stmt->bindParam(':start_date', $start_date);
        $stmt->bindParam(':end_date', $end_date);
        $stmt->execute();
        $productBreakdown = $stmt->fetchAll();
        
        return [
            'daily_sales' => $dailySales,
            'total_sales' => $totalSales,
            'product_breakdown' => $productBreakdown
        ];
    } catch (PDOException $e) {
        setFlashMessage('danger', 'Gagal mengambil data laporan: ' . $e->getMessage());
        return null;
    }
}

/**
 * Mendapatkan data untuk laporan pelanggan
 */
function getCustomerReport($conn, $start_date, $end_date) {
    try {
        // Top customers
        $stmt = $conn->prepare("SELECT c.customer_id, c.name, c.phone, c.email, 
                             COUNT(o.order_id) as order_count,
                             SUM(o.total_amount) as total_spent
                             FROM customers c
                             JOIN orders o ON c.customer_id = o.customer_id
                             WHERE o.order_date BETWEEN :start_date AND :end_date
                             AND o.payment_status = 'paid'
                             GROUP BY c.customer_id
                             ORDER BY total_spent DESC
                             LIMIT 10");
        $stmt->bindParam(':start_date', $start_date);
        $stmt->bindParam(':end_date', $end_date);
        $stmt->execute();
        $topCustomers = $stmt->fetchAll();
        
        // New customers in period
        $stmt = $conn->prepare("SELECT COUNT(*) as new_customer_count
                             FROM customers
                             WHERE created_at BETWEEN :start_date AND :end_date");
        $stmt->bindParam(':start_date', $start_date);
        $stmt->bindParam(':end_date', $end_date);
        $stmt->execute();
        $newCustomers = $stmt->fetch();
        
        return [
            'top_customers' => $topCustomers,
            'new_customers' => $newCustomers
        ];
    } catch (PDOException $e) {
        setFlashMessage('danger', 'Gagal mengambil data laporan: ' . $e->getMessage());
        return null;
    }
}

/**
 * Mendapatkan data untuk laporan pengiriman
 */
function getDeliveryReport($conn, $start_date, $end_date) {
    try {
        // Delivery status breakdown
        $stmt = $conn->prepare("SELECT status, COUNT(*) as count
                             FROM orders
                             WHERE order_date BETWEEN :start_date AND :end_date
                             GROUP BY status");
        $stmt->bindParam(':start_date', $start_date);
        $stmt->bindParam(':end_date', $end_date);
        $stmt->execute();
        $statusBreakdown = $stmt->fetchAll();
        
        // Delivery timeline
        $stmt = $conn->prepare("SELECT 
                             DATE(o.order_date) as order_date,
                             DATE(o.delivery_date) as delivery_date,
                             COUNT(*) as count,
                             AVG(TIMESTAMPDIFF(HOUR, o.order_date, o.delivery_date)) as avg_hours
                             FROM orders o
                             WHERE o.order_date BETWEEN :start_date AND :end_date
                             AND o.status = 'delivered'
                             GROUP BY DATE(o.order_date)");
        $stmt->bindParam(':start_date', $start_date);
        $stmt->bindParam(':end_date', $end_date);
        $stmt->execute();
        $deliveryTimeline = $stmt->fetchAll();
        
        return [
            'status_breakdown' => $statusBreakdown,
            'delivery_timeline' => $deliveryTimeline
        ];
    } catch (PDOException $e) {
        setFlashMessage('danger', 'Gagal mengambil data laporan: ' . $e->getMessage());
        return null;
    }
}