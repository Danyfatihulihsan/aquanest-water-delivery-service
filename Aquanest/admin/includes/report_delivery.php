<!-- Delivery Report Template -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-truck me-2"></i>
            Laporan Pengiriman: <?php echo date('d M Y', strtotime($start_date)); ?> - <?php echo date('d M Y', strtotime($end_date)); ?>
        </h5>
    </div>
    <div class="card-body">
        <!-- Delivery Status Breakdown -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="mb-4 fw-bold text-primary">
                    <i class="fas fa-tasks me-2"></i>Status Pesanan
                </h5>
                <div class="row">
                    <?php 
                    $statusColors = [
                        'pending' => 'warning',
                        'confirmed' => 'info',
                        'processing' => 'primary',
                        'shipped' => 'indigo',
                        'delivered' => 'success',
                        'cancelled' => 'danger'
                    ];
                    
                    $statusLabels = [
                        'pending' => 'Menunggu',
                        'confirmed' => 'Terkonfirmasi',
                        'processing' => 'Diproses',
                        'shipped' => 'Dikirim',
                        'delivered' => 'Terkirim',
                        'cancelled' => 'Dibatalkan'
                    ];
                    
                    $statusIcons = [
                        'pending' => 'fa-clock',
                        'confirmed' => 'fa-check-circle',
                        'processing' => 'fa-cog',
                        'shipped' => 'fa-shipping-fast',
                        'delivered' => 'fa-check-double',
                        'cancelled' => 'fa-times-circle'
                    ];
                    
                    $totalOrders = 0;
                    foreach ($reportData['status_breakdown'] as $status) {
                        $totalOrders += $status['count'];
                    }
                    
                    foreach ($reportData['status_breakdown'] as $status): 
                        $color = $statusColors[$status['status']] ?? 'secondary';
                        $label = $statusLabels[$status['status']] ?? ucfirst($status['status']);
                        $icon = $statusIcons[$status['status']] ?? 'fa-circle';
                        $percentage = ($totalOrders > 0) ? ($status['count'] / $totalOrders) * 100 : 0;
                    ?>
                        <div class="col-md-4 col-sm-6 mb-4">
                            <div class="card h-100">
                                <div class="card-body p-4">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="status-icon-circle me-3 d-flex align-items-center justify-content-center" 
                                             style="width: 50px; height: 50px; min-width: 50px; border-radius: 50%; background-color: var(--<?php echo $color; ?>-color); color: white;">
                                            <i class="fas <?php echo $icon; ?> fa-lg"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-1"><?php echo $label; ?></h6>
                                            <div class="d-flex align-items-center">
                                                <h3 class="mb-0 me-2"><?php echo $status['count']; ?></h3>
                                                <span class="badge bg-<?php echo $color; ?>"><?php echo number_format($percentage, 1); ?>%</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="progress" style="height: 6px;">
                                        <div class="progress-bar bg-<?php echo $color; ?>" role="progressbar" 
                                            style="width: <?php echo $percentage; ?>%" 
                                            aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Delivery Timeline -->
        <div class="card">
            <div class="card-body">
                <h5 class="mb-3 fw-bold text-primary">
                    <i class="fas fa-clock me-2"></i>Waktu Pengiriman
                </h5>
                <div class="table-container">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Tanggal Pesan</th>
                                <th>Jumlah Pesanan</th>
                                <th>Rata-rata Waktu Pengiriman</th>
                                <th>Performa</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reportData['delivery_timeline'] as $item): 
                                $hours = round($item['avg_hours']);
                                $performance = "";
                                $performanceClass = "";
                                
                                if ($hours <= 24) {
                                    $performance = "Sangat Baik";
                                    $performanceClass = "success";
                                } else if ($hours <= 48) {
                                    $performance = "Baik";
                                    $performanceClass = "info";
                                } else if ($hours <= 72) {
                                    $performance = "Normal";
                                    $performanceClass = "warning";
                                } else {
                                    $performance = "Lambat";
                                    $performanceClass = "danger";
                                }
                            ?>
                                <tr>
                                    <td><?php echo date('d M Y', strtotime($item['order_date'])); ?></td>
                                    <td><?php echo $item['count']; ?> pesanan</td>
                                    <td>
                                        <?php 
                                        if ($hours < 24) {
                                            echo $hours . ' jam';
                                        } else {
                                            $days = floor($hours / 24);
                                            $remainingHours = $hours % 24;
                                            echo $days . ' hari ' . $remainingHours . ' jam';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $performanceClass; ?>"><?php echo $performance; ?></span>
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

<script>
    // Variabel ini akan digunakan oleh report.js
    window.reportData = {
        type: 'delivery'
    };
</script>