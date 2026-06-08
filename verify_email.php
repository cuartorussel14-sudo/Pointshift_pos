<?php
require_once __DIR__ . '/config.php';

// Verify email (OTP) page
$message = '';
$message_type = '';
$allow_set_password = false; // control rendering of password set form
$verified_now = false;

// Determine user id from GET or POST so we can load status early
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : (isset($_POST['user_id']) ? intval($_POST['user_id']) : 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	// If this is a password set request
	if (isset($_POST['set_password']) || isset($_POST['new_password'])) {
		$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
		$new_password = isset($_POST['new_password']) ? trim($_POST['new_password']) : '';
		$confirm_password = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';

		if (!$user_id) {
			$message = 'Missing user.';
			$message_type = 'danger';
		} elseif ($new_password === '' || $confirm_password === '') {
			$message = 'Please enter and confirm your new password.';
			$message_type = 'danger';
		} elseif ($new_password !== $confirm_password) {
			$message = 'Passwords do not match.';
			$message_type = 'danger';
		} elseif (strlen($new_password) < 8) {
			$message = 'Password must be at least 8 characters.';
			$message_type = 'danger';
		} else {
			// Ensure the user's email is verified before allowing password set
			$check = $conn->prepare("SELECT email_verified FROM users WHERE id = ? LIMIT 1");
			$check->bind_param('i', $user_id);
			$check->execute();
			$rowc = $check->get_result()->fetch_assoc();
			if (!$rowc) {
				$message = 'User not found.';
				$message_type = 'danger';
			} elseif (empty($rowc['email_verified'])) {
				$message = 'You must verify your email before setting a password.';
				$message_type = 'danger';
			} else {
				$hashed = password_hash($new_password, PASSWORD_DEFAULT);
				$up = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
				$up->bind_param('si', $hashed, $user_id);
				if ($up->execute()) {
					$message = 'Password set successfully. You can now log in.';
					$message_type = 'success';
				} else {
					$message = 'Failed to set password. Please try again.';
					$message_type = 'danger';
				}
			}
		}

	} else {
		// Handle verification code submission
		$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
		$code = isset($_POST['code']) ? trim($_POST['code']) : '';

		if (!$user_id || $code === '') {
			$message = 'Please provide a valid user and code.';
			$message_type = 'danger';
		} else {
			// Fetch user verification data
			$stmt = $conn->prepare("SELECT email_verification_code, email_verification_expires_at, email_verified FROM users WHERE id = ? LIMIT 1");
			$stmt->bind_param('i', $user_id);
			$stmt->execute();
			$row = $stmt->get_result()->fetch_assoc();

			if (!$row) {
				$message = 'User not found.';
				$message_type = 'danger';
			} elseif ($row['email_verified']) {
				$message = 'Email is already verified.';
				$message_type = 'info';
				$allow_set_password = true;
			} elseif ($row['email_verification_code'] !== $code) {
				$message = 'Invalid verification code.';
				$message_type = 'danger';
			} elseif (!empty($row['email_verification_expires_at']) && strtotime($row['email_verification_expires_at']) < time()) {
				$message = 'Verification code has expired. Ask the admin to resend or request a new account.';
				$message_type = 'danger';
			} else {
				$u = $conn->prepare("UPDATE users SET email_verified = 1, email_verification_code = NULL, email_verification_expires_at = NULL, status = 'active' WHERE id = ?");
				$u->bind_param('i', $user_id);
				if ($u->execute()) {
					$message = 'Email verified successfully. Please set your account password below.';
					$message_type = 'success';
					$allow_set_password = true;
					$verified_now = true;
				} else {
					$message = 'Error updating user verification.';
					$message_type = 'danger';
				}
			}
		}
	}
}

// If provided user_id in GET, or from POST, show the form prefilled
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : (isset($user_id) ? intval($user_id) : 0);

// Try to fetch expiry info for UX hint
$code_expires = null;
if ($user_id && isset($conn) && $conn instanceof mysqli) {
	try {
		$s = $conn->prepare("SELECT email_verification_expires_at FROM users WHERE id = ? LIMIT 1");
		$s->bind_param('i', $user_id);
		$s->execute();
		$rr = $s->get_result()->fetch_assoc();
		if ($rr && !empty($rr['email_verification_expires_at'])) {
			$code_expires = $rr['email_verification_expires_at'];
		}
	} catch (Throwable $e) {
		$code_expires = null;
	}
}

// Also check whether the user is already verified so we can enable password set by default
if ($user_id && isset($conn) && $conn instanceof mysqli) {
	try {
		$sv = $conn->prepare("SELECT email_verified FROM users WHERE id = ? LIMIT 1");
		$sv->bind_param('i', $user_id);
		$sv->execute();
		$rv = $sv->get_result()->fetch_assoc();
		if ($rv && !empty($rv['email_verified'])) {
			$allow_set_password = true;
		}
	} catch (Throwable $e) {
		// ignore
	}
}

