<?php
	session_start();
	require('gui.class.php');

	$gui = new Gui();
	$signedin = false;

	// Logout
	if(isset($_GET['p']) && $_GET['p'] == "logout") {
		$_SESSION = array();
		session_destroy();
	}

	// Login form
	if(isset($_POST['apikey']) && !empty($_POST['apikey'])) {
		if($uid = $gui->signIn($_POST['apikey'])) {
			$_SESSION['uid'] = $uid;
			$_SESSION['apikey'] = $_POST['apikey'];
			header('Location: '.$_SERVER['PHP_SELF']);
			exit;
		} else {
			$msg = 'Wrong ApiKey - Try again';
		}
	}

	// Is user signed and valid?
	if(isset($_SESSION['apikey']) && isset($_SESSION['uid'])) {
		if($_SESSION['uid'] != $gui->signIn($_SESSION['apikey'])) {
			$_SESSION = array();
			session_destroy();
		} else {
			$signedin = true;
		}
	}

	//Get page
	if(!$signedin) {
		$page = 'login';
	} else {
		if(isset($_GET['p']) && !empty($_GET['p'])) {
			switch ($_GET['p']) {
				case 'status':
					$page = 'status';
					break;
				case 'graphs':
					$page = 'graphs';
					break;
				
				default:
					$page = '404';
					http_response_code(404);
					break;
			}
		} else {
			$page = 'status';
		}
	}
?>
<!doctype html>
<html lang="en">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
		<meta name="robots" content="noindex">
		<meta name="author" content="Martin 'kolaCZerk' Kolací">
		<title>evDash</title>

		<link href="css/bootstrap.min.css" rel="stylesheet">
		<link href="css/gui.css" rel="stylesheet">
	</head>
	<body class="text-center">
		<?php if($signedin): ?>
			<nav class="navbar navbar-expand-md navbar-dark bg-dark">
				<a class="navbar-brand" href="#">evDash</a>
				<button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbar" aria-controls="navbar" aria-expanded="false" aria-label="Toggle navigation">
					<span class="navbar-toggler-icon"></span>
				</button>

				<div class="navbar-collapse collapse w-100 order-1 order-md-0 dual-collapse2" id="navbar">
					<ul class="navbar-nav mr-auto">
						<li class="nav-item<?php if($page == 'status'){echo(' active');} ?>">
							<a class="nav-link" href="?p=status">Status</a>
						</li>
						<li class="nav-item<?php if($page == 'graphs'){echo(' active');} ?>">
							<a class="nav-link" href="?p=graphs">Graphs</a>
						</li>
					</ul>

					<ul class="navbar-nav ml-auto">
						<li class="nav-item">
							<a class="nav-link" href="?p=logout">Sign Out</a>
						</li>
					</ul>
				</div>
			</nav>
		<?php endif ?>
			<?php
				if(file_exists('./pages/'.$page.'.php')) {
					require('./pages/'.$page.'.php');
				}
			?>
	</body>
	<script src="https://code.jquery.com/jquery-3.5.1.min.js" crossorigin="anonymous"></script>
	<script>window.jQuery</script>
	<script src="js/bootstrap.bundle.min.js"></script>
</html>