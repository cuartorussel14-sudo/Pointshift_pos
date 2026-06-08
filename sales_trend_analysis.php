<?php
require_once 'config.php';
requireLogin();
requireAdmin();

$page_title = "Sales Trend Analysis";

// Get date range from query parameters or set defaults
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days')); // Last 30 days
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d'); // Today
$view_type = isset($_GET['view_type']) ? $_GET['view_type'] : 'daily'; // daily, weekly, monthly

// Fetch sales data by date
$sales_query = "
    SELECT 
        DATE(o.created_at) as sale_date,
        COUNT(DISTINCT o.id) as total_orders,
        SUM(o.total_amount) as total_sales,
        SUM(o.subtotal) as subtotal,
        SUM(o.discount_amount) as total_discount,
        SUM(o.tax_amount) as total_tax
    FROM orders o
    WHERE o.status = 'completed'
    AND DATE(o.created_at) BETWEEN ? AND ?
    GROUP BY DATE(o.created_at)
    ORDER BY sale_date ASC
";

$stmt = $conn->prepare($sales_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$sales_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch top selling products
$top_products_query = "
    SELECT 
        p.name,
        p.sku,
        c.name as category,
        SUM(oi.quantity) as total_quantity,
        SUM(oi.total_price) as total_revenue
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN products p ON oi.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE o.status = 'completed'
    AND DATE(o.created_at) BETWEEN ? AND ?
    GROUP BY p.id
    ORDER BY total_quantity DESC
    LIMIT 10
";

$stmt = $conn->prepare($top_products_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$top_products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch sales by category
$category_sales_query = "
    SELECT 
        COALESCE(c.name, 'Uncategorized') as category_name,
        COUNT(DISTINCT o.id) as order_count,
        SUM(oi.quantity) as total_items,
        SUM(oi.total_price) as total_revenue
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN products p ON oi.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE o.status = 'completed'
    AND DATE(o.created_at) BETWEEN ? AND ?
    GROUP BY c.id
    ORDER BY total_revenue DESC
";

$stmt = $conn->prepare($category_sales_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$category_sales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Low-performing products (slow movers)
$low_products_query = "
    SELECT 
        p.name, p.sku, COALESCE(c.name, 'Uncategorized') as category,
        SUM(oi.quantity) as total_quantity,
        SUM(oi.total_price) as total_revenue
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN products p ON oi.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE o.status = 'completed'
    AND DATE(o.created_at) BETWEEN ? AND ?
    GROUP BY p.id
    HAVING total_quantity > 0
    ORDER BY total_quantity ASC
    LIMIT 10
";

$stmt = $conn->prepare($low_products_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$low_products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Sales by cashier / staff
$staff_sales_query = "
    SELECT u.id, u.first_name, u.last_name, COUNT(DISTINCT o.id) as order_count, SUM(o.total_amount) as total_sales
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    WHERE o.status = 'completed'
    AND DATE(o.created_at) BETWEEN ? AND ?
    GROUP BY u.id
    ORDER BY total_sales DESC
";
$stmt = $conn->prepare($staff_sales_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$staff_sales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Sales by payment method
$payment_sales_query = "
    SELECT o.payment_method, COUNT(*) as orders, SUM(o.total_amount) as total_sales
    FROM orders o
    WHERE o.status = 'completed'
    AND DATE(o.created_at) BETWEEN ? AND ?
    GROUP BY o.payment_method
";
$stmt = $conn->prepare($payment_sales_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$payment_sales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Peak sales hours
$hours_query = "
    SELECT HOUR(o.created_at) as hour_of_day, COUNT(*) as orders, SUM(o.total_amount) as total_sales
    FROM orders o
    WHERE o.status = 'completed'
    AND DATE(o.created_at) BETWEEN ? AND ?
    GROUP BY hour_of_day
    ORDER BY hour_of_day ASC
";
$stmt = $conn->prepare($hours_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$hours_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate summary statistics
$total_sales = array_sum(array_column($sales_data, 'total_sales'));
$total_orders = array_sum(array_column($sales_data, 'total_orders'));
$avg_order_value = $total_orders > 0 ? $total_sales / $total_orders : 0;
$total_discount = array_sum(array_column($sales_data, 'total_discount'));

// Prepare data for charts
$dates = array_column($sales_data, 'sale_date');
$sales_amounts = array_column($sales_data, 'total_sales');
$order_counts = array_column($sales_data, 'total_orders');

// --- Holt-Winters forecasting (simple additive implementation) ---
/**
 * Triple exponential smoothing (Holt-Winters) - additive seasonality
 * @param array $series numeric indexed array of historical values
 * @param int $seasonLen season length (e.g., 7 for weekly)
 * @param int $horizon forecast horizon
 * @param float $alpha level smoothing
 * @param float $beta trend smoothing
 * @param float $gamma seasonal smoothing
 * @return array ["fitted"=>[], "forecast"=>[], "level"=>[], "trend"=>[], "season"=>[]]
 */
function holt_winters_additive(array $series, int $seasonLen = 7, int $horizon = 14, float $alpha = 0.2, float $beta = 0.01, float $gamma = 0.01) {
    $n = count($series);
    if ($n === 0) return ['fitted'=>[], 'forecast'=>[], 'level'=>[], 'trend'=>[], 'season'=>[]];

    // initialize seasonal indices by averaging seasons
    $seasonals = array_fill(0, $seasonLen, 0.0);
    $seasonAverages = [];
    $nSeasons = intdiv($n, $seasonLen);
    for ($i = 0; $i < $nSeasons; $i++) {
        $seasonAverages[$i] = array_sum(array_slice($series, $i*$seasonLen, $seasonLen)) / $seasonLen;
    }
    for ($i = 0; $i < $seasonLen; $i++) {
        $sum = 0;
        for ($j = 0; $j < $nSeasons; $j++) {
            $sum += $series[$j*$seasonLen + $i] - $seasonAverages[$j];
        }
        $seasonals[$i] = $sum / max(1, $nSeasons);
    }

    // initial level and trend
    $level = $series[0];
    $trend = 0.0;
    if ($n > 1) $trend = $series[1] - $series[0];

    $fitted = [];
    $levels = [];
    $trends = [];
    $seasons = [];

    for ($i = 0; $i < $n; $i++) {
        $seasonIndex = $i % $seasonLen;
        $lastLevel = $level;
        $lastTrend = $trend;

        // observation
        $obs = $series[$i];

        // update level, trend, seasonal
        $level = $alpha * ($obs - $seasonals[$seasonIndex]) + (1 - $alpha) * ($level + $trend);
        $trend = $beta * ($level - $lastLevel) + (1 - $beta) * $trend;
        $seasonals[$seasonIndex] = $gamma * ($obs - $level) + (1 - $gamma) * $seasonals[$seasonIndex];

        $forecast = $level + $trend + $seasonals[$seasonIndex];

        $fitted[] = $forecast;
        $levels[] = $level;
        $trends[] = $trend;
        $seasons[] = $seasonals[$seasonIndex];
    }

    // Forecast future points
    $forecasts = [];
    for ($m = 1; $m <= $horizon; $m++) {
        $seasonIndex = ($n + $m - 1) % $seasonLen;
        $forecasts[] = $level + $m * $trend + $seasonals[$seasonIndex];
    }

    return ['fitted'=>$fitted, 'forecast'=>$forecasts, 'level'=>$levels, 'trend'=>$trends, 'season'=>$seasons];
}

// run Holt-Winters on sales amounts
$season_length = isset($_GET['season_length']) ? max(1, (int)$_GET['season_length']) : 7; // default weekly
$forecast_horizon = isset($_GET['horizon']) ? max(1, (int)$_GET['horizon']) : 14; // days
$hw = holt_winters_additive(array_values($sales_amounts), $season_length, $forecast_horizon);

// residuals and anomaly detection (z-score)
$residuals = [];
foreach ($sales_amounts as $i => $val) {
    $f = $hw['fitted'][$i] ?? null;
    $residuals[$i] = $f !== null ? ($val - $f) : 0;
}
$meanResid = count($residuals) ? array_sum($residuals)/count($residuals) : 0;
$stdResid = count($residuals) ? sqrt(array_sum(array_map(function($r) use ($meanResid) { return pow($r - $meanResid,2); }, $residuals))/count($residuals)) : 0;
$anomalies = [];
// Detect anomalies by absolute residual threshold (no z-score column)
$threshold = 2.5 * $stdResid; // configurable multiplier
foreach ($residuals as $i => $r) {
    if (abs($r) >= $threshold) {
        $anomalies[] = ['date'=>$dates[$i] ?? null, 'value'=>$sales_amounts[$i], 'residual'=>$r];
    }
}

// Forecast accuracy metrics (Phase 4)
$mae = $mape = $rmse = 0.0;
$metric_count = 0;
if (!empty($sales_amounts) && !empty($hw['fitted'])) {
    // Optional simple validation split: last 20% as validation
    $n = count($sales_amounts);
    $val_start = max(2, (int)floor($n * 0.8)); // ensure at least 2 points for train
    $sum_abs_err = 0.0; $sum_abs_pct = 0.0; $sum_sq = 0.0; $cnt = 0;
    for ($i = 0; $i < $n; $i++) {
        $actual = (float)$sales_amounts[$i];
        $fit = isset($hw['fitted'][$i]) ? (float)$hw['fitted'][$i] : null;
        if ($fit === null) continue;
        // compute metrics on all fitted points (in-sample) for stability
        $err = $actual - $fit;
        $sum_abs_err += abs($err);
        if ($actual != 0.0) $sum_abs_pct += abs($err) / max(1e-9, abs($actual));
        $sum_sq += $err * $err;
        $cnt++;
    }
    if ($cnt > 0) {
        $mae = $sum_abs_err / $cnt;
        $mape = ($sum_abs_pct / $cnt) * 100.0;
        $rmse = sqrt($sum_sq / $cnt);
        $metric_count = $cnt;
    }
}

// prepare chart labels including forecast horizon
$chart_labels = [];
if (!empty($dates)) {
    foreach ($dates as $d) $chart_labels[] = date('M d', strtotime($d));
    $lastDate = end($dates);
    $base = strtotime($lastDate);
} else {
    // generate last N days
    $base = strtotime($end_date);
}
for ($m = 1; $m <= $forecast_horizon; $m++) {
    $chart_labels[] = date('M d', $base + 86400 * $m);
}

$hw_fitted_json = json_encode($hw['fitted']);
$hw_forecast_json = json_encode($hw['forecast']);
$stdResidVal = $stdResid;

// confidence intervals for forecast (approx using residual std)
$ci_upper = [];
$ci_lower = [];
foreach ($hw['forecast'] as $f) {
    $ci = 1.96 * $stdResidVal;
    $ci_upper[] = $f + $ci;
    $ci_lower[] = $f - $ci;
}
// pad fitted to match labels length (fitted + future nulls)
$fitted_padded = $hw['fitted'];
for ($i = 0; $i < count($hw['forecast']); $i++) $fitted_padded[] = null;



ob_start();
?>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="stats-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-2">Total Sales</h6>
                    <h3 class="mb-0">₱<?php echo number_format($total_sales, 2); ?></h3>
                </div>
                <div class="stats-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="stats-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-2">Total Orders</h6>
                    <h3 class="mb-0"><?php echo number_format($total_orders); ?></h3>
                </div>
                <div class="stats-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <i class="fas fa-shopping-cart"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="stats-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-2">Avg Order Value</h6>
                    <h3 class="mb-0">₱<?php echo number_format($avg_order_value, 2); ?></h3>
                </div>
                <div class="stats-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <i class="fas fa-calculator"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="stats-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-2">Total Discounts</h6>
                    <h3 class="mb-0">₱<?php echo number_format($total_discount, 2); ?></h3>
                </div>
                <div class="stats-icon" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                    <i class="fas fa-tags"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Card -->
<div class="content-card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Options</h5>
    </div>
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Start Date</label>
                <input type="date" class="form-control" name="start_date" value="<?php echo $start_date; ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">End Date</label>
                <input type="date" class="form-control" name="end_date" value="<?php echo $end_date; ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-danger w-100 btn-custom">
                    <i class="fas fa-search me-2"></i>Apply Filter
                </button>
            </div>
                        <div class="col-md-2">
                            <label class="form-label">Season Length</label>
                            <input type="number" class="form-control" name="season_length" value="<?php echo htmlspecialchars($season_length); ?>" min="1">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Forecast Horizon (days)</label>
                            <input type="number" class="form-control" name="horizon" value="<?php echo htmlspecialchars($forecast_horizon); ?>" min="1">
                        </div>
        </form>
    </div>
</div>

<!-- Forecast Performance Metrics (Phase 4) -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="stats-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-2">
                        <i class="fas fa-bullseye me-1"></i>MAE
                        <i class="fas fa-info-circle text-muted" data-bs-toggle="tooltip" title="Mean Absolute Error - Average prediction error in currency"></i>
                    </h6>
                    <h3 class="mb-0">₱<?php echo number_format($mae, 2); ?></h3>
                    <small class="text-muted">Lower is better</small>
                </div>
                <div class="stats-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <i class="fas fa-chart-line"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="stats-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-2">
                        <i class="fas fa-percentage me-1"></i>MAPE
                        <i class="fas fa-info-circle text-muted" data-bs-toggle="tooltip" title="Mean Absolute Percentage Error - Average prediction error as percentage"></i>
                    </h6>
                    <h3 class="mb-0"><?php echo number_format($mape, 2); ?>%</h3>
                    <small class="<?php echo $mape < 10 ? 'text-success' : ($mape < 20 ? 'text-warning' : 'text-danger'); ?>">
                        <?php 
                        if ($mape < 10) echo 'Excellent accuracy';
                        elseif ($mape < 20) echo 'Good accuracy';
                        else echo 'Fair accuracy';
                        ?>
                    </small>
                </div>
                <div class="stats-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <i class="fas fa-percent"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="stats-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-2">
                        <i class="fas fa-square-root-alt me-1"></i>RMSE
                        <i class="fas fa-info-circle text-muted" data-bs-toggle="tooltip" title="Root Mean Square Error - Penalizes larger errors more heavily"></i>
                    </h6>
                    <h3 class="mb-0">₱<?php echo number_format($rmse, 2); ?></h3>
                    <small class="text-muted">Based on <?php echo $metric_count; ?> data points</small>
                </div>
                <div class="stats-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <i class="fas fa-calculator"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Sales Trend Chart -->
<div class="content-card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Sales Trend Chart with Forecast</h5>
        <small class="text-muted">Holt-Winters Triple Exponential Smoothing (Additive Model)</small>
    </div>
    <div class="card-body">
        <canvas id="salesTrendChart" height="80"></canvas>
        <div class="mt-3 p-3 bg-light rounded">
            <div class="row text-center">
                <div class="col-md-3">
                    <small class="text-muted d-block">Season Length</small>
                    <strong><?php echo $season_length; ?> days</strong>
                </div>
                <div class="col-md-3">
                    <small class="text-muted d-block">Forecast Horizon</small>
                    <strong><?php echo $forecast_horizon; ?> days</strong>
                </div>
                <div class="col-md-3">
                    <small class="text-muted d-block">Historical Data Points</small>
                    <strong><?php echo count($sales_amounts); ?></strong>
                </div>
                <div class="col-md-3">
                    <small class="text-muted d-block">Confidence Interval</small>
                    <strong>95%</strong>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- AI-Powered Insights & Recommendations (Phase 5: Decision Making) -->
<div class="content-card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-lightbulb me-2"></i>Insights & Recommendations</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h6 class="text-primary"><i class="fas fa-chart-line me-2"></i>Trend Analysis</h6>
                <ul class="list-unstyled">
                    <?php
                    // Calculate trend direction
                    $trend_direction = 'stable';
                    $trend_percentage = 0;
                    if (!empty($hw['trend'])) {
                        $avg_trend = array_sum($hw['trend']) / count($hw['trend']);
                        $avg_sales = !empty($sales_amounts) ? array_sum($sales_amounts) / count($sales_amounts) : 1;
                        $trend_percentage = ($avg_trend / max(1, $avg_sales)) * 100;
                        if ($trend_percentage > 1) $trend_direction = 'increasing';
                        elseif ($trend_percentage < -1) $trend_direction = 'decreasing';
                    }
                    ?>
                    <li class="mb-2">
                        <i class="fas fa-<?php echo $trend_direction == 'increasing' ? 'arrow-up text-success' : ($trend_direction == 'decreasing' ? 'arrow-down text-danger' : 'minus text-warning'); ?> me-2"></i>
                        Sales trend is <strong><?php echo $trend_direction; ?></strong>
                        <?php if (abs($trend_percentage) > 0.1): ?>
                            (<?php echo $trend_percentage > 0 ? '+' : ''; ?><?php echo number_format($trend_percentage, 2); ?>% per day)
                        <?php endif; ?>
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-calendar-alt text-info me-2"></i>
                        Seasonal pattern detected with <strong><?php echo $season_length; ?>-day cycle</strong>
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-chart-bar text-primary me-2"></i>
                        Forecast accuracy: <strong><?php echo $mape < 10 ? 'Excellent' : ($mape < 20 ? 'Good' : 'Fair'); ?></strong> (MAPE: <?php echo number_format($mape, 2); ?>%)
                    </li>
                </ul>
            </div>
            <div class="col-md-6">
                <h6 class="text-success"><i class="fas fa-tasks me-2"></i>Recommended Actions</h6>
                <ul class="list-unstyled">
                    <?php if ($trend_direction == 'increasing'): ?>
                        <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Increase inventory for top-selling products</li>
                        <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Consider expanding product range in high-performing categories</li>
                    <?php elseif ($trend_direction == 'decreasing'): ?>
                        <li class="mb-2"><i class="fas fa-exclamation-triangle text-warning me-2"></i>Review pricing strategy and promotions</li>
                        <li class="mb-2"><i class="fas fa-exclamation-triangle text-warning me-2"></i>Analyze customer feedback and market trends</li>
                    <?php endif; ?>
                    
                    <?php if (!empty($low_products) && count($low_products) > 5): ?>
                        <li class="mb-2"><i class="fas fa-box text-info me-2"></i>Consider promotions for <?php echo count($low_products); ?> slow-moving products</li>
                    <?php endif; ?>
                    
                    <?php if (!empty($hw['forecast'])): ?>
                        <?php 
                        $avg_forecast = array_sum($hw['forecast']) / count($hw['forecast']);
                        $avg_historical = !empty($sales_amounts) ? array_sum($sales_amounts) / count($sales_amounts) : 0;
                        $forecast_change = $avg_historical > 0 ? (($avg_forecast - $avg_historical) / $avg_historical) * 100 : 0;
                        ?>
                        <li class="mb-2">
                            <i class="fas fa-crystal-ball text-primary me-2"></i>
                            Expected sales for next <?php echo $forecast_horizon; ?> days: 
                            <strong>₱<?php echo number_format(array_sum($hw['forecast']), 2); ?></strong>
                            <?php if (abs($forecast_change) > 5): ?>
                                <span class="badge bg-<?php echo $forecast_change > 0 ? 'success' : 'warning'; ?>">
                                    <?php echo $forecast_change > 0 ? '+' : ''; ?><?php echo number_format($forecast_change, 1); ?>%
                                </span>
                            <?php endif; ?>
                        </li>
                    <?php endif; ?>
                    
                    <?php if (!empty($hours_data)): ?>
                        <?php
                        $peak_hour = 0;
                        $max_orders = 0;
                        foreach ($hours_data as $h) {
                            if ($h['orders'] > $max_orders) {
                                $max_orders = $h['orders'];
                                $peak_hour = $h['hour_of_day'];
                            }
                        }
                        ?>
                        <li class="mb-2">
                            <i class="fas fa-clock text-warning me-2"></i>
                            Schedule more staff during peak hours (around <strong><?php echo sprintf('%02d:00', $peak_hour); ?></strong>)
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Top Selling Products -->
    <div class="col-md-4 mb-4">
        <div class="content-card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-trophy me-2"></i>Top 10 Selling Products</h5>
            </div>
            <div class="card-body" style="height: 250px; overflow-y: auto;">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Product</th>
                                <th>Category</th>
                                <th>Qty Sold</th>
                                <th>Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($top_products)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No data available</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($top_products as $index => $product): ?>
                                    <tr>
                                        <td>
                                            <span class="badge <?php
                                                echo $index == 0 ? 'bg-warning' : ($index == 1 ? 'bg-secondary' : ($index == 2 ? 'bg-info' : 'bg-light text-dark'));
                                            ?>">
                                                #<?php echo $index + 1; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($product['name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($product['sku']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($product['category'] ?? 'N/A'); ?></td>
                                        <td><span class="badge bg-primary"><?php echo number_format($product['total_quantity']); ?></span></td>
                                        <td><strong>₱<?php echo number_format($product['total_revenue'], 2); ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Fast-moving Products -->
    <div class="col-md-4 mb-4">
        <div class="content-card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-rocket me-2"></i>Fast-moving Products</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($top_products)): ?>
                    <canvas id="fastMovingChart" height="250"></canvas>
                <?php else: ?>
                    <p class="text-center text-muted py-4">No data available for fast-moving products chart.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Slow-moving Products -->
    <div class="col-md-4 mb-4">
        <div class="content-card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-snail me-2"></i>Slow-moving Products</h5>
            </div>
            <div class="card-body" style="height: 250px; overflow-y: auto;">
                <div class="table-responsive">
                    <table class="table table-hover table-sm">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>SKU</th>
                                <th>Category</th>
                                <th>Qty Sold</th>
                                <th>Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($low_products)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No low-performing products found for the selected period</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($low_products as $p): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($p['name']); ?></td>
                                        <td><small class="text-muted"><?php echo htmlspecialchars($p['sku']); ?></small></td>
                                        <td><?php echo htmlspecialchars($p['category']); ?></td>
                                        <td><span class="badge bg-secondary"><?php echo number_format($p['total_quantity']); ?></span></td>
                                        <td>₱<?php echo number_format($p['total_revenue'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Sales by Category and Sales by Payment Method side-by-side -->
<div class="row mb-4">
    <div class="col-md-6 mb-4">
        <div class="content-card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-layer-group me-2"></i>Sales by Category</h5>
            </div>
            <div class="card-body">
                <canvas id="categorySalesChart" height="180"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-4">
        <div class="content-card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-money-check-dollar me-2"></i>Sales by Payment Method</h5>
            </div>
            <div class="card-body">
                <canvas id="paymentMethodChart" height="140"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Sales by Staff and Peak Hours side-by-side -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="content-card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Sales by Staff</h5>
            </div>
            <div class="card-body">
                <canvas id="staffSalesChart" height="140"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="content-card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Peak Sales Hours</h5>
            </div>
            <div class="card-body">
                <canvas id="peakHoursChart" height="80"></canvas>
            </div>
        </div>
    </div>
</div>
<div class="content-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-table me-2"></i>Daily Sales Breakdown</h5>
        <button class="btn btn-sm btn-success" onclick="exportTableToCSV('sales_data.csv')">
            <i class="fas fa-file-excel me-2"></i>Export to CSV
        </button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover" id="salesTable">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Orders</th>
                        <th>Subtotal</th>
                        <th>Discount</th>
                        <th>Tax</th>
                        <th>Total Sales</th>
                        <th>Avg Order</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sales_data)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No sales data found for the selected period</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($sales_data as $sale): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($sale['sale_date'])); ?></td>
                                <td><span class="badge bg-info"><?php echo $sale['total_orders']; ?></span></td>
                                <td>₱<?php echo number_format($sale['subtotal'] ?? 0, 2); ?></td>
                                <td>₱<?php echo number_format($sale['total_discount'] ?? 0, 2); ?></td>
                                <td>₱<?php echo number_format($sale['total_tax'] ?? 0, 2); ?></td>
                                <td><strong>₱<?php echo number_format($sale['total_sales'] ?? 0, 2); ?></strong></td>
                                <td>₱<?php echo number_format((($sale['total_orders'] ?? 0) > 0) ? ($sale['total_sales'] / $sale['total_orders']) : 0, 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Initialize Bootstrap tooltips
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
// Sales Trend Chart
const salesTrendCtx = document.getElementById('salesTrendChart').getContext('2d');

// prepare series for chart: pad historical sales with nulls for forecast horizon
const chartLabels = <?php echo json_encode($chart_labels); ?>;
const salesSeries = <?php echo json_encode(array_merge($sales_amounts, array_fill(0, $forecast_horizon, null))); ?>;
const fittedSeries = <?php echo json_encode($fitted_padded); ?>;
const forecastSeries = <?php echo json_encode(array_merge(array_fill(0, count($sales_amounts), null), $hw['forecast'])); ?>;
const ciUpper = <?php echo json_encode(array_merge(array_fill(0, count($sales_amounts), null), $ci_upper)); ?>;
const ciLower = <?php echo json_encode(array_merge(array_fill(0, count($sales_amounts), null), $ci_lower)); ?>;
const orderCounts = <?php echo json_encode($order_counts); ?>;

new Chart(salesTrendCtx, {
    type: 'line',
    data: {
        labels: chartLabels,
        datasets: [
            {
                label: 'Actual Sales (₱)',
                data: salesSeries,
                borderColor: 'rgb(220, 53, 69)',
                backgroundColor: 'rgba(220, 53, 69, 0.08)',
                tension: 0.3,
                fill: false,
                pointRadius: 2
            },
            {
                label: 'Fitted (HW)',
                data: fittedSeries,
                borderColor: 'rgba(108,117,125,0.9)',
                borderDash: [6,4],
                pointRadius: 0,
                tension: 0.3,
                fill: false
            },
            {
                label: 'Forecast',
                data: forecastSeries,
                borderColor: 'rgb(40,167,69)',
                backgroundColor: 'rgba(40,167,69,0.08)',
                tension: 0.3,
                pointRadius: 2,
                fill: true
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        interaction: {mode: 'index', intersect: false},
        plugins: {
            legend: {position: 'top'},
            tooltip: {
                callbacks: {
                    label: function(context) {
                        let label = context.dataset.label || '';
                        if (label) label += ': ';
                        if (context.parsed.y !== null) {
                            label += '₱' + Number(context.parsed.y).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
                        }
                        return label;
                    }
                }
            }
        },
        scales: {
            y: {type: 'linear', display: true, position: 'left', title: {display:true, text:'Sales Amount (₱)'}},
            y1: {type:'linear', display:false, position:'right', grid: {drawOnChartArea:false}}
        }
    }
});

// Category Sales Chart
const categorySalesCtx = document.getElementById('categorySalesChart').getContext('2d');
new Chart(categorySalesCtx, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_column($category_sales, 'category_name')); ?>,
        datasets: [{
            data: <?php echo json_encode(array_column($category_sales, 'total_revenue')); ?>,
            backgroundColor: [
                'rgba(220, 53, 69, 0.8)',
                'rgba(13, 110, 253, 0.8)',
                'rgba(25, 135, 84, 0.8)',
                'rgba(255, 193, 7, 0.8)',
                'rgba(108, 117, 125, 0.8)',
                'rgba(111, 66, 193, 0.8)'
            ],
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'right',
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        let label = context.label || '';
                        if (label) {
                            label += ': ';
                        }
                        label += '₱' + context.parsed.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                        return label;
                    }
                }
            }
        }
    }
});

// Staff Sales Chart
const staffLabels = <?php echo json_encode(array_map(function($s){ return trim(($s['first_name'] ?? '') . ' ' . ($s['last_name'] ?? '')); }, $staff_sales)); ?>;
const staffSalesData = <?php echo json_encode(array_map(function($s){ return (float)($s['total_sales'] ?? 0); }, $staff_sales)); ?>;
const staffCtx = document.getElementById('staffSalesChart').getContext('2d');
new Chart(staffCtx, {
    type: 'bar',
    data: { labels: staffLabels, datasets: [{ label: 'Sales (₱)', data: staffSalesData, backgroundColor: 'rgba(13,110,253,0.8)'}] },
    options: { responsive:true, maintainAspectRatio:true }
});

// Payment Method Chart
const paymentLabels = <?php echo json_encode(array_column($payment_sales, 'payment_method')); ?>;
const paymentData = <?php echo json_encode(array_map(function($p){ return (float)$p['total_sales']; }, $payment_sales)); ?>;
const paymentCtx = document.getElementById('paymentMethodChart').getContext('2d');
new Chart(paymentCtx, { type: 'pie', data: { labels: paymentLabels, datasets: [{ data: paymentData, backgroundColor: ['#4e73df','#1cc88a','#36b9cc','#f6c23e','#e74a3b'] }] }, options: { responsive:true } });

// Fast-moving Products Chart
if (document.getElementById('fastMovingChart')) {
    const fastMovingLabels = <?php echo json_encode(array_column($top_products, 'name')); ?>;
    const fastMovingData = <?php echo json_encode(array_column($top_products, 'total_quantity')); ?>;
    const fastMovingCtx = document.getElementById('fastMovingChart').getContext('2d');
    new Chart(fastMovingCtx, {
        type: 'bar',
        data: {
            labels: fastMovingLabels,
            datasets: [{
                label: 'Quantity Sold',
                data: fastMovingData,
                backgroundColor: 'rgba(40,167,69,0.8)',
                borderColor: 'rgba(40,167,69,1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: { display: true, text: 'Quantity' }
                }
            }
        }
    });
}

// Peak Hours Chart
const hoursLabels = <?php echo json_encode(array_map(function($h){ return sprintf('%02d:00', $h['hour_of_day']); }, $hours_data)); ?>;
const hoursData = <?php echo json_encode(array_map(function($h){ return (int)$h['orders']; }, $hours_data)); ?>;
const peakCtx = document.getElementById('peakHoursChart').getContext('2d');
new Chart(peakCtx, { type: 'line', data: { labels: hoursLabels, datasets: [{ label: 'Orders', data: hoursData, borderColor: 'rgba(255,193,7,0.9)', backgroundColor: 'rgba(255,193,7,0.08)', fill:true }] }, options: { responsive:true } });

// Export to CSV function
function exportTableToCSV(filename) {
    const table = document.getElementById('salesTable');
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    for (let i = 0; i < rows.length; i++) {
        const row = [], cols = rows[i].querySelectorAll('td, th');
        
        for (let j = 0; j < cols.length; j++) {
            let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, '').replace(/(\s\s)/gm, ' ');
            data = data.replace(/"/g, '""');
            row.push('"' + data + '"');
        }
        
        csv.push(row.join(','));
    }
    
    const csvString = csv.join('\n');
    const link = document.createElement('a');
    link.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csvString);
    link.download = filename;
    link.click();
}
</script>

<?php
$content = ob_get_clean();
include 'layout.php';
?>
