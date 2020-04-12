<?php
	function redirect($url)
	{
		Header("HTTP 302 Found");
		Header("Location: ".$url);
		die();
	}

	include ('private.php');
	include ('lib_rest.php');

	$domain = isset($_REQUEST['domain']) ? $_REQUEST['domain'] : $portal;

	$arScope = array('user');

?>

<!DOCTYPE html>
<html lang="en">

	<head>
		<meta charset="UTF-8">
		<title>Authorization state</title>
	</head>

	<body>
		Current data:<pre>
			<?php
				$tokens=checkRestAuth();
				if (isset($_REQUEST['code'])) {
					$response=requestAccessToken($_REQUEST['code'],$domain);
					//print_r($response);
					fetchResponseTokens($tokens,$response);
				}
				executeREST(
					$portal,
					$RESTmethod,
					$RESTparams,
					$tokens['access']
				);
			?>
		</pre>

		<h2>access_token</h2>
		<?php renderTokenData($tokens['access'],3600); ?>

		<h2>refresh_token</h2>
		<?php renderTokenData($tokens['refresh'],3600*24*28); ?>

		<br/>
		Авторизоваться повторно:<br/>
		<a href="<?= requestCodeUrl($domain) ?>"> Перейти </a>

	</body>

</html>