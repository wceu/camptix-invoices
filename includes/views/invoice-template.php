<?php

defined( 'WPINC' ) || die();

/** @var string $invoice_number */
/** @var array $invoice_metas */
/** @var array $invoice_order */
/** @var string $logo */

?>

<html>
	<head>
		<meta charset="UTF-8">
		<link href="http://fonts.googleapis.com/css?family=Open+Sans:300,600,700" rel="stylesheet" type="text/css" />
		<style type="text/css">
			body {
				margin: 1em;
				font-family: 'Open Sans', sans-serif;
				font-size: 15px;
				line-height: 1.5;
				font-weight: 300;
				color: #444;
			}
		</style>
	</head>
	<body>
		<img src="<?php echo esc_url( $logo ); ?>" style="max-width:250px;max-height:200px;">
		<h1>Hello World!</h1>
		<p>To Do</p>
	</body>
</html>
