<!-- Sales Report Template -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-chart-line me-2"></i>
            Laporan Penjualan: <?php echo date('d M Y', strtotime($start_date)); ?> - <?php echo date('d M Y', strtotime($end_date)); ?>
        </h5>
    </div>
    <div class="card-body">
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="stats-card">
                    <h5>TOTAL PENJUALAN</h5>
                    <h2><?php echo formatRupiah($reportData['total_sales']['total_sales'] ?? 0); ?></h2>
                    <p>Dari <?php echo $reportData['total_sales']['order_count'] ?? 0; ?> pesanan</p>
                    <div class="progress mt-3" style="height: 6px;">
                        <div class="progress-bar bg-primary" role="progressbar" style="width: 100%" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="stats-card">
                    <h5>RATA-RATA PESANAN</h5>
                    <?php
                    $avgOrder = ($reportData['total_sales']['order_count'] > 0) 
                        ? ($reportData['total_sales']['total_sales'] / $reportData['total_sales']['order_count']) 
                        : 0;
                    ?>
                    <h2><?php echo formatRupiah($avgOrder); ?></h2>
                    <p>Nilai rata-rata per pesanan</p>
                    <div class="progress mt-3" style="height: 6px;">
                        <div class="progress-bar bg-success" role="progressbar" style="width: 75%" aria-valuenow="75" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Daily Sales Chart -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="mb-3 fw-bold text-primary">
                    <i class="fas fa-chart-bar me-2"></i>Grafik Penjualan Harian
                </h5>
                <div class="chart-container" style="height: 300px;">
                    <canvas id="dailySalesChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Product Breakdown -->
        <div class="card">
            <div class="card-body">
                <h5 class="mb-3 fw-bold text-primary">
                    <i class="fas fa-box me-2"></i>Penjualan Berdasarkan Produk
                </h5>
                <div class="table-container">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Produk</th>
                                <th>Jumlah Terjual</th>
                                <th>Total Penjualan</th>
                                <th>% dari Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $totalAmount = $reportData['total_sales']['total_sales'] ?? 0;
                            foreach ($reportData['product_breakdown'] as $product): 
                                $percentage = ($totalAmount > 0) ? ($product['total_amount'] / $totalAmount) * 100 : 0;
                            ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="product-color-indicator" style="width: 12px; height: 12px; border-radius: 50%; background-color: <?php echo 'hsl('.rand(180, 250).', 70%, 60%)'; ?>; margin-right: 10px;"></div>
                                            <span class="fw-medium"><?php echo $product['product_name']; ?></span>
                                        </div>
                                    </td>
                                    <td><?php echo $product['total_qty']; ?></td>
                                    <td class="fw-medium"><?php echo formatRupiah($product['total_amount']); ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <span class="me-2"><?php echo number_format($percentage, 1); ?>%</span>
                                            <div class="progress flex-grow-1" style="height: 5px;">
                                                <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo $percentage; ?>%" aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Initialize Sales Charts -->
<script>
    // Data untuk grafik dari PHP
    const salesChartData = <?php echo json_encode(array_map(function($item) { 
        return [
            'date' => date('d M', strtotime($item['date'])),
            'total' => (float)$item['total_sales'],
            'count' => (int)$item['order_count']
        ]; 
    }, $reportData['daily_sales'])); ?>;
    
    // Variabel ini akan digunakan oleh report.js
    window.reportData = {
        type: 'sales',
        salesData: salesChartData
    };
</script>