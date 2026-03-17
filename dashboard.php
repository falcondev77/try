<?php
require_once __DIR__ . '/functions.php';
bootstrapSettings();
requireLogin();
$user = currentUser();
$rewards = getRewards();

$stmt = db()->prepare('SELECT * FROM link_requests WHERE user_id = ? ORDER BY id DESC LIMIT 20');
$stmt->execute([$user['id']]);
$requests = $stmt->fetchAll();
?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard · <?php echo h(SITE_NAME); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        body.dash-page {
            background: #0a0a0a;
            color: #f5f5f5;
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
        }

        .dash-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 22px 48px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }

        .dash-logo {
            font-size: 17px;
            font-weight: 600;
            letter-spacing: -0.3px;
            color: #f5f5f5;
            text-decoration: none;
        }

        .dash-nav-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .dash-user-info {
            font-size: 13px;
            color: #737373;
        }

        .dash-points-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.2);
            color: #4ade80;
            border-radius: 100px;
            padding: 5px 12px;
            font-size: 13px;
            font-weight: 600;
        }

        .btn-nav-action {
            display: inline-block;
            padding: 8px 18px;
            border-radius: 7px;
            font-size: 13px;
            font-weight: 500;
            text-decoration: none;
            background: rgba(255,255,255,0.06);
            color: #a3a3a3;
            border: 1px solid rgba(255,255,255,0.08);
            transition: background 0.2s, color 0.2s;
        }

        .btn-nav-action:hover {
            background: rgba(255,255,255,0.1);
            color: #f5f5f5;
        }

        .dash-content {
            max-width: 860px;
            margin: 0 auto;
            padding: 48px 24px 80px;
        }

        .dash-section {
            margin-bottom: 40px;
        }

        .dash-section-title {
            font-size: 13px;
            font-weight: 500;
            color: #525252;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 14px;
        }

        .link-card {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 14px;
            padding: 28px;
        }

        .link-card-title {
            font-size: 20px;
            font-weight: 700;
            letter-spacing: -0.5px;
            margin: 0 0 6px;
            color: #f5f5f5;
        }

        .link-card-sub {
            font-size: 14px;
            color: #737373;
            margin: 0 0 24px;
        }

        .link-input-row {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 10px;
        }

        .link-input {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px;
            padding: 13px 16px;
            font-size: 14px;
            color: #f5f5f5;
            font-family: 'Inter', sans-serif;
            transition: border-color 0.2s;
            width: 100%;
            box-sizing: border-box;
        }

        .link-input::placeholder {
            color: #525252;
        }

        .link-input:focus {
            outline: none;
            border-color: rgba(59, 130, 246, 0.5);
            background: rgba(255,255,255,0.07);
        }

        .btn-analyze {
            padding: 13px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            background: #3b82f6;
            color: #fff;
            border: none;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
            white-space: nowrap;
            transition: background 0.2s, transform 0.15s;
        }

        .btn-analyze:hover:not(:disabled) {
            background: #2563eb;
            transform: translateY(-1px);
        }

        .btn-analyze:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .result-box {
            margin-top: 20px;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 10px;
            padding: 20px;
        }

        .result-box.hidden { display: none; }

        .result-product-title {
            font-size: 16px;
            font-weight: 600;
            color: #f5f5f5;
            margin: 0 0 16px;
            line-height: 1.4;
        }

        .result-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }

        .result-meta-item {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 8px;
            padding: 12px 14px;
        }

        .result-meta-label {
            font-size: 11px;
            color: #525252;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            margin-bottom: 4px;
        }

        .result-meta-value {
            font-size: 15px;
            font-weight: 600;
            color: #f5f5f5;
        }

        .result-meta-value.accent {
            color: #4ade80;
        }

        .result-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-result-primary {
            display: inline-block;
            padding: 11px 22px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            background: #3b82f6;
            color: #fff;
            transition: background 0.2s;
        }

        .btn-result-primary:hover { background: #2563eb; }

        .btn-result-secondary {
            display: inline-block;
            padding: 11px 22px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            background: rgba(255,255,255,0.06);
            color: #a3a3a3;
            border: 1px solid rgba(255,255,255,0.08);
            transition: background 0.2s, color 0.2s;
        }

        .btn-result-secondary:hover {
            background: rgba(255,255,255,0.1);
            color: #f5f5f5;
        }

        .result-alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.25);
            color: #fca5a5;
            border-radius: 8px;
            padding: 12px 14px;
            font-size: 14px;
        }

        .rewards-preview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 10px;
            margin-bottom: 14px;
        }

        .reward-card {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 10px;
            padding: 16px;
        }

        .reward-card-name {
            font-size: 13px;
            color: #a3a3a3;
            margin-bottom: 6px;
            line-height: 1.4;
        }

        .reward-card-points {
            font-size: 20px;
            font-weight: 700;
            color: #4ade80;
            letter-spacing: -0.5px;
            margin-bottom: 10px;
        }

        .reward-card-points span {
            font-size: 12px;
            font-weight: 400;
            color: #525252;
        }

        .btn-redeem {
            display: block;
            width: 100%;
            padding: 8px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            background: rgba(59, 130, 246, 0.15);
            color: #60a5fa;
            border: 1px solid rgba(59, 130, 246, 0.25);
            cursor: pointer;
            font-family: 'Inter', sans-serif;
            text-decoration: none;
            text-align: center;
            transition: background 0.2s, color 0.2s;
        }

        .btn-redeem:hover {
            background: rgba(59, 130, 246, 0.25);
            color: #93c5fd;
        }

        .rewards-more-link {
            font-size: 13px;
            color: #3b82f6;
            text-decoration: none;
        }

        .rewards-more-link:hover { text-decoration: underline; }

        .history-table {
            width: 100%;
            border-collapse: collapse;
        }

        .history-table th {
            font-size: 11px;
            font-weight: 500;
            color: #525252;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            padding: 0 12px 10px 0;
            text-align: left;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }

        .history-table td {
            padding: 12px 12px 12px 0;
            font-size: 13px;
            color: #a3a3a3;
            border-bottom: 1px solid rgba(255,255,255,0.04);
            vertical-align: top;
        }

        .history-table td.product-col {
            color: #d4d4d4;
            max-width: 260px;
        }

        .history-table td.points-col {
            color: #4ade80;
            font-weight: 600;
        }

        .table-wrap { overflow-x: auto; }

        .empty-state {
            text-align: center;
            padding: 40px 0;
            color: #525252;
            font-size: 14px;
        }

        @media (max-width: 640px) {
            .dash-nav { padding: 18px 20px; }
            .dash-content { padding: 32px 20px 60px; }
            .link-input-row { grid-template-columns: 1fr; }
            .dash-nav-right { gap: 10px; }
            .dash-user-info { display: none; }
        }
    </style>
