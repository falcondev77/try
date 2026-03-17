<?php
require_once __DIR__ . '/functions.php';
bootstrapSettings();
requireLogin();
$user = currentUser();
$rewards = getRewards();

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rewardId = (int) ($_POST['reward_id'] ?? 0);

    if ($rewardId > 0) {
        $stmt = db()->prepare('SELECT * FROM rewards WHERE id = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$rewardId]);
        $reward = $stmt->fetch();

        if (!$reward) {
            $message = 'Premio non trovato.';
            $messageType = 'error';
        } elseif ((int) $user['total_points'] < (int) $reward['points_cost']) {
            $message = 'Punti insufficienti per riscattare questo premio.';
            $messageType = 'error';
        } else {
            $stmt = db()->prepare('UPDATE users SET total_points = total_points - ? WHERE id = ?');
            $stmt->execute([$reward['points_cost'], $user['id']]);

            $stmt = db()->prepare('INSERT INTO redemptions (user_id, reward_id, reward_name, points_cost, created_at) VALUES (?, ?, ?, ?, NOW())');
            $stmt->execute([$user['id'], $reward['id'], $reward['reward_name'], $reward['points_cost']]);

            $user = currentUser();
            $message = 'Premio "' . $reward['reward_name'] . '" riscattato con successo! Ti contatteremo per la consegna.';
            $messageType = 'success';
        }
    }
}

$stmt = db()->prepare('SELECT r.*, rw.reward_name as rw_name FROM redemptions r LEFT JOIN rewards rw ON rw.id = r.reward_id WHERE r.user_id = ? ORDER BY r.id DESC LIMIT 20');
$stmt->execute([$user['id']]);
$redemptions = $stmt->fetchAll();
?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Premi · <?php echo h(SITE_NAME); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        body.rewards-page {
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

        .dash-points-badge {
            display: inline-flex;
            align-items: center;
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

        .page-content {
            max-width: 860px;
            margin: 0 auto;
            padding: 48px 24px 80px;
        }

        .page-header {
            margin-bottom: 36px;
        }

        .page-title {
            font-size: 26px;
            font-weight: 700;
            letter-spacing: -0.7px;
            margin: 0 0 6px;
            color: #f5f5f5;
        }

        .page-sub {
            font-size: 14px;
            color: #737373;
            margin: 0;
        }

        .section-label {
            font-size: 13px;
            font-weight: 500;
            color: #525252;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 14px;
        }

        .section-block {
            margin-bottom: 48px;
        }

        .alert-box {
            border-radius: 8px;
            padding: 14px 16px;
            font-size: 14px;
            margin-bottom: 28px;
        }

        .alert-box.success {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.2);
            color: #4ade80;
        }

        .alert-box.error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.25);
            color: #fca5a5;
        }

        .rewards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 14px;
        }

        .reward-card {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .reward-card-name {
            font-size: 14px;
            font-weight: 500;
            color: #d4d4d4;
            line-height: 1.4;
        }

        .reward-card-cost {
            font-size: 24px;
            font-weight: 700;
            color: #4ade80;
            letter-spacing: -0.5px;
        }

        .reward-card-cost span {
            font-size: 13px;
            font-weight: 400;
            color: #525252;
        }

        .reward-card-euro {
            font-size: 12px;
            color: #737373;
            margin-bottom: 4px;
        }

        .btn-redeem-full {
            display: block;
            width: 100%;
            padding: 10px;
            border-radius: 7px;
            font-size: 13px;
            font-weight: 600;
            background: #3b82f6;
            color: #fff;
            border: none;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
            transition: background 0.2s, transform 0.15s;
            margin-top: auto;
        }

        .btn-redeem-full:hover:not(:disabled) {
            background: #2563eb;
            transform: translateY(-1px);
        }

        .btn-redeem-full:disabled {
            background: rgba(255,255,255,0.06);
            color: #525252;
            cursor: not-allowed;
            border: 1px solid rgba(255,255,255,0.06);
        }

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
        }

        .history-table td.name-col { color: #d4d4d4; }

        .history-table td.points-col {
            color: #f87171;
            font-weight: 600;
        }

        .table-wrap { overflow-x: auto; }

        .empty-state {
            text-align: center;
            padding: 32px 0;
            color: #525252;
            font-size: 14px;
        }

        @media (max-width: 640px) {
            .dash-nav { padding: 18px 20px; }
            .page-content { padding: 32px 20px 60px; }
            .rewards-grid { grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); }
        }
    </style>
</head>
<body class="rewards-page">

    <nav class="dash-nav">
        <a href="dashboard.php" class="dash-logo"><?php echo h(SITE_NAME); ?></a>
        <div class="dash-nav-right">
            <span class="dash-points-badge"><?php echo (int) $user['total_points']; ?> pt</span>
            <a class="btn-nav-action" href="dashboard.php">Dashboard</a>
            <a class="btn-nav-action" href="logout.php">Esci</a>
        </div>
    </nav>

    <div class="page-content">

        <div class="page-header">
            <h1 class="page-title">Premi riscattabili</h1>
            <p class="page-sub">Hai <strong style="color:#4ade80"><?php echo (int) $user['total_points']; ?> punti</strong> disponibili. 100 punti = 1€ di valore.</p>
        </div>

        <?php if ($message): ?>
            <div class="alert-box <?php echo $messageType === 'success' ? 'success' : 'error'; ?>">
                <?php echo h($message); ?>
            </div>
        <?php endif; ?>

        <div class="section-block">
            <div class="section-label">Catalogo premi</div>
            <?php if ($rewards): ?>
                <div class="rewards-grid">
                    <?php foreach ($rewards as $reward): ?>
                        <?php
                            $canRedeem = (int) $user['total_points'] >= (int) $reward['points_cost'];
                            $euroValue = number_format((int) $reward['points_cost'] / 100, 0, ',', '.');
                        ?>
                        <div class="reward-card">
                            <div class="reward-card-name"><?php echo h($reward['reward_name']); ?></div>
                            <div class="reward-card-cost"><?php echo (int) $reward['points_cost']; ?> <span>pt</span></div>
                            <div class="reward-card-euro">Valore: €<?php echo $euroValue; ?></div>
                            <form method="post">
                                <input type="hidden" name="reward_id" value="<?php echo (int) $reward['id']; ?>">
                                <button type="submit" class="btn-redeem-full" <?php echo $canRedeem ? '' : 'disabled'; ?>>
                                    <?php echo $canRedeem ? 'Riscatta' : 'Punti insufficienti'; ?>
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="empty-state">Nessun premio disponibile al momento.</p>
            <?php endif; ?>
        </div>

        <?php if ($redemptions): ?>
        <div class="section-block">
            <div class="section-label">Storico riscatti</div>
            <div class="table-wrap">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Premio</th>
                            <th>Punti usati</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($redemptions as $row): ?>
                        <tr>
                            <td><?php echo h(date('d/m/Y', strtotime($row['created_at']))); ?></td>
                            <td class="name-col"><?php echo h($row['reward_name']); ?></td>
                            <td class="points-col">-<?php echo (int) $row['points_cost']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

    </div>

</body>
</html>
