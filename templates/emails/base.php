<?php
/**
 * Email wrapper. Inline styles are required in email clients; this template is email-only output.
 *
 * @var array $args { subject, body, footer, primary }
 *
 * @package ShapeLMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$shapelms_primary = sanitize_hex_color( $args['primary'] );
$shapelms_primary = $shapelms_primary ? $shapelms_primary : '#3b5bdb';
?>
<!doctype html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width">
	<title><?php echo esc_html( $args['subject'] ); ?></title>
</head>
<body style="margin:0;padding:0;background:#f4f5f7;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#1b1f27;">
	<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f5f7;padding:32px 16px;">
		<tr>
			<td align="center">
				<table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:8px;overflow:hidden;">
					<tr>
						<td style="background:<?php echo esc_attr( $shapelms_primary ); ?>;padding:20px 32px;color:#ffffff;font-size:20px;font-weight:700;">
							<?php echo esc_html( get_bloginfo( 'name' ) ); ?>
						</td>
					</tr>
					<tr>
						<td style="padding:32px;font-size:16px;line-height:1.6;">
							<h1 style="margin:0 0 16px;font-size:22px;"><?php echo esc_html( $args['subject'] ); ?></h1>
							<?php echo wp_kses_post( $args['body'] ); ?>
						</td>
					</tr>
					<tr>
						<td style="padding:16px 32px;background:#f9fafb;color:#6b7280;font-size:13px;">
							<?php echo esc_html( $args['footer'] ); ?>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>