</head>
<body class="dash-page">

    <nav class="dash-nav">
        <a href="dashboard.php" class="dash-logo"><?php echo h(SITE_NAME); ?></a>
        <div class="dash-nav-right">
            <span class="dash-user-info">Ciao, <?php echo h($user['name']); ?></span>
            <span class="dash-points-badge"><?php echo (int) $user['total_points']; ?> pt</span>
            <?php if ((int) $user['is_admin'] === 1): ?>
                <a class="btn-nav-action" href="admin.php">Admin</a>
            <?php endif; ?>
            <a class="btn-nav-action" href="logout.php">Esci</a>
        </div>
    </nav>

    <div class="dash-content">

        <div class="dash-section">
            <div class="link-card">
                <h1 class="link-card-title">Converti link Amazon</h1>
                <p class="link-card-sub">Incolla un link Amazon e il sistema rileva automaticamente la categoria e calcola i tuoi punti.</p>
                <div class="link-input-row">
                    <input type="text" id="amazonUrl" class="link-input" placeholder="https://amzn.eu/... oppure https://www.amazon.it/dp/...">
                    <button class="btn-analyze" id="analyzeBtn" type="button">Calcola punti</button>
                </div>
                <div id="resultBox" class="result-box hidden"></div>
            </div>
        </div>

        <div class="dash-section">
            <div class="dash-section-title">Premi in evidenza</div>
            <?php if ($rewards): ?>
                <div class="rewards-preview">
                    <?php foreach (array_slice($rewards, 0, 4) as $reward): ?>
                        <div class="reward-card">
                            <div class="reward-card-name"><?php echo h($reward['reward_name']); ?></div>
                            <div class="reward-card-points"><?php echo (int) $reward['points_cost']; ?> <span>pt</span></div>
                            <a href="rewards.php" class="btn-redeem">Riscatta</a>
                        </div>
                    <?php endforeach; ?>
                </div>
                <a href="rewards.php" class="rewards-more-link">Vedi tutti i premi &rarr;</a>
            <?php else: ?>
                <p class="empty-state">Nessun premio disponibile al momento.</p>
            <?php endif; ?>
        </div>

        <div class="dash-section">
            <div class="dash-section-title">Ultimi link generati</div>
            <?php if ($requests): ?>
                <div class="table-wrap">
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Prodotto</th>
                                <th>Categoria</th>
                                <th>Prezzo</th>
                                <th>Punti</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($requests as $row): ?>
                            <tr>
                                <td><?php echo h(date('d/m/Y', strtotime($row['created_at']))); ?></td>
                                <td class="product-col"><?php echo h($row['product_title'] ?: 'Prodotto Amazon'); ?></td>
                                <td><?php echo h($row['category_name'] ?: '-'); ?></td>
                                <td><?php echo h(formatEuro($row['product_price'] !== null ? (float) $row['product_price'] : null)); ?></td>
                                <td class="points-col"><?php echo (int) $row['points_preview']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="empty-state">Nessun link ancora. Incolla il tuo primo link Amazon qui sopra.</p>
            <?php endif; ?>
        </div>

    </div>

<script src="app.js"></script>
</body>
</html>
