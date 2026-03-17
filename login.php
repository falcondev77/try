<?php
require_once __DIR__ . '/functions.php';
bootstrapSettings();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    $stmt = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        $error = 'Credenziali non valide.';
    } else {
        $_SESSION['user_id'] = (int) $user['id'];
        header('Location: dashboard.php');
        exit;
    }
}
?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Accedi</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        body.auth-page {
            background: #0a0a0a;
            color: #f5f5f5;
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .auth-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 28px 48px;
        }

        .auth-logo {
            font-size: 18px;
            font-weight: 600;
            letter-spacing: -0.3px;
            color: #f5f5f5;
            text-decoration: none;
        }

        .auth-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 24px 80px;
        }

        .auth-box {
            width: 100%;
            max-width: 420px;
        }

        .auth-heading {
            font-size: 28px;
            font-weight: 700;
            letter-spacing: -0.8px;
            margin: 0 0 6px;
            color: #f5f5f5;
        }

        .auth-sub {
            font-size: 14px;
            color: #737373;
            margin: 0 0 36px;
        }

        .auth-sub a {
            color: #3b82f6;
            text-decoration: none;
        }

        .auth-sub a:hover {
            text-decoration: underline;
        }

        .auth-alert {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.25);
            color: #fca5a5;
            border-radius: 8px;
            padding: 12px 14px;
            font-size: 14px;
            margin-bottom: 24px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: #a3a3a3;
            margin-bottom: 6px;
        }

        .form-group input {
            width: 100%;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 12px 14px;
            font-size: 14px;
            color: #f5f5f5;
            font-family: 'Inter', sans-serif;
            transition: border-color 0.2s;
            box-sizing: border-box;
        }

        .form-group input::placeholder {
            color: #525252;
        }

        .form-group input:focus {
            outline: none;
            border-color: rgba(59, 130, 246, 0.5);
            background: rgba(255, 255, 255, 0.07);
        }

        .btn-auth {
            width: 100%;
            padding: 14px;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            background: #3b82f6;
            color: #fff;
            border: none;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
            letter-spacing: -0.1px;
            margin-top: 8px;
            transition: background 0.2s, transform 0.15s;
        }

        .btn-auth:hover {
            background: #2563eb;
            transform: translateY(-1px);
        }

        .auth-footer {
            text-align: center;
            margin-top: 24px;
            font-size: 14px;
            color: #525252;
        }

        .auth-footer a {
            color: #3b82f6;
            text-decoration: none;
        }

        .auth-footer a:hover {
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            .auth-nav { padding: 20px 24px; }
        }
    </style>
</head>
<body class="auth-page">

    <nav class="auth-nav">
        <a href="index.php" class="auth-logo"><?php echo h(SITE_NAME); ?></a>
    </nav>

    <div class="auth-container">
        <div class="auth-box">
            <h1 class="auth-heading">Bentornato</h1>
            <p class="auth-sub">Non hai un account? <a href="register.php">Registrati gratis</a></p>

            <?php if ($error): ?>
                <div class="auth-alert"><?php echo h($error); ?></div>
            <?php endif; ?>

            <form method="post">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" placeholder="mario@esempio.it" value="<?php echo h($_POST['email'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="La tua password" required>
                </div>
                <button type="submit" class="btn-auth">Accedi</button>
            </form>

            <p class="auth-footer">
                Hai dimenticato la password? Contatta il supporto.
            </p>
        </div>
    </div>

</body>
</html>