// Prepare minimal variables used by the layout wrapper
// Render a standalone verification UI with a distinct design
// We'll output a minimal, self-contained page (no site header/footer)
?><!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<title>Verify your email</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
	<style>
		:root{--brand:#d6333f}
		body{min-height:100vh;display:flex;align-items:center;justify-content:center;background:#ffffff;color:#212529}
		.verify-shell{width:100%;max-width:760px;margin:32px;padding:28px;border-radius:16px;background:#ffffff;box-shadow:0 8px 30px rgba(2,6,23,0.08);color:#212529}
		.brand-badge{width:84px;height:84px;border-radius:12px;background:var(--brand);display:flex;align-items:center;justify-content:center;color:white;font-size:2.1rem}
		.muted{color:#6c757d}
		.code-input{letter-spacing:6px;font-size:1.4rem;text-align:center;color:#212529;background:#fff;border:1px solid #ced4da}
		.small-muted{color:#6c757d}
		a.btn-link{color:#212529}
		/* Ensure form controls inside the verify shell are light-themed */
		.verify-shell .form-control{background:#fff;color:#212529;border:1px solid #ced4da}
		.verify-shell .form-control::placeholder{color:#6c757d}

		/* Strong overrides for Chrome and other browsers to ensure typed text is visible */
		.verify-shell input,
		.verify-shell textarea,
		.verify-shell select {
			color: #000 !important;
			background: #fff !important;
			-webkit-text-fill-color: #000 !important;
		}

		/* Autofill styles */
		input:-webkit-autofill,
		textarea:-webkit-autofill,
		select:-webkit-autofill,
		input:-webkit-autofill:focus,
		textarea:-webkit-autofill:focus,
		select:-webkit-autofill:focus {
			-webkit-text-fill-color: #000 !important;
			background-color: #fff !important;
			box-shadow: 0 0 0px 1000px #fff inset !important;
		}
	</style>
</head>
<body>
	<div class="verify-shell">
		<div class="d-flex gap-4 align-items-center mb-3">
			<div class="brand-badge"><i class="fas fa-envelope-open-text"></i></div>
			<div>
				<h2 class="mb-0">Verify your email</h2>
				<div class="muted">Enter the code sent to your email to finish setting up your account.</div>
			</div>
		</div>

		<?php if ($message): ?>
			<div class="alert alert-<?php echo htmlspecialchars($message_type); ?>" role="alert"><?php echo htmlspecialchars($message); ?></div>
		<?php endif; ?>

		<?php if ($code_expires):
			$expires_ts = strtotime($code_expires);
			if ($expires_ts && $expires_ts > time()):
				$mins = ceil(($expires_ts - time()) / 60);
		?>
			<div class="alert alert-info">Your code expires in approximately <?php echo $mins; ?> minute<?php echo $mins>1? 's':''; ?>.</div>
		<?php endif; endif; ?>

		<form method="POST" class="row g-3 mb-3">
			<input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user_id); ?>">
			<div class="col-12">
				<label class="form-label small-muted">Verification Code</label>
				<input type="text" name="code" required maxlength="10" class="form-control form-control-lg code-input" placeholder="______">
			</div>
			<div class="col-12 d-flex justify-content-between align-items-center">
				<div class="small-muted">Didn't receive it? Contact admin at <a href="mailto:<?php echo htmlspecialchars(defined('SMTP_USER') ? SMTP_USER : ''); ?>" class="small-muted"><?php echo htmlspecialchars(defined('SMTP_USER') ? SMTP_USER : ''); ?></a></div>
				<button class="btn btn-light btn-lg" type="submit">Verify</button>
			</div>
		</form>

		<div class="mb-3">
			<hr style="border-color:rgba(255,255,255,0.04)">
			<div class="small-muted mb-2">Set account password (enabled after verification)</div>
			<form method="POST" id="set-password-form" class="row g-2">
				<input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user_id); ?>">
				<div class="col-md-6">
					<div class="input-group">
						<input type="password" name="new_password" id="new_password" class="form-control" placeholder="New password" <?php echo $allow_set_password ? '' : 'disabled'; ?> aria-label="New password">
						<button class="btn btn-outline-secondary toggle-password" type="button" data-target="new_password" aria-label="Toggle new password visibility"><i class="fa fa-eye"></i></button>
					</div>
				</div>
				<div class="col-md-6">
					<div class="input-group">
						<input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Confirm password" <?php echo $allow_set_password ? '' : 'disabled'; ?> aria-label="Confirm password">
						<button class="btn btn-outline-secondary toggle-password" type="button" data-target="confirm_password" aria-label="Toggle confirm password visibility"><i class="fa fa-eye"></i></button>
					</div>
				</div>
				<div class="col-12 d-flex justify-content-end mt-2">
					<button class="btn btn-success" name="set_password" id="set-password-btn" type="submit" <?php echo $allow_set_password ? '' : 'disabled'; ?>>Set Password</button>
				</div>
			</form>
		</div>

		<div class="d-flex justify-content-between align-items-center">
			<a href="login.php" class="btn btn-link">Back to login</a>
			<div class="small-muted">Need help? <a href="mailto:<?php echo htmlspecialchars(defined('SMTP_USER') ? SMTP_USER : ''); ?>" class="small-muted">Contact admin</a></div>
		</div>

		<script>
			(function(){
				var allow = <?php echo $allow_set_password ? 'true' : 'false'; ?>;
				if (allow) {
					var np = document.getElementById('new_password');
					var cp = document.getElementById('confirm_password');
					var btn = document.getElementById('set-password-btn');
					if (np) np.disabled = false;
					if (cp) cp.disabled = false;
					if (btn) btn.disabled = false;
					if (np) np.focus();
				}
			})();

			// Password visibility toggle handlers
			(function(){
				function toggle(e){
					var btn = e.currentTarget;
					var targetId = btn.getAttribute('data-target');
					var input = document.getElementById(targetId);
					if (!input) return;
					if (input.type === 'password') {
						input.type = 'text';
						btn.querySelector('i').classList.remove('fa-eye');
						btn.querySelector('i').classList.add('fa-eye-slash');
					} else {
						input.type = 'password';
						btn.querySelector('i').classList.remove('fa-eye-slash');
						btn.querySelector('i').classList.add('fa-eye');
					}
				}
				var toggles = document.querySelectorAll('.toggle-password');
				toggles.forEach(function(b){ b.addEventListener('click', toggle); });
			})();
		</script>
	</div>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php

